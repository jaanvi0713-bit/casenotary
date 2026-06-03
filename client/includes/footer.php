        </main>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= adminAsset('js/app.js') ?>"></script>
<script src="<?= adminAsset('js/password-reveal.js') ?>"></script>
<?php if (!empty($pageScripts)): ?>
    <?= $pageScripts ?>
<?php endif; ?>
</body>
</html>
