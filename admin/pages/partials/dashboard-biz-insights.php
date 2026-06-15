<div class="row g-0 mb-4">
    <div class="col-12">
        <div class="biz-insights-panel">

            <div class="biz-insights-header">
                <div class="biz-insights-header-left">
                    <div class="biz-insights-icon-wrap" aria-hidden="true">
                        <i class="bi bi-bar-chart-line-fill"></i>
                    </div>
                    <div>
                        <h2 class="biz-insights-title">Business Insights</h2>
                        <p class="biz-insights-subtitle">Live overview — <?= date('F Y') ?></p>
                    </div>
                </div>
                <div class="biz-insights-header-right">
                    <div class="biz-tab-group" role="tablist" aria-label="Insight sections">
                        <button class="biz-tab active" data-biz-tab="financial" type="button" role="tab" aria-selected="true">Financial</button>
                        <button class="biz-tab" data-biz-tab="cases" type="button" role="tab" aria-selected="false">Cases</button>
                        <button class="biz-tab" data-biz-tab="clients" type="button" role="tab" aria-selected="false">Clients</button>
                        <button class="biz-tab" data-biz-tab="appointments" type="button" role="tab" aria-selected="false">Appointments</button>
                    </div>
                    <span class="biz-admin-badge" title="Controlled by Role Access → Insights permission">
                        <i class="bi bi-shield-lock-fill"></i> Insights access
                    </span>
                </div>
            </div>

            <div class="biz-tab-panel active" id="biz-panel-financial">
                <?php $issuedInvoices = (int) $stats['paid_invoices'] + (int) $stats['pending_invoices'] + (int) $stats['overdue_invoices']; ?>

                <p class="biz-section-label">Revenue</p>
                <div class="biz-kpi-row biz-kpi-row--4">
                    <div class="biz-kpi biz-kpi--teal">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-calendar-week"></i></span>
                            <span class="biz-kpi-label">This Week</span>
                        </div>
                        <div class="biz-kpi-value-row">
                            <span class="biz-kpi-value biz-kpi-value--money"><?= formatCurrency($stats['weekly_revenue']) ?></span>
                            <?= kpiTrendBadge($trends['revenue'], true) ?>
                        </div>
                        <span class="biz-kpi-sub">Completed payments — last 7 days</span>
                    </div>

                    <div class="biz-kpi biz-kpi--blue">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-calendar3"></i></span>
                            <span class="biz-kpi-label">This Month</span>
                        </div>
                        <span class="biz-kpi-value biz-kpi-value--money"><?= formatCurrency($stats['monthly_revenue']) ?></span>
                        <span class="biz-kpi-sub">Completed in <?= date('F') ?></span>
                    </div>

                    <div class="biz-kpi biz-kpi--primary">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-graph-up-arrow"></i></span>
                            <span class="biz-kpi-label">Total Revenue</span>
                        </div>
                        <span class="biz-kpi-value biz-kpi-value--money"><?= formatCurrency($stats['total_revenue']) ?></span>
                        <span class="biz-kpi-sub">All completed payments</span>
                    </div>

                    <div class="biz-kpi">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-receipt"></i></span>
                            <span class="biz-kpi-label">Avg Invoice</span>
                        </div>
                        <span class="biz-kpi-value biz-kpi-value--money"><?= formatCurrency($stats['avg_invoice_value']) ?></span>
                        <span class="biz-kpi-sub">Average paid invoice value</span>
                    </div>
                </div>

                <?php if ($hasWeeklyChartData): ?>
                <div class="biz-sparkline-card">
                    <div class="biz-sparkline-head">
                        <span class="biz-sparkline-label">7-day payment trend</span>
                        <span class="biz-sparkline-hint">Daily completed payments</span>
                    </div>
                    <div class="biz-sparkline-canvas-wrap">
                        <canvas id="bizRevenueSparkline" height="64"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <p class="biz-section-label">Collections</p>
                <div class="biz-kpi-row biz-kpi-row--4">
                    <a href="<?= url('pages/payments.php') ?>" class="biz-kpi biz-kpi--amber biz-kpi--link<?= $stats['outstanding_balance'] > 0 ? ' biz-kpi--alert' : '' ?>">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span>
                            <span class="biz-kpi-label">Outstanding</span>
                        </div>
                        <span class="biz-kpi-value biz-kpi-value--money"><?= formatCurrency($stats['outstanding_balance']) ?></span>
                        <span class="biz-kpi-sub">Pending + overdue <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
                    </a>

                    <div class="biz-kpi biz-kpi--green biz-kpi--collection">
                        <div class="biz-kpi-collection-inner">
                            <div class="biz-kpi-ring" style="--pct: <?= min(100, $stats['collection_rate']) ?>" aria-hidden="true">
                                <span class="biz-kpi-ring-value"><?= number_format($stats['collection_rate'], 0) ?>%</span>
                            </div>
                            <div class="biz-kpi-collection-body">
                                <span class="biz-kpi-label">Collection Rate</span>
                                <div class="biz-progress-bar-wrap" title="<?= number_format($stats['collection_rate'], 1) ?>% of all invoices paid">
                                    <div class="biz-progress-bar biz-progress-bar--green" style="width:<?= min(100, $stats['collection_rate']) ?>%"></div>
                                </div>
                                <span class="biz-kpi-sub"><?= $stats['paid_invoices'] ?> paid of <?= $issuedInvoices ?> issued</span>
                            </div>
                        </div>
                    </div>

                    <a href="<?= url('pages/payments.php?status=overdue') ?>" class="biz-kpi biz-kpi--red biz-kpi--link<?= $stats['overdue_invoices'] > 0 ? ' biz-kpi--alert' : '' ?>">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-clock-history"></i></span>
                            <span class="biz-kpi-label">Overdue</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['overdue_invoices']) ?></span>
                        <span class="biz-kpi-sub">Invoices past due date <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
                    </a>

                    <div class="biz-kpi biz-kpi--teal">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-check-all"></i></span>
                            <span class="biz-kpi-label">Paid Invoices</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['paid_invoices']) ?></span>
                        <span class="biz-kpi-sub">All-time completed</span>
                    </div>
                </div>
            </div>

            <div class="biz-tab-panel" id="biz-panel-cases">
                <p class="biz-section-label">Case overview</p>
                <div class="biz-cases-grid">
                    <div class="biz-kpi-col">
                        <div class="biz-kpi-row biz-kpi-row--2col">
                            <a href="<?= url('pages/cases.php') ?>" class="biz-kpi biz-kpi--primary biz-kpi--link">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-folder2-open"></i></span>
                                    <span class="biz-kpi-label">Active Cases</span>
                                </div>
                                <span class="biz-kpi-value"><?= number_format($stats['active_cases']) ?></span>
                                <span class="biz-kpi-sub">In progress / pending <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
                            </a>

                            <div class="biz-kpi biz-kpi--blue">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-folder-plus"></i></span>
                                    <span class="biz-kpi-label">New This Month</span>
                                </div>
                                <span class="biz-kpi-value"><?= number_format($stats['new_cases_month']) ?></span>
                                <span class="biz-kpi-sub">Opened in <?= date('F') ?></span>
                            </div>

                            <div class="biz-kpi biz-kpi--green">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-folder-check"></i></span>
                                    <span class="biz-kpi-label">Completed</span>
                                </div>
                                <span class="biz-kpi-value"><?= number_format($stats['completed_cases']) ?></span>
                                <span class="biz-kpi-sub">Closed or completed</span>
                            </div>

                            <?php if ($stats['urgent_cases'] > 0): ?>
                            <a href="<?= url('pages/cases.php?priority=urgent') ?>" class="biz-kpi biz-kpi--red biz-kpi--link">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-fire"></i></span>
                                    <span class="biz-kpi-label">Urgent / High</span>
                                </div>
                                <span class="biz-kpi-value"><?= number_format($stats['urgent_cases']) ?></span>
                                <span class="biz-kpi-sub">Active high-priority cases <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
                            </a>
                            <?php else: ?>
                            <div class="biz-kpi biz-kpi--green">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-shield-check"></i></span>
                                    <span class="biz-kpi-label">Urgent / High</span>
                                </div>
                                <span class="biz-kpi-value">0</span>
                                <span class="biz-kpi-sub">No urgent cases</span>
                            </div>
                            <?php endif; ?>

                            <?php if ($stats['cases_deadline_soon'] > 0): ?>
                            <a href="<?= url('pages/cases.php') ?>" class="biz-kpi biz-kpi--amber biz-kpi--link">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
                                    <span class="biz-kpi-label">Due This Week</span>
                                </div>
                                <span class="biz-kpi-value"><?= number_format($stats['cases_deadline_soon']) ?></span>
                                <span class="biz-kpi-sub">Deadline in next 7 days <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
                            </a>
                            <?php else: ?>
                            <div class="biz-kpi">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-calendar-check"></i></span>
                                    <span class="biz-kpi-label">Due This Week</span>
                                </div>
                                <span class="biz-kpi-value">0</span>
                                <span class="biz-kpi-sub">No deadlines this week</span>
                            </div>
                            <?php endif; ?>

                            <div class="biz-kpi">
                                <div class="biz-kpi-head">
                                    <span class="biz-kpi-icon"><i class="bi bi-stack"></i></span>
                                    <span class="biz-kpi-label">Total Cases</span>
                                </div>
                                <span class="biz-kpi-value"><?= number_format($totalCasesAll) ?></span>
                                <span class="biz-kpi-sub">All time</span>
                            </div>
                        </div>
                    </div>

                    <div class="biz-cases-charts">
                        <div class="biz-mini-chart-card">
                            <p class="biz-mini-chart-label">Status breakdown</p>
                            <?php if ($totalCasesAll > 0): ?>
                                <div class="biz-donut-wrap">
                                    <canvas id="caseStatusDonut" width="130" height="130"></canvas>
                                    <div class="biz-donut-center">
                                        <span class="biz-donut-total"><?= $totalCasesAll ?></span>
                                        <span class="biz-donut-sub">total</span>
                                    </div>
                                </div>
                                <ul class="biz-donut-legend">
                                    <?php
                                    $statusMeta = [
                                        'pending'            => ['Pending', '#f59e0b'],
                                        'in_progress'        => ['In Progress', '#3aafa9'],
                                        'waiting_for_client' => ['Waiting for Client', '#6366f1'],
                                        'completed'          => ['Completed', '#10b981'],
                                        'closed'             => ['Closed', '#64748b'],
                                    ];
                                    foreach ($statusMeta as $key => [$label, $color]):
                                        $count = $caseStatusBreakdown[$key] ?? 0;
                                        if ($count === 0) {
                                            continue;
                                        }
                                        $pct = $totalCasesAll > 0 ? round($count / $totalCasesAll * 100) : 0;
                                    ?>
                                    <li class="biz-donut-legend-item">
                                        <span class="biz-donut-dot" style="background:<?= $color ?>"></span>
                                        <span class="biz-donut-legend-label"><?= $label ?></span>
                                        <span class="biz-donut-legend-val"><?= $count ?> <span class="biz-donut-legend-pct">(<?= $pct ?>%)</span></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="biz-empty-note">No cases yet.</p>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($topServiceTypes)): ?>
                        <div class="biz-mini-chart-card">
                            <p class="biz-mini-chart-label">Top service types</p>
                            <ul class="biz-bar-list">
                                <?php
                                $maxCount = (int) ($topServiceTypes[0]['c'] ?? 1);
                                foreach ($topServiceTypes as $svc):
                                    $pct = $maxCount > 0 ? round((int) $svc['c'] / $maxCount * 100) : 0;
                                ?>
                                <li class="biz-bar-item">
                                    <span class="biz-bar-name" title="<?= e($svc['service_type']) ?>"><?= e($svc['service_type']) ?></span>
                                    <div class="biz-bar-track">
                                        <div class="biz-bar-fill" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <span class="biz-bar-count"><?= $svc['c'] ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="biz-tab-panel" id="biz-panel-clients">
                <p class="biz-section-label">Client metrics</p>
                <div class="biz-kpi-row biz-kpi-row--4">
                    <a href="<?= url('pages/clients.php') ?>" class="biz-kpi biz-kpi--primary biz-kpi--link">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-people-fill"></i></span>
                            <span class="biz-kpi-label">Total Clients</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['total_clients']) ?></span>
                        <span class="biz-kpi-sub">Registered in the system <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
                    </a>

                    <div class="biz-kpi biz-kpi--blue">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-person-plus-fill"></i></span>
                            <span class="biz-kpi-label">New This Month</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['new_clients_month']) ?></span>
                        <span class="biz-kpi-sub">Joined in <?= date('F Y') ?></span>
                    </div>

                    <div class="biz-kpi biz-kpi--teal">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-folder2-open"></i></span>
                            <span class="biz-kpi-label">Cases per Client</span>
                        </div>
                        <span class="biz-kpi-value"><?= $stats['total_clients'] > 0 ? number_format($totalCasesAll / $stats['total_clients'], 1) : '—' ?></span>
                        <span class="biz-kpi-sub">Average across all clients</span>
                    </div>

                    <div class="biz-kpi biz-kpi--green">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-currency-dollar"></i></span>
                            <span class="biz-kpi-label">Revenue per Client</span>
                        </div>
                        <span class="biz-kpi-value"><?= $stats['total_clients'] > 0 ? formatCurrency($stats['total_revenue'] / $stats['total_clients']) : '—' ?></span>
                        <span class="biz-kpi-sub">All-time average</span>
                    </div>

                    <div class="biz-kpi biz-kpi--amber">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-receipt"></i></span>
                            <span class="biz-kpi-label">Pending Invoices</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['pending_invoices']) ?></span>
                        <span class="biz-kpi-sub">Awaiting payment</span>
                    </div>
                </div>
            </div>

            <div class="biz-tab-panel" id="biz-panel-appointments">
                <p class="biz-section-label">Schedule</p>
                <div class="biz-kpi-row biz-kpi-row--4">
                    <a href="<?= url('pages/appointments.php') ?>" class="biz-kpi biz-kpi--primary biz-kpi--link">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-calendar-event-fill"></i></span>
                            <span class="biz-kpi-label">Upcoming</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['upcoming_appointments']) ?></span>
                        <span class="biz-kpi-sub">Scheduled ahead <i class="bi bi-arrow-right-short biz-kpi-arrow"></i></span>
                    </a>

                    <div class="biz-kpi biz-kpi--blue">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-calendar-month"></i></span>
                            <span class="biz-kpi-label">This Month</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['appointments_month']) ?></span>
                        <span class="biz-kpi-sub">Appointments in <?= date('F') ?></span>
                    </div>

                    <div class="biz-kpi biz-kpi--teal">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-person-video3"></i></span>
                            <span class="biz-kpi-label">Active Cases</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['active_cases']) ?></span>
                        <span class="biz-kpi-sub">Cases needing attention</span>
                    </div>

                    <div class="biz-kpi biz-kpi--green">
                        <div class="biz-kpi-head">
                            <span class="biz-kpi-icon"><i class="bi bi-clock-fill"></i></span>
                            <span class="biz-kpi-label">Clients</span>
                        </div>
                        <span class="biz-kpi-value"><?= number_format($stats['total_clients']) ?></span>
                        <span class="biz-kpi-sub">Total in system</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
