/**
 * Case Notary — loading spinners & skeleton screens
 */
(function () {
    'use strict';

    var overlay = null;
    var overlayMessage = null;
    var overlayCount = 0;
    var buttonStates = new WeakMap();

    function getOverlay() {
        if (!overlay) {
            overlay = document.getElementById('globalLoadingOverlay');
            overlayMessage = document.getElementById('globalLoadingMessage');
        }
        return overlay;
    }

    function shouldUsePageSkeleton() {
        var main = document.querySelector('.page-content');
        if (!main || main.hasAttribute('data-no-page-skeleton')) {
            return false;
        }
        if (document.body.classList.contains('page-chatbot')) {
            return false;
        }
        return !!main.querySelector('.page-loading-skeleton');
    }

    function show(message) {
        var el = getOverlay();
        if (!el) {
            return;
        }
        overlayCount += 1;
        if (overlayMessage) {
            overlayMessage.textContent = message || 'Loading…';
        }
        el.hidden = false;
        el.setAttribute('aria-busy', 'true');
        document.body.classList.add('is-global-loading');
    }

    function hide() {
        var el = getOverlay();
        if (!el) {
            return;
        }
        overlayCount = Math.max(0, overlayCount - 1);
        if (overlayCount > 0) {
            return;
        }
        el.hidden = true;
        el.setAttribute('aria-busy', 'false');
        document.body.classList.remove('is-global-loading');
    }

    function setButtonLoading(button, isLoading, loadingText) {
        if (!button) {
            return;
        }

        if (isLoading) {
            if (buttonStates.has(button)) {
                return;
            }
            buttonStates.set(button, {
                html: button.innerHTML,
                disabled: button.disabled
            });
            button.disabled = true;
            button.classList.add('is-btn-loading');
            button.setAttribute('aria-busy', 'true');
            var label = loadingText || button.getAttribute('data-loading-text') || 'Please wait…';
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="presentation" aria-hidden="true"></span>'
                + '<span>' + label + '</span>';
            return;
        }

        var state = buttonStates.get(button);
        if (!state) {
            button.classList.remove('is-btn-loading');
            button.removeAttribute('aria-busy');
            return;
        }
        button.innerHTML = state.html;
        button.disabled = state.disabled;
        button.classList.remove('is-btn-loading');
        button.removeAttribute('aria-busy');
        buttonStates.delete(button);
    }

    function skeletonList(count, itemClass) {
        var html = '';
        var total = Math.max(1, count || 3);
        var extraClass = itemClass ? ' ' + itemClass : '';

        for (var i = 0; i < total; i += 1) {
            html += '<div class="skeleton-list-item' + extraClass + '">'
                + '<div class="skeleton skeleton-line skeleton-line--md"></div>'
                + '<div class="skeleton skeleton-line skeleton-line--sm"></div>'
                + '</div>';
        }

        return html;
    }

    function skeletonTableRows(count) {
        var html = '<div class="skeleton-table">';
        var total = Math.max(1, count || 5);

        for (var i = 0; i < total; i += 1) {
            html += '<div class="skeleton-table-row">'
                + '<div class="skeleton skeleton-line skeleton-line--lg"></div>'
                + '<div class="skeleton skeleton-line skeleton-line--sm"></div>'
                + '<div class="skeleton skeleton-line skeleton-line--xs"></div>'
                + '</div>';
        }

        html += '</div>';
        return html;
    }

    function showIn(container, html) {
        if (!container) {
            return;
        }
        container.innerHTML = html;
        container.setAttribute('aria-busy', 'true');
        container.classList.add('is-skeleton-host');
    }

    function dismissPageSkeleton() {
        if (!shouldUsePageSkeleton()) {
            document.body.classList.add('page-ready');
            return;
        }

        var minMs = 100;
        var started = performance.now();

        function finish() {
            var elapsed = performance.now() - started;
            var delay = Math.max(0, minMs - elapsed);

            window.setTimeout(function () {
                document.body.classList.add('page-ready');
                var skeleton = document.querySelector('.page-loading-skeleton');
                if (!skeleton) {
                    return;
                }
                skeleton.addEventListener('transitionend', function () {
                    skeleton.remove();
                }, { once: true });
            }, delay);
        }

        if (document.readyState === 'complete') {
            finish();
        } else {
            window.addEventListener('load', finish, { once: true });
        }
    }

    function shouldSkipFormLoading(form) {
        if (!form || form.hasAttribute('data-no-global-loading')) {
            return true;
        }
        if (form.id === 'chatForm' || form.id === 'editRoleForm') {
            return true;
        }
        if (form.getAttribute('target') === '_blank') {
            return true;
        }
        return false;
    }

    function bindFormLoading() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (event.defaultPrevented || shouldSkipFormLoading(form)) {
                return;
            }

            var method = (form.getAttribute('method') || 'get').toLowerCase();
            var isPost = method === 'post';
            var isFilterGet = method === 'get' && !!form.closest('.table-toolbar, .case-toolbar, .settings-tabs');

            if (!isPost && !isFilterGet) {
                return;
            }

            var message = form.getAttribute('data-loading-message')
                || (isPost ? 'Saving…' : 'Loading…');
            show(message);

            var submitter = event.submitter;
            if (submitter && (submitter.type === 'submit' || submitter.tagName === 'BUTTON')) {
                setButtonLoading(submitter, true, submitter.getAttribute('data-loading-text') || message);
            }
        });
    }

    window.CaseNotaryLoading = {
        show: show,
        hide: hide,
        setButtonLoading: setButtonLoading,
        skeletonList: skeletonList,
        skeletonTableRows: skeletonTableRows,
        showIn: showIn
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', dismissPageSkeleton);
    } else {
        dismissPageSkeleton();
    }

    bindFormLoading();
})();
