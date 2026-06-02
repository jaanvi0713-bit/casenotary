(function () {
    'use strict';

    function initPortalFileUploads(root) {
        const scope = root || document;
        scope.querySelectorAll('[data-portal-upload]').forEach(function (wrap) {
            if (wrap.dataset.portalUploadInit === '1') {
                return;
            }
            wrap.dataset.portalUploadInit = '1';

            const input = wrap.querySelector('input[type="file"]');
            const preview = wrap.querySelector('[data-upload-preview]');
            const maxFiles = parseInt(wrap.dataset.maxFiles || '10', 10);

            if (!input || !preview) {
                return;
            }

            input.addEventListener('change', function () {
                preview.innerHTML = '';
                const files = Array.from(input.files || []).slice(0, maxFiles);

                if (input.files.length > maxFiles) {
                    const note = document.createElement('p');
                    note.className = 'portal-upload-note text-warning small mb-2';
                    note.textContent = 'Only the first ' + maxFiles + ' files will be sent.';
                    preview.appendChild(note);
                }

                if (files.length === 0) {
                    return;
                }

                const list = document.createElement('ul');
                list.className = 'portal-upload-list';

                files.forEach(function (file) {
                    const item = document.createElement('li');
                    item.className = 'portal-upload-item';

                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.className = 'portal-upload-thumb';
                        img.alt = file.name;
                        img.src = URL.createObjectURL(file);
                        item.appendChild(img);
                    } else {
                        const icon = document.createElement('span');
                        icon.className = 'portal-upload-file-icon';
                        icon.innerHTML = '<i class="bi bi-file-earmark"></i>';
                        item.appendChild(icon);
                    }

                    const meta = document.createElement('span');
                    meta.className = 'portal-upload-name';
                    meta.textContent = file.name;
                    item.appendChild(meta);

                    list.appendChild(item);
                });

                preview.appendChild(list);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPortalFileUploads(document);
    });

    window.initPortalFileUploads = initPortalFileUploads;
})();
