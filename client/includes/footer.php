        </main>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div id="globalLoadingOverlay" class="global-loading-overlay" hidden aria-live="polite" aria-busy="false">
    <div class="global-loading-overlay__panel" role="status">
        <div class="spinner-border global-loading-spinner" role="presentation" aria-hidden="true"></div>
        <p class="global-loading-overlay__message" id="globalLoadingMessage">Loading…</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= adminAsset('js/theme.js') ?>"></script>
<script src="<?= adminAsset('js/loading.js') ?>"></script>
<script src="<?= adminAsset('js/app.js') ?>"></script>
<script src="<?= adminAsset('js/notifications.js') ?>"></script>
<script src="<?= adminAsset('js/password-reveal.js') ?>"></script>
<script src="<?= adminAsset('js/password-strength.js') ?>"></script>
<?php if (!empty($pageScripts)): ?>
    <?= $pageScripts ?>
<?php endif; ?>
</body>
</html>
