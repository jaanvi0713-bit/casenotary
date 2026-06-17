(function () {
    'use strict';

    function showCopyFeedback(button) {
        const label = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
        button.disabled = true;
        window.setTimeout(function () {
            button.innerHTML = label;
            button.disabled = false;
        }, 1500);
    }

    document.querySelectorAll('.message-copy-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const text = button.getAttribute('data-copy-text') || '';
            if (!text) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    showCopyFeedback(button);
                }).catch(function () {
                    window.prompt('Copy message:', text);
                });
                return;
            }

            window.prompt('Copy message:', text);
            showCopyFeedback(button);
        });
    });

    document.querySelectorAll('.message-edit-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const item = button.closest('.message-thread-item');
            if (!item) {
                return;
            }

            const display = item.querySelector('.message-thread-bubble--display');
            const form = item.querySelector('.message-edit-form');
            if (!display || !form) {
                return;
            }

            display.classList.add('d-none');
            form.classList.remove('d-none');
            const textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.focus();
            }
        });
    });

    document.querySelectorAll('.message-edit-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            const item = button.closest('.message-thread-item');
            if (!item) {
                return;
            }

            const display = item.querySelector('.message-thread-bubble--display');
            const form = item.querySelector('.message-edit-form');
            if (!display || !form) {
                return;
            }

            form.classList.add('d-none');
            display.classList.remove('d-none');
        });
    });
})();
