/**
 * Notary Management System — Admin Portal JS
 */
(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    // Restore collapsed state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        body.classList.add('sidebar-collapsed');
    }

    // Mobile sidebar toggle
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            sidebarOverlay?.classList.toggle('show');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar?.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        });
    }

    // Desktop sidebar collapse
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function () {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
        });
    }

    // Subtle metric card hover (no scroll animation — cleaner)
    document.querySelectorAll('.metric-card').forEach(function (card) {
        card.addEventListener('mouseenter', function () {
            this.style.transition = 'transform 0.2s ease, box-shadow 0.2s ease';
        });
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // Portal selector on login page
    const portalOptions = document.querySelectorAll('.portal-option');
    const portalInput = document.getElementById('portalInput');
    const authVisual = document.getElementById('authVisual');
    const authLogo = document.getElementById('authLogo');
    const authTagline = document.getElementById('authTagline');
    const authFeatures = document.getElementById('authFeatures');
    const submitText = document.getElementById('submitText');
    const forgotLink = document.getElementById('forgotLink');

    if (portalOptions.length) {
        const portalContent = {
            admin: {
                tagline: 'Secure admin portal for managing notary operations, clients, cases, and documents.',
                logoIcon: 'bi-shield-check',
                submitLabel: 'Sign In to Admin Portal',
                showForgot: true,
                features: [
                    { icon: 'bi-lock-fill', text: 'Enterprise-grade security' },
                    { icon: 'bi-graph-up-arrow', text: 'Real-time analytics & reporting' },
                    { icon: 'bi-people-fill', text: 'Full client & case management' }
                ]
            },
            client: {
                tagline: 'Your secure client portal to track cases, view documents, appointments, and invoices.',
                logoIcon: 'bi-person-badge',
                submitLabel: 'Sign In to Client Portal',
                showForgot: false,
                features: [
                    { icon: 'bi-briefcase', text: 'Track your active cases' },
                    { icon: 'bi-file-earmark-text', text: 'Access documents anytime' },
                    { icon: 'bi-calendar-check', text: 'Manage appointments & invoices' }
                ]
            }
        };

        function renderFeatures(features) {
            if (!authFeatures) return;
            authFeatures.innerHTML = features.map(function (item) {
                return '<div class="auth-feature"><i class="bi ' + item.icon + '"></i><span>' + item.text + '</span></div>';
            }).join('');
        }

        function setPortal(portal) {
            const content = portalContent[portal];
            if (!content) return;

            portalOptions.forEach(function (option) {
                const isActive = option.dataset.portal === portal;
                option.classList.toggle('active', isActive);
                option.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            if (portalInput) portalInput.value = portal;
            if (authVisual) authVisual.classList.toggle('portal-client', portal === 'client');
            if (authLogo) authLogo.innerHTML = '<i class="bi ' + content.logoIcon + '"></i>';
            if (authTagline) authTagline.textContent = content.tagline;
            if (submitText) submitText.textContent = content.submitLabel;
            if (forgotLink) forgotLink.style.display = content.showForgot ? '' : 'none';
            renderFeatures(content.features);

            const url = new URL(window.location.href);
            url.searchParams.set('portal', portal);
            window.history.replaceState({}, '', url);
        }

        portalOptions.forEach(function (option) {
            option.addEventListener('click', function () {
                setPortal(this.dataset.portal);
            });
        });

        if (portalInput && portalContent[portalInput.value]) {
            setPortal(portalInput.value);
        }
    }
    // Generic table search & filter (list pages)
    const tableSearch = document.getElementById('tableSearch');
    const statusFilter = document.getElementById('statusFilter');
    const priorityFilter = document.getElementById('priorityFilter');
    const dataTable = document.getElementById('dataTable');

    if (dataTable) {
        const rows = dataTable.querySelectorAll('tbody tr');

        function filterTable() {
            const q = (tableSearch?.value || '').toLowerCase();
            const status = statusFilter?.value || '';
            const priority = priorityFilter?.value || '';
            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                const matchSearch = !q || text.includes(q);
                const matchStatus = !status || row.dataset.status === status;
                const matchPriority = !priority || row.dataset.priority === priority;
                row.style.display = matchSearch && matchStatus && matchPriority ? '' : 'none';
            });
        }

        tableSearch?.addEventListener('input', filterTable);
        statusFilter?.addEventListener('change', filterTable);
        priorityFilter?.addEventListener('change', filterTable);
    }
})();

window.AppointmentCalendar = {
    escapeHtml: function (value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },

    eventContent: function (info) {
        var props = info.event.extendedProps || {};
        var title = info.event.title || 'Appointment';
        var esc = window.AppointmentCalendar.escapeHtml;

        if (props.segmentRole === 'past') {
            var timeHtml = props.timeLabel
                ? '<div class="fc-event-time">' + esc(props.timeLabel) + '</div>'
                : '';
            return {
                html: '<div class="fc-event-main-frame fc-appt-split-past">' +
                    timeHtml +
                    '<div class="fc-event-title-container"><div class="fc-event-title fc-sticky">' + esc(title) + '</div></div>' +
                    '</div>'
            };
        }

        if (props.segmentRole === 'active') {
            if (!info.isStart) {
                return { html: '<div class="fc-event-main-frame fc-appt-continuation-bar"></div>' };
            }
            return {
                html: '<div class="fc-event-main-frame fc-appt-split-active">' +
                    '<div class="fc-event-title-container"><div class="fc-event-title fc-sticky">' + esc(title) + '</div></div>' +
                    '</div>'
            };
        }

        return true;
    },

    eventOrder: function (a, b) {
        if (a.groupId && a.groupId === b.groupId) {
            var aPart = (a.extendedProps && a.extendedProps.segmentPart) || 0;
            var bPart = (b.extendedProps && b.extendedProps.segmentPart) || 0;
            return aPart - bPart;
        }

        return 0;
    },

    eventDidMount: function (info) {
        var props = info.event.extendedProps || {};
        if (info.event.groupId) {
            info.el.setAttribute('data-appt-group', info.event.groupId);
        }
        if (props.isSplit) {
            info.el.setAttribute('data-appt-split', 'true');
        }
        if (props.segmentRole === 'active') {
            info.el.querySelectorAll('.fc-event-time').forEach(function (node) {
                node.remove();
            });
        }
    },

    calendarOptions: function (overrides) {
        var base = {
            eventContent: window.AppointmentCalendar.eventContent,
            eventOrder: window.AppointmentCalendar.eventOrder,
            eventDidMount: window.AppointmentCalendar.eventDidMount
        };

        if (!overrides) {
            return base;
        }

        return Object.assign({}, base, overrides);
    }
};
