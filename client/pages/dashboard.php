<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    Auth::logout();
    flash('success', 'Your client profile could not be found. Please contact support.');
    header('Location: ' . clientLoginUrl(TenantService::id()));
    exit;
}

$user = Auth::user();
$pageTitle = 'Dashboard';
$pageSubtitle = 'Welcome back, ' . userFirstName($user);
$stats = getClientDashboardStats($clientId);
$recentCases = getClientRecentCases($clientId, 5);
$upcomingAppointments = getClientUpcomingAppointments($clientId, 5);

require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0 dashboard-page">
    <div class="row g-3 mb-4 dashboard-kpi-row">
        <div class="col-sm-6 col-xl-3">
            <a href="<?= clientUrl('pages/cases.php') ?>" class="kpi-card kpi-card--primary">
                <div class="stat-card-icon"><i class="bi bi-briefcase"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Active Cases</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= number_format($stats['active_cases']) ?></span>
                    </div>
                    <div class="stat-card-footer">
                        <span class="stat-card-sub">In progress</span>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= clientUrl('pages/payments.php') ?>" class="kpi-card kpi-card--amber">
                <div class="stat-card-icon"><i class="bi bi-receipt"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Pending Invoices</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= number_format($stats['pending_invoices']) ?></span>
                    </div>
                    <div class="stat-card-footer">
                        <span class="stat-card-sub">Awaiting payment</span>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= clientUrl('pages/cases.php') ?>" class="kpi-card kpi-card--indigo">
                <div class="stat-card-icon"><i class="bi bi-file-earmark-text"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Documents</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= number_format($stats['documents']) ?></span>
                    </div>
                    <div class="stat-card-footer">
                        <span class="stat-card-sub">Available files</span>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= clientUrl('pages/appointments.php') ?>" class="kpi-card kpi-card--blue">
                <div class="stat-card-icon"><i class="bi bi-calendar-event"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Upcoming Appointments</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= number_format($stats['upcoming_appointments']) ?></span>
                    </div>
                    <div class="stat-card-footer">
                        <span class="stat-card-sub">Scheduled</span>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="dash-chart-card h-100">
                <div class="dash-chart-header">
                    <h2 class="dash-chart-title">Recent Cases</h2>
                    <a href="<?= clientUrl('pages/cases.php') ?>" class="btn btn-sm btn-soft">View all</a>
                </div>
                <?php if (empty($recentCases)): ?>
                    <div class="dash-chart-body">
                        <div class="empty-state empty-state-panel py-4">
                            <i class="bi bi-inbox"></i>
                            <p class="mb-0">No cases yet</p>
                            <span class="empty-state-hint">Your assigned cases will appear here.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table mb-0">
                            <thead>
                                <tr>
                                    <th>Case</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCases as $case): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= clientUrl('pages/case-view.php?id=' . $case['id']) ?>" class="cases-table-link">
                                                <span class="table-primary"><?= e($case['case_number']) ?></span>
                                                <span class="table-secondary d-block"><?= e($case['title']) ?></span>
                                            </a>
                                        </td>
                                        <td class="text-muted"><?= formatDate($case['updated_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="dash-chart-card h-100">
                <div class="dash-chart-header">
                    <div>
                        <h2 class="dash-chart-title">Upcoming Appointments</h2>
                        <span class="dash-chart-subtitle">Scheduled and in-progress sessions</span>
                    </div>
                    <a href="<?= clientUrl('pages/appointments.php') ?>" class="btn btn-sm btn-soft">View calendar</a>
                </div>
                <div class="dash-chart-body p-0 pt-0">
                    <?php if (empty($upcomingAppointments)): ?>
                        <div class="empty-state empty-state-panel py-4">
                            <i class="bi bi-calendar-x"></i>
                            <p class="mb-0">No upcoming appointments</p>
                            <span class="empty-state-hint">Scheduled sessions will appear here.</span>
                        </div>
                    <?php else: ?>
                        <ul class="schedule-list schedule-list-compact">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <?php $apptStart = appointmentEffectiveStart($appointment); ?>
                                <li class="schedule-item">
                                    <div class="schedule-date">
                                        <span><?= $apptStart ? date('d', strtotime($apptStart)) : '—' ?></span>
                                        <small><?= $apptStart ? date('M', strtotime($apptStart)) : '' ?></small>
                                    </div>
                                    <div class="schedule-info">
                                        <span class="schedule-title"><?= e($appointment['title']) ?></span>
                                        <span class="schedule-meta"><?= formatAppointmentScheduleMeta($appointment) ?></span>
                                    </div>
                                    <?= statusBadge($appointment['status']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
