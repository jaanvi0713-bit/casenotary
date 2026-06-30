<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('companies');
Auth::requireSuperAdmin();

$pageTitle = 'Companies';
$pageSubtitle = 'Manage workspaces and switch between companies';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyRequest()) {
        flash('error', 'Invalid request.');
        redirect('pages/companies.php');
    }

    try {
        $name = trim((string) ($_POST['name'] ?? ''));
        $companyId = CompanyService::create($name);
        TenantService::set($companyId);
        flash('success', 'Company created. You are now working in ' . e($name) . '.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect('pages/companies.php');
}

$companies = CompanyService::listAll();
$activeId  = TenantService::id();

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header appointment-calendar-header">
        <div>
            <h2 class="saas-card-title">Companies</h2>
            <p class="saas-card-subtitle mb-0">Create and switch between isolated company workspaces.</p>
        </div>
    </div>
    <div class="card-body p-4">
        <form method="post" class="row g-3 align-items-end mb-4">
            <?= CSRF::field() ?>
            <div class="col-md-8">
                <label class="form-label">New company name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Premier Notary Services">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-building-add me-1"></i> Create company</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Clients</th>
                        <th>Cases</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($companies === []): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No companies yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($companies as $company): ?>
                            <?php
                            $companyId = (int) $company['id'];
                            $displayName = trim((string) ($company['brand_name'] ?? ''));
                            if ($displayName === '') {
                                $displayName = (string) ($company['name'] ?? '');
                            }
                            $isActiveWorkspace = $companyId === $activeId;
                            $canDelete = CompanyService::canDelete($companyId);
                            ?>
                            <tr<?= $isActiveWorkspace ? ' class="company-row-current"' : '' ?>>
                                <td>
                                    <strong><?= e($displayName) ?></strong>
                                    <?php if ($isActiveWorkspace): ?>
                                        <span class="badge bg-primary ms-2">Current</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) ($company['client_count'] ?? 0) ?></td>
                                <td><?= (int) ($company['case_count'] ?? 0) ?></td>
                                <td><span class="text-success">Active</span></td>
                                <td class="text-end">
                                    <div class="company-actions d-inline-flex flex-wrap gap-1 justify-content-end">
                                        <?php if (!$isActiveWorkspace): ?>
                                            <form method="post" action="<?= url('actions/switch-company.php') ?>" class="d-inline">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="company_id" value="<?= $companyId ?>">
                                                <input type="hidden" name="return" value="<?= e(currentAdminReturn()) ?>">
                                                <button type="submit" class="btn btn-soft btn-sm">Switch</button>
                                            </form>
                                        <?php else: ?>
                                            <a href="<?= url('pages/settings.php?tab=branding') ?>" class="btn btn-soft btn-sm">Settings</a>
                                        <?php endif; ?>

                                        <?php if ($canDelete): ?>
                                            <button type="button"
                                                    class="btn btn-soft-danger btn-sm company-delete-btn"
                                                    title="Delete company and all related data"
                                                    data-company-id="<?= $companyId ?>"
                                                    data-company-name="<?= e($displayName) ?>"
                                                    data-confirm-message="<?= e(CompanyService::deleteConfirmMessage($companyId, $displayName)) ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="companyDeleteModal" tabindex="-1" aria-labelledby="companyDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content company-delete-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger" id="companyDeleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete company?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="company-delete-modal__alert mb-3" role="alert">
                    <strong>Warning:</strong> This action is permanent and cannot be undone.
                </div>
                <p class="mb-2 company-delete-modal__message" id="companyDeleteModalMessage"></p>
                <p class="text-muted small mb-0">All clients, cases, staff, documents, and settings for this workspace will be removed.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?= url('actions/company-action.php') ?>" id="companyDeleteForm">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="delete_company">
                    <input type="hidden" name="company_id" id="companyDeleteId" value="">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Yes, delete company
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
(function() {
    var modalEl = document.getElementById("companyDeleteModal");
    if (!modalEl || typeof bootstrap === "undefined") return;

    var modal = new bootstrap.Modal(modalEl);
    var messageEl = document.getElementById("companyDeleteModalMessage");
    var idInput = document.getElementById("companyDeleteId");

    document.querySelectorAll(".company-delete-btn").forEach(function(btn) {
        btn.addEventListener("click", function() {
            if (messageEl) {
                messageEl.textContent = btn.dataset.confirmMessage || "";
            }
            if (idInput) {
                idInput.value = btn.dataset.companyId || "";
            }
            modal.show();
        });
    });
})();
</script>
HTML;

require __DIR__ . '/../includes/footer.php';
