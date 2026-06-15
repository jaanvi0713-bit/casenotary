<?php
require_once __DIR__ . '/../core/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

Auth::requirePage('cases');
if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
    flash('error', 'You have read-only access to cases.');
    redirect($isEdit && $id > 0 ? 'pages/case-view.php?id=' . $id : 'pages/cases.php');
}

if ($isEdit) {
    $case = CaseService::getCaseById($id);
    if (!$case) {
        flash('error', 'Case not found.');
        redirect('pages/cases.php');
    }
    $pageTitle = 'Edit Case';
    $pageSubtitle = trim((string) ($case['case_number'] ?? '')) !== ''
        ? $case['case_number'] . ' — ' . ($case['title'] ?? '')
        : ($case['title'] ?? 'Update case details');
} else {
    $pageTitle = 'New Case';
    $pageSubtitle = 'Set up a new legal case workspace';
    $case = null;
}

$clients = getAllClients();
$admins  = CaseService::getAdmins();
$caseBilling    = $isEdit ? CaseService::getCaseBilling($case) : CaseService::emptyCaseBilling();
$nonVatServices = ($caseBilling['non_vat'] ?? []) !== [] ? $caseBilling['non_vat'] : [['type' => '', 'net' => 0]];
$vatServices    = ($caseBilling['vat'] ?? []) !== [] ? $caseBilling['vat'] : [['type' => '', 'net' => 0]];
$vatRate        = (float) ($caseBilling['vat_rate'] ?? CaseService::vatRate());
$nonVatRate     = CaseService::NON_VAT_RATE;

require __DIR__ . '/../includes/header.php';
?>

<link href="<?= asset('css/case-workspace.css') ?>" rel="stylesheet">

<div class="case-form-page">
    <div class="case-form-header">
        <a href="<?= url($isEdit ? 'pages/case-view.php?id=' . $id : 'pages/cases.php') ?>" class="btn btn-primary btn-sm case-back-btn">
            <i class="bi bi-arrow-left"></i> Back to <?= $isEdit ? 'Case' : 'Cases' ?>
        </a>
        <div class="case-form-header-main">
            <div>
                <h1 class="case-form-title"><?= $isEdit ? 'Edit Case' : 'Create New Case' ?></h1>
                <p class="case-form-subtitle"><?= $isEdit ? 'Update case details and assignment.' : 'Set up a new legal case workspace for your client.' ?></p>
            </div>
            <?php if ($isEdit && !empty($case['case_number'])): ?>
                <span class="case-form-badge"><?= e($case['case_number']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" action="<?= url('actions/case-action.php') ?>" class="case-form" enctype="multipart/form-data">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="<?= $isEdit ? 'update_case' : 'create_case' ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="case_id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="case-form-card">
            <div class="case-form-section">
                <div class="case-form-section-head">
                    <i class="bi bi-briefcase"></i>
                    <div>
                        <h2 class="case-form-section-title">Case Information</h2>
                        <p class="case-form-section-desc">Basic details that identify this matter.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="case-form-label" for="title">Case Title <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title" class="form-control case-form-control" required
                               placeholder="e.g. Smith Property Transfer"
                               value="<?= e($case['title'] ?? ($_SESSION['old']['title'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="case-form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-control case-form-control" rows="3"
                                  placeholder="Brief summary of the case scope and requirements…"><?= e($case['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="case-form-section">
                <div class="case-form-section-head">
                    <i class="bi bi-people"></i>
                    <div>
                        <h2 class="case-form-section-title">Client & Assignment</h2>
                        <p class="case-form-section-desc">Who this case belongs to and who manages it.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="case-form-label" for="client_id">Client <span class="text-danger">*</span></label>
                        <select id="client_id" name="client_id" class="form-select case-form-control" required>
                            <option value="">Select a client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= (string) ($case['client_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>>
                                    <?= e(clientFullName($client)) ?><?= $client['company_name'] ? ' — ' . e($client['company_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$isEdit): ?>
                            <small class="text-muted d-block mt-1"><a href="<?= url('pages/client-form.php') ?>">Add a new client</a> if not listed.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="assigned_admin_id">Assigned Admin</label>
                        <select id="assigned_admin_id" name="assigned_admin_id" class="form-select case-form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?= $admin['id'] ?>" <?= (string) ($case['assigned_admin_id'] ?? '') === (string) $admin['id'] ? 'selected' : '' ?>>
                                    <?= e(trim($admin['name'] ?? '') !== '' ? $admin['name'] : userFullName($admin)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (!$isEdit): ?>
            <div class="case-form-section">
                <div class="case-form-section-head">
                    <i class="bi bi-chat-left-text"></i>
                    <div>
                        <h2 class="case-form-section-title">Client Instructions & Files</h2>
                        <p class="case-form-section-desc">Instructions emailed to the client and optional intake documents.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="case-form-label" for="client_instructions">Instructions for Client</label>
                        <textarea id="client_instructions" name="client_instructions" class="form-control case-form-control" rows="3"
                                  placeholder="What the client should prepare, bring, or complete…"><?= e($case['client_instructions'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="document">Upload File (optional)</label>
                        <input type="file" id="document" name="document" class="form-control case-form-control"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_emails" value="1" id="send_emails" checked>
                                <label class="form-check-label" for="send_emails">Email quotation PDF to client</label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="send_client_letter" value="1" id="send_client_letter" checked>
                                <label class="form-check-label" for="send_client_letter">Email Client Letter quotation PDF to client</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="case-form-section case-form-section-last">
                <div class="case-form-section-head">
                    <i class="bi bi-cash-stack"></i>
                    <div>
                        <h2 class="case-form-section-title">Service & Billing</h2>
                        <p class="case-form-section-desc">Services, fees, and totals used for invoices and client documents.</p>
                    </div>
                </div>
                <div class="case-services-block">
                    <p class="case-form-section-desc mb-3">Add services in each section as needed. The <strong>total fee</strong> is saved on the case and used for invoices, payments, and client documents.</p>

                    <div class="case-billing-part mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <h3 class="case-billing-part-title mb-0">Non-VAT services</h3>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <label class="case-form-label mb-0 small text-nowrap" for="caseNonVatRate">Rate (%)</label>
                                <input type="number" step="0.01" min="0" max="0" id="caseNonVatRate" name="non_vat_rate"
                                       class="form-control form-control-sm case-billing-rate-input case-form-control case-billing-rate-fixed"
                                       value="0" readonly tabindex="-1" aria-readonly="true">
                                <button type="button" class="btn btn-soft btn-sm js-add-service-row" data-billing-part="non_vat">
                                    <i class="bi bi-plus-lg"></i> Add service
                                </button>
                            </div>
                        </div>
                        <div class="case-billing-grid">
                            <div class="case-billing-grid__head">
                                <span class="case-billing-grid__col case-billing-grid__col--service">Service</span>
                                <span class="case-billing-grid__col case-billing-grid__col--amount">Net amount</span>
                                <span class="case-billing-grid__col case-billing-grid__col--total">Total</span>
                                <span class="case-billing-grid__col case-billing-grid__col--action"></span>
                            </div>
                            <div id="case-services-non-vat" class="case-billing-grid__body" data-billing-part="non_vat">
                                <?php foreach ($nonVatServices as $index => $service):
                                    $lineNet = (float) ($service['net'] ?? 0);
                                    $lineGross = round($lineNet + round($lineNet * $nonVatRate / 100, 2), 2);
                                ?>
                                    <div class="case-billing-grid__row case-service-row">
                                        <div class="case-billing-grid__col case-billing-grid__col--service">
                                            <input type="text" name="services_non_vat[type][]" class="form-control case-form-control case-service-type"
                                                   placeholder="e.g. Disbursement" value="<?= e($service['type'] ?? '') ?>">
                                        </div>
                                        <div class="case-billing-grid__col case-billing-grid__col--amount">
                                            <div class="input-group case-fee-input">
                                                <span class="input-group-text"><?= e(currencySymbol()) ?></span>
                                                <input type="number" step="0.01" min="0" name="services_non_vat[fee][]"
                                                       class="form-control case-form-control case-service-fee" data-billing-part="non_vat"
                                                       value="<?= e((string) ($service['net'] ?? '0')) ?>">
                                            </div>
                                        </div>
                                        <div class="case-billing-grid__col case-billing-grid__col--total">
                                            <div class="case-line-total" data-line-gross><?= formatCurrency($lineGross) ?></div>
                                        </div>
                                        <div class="case-billing-grid__col case-billing-grid__col--action">
                                            <button type="button" class="btn btn-soft-danger btn-sm js-remove-service" title="Remove"<?= $index === 0 ? ' hidden' : '' ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="case-billing-part mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <h3 class="case-billing-part-title mb-0">VAT services</h3>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <label class="case-form-label mb-0 small text-nowrap" for="caseVatRate">Rate (%)</label>
                                <input type="number" step="0.01" min="0" max="100" id="caseVatRate" name="vat_rate"
                                       class="form-control form-control-sm case-billing-rate-input case-form-control"
                                       value="<?= e(rtrim(rtrim(number_format($vatRate, 2), '0'), '.')) ?>">
                                <button type="button" class="btn btn-soft btn-sm js-add-service-row" data-billing-part="vat">
                                    <i class="bi bi-plus-lg"></i> Add service
                                </button>
                            </div>
                        </div>
                        <div class="case-billing-grid">
                            <div class="case-billing-grid__head">
                                <span class="case-billing-grid__col case-billing-grid__col--service">Service</span>
                                <span class="case-billing-grid__col case-billing-grid__col--amount">Net amount</span>
                                <span class="case-billing-grid__col case-billing-grid__col--total">Total</span>
                                <span class="case-billing-grid__col case-billing-grid__col--action"></span>
                            </div>
                            <div id="case-services-vat" class="case-billing-grid__body" data-billing-part="vat">
                                <?php foreach ($vatServices as $index => $service):
                                    $lineNet = (float) ($service['net'] ?? 0);
                                    $lineGross = round($lineNet + round($lineNet * $vatRate / 100, 2), 2);
                                ?>
                                    <div class="case-billing-grid__row case-service-row">
                                        <div class="case-billing-grid__col case-billing-grid__col--service">
                                            <input type="text" name="services_vat[type][]" class="form-control case-form-control case-service-type"
                                                   placeholder="e.g. Notarisation" value="<?= e($service['type'] ?? '') ?>">
                                        </div>
                                        <div class="case-billing-grid__col case-billing-grid__col--amount">
                                            <div class="input-group case-fee-input">
                                                <span class="input-group-text"><?= e(currencySymbol()) ?></span>
                                                <input type="number" step="0.01" min="0" name="services_vat[fee][]"
                                                       class="form-control case-form-control case-service-fee" data-billing-part="vat"
                                                       value="<?= e((string) ($service['net'] ?? '0')) ?>">
                                            </div>
                                        </div>
                                        <div class="case-billing-grid__col case-billing-grid__col--total">
                                            <div class="case-line-total" data-line-gross><?= formatCurrency($lineGross) ?></div>
                                        </div>
                                        <div class="case-billing-grid__col case-billing-grid__col--action">
                                            <button type="button" class="btn btn-soft-danger btn-sm js-remove-service" title="Remove"<?= $index === 0 ? ' hidden' : '' ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="case-billing-summary card border-0 bg-light">
                        <div class="card-body py-3">
                            <div class="row g-2 case-billing-summary-grid">
                                <div class="col-sm-6">
                                    <div class="case-billing-summary-block">
                                        <div class="case-billing-summary-block-title">Non-VAT</div>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>Net</span>
                                            <span id="billingNonVatNet"><?= formatCurrency((float) ($caseBilling['totals']['non_vat_net_subtotal'] ?? $caseBilling['totals']['non_vat_subtotal'] ?? 0)) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>Rate amount</span>
                                            <span id="billingNonVatRateAmount"><?= formatCurrency((float) ($caseBilling['totals']['non_vat_rate_amount'] ?? 0)) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-semibold mt-1">
                                            <span>Subtotal</span>
                                            <strong id="billingNonVatSubtotal"><?= formatCurrency((float) ($caseBilling['totals']['non_vat_subtotal'] ?? 0)) ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="case-billing-summary-block">
                                        <div class="case-billing-summary-block-title">VAT services</div>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>Net</span>
                                            <span id="billingVatNetSubtotal"><?= formatCurrency((float) ($caseBilling['totals']['vat_net_subtotal'] ?? 0)) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>VAT</span>
                                            <span id="billingVatAmount"><?= formatCurrency((float) ($caseBilling['totals']['vat_amount'] ?? 0)) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-semibold mt-1">
                                            <span>Subtotal</span>
                                            <strong id="billingVatGrossSubtotal"><?= formatCurrency((float) ($caseBilling['totals']['vat_gross_subtotal'] ?? 0)) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-3 mt-2 border-top">
                                <span class="case-form-label mb-0">Total fee</span>
                                <strong class="case-services-total-value fs-5 mb-0" id="billingGrandTotal"><?= formatCurrency((float) ($caseBilling['totals']['grand_total'] ?? 0)) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="case-form-footer">
            <p class="case-form-required-note"><span class="text-danger">*</span> Required fields</p>
            <div class="case-form-actions">
                <a href="<?= url($isEdit ? 'pages/case-view.php?id=' . $id : 'pages/cases.php') ?>" class="btn btn-soft">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $isEdit ? 'Save Changes' : 'Create Case' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<template id="case-service-row-template-non-vat">
    <div class="case-billing-grid__row case-service-row">
        <div class="case-billing-grid__col case-billing-grid__col--service">
            <input type="text" name="services_non_vat[type][]" class="form-control case-form-control case-service-type" placeholder="e.g. Disbursement">
        </div>
        <div class="case-billing-grid__col case-billing-grid__col--amount">
            <div class="input-group case-fee-input">
                <span class="input-group-text"><?= e(currencySymbol()) ?></span>
                <input type="number" step="0.01" min="0" name="services_non_vat[fee][]" class="form-control case-form-control case-service-fee" data-billing-part="non_vat" value="0">
            </div>
        </div>
        <div class="case-billing-grid__col case-billing-grid__col--total">
            <div class="case-line-total" data-line-gross><?= formatCurrency(0) ?></div>
        </div>
        <div class="case-billing-grid__col case-billing-grid__col--action">
            <button type="button" class="btn btn-soft-danger btn-sm js-remove-service" title="Remove"><i class="bi bi-trash"></i></button>
        </div>
    </div>
</template>

<template id="case-service-row-template-vat">
    <div class="case-billing-grid__row case-service-row">
        <div class="case-billing-grid__col case-billing-grid__col--service">
            <input type="text" name="services_vat[type][]" class="form-control case-form-control case-service-type" placeholder="e.g. Notarisation">
        </div>
        <div class="case-billing-grid__col case-billing-grid__col--amount">
            <div class="input-group case-fee-input">
                <span class="input-group-text"><?= e(currencySymbol()) ?></span>
                <input type="number" step="0.01" min="0" name="services_vat[fee][]" class="form-control case-form-control case-service-fee" data-billing-part="vat" value="0">
            </div>
        </div>
        <div class="case-billing-grid__col case-billing-grid__col--total">
            <div class="case-line-total" data-line-gross><?= formatCurrency(0) ?></div>
        </div>
        <div class="case-billing-grid__col case-billing-grid__col--action">
            <button type="button" class="btn btn-soft-danger btn-sm js-remove-service" title="Remove"><i class="bi bi-trash"></i></button>
        </div>
    </div>
</template>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var vatRate = <?= json_encode($vatRate) ?>;
    var nonVatRateDefault = 0;
    var currencySymbol = <?= json_encode(currencySymbol()) ?>;
    var currencyLocale = <?= json_encode(currencyLocale()) ?>;
    var lists = {
        non_vat: document.getElementById("case-services-non-vat"),
        vat: document.getElementById("case-services-vat")
    };
    var templates = {
        non_vat: document.getElementById("case-service-row-template-non-vat"),
        vat: document.getElementById("case-service-row-template-vat")
    };

    function formatMoney(value) {
        return currencySymbol + Number(value || 0).toLocaleString(currencyLocale, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getVatRate() {
        var inp = document.getElementById("caseVatRate");
        return inp ? Math.max(0, parseFloat(inp.value || "0") || 0) : vatRate;
    }

    function getNonVatRate() {
        return 0;
    }

    function roundMoney(value) {
        return Math.round((value || 0) * 100) / 100;
    }

    function sumPart(part) {
        var total = 0;
        if (!lists[part]) return 0;
        lists[part].querySelectorAll(".case-service-fee").forEach(function(input) {
            total += parseFloat(input.value || "0") || 0;
        });
        return total;
    }

    function updatePartLineTotals(part, rate) {
        var list = lists[part];
        if (!list) return;
        list.querySelectorAll(".case-service-row").forEach(function(row) {
            var feeInput = row.querySelector(".case-service-fee");
            var net = parseFloat(feeInput && feeInput.value || "0") || 0;
            var gross = roundMoney(net + roundMoney(net * rate / 100));
            var grossEl = row.querySelector("[data-line-gross]");
            if (grossEl) grossEl.textContent = formatMoney(gross);
        });
    }

    function updateTotals() {
        var nonVatRateVal = getNonVatRate();
        var vatRateVal = getVatRate();
        var nonVatNet = sumPart("non_vat");
        var vatNet = sumPart("vat");
        var nonVatRateAmount = roundMoney(nonVatNet * nonVatRateVal / 100);
        var nonVatGross = roundMoney(nonVatNet + nonVatRateAmount);
        var vatAmount = roundMoney(vatNet * vatRateVal / 100);
        var vatGross = roundMoney(vatNet + vatAmount);
        var grand = roundMoney(nonVatGross + vatGross);

        updatePartLineTotals("non_vat", nonVatRateVal);
        updatePartLineTotals("vat", vatRateVal);

        var elNonNet = document.getElementById("billingNonVatNet");
        var elNonRate = document.getElementById("billingNonVatRateAmount");
        var elNon = document.getElementById("billingNonVatSubtotal");
        var elNet = document.getElementById("billingVatNetSubtotal");
        var elVat = document.getElementById("billingVatAmount");
        var elVatGross = document.getElementById("billingVatGrossSubtotal");
        var elGrand = document.getElementById("billingGrandTotal");

        if (elNonNet) elNonNet.textContent = formatMoney(nonVatNet);
        if (elNonRate) elNonRate.textContent = formatMoney(nonVatRateAmount);
        if (elNon) elNon.textContent = formatMoney(nonVatGross);
        if (elNet) elNet.textContent = formatMoney(vatNet);
        if (elVat) elVat.textContent = formatMoney(vatAmount);
        if (elVatGross) elVatGross.textContent = formatMoney(vatGross);
        if (elGrand) elGrand.textContent = formatMoney(grand);
    }

    function refreshRemoveButtons(list) {
        var rows = list.querySelectorAll(".case-service-row");
        rows.forEach(function(row) {
            var removeBtn = row.querySelector(".js-remove-service");
            if (removeBtn) removeBtn.hidden = rows.length === 1;
        });
    }

    function bindRow(row, list) {
        row.querySelectorAll(".case-service-fee").forEach(function(input) {
            input.addEventListener("input", updateTotals);
        });
        var removeBtn = row.querySelector(".js-remove-service");
        if (removeBtn) {
            removeBtn.addEventListener("click", function() {
                if (list.querySelectorAll(".case-service-row").length === 1) return;
                row.remove();
                refreshRemoveButtons(list);
                updateTotals();
            });
        }
    }

    Object.keys(lists).forEach(function(part) {
        var list = lists[part];
        if (!list) return;
        list.querySelectorAll(".case-service-row").forEach(function(row) {
            bindRow(row, list);
        });
        refreshRemoveButtons(list);
    });
    updateTotals();

    var vatRateInput = document.getElementById("caseVatRate");
    if (vatRateInput) {
        vatRateInput.addEventListener("input", updateTotals);
        vatRateInput.addEventListener("change", updateTotals);
    }

    document.querySelectorAll(".js-add-service-row").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var part = btn.getAttribute("data-billing-part");
            var list = lists[part];
            var template = templates[part];
            if (!list || !template) return;
            var row = template.content.firstElementChild.cloneNode(true);
            list.appendChild(row);
            bindRow(row, list);
            refreshRemoveButtons(list);
            row.querySelector(".case-service-type").focus();
        });
    });

    document.querySelector(".case-form").addEventListener("submit", function(e) {
        var hasLine = false;
        Object.keys(lists).forEach(function(part) {
            var list = lists[part];
            if (!list) return;
            list.querySelectorAll(".case-service-row").forEach(function(row) {
                var type = (row.querySelector(".case-service-type").value || "").trim();
                var fee = parseFloat(row.querySelector(".case-service-fee").value || "0") || 0;
                if (type !== "" || fee > 0) hasLine = true;
            });
        });
        if (!hasLine) {
            e.preventDefault();
            alert("Add at least one service under Non-VAT or VAT.");
        }
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
