<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientLoginUrl());
    exit;
}

$pageTitle = 'Appointments';
$allAppointments = getClientAppointments($clientId);
$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$filteredAppointments = array_values(array_filter($allAppointments, static function (array $appt) use ($q, $statusFilter): bool {
    if ($statusFilter !== '' && ($appt['status'] ?? '') !== $statusFilter) {
        return false;
    }

    if ($q === '') {
        return true;
    }

    $searchBlob = caseRowSearchBlob($appt, [
        $appt['title'] ?? '',
        appointmentCaseLabel($appt),
        $appt['location'] ?? '',
        $appt['description'] ?? '',
    ]);

    return stripos($searchBlob, $q) !== false;
}));
$perPage = 10;
$page = requestPageNumber();
$totalAppointments = count($filteredAppointments);
$totalPages = max(1, (int) ceil($totalAppointments / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$appointments = array_slice($filteredAppointments, paginationOffset($page, $perPage), $perPage);
$client = ClientService::getById($clientId) ?? ['id' => $clientId];
$upcomingCount = (int) (getClientDashboardStats($clientId)['upcoming_appointments'] ?? 0);
$pageSubtitle = $upcomingCount . ' upcoming';

$calendarEvents = [];
foreach ($allAppointments as $appt) {
    $start = appointmentEffectiveStart($appt);
    if (!$start) {
        continue;
    }

    [$calStart, $calEnd] = resolveAppointmentCalendarRange($appt);
    if (!$calStart) {
        continue;
    }

    $links = GoogleCalendarService::getCalendarLinks((int) ($appt['id'] ?? 0), $appt, $client, true);

    foreach (buildAppointmentCalendarEvents($appt, [
        'status'      => $appt['status'] ?? 'scheduled',
        'location'    => $appt['location'] ?? '',
        'description' => $appt['description'] ?? '',
        'startLabel'  => formatDateTime($calStart, 'M j, Y g:i A'),
        'endLabel'    => formatDateTime($calEnd, 'M j, Y g:i A'),
        'googleUrl'   => $links['google'],
        'outlookUrl'  => $links['outlook'],
        'icsUrl'      => $links['ics'],
    ]) as $event) {
        $calendarEvents[] = $event;
    }
}

$calendarInitialDate = appointmentCalendarInitialDate($appointments);

$pageStyles = '<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">';

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <p class="text-muted small mb-0">View your appointments below.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="saas-card">
            <div class="saas-card-header appointment-calendar-header">
                <div>
                    <h2 class="saas-card-title">Calendar View</h2>
                    <p class="saas-card-subtitle mb-0">Month, week, and day views — click an event for details</p>
                </div>
            </div>
            <div class="appointment-calendar-wrap">
                <div id="appointmentCalendar"></div>
                <div class="appointment-calendar-legend">
                    <span><i style="background:#3aafa9"></i> Scheduled</span>
                    <span><i style="background:#10b981"></i> Confirmed</span>
                    <span><i style="background:#8b5cf6"></i> Rescheduled</span>
                    <span><i style="background:#f59e0b"></i> Past</span>
                    <span><i style="background:#64748b"></i> Completed</span>
                    <span><i style="background:#ef4444"></i> Cancelled</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="saas-card">
            <div class="saas-card-header appointment-list-header">
                <div>
                    <h2 class="saas-card-title">All Appointments</h2>
                    <p class="saas-card-subtitle mb-0"><?= $totalAppointments ?> total</p>
                </div>
            </div>
            <form method="get" class="table-toolbar">
                <div class="table-search">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control form-control-sm" id="tableSearch" name="q" value="<?= e($q) ?>" placeholder="Search appointments...">
                </div>
                <select class="form-select form-select-sm table-filter" id="statusFilter" name="status" onchange="this.form.requestSubmit()">
                    <option value="">All statuses</option>
                    <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="rescheduled" <?= $statusFilter === 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </form>
            <div class="card-body p-0">
                <?php if (empty($appointments)): ?>
                    <div class="empty-state py-5">
                        <i class="bi bi-calendar-x"></i>
                        <p class="mb-0">No appointments scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table appointment-list-table mb-0" id="dataTable">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Date & Time</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appt): ?>
                                    <?php
                                    $start = appointmentStart($appt);
                                    $links = $start
                                        ? GoogleCalendarService::getCalendarLinks((int) ($appt['id'] ?? 0), $appt, $client, true)
                                        : null;
                                    $showCalendar = $links && in_array($appt['status'] ?? '', ['scheduled', 'confirmed', 'rescheduled'], true);
                                    $searchBlob = caseRowSearchBlob($appt, [
                                        $appt['title'] ?? '',
                                        appointmentCaseLabel($appt),
                                        $appt['location'] ?? '',
                                        $appt['description'] ?? '',
                                    ]);
                                    ?>
                                    <tr data-status="<?= e($appt['status']) ?>" data-search="<?= e($searchBlob) ?>"<?= ($appt['status'] ?? '') === 'requested' ? ' class="table-row-requested"' : '' ?>>
                                        <td>
                                            <span class="table-primary"><?= e($appt['title']) ?></span>
                                            <?php if (!empty($appt['description'])): ?>
                                                <span class="table-secondary d-block"><?= e(mb_strimwidth($appt['description'], 0, 60, '...')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?= $start ? formatDateTime($start) : '—' ?></td>
                                        <td><?= e($appt['location'] ?? '—') ?></td>
                                        <td><?= statusBadge($appt['status'] ?? 'scheduled') ?></td>
                                        <td class="text-end">
                                            <?php if (($appt['status'] ?? '') === 'requested'): ?>
                                                <span class="text-muted small">Pending approval</span>
                                            <?php elseif ($showCalendar): ?>
                                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                    <a href="<?= e($links['google']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm" title="Add to Google Calendar">
                                                        <i class="bi bi-google"></i>
                                                    </a>
                                                    <a href="<?= e($links['outlook']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm" title="Add to Outlook Calendar">
                                                        <i class="bi bi-microsoft"></i>
                                                    </a>
                                                    <a href="<?= e($links['ics']) ?>" class="btn btn-soft btn-sm" title="Download calendar file">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                        <small class="text-muted">
                            Showing <?= count($appointments) ?> of <?= $totalAppointments ?> appointments
                        </small>
                        <?= renderPaginationNav($page, $totalPages) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="appointmentDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="apptModalTitle">Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>When:</strong> <span id="apptModalWhen"></span></p>
                <p class="mb-2"><strong>Status:</strong> <span id="apptModalStatus"></span></p>
                <p class="mb-2" id="apptModalLocationWrap"><strong>Location:</strong> <span id="apptModalLocation"></span></p>
                <p class="mb-0" id="apptModalDescWrap"><strong>Notes:</strong> <span id="apptModalDesc"></span></p>
            </div>
            <div class="modal-footer">
                <a href="#" id="apptModalGoogle" class="btn btn-primary btn-sm d-none" target="_blank" rel="noopener">
                    <i class="bi bi-google me-1"></i> Google Calendar
                </a>
                <a href="#" id="apptModalOutlook" class="btn btn-soft btn-sm d-none" target="_blank" rel="noopener">
                    <i class="bi bi-microsoft me-1"></i> Outlook Calendar
                </a>
                <a href="#" id="apptModalIcs" class="btn btn-soft btn-sm d-none">
                    <i class="bi bi-download me-1"></i> Download .ics
                </a>
                <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var calendarEl = document.getElementById("appointmentCalendar");
    if (!calendarEl || typeof FullCalendar === "undefined") {
        return;
    }

    var calendarEvents = ' . json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
    var modal = document.getElementById("appointmentDetailModal");
    var bsModal = modal ? new bootstrap.Modal(modal) : null;

    var calendar = new FullCalendar.Calendar(calendarEl, Object.assign({
        timeZone: "local",
        initialView: "dayGridMonth",
        initialDate: ' . json_encode($calendarInitialDate) . ',
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek"
        },
        height: "auto",
        events: calendarEvents,
        eventTimeFormat: {
            hour: "numeric",
            minute: "2-digit",
            meridiem: "short"
        },
        eventClick: function(info) {
            if (!bsModal) {
                return;
            }

            var props = info.event.extendedProps || {};
            document.getElementById("apptModalTitle").textContent = info.event.title;
            document.getElementById("apptModalWhen").textContent = props.startLabel + (props.endLabel ? " - " + props.endLabel : "");
            document.getElementById("apptModalStatus").textContent = (props.status || "").replace(/_/g, " ");
            document.getElementById("apptModalLocation").textContent = props.location || "—";
            document.getElementById("apptModalDesc").textContent = props.description || "—";
            document.getElementById("apptModalLocationWrap").style.display = props.location ? "" : "none";
            document.getElementById("apptModalDescWrap").style.display = props.description ? "" : "none";

            var showCalendar = ["scheduled", "confirmed", "rescheduled"].indexOf(props.status) !== -1;
            var googleBtn = document.getElementById("apptModalGoogle");
            var outlookBtn = document.getElementById("apptModalOutlook");
            var icsBtn = document.getElementById("apptModalIcs");

            if (showCalendar && props.googleUrl) {
                googleBtn.href = props.googleUrl;
                googleBtn.classList.remove("d-none");
            } else {
                googleBtn.classList.add("d-none");
            }

            if (showCalendar && props.outlookUrl) {
                outlookBtn.href = props.outlookUrl;
                outlookBtn.classList.remove("d-none");
            } else {
                outlookBtn.classList.add("d-none");
            }

            if (showCalendar && props.icsUrl) {
                icsBtn.href = props.icsUrl;
                icsBtn.classList.remove("d-none");
            } else {
                icsBtn.classList.add("d-none");
            }

            bsModal.show();
        }
    }, window.AppointmentCalendar ? window.AppointmentCalendar.calendarOptions() : {}));

    calendar.render();

});
</script>';

require __DIR__ . '/../includes/footer.php';
