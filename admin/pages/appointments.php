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

$statusColors = [
    'scheduled' => '#3aafa9',
    'confirmed' => '#10b981',
    'completed' => '#64748b',
    'cancelled' => '#ef4444',
];

$calendarEvents = [];
foreach ($appointments as $appt) {
    $start = appointmentStart($appt);
    if (!$start) {
        continue;
    }

    $end = appointmentEnd($appt) ?: date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));
    $calUrl = $appt['meeting_link'] ?? GoogleCalendarService::buildAddToCalendarUrl($appt, $appt);

    $calendarEvents[] = [
        'id'              => (string) ($appt['id'] ?? ''),
        'title'           => $appt['title'] ?? 'Appointment',
        'start'           => date('c', strtotime($start)),
        'end'             => date('c', strtotime($end)),
        'backgroundColor' => $statusColors[$appt['status'] ?? 'scheduled'] ?? '#3aafa9',
        'borderColor'     => $statusColors[$appt['status'] ?? 'scheduled'] ?? '#3aafa9',
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
            'hasConflict' => false,
        ],
    ];
}

for ($i = 0; $i < count($calendarEvents); $i++) {
    $statusA = $calendarEvents[$i]['extendedProps']['status'] ?? '';
    if (!in_array($statusA, ['scheduled', 'confirmed'], true)) {
        continue;
    }
    for ($j = $i + 1; $j < count($calendarEvents); $j++) {
        $statusB = $calendarEvents[$j]['extendedProps']['status'] ?? '';
        if (!in_array($statusB, ['scheduled', 'confirmed'], true)) {
            continue;
        }
        if (strtotime($calendarEvents[$i]['start']) < strtotime($calendarEvents[$j]['end'])
            && strtotime($calendarEvents[$j]['start']) < strtotime($calendarEvents[$i]['end'])) {
            $calendarEvents[$i]['classNames'] = array_merge($calendarEvents[$i]['classNames'] ?? [], ['fc-event-conflict']);
            $calendarEvents[$j]['classNames'] = array_merge($calendarEvents[$j]['classNames'] ?? [], ['fc-event-conflict']);
            $calendarEvents[$i]['extendedProps']['hasConflict'] = true;
            $calendarEvents[$j]['extendedProps']['hasConflict'] = true;
        }
    }
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
            <span><i style="background:#64748b"></i> Completed</span>
            <span><i style="background:#ef4444"></i> Cancelled</span>
            <span><i style="background:#f59e0b;border:2px solid #f59e0b"></i> Overlap</span>
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
                <div id="apptConflictAlert" class="alert alert-warning border-0 small d-none mb-3"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Client <span class="text-danger">*</span></label>
                        <select name="client_id" id="appt_client_id" class="form-select" required>
                            <option value="">Select client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>"><?= e(clientFullName($client)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Related Case</label>
                        <select name="case_id" id="appt_case_id" class="form-select">
                            <option value="">None</option>
                        </select>
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
    var conflictCheckUrl = ' . json_encode(url('actions/appointment-check.php')) . ';
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
    var conflictAlert = document.getElementById("apptConflictAlert");
    var endsAtInput = document.getElementById("appt_ends_at");
    var conflictTimer = null;
    var hasBlockingConflict = false;

    function setCreateMode() {
        if (apptActionInput) apptActionInput.value = "create_appointment";
        if (apptIdInput) apptIdInput.value = "";
        if (scheduleTitle) scheduleTitle.textContent = "Schedule Appointment";
        if (scheduleSubmitBtn) scheduleSubmitBtn.textContent = "Schedule & Notify Client";
        if (clientSelect) { clientSelect.disabled = false; clientSelect.required = true; }
        if (caseSelect) caseSelect.disabled = false;
        hideConflictAlert();
    }

    function hideConflictAlert() {
        hasBlockingConflict = false;
        if (conflictAlert) {
            conflictAlert.classList.add("d-none");
            conflictAlert.textContent = "";
        }
        if (scheduleSubmitBtn) scheduleSubmitBtn.disabled = false;
    }

    function showConflictAlert(items) {
        if (!conflictAlert) return;
        if (!items || !items.length) {
            hideConflictAlert();
            return;
        }
        hasBlockingConflict = true;
        var lines = items.map(function(item) {
            return "• " + item.title + " (" + item.start + " – " + item.end + ")" + (item.client ? " — " + item.client : "");
        });
        conflictAlert.innerHTML = "<strong>Scheduling conflict:</strong> this time overlaps with:<br>" + lines.join("<br>");
        conflictAlert.classList.remove("d-none");
        if (scheduleSubmitBtn) scheduleSubmitBtn.disabled = true;
    }

    function checkConflicts() {
        if (!startsAtInput || !startsAtInput.value) {
            hideConflictAlert();
            return;
        }
        var params = new URLSearchParams();
        params.set("starts_at", startsAtInput.value);
        if (endsAtInput && endsAtInput.value) params.set("ends_at", endsAtInput.value);
        if (apptIdInput && apptIdInput.value) params.set("appointment_id", apptIdInput.value);

        fetch(conflictCheckUrl + "?" + params.toString(), { headers: { "Accept": "application/json" } })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error && !data.conflicts) {
                    hideConflictAlert();
                    return;
                }
                showConflictAlert(data.conflicts || []);
            })
            .catch(function() { hideConflictAlert(); });
    }

    function scheduleConflictCheck() {
        clearTimeout(conflictTimer);
        conflictTimer = setTimeout(checkConflicts, 300);
    }

    if (startsAtInput) startsAtInput.addEventListener("change", scheduleConflictCheck);
    if (endsAtInput) endsAtInput.addEventListener("change", scheduleConflictCheck);

    var scheduleForm = document.getElementById("scheduleForm");
    if (scheduleForm) {
        scheduleForm.addEventListener("submit", function(e) {
            if (hasBlockingConflict) {
                e.preventDefault();
                return false;
            }
        });
    }

    function setEditMode(data) {
        if (apptActionInput) apptActionInput.value = "update_appointment";
        if (apptIdInput) apptIdInput.value = data.id || "";
        if (scheduleTitle) scheduleTitle.textContent = "Edit Appointment";
        if (scheduleSubmitBtn) scheduleSubmitBtn.textContent = "Save Changes";
        if (clientSelect) { clientSelect.disabled = true; clientSelect.required = false; }
        if (caseSelect) caseSelect.disabled = true;
        document.getElementById("appt_title").value = data.title || "";
        document.getElementById("appt_starts_at").value = data.starts || "";
        document.getElementById("appt_ends_at").value = data.ends || "";
        document.getElementById("appt_location").value = data.location || "";
        document.getElementById("appt_status").value = data.status || "scheduled";
        document.getElementById("appt_description").value = data.description || "";
        hideConflictAlert();
        if (scheduleModal) scheduleModal.show();
        scheduleConflictCheck();
    }

    document.querySelectorAll(".btn-edit-appt").forEach(function(btn) {
        btn.addEventListener("click", function() {
            setEditMode({
                id: btn.dataset.id,
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
        hideConflictAlert();
        scheduleModal.show();
        scheduleConflictCheck();
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
        if (props.hasConflict) {
            document.getElementById("eventDetailDescription").textContent = (props.description || "—") + " ⚠ Overlaps with another appointment";
        }

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
        var savedView = localStorage.getItem("appointmentCalendarView") || "timeGridWeek";
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: savedView,
            height: "auto",
            nowIndicator: true,
            slotMinTime: "07:00:00",
            slotMaxTime: "20:00:00",
            allDaySlot: false,
            businessHours: {
                daysOfWeek: [1, 2, 3, 4, 5, 6],
                startTime: "08:00",
                endTime: "18:00"
            },
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek"
            },
            buttonText: {
                month: "Month",
                week: "Week",
                day: "Day",
                list: "List"
            },
            events: calendarEvents,
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                showEventDetails(info.event);
            },
            dateClick: function(info) {
                openScheduleModal(info.date);
            },
            datesSet: function(info) {
                localStorage.setItem("appointmentCalendarView", info.view.type);
            },
            eventTimeFormat: {
                hour: "numeric",
                minute: "2-digit",
                meridiem: "short"
            },
            slotLabelFormat: {
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
