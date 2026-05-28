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

    <form method="post" action="<?= url('actions/case-action.php') ?>" class="case-form">
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
                            <?php foreach (['pending','in_progress','waiting_for_client','completed','closed'] as $st): ?>
                                <option value="<?= $st ?>" <?= ($case['status'] ?? 'pending') === $st ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $st)) ?>
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

            <div class="case-form-section case-form-section-last">
                <div class="case-form-section-head">
                    <i class="bi bi-cash-stack"></i>
                    <div>
                        <h2 class="case-form-section-title">Service & Schedule</h2>
                        <p class="case-form-section-desc">Billing, priority, and target completion date.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <label class="case-form-label" for="service_type">Service Type <span class="text-danger">*</span></label>
                        <input type="text" id="service_type" name="service_type" class="form-control case-form-control" required
                               placeholder="Property Transfer"
                               value="<?= e($case['service_type'] ?? ($_SESSION['old']['service_type'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="case-form-label" for="service_fee">Service Fee</label>
                        <div class="input-group case-fee-input">
                            <span class="input-group-text"><?= e(currencySymbol()) ?></span>
                            <input type="number" step="0.01" min="0" id="service_fee" name="service_fee"
                                   class="form-control case-form-control"
                                   value="<?= e((string) ($case['service_fee'] ?? '0')) ?>">
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="case-form-label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-select case-form-control">
                            <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                <option value="<?= $p ?>" <?= ($case['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
