/**
 * Role access matrix — checkbox toggles, edit-role modal, live preview.
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
    var editForm = document.getElementById('editRoleForm');
    var slugInput = document.getElementById('editRoleSlug');
    var labelInput = document.getElementById('editRoleLabel');
    var descriptionInput = document.getElementById('editRoleDescription');
    var builtinNote = document.getElementById('editRoleBuiltinNote');
    var activeDescFallback = '';

    function escapeSelector(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function syncRolePreview(slug, label, description) {
        if (!slug) {
            return;
        }

        var safeSlug = escapeSelector(slug);
        var head = document.querySelector('.settings-roles-matrix__role-head[data-role="' + safeSlug + '"]');

        if (head) {
            var nameEl = head.querySelector('.settings-roles-role-card__name');
            if (nameEl) {
                nameEl.textContent = label;
            }

            head.querySelectorAll('[data-edit-role]').forEach(function (button) {
                button.setAttribute('data-role-label', label);
                button.setAttribute('data-role-description', description);
                button.setAttribute('aria-label', 'Edit ' + label + ' role');
            });
        }

        var legend = document.querySelector('.settings-roles-legend-card[data-role="' + safeSlug + '"]');
        if (legend) {
            var title = legend.querySelector('.settings-roles-legend-card__title');
            var avatar = legend.querySelector('.settings-roles-legend-card__avatar');
            var desc = legend.querySelector('.settings-roles-legend-card__desc');
            var fallback = activeDescFallback || legend.getAttribute('data-desc-fallback') || '';

            if (title) {
                title.textContent = label;
            }
            if (avatar) {
                avatar.textContent = (label || '?').charAt(0).toUpperCase();
            }
            if (desc) {
                desc.textContent = description || fallback;
            }
        }
    }

    function showRoleToast(message, type) {
        var container = document.querySelector('.page-content');
        if (!container) {
            return;
        }

        var alert = document.createElement('div');
        alert.className = 'alert alert-' + (type || 'success') + ' alert-dismissible fade show';
        alert.setAttribute('role', 'alert');
        alert.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        container.insertBefore(alert, container.firstChild);

        window.setTimeout(function () {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            } else {
                alert.remove();
            }
        }, 4000);
    }

    function bindPreviewInputs() {
        if (!labelInput || !descriptionInput || !slugInput) {
            return;
        }

        function handlePreview() {
            syncRolePreview(
                slugInput.value,
                labelInput.value.trim(),
                descriptionInput.value.trim()
            );
        }

        labelInput.addEventListener('input', handlePreview);
        descriptionInput.addEventListener('input', handlePreview);
    }

    document.querySelectorAll('[data-edit-role]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!slugInput || !labelInput || !descriptionInput) {
                return;
            }

            slugInput.value = button.getAttribute('data-role-slug') || '';
            labelInput.value = button.getAttribute('data-role-label') || '';
            descriptionInput.value = button.getAttribute('data-role-description') || '';

            var legend = document.querySelector(
                '.settings-roles-legend-card[data-role="' + escapeSelector(slugInput.value) + '"]'
            );
            activeDescFallback = legend ? (legend.getAttribute('data-desc-fallback') || '') : '';

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

    bindPreviewInputs();

    if (editForm) {
        editForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var submitBtn = editForm.querySelector('button[type="submit"]');
            if (submitBtn && window.CaseNotaryLoading) {
                window.CaseNotaryLoading.setButtonLoading(submitBtn, true, 'Saving…');
            } else if (submitBtn) {
                submitBtn.disabled = true;
            }

            var formData = new FormData(editForm);
            formData.append('ajax', '1');

            fetch(editForm.getAttribute('action') || '', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) {
                            throw new Error(payload.message || 'Could not save role.');
                        }
                        return payload;
                    });
                })
                .then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Could not save role.');
                    }

                    var label = payload.label || labelInput.value.trim();
                    var description = payload.description || '';
                    syncRolePreview(payload.slug || slugInput.value, label, description);

                    document.querySelectorAll('[data-edit-role][data-role-slug="' + escapeSelector(payload.slug || slugInput.value) + '"]').forEach(function (button) {
                        button.setAttribute('data-role-label', label);
                        button.setAttribute('data-role-description', description);
                    });

                    editModal.hide();
                    showRoleToast('Role "' + label + '" updated.');
                })
                .catch(function (error) {
                    showRoleToast(error.message || 'Could not save role.', 'danger');
                })
                .finally(function () {
                    if (submitBtn && window.CaseNotaryLoading) {
                        window.CaseNotaryLoading.setButtonLoading(submitBtn, false);
                    } else if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    }
})();
