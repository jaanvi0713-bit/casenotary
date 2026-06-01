<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Welcome back, ' . userFullName(Auth::user());
$stats        = getDashboardStats();
$trends       = getDashboardTrends($stats);
$chartData    = getRevenueChartData();
$invoiceData  = getInvoiceChartData();
$weeklyData   = getWeeklyPaymentsChartData();
$hasRevenueChartData = chartSeriesHasData($chartData['data'])
    || chartSeriesHasData($invoiceData['data']);
$hasWeeklyChartData  = chartSeriesHasData($weeklyData['payments'])
    || chartSeriesHasData($weeklyData['invoices']);
$recentCases        = getRecentCases(8);
$upcomingAppointments = getUpcomingAppointments(4);
$businessActivity   = getBusinessActivityFeed(20);

require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0 dashboard-page">
    <!-- Stat cards -->
    <div class="row g-3 mb-4 dashboard-kpi-row">
        <div class="col-sm-6 col-xl-3">
<<<<<<< HEAD
            <a href="<?= url('pages/clients.php') ?>" class="kpi-card">
                <div class="stat-card-icon"><i class="bi bi-people"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Total Clients</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= number_format($stats['total_clients']) ?></span>
=======
            <a href="<?= url('pages/clients.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-people"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Total Clients</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= number_format($stats['total_clients']) ?></span>
>>>>>>> 6cceb44c73d32ab08a4c4a93921ea1b8740633ce
                        <?= kpiTrendBadge($trends['clients'], true) ?>
                    </div>
                    <span class="dash-stat-foot">New clients - Last 7 days</span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
<<<<<<< HEAD
            <a href="<?= url('pages/payments.php') ?>" class="kpi-card">
                <div class="stat-card-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Total Payments</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= formatCurrency($stats['total_revenue']) ?></span>
=======
            <a href="<?= url('pages/payments.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-briefcase"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Total Payments</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= formatCurrency($stats['total_revenue']) ?></span>
>>>>>>> 6cceb44c73d32ab08a4c4a93921ea1b8740633ce
                        <?= kpiTrendBadge($trends['revenue'], true) ?>
                    </div>
                    <span class="dash-stat-foot">Completed - Last 7 days</span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
<<<<<<< HEAD
            <a href="<?= url('pages/payments.php') ?>" class="kpi-card">
                <div class="stat-card-icon"><i class="bi bi-receipt"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Pending Invoices</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= number_format($stats['pending_invoices']) ?></span>
=======
            <a href="<?= url('pages/payments.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-calendar"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Pending Invoices</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= number_format($stats['pending_invoices']) ?></span>
>>>>>>> 6cceb44c73d32ab08a4c4a93921ea1b8740633ce
                        <?= kpiTrendBadge($trends['invoices'], true) ?>
                    </div>
                    <span class="dash-stat-foot">Awaiting payment - Last 7 days</span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
<<<<<<< HEAD
            <a href="<?= url('pages/cases.php') ?>" class="kpi-card">
                <div class="stat-card-icon"><i class="bi bi-briefcase"></i></div>
                <div class="stat-card-body">
                    <div class="stat-card-title">Active Cases</div>
                    <div class="stat-card-value-row">
                        <span class="stat-card-value"><?= number_format($stats['active_cases']) ?></span>
=======
            <a href="<?= url('pages/cases.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-chat-dots"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Active Cases</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= number_format($stats['active_cases']) ?></span>
>>>>>>> 6cceb44c73d32ab08a4c4a93921ea1b8740633ce
                        <?= kpiTrendBadge($trends['cases'], true) ?>
                    </div>
                    <span class="dash-stat-foot">In progress - Last 7 days</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Charts row -->
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="dash-chart-card">
                <div class="dash-chart-header">
                    <div class="chart-legend">
                        <span class="chart-legend-item-static">
                            <span class="legend-dot legend-dot-dark"></span>
                            Total Revenue
                        </span>
                        <span class="chart-legend-item-static">
                            <span class="legend-dot legend-dot-primary"></span>
                            Total Payments
                        </span>
                    </div>
                    <div class="chart-period-toggle btn-group btn-group-sm" role="group" aria-label="Chart period">
                        <button type="button" class="btn btn-period" data-period="day">Day</button>
                        <button type="button" class="btn btn-period" data-period="week">Week</button>
                        <button type="button" class="btn btn-period active" data-period="month">Month</button>
                    </div>
                </div>
                <div class="dash-chart-body dash-chart-body-lg">
                    <?php if (!$hasRevenueChartData): ?>
                        <div class="chart-empty-state">
                            <i class="bi bi-graph-up"></i>
                            <p class="mb-0">No revenue or payment data yet.</p>
                            <span class="chart-empty-hint">Completed payments and invoices will appear here.</span>
                        </div>
                    <?php else: ?>
                        <div class="chart-canvas-wrap">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="dash-chart-card h-100">
                <div class="dash-chart-header">
                    <div>
                        <h2 class="dash-chart-title">This week</h2>
                        <div class="chart-legend chart-legend-inline mt-1">
                            <span class="chart-legend-item-static">
                                <span class="legend-dot legend-dot-dark"></span> Payments
                            </span>
                            <span class="chart-legend-item-static">
                                <span class="legend-dot legend-dot-primary"></span> Invoices
                            </span>
                        </div>
                    </div>
                </div>
                <div class="dash-chart-body">
                    <?php if (!$hasWeeklyChartData): ?>
                        <div class="chart-empty-state">
                            <i class="bi bi-bar-chart"></i>
                            <p class="mb-0">No payments or invoices this week.</p>
                            <span class="chart-empty-hint">Daily activity from the last 7 days will show here.</span>
                        </div>
                    <?php else: ?>
                        <div class="chart-canvas-wrap chart-canvas-wrap-sm">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="dash-chart-card">
                <div class="dash-chart-header">
                    <h2 class="dash-chart-title">Upcoming Appointments</h2>
                    <a href="<?= url('pages/appointments.php') ?>" class="btn btn-sm btn-soft">View all</a>
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
                            <?php foreach ($upcomingAppointments as $appt): ?>
                                <li class="schedule-item">
                                    <div class="schedule-date">
                                        <span><?= date('d', strtotime($appt['start_time'])) ?></span>
                                        <small><?= date('M', strtotime($appt['start_time'])) ?></small>
                                    </div>
                                    <div class="schedule-info">
                                        <span class="schedule-title"><?= e($appt['title']) ?></span>
                                        <span class="schedule-meta"><?= formatDateTime($appt['start_time'], 'g:i A') ?> · <?= e(clientFullName($appt)) ?></span>
                                    </div>
                                    <?= statusBadge($appt['status']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Cases + Activity -->
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="dash-chart-card">
                <div class="dash-chart-header">
                    <h2 class="dash-chart-title">Recent Cases</h2>
                    <a href="<?= url('pages/cases.php') ?>" class="btn btn-sm btn-soft">View all</a>
                </div>
                <div class="table-toolbar">
                    <div class="table-search">
                        <i class="bi bi-search"></i>
                        <input type="search" id="caseTableSearch" class="form-control form-control-sm" placeholder="Filter cases...">
                    </div>
                    <select id="caseStatusFilter" class="form-select form-select-sm table-filter">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="waiting_for_client">Waiting</option>
                        <option value="completed">Completed</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <?php if (empty($recentCases)): ?>
                    <div class="empty-state empty-state-panel py-4">
                        <i class="bi bi-inbox"></i>
                        <p class="mb-0">No cases yet</p>
                        <span class="empty-state-hint">Recent cases will appear here once created.</span>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table saas-table mb-0" id="casesTable">
                        <thead>
                            <tr>
                                <th>Case</th>
                                <th>Client</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCases as $case): ?>
                                <tr data-status="<?= e($case['status']) ?>">
                                    <td>
                                        <a href="<?= url('pages/case-view.php?id=' . $case['id']) ?>" class="cases-table-link">
                                            <span class="table-primary"><?= e($case['case_number']) ?></span>
                                            <span class="table-secondary d-block"><?= e($case['title']) ?></span>
                                        </a>
                                    </td>
                                    <td><?= e(clientFullName($case)) ?></td>
                                    <td><?= statusBadge($case['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-4" id="activity">
            <div class="dash-chart-card activity-card h-100">
                <div class="dash-chart-header">
                    <div>
                        <h2 class="dash-chart-title">Activity</h2>
                        <span class="dash-chart-subtitle">Recent business events</span>
                    </div>
                </div>
                <div class="activity-scroll">
                    <?php if (empty($businessActivity)): ?>
                        <div class="empty-state empty-state-panel py-4">
                            <i class="bi bi-activity"></i>
                            <p class="mb-0">No recent activity</p>
                            <span class="empty-state-hint">Business events will show up here.</span>
                        </div>
                    <?php else: ?>
                        <ul class="activity-stream">
                            <?php foreach ($businessActivity as $item): ?>
                                <li class="activity-stream-item">
                                    <div class="activity-stream-icon <?= e($item['meta']['class']) ?>">
                                        <i class="bi <?= e($item['meta']['icon']) ?>"></i>
                                    </div>
                                    <div class="activity-stream-body">
                                        <p class="activity-stream-title"><?= e($item['title']) ?></p>
                                        <p class="activity-stream-detail"><?= e($item['detail']) ?></p>
                                    </div>
                                    <time class="activity-stream-time"><?= timeAgo($item['created_at']) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
document.addEventListener("DOMContentLoaded", function() {
    const primary = getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() || "#3aafa9";
    const secondary = getComputedStyle(document.documentElement).getPropertyValue("--secondary").trim() || "#00182c";
    const currencySymbol = ' . json_encode(currencySymbol()) . ';
    const formatMoney = function(v) {
        return currencySymbol + " " + Number(v).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const formatAxisMoney = function(v) {
        const n = Number(v);
        if (n >= 10000000) return currencySymbol + " " + (n / 10000000).toFixed(1) + "Cr";
        if (n >= 100000) return currencySymbol + " " + (n / 100000).toFixed(1) + "L";
        if (n >= 1000) return currencySymbol + " " + (n / 1000).toFixed(1) + "K";
        return currencySymbol + " " + n.toLocaleString("en-IN", { maximumFractionDigits: 0 });
    };

    const monthLabels = ' . json_encode($chartData['labels']) . ';
    const revenueData = ' . json_encode($chartData['data']) . ';
    const invoiceData = ' . json_encode($invoiceData['data']) . ';
    const weekLabels = ' . json_encode($weeklyData['labels']) . ';
    const weekPayments = ' . json_encode($weeklyData['payments']) . ';
    const weekInvoices = ' . json_encode($weeklyData['invoices']) . ';
    const hasRevenueChart = ' . ($hasRevenueChartData ? 'true' : 'false') . ';
    const hasWeeklyChart = ' . ($hasWeeklyChartData ? 'true' : 'false') . ';

    const areaCtx = document.getElementById("revenueChart");
    let areaChart = null;

    function buildAreaChart(labels, revData, payData) {
        if (!areaCtx) return;
        if (areaChart) areaChart.destroy();

        areaChart = new Chart(areaCtx, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Total Revenue",
                        data: revData,
                        borderColor: secondary,
                        backgroundColor: function(ctx) {
                            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                            g.addColorStop(0, "rgba(0, 24, 44, 0.18)");
                            g.addColorStop(1, "rgba(0, 24, 44, 0.01)");
                            return g;
                        },
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: labels.length > 1 ? 4 : 6,
                        pointBackgroundColor: secondary,
                        pointBorderColor: "#fff",
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    },
                    {
                        label: "Total Payments",
                        data: payData,
                        borderColor: primary,
                        backgroundColor: function(ctx) {
                            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                            g.addColorStop(0, "rgba(58, 175, 169, 0.25)");
                            g.addColorStop(1, "rgba(58, 175, 169, 0.02)");
                            return g;
                        },
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: labels.length > 1 ? 4 : 6,
                        pointBackgroundColor: primary,
                        pointBorderColor: "#fff",
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                layout: { padding: { left: 4, right: 8, top: 8, bottom: 0 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: secondary,
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { family: "Montserrat", size: 12 },
                        bodyFont: { family: "Montserrat", size: 12 },
                        callbacks: {
                            label: function(c) {
                                return c.dataset.label + ": " + formatMoney(c.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 11 },
                            color: "#94a3b8",
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 7
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgba(0,24,44,0.06)" },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 11 },
                            color: "#94a3b8",
                            padding: 6,
                            maxTicksLimit: 6,
                            callback: function(v) { return formatAxisMoney(v); }
                        }
                    }
                }
            }
        });
    }

    if (hasRevenueChart && areaCtx) {
        buildAreaChart(monthLabels, invoiceData, revenueData);

        document.querySelectorAll(".btn-period").forEach(function(btn) {
            btn.addEventListener("click", function() {
                document.querySelectorAll(".btn-period").forEach(function(b) { b.classList.remove("active"); });
                this.classList.add("active");
                const period = this.dataset.period;
                if (period === "month") {
                    buildAreaChart(monthLabels, invoiceData, revenueData);
                } else if (period === "week") {
                    buildAreaChart(weekLabels, weekInvoices, weekPayments);
                } else {
                    const todayPayments = weekPayments[weekPayments.length - 1] || 0;
                    const todayInvoices = weekInvoices[weekInvoices.length - 1] || 0;
                    buildAreaChart(["Today"], [todayInvoices], [todayPayments]);
                }
            });
        });
    }

    const barCtx = document.getElementById("weeklyChart");
    if (hasWeeklyChart && barCtx) {
        new Chart(barCtx, {
            type: "bar",
            data: {
                labels: weekLabels,
                datasets: [
                    {
                        label: "Payments",
                        data: weekPayments,
                        backgroundColor: secondary,
                        borderRadius: 4,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.75
                    },
                    {
                        label: "Invoices",
                        data: weekInvoices,
                        backgroundColor: primary,
                        borderRadius: { topLeft: 4, topRight: 4 },
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.75
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { left: 4, right: 8, top: 8, bottom: 0 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: secondary,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(c) {
                                return c.dataset.label + ": " + formatMoney(c.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 10 },
                            color: "#94a3b8",
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 7
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: "rgba(0,24,44,0.06)" },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 11 },
                            color: "#94a3b8",
                            padding: 6,
                            maxTicksLimit: 6,
                            callback: function(v) { return formatAxisMoney(v); }
                        }
                    }
                }
            }
        });
    }

    const searchInput = document.getElementById("caseTableSearch");
    const statusFilter = document.getElementById("caseStatusFilter");
    const rows = document.querySelectorAll("#casesTable tbody tr");

    function filterCases() {
        const q = (searchInput?.value || "").toLowerCase();
        const status = statusFilter?.value || "";
        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            const matchSearch = !q || text.includes(q);
            const matchStatus = !status || row.dataset.status === status;
            row.style.display = matchSearch && matchStatus ? "" : "none";
        });
    }

    searchInput?.addEventListener("input", filterCases);
    statusFilter?.addEventListener("change", filterCases);
});
</script>';

require __DIR__ . '/../includes/footer.php';
