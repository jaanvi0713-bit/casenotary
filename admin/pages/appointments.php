<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Appointments';
$allAppointments = getAllAppointments();
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
        clientFullName($appt),
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
$clients = getAllClients();
$stats = getDashboardStats();
$pendingRequests = count(array_filter($allAppointments, static fn(array $a): bool => ($a['status'] ?? '') === 'requested'));
$pageSubtitle = $stats['upcoming_appointments'] . ' upcoming'
    . ($pendingRequests > 0 ? ' · ' . $pendingRequests . ' pending request' . ($pendingRequests === 1 ? '' : 's') : '');

$addedId = (int) ($_GET['added'] ?? 0);
$addedAppointment = null;
$addedCalendarUrl = null;
$addedOutlookUrl = null;
$addedIcsUrl = null;

if ($addedId > 0) {
    $addedAppointment = AppointmentService::getById($addedId);
    if ($addedAppointment) {
        $addedClient = ClientService::getById((int) ($addedAppointment['client_id'] ?? 0)) ?? $addedAppointment;
        $addedLinks = GoogleCalendarService::getCalendarLinks($addedId, $addedAppointment, $addedClient);
        $addedCalendarUrl = $addedLinks['google'];
        $addedOutlookUrl = $addedLinks['outlook'];
        $addedIcsUrl = $addedLinks['ics'];
    }
}

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

    $links = GoogleCalendarService::getCalendarLinks((int) ($appt['id'] ?? 0), $appt, $appt);

    foreach (buildAppointmentCalendarEvents($appt, [
        'client'      => clientFullName($appt),
        'case'        => appointmentCaseLabel($appt),
        'status'      => $appt['status'] ?? 'scheduled',
        'location'    => $appt['location'] ?? '',
        'description' => $appt['description'] ?? '',
        'startLabel'  => formatDateTime($calStart, 'M j, Y g:i A'),
        'endLabel'    => formatDateTime($calEnd, 'M j, Y g:i A'),
        'calUrl'      => $links['google'],
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

<?php if ($addedAppointment && $addedCalendarUrl): ?>
<div class="alert alert-success border-0 shadow-sm mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <strong><i class="bi bi-check-circle me-2"></i>Appointment scheduled!</strong>
            <span class="d-block small mt-1">“<?= e($addedAppointment['title']) ?>” — add it to your calendar below.</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e($addedCalendarUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm" id="openGoogleCalendar">
                <i class="bi bi-google me-1"></i> Add to Google Calendar
            </a>
            <?php if ($addedOutlookUrl): ?>
            <a href="<?= e($addedOutlookUrl) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                <i class="bi bi-microsoft me-1"></i> Add to Outlook Calendar
            </a>
            <?php endif; ?>
            <a href="<?= e($addedIcsUrl) ?>" class="btn btn-soft btn-sm">
                <i class="bi bi-download me-1"></i> Download .ics
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($pendingRequests > 0): ?>
<div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <strong><i class="bi bi-inbox me-2"></i><?= $pendingRequests ?> appointment request<?= $pendingRequests === 1 ? '' : 's' ?> awaiting review</strong>
            <span class="d-block small mt-1">Open a request, set the status to Scheduled or Confirmed, and save to approve it.</span>
        </div>
        <a href="<?= e(url('pages/appointments.php?status=requested')) ?>" class="btn btn-soft btn-sm">
            <i class="bi bi-funnel me-1"></i> Show requests
        </a>
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
            <p class="saas-card-subtitle"><?= $totalAppointments ?> total appointments</p>
        </div>
    </div>
    <form method="get" class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="tableSearch" name="q" value="<?= e($q) ?>" placeholder="Search by service...">
        </div>
        <select class="form-select form-select-sm table-filter" id="statusFilter" name="status" onchange="this.form.requestSubmit()">
            <option value="">All statuses</option>
            <option value="requested" <?= $statusFilter === 'requested' ? 'selected' : '' ?>>Requested</option>
            <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </form>
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
                            <?php
                            $caseLabel = appointmentCaseLabel($appt);
                            $searchBlob = caseRowSearchBlob($appt, [
                                $appt['title'] ?? '',
                                clientFullName($appt),
                                appointmentCaseLabel($appt),
                                $appt['location'] ?? '',
                                $appt['description'] ?? '',
                            ]);
                            ?>
                            <tr data-status="<?= e($appt['status']) ?>" data-search="<?= e($searchBlob) ?>"<?= ($appt['status'] ?? '') === 'requested' ? ' class="table-row-requested"' : '' ?>>
                                <td>
                                    <span class="table-primary"><?= formatDate(appointmentStart($appt)) ?></span>
                                    <span class="table-secondary d-block">
                                        <?= formatDateTime(appointmentStart($appt), 'g:i A') ?>
                                    </span>
                                </td>
                                <td><?= e($appt['title']) ?></td>
                                <td><?= e(clientFullName($appt)) ?></td>
                                <td class="text-muted"><?= e($caseLabel !== 'None' ? $caseLabel : '—') ?></td>
                                <td><?= statusBadge($appt['status']) ?></td>
                                <td class="appointment-cell-calendar">
                                    <?php
                                    $links = appointmentStart($appt)
                                        ? GoogleCalendarService::getCalendarLinks((int) ($appt['id'] ?? 0), $appt, $appt)
                                        : null;
                                    ?>
                                    <?php if ($links && $links['google'] && ($appt['status'] ?? '') !== 'requested'): ?>
                                        <div class="appointment-inline-actions">
                                        <a href="<?= e($links['google']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm" title="Add to Google Calendar">
                                            <i class="bi bi-google"></i>
                                        </a>
                                        <?php if ($links['outlook']): ?>
                                        <a href="<?= e($links['outlook']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm" title="Add to Outlook Calendar">
                                            <i class="bi bi-microsoft"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?= e($links['ics']) ?>" class="btn btn-soft btn-sm" title="Download .ics">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        </div>
                                    <?php elseif (($appt['status'] ?? '') === 'requested'): ?>
                                        <span class="text-muted small">Awaiting approval</span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="appointment-cell-actions">
                                    <div class="appointment-inline-actions">
                                    <button type="button" class="btn btn-soft btn-sm btn-edit-appt"
                                        data-id="<?= (int) $appt['id'] ?>"
                                        data-client-id="<?= (int) ($appt['client_id'] ?? 0) ?>"
                                        data-case-id="<?= (int) ($appt['case_id'] ?? $appt['resolved_case_id'] ?? 0) ?>"
                                        data-client="<?= e(clientFullName($appt)) ?>"
                                        data-case="<?= e($caseLabel) ?>"
                                        data-title="<?= e($appt['title']) ?>"
                                        data-starts="<?= e(date('Y-m-d\TH:i', strtotime(appointmentStart($appt)))) ?>"
                                        data-ends="<?= e(appointmentEnd($appt) ? date('Y-m-d\TH:i', strtotime(appointmentEnd($appt))) : '') ?>"
                                        data-location="<?= e($appt['location'] ?? '') ?>"
                                        data-status="<?= e($appt['status']) ?>"
                                        data-description="<?= e($appt['description'] ?? '') ?>">Edit</button>
                                    <form method="post" action="<?= url('actions/appointment-action.php') ?>" class="d-inline" onsubmit="return confirm('Delete this appointment permanently? This cannot be undone.');">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="delete_appointment">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $appt['id'] ?>">
                                        <button type="submit" class="btn btn-soft-danger btn-sm">Delete</button>
                                    </form>
                                    </div>
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
                <a href="#" target="_blank" rel="noopener" class="btn btn-soft btn-sm d-none" id="eventDetailOutlook">
                    <i class="bi bi-microsoft me-1"></i> Outlook Calendar
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
                    <div class="col-md-4">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" id="appt_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Time <span class="text-danger">*</span></label>
                        <input type="time" id="appt_start_time" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Time</label>
                        <input type="time" id="appt_end_time" class="form-control">
                    </div>
                    <input type="hidden" name="starts_at" id="appt_starts_at">
                    <input type="hidden" name="ends_at" id="appt_ends_at">
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="appt_location" class="form-control" placeholder="Office, Zoom link, etc.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="appt_status" class="form-select">
                            <option value="requested">Requested</option>
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
    $casesByClient[(int) $c['client_id']][] = [
        'id'     => (int) $c['id'],
        'label'  => $c['case_number'] . ' — ' . $c['title'],
        'status' => $c['status'] ?? '',
    ];
}
foreach ($casesByClient as &$clientCases) {
    usort($clientCases, static function (array $a, array $b): int {
        $closed = ['completed', 'closed'];
        $aOpen = !in_array($a['status'], $closed, true);
        $bOpen = !in_array($b['status'], $closed, true);

        return $bOpen <=> $aOpen;
    });
}
unset($clientCases);
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var casesByClient = ' . json_encode($casesByClient) . ';
    var calendarEvents = ' . json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
    var clientSelect = document.getElementById("appt_client_id");
    var caseSelect = document.getElementById("appt_case_id");
    var scheduleModalEl = document.getElementById("scheduleModal");
    var scheduleModal = scheduleModalEl ? new bootstrap.Modal(scheduleModalEl) : null;
    var eventModalEl = document.getElementById("eventDetailModal");
    var eventModal = eventModalEl ? new bootstrap.Modal(eventModalEl) : null;
    var startsAtInput = document.getElementById("appt_starts_at");
    var endsAtInput = document.getElementById("appt_ends_at");
    var apptDateInput = document.getElementById("appt_date");
    var apptStartTimeInput = document.getElementById("appt_start_time");
    var apptEndTimeInput = document.getElementById("appt_end_time");
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
        if (apptDateInput) apptDateInput.value = "";
        if (apptStartTimeInput) apptStartTimeInput.value = "";
        if (apptEndTimeInput) apptEndTimeInput.value = "";
        if (startsAtInput) startsAtInput.value = "";
        if (endsAtInput) endsAtInput.value = "";
        if (scheduleForm) scheduleForm.dataset.endDate = "";
    }

    function parseDateTimeParts(value) {
        if (!value) {
            return { date: "", time: "" };
        }
        var normalized = String(value).trim().replace(" ", "T");
        var parts = normalized.split("T");
        return {
            date: parts[0] || "",
            time: (parts[1] || "").substring(0, 5)
        };
    }

    function setScheduleFields(startValue, endValue) {
        var start = parseDateTimeParts(startValue);
        var end = parseDateTimeParts(endValue);
        if (apptDateInput) apptDateInput.value = start.date;
        if (apptStartTimeInput) apptStartTimeInput.value = start.time;
        if (apptEndTimeInput) apptEndTimeInput.value = end.time;
    }

    function syncHiddenDateTimes(endDateOverride) {
        var dateVal = apptDateInput ? apptDateInput.value : "";
        var startTime = apptStartTimeInput ? apptStartTimeInput.value : "";
        var endTime = apptEndTimeInput ? apptEndTimeInput.value : "";
        var endDateVal = endDateOverride || dateVal;

        if (startsAtInput) {
            startsAtInput.value = dateVal && startTime ? (dateVal + "T" + startTime) : "";
        }
        if (endsAtInput) {
            endsAtInput.value = endTime ? (endDateVal + "T" + endTime) : "";
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
        setScheduleFields(data.starts || "", data.ends || "");
        var startParts = parseDateTimeParts(data.starts || "");
        var endParts = parseDateTimeParts(data.ends || "");
        if (scheduleForm) {
            scheduleForm.dataset.endDate = (endParts.date && endParts.date !== startParts.date) ? endParts.date : "";
        }
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
        scheduleForm.addEventListener("submit", function(e) {
            if (apptActionInput && apptActionInput.value === "update_appointment" && clientSelect) {
                clientSelect.required = false;
                clientSelect.disabled = true;
            }
            syncHiddenDateTimes(scheduleForm.dataset.endDate || "");
            if (!startsAtInput || !startsAtInput.value) {
                e.preventDefault();
                alert("Please select a date and start time.");
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
            var cases = casesByClient[cid] || [];
            cases.forEach(function(c) {
                var opt = document.createElement("option");
                opt.value = c.id;
                opt.textContent = c.label;
                caseSelect.appendChild(opt);
            });
            if (cases.length > 0) {
                caseSelect.value = String(cases[0].id);
            }
        });
    }

    function pad(n) { return String(n).padStart(2, "0"); }

    function toDateValue(date) {
        return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
    }

    function openScheduleModal(date) {
        if (!scheduleModal || !apptDateInput || !apptStartTimeInput) return;
        var start = new Date(date);
        var startTime = "09:00";
        if (start.getHours() !== 0 || start.getMinutes() !== 0) {
            startTime = pad(start.getHours()) + ":" + pad(start.getMinutes());
        }
        apptDateInput.value = toDateValue(start);
        apptStartTimeInput.value = startTime;
        if (apptEndTimeInput) apptEndTimeInput.value = "";
        if (scheduleForm) scheduleForm.dataset.endDate = "";
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
        var outlookBtn = document.getElementById("eventDetailOutlook");
        var icsBtn = document.getElementById("eventDetailIcs");
        var showCalendar = props.status !== "requested";
        if (showCalendar && props.calUrl) {
            googleBtn.href = props.calUrl;
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

        if (eventModal) eventModal.show();
    }

    var calendarEl = document.getElementById("appointmentCalendar");
    if (calendarEl && window.FullCalendar) {
        var calendar = new FullCalendar.Calendar(calendarEl, Object.assign({
            timeZone: "local",
            initialView: "dayGridMonth",
            initialDate: ' . json_encode($calendarInitialDate) . ',
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
        }, window.AppointmentCalendar ? window.AppointmentCalendar.calendarOptions() : {}));
        calendar.render();
    }

    var filterRequestedBtn = document.getElementById("filterRequestedBtn");
    var statusFilter = document.getElementById("statusFilter");
    if (filterRequestedBtn && statusFilter) {
        filterRequestedBtn.addEventListener("click", function() {
            statusFilter.value = "requested";
            statusFilter.dispatchEvent(new Event("change"));
            document.getElementById("dataTable")?.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    }
});
</script>';
require __DIR__ . '/../includes/footer.php';
