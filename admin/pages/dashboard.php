<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('dashboard');

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Welcome back, ' . userFullName(Auth::user());
$stats        = getDashboardStats();
$canViewInsights     = Auth::can(RoleAccess::PERMISSION_INSIGHTS);
$caseStatusBreakdown = $canViewInsights ? getCaseStatusBreakdown() : [];
$topServiceTypes     = $canViewInsights ? getTopServiceTypes(5) : [];
$totalCasesAll       = $canViewInsights ? array_sum($caseStatusBreakdown) : 0;
$trends       = getDashboardTrends($stats);
$chartData    = getRevenueChartData();
$invoiceData  = getInvoiceChartData();
$weeklyData   = getWeeklyPaymentsChartData();
$hasRevenueChartData = chartSeriesHasData($chartData['data'])
    || chartSeriesHasData($invoiceData['data']);
$hasWeeklyChartData  = chartSeriesHasData($weeklyData['payments'])
    || chartSeriesHasData($weeklyData['invoices']);
$recentCases          = getRecentCases(8);
$upcomingAppointments = getUpcomingAppointments(4);
$allBusinessActivity  = getBusinessActivityFeed(0);
$activityPerPage      = 10;
$activityPage         = requestPageNumber('activity_page');
$totalActivity        = count($allBusinessActivity);
$totalActivityPages   = max(1, (int) ceil($totalActivity / $activityPerPage));
if ($activityPage > $totalActivityPages) {
    $activityPage = $totalActivityPages;
}
$businessActivity = array_slice(
    $allBusinessActivity,
    paginationOffset($activityPage, $activityPerPage),
    $activityPerPage
);
$activityShowingFrom = $totalActivity > 0 ? paginationOffset($activityPage, $activityPerPage) + 1 : 0;
$activityShowingTo   = min($totalActivity, $activityPage * $activityPerPage);

require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0 dashboard-page">
    <!-- Stat cards -->
    <div class="dashboard-kpi-row mb-4">
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/clients.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-people"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Total Clients</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= number_format($stats['total_clients']) ?></span>
                        <?= kpiTrendBadge($trends['clients'], true) ?>
                    </div>
                    <span class="dash-stat-foot">New clients - Last 7 days</span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/payments.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-briefcase"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Total Payments</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= formatCurrency($stats['total_revenue']) ?></span>
                        <?= kpiTrendBadge($trends['revenue'], true) ?>
                    </div>
                    <span class="dash-stat-foot">Completed - Last 7 days</span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/payments.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-calendar"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Pending Invoices</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= number_format($stats['pending_invoices']) ?></span>
                        <?= kpiTrendBadge($trends['invoices'], true) ?>
                    </div>
                    <span class="dash-stat-foot">Awaiting payment - Last 7 days</span>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url('pages/cases.php') ?>" class="dash-stat-card">
                <div class="dash-stat-icon"><i class="bi bi-chat-dots"></i></div>
                <div class="dash-stat-content">
                    <span class="dash-stat-label">Active Cases</span>
                    <div class="dash-stat-value-row">
                        <span class="dash-stat-value"><?= number_format($stats['active_cases']) ?></span>
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

    <?php if ($canViewInsights): ?>
    <?php require __DIR__ . '/partials/dashboard-biz-insights.php'; ?>
    <?php endif; ?>

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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCases as $case): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('pages/case-view.php?id=' . $case['id']) ?>" class="cases-table-link">
                                            <span class="table-primary"><?= e($case['case_number']) ?></span>
                                            <span class="table-secondary d-block"><?= e($case['title']) ?></span>
                                        </a>
                                    </td>
                                    <td><?= e(clientFullName($case)) ?></td>
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
                    <?php if ($totalActivity === 0): ?>
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
                <?php if ($totalActivity > 0): ?>
                    <div class="activity-pagination d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
                        <small class="text-muted">
                            Showing <?= $activityShowingFrom ?>–<?= $activityShowingTo ?> of <?= $totalActivity ?> activities
                        </small>
                        <?= renderPaginationNav($activityPage, $totalActivityPages, 'activity_page', 'activity') ?>
                    </div>
                <?php endif; ?>
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

    const chartTheme = function() {
        return window.CaseNotaryTheme && window.CaseNotaryTheme.getChartTheme
            ? window.CaseNotaryTheme.getChartTheme()
            : {
                tick: "#94a3b8",
                grid: "rgba(0,24,44,0.06)",
                pointBorder: "#ffffff",
                gradSecondaryStart: "rgba(0, 24, 44, 0.18)",
                gradSecondaryEnd: "rgba(0, 24, 44, 0.01)",
                gradPrimaryStart: "rgba(58, 175, 169, 0.25)",
                gradPrimaryEnd: "rgba(58, 175, 169, 0.02)",
                tooltipBg: null
            };
    };

    const areaCtx = document.getElementById("revenueChart");
    let areaChart = null;
    let areaChartState = { labels: monthLabels, revData: invoiceData, payData: revenueData };

    function buildAreaChart(labels, revData, payData) {
        if (!areaCtx) return;
        areaChartState = { labels: labels, revData: revData, payData: payData };
        if (areaChart) areaChart.destroy();

        const theme = chartTheme();
        const tooltipBg = theme.tooltipBg || secondary;

        areaChart = new Chart(areaCtx, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Total Revenue",
                        data: revData,
                        borderColor: theme.revenueLineColor || secondary,
                        backgroundColor: function(ctx) {
                            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                            g.addColorStop(0, theme.gradSecondaryStart);
                            g.addColorStop(1, theme.gradSecondaryEnd);
                            return g;
                        },
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: labels.length > 1 ? 4 : 6,
                        pointBackgroundColor: secondary,
                        pointBorderColor: theme.pointBorder,
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    },
                    {
                        label: "Total Payments",
                        data: payData,
                        borderColor: primary,
                        backgroundColor: function(ctx) {
                            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 280);
                            g.addColorStop(0, theme.gradPrimaryStart);
                            g.addColorStop(1, theme.gradPrimaryEnd);
                            return g;
                        },
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: labels.length > 1 ? 4 : 6,
                        pointBackgroundColor: primary,
                        pointBorderColor: theme.pointBorder,
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
                        backgroundColor: tooltipBg,
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
                            color: theme.tick,
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 7
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: theme.grid },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 11 },
                            color: theme.tick,
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
    let barChart = null;

    function buildBarChart() {
        if (!barCtx) return;
        if (barChart) barChart.destroy();

        const theme = chartTheme();
        const tooltipBg = theme.tooltipBg || secondary;

        barChart = new Chart(barCtx, {
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
                        backgroundColor: tooltipBg,
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
                            color: theme.tick,
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: window.innerWidth < 576 ? 4 : 7
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: theme.grid },
                        border: { display: false },
                        ticks: {
                            font: { family: "Montserrat", size: 11 },
                            color: theme.tick,
                            padding: 6,
                            maxTicksLimit: 6,
                            callback: function(v) { return formatAxisMoney(v); }
                        }
                    }
                }
            }
        });
    }

    if (hasWeeklyChart && barCtx) {
        buildBarChart();
    }

    window.addEventListener("themechange", function() {
        if (hasRevenueChart && areaCtx) {
            buildAreaChart(areaChartState.labels, areaChartState.revData, areaChartState.payData);
        }
        if (hasWeeklyChart && barCtx) {
            buildBarChart();
        }
    });
' . ($canViewInsights ? '
    document.querySelectorAll(".biz-tab").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var tab = this.dataset.bizTab;
            document.querySelectorAll(".biz-tab").forEach(function(b) {
                b.classList.remove("active");
                b.setAttribute("aria-selected", "false");
            });
            this.classList.add("active");
            this.setAttribute("aria-selected", "true");
            document.querySelectorAll(".biz-tab-panel").forEach(function(p) { p.classList.remove("active"); });
            var panel = document.getElementById("biz-panel-" + tab);
            if (panel) panel.classList.add("active");
        });
    });

    var sparkCtx = document.getElementById("bizRevenueSparkline");
    if (sparkCtx && hasWeeklyChart) {
        new Chart(sparkCtx, {
            type: "line",
            data: {
                labels: weekLabels,
                datasets: [{
                    label: "Payments",
                    data: weekPayments,
                    borderColor: primary,
                    backgroundColor: function(ctx) {
                        var g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 64);
                        g.addColorStop(0, "rgba(58, 175, 169, 0.22)");
                        g.addColorStop(1, "rgba(58, 175, 169, 0.01)");
                        return g;
                    },
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: primary
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: secondary,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(c) {
                                return formatMoney(c.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: true }
                }
            }
        });
    }

    var donutCtx = document.getElementById("caseStatusDonut");
    if (donutCtx) {
        var statusCounts = ' . json_encode(array_values($caseStatusBreakdown)) . ';
        var statusColors = ["#f59e0b", "#3aafa9", "#6366f1", "#10b981", "#64748b"];
        var statusLabels = ["Pending", "In Progress", "Waiting for Client", "Completed", "Closed"];
        var donutBorder = getComputedStyle(document.documentElement).getPropertyValue("--white").trim() || "#fff";
        new Chart(donutCtx, {
            type: "doughnut",
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusColors,
                    borderWidth: 2,
                    borderColor: donutBorder,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: false,
                cutout: "68%",
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                return " " + c.label + ": " + c.parsed;
                            }
                        }
                    }
                }
            }
        });
    }
' : '') . '

    const searchInput = document.getElementById("caseTableSearch");
    const rows = document.querySelectorAll("#casesTable tbody tr");

    function filterCases() {
        const q = (searchInput?.value || "").toLowerCase();
        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            const matchSearch = !q || text.includes(q);
            row.style.display = matchSearch ? "" : "none";
        });
    }

    searchInput?.addEventListener("input", filterCases);
});
</script>';

require __DIR__ . '/../includes/footer.php';
