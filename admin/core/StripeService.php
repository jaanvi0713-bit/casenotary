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

        curl_setopt_array($ch, $options);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body ?: '', true);

        if ($status >= 400 || !is_array($data)) {
            $message = is_array($data) ? ($data['error']['message'] ?? 'Stripe request failed.') : 'Stripe request failed.';
            throw new RuntimeException($message);
        }

        return $data;
    }
}
