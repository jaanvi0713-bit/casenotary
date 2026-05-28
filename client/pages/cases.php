<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    redirect('../../admin/auth/login.php');
}

$pageTitle = 'My Cases';
$cases = getClientCases($clientId);

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">My Cases</h2>
            <p class="saas-card-subtitle"><?= count($cases) ?> assigned case(s)</p>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($cases)): ?>
            <div class="empty-state py-5">
                <i class="bi bi-briefcase"></i>
                <p>No cases assigned yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table mb-0">
                    <thead>
                        <tr>
                            <th>Case #</th>
                            <th>Title</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $case): ?>
                            <tr>
                                <td><strong><?= e($case['case_number']) ?></strong></td>
                                <td><?= e($case['title']) ?></td>
                                <td><?= e($case['service_type']) ?></td>
                                <td><?= statusBadge($case['status']) ?></td>
                                <td class="text-muted"><?= formatDate($case['updated_at']) ?></td>
                                <td><a href="<?= clientUrl('pages/case-view.php?id=' . $case['id']) ?>" class="btn btn-soft btn-sm">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
