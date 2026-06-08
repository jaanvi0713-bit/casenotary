<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$company = getCompanySettings();
$navItems = RoleAccess::navItemsForRole(Auth::role());
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <?= renderCompanyLogo('sidebar', $company, 'admin') ?>
            </div>
            <div class="brand-text">
                <span class="brand-name"><?= e(companyBrandName($company)) ?></span>
                <span class="brand-tag">Admin</span>
            </div>
        </div>
        <button type="button" class="sidebar-collapse-btn d-none d-lg-flex" id="sidebarCollapse" aria-label="Collapse sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($navItems as $item): ?>
                <li class="nav-item">
                    <a href="<?= url($item['href']) ?>"
                       class="nav-link <?= $currentPage === $item['page'] ? 'active' : '' ?>"
                       title="<?= e($item['label']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i>
                        <span class="nav-label"><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <?php if (Auth::isSuperAdmin() && TenantService::isEnabled()): ?>
        <?php $sidebarCompanies = TenantService::listActive(); ?>
        <div class="sidebar-company-switch">
            <form method="post" action="<?= url('actions/switch-company.php') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="return" value="<?= e(currentAdminReturn()) ?>">
                <label class="sidebar-company-switch__label" for="sidebarCompanySelect">Workspace</label>
                <select id="sidebarCompanySelect"
                        name="company_id"
                        class="sidebar-company-switch__select"
                        aria-label="Switch company"
                        onchange="this.form.submit()">
                    <?php foreach ($sidebarCompanies as $sidebarCompany): ?>
                        <option value="<?= (int) $sidebarCompany['id'] ?>"
                            <?= (int) $sidebarCompany['id'] === TenantService::id() ? 'selected' : '' ?>>
                            <?= e($sidebarCompany['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        <a href="<?= url('auth/logout.php') ?>" class="sidebar-logout" title="Sign Out">
            <i class="bi bi-box-arrow-right"></i>
            <span class="nav-label">Sign Out</span>
        </a>
    </div>
</aside>
