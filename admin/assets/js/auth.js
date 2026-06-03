/**
 * Auth pages — password visibility toggle
 */
(function () {
    'use strict';

    function buildInput(visible, source) {
        var input = document.createElement('input');
        input.type = visible ? 'text' : 'password';
        input.className = source.className;
        input.id = source.id;
        input.name = source.name;
        input.value = source.value;
        input.required = source.required;
        input.setAttribute('spellcheck', 'false');
        input.setAttribute('autocomplete', visible ? 'off' : 'current-password');
        return input;
    }

    function updateToggleUi(button, visible) {
        var icon = button.querySelector('i');
        if (icon) {
            icon.className = 'bi ' + (visible ? 'bi-eye-slash' : 'bi-eye');
        }

        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }

    function bindPasswordToggle(button) {
        if (button.dataset.toggleBound === '1') {
            return;
        }

        var targetId = button.getAttribute('data-password-toggle');
        if (!targetId) {
            return;
        }

        button.dataset.toggleBound = '1';

        var isVisible = false;

        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var current = document.getElementById(targetId);
            if (!current) {
                return;
            }

            isVisible = !isVisible;
            var next = buildInput(isVisible, current);
            current.replaceWith(next);

            if (isVisible) {
                next.focus();
                var length = next.value.length;
                next.setSelectionRange(length, length);
            }

            updateToggleUi(button, isVisible);
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach(bindPasswordToggle);
})();
