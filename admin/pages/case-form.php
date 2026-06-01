<?php
require_once __DIR__ . '/../core/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit) {
    Auth::requireAdmin();
    $case = CaseService::getCaseById($id);
    if (!$case) {
        flash('error', 'Case not found.');
        redirect('pages/cases.php');
    }
    $pageTitle = 'Edit Case';
} else {
    Auth::requireAdmin();
    $pageTitle = 'New Case';
    $case = null;
}

$clients = getAllClients();
$admins  = CaseService::getAdmins();
$caseServices = $isEdit ? CaseService::getCaseServices($case) : [['type' => '', 'fee' => 0]];

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
                    <div class="col-lg-8">
                        <label class="case-form-label" for="title">Case Title <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title" class="form-control case-form-control" required
                               placeholder="e.g. Smith Property Transfer"
                               value="<?= e($case['title'] ?? ($_SESSION['old']['title'] ?? '')) ?>">
                    </div>
                    <div class="col-lg-4">
                        <label class="case-form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select case-form-control">
                            <?php
                            $statusOptions = $isEdit
                                ? CaseService::getAllowedStatuses($case['status'] ?? 'pending')
                                : CaseService::STATUSES;
                            foreach ($statusOptions as $st):
                            ?>
                                <option value="<?= $st ?>" <?= ($case['status'] ?? 'pending') === $st ? 'selected' : '' ?>>
                                    <?= CaseService::statusLabel($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                                    <?= e($admin['name']) ?>
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
                        <h2 class="case-form-section-title">Service & Schedule</h2>
                        <p class="case-form-section-desc">Billing, priority, and target completion date.</p>
                    </div>
                </div>
                <div class="case-services-block">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <p class="case-form-section-desc mb-0">Add one or more services for this case.</p>
                        <button type="button" class="btn btn-soft btn-sm" id="add-service-row">
                            <i class="bi bi-plus-lg"></i> Add Service
                        </button>
                    </div>

                    <div id="case-services-list" class="case-services-list">
                        <?php foreach ($caseServices as $index => $service): ?>
                            <div class="case-service-row row g-2 align-items-end<?= $index > 0 ? '' : ' case-service-row-first' ?>">
                                <div class="col-md-6 col-lg-5">
                                    <?php if ($index === 0): ?>
                                        <label class="case-form-label">Service Type <span class="text-danger">*</span></label>
                                    <?php endif; ?>
                                    <input type="text" name="services[type][]" class="form-control case-form-control case-service-type"
                                           placeholder="Property Transfer" required
                                           value="<?= e($service['type']) ?>">
                                </div>
                                <div class="col-md-4 col-lg-3">
                                    <?php if ($index === 0): ?>
                                        <label class="case-form-label">Service Fee</label>
                                    <?php endif; ?>
                                    <div class="input-group case-fee-input">
                                        <span class="input-group-text"><?= e(currencySymbol()) ?></span>
                                        <input type="number" step="0.01" min="0" name="services[fee][]"
                                               class="form-control case-form-control case-service-fee"
                                               value="<?= e((string) ($service['fee'] ?? '0')) ?>">
                                    </div>
                                </div>
                                <div class="col-md-2 col-lg-2">
                                    <button type="button" class="btn btn-soft-danger btn-sm w-100 js-remove-service"<?= $index === 0 ? ' hidden' : '' ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="case-services-total mt-3">
                        <span class="case-form-label d-inline">Total Fee</span>
                        <strong class="case-services-total-value"><?= formatCurrency(CaseService::caseServicesTotal($caseServices)) ?></strong>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6 col-lg-4">
                        <label class="case-form-label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-select case-form-control">
                            <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                <option value="<?= $p ?>" <?= ($case['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="case-form-label" for="deadline">Deadline</label>
                        <input type="date" id="deadline" name="deadline" class="form-control case-form-control"
                               value="<?= e($case['deadline'] ?? '') ?>">
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

<template id="case-service-row-template">
    <div class="case-service-row row g-2 align-items-end">
        <div class="col-md-6 col-lg-5">
            <input type="text" name="services[type][]" class="form-control case-form-control case-service-type"
                   placeholder="Property Transfer" required>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="input-group case-fee-input">
                <span class="input-group-text"><?= e(currencySymbol()) ?></span>
                <input type="number" step="0.01" min="0" name="services[fee][]"
                       class="form-control case-form-control case-service-fee" value="0">
            </div>
        </div>
        <div class="col-md-2 col-lg-2">
            <button type="button" class="btn btn-soft-danger btn-sm w-100 js-remove-service">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var list = document.getElementById("case-services-list");
    var template = document.getElementById("case-service-row-template");
    var addBtn = document.getElementById("add-service-row");
    var totalEl = document.querySelector(".case-services-total-value");
    var currencySymbol = <?= json_encode(currencySymbol()) ?>;
    var currencyLocale = <?= json_encode(currencyLocale()) ?>;

    function formatMoney(value) {
        return currencySymbol + Number(value || 0).toLocaleString(currencyLocale, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateTotal() {
        var total = 0;
        list.querySelectorAll(".case-service-fee").forEach(function(input) {
            total += parseFloat(input.value || "0") || 0;
        });
        if (totalEl) {
            totalEl.textContent = formatMoney(total);
        }
    }

    function refreshRemoveButtons() {
        var rows = list.querySelectorAll(".case-service-row");
        rows.forEach(function(row, index) {
            var removeBtn = row.querySelector(".js-remove-service");
            if (removeBtn) {
                removeBtn.hidden = rows.length === 1;
            }
        });
    }

    function bindRow(row) {
        row.querySelectorAll(".case-service-fee").forEach(function(input) {
            input.addEventListener("input", updateTotal);
        });

        var removeBtn = row.querySelector(".js-remove-service");
        if (removeBtn) {
            removeBtn.addEventListener("click", function() {
                if (list.querySelectorAll(".case-service-row").length === 1) {
                    return;
                }
                row.remove();
                refreshRemoveButtons();
                updateTotal();
            });
        }
    }

    list.querySelectorAll(".case-service-row").forEach(bindRow);
    updateTotal();

    if (addBtn && template) {
        addBtn.addEventListener("click", function() {
            var row = template.content.firstElementChild.cloneNode(true);
            list.appendChild(row);
            bindRow(row);
            refreshRemoveButtons();
            row.querySelector(".case-service-type").focus();
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
