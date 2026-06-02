<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Cases';
$pageSubtitle = 'Legal case workspaces — manage clients, documents, billing & more';
$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$priorityFilter = trim((string) ($_GET['priority'] ?? ''));
$perPage = 10;
$page = requestPageNumber();
$totalCases = countCases($q, $statusFilter, $priorityFilter);
$totalPages = max(1, (int) ceil($totalCases / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$cases = getCasesPaginated($page, $perPage, $q, $statusFilter, $priorityFilter);

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">Case Management</h2>
            <p class="saas-card-subtitle"><?= $totalCases ?> total cases</p>
        </div>
        <a href="<?= url('pages/case-form.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> New Case
        </a>
    </div>
    <form method="get" class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="tableSearch" name="q" value="<?= e($q) ?>" placeholder="Search cases...">
        </div>
        <select class="form-select form-select-sm table-filter" id="statusFilter" name="status">
            <option value="">All statuses</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="waiting_for_client" <?= $statusFilter === 'waiting_for_client' ? 'selected' : '' ?>>Waiting for Client</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
        <select class="form-select form-select-sm table-filter" id="priorityFilter" name="priority">
            <option value="">All priorities</option>
            <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
            <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
        </select>
        <button type="submit" class="btn btn-light btn-sm">Apply</button>
        <a href="<?= url('pages/cases.php') ?>" class="btn btn-soft btn-sm">Reset</a>
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
                            <th>Priority</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $case): ?>
                            <tr data-status="<?= e($case['status']) ?>" data-priority="<?= e($case['priority']) ?>">
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
                                <td><?= priorityBadge($case['priority']) ?></td>
                                <td><?= formatDate($case['deadline']) ?></td>
                                <td><?= statusBadge($case['status']) ?></td>
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
