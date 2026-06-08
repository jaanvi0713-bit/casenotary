/**
 * Role access matrix — ensure checkbox clicks are not swallowed by layout parents.
 */
(function () {
    'use strict';

    var form = document.getElementById('settingsRolesForm');
    if (!form) {
        return;
    }

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
})();
