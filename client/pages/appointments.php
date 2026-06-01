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
    $start = appointmentEffectiveStart($appt);
    if (!$start) {
        continue;
    }

    $end = appointmentEffectiveEnd($appt) ?: date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));
    $links = GoogleCalendarService::getCalendarLinks((int) ($appt['id'] ?? 0), $appt, $client, true);

    $calendarEvents[] = [
        'id'              => (string) ($appt['id'] ?? ''),
        'title'           => $appt['title'] ?? 'Appointment',
        'start'           => calendarEventDateTime($start),
        'end'             => calendarEventDateTime($end),
        'backgroundColor' => $statusColors[$appt['status'] ?? 'scheduled'] ?? '#3aafa9',
        'borderColor'     => $statusColors[$appt['status'] ?? 'scheduled'] ?? '#3aafa9',
        'extendedProps'   => [
            'status'      => $appt['status'] ?? 'scheduled',
            'location'    => $appt['location'] ?? '',
            'description' => $appt['description'] ?? '',
            'startLabel'  => formatDateTime($start, 'M j, Y g:i A'),
            'endLabel'    => formatDateTime($end, 'M j, Y g:i A'),
            'googleUrl'   => $links['google'],
            'outlookUrl'  => $links['outlook'],
            'icsUrl'      => $links['ics'],
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
                                            <?php if ($showCalendar): ?>
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
        timeZone: 'local',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        height: 'auto',
        events: events,
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

            const showCalendar = ['scheduled', 'confirmed'].includes(props.status);
            const googleBtn = document.getElementById('apptModalGoogle');
            const outlookBtn = document.getElementById('apptModalOutlook');
            const icsBtn = document.getElementById('apptModalIcs');

            if (showCalendar && props.googleUrl) {
                googleBtn.href = props.googleUrl;
                googleBtn.classList.remove('d-none');
            } else {
                googleBtn.classList.add('d-none');
            }

            if (showCalendar && props.outlookUrl) {
                outlookBtn.href = props.outlookUrl;
                outlookBtn.classList.remove('d-none');
            } else {
                outlookBtn.classList.add('d-none');
            }

            if (showCalendar && props.icsUrl) {
                icsBtn.href = props.icsUrl;
                icsBtn.classList.remove('d-none');
            } else {
                icsBtn.classList.add('d-none');
            }

            bsModal.show();
        }
    });

    calendar.render();
});
</script>
HTML;

require __DIR__ . '/../includes/footer.php';
