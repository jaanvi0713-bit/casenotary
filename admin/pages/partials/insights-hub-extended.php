<?php
/** @var array $hub */
$health = (int) ($hub['health_score'] ?? 0);
$forecast = $hub['forecast'] ?? [];
$prediction = $hub['prediction_suite'] ?? [];
$operational = $hub['operational'] ?? [];
$revChart = $hub['revenue_chart'] ?? ['labels' => [], 'data' => []];
$invChart = $hub['invoice_chart'] ?? ['labels' => [], 'data' => []];
$cohorts = $hub['client_cohorts'] ?? ['labels' => [], 'data' => []];
?>

<div class="biz-tab-panel active" id="biz-panel-overview">
    <?php
    $healthLabel = $health >= 80 ? 'Excellent' : ($health >= 60 ? 'Good' : ($health >= 40 ? 'Fair' : 'Needs attention'));
    ?>
    <div class="biz-hub-top">
        <div class="biz-health-card">
            <div class="biz-health-ring" style="--health: <?= $health ?>">
                <span class="biz-health-value"><?= $health ?></span>
            </div>
            <h3 class="biz-health-title">Business health</h3>
            <span class="biz-health-badge"><?= e($healthLabel) ?></span>
            <p class="biz-health-desc">Collections, cases &amp; revenue combined.</p>
        </div>
        <div class="biz-ai-live-card" id="bizAiLiveCard">
            <div class="biz-ai-live-accent" aria-hidden="true"></div>
            <div class="biz-ai-live-head">
                <div class="biz-ai-live-head-main">
                    <p class="biz-ai-live-kicker"><span class="biz-ai-live-dot" aria-hidden="true"></span> Live forecast</p>
                    <h3 class="biz-ai-live-title">30-day revenue outlook</h3>
                </div>
                <div class="biz-ai-live-meta">
                    <span class="biz-ai-confidence biz-ai-confidence--<?= e((string) ($prediction['confidence'] ?? 'low')) ?>" id="bizAiConfidence"><?= e(ucfirst((string) ($prediction['confidence'] ?? 'low'))) ?></span>
                    <span class="biz-ai-updated" id="bizAiUpdated">Syncing…</span>
                </div>
            </div>
            <div class="biz-ai-chart-wrap">
                <canvas id="bizAiForecastChart" height="120"></canvas>
            </div>
            <div class="biz-ai-stats-grid" id="bizAiMetrics">
                <div class="biz-ai-stat biz-ai-stat--base">
                    <span class="biz-ai-stat-label">Base</span>
                    <strong class="biz-ai-stat-value" data-ai-scenario="base" data-value="<?= (float) ($prediction['base'] ?? 0) ?>"><?= formatCurrency((float) ($prediction['base'] ?? 0)) ?></strong>
                </div>
                <div class="biz-ai-stat biz-ai-stat--best">
                    <span class="biz-ai-stat-label">Best</span>
                    <strong class="biz-ai-stat-value" data-ai-scenario="best" data-value="<?= (float) ($prediction['best'] ?? 0) ?>"><?= formatCurrency((float) ($prediction['best'] ?? 0)) ?></strong>
                </div>
                <div class="biz-ai-stat biz-ai-stat--worst">
                    <span class="biz-ai-stat-label">Worst</span>
                    <strong class="biz-ai-stat-value" data-ai-scenario="worst" data-value="<?= (float) ($prediction['worst'] ?? 0) ?>"><?= formatCurrency((float) ($prediction['worst'] ?? 0)) ?></strong>
                </div>
                <div class="biz-ai-stat">
                    <span class="biz-ai-stat-label">MTD revenue</span>
                    <strong class="biz-ai-stat-value" data-ai-metric="monthly_revenue"><?= formatCurrency((float) ($stats['monthly_revenue'] ?? 0)) ?></strong>
                </div>
                <div class="biz-ai-stat">
                    <span class="biz-ai-stat-label">Active cases</span>
                    <strong class="biz-ai-stat-value" data-ai-metric="active_cases"><?= number_format((int) ($stats['active_cases'] ?? 0)) ?></strong>
                </div>
                <div class="biz-ai-stat">
                    <span class="biz-ai-stat-label">Collection</span>
                    <strong class="biz-ai-stat-value" data-ai-metric="collection_rate"><?= number_format((float) ($stats['collection_rate'] ?? 0), 1) ?>%</strong>
                </div>
            </div>
            <div class="biz-ai-signals" id="bizAiSignals" aria-live="polite"></div>
        </div>
    </div>

    <p class="biz-section-label">Live alerts</p>
    <div class="biz-alert-grid">
        <?php foreach ($hub['alerts'] ?? [] as $alert): ?>
        <div class="biz-alert-card biz-alert-card--<?= e($alert['type']) ?>">
            <i class="bi <?= e($alert['icon']) ?>"></i>
            <div>
                <strong><?= e($alert['title']) ?></strong>
                <p><?= e($alert['message']) ?></p>
                <?php if (!empty($alert['link'])): ?>
                    <a href="<?= e($alert['link']) ?>" class="biz-alert-link">View details →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="biz-section-label">Data sources</p>
    <div class="biz-source-grid">
        <?php foreach ($hub['data_sources'] ?? [] as $src): ?>
        <div class="biz-source-card">
            <i class="bi <?= e($src['icon']) ?>"></i>
            <span><?= e($src['name']) ?></span>
            <span class="biz-source-badge biz-source-badge--<?= e($src['status']) ?>"><?= $src['status'] === 'live' ? 'Connected' : 'Connect' ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="biz-section-label">6-month trend</p>
    <div class="biz-chart-card">
        <div class="biz-chart-canvas-wrap biz-chart-canvas-wrap--tall">
            <canvas id="bizOverviewRevenueChart" height="200"></canvas>
        </div>
    </div>
</div>

<div class="biz-tab-panel" id="biz-panel-intelligence">
    <p class="biz-section-label">Predictive forecast</p>
    <div class="biz-kpi-row biz-kpi-row--4">
        <div class="biz-kpi biz-kpi--primary">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-graph-up-arrow"></i></span><span class="biz-kpi-label">Next month est.</span></div>
            <span class="biz-kpi-value biz-kpi-value--money"><?= formatCurrency((float) ($forecast['next_month_estimate'] ?? 0)) ?></span>
            <span class="biz-kpi-sub">Based on recent payment trends</span>
        </div>
        <div class="biz-kpi biz-kpi--blue">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-calculator"></i></span><span class="biz-kpi-label">Trailing avg</span></div>
            <span class="biz-kpi-value biz-kpi-value--money"><?= formatCurrency((float) ($forecast['trailing_avg'] ?? 0)) ?></span>
            <span class="biz-kpi-sub">6-month monthly average</span>
        </div>
        <div class="biz-kpi biz-kpi--teal">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-arrow-<?= ($forecast['trend_direction'] ?? '') === 'down' ? 'down' : 'up' ?>-short"></i></span><span class="biz-kpi-label">Trend</span></div>
            <span class="biz-kpi-value"><?= ucfirst((string) ($forecast['trend_direction'] ?? 'flat')) ?></span>
            <span class="biz-kpi-sub">Month-over-month direction</span>
        </div>
        <div class="biz-kpi">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-shield-check"></i></span><span class="biz-kpi-label">Confidence</span></div>
            <span class="biz-kpi-value"><?= ucfirst((string) ($forecast['confidence'] ?? 'low')) ?></span>
            <span class="biz-kpi-sub">More history improves accuracy</span>
        </div>
    </div>

    <p class="biz-section-label">Prediction drivers</p>
    <div class="biz-driver-grid">
        <?php foreach ($prediction['drivers'] ?? [] as $driver): ?>
        <div class="biz-driver-card">
            <span><?= e($driver['label']) ?></span>
            <strong><?= e($driver['value']) ?></strong>
            <i class="bi bi-arrow-<?= ($driver['direction'] ?? 'flat') === 'down' ? 'down' : 'up' ?>-right-short"></i>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="biz-section-label">Risk matrix</p>
    <div class="biz-rec-list">
        <?php foreach ($prediction['risks'] ?? [] as $risk): ?>
        <div class="biz-rec-item biz-rec-item--<?= e($risk['impact'] ?? 'low') ?>">
            <span class="biz-rec-priority"><?= e(ucfirst((string) ($risk['impact'] ?? 'low'))) ?></span>
            <div>
                <strong><?= e($risk['title'] ?? '') ?></strong>
                <p>Probability: <?= (int) ($risk['probability'] ?? 0) ?>% — <?= e($risk['mitigation'] ?? '') ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="biz-mini-chart-card">
                <p class="biz-mini-chart-label">Revenue vs invoiced (6 mo)</p>
                <div class="biz-chart-canvas-wrap biz-chart-canvas-wrap--md">
                    <canvas id="bizIntelCompareChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="biz-mini-chart-card">
                <p class="biz-mini-chart-label">Anomaly detection</p>
                <ul class="biz-insight-list">
                    <?php foreach (array_filter($hub['alerts'] ?? [], static fn ($a) => in_array($a['type'], ['danger', 'warning', 'info'], true)) as $a): ?>
                    <li><i class="bi <?= e($a['icon']) ?>"></i> <?= e($a['title']) ?>: <?= e($a['message']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <p class="biz-section-label">AI next actions</p>
    <ul class="biz-insight-list">
        <?php foreach ($prediction['next_actions'] ?? [] as $action): ?>
        <li><i class="bi bi-stars"></i> <?= e($action) ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="biz-tab-panel" id="biz-panel-operations">
    <p class="biz-section-label">Operational efficiency</p>
    <div class="biz-kpi-row biz-kpi-row--4">
        <div class="biz-kpi biz-kpi--green">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-check2-circle"></i></span><span class="biz-kpi-label">Completion rate</span></div>
            <span class="biz-kpi-value"><?= number_format((float) ($operational['completion_rate'] ?? 0), 1) ?>%</span>
            <span class="biz-kpi-sub">Cases completed or closed</span>
        </div>
        <div class="biz-kpi biz-kpi--blue">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-hourglass-split"></i></span><span class="biz-kpi-label">Avg case duration</span></div>
            <span class="biz-kpi-value"><?= $operational['avg_case_days'] !== null ? number_format((float) $operational['avg_case_days'], 0) . 'd' : '—' ?></span>
            <span class="biz-kpi-sub">Creation to completion</span>
        </div>
        <div class="biz-kpi biz-kpi--primary">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-stack"></i></span><span class="biz-kpi-label">Total cases</span></div>
            <span class="biz-kpi-value"><?= number_format((int) ($operational['total_cases'] ?? 0)) ?></span>
            <span class="biz-kpi-sub">All-time volume</span>
        </div>
        <div class="biz-kpi biz-kpi--teal">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-percent"></i></span><span class="biz-kpi-label">Collection rate</span></div>
            <span class="biz-kpi-value"><?= number_format((float) $stats['collection_rate'], 0) ?>%</span>
            <span class="biz-kpi-sub">Invoice payment success</span>
        </div>
    </div>

    <?php if (!empty($operational['staff_workload'])): ?>
    <p class="biz-section-label">Team workload</p>
    <div class="table-responsive biz-table-wrap">
        <table class="table saas-table mb-0">
            <thead><tr><th>Staff</th><th>Assigned cases</th><th>Completed</th><th>Efficiency</th></tr></thead>
            <tbody>
                <?php foreach ($operational['staff_workload'] as $row): ?>
                <?php $eff = (int) $row['case_count'] > 0 ? round((int) $row['completed'] / (int) $row['case_count'] * 100) : 0; ?>
                <tr>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                    <td><?= (int) $row['case_count'] ?></td>
                    <td><?= (int) $row['completed'] ?></td>
                    <td><div class="biz-bar-track" style="max-width:120px;display:inline-block;vertical-align:middle;width:80px"><div class="biz-bar-fill" style="width:<?= $eff ?>%"></div></div> <?= $eff ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <p class="biz-section-label">Case pipeline funnel</p>
    <div class="biz-funnel">
        <?php foreach ($hub['case_funnel'] ?? [] as $step): ?>
        <div class="biz-funnel-step">
            <div class="biz-funnel-bar" style="width: <?= max(8, (int) $step['pct']) ?>%"></div>
            <span class="biz-funnel-label"><?= e($step['stage']) ?></span>
            <span class="biz-funnel-val"><?= (int) $step['count'] ?> (<?= (int) $step['pct'] ?>%)</span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($hub['payment_methods'])): ?>
    <p class="biz-section-label">Payment channels</p>
    <ul class="biz-bar-list">
        <?php $maxPay = (float) ($hub['payment_methods'][0]['total'] ?? 1); foreach ($hub['payment_methods'] as $pm): ?>
        <li class="biz-bar-item">
            <span class="biz-bar-name"><?= e(ucfirst(str_replace('_', ' ', $pm['payment_method'] ?? 'other'))) ?></span>
            <div class="biz-bar-track"><div class="biz-bar-fill" style="width:<?= $maxPay > 0 ? round((float) $pm['total'] / $maxPay * 100) : 0 ?>%"></div></div>
            <span class="biz-bar-count"><?= formatCurrency((float) $pm['total']) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<div class="biz-tab-panel" id="biz-panel-audience">
    <p class="biz-section-label">Client metrics</p>
    <div class="biz-kpi-row biz-kpi-row--4">
        <a href="<?= url('pages/clients.php') ?>" class="biz-kpi biz-kpi--primary biz-kpi--link">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-people-fill"></i></span><span class="biz-kpi-label">Total clients</span></div>
            <span class="biz-kpi-value"><?= number_format($stats['total_clients']) ?></span>
            <span class="biz-kpi-sub">Registered <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
        </a>
        <div class="biz-kpi biz-kpi--blue">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-person-plus"></i></span><span class="biz-kpi-label">New this month</span></div>
            <span class="biz-kpi-value"><?= number_format($stats['new_clients_month']) ?></span>
        </div>
        <div class="biz-kpi biz-kpi--teal">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-currency-pound"></i></span><span class="biz-kpi-label">Revenue / client</span></div>
            <span class="biz-kpi-value biz-kpi-value--money"><?= $stats['total_clients'] > 0 ? formatCurrency($stats['total_revenue'] / $stats['total_clients']) : '—' ?></span>
        </div>
        <div class="biz-kpi biz-kpi--green">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-folder2-open"></i></span><span class="biz-kpi-label">Cases / client</span></div>
            <span class="biz-kpi-value"><?= $stats['total_clients'] > 0 ? number_format($totalCasesAll / $stats['total_clients'], 1) : '—' ?></span>
        </div>
    </div>

    <p class="biz-section-label">Micro-segments</p>
    <div class="biz-segment-grid">
        <?php foreach ($hub['client_segments'] ?? [] as $seg): ?>
        <div class="biz-segment-card">
            <span class="biz-segment-dot" style="background:<?= e($seg['color']) ?>"></span>
            <strong><?= e($seg['name']) ?></strong>
            <span class="biz-segment-count"><?= number_format((int) $seg['count']) ?></span>
            <p><?= e($seg['desc']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="biz-section-label">Client acquisition cohorts</p>
    <div class="biz-chart-card">
        <div class="biz-chart-canvas-wrap biz-chart-canvas-wrap--md">
            <canvas id="bizCohortChart" height="180"></canvas>
        </div>
    </div>

    <?php if (!empty($hub['top_clients'])): ?>
    <p class="biz-section-label">Top clients by revenue</p>
    <div class="table-responsive biz-table-wrap">
        <table class="table saas-table mb-0">
            <thead><tr><th>Client</th><th>Revenue</th><th>Invoices</th></tr></thead>
            <tbody>
                <?php foreach ($hub['top_clients'] as $tc): ?>
                <tr>
                    <td><?= e(clientFullName($tc)) ?></td>
                    <td><?= formatCurrency((float) $tc['revenue']) ?></td>
                    <td><?= (int) $tc['invoice_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="biz-tab-panel" id="biz-panel-reports">
    <p class="biz-section-label">Automated reporting</p>
    <?php
    $insightsFreq = (string) ($companySettings['insights_digest_frequency'] ?? 'monthly');
    if (!in_array($insightsFreq, ['weekly', 'monthly'], true)) {
        $insightsFreq = 'monthly';
    }
    $insightsFormat = (string) ($companySettings['insights_digest_format'] ?? 'pdf');
    if (!in_array($insightsFormat, ['pdf', 'csv'], true)) {
        $insightsFormat = 'pdf';
    }
    $insightsRecipients = (string) ($companySettings['insights_digest_recipients'] ?? ($companySettings['office_email'] ?? ''));
    ?>
    <div class="biz-report-card">
        <div class="biz-report-card__icon"><i class="bi bi-envelope-paper"></i></div>
        <div>
            <h3>Schedule insight digests</h3>
            <p>Save schedule, format, and recipients. Use Export now for an immediate snapshot.</p>
        </div>
    </div>
    <form class="biz-report-form" method="post" action="<?= url('actions/insights-report-action.php') ?>">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_digest_preferences">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Frequency</label>
                <select class="form-select" name="insights_digest_frequency">
                    <option value="weekly" <?= $insightsFreq === 'weekly' ? 'selected' : '' ?>>Weekly — Monday 8:00</option>
                    <option value="monthly" <?= $insightsFreq === 'monthly' ? 'selected' : '' ?>>Monthly — 1st of month</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Format</label>
                <select class="form-select" name="insights_digest_format">
                    <option value="pdf" <?= $insightsFormat === 'pdf' ? 'selected' : '' ?>>PDF summary</option>
                    <option value="csv" <?= $insightsFormat === 'csv' ? 'selected' : '' ?>>CSV export</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Recipients</label>
                <input type="text" class="form-control" name="insights_digest_recipients" value="<?= e($insightsRecipients) ?>" placeholder="admin@company.com, finance@company.com">
            </div>
        </div>
        <p class="form-text mt-2"><i class="bi bi-info-circle"></i> Comma-separated recipients are supported.</p>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2-circle"></i> Save digest settings</button>
            <a href="<?= url('actions/insights-export.php') ?>" class="btn btn-soft btn-sm"><i class="bi bi-download"></i> Export snapshot now</a>
        </div>
    </form>

    <p class="biz-section-label">Quick exports</p>
    <div class="biz-kpi-row biz-kpi-row--4">
        <a href="<?= url('actions/insights-export.php?type=payments') ?>" class="biz-kpi biz-kpi--teal biz-kpi--link biz-kpi--export">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-credit-card"></i></span><span class="biz-kpi-label">Payments</span></div>
            <span class="biz-kpi-export-action">Download CSV <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
        </a>
        <a href="<?= url('actions/insights-export.php?type=cases') ?>" class="biz-kpi biz-kpi--blue biz-kpi--link biz-kpi--export">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-briefcase"></i></span><span class="biz-kpi-label">Cases</span></div>
            <span class="biz-kpi-export-action">Download CSV <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
        </a>
        <a href="<?= url('actions/insights-export.php?type=clients') ?>" class="biz-kpi biz-kpi--green biz-kpi--link biz-kpi--export">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-people"></i></span><span class="biz-kpi-label">Clients</span></div>
            <span class="biz-kpi-export-action">Download CSV <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
        </a>
        <a href="<?= url('actions/insights-export.php?type=appointments') ?>" class="biz-kpi biz-kpi--violet biz-kpi--link biz-kpi--export">
            <div class="biz-kpi-head"><span class="biz-kpi-icon"><i class="bi bi-calendar-event"></i></span><span class="biz-kpi-label">Appointments</span></div>
            <span class="biz-kpi-export-action">Download CSV <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
        </a>
    </div>
</div>
