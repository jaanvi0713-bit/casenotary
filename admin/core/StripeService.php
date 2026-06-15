<?php

declare(strict_types=1);

class StripeService
{
    public static function isConfigured(): bool
    {
        $settings = getCompanySettings();

        return !empty($settings['stripe_public_key']) && !empty($settings['stripe_secret_key']);
    }

    public static function publicKey(): ?string
    {
        $settings = getCompanySettings();

        return $settings['stripe_public_key'] ?? null;
    }

    public static function createCheckoutSession(array $invoice, int $clientId, float $amount): array
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('Stripe is not configured. Add keys in Settings → Payments.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nothing left to pay on this invoice.');
        }

        $currency = strtolower(getCurrencySettings()['code'] ?? 'inr');
        $config   = require __DIR__ . '/../config/config.php';
        $success  = rtrim($config['client_url'], '/') . '/pages/stripe-return.php?session_id={CHECKOUT_SESSION_ID}';
        $cancel   = rtrim($config['client_url'], '/') . '/pages/payments.php?cancelled=1';

        $params = [
            'mode'                                   => 'payment',
            'success_url'                            => $success,
            'cancel_url'                             => $cancel,
            'client_reference_id'                    => (string) $invoice['id'],
            'metadata[invoice_id]'                   => (string) $invoice['id'],
            'metadata[client_id]'                    => (string) $clientId,
            'line_items[0][quantity]'                => 1,
            'line_items[0][price_data][currency]'     => $currency,
            'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
            'line_items[0][price_data][product_data][name]' => 'Invoice ' . ($invoice['invoice_number'] ?? ''),
        ];

        if (!empty($invoice['case_number'])) {
            $params['line_items[0][price_data][product_data][description]'] =
                $invoice['case_number'] . ' — ' . ($invoice['case_title'] ?? $invoice['title'] ?? '');
        }

        return self::request('POST', 'checkout/sessions', $params);
    }

    public static function createPaymentLink(array $invoice, float $amount): string
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('Stripe is not configured. Add keys in Settings → Payments.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Invoice amount must be greater than zero.');
        }

        $currency      = strtolower(getCurrencySettings()['code'] ?? 'gbp');
        $config        = require __DIR__ . '/../config/config.php';
        $redirectUrl   = rtrim($config['client_url'], '/') . '/pages/payments.php?paid=1';
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');
        $caseNumber    = (string) ($invoice['case_number'] ?? '');
        $productName   = 'Invoice ' . $invoiceNumber;
        if ($caseNumber !== '') {
            $productName .= ' — ' . $caseNumber;
        }

        $price = self::request('POST', 'prices', [
            'unit_amount'        => (int) round($amount * 100),
            'currency'           => $currency,
            'product_data[name]' => $productName,
        ]);

        $priceId = (string) ($price['id'] ?? '');
        if ($priceId === '') {
            throw new RuntimeException('Stripe did not return a price ID.');
        }

        $linkParams = [
            'line_items[0][price]'                 => $priceId,
            'line_items[0][quantity]'              => 1,
            'after_completion[type]'               => 'redirect',
            'after_completion[redirect][url]'      => $redirectUrl,
        ];

        if (!empty($invoice['id'])) {
            $linkParams['metadata[invoice_id]'] = (string) $invoice['id'];
        }

        $link = self::request('POST', 'payment_links', $linkParams);
        $url  = (string) ($link['url'] ?? '');

        if ($url === '') {
            throw new RuntimeException('Stripe did not return a payment link URL.');
        }

        return $url;
    }

    public static function retrieveCheckoutSession(string $sessionId): array
    {
        return self::request('GET', 'checkout/sessions/' . urlencode($sessionId));
    }

    private static function request(string $method, string $endpoint, array $params = []): array
    {
        $settings = getCompanySettings();
        $secret   = $settings['stripe_secret_key'] ?? '';

        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
        $ch  = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $secret . ':',
            CURLOPT_TIMEOUT        => 30,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }

        $caBundle = self::caBundlePath();
        if ($caBundle !== null) {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            $options[CURLOPT_CAINFO]         = $caBundle;
        }

        curl_setopt_array($ch, $options);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status === 0) {
            $message = $curlError !== '' ? $curlError : 'Could not reach Stripe. Check your server can connect to api.stripe.com.';
            if (stripos($message, 'SSL certificate') !== false) {
                $message = 'Secure connection to the payment provider failed. Please try bank transfer or contact the office.';
            }
            throw new RuntimeException($message);
        }

        $data = json_decode($body ?: '', true);

        if ($status >= 400 || !is_array($data)) {
            $message = is_array($data) ? ($data['error']['message'] ?? 'Stripe request failed.') : 'Stripe request failed.';
            throw new RuntimeException($message);
        }

        return $data;
    }

    private static function caBundlePath(): ?string
    {
        $candidates = [
            __DIR__ . '/../certs/cacert.pem',
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
        ];

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
