<?php

declare(strict_types=1);

class Auth
{
    /** @return list<string> */
    public static function staffRoles(?int $companyId = null): array
    {
        $roles = ['super_admin'];
        $companyId = $companyId ?? (TenantService::isEnabled() ? TenantService::id() : 1);

        return array_values(array_unique(array_merge($roles, CompanyRoleService::activeSlugsForCompany($companyId))));
    }

    public static function role(): string
    {
        return (string) ($_SESSION['user_role'] ?? '');
    }

    public static function can(string $permission): bool
    {
        return self::isStaff() && RoleAccess::allows(self::role(), $permission);
    }

    public static function requirePermission(string $permission): void
    {
        self::requireAdmin();

        if (!self::can($permission)) {
            flash('error', 'You do not have permission to access that area.');
            redirect(self::defaultLandingPath());
        }
    }

    public static function requirePage(string $pageKey): void
    {
        self::requireAdmin();

        if (!RoleAccess::canAccessPage(self::role(), $pageKey)) {
            flash('error', 'You do not have permission to access that page.');
            redirect(self::defaultLandingPath());
        }
    }

    public static function isReadOnly(): bool
    {
        return self::isStaff() && RoleAccess::isReadOnlyRole(self::role());
    }

    public static function restrictsToAssignedCases(): bool
    {
        return self::isStaff() && RoleAccess::restrictsToAssignedCases(self::role());
    }

    public static function canManage(string $permission): bool
    {
        return self::can($permission) && !self::isReadOnly();
    }

    public static function guardAction(): void
    {
        self::requireAdmin();

        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $permission = RoleAccess::permissionForAction($script);

        if ($permission !== null && !self::can($permission)) {
            flash('error', 'You do not have permission to perform that action.');
            redirect(self::defaultLandingPath());
        }

        if (
            self::isReadOnly()
            && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && !RoleAccess::isReadOnlyPostAction($script)
        ) {
            flash('error', 'Your account is read-only. You cannot save or change data.');
            redirect(self::defaultLandingPath());
        }
    }

    public static function defaultLandingPath(): string
    {
        return RoleAccess::defaultLandingPath(self::role());
    }

    public static function attempt(string $email, string $password, string $requiredRole = 'admin', int $loginCompanyId = 0): array
    {
        if ($requiredRole === 'admin') {
            $user = Database::fetch(
                'SELECT * FROM users WHERE email = ? LIMIT 1',
                [$email]
            );
        } elseif (
            $requiredRole === 'client'
            && $loginCompanyId > 0
            && TenantService::isEnabled()
            && Database::columnExists('users', 'company_id')
        ) {
            $user = Database::fetch(
                'SELECT * FROM users WHERE email = ? AND role = ? AND company_id = ? LIMIT 1',
                [$email, $requiredRole, $loginCompanyId]
            );
        } else {
            $user = Database::fetch(
                'SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1',
                [$email, $requiredRole]
            );
        }

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (trim((string) ($user['role'] ?? '')) === '') {
            Database::query(
                "UPDATE users SET role = 'admin' WHERE id = ? AND (role IS NULL OR role = '')",
                [(int) $user['id']]
            );
            $user['role'] = 'admin';
        }

        if (!self::isUserActive($user)) {
            return ['success' => false, 'message' => 'This account is inactive. Contact support.'];
        }

        if ($requiredRole === 'admin') {
            $userCompanyId = (int) ($user['company_id'] ?? 0);
            if (!RoleAccess::isStaffRole((string) ($user['role'] ?? ''), $userCompanyId > 0 ? $userCompanyId : null)) {
                return ['success' => false, 'message' => 'Invalid email or password.'];
            }
        }

        if ($requiredRole === 'client' && !self::ensureClientProfileLink($user)) {
            return ['success' => false, 'message' => 'Client profile not found for this account.'];
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

        TenantService::resolveOnLogin($user);

        if (class_exists(AssistantService::class, false)) {
            AssistantService::resetSessionForLogin();
        } else {
            unset(
                $_SESSION['assistant_messages'],
                $_SESSION['assistant_conversation_id'],
                $_SESSION['assistant_session_user_id']
            );
        }
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

        if (Database::columnExists('users', 'name')) {
            return Database::fetch(
                'SELECT id, email, role, name, avatar, status FROM users WHERE id = ?',
                [$_SESSION['user_id']]
            );
        }

        return Database::fetch(
            'SELECT id, email, role, first_name, last_name, avatar, status FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    public static function isStaff(): bool
    {
        if (!self::check()) {
            return false;
        }

        $companyId = TenantService::isEnabled() ? TenantService::id() : (int) ($_SESSION['company_id'] ?? 1);

        return RoleAccess::isStaffRole((string) ($_SESSION['user_role'] ?? ''), $companyId);
    }

    /** Admin portal access (admin and super_admin). */
    public static function isAdmin(): bool
    {
        return self::isStaff();
    }

    public static function isSuperAdmin(): bool
    {
        return self::check() && ($_SESSION['user_role'] ?? '') === 'super_admin';
    }

    public static function requireAdmin(): void
    {
        if (!self::isStaff()) {
            header('Location: ' . url('auth/login.php'));
            exit;
        }
    }

    public static function requireSuperAdmin(): void
    {
        if (!self::isSuperAdmin()) {
            flash('error', 'Super admin access required.');
            redirect('pages/dashboard.php');
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

        $userId = self::id();
        if (!$userId) {
            return null;
        }

        $client = Database::fetch('SELECT id FROM clients WHERE user_id = ? LIMIT 1', [$userId]);
        if ($client) {
            return (int) $client['id'];
        }

        $user = Database::fetch('SELECT email, client_id FROM users WHERE id = ?', [$userId]);

        if (!empty($user['client_id']) && Database::columnExists('users', 'client_id')) {
            return (int) $user['client_id'];
        }

        $email = trim($user['email'] ?? ($_SESSION['user_email'] ?? ''));
        if ($email === '' || !Database::columnExists('clients', 'email')) {
            return null;
        }

        $client = Database::fetch('SELECT id, user_id FROM clients WHERE email = ? LIMIT 1', [$email]);
        if (!$client) {
            return null;
        }

        if (empty($client['user_id'])) {
            Database::query('UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?', [$userId, $client['id']]);
        }

        try {
            Database::query('UPDATE users SET client_id = ? WHERE id = ?', [(int) $client['id'], $userId]);
        } catch (Throwable $e) {
            // optional column
        }

        return (int) $client['id'];
    }

    public static function requireClient(): void
    {
        if (!self::isClient()) {
            header('Location: ' . adminUrl('auth/login.php?portal=client'));
            exit;
        }
    }

    public static function guest(?string $portal = null): void
    {
        if (!self::check()) {
            return;
        }

        if (self::isClient()) {
            header('Location: ' . clientUrl('pages/dashboard.php'));
            exit;
        }

        header('Location: ' . url('pages/dashboard.php'));
        exit;
    }

    private static function isUserActive(array $user): bool
    {
        $status = $user['status'] ?? 'active';

        if ($status !== 'active') {
            return false;
        }

        if (array_key_exists('is_active', $user) && $user['is_active'] !== null && (int) $user['is_active'] !== 1) {
            return false;
        }

        return true;
    }

    private static function ensureClientProfileLink(array $user): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $email  = $user['email'] ?? '';

        if ($userId <= 0) {
            return false;
        }

        $client = Database::fetch('SELECT id, user_id FROM clients WHERE user_id = ? LIMIT 1', [$userId]);

        if ($client) {
            return true;
        }

        $client = Database::fetch('SELECT id, user_id FROM clients WHERE email = ? LIMIT 1', [$email]);

        if (!$client) {
            return false;
        }

        if (empty($client['user_id'])) {
            Database::query('UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?', [$userId, $client['id']]);
        }

        try {
            Database::query('UPDATE users SET client_id = ? WHERE id = ?', [(int) $client['id'], $userId]);
        } catch (Throwable $e) {
            // optional column
        }

        return true;
    }

    private static function updateLastLogin(int $userId): void
    {
        Database::query(
            'UPDATE users SET last_login = NOW() WHERE id = ?',
            [$userId]
        );
    }

    private static function logAudit(int $userId, string $action): void
    {
        try {
            Database::insert(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
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
        $user = Database::fetch('SELECT id, email, role, company_id FROM users WHERE email = ? LIMIT 1', [$email]);

        if (
            !$user
            || !RoleAccess::isStaffRole(
                (string) ($user['role'] ?? ''),
                (int) ($user['company_id'] ?? 0) > 0 ? (int) $user['company_id'] : null
            )
        ) {
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

        $strengthError = passwordStrengthError($newPassword);
        if ($strengthError !== null) {
            return ['success' => false, 'message' => $strengthError];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        Database::query('UPDATE users SET password = ? WHERE email = ?', [$hashedPassword, $email]);
        Database::query('UPDATE password_resets SET used = 1 WHERE id = ?', [$reset['id']]);

        return ['success' => true, 'message' => 'Password updated successfully. You can now sign in.'];
    }
}
