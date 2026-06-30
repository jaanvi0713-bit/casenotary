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
require_once __DIR__ . '/CaseChecklistService.php';
require_once __DIR__ . '/CaseDeadlineService.php';
require_once __DIR__ . '/CaseDocumentRequestService.php';
require_once __DIR__ . '/DocumentSummaryService.php';
require_once __DIR__ . '/ClientIntakeService.php';
require_once __DIR__ . '/CaseService.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/FinancialDocumentRenderer.php';
require_once __DIR__ . '/DocumentTemplate.php';
require_once __DIR__ . '/ClientLetterService.php';
require_once __DIR__ . '/InvoiceService.php';
require_once __DIR__ . '/ReceiptService.php';
require_once __DIR__ . '/StripeService.php';
require_once __DIR__ . '/PaymentGatewayService.php';
require_once __DIR__ . '/InsightsService.php';
require_once __DIR__ . '/ClientService.php';
require_once __DIR__ . '/ClientMessageService.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/AppointmentService.php';
require_once __DIR__ . '/GoogleCalendarService.php';
require_once __DIR__ . '/GoogleOAuthService.php';
require_once __DIR__ . '/ReminderService.php';
require_once __DIR__ . '/ProfileService.php';
require_once __DIR__ . '/SettingsService.php';
require_once __DIR__ . '/BackupService.php';
require_once __DIR__ . '/TenantService.php';
require_once __DIR__ . '/CompanyService.php';

require_once __DIR__ . '/AssistantHelpers.php';
require_once __DIR__ . '/AssistantMessageTolerance.php';
require_once __DIR__ . '/AssistantRouter.php';
require_once __DIR__ . '/AssistantDashboard.php';
require_once __DIR__ . '/AssistantActions.php';
require_once __DIR__ . '/AssistantDraftEdit.php';
require_once __DIR__ . '/AssistantAppointmentSchedule.php';
require_once __DIR__ . '/AssistantSearch.php';
require_once __DIR__ . '/AssistantDocuments.php';
require_once __DIR__ . '/AssistantIntake.php';
require_once __DIR__ . '/AssistantClientCreate.php';
require_once __DIR__ . '/AssistantCompliance.php';
require_once __DIR__ . '/AssistantCalculations.php';
require_once __DIR__ . '/AssistantPracticeFaq.php';
require_once __DIR__ . '/AssistantKnowledge.php';
require_once __DIR__ . '/AssistantMessageDrafts.php';
require_once __DIR__ . '/AssistantReminders.php';
require_once __DIR__ . '/AssistantCaseInfo.php';
require_once __DIR__ . '/AssistantBuiltin.php';
require_once __DIR__ . '/ChatbotQueries.php';
require_once __DIR__ . '/AssistantChatStore.php';
require_once __DIR__ . '/AssistantService.php';

// Optional chatbot modules (legacy deployments).
$requireIfExists = static function (string $file): void {
    $path = __DIR__ . '/' . $file;
    if (is_file($path)) {
        require_once $path;
    }
};

$requireIfExists('ChatbotConversation.php');
$requireIfExists('ChatbotChatStore.php');
$requireIfExists('ChatbotScope.php');
$requireIfExists('ChatbotDraft.php');
$requireIfExists('ChatbotCaseContext.php');
$requireIfExists('ChatbotActions.php');
$requireIfExists('ChatbotReports.php');
$requireIfExists('ChatbotDocumentText.php');
$requireIfExists('ChatbotDocuments.php');
$requireIfExists('ChatbotInsights.php');
$requireIfExists('ChatbotCompanyKnowledge.php');
$requireIfExists('ClientChatbotService.php');
$requireIfExists('ChatbotKnowledge.php');
$requireIfExists('ChatbotQueries.php');
$requireIfExists('ChatbotService.php');
