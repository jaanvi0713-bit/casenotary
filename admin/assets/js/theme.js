/**
 * Case Notary — Theme toggle (light / dark)
 * Persists preference in localStorage across sessions.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'casenotary-theme';
    var root = document.documentElement;

    function getSystemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function getStoredTheme() {
        try {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (stored === 'light' || stored === 'dark') {
                return stored;
            }
        } catch (e) {
            /* ignore */
        }
        return null;
    }

    function getEffectiveTheme() {
        return getStoredTheme() || getSystemTheme();
    }

    function applyTheme(theme) {
        var resolved = theme === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', resolved);
        root.setAttribute('data-bs-theme', resolved);
        updateToggleButtons(resolved);
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: resolved } }));
    }

    function updateToggleButtons(theme) {
        document.querySelectorAll('.theme-toggle').forEach(function (btn) {
            var isDark = theme === 'dark';
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            btn.setAttribute('title', isDark ? 'Light mode' : 'Dark mode');

        });
    }

    function toggleTheme() {
        var current = root.getAttribute('data-theme') || getEffectiveTheme();
        var next = current === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (e) {
            /* ignore */
        }
        applyTheme(next);
    }

    function init() {
        applyTheme(getEffectiveTheme());

        document.querySelectorAll('.theme-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                toggleTheme();
            });
        });

        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (event) {
                if (!getStoredTheme()) {
                    applyTheme(event.matches ? 'dark' : 'light');
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function readCssVar(name, fallback) {
        var value = getComputedStyle(root).getPropertyValue(name).trim();
        return value || fallback;
    }

    function getChartTheme() {
        var isDark = root.getAttribute('data-theme') === 'dark';

        if (!isDark) {
            return {
                tick: '#94a3b8',
                grid: 'rgba(0,24,44,0.06)',
                pointBorder: '#ffffff',
                gradSecondaryStart: 'rgba(0, 24, 44, 0.18)',
                gradSecondaryEnd: 'rgba(0, 24, 44, 0.01)',
                gradPrimaryStart: 'rgba(58, 175, 169, 0.25)',
                gradPrimaryEnd: 'rgba(58, 175, 169, 0.02)',
                tooltipBg: null
            };
        }

        return {
            tick: readCssVar('--chart-tick', '#7a8ea6'),
            grid: readCssVar('--chart-grid', 'rgba(148, 163, 184, 0.12)'),
            pointBorder: readCssVar('--chart-point-border', '#1f2b3d'),
            gradSecondaryStart: readCssVar('--chart-gradient-dark', 'rgba(148, 163, 184, 0.22)'),
            gradSecondaryEnd: readCssVar('--chart-gradient-dark-end', 'rgba(148, 163, 184, 0.02)'),
            gradPrimaryStart: readCssVar('--chart-gradient-primary', 'rgba(58, 175, 169, 0.28)'),
            gradPrimaryEnd: readCssVar('--chart-gradient-primary-end', 'rgba(58, 175, 169, 0.03)'),
            tooltipBg: readCssVar('--surface-raised', '#1f2b3d'),
            revenueLineColor: '#ffffff'
        };
    }

    window.CaseNotaryTheme = {
        get: getEffectiveTheme,
        set: function (theme) {
            try {
                localStorage.setItem(STORAGE_KEY, theme);
            } catch (e) {
                /* ignore */
            }
            applyTheme(theme);
        },
        toggle: toggleTheme,
        getChartTheme: getChartTheme
    };
})();
