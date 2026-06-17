<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('insights');

$pageTitle    = 'Business Insights';
$pageSubtitle = 'Intelligence hub — ' . date('F Y');
$stats        = getDashboardStats();
$trends       = getDashboardTrends($stats);
$caseStatusBreakdown = getCaseStatusBreakdown();
$topServiceTypes     = getTopServiceTypes(5);
$totalCasesAll       = array_sum($caseStatusBreakdown);
$weeklyData          = getWeeklyPaymentsChartData();
$hasWeeklyChartData  = chartSeriesHasData($weeklyData['payments'])
    || chartSeriesHasData($weeklyData['invoices']);
$weekPayTotal        = array_sum($weeklyData['payments']);
$weekInvTotal        = array_sum($weeklyData['invoices']);
$peakPayAmt          = !empty($weeklyData['payments']) ? max($weeklyData['payments']) : 0;
$peakPayDay          = '';
foreach ($weeklyData['payments'] as $i => $v) {
    if ((float) $v === (float) $peakPayAmt && $peakPayAmt > 0) {
        $peakPayDay = $weeklyData['labels'][$i] ?? '';
        break;
    }
}
$avgDailyPay         = $weekPayTotal / 7;
$hub                 = InsightsService::getHubData($stats);
$livePrediction      = InsightsService::getLivePredictionPayload($stats);
$companySettings     = getCompanySettings();
$revenueChartData    = $hub['revenue_chart'];
$invoiceChartData    = $hub['invoice_chart'];
$cohortChartData     = $hub['client_cohorts'];

require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0 insights-page">
    <?php require __DIR__ . '/partials/dashboard-biz-insights.php'; ?>
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
    const rootTheme = (document.documentElement.getAttribute("data-theme") || "").toLowerCase();
    const rootBsTheme = (document.documentElement.getAttribute("data-bs-theme") || "").toLowerCase();
    const bodyTheme = (document.body && document.body.getAttribute("data-theme") ? document.body.getAttribute("data-theme") : "").toLowerCase();
    const isDarkTheme = rootTheme === "dark" || rootBsTheme === "dark" || bodyTheme === "dark";
    const chartTextColor = isDarkTheme ? "#ffffff" : "#475569";
    const chartGridColor = isDarkTheme ? "rgba(148, 163, 184, 0.26)" : "rgba(148, 163, 184, 0.2)";
    const chartTooltipBg = isDarkTheme ? "#0f172a" : "#ffffff";
    const chartTooltipTitle = isDarkTheme ? "#ffffff" : "#0f172a";
    const chartTooltipBody = isDarkTheme ? "#e2e8f0" : "#334155";
    const chartTooltipBorder = isDarkTheme ? "rgba(148, 163, 184, 0.4)" : "rgba(148, 163, 184, 0.22)";
    if (typeof Chart !== "undefined") {
        Chart.defaults.color = chartTextColor;
        Chart.defaults.borderColor = chartGridColor;
        if (Chart.defaults.plugins && Chart.defaults.plugins.legend && Chart.defaults.plugins.legend.labels) {
            Chart.defaults.plugins.legend.labels.color = chartTextColor;
        }
    }
    const weekLabels = ' . json_encode($weeklyData['labels']) . ';
    const weekPayments = ' . json_encode($weeklyData['payments']) . ';
    const weekInvoices = ' . json_encode($weeklyData['invoices']) . ';
    const hasWeeklyChart = ' . ($hasWeeklyChartData ? 'true' : 'false') . ';

    var bizCaseDonutChart = null;
    var bizRevenueChart = null;

    function buildBizCaseDonut() {
        var donutCtx = document.getElementById("caseStatusDonut");
        if (!donutCtx || bizCaseDonutChart) return;

        var statusCounts = ' . json_encode(array_values($caseStatusBreakdown)) . ';
        var statusColors = ["#6366f1", "#3aafa9", "#3b82f6", "#10b981", "#64748b"];
        var statusLabels = ["Pending", "In Progress", "Waiting for Client", "Completed", "Closed"];
        var cardEl = donutCtx.closest(".biz-mini-chart-card");
        var donutBorder = cardEl ? getComputedStyle(cardEl).backgroundColor : "#fff";
        bizCaseDonutChart = new Chart(donutCtx, {
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
            if (tab === "cases") {
                buildBizCaseDonut();
            }
            if (tab === "overview" || tab === "intelligence") {
                buildHubCharts();
            }
            if (tab === "audience") {
                buildCohortChart();
            }
        });
    });

    var requestedTab = new URLSearchParams(window.location.search).get("tab");
    if (requestedTab) {
        var desiredBtn = document.querySelector(".biz-tab[data-biz-tab=\"" + requestedTab + "\"]");
        if (desiredBtn) {
            desiredBtn.click();
        }
    }

    var hubChartsBuilt = false;
    function buildHubCharts() {
        if (hubChartsBuilt) return;
        var ov = document.getElementById("bizOverviewRevenueChart");
        if (ov) {
            new Chart(ov, {
                type: "bar",
                data: {
                    labels: ' . json_encode($revenueChartData['labels']) . ',
                    datasets: [{
                        label: "Revenue",
                        data: ' . json_encode($revenueChartData['data']) . ',
                        backgroundColor: "rgba(58, 175, 169, 0.75)",
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: chartTooltipBg,
                            titleColor: chartTooltipTitle,
                            bodyColor: chartTooltipBody,
                            borderColor: chartTooltipBorder,
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: { grid: { color: chartGridColor }, ticks: { color: chartTextColor } },
                        y: { beginAtZero: true, grid: { color: chartGridColor }, ticks: { color: chartTextColor } }
                    }
                }
            });
        }
        var intel = document.getElementById("bizIntelCompareChart");
        if (intel) {
            new Chart(intel, {
                type: "line",
                data: {
                    labels: ' . json_encode($revenueChartData['labels']) . ',
                    datasets: [
                        { label: "Payments", data: ' . json_encode($revenueChartData['data']) . ', borderColor: primary, tension: 0.3 },
                        { label: "Invoiced", data: ' . json_encode($invoiceChartData['data']) . ', borderColor: "#6366f1", borderDash: [5,4], tension: 0.3 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: chartTextColor } },
                        tooltip: {
                            backgroundColor: chartTooltipBg,
                            titleColor: chartTooltipTitle,
                            bodyColor: chartTooltipBody,
                            borderColor: chartTooltipBorder,
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: { grid: { color: chartGridColor }, ticks: { color: chartTextColor } },
                        y: { beginAtZero: true, grid: { color: chartGridColor }, ticks: { color: chartTextColor } }
                    }
                }
            });
        }
        hubChartsBuilt = true;
    }

    function buildCohortChart() {
        var ctx = document.getElementById("bizCohortChart");
        if (!ctx || ctx.dataset.built) return;
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: ' . json_encode($cohortChartData['labels']) . ',
                datasets: [{ label: "New clients", data: ' . json_encode($cohortChartData['data']) . ', backgroundColor: "#3b82f6", borderRadius: 6 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: chartTooltipBg,
                        titleColor: chartTooltipTitle,
                        bodyColor: chartTooltipBody,
                        borderColor: chartTooltipBorder,
                        borderWidth: 1
                    }
                },
                scales: {
                    x: { grid: { color: chartGridColor }, ticks: { color: chartTextColor } },
                    y: { beginAtZero: true, grid: { color: chartGridColor }, ticks: { stepSize: 1, color: chartTextColor } }
                }
            }
        });
        ctx.dataset.built = "1";
    }

    buildHubCharts();

    var sparkCtx = document.getElementById("bizRevenueChart");
    if (sparkCtx && hasWeeklyChart) {
        var chartPayColor = "#14b8a6";
        var chartPayFill = "rgba(20, 184, 166, 0.18)";
        var chartInvColor = "#6366f1";
        var chartGrid = isDarkTheme ? "rgba(148, 163, 184, 0.3)" : "rgba(148, 163, 184, 0.35)";
        var chartTick = isDarkTheme ? "#ffffff" : "#64748b";

        bizRevenueChart = new Chart(sparkCtx, {
            type: "line",
            data: {
                labels: weekLabels,
                datasets: [
                    {
                        label: "Payments",
                        data: weekPayments,
                        borderColor: chartPayColor,
                        backgroundColor: function(ctx) {
                            var g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 160);
                            g.addColorStop(0, "rgba(20, 184, 166, 0.35)");
                            g.addColorStop(1, "rgba(20, 184, 166, 0.02)");
                            return g;
                        },
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: "#fff",
                        pointBorderColor: chartPayColor,
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: chartPayColor,
                        pointHoverBorderColor: "#fff",
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: "Invoiced",
                        data: weekInvoices,
                        borderColor: chartInvColor,
                        backgroundColor: "transparent",
                        borderWidth: 2.5,
                        borderDash: [6, 4],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: "#fff",
                        pointBorderColor: chartInvColor,
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: chartInvColor,
                        pointHoverBorderColor: "#fff",
                        pointHoverBorderWidth: 2
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
                        backgroundColor: chartTooltipBg,
                        titleColor: chartTooltipTitle,
                        bodyColor: chartTooltipBody,
                        borderColor: chartTooltipBorder,
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: true,
                        callbacks: {
                            label: function(c) {
                                return " " + c.dataset.label + ": " + formatMoney(c.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: chartGrid, drawBorder: false },
                        ticks: {
                            color: chartTick,
                            font: { size: 11, weight: "600" },
                            maxRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartGrid, drawBorder: false },
                        ticks: {
                            color: chartTick,
                            font: { size: 11 },
                            maxTicksLimit: 5,
                            callback: function(v) {
                                if (v >= 1000) return currencySymbol + (v / 1000).toFixed(v >= 10000 ? 0 : 1) + "k";
                                return currencySymbol + " " + v;
                            }
                        }
                    }
                }
            }
        });

        document.querySelectorAll(".biz-chart-legend-btn").forEach(function(btn) {
            btn.addEventListener("click", function() {
                if (!bizRevenueChart) return;
                var key = this.dataset.chartSeries;
                var idx = key === "payments" ? 0 : 1;
                var visible = bizRevenueChart.isDatasetVisible(idx);
                bizRevenueChart.setDatasetVisibility(idx, !visible);
                bizRevenueChart.update();
                this.classList.toggle("active", !visible);
                this.setAttribute("aria-pressed", !visible ? "true" : "false");
            });
        });
    }

    var bizAiForecastChart = null;
    var aiLiveUrl = ' . json_encode(url('actions/insights-live.php')) . ';
    var aiInitial = ' . json_encode($livePrediction) . ';
    var aiPollTimer = null;

    function animateAiNumber(el, target, isMoney) {
        if (!el) return;
        var start = parseFloat(el.dataset.value || "0");
        if (isNaN(start)) start = 0;
        if (Math.abs(start - target) < 0.01) {
            el.dataset.value = String(target);
            if (isMoney) {
                el.textContent = formatMoney(target);
            } else if (el.dataset.aiMetric === "collection_rate") {
                el.textContent = target.toFixed(1) + "%";
            } else {
                el.textContent = String(Math.round(target));
            }
            return;
        }
        var startTime = null;
        var duration = 900;
        function step(ts) {
            if (!startTime) startTime = ts;
            var p = Math.min(1, (ts - startTime) / duration);
            var eased = 1 - Math.pow(1 - p, 3);
            var current = start + (target - start) * eased;
            el.dataset.value = String(current);
            if (isMoney) {
                el.textContent = formatMoney(current);
            } else if (el.dataset.aiMetric === "collection_rate") {
                el.textContent = current.toFixed(1) + "%";
            } else {
                el.textContent = String(Math.round(current));
            }
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function renderAiSignals(signals) {
        var box = document.getElementById("bizAiSignals");
        if (!box || !signals || !signals.length) return;
        box.innerHTML = signals.slice(0, 2).map(function(s) {
            return "<span class=\"biz-ai-signal-pill biz-ai-signal-pill--" + (s.type || "info") + "\">" + s.text + "</span>";
        }).join("");
    }

    function buildAiForecastChart(forecast) {
        var ctx = document.getElementById("bizAiForecastChart");
        if (!ctx || !forecast) return;
        var labels = forecast.labels || [];
        var tickEvery = Math.max(1, Math.floor(labels.length / 6));
        var displayLabels = labels.map(function(l, i) { return i % tickEvery === 0 ? l : ""; });

        if (bizAiForecastChart) {
            bizAiForecastChart.data.labels = displayLabels;
            bizAiForecastChart.data.datasets[0].data = forecast.base || [];
            bizAiForecastChart.data.datasets[1].data = forecast.best || [];
            bizAiForecastChart.data.datasets[2].data = forecast.worst || [];
            bizAiForecastChart.update("none");
            return;
        }

        bizAiForecastChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: displayLabels,
                datasets: [
                    { label: "Base", data: forecast.base || [], borderColor: "#3aafa9", backgroundColor: "rgba(58,175,169,0.12)", fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4 },
                    { label: "Best", data: forecast.best || [], borderColor: "#10b981", borderDash: [6, 4], tension: 0.35, borderWidth: 2, pointRadius: 0, fill: false },
                    { label: "Worst", data: forecast.worst || [], borderColor: "#f59e0b", borderDash: [4, 4], tension: 0.35, borderWidth: 2, pointRadius: 0, fill: false }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 800 },
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: true, position: "bottom", labels: { boxWidth: 10, font: { size: 10 }, color: chartTextColor } },
                    tooltip: {
                        backgroundColor: chartTooltipBg,
                        titleColor: chartTooltipTitle,
                        bodyColor: chartTooltipBody,
                        borderColor: chartTooltipBorder,
                        borderWidth: 1,
                        callbacks: {
                            label: function(c) { return " " + c.dataset.label + ": " + formatMoney(c.parsed.y); }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, font: { size: 9 }, color: chartTextColor } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: chartTextColor,
                            callback: function(v) {
                                if (v >= 1000) return currencySymbol + (v / 1000).toFixed(0) + "k";
                                return currencySymbol + " " + v;
                            },
                            font: { size: 10 }
                        }
                    }
                }
            }
        });
    }

    function applyAiLive(data) {
        if (!data) return;
        var card = document.getElementById("bizAiLiveCard");
        if (card) card.classList.add("biz-ai-live-card--pulse");

        document.querySelectorAll("[data-ai-scenario]").forEach(function(el) {
            var key = el.dataset.aiScenario;
            if (data[key] !== undefined) animateAiNumber(el, parseFloat(data[key]), true);
        });

        var metrics = data.live_metrics || {};
        var mRev = document.querySelector("[data-ai-metric=\"monthly_revenue\"]");
        if (mRev && metrics.monthly_revenue !== undefined) animateAiNumber(mRev, metrics.monthly_revenue, true);
        var mCases = document.querySelector("[data-ai-metric=\"active_cases\"]");
        if (mCases && metrics.active_cases !== undefined) animateAiNumber(mCases, metrics.active_cases, false);
        var mColl = document.querySelector("[data-ai-metric=\"collection_rate\"]");
        if (mColl && metrics.collection_rate !== undefined) animateAiNumber(mColl, metrics.collection_rate, false);

        var conf = document.getElementById("bizAiConfidence");
        if (conf && data.confidence) {
            conf.textContent = data.confidence.charAt(0).toUpperCase() + data.confidence.slice(1);
            conf.className = "biz-ai-confidence biz-ai-confidence--" + data.confidence;
        }

        var updated = document.getElementById("bizAiUpdated");
        if (updated) {
            var d = data.updated_at ? new Date(data.updated_at) : new Date();
            updated.textContent = "Updated " + d.toLocaleTimeString();
        }

        renderAiSignals(data.signals || []);
        buildAiForecastChart(data.forecast || {});

        setTimeout(function() {
            if (card) card.classList.remove("biz-ai-live-card--pulse");
        }, 600);
    }

    function pollAiLive() {
        fetch(aiLiveUrl, { credentials: "same-origin", headers: { "Accept": "application/json" } })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(data) { if (data && !data.error) applyAiLive(data); })
            .catch(function() {});
    }

    applyAiLive(aiInitial);
    aiPollTimer = setInterval(pollAiLive, 5000);
    document.addEventListener("visibilitychange", function() {
        if (document.hidden) {
            if (aiPollTimer) clearInterval(aiPollTimer);
        } else {
            pollAiLive();
            aiPollTimer = setInterval(pollAiLive, 5000);
        }
    });

    // Rebuild charts with correct palette after a runtime theme toggle.
    window.addEventListener("themechange", function() {
        window.location.reload();
    });
});
</script>';

require __DIR__ . '/../includes/footer.php';
