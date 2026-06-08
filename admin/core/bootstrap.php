<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session']['name']);
    session_set_cookie_params([
        'lifetime' => $config['session']['lifetime'],
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    ]);
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CSRF.php';
require_once __DIR__ . '/CompanyRoleService.php';
require_once __DIR__ . '/RoleAccess.php';
require_once __DIR__ . '/CompanyRoleAccessService.php';
require_once __DIR__ . '/NotificationPreferenceService.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/CaseService.php';
require_once __DIR__ . '/DocumentTemplate.php';
require_once __DIR__ . '/ClientLetterService.php';
require_once __DIR__ . '/InvoiceService.php';
require_once __DIR__ . '/ReceiptService.php';
require_once __DIR__ . '/StripeService.php';
require_once __DIR__ . '/ClientService.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/AppointmentService.php';
require_once __DIR__ . '/GoogleCalendarService.php';
require_once __DIR__ . '/GoogleOAuthService.php';
require_once __DIR__ . '/ReminderService.php';
require_once __DIR__ . '/ProfileService.php';
require_once __DIR__ . '/SettingsService.php';
require_once __DIR__ . '/TenantService.php';
require_once __DIR__ . '/CompanyService.php';
require_once __DIR__ . '/ChatbotConversation.php';
require_once __DIR__ . '/ChatbotChatStore.php';
require_once __DIR__ . '/ChatbotKnowledge.php';
require_once __DIR__ . '/ChatbotQueries.php';
require_once __DIR__ . '/ChatbotService.php';
