<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    Auth::logout();
    flash('success', 'Your client profile could not be found. Please contact support.');
    header('Location: ' . adminUrl('auth/login.php?portal=client'));
    exit;
}

$pageTitle = 'Dashboard';
$stats = getClientDashboardStats($clientId);
$recentCases = getClientRecentCases($clientId, 5);
$upcomingAppointments = getClientUpcomingAppointments($clientId, 5);
$user = Auth::user();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?= e(userFirstName($user)) ?>! Here is an overview of your account.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-primary">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-briefcase-fill"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Active Cases</span>
                    <h3 class="stat-value"><?= number_format($stats['active_cases']) ?></h3>
                </div>
            </div>
            <div class="stat-card-footer">
                <span class="stat-trend"><i class="bi bi-arrow-repeat"></i> In progress</span>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-secondary">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Pending Invoices</span>
                    <h3 class="stat-value"><?= number_format($stats['pending_invoices']) ?></h3>
                </div>
            </div>
            <div class="stat-card-footer">
                <span class="stat-trend"><i class="bi bi-clock"></i> Awaiting payment</span>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-info">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Documents</span>
                    <h3 class="stat-value"><?= number_format($stats['documents']) ?></h3>
                </div>
            </div>
            <div class="stat-card-footer">
                <span class="stat-trend"><i class="bi bi-folder2-open"></i> Available files</span>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-warning">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Upcoming Appointments</span>
                    <h3 class="stat-value"><?= number_format($stats['upcoming_appointments']) ?></h3>
                </div>
            </div>
            <div class="stat-card-footer">
                <span class="stat-trend"><i class="bi bi-calendar-check"></i> Scheduled</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="bi bi-briefcase me-2"></i>Recent Cases</h5>
            </div>
            <div class="content-card-body p-0">
                <?php if (empty($recentCases)): ?>
                    <div class="empty-state py-5">
                        <i class="bi bi-inbox"></i>
                        <p>No cases yet. Your assigned cases will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Case</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCases as $case): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= clientUrl('pages/case-view.php?id=' . $case['id']) ?>" class="cases-table-link">
                                                <strong><?= e($case['case_number']) ?></strong>
                                                <div class="text-muted small"><?= e($case['title']) ?></div>
                                            </a>
                                        </td>
                                        <td><?= statusBadge($case['status']) ?></td>
                                        <td class="text-muted"><?= formatDate($case['updated_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="content-card">
            <div class="content-card-header">
                <h5><i class="bi bi-calendar-event me-2"></i>Upcoming Appointments</h5>
            </div>
            <div class="content-card-body">
                <?php if (empty($upcomingAppointments)): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-calendar-x"></i>
                        <p>No upcoming appointments scheduled.</p>
                    </div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-date">
                                    <span class="day"><?= date('d', strtotime($appointment['start_time'])) ?></span>
                                    <span class="month"><?= date('M', strtotime($appointment['start_time'])) ?></span>
                                </div>
                                <div class="appointment-details">
                                    <strong><?= e($appointment['title']) ?></strong>
                                    <span><?= formatDateTime($appointment['start_time']) ?></span>
                                    <?= statusBadge($appointment['status']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
