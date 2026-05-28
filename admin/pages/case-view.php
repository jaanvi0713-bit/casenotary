<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$caseId = (int) ($_GET['id'] ?? 0);
$workspace = CaseService::getWorkspace($caseId);

if (!$workspace) {
    flash('error', 'Case not found.');
    redirect('pages/cases.php');
}

$case       = $workspace['case'];
$pageTitle  = $case['case_number'];
$pageSubtitle = $case['title'];

$successMsg = flash('success');
$errorMsg   = flash('error');

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

<div class="case-workspace">
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
        <div class="case-workspace-actions">
            <form method="post" action="<?= url('actions/case-action.php') ?>" class="case-status-form">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="case_id" value="<?= $caseId ?>">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach (['pending','in_progress','waiting_for_client','completed','closed'] as $st): ?>
                        <option value="<?= $st ?>" <?= $case['status'] === $st ? 'selected' : '' ?>>
                            <?= ucwords(str_replace('_', ' ', $st)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
    </ul>

    <div class="tab-content case-tab-content">
        <!-- Overview -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row g-3 align-items-start">
                <div class="col-lg-8">
                    <div class="case-panel">
                        <h3 class="case-panel-title">Case Details</h3>
                        <div class="case-detail-grid">
                            <div><span class="case-detail-label">Client</span><strong><?= e(clientFullName($case)) ?></strong><?php if ($case['company_name']): ?><small class="d-block text-muted"><?= e($case['company_name']) ?></small><?php endif; ?></div>
                            <div><span class="case-detail-label">Email</span><strong><?= e($case['email'] ?? '—') ?></strong></div>
                            <div><span class="case-detail-label">Service Type</span><strong><?= e($case['service_type']) ?></strong></div>
                            <div><span class="case-detail-label">Service Fee</span><strong><?= formatCurrency((float) $case['service_fee']) ?></strong></div>
                            <div><span class="case-detail-label">Assigned Admin</span><strong><?= e($case['admin_name'] ?? 'Unassigned') ?></strong></div>
                            <div><span class="case-detail-label">Deadline</span><strong><?= formatDate($case['deadline']) ?></strong></div>
                            <div><span class="case-detail-label">Created</span><strong><?= formatDateTime($case['created_at']) ?></strong></div>
                            <div><span class="case-detail-label">Last Updated</span><strong><?= formatDateTime($case['updated_at']) ?></strong></div>
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
                                    <div><strong><?= e($ev['title']) ?></strong><small><?= timeAgo($ev['time']) ?></small></div>
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
                <?php if (empty($workspace['documents'])): ?>
                    <div class="empty-state py-4"><i class="bi bi-folder2-open"></i><p>No documents yet.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table mb-0">
                            <thead><tr><th>File</th><th>Source</th><th>Uploaded By</th><th>Date</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($workspace['documents'] as $doc): ?>
                                    <tr>
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
                                                <button type="submit" class="btn btn-soft btn-sm text-danger" aria-label="Delete"><i class="bi bi-trash"></i></button>
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
                                            <a href="<?= url('actions/document-download.php?path=' . urlencode($q['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank">View</a>
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
                                        <div><strong><?= e($p['proposal_number']) ?></strong><small><?= formatCurrency((float) $p['amount']) ?> · <?= formatDate($p['created_at']) ?></small></div>
                                        <?php if (!empty($p['pdf_path'])): ?>
                                            <a href="<?= url('actions/document-download.php?path=' . urlencode($p['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank">View PDF</a>
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
                <?php if (empty($workspace['invoices'])): ?>
                    <div class="empty-state py-4"><i class="bi bi-receipt"></i><p>No invoices yet.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table mb-0">
                            <thead><tr><th>Invoice #</th><th>Amount</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($workspace['invoices'] as $inv): ?>
                                    <tr>
                                        <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                                        <td><?= formatCurrency((float) $inv['total']) ?></td>
                                        <td><?= formatDate($inv['due_date']) ?></td>
                                        <td><?= statusBadge($inv['payment_status'] ?? $inv['status'] ?? 'pending') ?></td>
                                        <td>
                                            <?php if (!empty($inv['pdf_path'])): ?>
                                                <a href="<?= url('actions/document-download.php?path=' . urlencode($inv['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank">View</a>
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
                            <div class="table-responsive">
                                <table class="table saas-table mb-0">
                                    <thead><tr><th>Invoice</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($workspace['payments'] as $pay): ?>
                                            <tr>
                                                <td><?= e($pay['invoice_number']) ?></td>
                                                <td><strong><?= formatCurrency((float) $pay['amount']) ?></strong></td>
                                                <td><?= e(ucwords(str_replace('_', ' ', $pay['payment_method'] ?? ''))) ?></td>
                                                <td><?= paymentStatusBadge($pay['payment_status']) ?></td>
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
                                        <div><strong><?= e($r['receipt_number']) ?></strong><small><?= formatCurrency((float) $r['amount']) ?></small></div>
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
                <div class="case-notes-scroll">
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
                <h3 class="case-panel-title">Case Activity Timeline</h3>
                <div class="case-activity-scroll">
                    <?php if (empty($workspace['activity'])): ?>
                        <p class="text-muted small">No activity recorded.</p>
                    <?php else: ?>
                        <ul class="case-timeline">
                            <?php foreach ($workspace['activity'] as $ev): ?>
                                <li class="case-timeline-item">
                                    <div class="case-timeline-icon"><i class="bi <?= caseActivityIcon($ev['type']) ?>"></i></div>
                                    <div class="case-timeline-body">
                                        <strong><?= e($ev['title']) ?></strong>
                                        <span><?= e($ev['detail']) ?></span>
                                        <time><?= timeAgo($ev['time']) ?></time>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
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
                <div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string) $case['service_fee']) ?>"></div>
                <div class="mb-3"><label class="form-label">Tax Rate (%)</label><input type="number" step="0.01" name="tax_rate" class="form-control" value="0"></div>
                <div class="mb-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
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
                <div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string) $case['service_fee']) ?>"></div>
                <div class="mb-3"><label class="form-label">Tax Rate (%)</label><input type="number" step="0.01" name="tax_rate" class="form-control" value="0"></div>
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
                <div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string) $case['service_fee']) ?>"></div>
                <div class="mb-3"><label class="form-label">Proposal Content</label><textarea name="content" class="form-control" rows="6">We propose to provide notary services for <?= e($case['title']) ?> as outlined in this case workspace. Total estimated fee: <?= formatCurrency((float) $case['service_fee']) ?>.</textarea></div>
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
    if (hash && tabAliases[hash]) {
        hash = tabAliases[hash];
    }
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
});
</script>';
require __DIR__ . '/../includes/footer.php';
