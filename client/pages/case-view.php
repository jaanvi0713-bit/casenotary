<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
$caseId   = (int) ($_GET['id'] ?? 0);
$case     = CaseService::getCaseForClient($caseId, $clientId);

if (!$case) {
    flash('error', 'Case not found or access denied.');
    header('Location: ' . clientUrl('pages/cases.php'));
    exit;
}

$workspace = CaseService::getWorkspace($caseId);
$pageTitle = $case['case_number'];

require __DIR__ . '/../includes/header.php';
?>

<div class="case-workspace">
    <div class="case-workspace-header">
        <div>
            <a href="<?= clientUrl('pages/cases.php') ?>" class="case-back-link"><i class="bi bi-arrow-left"></i> My Cases</a>
            <div class="case-workspace-title-row">
                <h1 class="case-workspace-title"><?= e($case['case_number']) ?></h1>
                <?= statusBadge($case['status']) ?>
            </div>
            <p class="case-workspace-subtitle"><?= e($case['title']) ?></p>
        </div>
    </div>

    <ul class="nav nav-tabs case-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">Overview</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents" type="button">Documents</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#quotations" type="button">Quotations</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#invoices" type="button">Invoices</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#receipts" type="button">Receipts</button></li>
    </ul>

    <div class="tab-content case-tab-content">
        <div class="tab-pane fade show active" id="overview">
            <div class="case-panel">
                <h3 class="case-panel-title">Case Details</h3>
                <div class="case-detail-grid">
                    <div class="case-detail-span-2">
                        <span class="case-detail-label">Services</span>
                        <div class="case-services-table-wrap mt-1">
                            <table class="table table-sm case-services-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th class="text-end">Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?= CaseService::formatCaseServicesHtml($case) ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end"><?= formatCurrency((float) $case['service_fee']) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div><span class="case-detail-label">Deadline</span><strong><?= formatDate($case['deadline']) ?></strong></div>
                    <div><span class="case-detail-label">Priority</span><strong><?= ucfirst($case['priority']) ?></strong></div>
                </div>
                <?php if ($case['description']): ?>
                    <div class="case-description mt-3"><span class="case-detail-label">Description</span><p><?= nl2br(e($case['description'])) ?></p></div>
                <?php endif; ?>
                <?php if (!empty($case['client_instructions'])): ?>
                    <div class="case-description mt-3">
                        <span class="case-detail-label">Your Instructions</span>
                        <p><?= nl2br(e($case['client_instructions'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="documents">
            <div class="case-panel">
                <div class="case-panel-header">
                    <h3 class="case-panel-title mb-0">Documents</h3>
                    <form method="post" action="<?= adminUrl('actions/case-action.php') ?>" enctype="multipart/form-data" class="case-upload-form">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="upload_document">
                        <input type="hidden" name="case_id" value="<?= $caseId ?>">
                        <input type="file" name="document" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip" required>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Upload</button>
                    </form>
                </div>
                <?php if (empty($workspace['documents'])): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-file-earmark-arrow-up"></i>
                        <p class="mb-0">No documents yet. Use the form above to upload files for this case.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table mb-0">
                            <thead><tr><th>File</th><th>Source</th><th>Date</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($workspace['documents'] as $doc): ?>
                                    <tr>
                                        <td><?= e($doc['original_name'] ?? $doc['file_name']) ?></td>
                                        <td><?= ucfirst($doc['upload_source'] ?? 'admin') ?></td>
                                        <td><?= formatDateTime($doc['created_at']) ?></td>
                                        <td><a href="<?= adminUrl('actions/document-download.php?id=' . $doc['id']) ?>" class="btn btn-soft btn-sm"><i class="bi bi-download"></i></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="quotations">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="case-panel">
                        <h3 class="case-panel-title">Quotations</h3>
                        <ul class="case-doc-list">
                            <?php foreach ($workspace['quotations'] as $q): ?>
                                <li>
                                    <div><strong><?= e($q['quotation_number']) ?></strong><small><?= formatCurrency((float) $q['total']) ?></small></div>
                                    <?php if ($q['pdf_path']): ?><a href="<?= adminUrl('actions/document-download.php?path=' . urlencode($q['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank">View</a><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="case-panel">
                        <h3 class="case-panel-title">Proposals</h3>
                        <ul class="case-doc-list">
                            <?php foreach ($workspace['proposals'] as $p): ?>
                                <li>
                                    <div><strong><?= e($p['proposal_number']) ?></strong><small><?= formatCurrency((float) $p['amount']) ?></small></div>
                                    <?php if ($p['pdf_path']): ?><a href="<?= adminUrl('actions/document-download.php?path=' . urlencode($p['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank">View</a><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="invoices">
            <div class="case-panel">
                <h3 class="case-panel-title">Invoices</h3>
                <div class="table-responsive">
                    <table class="table saas-table mb-0">
                        <thead><tr><th>Invoice #</th><th>Amount</th><th>Due</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($workspace['invoices'] as $inv): ?>
                                <tr>
                                    <td><?= e($inv['invoice_number']) ?></td>
                                    <td><?= formatCurrency((float) $inv['total']) ?></td>
                                    <td><?= formatDate($inv['due_date']) ?></td>
                                    <td><?= statusBadge(effectiveInvoiceStatus($inv)) ?></td>
                                    <td class="text-end">
                                        <?php if ($inv['pdf_path']): ?>
                                            <a href="<?= adminUrl('actions/document-download.php?path=' . urlencode($inv['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank">PDF</a>
                                        <?php endif; ?>
                                        <?php
                                        $st = effectiveInvoiceStatus($inv);
                                        $remaining = CaseService::getInvoiceRemainingBalance($inv);
                                        ?>
                                        <?php if (in_array($st, ['pending', 'overdue', 'partially_paid'], true) && $remaining > 0 && StripeService::isConfigured()): ?>
                                            <form method="post" action="<?= clientUrl('actions/stripe-checkout.php') ?>" class="d-inline">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Pay</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="receipts">
            <div class="case-panel">
                <h3 class="case-panel-title">Receipts</h3>
                <ul class="case-doc-list">
                    <?php foreach ($workspace['receipts'] as $r): ?>
                        <li>
                            <div><strong><?= e($r['receipt_number']) ?></strong><small><?= formatCurrency((float) $r['amount']) ?></small></div>
                            <a href="<?= clientUrl('actions/receipt-download.php?id=' . (int) $r['id']) ?>" class="btn btn-soft btn-sm" target="_blank">Download</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hash = window.location.hash;
    if (!hash) return;
    const trigger = document.querySelector('[data-bs-target="' + hash + '"]');
    if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
?>
