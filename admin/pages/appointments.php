<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Appointments';
$appointments = getAllAppointments();
$clients = getAllClients();
$stats = getDashboardStats();
$pageSubtitle = $stats['upcoming_appointments'] . ' upcoming';

$addedId = (int) ($_GET['added'] ?? 0);
$addedAppointment = null;
$addedCalendarUrl = null;
$addedIcsUrl = null;

if ($addedId > 0) {
    $addedAppointment = AppointmentService::getById($addedId);
    if ($addedAppointment) {
        $addedClient = ClientService::getById((int) ($addedAppointment['client_id'] ?? 0)) ?? $addedAppointment;
        $addedCalendarUrl = $addedAppointment['meeting_link'] ?? GoogleCalendarService::buildAddToCalendarUrl($addedAppointment, $addedClient);
        $addedIcsUrl = url('actions/appointment-ics.php?id=' . $addedId);
    }
}

$statusColors = appointmentStatusColors();

$calendarEvents = [];
foreach ($appointments as $appt) {
    $start = appointmentEffectiveStart($appt);
    if (!$start) {
        continue;
    }

    $end = appointmentEffectiveEnd($appt) ?: date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));
    $calUrl = $appt['meeting_link'] ?? GoogleCalendarService::buildAddToCalendarUrl($appt, $appt);
    $eventColors = appointmentCalendarEventColors($appt);

    $calendarEvents[] = [
        'id'              => (string) ($appt['id'] ?? ''),
        'title'           => $appt['title'] ?? 'Appointment',
        'start'           => calendarEventDateTime($start),
        'end'             => calendarEventDateTime($end),
        'backgroundColor' => $eventColors['backgroundColor'],
        'borderColor'     => $eventColors['borderColor'],
        'classNames'      => $eventColors['classNames'],
        'extendedProps'   => [
            'client'      => clientFullName($appt),
            'case'        => $appt['case_number'] ?? '',
            'status'      => $appt['status'] ?? 'scheduled',
            'location'    => $appt['location'] ?? '',
            'description' => $appt['description'] ?? '',
            'startLabel'  => formatDateTime($start, 'M j, Y g:i A'),
            'endLabel'    => formatDateTime($end, 'M j, Y g:i A'),
            'calUrl'      => $calUrl,
            'icsUrl'      => url('actions/appointment-ics.php?id=' . (int) ($appt['id'] ?? 0)),
        ],
    ];
}

$pageStyles = '<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">';

require __DIR__ . '/../includes/header.php';
?>

<?php if ($addedAppointment && $addedCalendarUrl): ?>
<div class="alert alert-success border-0 shadow-sm mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <strong><i class="bi bi-check-circle me-2"></i>Appointment scheduled!</strong>
            <span class="d-block small mt-1">“<?= e($addedAppointment['title']) ?>” — click below to add it to Google Calendar (one click, no setup needed).</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e($addedCalendarUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm" id="openGoogleCalendar">
                <i class="bi bi-google me-1"></i> Add to Google Calendar
            </a>
            <a href="<?= e($addedIcsUrl) ?>" class="btn btn-soft btn-sm">
                <i class="bi bi-download me-1"></i> Download .ics
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <p class="text-muted small mb-0">View appointments on the calendar or in the list below.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
        <i class="bi bi-plus-lg"></i> Schedule Appointment
    </button>
</div>

<div class="saas-card mb-4">
    <div class="saas-card-header appointment-calendar-header">
        <div>
            <h2 class="saas-card-title">Calendar View</h2>
            <p class="saas-card-subtitle">Month, week, and day views — click a date to schedule, click an event for details</p>
        </div>
    </div>
    <div class="appointment-calendar-wrap">
        <div id="appointmentCalendar"></div>
        <div class="appointment-calendar-legend">
            <span><i style="background:#3aafa9"></i> Scheduled</span>
            <span><i style="background:#10b981"></i> Confirmed</span>
            <span><i style="background:#f59e0b"></i> Past</span>
            <span><i style="background:#64748b"></i> Completed</span>
            <span><i style="background:#ef4444"></i> Cancelled</span>
        </div>
    </div>
</div>

<div class="saas-card">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Appointment List</h2>
            <p class="saas-card-subtitle"><?= count($appointments) ?> total appointments</p>
        </div>
    </div>
    <div class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="tableSearch" placeholder="Search appointments...">
        </div>
        <select class="form-select form-select-sm table-filter" id="statusFilter">
            <option value="">All statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div class="card-body p-0">
        <?php if (empty($appointments)): ?>
            <div class="empty-state py-5">
                <i class="bi bi-calendar3"></i>
                <p class="mb-0">No appointments scheduled yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table appointment-list-table mb-0" id="dataTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Title</th>
                            <th>Client</th>
                            <th>Case</th>
                            <th>Status</th>
                            <th>Calendar</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appt): ?>
                            <tr data-status="<?= e($appt['status']) ?>">
                                <td>
                                    <span class="table-primary"><?= formatDate(appointmentStart($appt)) ?></span>
                                    <span class="table-secondary d-block">
                                        <?= formatDateTime(appointmentStart($appt), 'g:i A') ?>
                                    </span>
                                </td>
                                <td><?= e($appt['title']) ?></td>
                                <td><?= e(clientFullName($appt)) ?></td>
                                <td class="text-muted"><?= e($appt['case_number'] ?: '—') ?></td>
                                <td><?= statusBadge($appt['status']) ?></td>
                                <td>
                                    <?php
                                    $calUrl = $appt['meeting_link'] ?? null;
                                    if (!$calUrl && appointmentStart($appt)) {
                                        $calUrl = GoogleCalendarService::buildAddToCalendarUrl($appt, $appt);
                                    }
                                    ?>
                                    <?php if ($calUrl): ?>
                                        <a href="<?= e($calUrl) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm" title="Add to Google Calendar">
                                            <i class="bi bi-google"></i>
                                        </a>
                                        <a href="<?= url('actions/appointment-ics.php?id=' . (int) $appt['id']) ?>" class="btn btn-soft btn-sm" title="Download .ics">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-soft btn-sm btn-edit-appt"
                                        data-id="<?= (int) $appt['id'] ?>"
                                        data-client="<?= e(clientFullName($appt)) ?>"
                                        data-case="<?= e($appt['case_number'] ?: 'None') ?>"
                                        data-title="<?= e($appt['title']) ?>"
                                        data-starts="<?= e(date('Y-m-d\TH:i', strtotime(appointmentStart($appt)))) ?>"
                                        data-ends="<?= e(appointmentEnd($appt) ? date('Y-m-d\TH:i', strtotime(appointmentEnd($appt))) : '') ?>"
                                        data-location="<?= e($appt['location'] ?? '') ?>"
                                        data-status="<?= e($appt['status']) ?>"
                                        data-description="<?= e($appt['description'] ?? '') ?>">Edit</button>
                                    <?php if (($appt['status'] ?? '') !== 'cancelled'): ?>
                                        <form method="post" action="<?= url('actions/appointment-action.php') ?>" class="d-inline" onsubmit="return confirm('Cancel this appointment?');">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="action" value="cancel_appointment">
                                            <input type="hidden" name="appointment_id" value="<?= (int) $appt['id'] ?>">
                                            <button type="submit" class="btn btn-soft-danger btn-sm">Delete</button>
                                        </form>
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

<div class="modal fade" id="eventDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventDetailTitle">Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Client</dt>
                    <dd class="col-sm-8" id="eventDetailClient">—</dd>
                    <dt class="col-sm-4 text-muted">Case</dt>
                    <dd class="col-sm-8" id="eventDetailCase">—</dd>
                    <dt class="col-sm-4 text-muted">When</dt>
                    <dd class="col-sm-8" id="eventDetailWhen">—</dd>
                    <dt class="col-sm-4 text-muted">Location</dt>
                    <dd class="col-sm-8" id="eventDetailLocation">—</dd>
                    <dt class="col-sm-4 text-muted">Status</dt>
                    <dd class="col-sm-8" id="eventDetailStatus">—</dd>
                    <dt class="col-sm-4 text-muted">Notes</dt>
                    <dd class="col-sm-8" id="eventDetailDescription">—</dd>
                </dl>
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" rel="noopener" class="btn btn-primary btn-sm d-none" id="eventDetailGoogle">
                    <i class="bi bi-google me-1"></i> Google Calendar
                </a>
                <a href="#" class="btn btn-soft btn-sm d-none" id="eventDetailIcs">
                    <i class="bi bi-download me-1"></i> Download .ics
                </a>
                <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= url('actions/appointment-action.php') ?>" class="modal-content" id="scheduleForm">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" id="appt_form_action" value="create_appointment">
            <input type="hidden" name="appointment_id" id="appt_form_id" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalTitle">Schedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6 appt-field-create">
                        <label class="form-label">Client <span class="text-danger">*</span></label>
                        <select name="client_id" id="appt_client_id" class="form-select" required>
                            <option value="">Select client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>"><?= e(clientFullName($client)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 appt-field-create">
                        <label class="form-label">Related Case</label>
                        <select name="case_id" id="appt_case_id" class="form-select">
                            <option value="">None</option>
                        </select>
                    </div>
                    <div class="col-md-6 appt-field-edit d-none">
                        <label class="form-label">Client</label>
                        <input type="text" class="form-control appt-readonly-field" id="appt_client_display" readonly tabindex="-1">
                    </div>
                    <div class="col-md-6 appt-field-edit d-none">
                        <label class="form-label">Related Case</label>
                        <input type="text" class="form-control appt-readonly-field" id="appt_case_display" readonly tabindex="-1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="appt_title" class="form-control" required placeholder="e.g. Document Signing Meeting">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="starts_at" id="appt_starts_at" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End</label>
                        <input type="datetime-local" name="ends_at" id="appt_ends_at" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="appt_location" class="form-control" placeholder="Office, Zoom link, etc.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="appt_status" class="form-select">
                            <option value="scheduled">Scheduled</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="appt_description" class="form-control" rows="2" placeholder="Notes for the client…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="scheduleSubmitBtn">Schedule & Notify Client</button>
            </div>
        </form>
    </div>
</div>

<?php
$casesByClient = [];
foreach (getAllCases() as $c) {
    $casesByClient[(int) $c['client_id']][] = ['id' => (int) $c['id'], 'label' => $c['case_number'] . ' — ' . $c['title']];
}
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var casesByClient = ' . json_encode($casesByClient) . ';
    var calendarEvents = ' . json_encode($calendarEvents) . ';
    var clientSelect = document.getElementById("appt_client_id");
    var caseSelect = document.getElementById("appt_case_id");
    var scheduleModalEl = document.getElementById("scheduleModal");
    var scheduleModal = scheduleModalEl ? new bootstrap.Modal(scheduleModalEl) : null;
    var eventModalEl = document.getElementById("eventDetailModal");
    var eventModal = eventModalEl ? new bootstrap.Modal(eventModalEl) : null;
    var startsAtInput = document.getElementById("appt_starts_at");
    var apptActionInput = document.getElementById("appt_form_action");
    var apptIdInput = document.getElementById("appt_form_id");
    var scheduleTitle = document.getElementById("scheduleModalTitle");
    var scheduleSubmitBtn = document.getElementById("scheduleSubmitBtn");
    var clientDisplay = document.getElementById("appt_client_display");
    var caseDisplay = document.getElementById("appt_case_display");
    var createFields = document.querySelectorAll(".appt-field-create");
    var editFields = document.querySelectorAll(".appt-field-edit");

    function setCreateMode() {
        if (apptActionInput) apptActionInput.value = "create_appointment";
        if (apptIdInput) apptIdInput.value = "";
        if (scheduleTitle) scheduleTitle.textContent = "Schedule Appointment";
        if (scheduleSubmitBtn) scheduleSubmitBtn.textContent = "Schedule & Notify Client";
        createFields.forEach(function(el) { el.classList.remove("d-none"); });
        editFields.forEach(function(el) { el.classList.add("d-none"); });
        if (clientSelect) {
            clientSelect.disabled = false;
            clientSelect.required = true;
            clientSelect.value = "";
        }
        if (caseSelect) {
            caseSelect.disabled = false;
            caseSelect.innerHTML = "<option value=\"\">None</option>";
        }
    }

    function setEditMode(data) {
        if (apptActionInput) apptActionInput.value = "update_appointment";
        if (apptIdInput) apptIdInput.value = data.id || "";
        if (scheduleTitle) scheduleTitle.textContent = "Edit Appointment";
        if (scheduleSubmitBtn) scheduleSubmitBtn.textContent = "Save Changes";
        createFields.forEach(function(el) { el.classList.add("d-none"); });
        editFields.forEach(function(el) { el.classList.remove("d-none"); });
        if (clientSelect) {
            clientSelect.required = false;
            clientSelect.disabled = true;
        }
        if (caseSelect) {
            caseSelect.disabled = true;
        }
        if (clientDisplay) clientDisplay.value = data.client || "—";
        if (caseDisplay) caseDisplay.value = data.case || "None";
        document.getElementById("appt_title").value = data.title || "";
        document.getElementById("appt_starts_at").value = data.starts || "";
        document.getElementById("appt_ends_at").value = data.ends || "";
        document.getElementById("appt_location").value = data.location || "";
        document.getElementById("appt_status").value = data.status || "scheduled";
        document.getElementById("appt_description").value = data.description || "";
        if (scheduleModal) scheduleModal.show();
    }

    document.querySelectorAll(".btn-edit-appt").forEach(function(btn) {
        btn.addEventListener("click", function() {
            setEditMode({
                id: btn.dataset.id,
                client: btn.dataset.client,
                case: btn.getAttribute("data-case"),
                title: btn.dataset.title,
                starts: btn.dataset.starts,
                ends: btn.dataset.ends,
                location: btn.dataset.location,
                status: btn.dataset.status,
                description: btn.dataset.description
            });
        });
    });

    if (scheduleModalEl) {
        scheduleModalEl.addEventListener("hidden.bs.modal", setCreateMode);
    }

    var scheduleForm = document.getElementById("scheduleForm");
    if (scheduleForm) {
        scheduleForm.addEventListener("submit", function() {
            if (apptActionInput && apptActionInput.value === "update_appointment" && clientSelect) {
                clientSelect.required = false;
                clientSelect.disabled = true;
            }
        });
    }

    document.querySelectorAll("[data-bs-target=\"#scheduleModal\"]").forEach(function(btn) {
        btn.addEventListener("click", setCreateMode);
    });

    if (clientSelect && caseSelect) {
        clientSelect.addEventListener("change", function() {
            var cid = this.value;
            caseSelect.innerHTML = "<option value=\"\">None</option>";
            (casesByClient[cid] || []).forEach(function(c) {
                var opt = document.createElement("option");
                opt.value = c.id;
                opt.textContent = c.label;
                caseSelect.appendChild(opt);
            });
        });
    }

    function pad(n) { return String(n).padStart(2, "0"); }

    function toLocalInputValue(date) {
        return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate()) +
            "T" + pad(date.getHours()) + ":" + pad(date.getMinutes());
    }

    function openScheduleModal(date) {
        if (!scheduleModal || !startsAtInput) return;
        var start = new Date(date);
        if (start.getHours() === 0 && start.getMinutes() === 0) {
            start.setHours(9, 0, 0, 0);
        }
        startsAtInput.value = toLocalInputValue(start);
        scheduleModal.show();
    }

    function showEventDetails(event) {
        var props = event.extendedProps || {};
        document.getElementById("eventDetailTitle").textContent = event.title || "Appointment";
        document.getElementById("eventDetailClient").textContent = props.client || "—";
        document.getElementById("eventDetailCase").textContent = props.case || "—";
        document.getElementById("eventDetailWhen").textContent = props.endLabel
            ? props.startLabel + " → " + props.endLabel
            : (props.startLabel || "—");
        document.getElementById("eventDetailLocation").textContent = props.location || "—";
        document.getElementById("eventDetailStatus").textContent = (props.status || "scheduled").replace("_", " ");
        document.getElementById("eventDetailDescription").textContent = props.description || "—";

        var googleBtn = document.getElementById("eventDetailGoogle");
        var icsBtn = document.getElementById("eventDetailIcs");
        if (props.calUrl) {
            googleBtn.href = props.calUrl;
            googleBtn.classList.remove("d-none");
        } else {
            googleBtn.classList.add("d-none");
        }
        if (props.icsUrl) {
            icsBtn.href = props.icsUrl;
            icsBtn.classList.remove("d-none");
        } else {
            icsBtn.classList.add("d-none");
        }

        if (eventModal) eventModal.show();
    }

    var calendarEl = document.getElementById("appointmentCalendar");
    if (calendarEl && window.FullCalendar) {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            timeZone: "local",
            initialView: "dayGridMonth",
            height: "auto",
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek"
            },
            events: calendarEvents,
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                showEventDetails(info.event);
            },
            dateClick: function(info) {
                openScheduleModal(info.date);
            },
            eventTimeFormat: {
                hour: "numeric",
                minute: "2-digit",
                meridiem: "short"
            }
        });
        calendar.render();
    }
});
</script>';
require __DIR__ . '/../includes/footer.php';
