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
$upcomingCount = (int) (getClientDashboardStats($clientId)['upcoming_appointments'] ?? 0);
$pageSubtitle = $upcomingCount . ' upcoming';

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
            'status'      => $appt['status'] ?? 'scheduled',
            'location'    => $appt['location'] ?? '',
            'description' => $appt['description'] ?? '',
            'startLabel'  => formatDateTime($start, 'M j, Y g:i A'),
            'endLabel'    => formatDateTime($end, 'M j, Y g:i A'),
            'calUrl'      => $calUrl,
            'icsUrl'      => clientUrl('actions/appointment-ics.php?id=' . (int) ($appt['id'] ?? 0)),
        ],
    ];
}

$pageStyles = '<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">';

require __DIR__ . '/../includes/header.php';
?>

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
                                    $calUrl = $appt['meeting_link'] ?? ($start ? GoogleCalendarService::buildAddToCalendarUrl($appt, $appt) : null);
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
                                            <?php if ($calUrl && in_array($appt['status'] ?? '', ['scheduled', 'confirmed'], true)): ?>
                                                <a href="<?= e($calUrl) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                                                    <i class="bi bi-google"></i> Add to Calendar
                                                </a>
                                                <a href="<?= e(clientUrl('actions/appointment-ics.php?id=' . (int) $appt['id'])) ?>" class="btn btn-soft btn-sm">
                                                    <i class="bi bi-download"></i> .ics
                                                </a>
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
                <a href="#" id="apptModalCalLink" class="btn btn-primary btn-sm d-none" target="_blank" rel="noopener">
                    <i class="bi bi-google me-1"></i> Add to Google Calendar
                </a>
                <a href="#" id="apptModalIcsLink" class="btn btn-soft btn-sm d-none">
                    <i class="bi bi-download me-1"></i> Download .ics
                </a>
                <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script type="application/json" id="calendarEventsData">'
    . json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
    . '</script>';
$pageScripts .= <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('appointmentCalendar');
    if (!calendarEl || typeof FullCalendar === 'undefined') return;

    const events = JSON.parse(document.getElementById('calendarEventsData').textContent);
    const modal = document.getElementById('appointmentDetailModal');
    const bsModal = modal ? new bootstrap.Modal(modal) : null;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: localStorage.getItem('appointmentCalendarView') || 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: { month: 'Month', week: 'Week', day: 'Day', list: 'List' },
        height: 'auto',
        nowIndicator: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        events: events,
        datesSet: function(info) {
            localStorage.setItem('appointmentCalendarView', info.view.type);
        },
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        eventClick: function (info) {
            if (!bsModal) return;
            const props = info.event.extendedProps || {};
            document.getElementById('apptModalTitle').textContent = info.event.title;
            document.getElementById('apptModalWhen').textContent = props.startLabel + (props.endLabel ? ' – ' + props.endLabel : '');
            document.getElementById('apptModalStatus').textContent = (props.status || '').replace(/_/g, ' ');
            document.getElementById('apptModalLocation').textContent = props.location || '—';
            document.getElementById('apptModalDesc').textContent = props.description || '—';
            document.getElementById('apptModalLocationWrap').style.display = props.location ? '' : 'none';
            document.getElementById('apptModalDescWrap').style.display = props.description ? '' : 'none';
            const calLink = document.getElementById('apptModalCalLink');
            const icsLink = document.getElementById('apptModalIcsLink');
            if (props.calUrl && ['scheduled', 'confirmed'].includes(props.status)) {
                calLink.href = props.calUrl;
                calLink.classList.remove('d-none');
            } else {
                calLink.classList.add('d-none');
            }
            if (props.icsUrl && ['scheduled', 'confirmed'].includes(props.status)) {
                icsLink.href = props.icsUrl;
                icsLink.classList.remove('d-none');
            } else {
                icsLink.classList.add('d-none');
            }
            bsModal.show();
        }
    });

    calendar.render();
});
</script>
HTML;

require __DIR__ . '/../includes/footer.php';
