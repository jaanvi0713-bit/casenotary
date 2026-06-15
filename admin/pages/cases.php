<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('cases');

$pageTitle = 'Cases';
$pageSubtitle = Auth::restrictsToAssignedCases()
    ? 'Cases assigned to you'
    : 'Legal case workspaces — manage clients, documents, billing & more';
$canManageCases = Auth::canManage(RoleAccess::PERMISSION_CASES);
$q = trim((string) ($_GET['q'] ?? ''));
$perPage = 10;
$page = requestPageNumber();
$totalCases = countCases($q);
$totalPages = max(1, (int) ceil($totalCases / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$cases = getCasesPaginated($page, $perPage, $q);

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">Case Management</h2>
            <p class="saas-card-subtitle"><?= $totalCases ?> total cases</p>
        </div>
        <?php if ($canManageCases): ?>
        <a href="<?= url('pages/case-form.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> New Case
        </a>
        <?php endif; ?>
    </div>
    <form method="get" class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="tableSearch" name="q" value="<?= e($q) ?>" placeholder="Search cases...">
        </div>
    </form>
    <div class="card-body p-0">
        <?php if (empty($cases)): ?>
            <div class="empty-state py-5">
                <i class="bi bi-briefcase"></i>
                <p>No cases found. <a href="<?= url('pages/case-form.php') ?>">Create your first case</a>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table mb-0" id="dataTable">
                    <thead>
                        <tr>
                            <th>Case #</th>
                            <th>Title</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Fee</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $case): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('pages/case-view.php?id=' . $case['id']) ?>" class="cases-table-link">
                                        <strong><?= e($case['case_number']) ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= url('pages/case-view.php?id=' . $case['id']) ?>" class="cases-table-link">
                                        <div class="case-cell">
                                            <strong><?= e($case['title']) ?></strong>
                                            <?php if ($case['company_name']): ?>
                                                <small><?= e($case['company_name']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </td>
                                <td><?= e(clientFullName($case)) ?></td>
                                <td><?= e($case['service_type']) ?></td>
                                <td><?= formatCurrency((float) $case['service_fee']) ?></td>
                                <td>
                                    <a href="<?= url('pages/case-view.php?id=' . $case['id']) ?>" class="btn btn-soft btn-sm">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= count($cases) ?> of <?= $totalCases ?> cases
                </small>
                <?= renderPaginationNav($page, $totalPages) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
