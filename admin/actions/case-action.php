<?php
require_once __DIR__ . '/../core/bootstrap.php';

$config = require __DIR__ . '/../config/config.php';
$csrfName = $config['security']['csrf_token_name'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/cases.php');
}

$isAdmin  = Auth::isAdmin();
$isClient = Auth::isClient();

if (!$isAdmin && !$isClient) {
    redirect('auth/login.php');
}

if (!CSRF::verifyRequest()) {
    flash('error', 'Invalid security token. Please try again.');
    redirect('pages/cases.php');
}

$action = $_POST['action'] ?? '';
$caseId = (int) ($_POST['case_id'] ?? $_GET['case_id'] ?? 0);

function redirectCase(int $caseId, string $tab = ''): void
{
    $hash = $tab ? '#' . $tab : '';
    redirect('pages/case-view.php?id=' . $caseId . $hash);
}

function redirectClientCase(int $caseId, string $tab = ''): void
{
    $hash = $tab ? '#' . $tab : '';
    header('Location: ' . clientUrl('pages/case-view.php?id=' . $caseId . $hash));
    exit;
}

try {
    switch ($action) {
        case 'create_case':
            Auth::requireAdmin();
            $id = CaseService::createCase($_POST, Auth::id());
            $caseId = $id;

            $uploadWarning = null;
            if (!empty($_FILES['document']['name'])) {
                $uploadResult = CaseService::uploadDocument($id, $_FILES['document'], Auth::id(), 'admin');
                if (!$uploadResult['success']) {
                    $uploadWarning = $uploadResult['message'];
                }
            }

            $emailResult = CaseService::runCreateWorkflow($id, $_POST, Auth::id());
            $msg         = 'Case created successfully.';
            if ($emailResult['quote_sent']) {
                $msg .= ' Quotation email sent.';
            }
            if ($emailResult['login_sent']) {
                $msg .= ' Portal login email sent.';
            }

            if (!empty($emailResult['error'])) {
                flash('warning', $msg . ' ' . $emailResult['error']);
            } elseif ($uploadWarning) {
                flash('warning', $msg . ' File upload failed: ' . $uploadWarning);
            } else {
                flash('success', $msg);
            }

            redirect('pages/case-view.php?id=' . $id);
            break;

        case 'update_case':
            Auth::requireAdmin();
            if ($caseId <= 0) {
                throw new RuntimeException('Invalid case.');
            }
            CaseService::updateCase($caseId, $_POST);
            flash('success', 'Case updated successfully.');
            redirectCase($caseId);
            break;

        case 'update_status':
            Auth::requireAdmin();
            $newStatus = $_POST['status'] ?? 'pending';
            CaseService::updateStatus($caseId, $newStatus, Auth::id());
            flash('success', 'Case status updated.');
            redirectCase($caseId);
            break;

        case 'upload_document':
            $source = $isClient ? 'client' : 'admin';
            if ($isClient) {
                $clientId = Auth::clientId();
                $case = CaseService::getCaseForClient($caseId, $clientId);
                if (!$case) {
                    throw new RuntimeException('Access denied.');
                }
            } elseif ($caseId <= 0) {
                throw new RuntimeException('Invalid case.');
            }

            $result = CaseService::uploadDocument($caseId, $_FILES['document'] ?? [], Auth::id(), $source);
            flash($result['success'] ? 'success' : 'error', $result['message']);
            $isClient ? redirectClientCase($caseId, 'documents') : redirectCase($caseId, 'documents');
            break;

        case 'add_note':
            Auth::requireAdmin();
            $note = trim($_POST['note'] ?? '');
            if ($note === '') {
                throw new RuntimeException('Note cannot be empty.');
            }
            CaseService::addNote($caseId, Auth::id(), $note, true);
            flash('success', 'Note added.');
            redirectCase($caseId, 'notes');
            break;

        case 'generate_quotation':
            Auth::requireAdmin();
            CaseService::generateQuotation($caseId, $_POST);
            flash('success', 'Quotation generated.');
            redirectCase($caseId, 'quotations');
            break;

        case 'generate_proposal':
            Auth::requireAdmin();
            CaseService::generateProposal($caseId, $_POST);
            flash('success', 'Proposal generated.');
            redirectCase($caseId, 'quotations');
            break;

        case 'generate_invoice':
            Auth::requireAdmin();
            CaseService::generateInvoice($caseId, $_POST);
            flash('success', 'Invoice generated.');
            redirectCase($caseId, 'invoices');
            break;

        case 'record_payment':
            Auth::requireAdmin();
            $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
            CaseService::recordPayment($invoiceId, $_POST, Auth::id());
            flash('success', 'Payment recorded and receipt generated.');
            redirectCase($caseId, 'invoice-payments');
            break;

        case 'delete_case':
            Auth::requireAdmin();
            if ($caseId <= 0) {
                throw new RuntimeException('Invalid case.');
            }
            CaseService::deleteCase($caseId);
            flash('success', 'Case deleted.');
            redirect('pages/cases.php');
            break;

        case 'delete_document':
            Auth::requireAdmin();
            $documentId = (int) ($_POST['document_id'] ?? 0);
            if ($caseId <= 0 || $documentId <= 0) {
                throw new RuntimeException('Invalid document.');
            }
            CaseService::deleteDocument($documentId, $caseId);
            flash('success', 'Document removed.');
            redirectCase($caseId, 'documents');
            break;

        default:
            flash('error', 'Unknown action.');
            redirect('pages/cases.php');
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (str_contains($message, 'SQLSTATE')) {
        flash('error', 'Could not save the case. If this is a new install, run: php admin/sql/migrate_cases.php');
    } else {
        flash('error', $message);
    }
    if ($caseId > 0) {
        $isClient ? redirectClientCase($caseId) : redirectCase($caseId);
    }
    redirect('pages/cases.php');
}
