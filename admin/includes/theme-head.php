<?php
/**
 * Early theme bootstrap — prevents flash of wrong theme on load.
 * Include inside <head> before stylesheets.
 */
?>
<script>
(function () {
    try {
        var key = 'casenotary-theme';
        var stored = localStorage.getItem(key);
        var theme = stored === 'dark' || stored === 'light'
            ? stored
            : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.setAttribute('data-bs-theme', 'light');
    }
})();
</script>
