<?php

declare(strict_types=1);

class GoogleOAuthService
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/calendar.events';

    public static function isConfigured(): bool
    {
        $settings = getCompanySettings();

        return !empty($settings['google_client_id']) && !empty($settings['google_client_secret']);
    }

    public static function isConnected(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $settings = getCompanySettings();

        return !empty($settings['google_refresh_token']) || !empty($settings['google_access_token']);
    }

    public static function getAuthUrl(): string
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('Add Google Client ID and Secret in Settings → Calendar first.');
        }

        $settings = getCompanySettings();
        $params   = [
            'client_id'     => $settings['google_client_id'],
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => CSRF::generateToken(),
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public static function handleCallback(string $code, string $state): void
    {
        if (!CSRF::validate($state)) {
            throw new RuntimeException('Invalid OAuth state. Please try again.');
        }

        if (!self::isConfigured()) {
            throw new RuntimeException('Google Calendar is not configured.');
        }

        $settings = getCompanySettings();
        $payload  = [
            'code'          => $code,
            'client_id'     => $settings['google_client_id'],
            'client_secret' => $settings['google_client_secret'],
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
        ];

        $response = self::httpPost(self::TOKEN_URL, $payload);
        self::storeTokens($response);
    }

    public static function disconnect(): void
    {
        $settings = getCompanySettings();
        $id       = (int) ($settings['id'] ?? 0);

        if ($id <= 0) {
            return;
        }

        try {
            Database::query(
                'UPDATE company_settings SET google_access_token = NULL, google_refresh_token = NULL, google_token_expires = NULL, updated_at = NOW() WHERE id = ?',
                [$id]
            );
        } catch (Throwable $e) {
            // optional columns
        }

        SettingsService::clearCache();
    }

    public static function getValidAccessToken(): ?string
    {
        if (!self::isConnected()) {
            return null;
        }

        $settings = getCompanySettings();
        $expires  = (int) ($settings['google_token_expires'] ?? 0);

        if (!empty($settings['google_access_token']) && $expires > (time() + 60)) {
            return $settings['google_access_token'];
        }

        if (empty($settings['google_refresh_token'])) {
            return $settings['google_access_token'] ?? null;
        }

        $payload = [
            'client_id'     => $settings['google_client_id'],
            'client_secret' => $settings['google_client_secret'],
            'refresh_token' => $settings['google_refresh_token'],
            'grant_type'    => 'refresh_token',
        ];

        $response = self::httpPost(self::TOKEN_URL, $payload);
        self::storeTokens($response, keepRefreshToken: true);

        $settings = getCompanySettings();

        return $settings['google_access_token'] ?? null;
    }

    public static function redirectUri(): string
    {
        return url('actions/google-oauth-callback.php');
    }

    private static function storeTokens(array $response, bool $keepRefreshToken = false): void
    {
        $settings = getCompanySettings();
        $id       = (int) ($settings['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Company settings record not found.');
        }

        $accessToken  = $response['access_token'] ?? null;
        $refreshToken = $response['refresh_token'] ?? null;
        $expiresIn    = (int) ($response['expires_in'] ?? 3600);
        $expiresAt    = time() + max(60, $expiresIn);

        if ($keepRefreshToken && !$refreshToken) {
            $refreshToken = $settings['google_refresh_token'] ?? null;
        }

        Database::query(
            'UPDATE company_settings SET google_access_token = ?, google_refresh_token = ?, google_token_expires = ?, updated_at = NOW() WHERE id = ?',
            [$accessToken, $refreshToken, $expiresAt, $id]
        );

        SettingsService::clearCache();
    }

    private static function httpPost(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $body, true);

        if ($code >= 400 || !is_array($data)) {
            $message = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'OAuth request failed.') : 'OAuth request failed.';
            throw new RuntimeException((string) $message);
        }

        return $data;
    }
}
