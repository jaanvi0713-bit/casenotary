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
$weeklyTotals = [];
foreach ($weeklyData['payments'] as $i => $amount) {
    $weeklyTotals[] = $amount + ($weeklyData['invoices'][$i] ?? 0);
}
$recentCases        = getRecentCases(8);
$upcomingAppointments = getUpcomingAppointments(4);
$businessActivity   = getBusinessActivityFeed(20);

require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0 dashboard-page">
    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/clients.php') ?>" class="stat-card">
                <div class="stat-card-icon"><i class="bi bi-people"></i></div>
                <div class="stat-card-title">Total Clients · Last 7 days</div>
                <div class="stat-card-value"><?= number_format($stats['total_clients']) ?></div>
                <div class="stat-card-bottom">
                    <span class="stat-card-sub">New clients</span>
                    <?= kpiTrendBadge($trends['clients']) ?>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/payments.php') ?>" class="stat-card">
                <div class="stat-card-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-card-title">Total Payments · Last 7 days</div>
                <div class="stat-card-value-row">
                    <span class="stat-card-value"><?= formatCurrency($stats['total_revenue']) ?></span>
                    <?= kpiTrendBadge($trends['revenue'], true) ?>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/payments.php') ?>" class="stat-card">
                <div class="stat-card-icon"><i class="bi bi-receipt"></i></div>
                <div class="stat-card-title">Pending Invoices · Last 7 days</div>
                <div class="stat-card-value-row">
                    <span class="stat-card-value"><?= number_format($stats['pending_invoices']) ?></span>
                    <?= kpiTrendBadge($trends['invoices'], true) ?>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/cases.php') ?>" class="stat-card">
                <div class="stat-card-icon"><i class="bi bi-briefcase"></i></div>
                <div class="stat-card-title">Active Cases · Last 7 days</div>
                <div class="stat-card-value-row">
                    <span class="stat-card-value"><?= number_format($stats['active_cases']) ?></span>
                    <?= kpiTrendBadge($trends['cases'], true) ?>
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
                        <label class="chart-legend-item">
                            <input type="radio" name="areaLegend" checked disabled>
                            <span class="legend-dot legend-dot-dark"></span>
                            Total Revenue
                        </label>
                        <label class="chart-legend-item">
                            <input type="radio" name="areaLegend" disabled>
                            <span class="legend-dot legend-dot-primary"></span>
                            Total Payments
                        </label>
                    </div>
                    <div class="chart-period-toggle btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-period" data-period="day">Day</button>
                        <button type="button" class="btn btn-period" data-period="week">Week</button>
                        <button type="button" class="btn btn-period active" data-period="month">Month</button>
                    </div>
                </div>
                <div class="dash-chart-body dash-chart-body-lg">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="dash-chart-card h-100">
                <div class="dash-chart-header">
                    <div>
                        <h2 class="dash-chart-title">Profit this week</h2>
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
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments -->
    <?php if (!empty($upcomingAppointments)): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="dash-chart-card">
                <div class="dash-chart-header">
                    <h2 class="dash-chart-title">Upcoming Appointments</h2>
                    <a href="<?= url('pages/appointments.php') ?>" class="btn btn-sm btn-soft">View all</a>
                </div>
                <div class="dash-chart-body p-0 pt-0">
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
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Cases + Activity -->
    <div class="row g-4">
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
                        <div class="empty-state py-4">
                            <i class="bi bi-activity"></i>
                            <p class="mb-0">No recent activity</p>
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

    const monthLabels = ' . json_encode($chartData['labels']) . ';
    const revenueData = ' . json_encode($chartData['data']) . ';
    const paymentData = ' . json_encode($chartData['data']) . ';
    const invoiceData = ' . json_encode($invoiceData['data']) . ';

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
                        pointRadius: 4,
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
                        pointRadius: 4,
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
                        ticks: { font: { family: "Montserrat", size: 11 }, color: "#94a3b8" }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgba(0,24,44,0.06)" },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 11 },
                            color: "#94a3b8",
                            callback: function(v) { return formatMoney(v); }
                        }
                    }
                }
            }
        });
    }

    buildAreaChart(monthLabels, invoiceData, revenueData);

    document.querySelectorAll(".btn-period").forEach(function(btn) {
        btn.addEventListener("click", function() {
            document.querySelectorAll(".btn-period").forEach(function(b) { b.classList.remove("active"); });
            this.classList.add("active");
            const period = this.dataset.period;
            if (period === "month") {
                buildAreaChart(monthLabels, invoiceData, revenueData);
            } else if (period === "week") {
                buildAreaChart(' . json_encode($weeklyData['labels']) . ', ' . json_encode($weeklyData['payments']) . ', ' . json_encode($weeklyTotals) . ');
            } else {
                buildAreaChart(["Today"], [revenueData[revenueData.length-1]||0], [paymentData[paymentData.length-1]||0]);
            }
        });
    });

    const barCtx = document.getElementById("weeklyChart");
    if (barCtx) {
        new Chart(barCtx, {
            type: "bar",
            data: {
                labels: ' . json_encode($weeklyData['labels']) . ',
                datasets: [
                    {
                        label: "Payments",
                        data: ' . json_encode($weeklyData['payments']) . ',
                        backgroundColor: secondary,
                        borderRadius: 4,
                        borderSkipped: false,
                        barPercentage: 0.55
                    },
                    {
                        label: "Invoices",
                        data: ' . json_encode($weeklyData['invoices']) . ',
                        backgroundColor: primary,
                        borderRadius: { topLeft: 4, topRight: 4 },
                        borderSkipped: false,
                        barPercentage: 0.55
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                        ticks: { font: { family: "Montserrat", size: 11 }, color: "#94a3b8" }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: "rgba(0,24,44,0.06)" },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 11 },
                            color: "#94a3b8",
                            callback: function(v) { return formatMoney(v); }
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
