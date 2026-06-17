<?php

declare(strict_types=1);

/**
 * Maps staff roles to admin portal areas (pages and actions).
 */
class RoleAccess
{
    public const PERMISSION_DASHBOARD = 'dashboard';
    public const PERMISSION_INSIGHTS = 'insights';
    public const PERMISSION_COMPANIES = 'companies';
    public const PERMISSION_USERS = 'users';
    public const PERMISSION_CLIENTS = 'clients';
    public const PERMISSION_CASES = 'cases';
    public const PERMISSION_PAYMENTS = 'payments';
    public const PERMISSION_APPOINTMENTS = 'appointments';
    public const PERMISSION_NOTIFICATIONS = 'notifications';
    public const PERMISSION_CHATBOT = 'chatbot';
    /** Same permission key as chatbot (legacy nav label). */
    public const PERMISSION_ASSISTANT = 'chatbot';
    public const PERMISSION_SETTINGS = 'settings';
    public const PERMISSION_PROFILE = 'profile';

    /** @var list<string> */
    public const STAFF_ROLES = ['super_admin', 'admin', 'manager', 'staff', 'viewer'];

    /** POST actions allowed for read-only (viewer) roles. */
    private const READ_ONLY_POST_ACTIONS = [
        'profile-action.php',
        'notification-read.php',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'admin' => [
            self::PERMISSION_DASHBOARD,
            self::PERMISSION_INSIGHTS,
            self::PERMISSION_USERS,
            self::PERMISSION_CLIENTS,
            self::PERMISSION_CASES,
            self::PERMISSION_PAYMENTS,
            self::PERMISSION_APPOINTMENTS,
            self::PERMISSION_NOTIFICATIONS,
            self::PERMISSION_CHATBOT,
            self::PERMISSION_SETTINGS,
            self::PERMISSION_PROFILE,
        ],
        'manager' => [
            self::PERMISSION_DASHBOARD,
            self::PERMISSION_INSIGHTS,
            self::PERMISSION_CLIENTS,
            self::PERMISSION_CASES,
            self::PERMISSION_PAYMENTS,
            self::PERMISSION_APPOINTMENTS,
            self::PERMISSION_NOTIFICATIONS,
            self::PERMISSION_CHATBOT,
            self::PERMISSION_PROFILE,
        ],
        'staff' => [
            self::PERMISSION_DASHBOARD,
            self::PERMISSION_CLIENTS,
            self::PERMISSION_CASES,
            self::PERMISSION_APPOINTMENTS,
            self::PERMISSION_NOTIFICATIONS,
            self::PERMISSION_CHATBOT,
            self::PERMISSION_PROFILE,
        ],
        'viewer' => [
            self::PERMISSION_DASHBOARD,
            self::PERMISSION_CLIENTS,
            self::PERMISSION_CASES,
            self::PERMISSION_PAYMENTS,
            self::PERMISSION_APPOINTMENTS,
            self::PERMISSION_NOTIFICATIONS,
            self::PERMISSION_PROFILE,
        ],
    ];

    /** @var array<string, string> */
    private const PAGE_PERMISSIONS = [
        'dashboard' => self::PERMISSION_DASHBOARD,
        'insights' => self::PERMISSION_INSIGHTS,
        'companies' => self::PERMISSION_COMPANIES,
        'users' => self::PERMISSION_USERS,
        'user-form' => self::PERMISSION_USERS,
        'clients' => self::PERMISSION_CLIENTS,
        'client-form' => self::PERMISSION_CLIENTS,
        'cases' => self::PERMISSION_CASES,
        'case-form' => self::PERMISSION_CASES,
        'case-view' => self::PERMISSION_CASES,
        'payments' => self::PERMISSION_PAYMENTS,
        'appointments' => self::PERMISSION_APPOINTMENTS,
        'notifications' => self::PERMISSION_NOTIFICATIONS,
        'message-view' => self::PERMISSION_NOTIFICATIONS,
        'assistant' => self::PERMISSION_CHATBOT,
        'chatbot' => self::PERMISSION_CHATBOT,
        'settings' => self::PERMISSION_SETTINGS,
    ];

    /** @var array<string, string> */
    private const ACTION_PERMISSIONS = [
        'client-action.php' => self::PERMISSION_CLIENTS,
        'case-action.php' => self::PERMISSION_CASES,
        'document-download.php' => self::PERMISSION_CASES,
        'case-pack-download.php' => self::PERMISSION_CASES,
        'client-letter-preview.php' => self::PERMISSION_CASES,
        'payment-action.php' => self::PERMISSION_PAYMENTS,
        'payment-export.php' => self::PERMISSION_PAYMENTS,
        'receipt-download.php' => self::PERMISSION_PAYMENTS,
        'appointment-action.php' => self::PERMISSION_APPOINTMENTS,
        'appointment-check.php' => self::PERMISSION_APPOINTMENTS,
        'appointment-ics.php' => self::PERMISSION_APPOINTMENTS,
        'notification-action.php' => self::PERMISSION_NOTIFICATIONS,
        'notification-read.php' => self::PERMISSION_NOTIFICATIONS,
        'message-action.php' => self::PERMISSION_NOTIFICATIONS,
        'reminder-run.php' => self::PERMISSION_NOTIFICATIONS,
        'settings-action.php' => self::PERMISSION_SETTINGS,
        'settings-backup.php' => self::PERMISSION_SETTINGS,
        'settings-restore.php' => self::PERMISSION_SETTINGS,
        'settings-test-smtp.php' => self::PERMISSION_SETTINGS,
        'company-logo.php' => self::PERMISSION_SETTINGS,
        'company-favicon.php' => self::PERMISSION_SETTINGS,
        'google-oauth.php' => self::PERMISSION_SETTINGS,
        'google-oauth-callback.php' => self::PERMISSION_SETTINGS,
        'google-oauth-disconnect.php' => self::PERMISSION_SETTINGS,
        'profile-action.php' => self::PERMISSION_PROFILE,
        'user-action.php' => self::PERMISSION_USERS,
        'role-action.php' => self::PERMISSION_SETTINGS,
        'switch-company.php' => self::PERMISSION_COMPANIES,
        'insights-export.php' => self::PERMISSION_INSIGHTS,
        'insights-live.php' => self::PERMISSION_INSIGHTS,
        'insights-report-action.php' => self::PERMISSION_INSIGHTS,
    ];

    /** @var array<string, array{icon: string, label: string, href: string, page: string, permission: string}> */
    public const NAV_ITEMS = [
        'dashboard' => [
            'icon' => 'bi-grid-1x2',
            'label' => 'Dashboard',
            'href' => 'pages/dashboard.php',
            'page' => 'dashboard',
            'permission' => self::PERMISSION_DASHBOARD,
        ],
        'insights' => [
            'icon' => 'bi-bar-chart-line-fill',
            'label' => 'Insights',
            'href' => 'pages/insights.php',
            'page' => 'insights',
            'permission' => self::PERMISSION_INSIGHTS,
        ],
        'clients' => [
            'icon' => 'bi-people',
            'label' => 'Clients',
            'href' => 'pages/clients.php',
            'page' => 'clients',
            'permission' => self::PERMISSION_CLIENTS,
        ],
        'cases' => [
            'icon' => 'bi-briefcase',
            'label' => 'Cases',
            'href' => 'pages/cases.php',
            'page' => 'cases',
            'permission' => self::PERMISSION_CASES,
        ],
        'payments' => [
            'icon' => 'bi-credit-card',
            'label' => 'Payments',
            'href' => 'pages/payments.php',
            'page' => 'payments',
            'permission' => self::PERMISSION_PAYMENTS,
        ],
        'appointments' => [
            'icon' => 'bi-calendar3',
            'label' => 'Appointments',
            'href' => 'pages/appointments.php',
            'page' => 'appointments',
            'permission' => self::PERMISSION_APPOINTMENTS,
        ],
        'notifications' => [
            'icon' => 'bi-bell',
            'label' => 'Notifications',
            'href' => 'pages/notifications.php',
            'page' => 'notifications',
            'permission' => self::PERMISSION_NOTIFICATIONS,
        ],
        'chatbot' => [
            'icon' => 'bi-robot',
            'label' => 'AI Assistant',
            'href' => 'pages/assistant.php',
            'page' => 'assistant',
            'permission' => self::PERMISSION_CHATBOT,
        ],
        'users' => [
            'icon' => 'bi-person-badge',
            'label' => 'Users',
            'href' => 'pages/users.php',
            'page' => 'users',
            'permission' => self::PERMISSION_USERS,
        ],
        'settings' => [
            'icon' => 'bi-gear',
            'label' => 'Settings',
            'href' => 'pages/settings.php',
            'page' => 'settings',
            'permission' => self::PERMISSION_SETTINGS,
        ],
    ];

    public static function isStaffRole(string $role, ?int $companyId = null): bool
    {
        return CompanyRoleService::isStaffRole($role, $companyId);
    }

    public static function resolveCompanyId(): int
    {
        return TenantService::isEnabled() ? TenantService::id() : 1;
    }

    public static function allows(string $role, string $permission, ?int $companyId = null): bool
    {
        if (!self::isStaffRole($role, $companyId ?? self::resolveCompanyId())) {
            return false;
        }

        if ($role === 'super_admin') {
            return true;
        }

        $config = CompanyRoleAccessService::get($companyId ?? self::resolveCompanyId(), $role);
        $granted = $config['permissions'];

        if (in_array('*', $granted, true)) {
            return true;
        }

        return in_array($permission, $granted, true);
    }

    public static function canAccessPage(string $role, string $pageKey, ?int $companyId = null): bool
    {
        $permission = self::PAGE_PERMISSIONS[$pageKey] ?? null;

        if ($permission === null) {
            return self::allows($role, self::PERMISSION_DASHBOARD, $companyId);
        }

        if ($pageKey === 'settings' && $permission === self::PERMISSION_SETTINGS) {
            return self::allows($role, self::PERMISSION_SETTINGS, $companyId)
                || self::allows($role, self::PERMISSION_PROFILE, $companyId);
        }

        return self::allows($role, $permission, $companyId);
    }

    public static function permissionForPage(string $pageKey): ?string
    {
        return self::PAGE_PERMISSIONS[$pageKey] ?? null;
    }

    public static function permissionForAction(string $scriptBasename): ?string
    {
        return self::ACTION_PERMISSIONS[$scriptBasename] ?? null;
    }

    /** @return list<array{icon: string, label: string, href: string, page: string}> */
    public static function navItemsForRole(string $role, ?int $companyId = null): array
    {
        $items = [];
        $companyId = $companyId ?? self::resolveCompanyId();

        foreach (self::NAV_ITEMS as $key => $item) {
            if (!self::allows($role, $item['permission'], $companyId)) {
                continue;
            }
            $items[] = [
                'icon' => $item['icon'],
                'label' => $item['label'],
                'href' => $item['href'],
                'page' => $item['page'],
            ];
        }

        return $items;
    }

    public static function defaultLandingPath(string $role, ?int $companyId = null): string
    {
        foreach (self::navItemsForRole($role, $companyId) as $item) {
            return $item['href'];
        }

        return 'pages/settings.php?tab=profile';
    }

    public static function isReadOnlyRole(string $role, ?int $companyId = null): bool
    {
        if ($role === 'super_admin') {
            return false;
        }

        return CompanyRoleAccessService::get($companyId ?? self::resolveCompanyId(), $role)['read_only'];
    }

    public static function restrictsToAssignedCases(string $role, ?int $companyId = null): bool
    {
        if ($role === 'super_admin') {
            return false;
        }

        return CompanyRoleAccessService::get($companyId ?? self::resolveCompanyId(), $role)['assigned_cases_only'];
    }

    public static function isReadOnlyPostAction(string $scriptBasename): bool
    {
        return in_array($scriptBasename, self::READ_ONLY_POST_ACTIONS, true);
    }

    public static function roleLabel(string $role, ?int $companyId = null): string
    {
        return CompanyRoleService::labelForSlug($role, $companyId);
    }

    /** @return list<string> */
    public static function assignableRoles(string $actorRole, ?int $companyId = null): array
    {
        return CompanyRoleService::assignableSlugsForActor($actorRole, $companyId);
    }

    public static function roleDescription(string $role, ?int $companyId = null): string
    {
        return CompanyRoleService::descriptionForSlug($role, $companyId);
    }

    public static function builtinRoleDescription(string $role): string
    {
        return CompanyRoleService::builtinDescription($role);
    }
}
