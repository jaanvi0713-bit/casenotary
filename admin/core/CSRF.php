<?php

declare(strict_types=1);

class CSRF
{
    public static function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $name   = $config['security']['csrf_token_name'];
        $token  = self::generateToken();

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    public static function validate(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function verifyRequest(): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        $name   = $config['security']['csrf_token_name'];
        $token  = $_POST[$name] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!self::validate($token)) {
            return false;
        }

        return true;
    }
}
