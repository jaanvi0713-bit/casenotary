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
                            $displayName = trim((string) ($company['brand_name'] ?? ''));
                            if ($displayName === '') {
                                $displayName = (string) ($company['name'] ?? '');
                            }
                            ?>
                            <tr<?= (int) $company['id'] === $activeId ? ' class="table-active"' : '' ?>>
                                <td>
                                    <strong><?= e($displayName) ?></strong>
                                    <?php if ((int) $company['id'] === $activeId): ?>
                                        <span class="badge bg-primary ms-2">Active</span>
                                    <?php endif; ?>
                                    <div class="small text-muted"><?= e($company['slug']) ?></div>
                                </td>
                                <td><?= (int) ($company['client_count'] ?? 0) ?></td>
                                <td><?= (int) ($company['case_count'] ?? 0) ?></td>
                                <td><?= e(ucfirst((string) ($company['status'] ?? 'active'))) ?></td>
                                <td class="text-end">
                                    <?php if ((int) $company['id'] !== $activeId): ?>
                                        <form method="post" action="<?= url('actions/switch-company.php') ?>" class="d-inline">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                                            <input type="hidden" name="return" value="<?= e(currentAdminReturn()) ?>">
                                            <button type="submit" class="btn btn-soft btn-sm">Switch</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="<?= url('pages/settings.php?tab=branding') ?>" class="btn btn-soft btn-sm">Settings</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
