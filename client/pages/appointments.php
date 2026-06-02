<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . adminUrl('auth/login.php?portal=client'));
    exit;
}

$pageTitle = 'Appointments';
$appointments = getClientAppointments($clientId);
$client = ClientService::getById($clientId) ?? ['id' => $clientId];
$clientCases = AppointmentService::getCasesForClient($clientId);
$upcomingCount = (int) (getClientDashboardStats($clientId)['upcoming_appointments'] ?? 0);
$pageSubtitle = $upcomingCount . ' upcoming';

$calendarEvents = [];
foreach ($appointments as $appt) {
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
        <p class="text-muted small mb-0">View your appointments or request a new one below.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestAppointmentModal">
        <i class="bi bi-calendar-plus"></i> Request Appointment
    </button>
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
                    <span><i style="background:#6366f1"></i> Requested</span>
                    <span><i style="background:#3aafa9"></i> Scheduled</span>
                    <span><i style="background:#10b981"></i> Confirmed</span>
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
                    <p class="saas-card-subtitle mb-0"><?= count($appointments) ?> total</p>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($appointments)): ?>
                    <div class="empty-state py-5">
                        <i class="bi bi-calendar-x"></i>
                        <p class="mb-0">No appointments scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table saas-table appointment-list-table mb-0">
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
                                    $showCalendar = $links && in_array($appt['status'] ?? '', ['scheduled', 'confirmed'], true);
                                    ?>
                                    <tr>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="requestAppointmentModal" tabindex="-1" aria-labelledby="requestAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= e(clientUrl('actions/appointment-request.php')) ?>" id="requestAppointmentForm">
                <?= CSRF::field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="requestAppointmentModalLabel">Request Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Submit your preferred date and time. Our team will review and confirm your appointment.</p>
                    <div class="row g-3">
                        <?php if (!empty($clientCases)): ?>
                        <div class="col-md-6">
                            <label class="form-label">Related Case</label>
                            <select name="case_id" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($clientCases as $case): ?>
                                    <option value="<?= (int) $case['id'] ?>" <?= (int) old('case_id') === (int) $case['id'] ? 'selected' : '' ?>>
                                        <?= e($case['case_number'] . ' — ' . $case['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required maxlength="255"
                                   value="<?= e(old('title', '')) ?>" placeholder="e.g. Document signing meeting">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preferred Date <span class="text-danger">*</span></label>
                            <input type="date" id="req_appt_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" id="req_appt_start_time" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" id="req_appt_end_time" class="form-control">
                        </div>
                        <input type="hidden" name="starts_at" id="req_starts_at">
                        <input type="hidden" name="ends_at" id="req_ends_at">
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" maxlength="255"
                                   value="<?= e(old('location', '')) ?>" placeholder="Office, Zoom, etc.">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Anything we should know before scheduling…"><?= e(old('description', '')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
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

            var showCalendar = ["scheduled", "confirmed"].indexOf(props.status) !== -1;
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

    var requestForm = document.getElementById("requestAppointmentForm");
    var reqDate = document.getElementById("req_appt_date");
    var reqStart = document.getElementById("req_appt_start_time");
    var reqEnd = document.getElementById("req_appt_end_time");
    var reqStartsAt = document.getElementById("req_starts_at");
    var reqEndsAt = document.getElementById("req_ends_at");

    if (requestForm) {
        requestForm.addEventListener("submit", function(e) {
            var dateVal = reqDate ? reqDate.value : "";
            var startTime = reqStart ? reqStart.value : "";
            var endTime = reqEnd ? reqEnd.value : "";
            if (reqStartsAt) {
                reqStartsAt.value = dateVal && startTime ? (dateVal + "T" + startTime) : "";
            }
            if (reqEndsAt) {
                reqEndsAt.value = endTime ? (dateVal + "T" + endTime) : "";
            }
            if (!reqStartsAt || !reqStartsAt.value) {
                e.preventDefault();
                alert("Please select a preferred date and start time.");
            }
        });
    }
});
</script>';

require __DIR__ . '/../includes/footer.php';
