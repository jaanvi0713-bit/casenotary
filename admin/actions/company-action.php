<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();
Auth::requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request. Please try again.');
    redirect('pages/companies.php');
}

$action    = (string) ($_POST['action'] ?? '');
$companyId = (int) ($_POST['company_id'] ?? 0);

try {
    switch ($action) {
        case 'delete_company':
            $company = CompanyService::getById($companyId);
            $name = trim((string) ($company['name'] ?? 'Company'));
            CompanyService::delete($companyId);
            flash('success', 'Deleted company "' . $name . '" permanently.');
            break;

        default:
            flash('error', 'Unknown action.');
            break;
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/companies.php');
