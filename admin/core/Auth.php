<?php

declare(strict_types=1);

class Auth
{
    public static function attempt(string $email, string $password, string $requiredRole = 'admin'): array
    {
        $user = Database::fetch(
            'SELECT * FROM users WHERE email = ? AND role = ? AND status = ? LIMIT 1',
            [$email, $requiredRole, 'active']
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        self::login($user);
        self::updateLastLogin((int) $user['id']);
        self::logAudit((int) $user['id'], 'login');

        return ['success' => true, 'user' => $user];
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = userFullName($user);
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();
    }

    public static function logout(): void
    {
        if (self::check()) {
            self::logAudit((int) $_SESSION['user_id'], 'logout');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return Database::fetch(
            'SELECT id, email, role, name, avatar, status FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::check() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            header('Location: ' . url('auth/login.php'));
            exit;
        }
    }

    public static function isClient(): bool
    {
        return self::check() && ($_SESSION['user_role'] ?? '') === 'client';
    }

    public static function clientId(): ?int
    {
        if (!self::isClient()) {
            return null;
        }
        $row = Database::fetch('SELECT id FROM clients WHERE user_id = ? LIMIT 1', [self::id()]);
        return $row ? (int) $row['id'] : null;
    }

    public static function requireClient(): void
    {
        if (!self::isClient()) {
            header('Location: ' . clientUrl('auth/login.php'));
            exit;
        }
    }

    public static function guest(): void
    {
        if (self::check()) {
            header('Location: ' . url('pages/dashboard.php'));
            exit;
        }
    }

    private static function updateLastLogin(int $userId): void
    {
        Database::query(
            'UPDATE users SET last_login = NOW(), last_login_at = NOW() WHERE id = ?',
            [$userId]
        );
    }

    private static function logAudit(int $userId, string $action): void
    {
        try {
            Database::insert(
                'INSERT INTO audit_logs (user_id, event, auditable_type, auditable_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $action,
                    'user',
                    $userId,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                ]
            );
        } catch (Throwable $e) {
            // Audit logging is optional — do not block login
        }
    }

    public static function createPasswordReset(string $email): array
    {
        $user = Database::fetch(
            'SELECT id, email, role FROM users WHERE email = ? AND role = ? LIMIT 1',
            [$email, 'admin']
        );

        if (!$user) {
            return ['success' => true, 'message' => 'If that email exists, a reset link has been sent.'];
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        Database::query(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)',
            [$email, hash('sha256', $token), $expiresAt]
        );

        $resetLink = url('auth/reset-password.php?token=' . $token . '&email=' . urlencode($email));

        return [
            'success'    => true,
            'message'    => 'If that email exists, a reset link has been sent.',
            'reset_link' => $resetLink,
        ];
    }

    public static function resetPassword(string $email, string $token, string $newPassword): array
    {
        $hashedToken = hash('sha256', $token);

        $reset = Database::fetch(
            'SELECT * FROM password_resets WHERE email = ? AND token = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$email, $hashedToken]
        );

        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        Database::query('UPDATE users SET password = ? WHERE email = ?', [$hashedPassword, $email]);
        Database::query('UPDATE password_resets SET used = 1 WHERE id = ?', [$reset['id']]);

        return ['success' => true, 'message' => 'Password updated successfully. You can now sign in.'];
    }
}
