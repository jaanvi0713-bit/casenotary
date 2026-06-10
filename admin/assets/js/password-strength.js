/**
 * Client-side password strength validation (matches passwordStrengthError in PHP).
 */
(function () {
    'use strict';

    function passwordStrengthError(value) {
        if (!value || value.length < 8) {
            return 'Password must be at least 8 characters.';
        }
        if (!/[A-Z]/.test(value)) {
            return 'Password must contain at least one uppercase letter.';
        }
        if (!/[a-z]/.test(value)) {
            return 'Password must contain at least one lowercase letter.';
        }
        if (!/[0-9]/.test(value)) {
            return 'Password must contain at least one number.';
        }
        return '';
    }

    function validateForm(form) {
        var strengthFields = form.querySelectorAll('[data-password-strength]');

        for (var i = 0; i < strengthFields.length; i++) {
            var field = strengthFields[i];

            if (field.disabled) {
                continue;
            }

            if (field.hasAttribute('data-password-strength-optional') && field.value === '') {
                continue;
            }

            var error = passwordStrengthError(field.value);
            if (error) {
                return { field: field, message: error };
            }
        }

        return null;
    }

    function bindForm(form) {
        if (!form || form.dataset.passwordStrengthBound === '1') {
            return;
        }

        form.dataset.passwordStrengthBound = '1';
        form.addEventListener('submit', function (event) {
            var result = validateForm(form);
            if (!result) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (typeof result.field.reportValidity === 'function') {
                result.field.setCustomValidity(result.message);
                result.field.reportValidity();
                result.field.setCustomValidity('');
            } else {
                window.alert(result.message);
            }

            result.field.focus();
        });
    }

    function bindAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('.js-password-strength-form').forEach(bindForm);
    }

    window.passwordStrengthError = passwordStrengthError;

    bindAll();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindAll();
        });
    }
})();
