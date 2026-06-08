/**
 * Password show/hide — class-based masking (no slow type= switching).
 */
(function () {
    'use strict';

    var useTextSecurity = (function () {
        var el = document.createElement('input');
        el.style.webkitTextSecurity = 'disc';
        return el.style.webkitTextSecurity === 'disc';
    })();

    function findInput(field) {
        return field.querySelector('input.login-pw-masked')
            || field.querySelector('.login-pw-input-wrap input[name]');
    }

    function setRevealState(field, visible) {
        var input = findInput(field);
        var btn = field.querySelector('.login-pw-reveal');
        if (!input || !btn) {
            return;
        }

        field.classList.toggle('login-pw-field--revealed', visible);
        btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
        btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        btn.title = visible ? 'Hide password' : 'Show password';

        if (!useTextSecurity) {
            input.setAttribute('type', visible ? 'text' : 'password');
        }
    }

    function onRevealClick(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var btn = event.currentTarget;
        var field = btn.closest('.login-pw-field');
        if (!field) {
            return;
        }

        var input = findInput(field);
        if (!input || input.disabled || input.readOnly) {
            return;
        }

        setRevealState(field, !field.classList.contains('login-pw-field--revealed'));
    }

    function prepareInput(input) {
        if (!input || input.dataset.pwPrepared === '1') {
            return;
        }

        input.dataset.pwPrepared = '1';
        input.classList.add('login-pw-masked');

        input.setAttribute('spellcheck', 'false');

        if (useTextSecurity) {
            input.setAttribute('type', 'text');
        } else {
            input.setAttribute('type', 'password');
        }

        var legacyPlain = input.parentElement && input.parentElement.querySelector('.login-pw-plain');
        if (legacyPlain) {
            legacyPlain.remove();
        }
    }

    function bindButton(btn) {
        if (!btn || btn.dataset.pwBound === '1') {
            return;
        }

        btn.dataset.pwBound = '1';
        btn.setAttribute('type', 'button');
        btn.addEventListener('click', onRevealClick);
    }

    function bindAll(root) {
        var scope = root && root.querySelectorAll ? root : document;

        scope.querySelectorAll('.login-pw-field').forEach(function (field) {
            prepareInput(findInput(field));
            bindButton(field.querySelector('.login-pw-reveal'));
        });
    }

    window.bootPasswordRevealFields = bindAll;

    bindAll();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindAll();
        });
    }

    if (typeof MutationObserver !== 'undefined') {
        var pending = null;
        var observer = new MutationObserver(function () {
            if (pending) {
                return;
            }
            pending = window.setTimeout(function () {
                pending = null;
                bindAll();
            }, 50);
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();
