/**
 * Topbar notifications — mark all read + polling refresh.
 */
(function () {
    'use strict';

    var root = document.getElementById('topbarNotifications');
    if (!root) {
        return;
    }

    var apiUrl = root.getAttribute('data-api-url') || '';
    var csrfName = root.getAttribute('data-csrf-name') || '_csrf_token';
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') || '' : '';
    var bellBtn = root.querySelector('.topbar-btn');
    var listEl = document.getElementById('notificationDropdownList');
    var badgeEl = document.getElementById('notificationHeaderBadge');
    var markAllBtn = document.getElementById('notificationMarkAllRead');
    var pollMs = 60000;
    var pollTimer = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatBadgeCount(count) {
        return count > 9 ? '9+' : String(count);
    }

    function updateBellDot(count) {
        if (!bellBtn) {
            return;
        }

        var dot = bellBtn.querySelector('.notification-dot');

        if (count > 0) {
            if (!dot) {
                dot = document.createElement('span');
                dot.className = 'notification-dot';
                bellBtn.appendChild(dot);
            }
            dot.textContent = formatBadgeCount(count);
        } else if (dot) {
            dot.remove();
        }
    }

    function updateHeaderBadge(count) {
        if (!badgeEl) {
            return;
        }

        if (count > 0) {
            badgeEl.textContent = count + ' new';
            badgeEl.classList.remove('d-none');
        } else {
            badgeEl.classList.add('d-none');
        }
    }

    function updateMarkAllButton(count) {
        if (!markAllBtn) {
            return;
        }

        markAllBtn.classList.toggle('d-none', count <= 0);
    }

    function renderNotificationList(notifications) {
        if (!listEl) {
            return;
        }

        if (!notifications || notifications.length === 0) {
            listEl.innerHTML = '<div class="dropdown-item-text text-muted text-center py-4 small">No notifications</div>';
            return;
        }

        listEl.innerHTML = notifications.map(function (item) {
            var unreadClass = item.is_read ? '' : ' unread';
            return '<a href="' + escapeHtml(item.href) + '" class="dropdown-item notification-item' + unreadClass + '">' +
                '<div class="notification-icon"><i class="bi ' + escapeHtml(item.icon) + '"></i></div>' +
                '<div class="notification-content">' +
                '<strong>' + escapeHtml(item.title) + '</strong>' +
                '<p>' + escapeHtml(item.message) + '</p>' +
                '<small>' + escapeHtml(item.time_ago) + '</small>' +
                '</div></a>';
        }).join('');
    }

    function applyPayload(payload) {
        if (!payload || !payload.success) {
            return;
        }

        var count = Number(payload.unread_count) || 0;
        updateBellDot(count);
        updateHeaderBadge(count);
        updateMarkAllButton(count);
        renderNotificationList(payload.notifications || []);
    }

    function fetchNotifications() {
        if (!apiUrl) {
            return Promise.resolve();
        }

        return fetch(apiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            })
            .then(applyPayload)
            .catch(function () {
                /* ignore polling errors */
            });
    }

    function markAllRead() {
        if (!apiUrl || !csrfToken) {
            return;
        }

        var body = new URLSearchParams();
        body.append('action', 'mark_all_read');
        body.append(csrfName, csrfToken);

        fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(applyPayload)
            .catch(function () {
                /* ignore */
            });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            markAllRead();
        });
    }

    function startPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        pollTimer = window.setInterval(function () {
            if (document.visibilityState === 'visible') {
                fetchNotifications();
            }
        }, pollMs);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            fetchNotifications();
        }
    });

    startPolling();
})();
