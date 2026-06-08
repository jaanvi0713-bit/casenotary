<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('cases');

$caseId = (int) ($_GET['id'] ?? 0);
$workspace = CaseService::getWorkspace($caseId);

if (!$workspace) {
    flash('error', 'Case not found.');
    redirect('pages/cases.php');
}

$case         = $workspace['case'];
$caseBilling  = CaseService::getCaseBilling($case);
$billingTotals = $caseBilling['totals'];
$invNet          = (float) ($billingTotals['non_vat_net_subtotal'] ?? $billingTotals['non_vat_subtotal'])
    + (float) $billingTotals['vat_net_subtotal'];
$invoicePrefillItems = CaseService::billingToInvoiceLineItems($caseBilling);
if ($invoicePrefillItems === []) {
    $invoicePrefillItems = [[
        'description' => $case['service_type'] ?? 'Notary service',
        'quantity'    => 1,
        'unit_price'  => (float) $case['service_fee'],
        'line_total'  => (float) $case['service_fee'],
    ]];
}
$pageTitle  = $case['case_number'];
$pageSubtitle = $case['title'];
$allowedStatuses = CaseService::getAllowedStatuses($case['status']);
$clientLetterPath   = CaseService::getClientLetterRelativePath($caseId);
$clientLetterPaths  = ClientLetterService::getGeneratedLetterPaths($caseId);
$letterSections     = ClientLetterService::getSectionsForCase($caseId);
$letterLabels       = ClientLetterService::sectionLabels(); // hidden fields for standard template
$letterIsPdf        = $clientLetterPaths['pdf'] !== null;
$savedClientLetters = $workspace['client_letters'] ?? [];
$currentSavedLetter = ClientLetterService::getCurrentSavedLetter($caseId);
$hasGeneratedDraft  = $clientLetterPaths['html'] !== null || $clientLetterPaths['pdf'] !== null;
$csrfToken          = CSRF::generateToken();
$csrfFieldName      = (require __DIR__ . '/../config/config.php')['security']['csrf_token_name'];

$successMsg = flash('success');
$errorMsg   = flash('error');

$allActivity       = $workspace['activity'] ?? [];
$activityPerPage   = 10;
$activityPage      = requestPageNumber('activity_page');
$totalActivity     = count($allActivity);
$totalActivityPages = max(1, (int) ceil($totalActivity / $activityPerPage));
if ($activityPage > $totalActivityPages) {
    $activityPage = $totalActivityPages;
}
$pagedActivity = array_slice(
    $allActivity,
    paginationOffset($activityPage, $activityPerPage),
    $activityPerPage
);
$activityShowingFrom = $totalActivity > 0 ? paginationOffset($activityPage, $activityPerPage) + 1 : 0;
$activityShowingTo   = min($totalActivity, $activityPage * $activityPerPage);
$canEditCases        = Auth::canManage(RoleAccess::PERMISSION_CASES);

require __DIR__ . '/../includes/header.php';
?>

<link href="<?= asset('css/case-workspace.css') ?>" rel="stylesheet">

<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$canEditCases): ?>
    <div class="alert alert-info mb-3"><i class="bi bi-eye me-2"></i>Read-only access — you can view this case but cannot make changes.</div>
<?php endif; ?>

<div class="case-workspace<?= $canEditCases ? '' : ' case-workspace--view-only' ?>">
    <!-- Case header -->
    <div class="case-workspace-header">
        <div class="case-workspace-header-left">
            <a href="<?= url('pages/cases.php') ?>" class="btn btn-primary btn-sm case-back-btn"><i class="bi bi-arrow-left"></i> Cases</a>
            <div class="case-workspace-title-row">
                <h1 class="case-workspace-title"><?= e($case['case_number']) ?></h1>
                <?= statusBadge($case['status']) ?>
                <?= priorityBadge($case['priority']) ?>
            </div>
            <p class="case-workspace-subtitle"><?= e($case['title']) ?></p>
        </div>
        <?php if ($canEditCases): ?>
        <div class="case-workspace-actions">
            <form method="post" action="<?= url('actions/case-action.php') ?>" class="case-status-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                <label class="case-status-label" for="caseStatusSelect">Status</label>
                <select id="caseStatusSelect" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= $st ?>" <?= $case['status'] === $st ? 'selected' : '' ?>>
                            <?= CaseService::statusLabel($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                $nextStatuses = array_values(array_filter(
                    CaseService::STATUS_TRANSITIONS[$case['status']] ?? [],
                    fn($st) => $st !== $case['status']
                ));
                if ($nextStatuses !== []):
                ?>
                <small class="case-status-hint">Next: <?= e(implode(', ', array_map([CaseService::class, 'statusLabel'], $nextStatuses))) ?></small>
                <?php endif; ?>
            </form>
            <a href="<?= url('pages/case-form.php?id=' . $caseId) ?>" class="btn btn-soft btn-sm">Edit</a>
            <div class="dropdown">
                <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Quick Actions</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalInvoice"><i class="bi bi-receipt me-2"></i>Generate Invoice</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalQuotation"><i class="bi bi-file-earmark-text me-2"></i>Generate Quotation</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalProposal"><i class="bi bi-file-text me-2"></i>Generate Proposal</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#documents" data-case-tab="documents"><i class="bi bi-upload me-2"></i>Upload Document</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= url('actions/case-action.php') ?>" onsubmit="return confirm('Delete this case permanently?');">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="delete_case">
                            <input type="hidden" name="case_id" value="<?= $caseId ?>">
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete Case</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs case-tabs" id="caseTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">Overview</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents" type="button">Documents</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#quotations" type="button">Quotations</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#invoices" type="button">Invoices</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#invoice-payments" type="button">Invoice & Payments</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#notes" type="button">Notes</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity" type="button">Activity</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#client-letter" type="button">Client Letter</button></li>
    </ul>

    <div class="tab-content case-tab-content">
        <!-- Overview -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row g-3 align-items-start">
                <div class="col-lg-8">
                    <div class="case-panel">
                        <h3 class="case-panel-title">Case Details</h3>
                        <div class="case-detail-grid case-detail-grid--overview">
                            <div class="case-detail-item">
                                <span class="case-detail-label">Client</span>
                                <strong><?= e(clientFullName($case)) ?></strong>
                                <?php if ($case['company_name']): ?><small class="d-block text-muted"><?= e($case['company_name']) ?></small><?php endif; ?>
                            </div>
                            <div class="case-detail-item">
                                <span class="case-detail-label">Email</span>
                                <strong><?= e($case['email'] ?? '—') ?></strong>
                            </div>
                            <div class="case-detail-item">
                                <span class="case-detail-label">Assigned admin</span>
                                <strong><?= e($case['admin_name'] ?? 'Unassigned') ?></strong>
                            </div>
                            <div class="case-detail-item">
                                <span class="case-detail-label">Deadline</span>
                                <strong><?= formatDate($case['deadline']) ?></strong>
                            </div>
                            <div class="case-detail-item case-detail-item--billing">
                                <span class="case-detail-label">Services &amp; fees</span>
                                <?= CaseService::formatCaseBillingOverviewHtml($case) ?>
                            </div>
                            <div class="case-detail-item">
                                <span class="case-detail-label">Created</span>
                                <strong><?= formatDateTime($case['created_at']) ?></strong>
                            </div>
                            <div class="case-detail-item">
                                <span class="case-detail-label">Last updated</span>
                                <strong><?= formatDateTime($case['updated_at']) ?></strong>
                            </div>
                        </div>
                        <?php if (!empty($case['description'])): ?>
                            <div class="case-description mt-3">
                                <span class="case-detail-label">Description</span>
                                <p><?= nl2br(e($case['description'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($case['client_instructions'])): ?>
                            <div class="case-description mt-3">
                                <span class="case-detail-label">Client Instructions</span>
                                <p><?= nl2br(e($case['client_instructions'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="case-panel">
                        <h3 class="case-panel-title">Summary</h3>
                        <ul class="case-summary-list">
                            <li><i class="bi bi-file-earmark"></i> <?= count($workspace['documents']) ?> Documents</li>
                            <li><i class="bi bi-receipt"></i> <?= count($workspace['invoices']) ?> Invoices</li>
                            <li><i class="bi bi-cash-coin"></i> <?= count($workspace['payments']) ?> Payments</li>
                            <li><i class="bi bi-file-text"></i> <?= count($workspace['proposals']) + count($workspace['quotations']) ?> Quotes & Proposals</li>
                        </ul>
                    </div>
                    <div class="case-panel mt-3">
                        <h3 class="case-panel-title">Recent Activity</h3>
                        <div class="case-mini-activity">
                            <?php foreach (array_slice($workspace['activity'], 0, 5) as $ev): ?>
                                <div class="case-mini-activity-item">
                                    <i class="bi <?= caseActivityIcon($ev['type']) ?>"></i>
                                    <div>
                                        <strong><?= e($ev['title']) ?></strong>
                                        <?php if (!empty($ev['detail'])): ?><small class="d-block text-muted"><?= e($ev['detail']) ?></small><?php endif; ?>
                                        <small><?= timeAgo($ev['time']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents -->
        <div class="tab-pane fade" id="documents">
            <div class="case-panel">
                <div class="case-panel-header">
                    <h3 class="case-panel-title mb-0">Documents</h3>
                    <form method="post" action="<?= url('actions/case-action.php') ?>" enctype="multipart/form-data" class="case-upload-form">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="upload_document">
                        <input type="hidden" name="case_id" value="<?= $caseId ?>">
                        <input type="file" name="document" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip" required>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Upload</button>
                    </form>
                </div>
                <p class="case-panel-hint">PDF, DOC, DOCX, JPG, PNG, ZIP — max 10MB</p>
                <?php if (!empty($workspace['documents'])): ?>
                <div class="case-toolbar">
                    <div class="case-toolbar-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control form-control-sm case-filter-input" data-filter-target="#documentsTable tbody tr" placeholder="Search documents...">
                    </div>
                    <select class="form-select form-select-sm case-filter-select" data-filter-target="#documentsTable tbody tr" data-filter-attr="data-source">
                        <option value="">All sources</option>
                        <option value="admin">Admin</option>
                        <option value="client">Client</option>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (empty($workspace['documents'])): ?>
                    <div class="empty-state py-4"><i class="bi bi-folder2-open"></i><p>No documents yet.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table mb-0" id="documentsTable">
                            <thead><tr><th>File</th><th>Source</th><th>Uploaded By</th><th>Date</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($workspace['documents'] as $doc): ?>
                                    <tr data-source="<?= e($doc['upload_source'] ?? 'admin') ?>">
                                        <td><span class="table-primary"><?= e($doc['original_name'] ?? $doc['file_name']) ?></span><small class="d-block text-muted"><?= strtoupper(e($doc['file_type'] ?? '')) ?> · <?= number_format(($doc['file_size'] ?? 0) / 1024, 1) ?> KB</small></td>
                                        <td><span class="status-badge badge-<?= ($doc['upload_source'] ?? 'admin') === 'client' ? 'scheduled' : 'default' ?>"><?= ucfirst($doc['upload_source'] ?? 'admin') ?></span></td>
                                        <td><?= e($doc['uploader_name'] ?? 'System') ?></td>
                                        <td class="text-muted"><?= formatDateTime($doc['created_at']) ?></td>
                                        <td>
                                            <a href="<?= url('actions/document-download.php?id=' . $doc['id']) ?>" class="btn btn-soft btn-sm"><i class="bi bi-download"></i></a>
                                            <form method="post" action="<?= url('actions/case-action.php') ?>" class="d-inline" onsubmit="return confirm('Remove this document?');">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="delete_document">
                                                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                                <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                                <button type="submit" class="btn btn-soft-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quotations & Proposals -->
        <div class="tab-pane fade" id="quotations">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="case-panel">
                        <div class="case-panel-header">
                            <h3 class="case-panel-title mb-0">Quotations</h3>
                            <button class="btn btn-soft btn-sm" data-bs-toggle="modal" data-bs-target="#modalQuotation"><i class="bi bi-plus"></i> New</button>
                        </div>
                        <?php if (empty($workspace['quotations'])): ?>
                            <p class="text-muted small py-3 mb-0">No quotations yet.</p>
                        <?php else: ?>
                            <ul class="case-doc-list">
                                <?php foreach ($workspace['quotations'] as $q): ?>
                                    <li>
                                        <div><strong><?= e($q['quotation_number']) ?></strong><small><?= formatCurrency((float) $q['total']) ?> · <?= formatDate($q['created_at']) ?></small></div>
                                        <?php if (!empty($q['pdf_path'])): ?>
                                            <a href="<?= url('actions/document-download.php?path=' . urlencode($q['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="case-panel">
                        <div class="case-panel-header">
                            <h3 class="case-panel-title mb-0">Proposals</h3>
                            <button class="btn btn-soft btn-sm" data-bs-toggle="modal" data-bs-target="#modalProposal"><i class="bi bi-plus"></i> New</button>
                        </div>
                        <?php if (empty($workspace['proposals'])): ?>
                            <p class="text-muted small py-3 mb-0">No proposals yet.</p>
                        <?php else: ?>
                            <ul class="case-doc-list">
                                <?php foreach ($workspace['proposals'] as $p): ?>
                                    <li>
                                        <div><strong><?= e($p['proposal_number']) ?></strong><small><?= formatCurrency((float) ($p['amount'] ?? $p['total'] ?? 0)) ?> · <?= formatDate($p['created_at']) ?></small></div>
                                        <?php if (!empty($p['pdf_path'])): ?>
                                            <a href="<?= url('actions/document-download.php?path=' . urlencode($p['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices -->
        <div class="tab-pane fade" id="invoices">
            <div class="case-panel">
                <div class="case-panel-header">
                    <h3 class="case-panel-title mb-0">Invoices</h3>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalInvoice"><i class="bi bi-plus"></i> Generate Invoice</button>
                </div>
                <?php if (!empty($workspace['invoices'])): ?>
                <div class="case-toolbar">
                    <div class="case-toolbar-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control form-control-sm case-filter-input" data-filter-target="#invoicesTable tbody tr" placeholder="Search invoices...">
                    </div>
                    <select class="form-select form-select-sm case-filter-select" data-filter-target="#invoicesTable tbody tr" data-filter-attr="data-status">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="partially_paid">Partially Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (empty($workspace['invoices'])): ?>
                    <div class="empty-state py-4"><i class="bi bi-receipt"></i><p>No invoices yet.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table mb-0" id="invoicesTable">
                            <thead><tr><th>Invoice #</th><th>Amount</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($workspace['invoices'] as $inv): ?>
                                    <?php $invStatus = $inv['payment_status'] ?? $inv['status'] ?? 'pending'; ?>
                                    <tr data-status="<?= e($invStatus) ?>">
                                        <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                                        <td><?= formatCurrency((float) $inv['total']) ?></td>
                                        <td><?= formatDate($inv['due_date']) ?></td>
                                        <td><?= statusBadge($invStatus) ?></td>
                                        <td class="text-nowrap">
                                            <?php if (!empty($inv['pdf_path'])): ?>
                                                <a href="<?= url('actions/document-download.php?path=' . urlencode($inv['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank" rel="noopener"><i class="bi bi-receipt"></i> View</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice & Payments -->
        <div class="tab-pane fade" id="invoice-payments">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="case-panel">
                        <h3 class="case-panel-title">Payment History</h3>
                        <?php if (empty($workspace['payments'])): ?>
                            <p class="text-muted small py-3">No payments recorded.</p>
                        <?php else: ?>
                            <div class="case-toolbar mb-2">
                                <div class="case-toolbar-search">
                                    <i class="bi bi-search"></i>
                                    <input type="search" class="form-control form-control-sm case-filter-input" data-filter-target="#paymentsTable tbody tr" placeholder="Search payments...">
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table saas-table mb-0" id="paymentsTable">
                                    <thead><tr><th>Invoice</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($workspace['payments'] as $pay): ?>
                                            <tr>
                                                <td><?= e($pay['invoice_number']) ?></td>
                                                <td><strong><?= formatCurrency((float) $pay['amount']) ?></strong></td>
                                    <td><?= paymentMethodBadge($pay['payment_method'] ?? 'other') ?></td>
                                    <td><?= paymentStatusBadge($pay['payment_status'] ?? paymentStatusValue($pay)) ?></td>
                                                <td class="text-muted"><?= formatDateTime($pay['paid_at'] ?? $pay['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="case-panel">
                        <h3 class="case-panel-title">Receipts</h3>
                        <?php if (empty($workspace['receipts'])): ?>
                            <p class="text-muted small py-3">Receipts appear after payments.</p>
                        <?php else: ?>
                            <ul class="case-doc-list">
                                <?php foreach ($workspace['receipts'] as $r): ?>
                                    <li>
                                        <div><strong><?= e($r['receipt_number']) ?></strong><small><?= formatCurrency((float) ($r['amount'] ?? $r['payment_amount'] ?? 0)) ?></small></div>
                                        <a href="<?= url('actions/receipt-download.php?id=' . (int) $r['id']) ?>" class="btn btn-soft btn-sm" target="_blank"><i class="bi bi-receipt"></i> View</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($workspace['invoices'])): ?>
                            <hr>
                            <h4 class="case-panel-subtitle">Record Payment</h4>
                            <form method="post" action="<?= url('actions/case-action.php') ?>" class="case-inline-form">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="record_payment">
                                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                <select name="invoice_id" class="form-select form-select-sm mb-2" required>
                                    <?php foreach ($workspace['invoices'] as $inv): ?>
                                        <?php if (($inv['payment_status'] ?? $inv['status']) !== 'paid'): ?>
                                            <option value="<?= $inv['id'] ?>"><?= e($inv['invoice_number']) ?> — <?= formatCurrency((float) $inv['total']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <select name="payment_method" class="form-select form-select-sm mb-2">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="stripe">Stripe</option>
                                    <option value="other">Other</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm w-100">Record Payment</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="tab-pane fade" id="notes">
            <div class="case-panel">
                <h3 class="case-panel-title">Internal Notes</h3>
                <form method="post" action="<?= url('actions/case-action.php') ?>" class="case-note-form mb-3">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="add_note">
                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                    <textarea name="note" class="form-control" rows="3" placeholder="Add an internal note (admin only)..." required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm mt-2">Add Note</button>
                </form>
                <?php if (!empty($workspace['notes'])): ?>
                <div class="case-toolbar mb-2">
                    <div class="case-toolbar-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control form-control-sm case-filter-input" data-filter-target="#notesList .case-note-item" placeholder="Search notes...">
                    </div>
                </div>
                <?php endif; ?>
                <div class="case-notes-scroll" id="notesList">
                    <?php if (empty($workspace['notes'])): ?>
                        <p class="text-muted small">No notes yet.</p>
                    <?php else: ?>
                        <?php foreach ($workspace['notes'] as $note): ?>
                            <div class="case-note-item">
                                <div class="case-note-meta"><strong><?= e($note['author_name']) ?></strong> · <?= timeAgo($note['created_at']) ?></div>
                                <p><?= nl2br(e($note['note'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div class="tab-pane fade" id="activity">
            <div class="case-panel">
                <div class="case-panel-header">
                    <h3 class="case-panel-title mb-0">Case Activity Timeline</h3>
                    <?php if (!empty($workspace['activity'])): ?>
                    <select class="form-select form-select-sm case-filter-select" id="activityTypeFilter" data-filter-target="#activityTimeline .case-timeline-item" data-filter-attr="data-type">
                        <option value="">All activity</option>
                        <option value="case_created">Case created</option>
                        <option value="status">Status changes</option>
                        <option value="document">Documents</option>
                        <option value="quotation">Quotations</option>
                        <option value="proposal">Proposals</option>
                        <option value="invoice">Invoices</option>
                        <option value="payment">Payments</option>
                        <option value="note">Notes</option>
                        <option value="appointment">Appointments</option>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="case-activity-scroll">
                    <?php if ($totalActivity === 0): ?>
                        <div class="empty-state py-4"><i class="bi bi-activity"></i><p>No activity recorded yet.</p></div>
                    <?php else: ?>
                        <ul class="case-timeline" id="activityTimeline">
                            <?php
                            $lastGroup = '';
                            foreach ($pagedActivity as $ev):
                                $group = caseActivityDateLabel($ev['time']);
                                if ($group !== $lastGroup):
                                    $lastGroup = $group;
                            ?>
                                <li class="case-timeline-group"><?= e($group) ?></li>
                            <?php endif; ?>
                                <li class="case-timeline-item" data-type="<?= e($ev['type']) ?>">
                                    <div class="case-timeline-icon <?= caseActivityTone($ev['type']) ?>"><i class="bi <?= caseActivityIcon($ev['type']) ?>"></i></div>
                                    <div class="case-timeline-body">
                                        <strong><?= e($ev['title']) ?></strong>
                                        <?php if (!empty($ev['detail'])): ?><span><?= e($ev['detail']) ?></span><?php endif; ?>
                                        <div class="case-timeline-meta">
                                            <time><?= formatDateTime($ev['time']) ?></time>
                                            <?php if (!empty($ev['actor'])): ?><span class="case-timeline-actor">· <?= e($ev['actor']) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if ($totalActivity > 0): ?>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-1 pt-3 mt-2 border-top">
                        <small class="text-muted">
                            Showing <?= $activityShowingFrom ?>–<?= $activityShowingTo ?> of <?= $totalActivity ?> activities
                        </small>
                        <?= renderPaginationNav($activityPage, $totalActivityPages, 'activity_page', 'activity') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Client Letter -->
        <div class="tab-pane fade" id="client-letter">
            <div class="case-panel client-letter-panel">
                <div class="case-panel-header">
                    <h3 class="case-panel-title mb-0">Client Letter</h3>
                </div>

                <p class="case-panel-hint mb-3">
                    Generate a professional engagement letter, save it to this client’s record, and publish it to their portal when ready.
                </p>

                <form method="post" action="<?= url('actions/case-action.php') ?>" id="clientLetterForm">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                    <?php foreach ($letterLabels as $key => $label): ?>
                        <textarea name="letter_<?= e($key) ?>" hidden aria-hidden="true"><?= e($letterSections[$key] ?? '') ?></textarea>
                    <?php endforeach; ?>

                    <div class="mb-3">
                        <label class="form-label">Instructions for client</label>
                        <textarea name="client_instructions" class="form-control form-control-sm" rows="2" placeholder="Optional notes for the client"><?= e($case['client_instructions'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">When regenerating a saved letter</label>
                        <div class="d-flex flex-wrap gap-3 small">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="letter_version_mode" id="letterModeDraft" value="draft" checked>
                                <label class="form-check-label" for="letterModeDraft">Draft only (keep version history)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="letter_version_mode" id="letterModeReplace" value="replace">
                                <label class="form-check-label" for="letterModeReplace">Replace current saved version</label>
                            </div>
                        </div>
                    </div>

                    <div class="client-letter-actions d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-soft btn-sm" id="clientLetterPreviewBtn" <?= !$hasGeneratedDraft ? 'disabled' : '' ?>>
                            <i class="bi bi-eye"></i> Preview letter
                        </button>
                        <?php if ($hasGeneratedDraft): ?>
                            <a href="<?= url('actions/document-download.php?path=' . urlencode($clientLetterPath ?? ('cases/' . $caseId . '/generated/client_letter.html'))) ?>" class="btn btn-soft btn-sm" target="_blank" rel="noopener">
                                <i class="bi bi-<?= $letterIsPdf ? 'file-pdf' : 'file-earmark-text' ?>"></i> Download<?= $letterIsPdf ? ' PDF' : '' ?>
                            </a>
                        <?php endif; ?>
                        <button type="submit" name="action" value="generate_client_letter" class="btn btn-primary btn-sm">
                            <i class="bi bi-file-earmark-plus"></i> <?= $hasGeneratedDraft ? 'Regenerate' : 'Generate letter' ?>
                        </button>
                        <button type="submit" name="action" value="save_client_letter_record" class="btn btn-soft btn-sm" <?= !$hasGeneratedDraft ? 'disabled' : '' ?>>
                            <i class="bi bi-save"></i> Save to client record
                        </button>
                        <button type="submit" name="action" value="publish_client_letter" class="btn btn-soft btn-sm" <?= !$hasGeneratedDraft && !$currentSavedLetter ? 'disabled' : '' ?>>
                            <i class="bi bi-globe"></i> Publish to portal
                        </button>
                        <button type="submit" name="action" value="send_client_letter" class="btn btn-primary btn-sm" <?= !$hasGeneratedDraft && !$currentSavedLetter ? 'disabled' : '' ?>>
                            <i class="bi bi-envelope"></i> Email to client
                        </button>
                    </div>

                    <div class="client-letter-preview-wrap client-letter-preview-wrap--full mb-4">
                        <div class="client-letter-preview-head">
                            <span class="small fw-semibold">Letter preview</span>
                            <a href="#" class="small" id="clientLetterPreviewNewTab" target="_blank" rel="noopener">Open in new tab</a>
                        </div>
                        <div class="client-letter-preview-frame-wrap">
                            <iframe id="clientLetterPreviewFrame" title="Letter preview" class="client-letter-preview-frame"></iframe>
                            <div id="clientLetterPreviewPlaceholder" class="client-letter-preview-placeholder">
                                <span class="text-muted small">Generate a letter, then click <strong>Preview letter</strong>.</span>
                            </div>
                        </div>
                    </div>
                </form>

                <h4 class="h6 fw-semibold mb-2">Saved letters for this case</h4>
                <?php if (empty($savedClientLetters)): ?>
                    <p class="text-muted small mb-0">No letters saved yet. Generate a letter, then use <strong>Save to client record</strong>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Version</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($savedClientLetters as $sl): ?>
                                    <?php
                                    $slPath = ClientLetterService::getDownloadPath($sl);
                                    $isPublished = !empty($sl['published_to_portal']);
                                    $isCurrent = !empty($sl['is_current']);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($sl['title']) ?></strong>
                                            <?php if ($isCurrent): ?><span class="badge bg-primary-subtle text-primary ms-1">Current</span><?php endif; ?>
                                            <div class="small text-muted"><?= e($sl['case_number'] ?? '') ?></div>
                                        </td>
                                        <td>v<?= (int) $sl['version'] ?></td>
                                        <td><?= formatDateTime($sl['created_at']) ?></td>
                                        <td>
                                            <?php if ($isPublished): ?>
                                                <span class="badge bg-success-subtle text-success">On portal</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary">Internal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($slPath): ?>
                                                <a href="<?= url('actions/document-download.php?letter_id=' . (int) $sl['id']) ?>" class="btn btn-soft btn-sm" target="_blank" rel="noopener" title="Download"><i class="bi bi-download"></i></a>
                                            <?php endif; ?>
                                            <?php if (!$isPublished): ?>
                                                <form method="post" action="<?= url('actions/case-action.php') ?>" class="d-inline">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="publish_client_letter">
                                                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                                    <input type="hidden" name="letter_id" value="<?= (int) $sl['id'] ?>">
                                                    <button type="submit" class="btn btn-soft btn-sm" title="Publish to portal"><i class="bi bi-globe"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="<?= url('actions/case-action.php') ?>" class="d-inline">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="unpublish_client_letter">
                                                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                                    <input type="hidden" name="letter_id" value="<?= (int) $sl['id'] ?>">
                                                    <button type="submit" class="btn btn-soft btn-sm" title="Unpublish"><i class="bi bi-eye-slash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="<?= url('actions/case-action.php') ?>" class="d-inline" onsubmit="return confirm('Delete this letter version?');">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="delete_client_letter">
                                                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                                                <input type="hidden" name="letter_id" value="<?= (int) $sl['id'] ?>">
                                                <button type="submit" class="btn btn-soft btn-sm text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="modalInvoice" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" action="<?= url('actions/case-action.php') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="generate_invoice">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <div class="modal-header"><h5 class="modal-title">Generate Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Line items</label>
                    <div id="invoiceItems">
                        <?php foreach ($invoicePrefillItems as $item): ?>
                            <?php $isVatLine = str_contains((string) ($item['description'] ?? ''), '(VAT net)'); ?>
                            <div class="row g-2 mb-2 invoice-item-row" data-vat-line="<?= $isVatLine ? '1' : '0' ?>">
                                <div class="col-2"><input type="number" step="0.01" min="0" name="item_qty[]" class="form-control form-control-sm invoice-item-qty" value="<?= e((string) ($item['quantity'] ?? 1)) ?>" placeholder="Qty"></div>
                                <div class="col-6"><input type="text" name="item_description[]" class="form-control form-control-sm" value="<?= e($item['description']) ?>" placeholder="Description"></div>
                                <div class="col-4"><input type="number" step="0.01" min="0" name="item_amount[]" class="form-control form-control-sm invoice-item-amount" value="<?= e((string) ($item['unit_price'] ?? 0)) ?>" placeholder="Unit amount"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-soft btn-sm mt-1" id="addInvoiceItemBtn"><i class="bi bi-plus-lg"></i> Add item</button>
                </div>
                <div class="row g-2 mb-3 small">
                    <div class="col-md-6">
                        <div class="p-2 border rounded-2">
                            <div><strong>Net subtotal:</strong> <span id="invoiceSubtotalPreview"><?= formatCurrency($invNet) ?></span></div>
                            <div><strong>VAT:</strong> <span id="invoiceVatPreview"><?= formatCurrency((float) $billingTotals['vat_amount']) ?></span></div>
                            <div><strong>Total:</strong> <span id="invoiceTotalPreview"><?= formatCurrency((float) $billingTotals['grand_total']) ?></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="invoiceIncludeVat" name="include_vat" value="1"<?= (float) ($billingTotals['vat_amount'] ?? 0) > 0 ? ' checked' : '' ?>>
                            <label class="form-check-label" for="invoiceIncludeVat">Include VAT</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
                <?php $defaultPayTerms = trim((string) (getCompanySettings()['default_invoice_payment_terms'] ?? '')); ?>
                <div class="mb-3"><label class="form-label">Payment Terms</label><input type="text" name="payment_terms" class="form-control" value="<?= e($defaultPayTerms !== '' ? $defaultPayTerms : 'Payment due within 14 days') ?>"></div>
                <div class="mb-3"><label class="form-label">Payment Instructions</label><textarea name="payment_instructions" class="form-control" rows="2" placeholder="Optional override — bank details from Settings are used when empty"></textarea></div>
                <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Generate</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="modalQuotation" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" action="<?= url('actions/case-action.php') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="generate_quotation">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <div class="modal-header"><h5 class="modal-title">Generate Quotation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="Quotation — <?= e($case['title']) ?>"></div>
                <div class="mb-3"><label class="form-label">Amount (total incl. VAT)</label><input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string) $billingTotals['grand_total']) ?>"></div>
                <div class="mb-3"><label class="form-label">Tax Rate (%)</label><input type="number" step="0.01" name="tax_rate" class="form-control" value="<?= (float) $billingTotals['vat_amount'] > 0 && $invNet > 0 ? e((string) round($billingTotals['vat_amount'] / $invNet * 100, 2)) : '0' ?>"></div>
                <div class="mb-3"><label class="form-label">Valid Until</label><input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Generate</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="modalProposal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="post" action="<?= url('actions/case-action.php') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="generate_proposal">
            <input type="hidden" name="case_id" value="<?= $caseId ?>">
            <div class="modal-header"><h5 class="modal-title">Generate Proposal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="Proposal — <?= e($case['title']) ?>"></div>
                <div class="mb-3"><label class="form-label">Amount (total fee)</label><input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string) $billingTotals['grand_total']) ?>"></div>
                <div class="mb-3"><label class="form-label">Proposal Content</label><textarea name="content" class="form-control" rows="6">We propose to provide notary services for <?= e($case['title']) ?> as outlined in this case workspace. Total estimated fee: <?= formatCurrency((float) $billingTotals['grand_total']) ?>.</textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Generate PDF</button></div>
        </form>
    </div></div>
</div>

<?php
$pageScripts = '<script>
document.addEventListener("DOMContentLoaded", function() {
    var hash = window.location.hash.replace("#", "");
    var tabAliases = { invoices: "invoices", payments: "invoice-payments" };
    if (hash && tabAliases[hash]) hash = tabAliases[hash];
    var activityPageParam = parseInt(new URLSearchParams(window.location.search).get("activity_page") || "1", 10);
    if (!hash && activityPageParam > 1) hash = "activity";
    if (hash) {
        var tabBtn = document.querySelector("[data-bs-target=\"#" + hash + "\"]");
        if (tabBtn) new bootstrap.Tab(tabBtn).show();
    }
    document.querySelectorAll("[data-case-tab]").forEach(function(el) {
        el.addEventListener("click", function(e) {
            e.preventDefault();
            var target = this.getAttribute("data-case-tab");
            var tabBtn = document.querySelector("[data-bs-target=\"#" + target + "\"]");
            if (tabBtn) new bootstrap.Tab(tabBtn).show();
        });
    });

    function filterRows(containerSelector, query, attr, value) {
        document.querySelectorAll(containerSelector).forEach(function(row) {
            var text = row.textContent.toLowerCase();
            var matchSearch = !query || text.includes(query);
            var matchAttr = !attr || !value || row.getAttribute(attr) === value;
            row.style.display = matchSearch && matchAttr ? "" : "none";
        });
    }

    document.querySelectorAll(".case-filter-input").forEach(function(input) {
        input.addEventListener("input", function() {
            var target = this.dataset.filterTarget;
            var panel = this.closest(".case-panel");
            var select = panel ? panel.querySelector(".case-filter-select") : null;
            var attr = select ? select.dataset.filterAttr : null;
            var val = select ? select.value : "";
            filterRows(target, (this.value || "").toLowerCase(), attr, val);
        });
    });

    document.querySelectorAll(".case-filter-select").forEach(function(select) {
        select.addEventListener("change", function() {
            var target = this.dataset.filterTarget;
            var panel = this.closest(".case-panel") || this.closest(".tab-pane");
            var search = panel ? panel.querySelector(".case-filter-input") : null;
            var q = search ? (search.value || "").toLowerCase() : "";
            filterRows(target, q, this.dataset.filterAttr, this.value);
            document.querySelectorAll(target.replace(/[^ ]+$/, "") + " .case-timeline-group").forEach(function(g) {
                g.style.display = "";
            });
        });
    });

    var letterForm = document.getElementById("clientLetterForm");
    if (letterForm) {
        var previewUrl = "' . e(url('actions/client-letter-preview.php')) . '";
        var csrfName = "' . e($csrfFieldName) . '";
        var csrfToken = "' . e($csrfToken) . '";

        function letterFormData() {
            var fd = new FormData(letterForm);
            fd.set("embed", "1");
            fd.set(csrfName, csrfToken);
            return fd;
        }

        function refreshLetterPreview() {
            var frame = document.getElementById("clientLetterPreviewFrame");
            var placeholder = document.getElementById("clientLetterPreviewPlaceholder");
            var newTab = document.getElementById("clientLetterPreviewNewTab");
            if (!frame) return;
            fetch(previewUrl, { method: "POST", body: letterFormData(), credentials: "same-origin" })
                .then(function(r) { if (!r.ok) throw new Error(); return r.text(); })
                .then(function(html) {
                    frame.srcdoc = html;
                    frame.classList.add("is-loaded");
                    if (placeholder) placeholder.classList.add("is-hidden");
                    if (newTab) {
                        if (newTab._blobUrl) URL.revokeObjectURL(newTab._blobUrl);
                        newTab._blobUrl = URL.createObjectURL(new Blob([html], { type: "text/html" }));
                        newTab.href = newTab._blobUrl;
                    }
                })
                .catch(function() {
                    if (placeholder) {
                        placeholder.classList.remove("is-hidden");
                        placeholder.innerHTML = "<span class=\"text-danger small\">Preview failed. Refresh the page and try again.</span>";
                    }
                });
        }

        var previewBtn = document.getElementById("clientLetterPreviewBtn");
        if (previewBtn) previewBtn.addEventListener("click", refreshLetterPreview);

        function maybeRefreshLetterPreview() {
            if (document.querySelector("#client-letter.active, #client-letter.show")) {
                refreshLetterPreview();
            }
        }

        document.querySelectorAll("[data-bs-target=\"#client-letter\"]").forEach(function(tab) {
            tab.addEventListener("shown.bs.tab", maybeRefreshLetterPreview);
        });

        if (window.location.hash === "#client-letter") {
            setTimeout(refreshLetterPreview, 400);
        }
    }

    var itemsWrap = document.getElementById("invoiceItems");
    var addItemBtn = document.getElementById("addInvoiceItemBtn");
    var includeVat = document.getElementById("invoiceIncludeVat");
    var subtotalEl = document.getElementById("invoiceSubtotalPreview");
    var vatEl = document.getElementById("invoiceVatPreview");
    var totalEl = document.getElementById("invoiceTotalPreview");

    function formatMoney(num) {
        return "£" + (Math.round((num || 0) * 100) / 100).toFixed(2);
    }

    var invoiceVatRate = ' . json_encode((float) $caseBilling['vat_rate']) . ';

    function recalcInvoicePreview() {
        if (!itemsWrap || !subtotalEl || !vatEl || !totalEl) return;
        var subtotal = 0;
        var vatBase = 0;
        itemsWrap.querySelectorAll(".invoice-item-row").forEach(function(row) {
            var qty = parseFloat((row.querySelector(".invoice-item-qty") || {}).value || "0") || 0;
            var unit = parseFloat((row.querySelector(".invoice-item-amount") || {}).value || "0") || 0;
            if (qty <= 0) qty = 1;
            var line = qty * unit;
            subtotal += line;
            if (row.getAttribute("data-vat-line") === "1") {
                vatBase += line;
            }
        });
        var vat = (includeVat && includeVat.checked) ? Math.round(vatBase * invoiceVatRate) / 100 : 0;
        var total = subtotal + vat;
        subtotalEl.textContent = formatMoney(subtotal);
        vatEl.textContent = formatMoney(vat);
        totalEl.textContent = formatMoney(total);
    }

    if (addItemBtn && itemsWrap) {
        addItemBtn.addEventListener("click", function() {
            var row = document.createElement("div");
            row.className = "row g-2 mb-2 invoice-item-row";
            row.setAttribute("data-vat-line", "0");
            row.innerHTML =
                "<div class=\"col-2\"><input type=\"number\" step=\"0.01\" min=\"0\" name=\"item_qty[]\" class=\"form-control form-control-sm invoice-item-qty\" value=\"1\" placeholder=\"Qty\"></div>" +
                "<div class=\"col-5\"><input type=\"text\" name=\"item_description[]\" class=\"form-control form-control-sm\" placeholder=\"Description\"></div>" +
                "<div class=\"col-4\"><input type=\"number\" step=\"0.01\" min=\"0\" name=\"item_amount[]\" class=\"form-control form-control-sm invoice-item-amount\" value=\"0\" placeholder=\"Unit amount\"></div>" +
                "<div class=\"col-1\"><button type=\"button\" class=\"btn btn-soft btn-sm invoice-item-remove\">×</button></div>";
            itemsWrap.appendChild(row);
            recalcInvoicePreview();
        });

        itemsWrap.addEventListener("click", function(e) {
            var btn = e.target.closest(".invoice-item-remove");
            if (!btn) return;
            var rows = itemsWrap.querySelectorAll(".invoice-item-row");
            if (rows.length <= 1) return;
            btn.closest(".invoice-item-row").remove();
            recalcInvoicePreview();
        });

        itemsWrap.addEventListener("input", recalcInvoicePreview);
    }

    if (includeVat) {
        includeVat.addEventListener("change", recalcInvoicePreview);
    }
    recalcInvoicePreview();
});
</script>';
require __DIR__ . '/../includes/footer.php';
