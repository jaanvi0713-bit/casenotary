<?php
/** @var string $settingsNavTab */
/** @var bool $canManageSettings */
/** @var list<string> $editableRoleKeys */
?>
<ul class="nav nav-tabs settings-tabs px-3 pt-3" role="tablist">
    <?php if (Auth::can(RoleAccess::PERMISSION_PROFILE)): ?>
    <li class="nav-item">
        <a class="nav-link <?= $settingsNavTab === 'profile' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=profile') ?>">My Profile</a>
    </li>
    <?php endif; ?>
    <?php if ($canManageSettings): ?>
    <li class="nav-item">
        <a class="nav-link <?= $settingsNavTab === 'branding' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=branding') ?>">Branding</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $settingsNavTab === 'email' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=email') ?>">Email / SMTP</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $settingsNavTab === 'payments' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=payments') ?>">Payments</a>
    </li>
    <?php if ($editableRoleKeys !== []): ?>
    <li class="nav-item">
        <a class="nav-link <?= $settingsNavTab === 'roles' ? 'active' : '' ?>" href="<?= url('pages/settings-roles.php') ?>">Role access</a>
    </li>
    <?php endif; ?>
    <?php endif; ?>
</ul>
