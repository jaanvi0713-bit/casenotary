/**
 * Role access matrix — checkbox toggles and edit-role modal.
 */
(function () {
    'use strict';

    var form = document.getElementById('settingsRolesForm');

    if (form) {
        function syncCellState(cell) {
            var checkbox = cell.querySelector('.settings-roles-matrix__checkbox');
            if (!checkbox) {
                return;
            }
            cell.classList.toggle('is-on', checkbox.checked);
        }

        form.querySelectorAll('.settings-roles-matrix__cell').forEach(function (cell) {
            syncCellState(cell);

            cell.addEventListener('click', function (event) {
                if (event.target.closest('.settings-roles-matrix__checkbox, .settings-roles-toggle')) {
                    return;
                }
                var checkbox = cell.querySelector('.settings-roles-matrix__checkbox');
                if (!checkbox) {
                    return;
                }
                checkbox.checked = !checkbox.checked;
                syncCellState(cell);
            });
        });

        form.querySelectorAll('.settings-roles-matrix__checkbox').forEach(function (input) {
            input.addEventListener('change', function () {
                var cell = input.closest('.settings-roles-matrix__cell');
                if (cell) {
                    syncCellState(cell);
                }
            });
        });
    }

    var editModalEl = document.getElementById('editRoleModal');
    if (!editModalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var editModal = bootstrap.Modal.getOrCreateInstance(editModalEl);
    var slugInput = document.getElementById('editRoleSlug');
    var labelInput = document.getElementById('editRoleLabel');
    var descriptionInput = document.getElementById('editRoleDescription');
    var builtinNote = document.getElementById('editRoleBuiltinNote');

    document.querySelectorAll('[data-edit-role]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!slugInput || !labelInput || !descriptionInput) {
                return;
            }

            slugInput.value = button.getAttribute('data-role-slug') || '';
            labelInput.value = button.getAttribute('data-role-label') || '';
            descriptionInput.value = button.getAttribute('data-role-description') || '';

            if (builtinNote) {
                var isBuiltin = button.getAttribute('data-role-builtin') === '1';
                builtinNote.classList.toggle('d-none', !isBuiltin);
            }

            editModal.show();
            window.setTimeout(function () {
                labelInput.focus();
                labelInput.select();
            }, 150);
        });
    });
})();
