/**
 * navbar-scroll.js
 *
 * Performant scroll-tracking for the site navigation.
 *
 * Features:
 *  - Uses requestAnimationFrame so scroll handling never blocks the main thread.
 *  - Adds/removes the `.scrolled` class on the mobile menu button once the user
 *    has scrolled past SCROLL_THRESHOLD (50 px).
 *  - Guarantees that every `overflow: hidden` attribute applied to <body> and
 *    <html> by the mobile-menu overlay is fully cleaned up when the menu closes,
 *    preventing the page from staying locked in a non-scrollable state.
 */
(function () {
    'use strict';

    var SCROLL_THRESHOLD = 50;

    var ticking = false;
    var lastScrollY = 0;
    var navbarBtn = null;
    var sidebarEl = null;

    /* ------------------------------------------------------------------ */
    /* Scroll detection – rAF-based to avoid layout thrashing              */
    /* ------------------------------------------------------------------ */

    function onScroll() {
        lastScrollY = window.scrollY;
        if (!ticking) {
            requestAnimationFrame(updateScrolledState);
            ticking = true;
        }
    }

    function updateScrolledState() {
        if (navbarBtn) {
            if (lastScrollY >= SCROLL_THRESHOLD) {
                navbarBtn.classList.add('scrolled');
            } else {
                navbarBtn.classList.remove('scrolled');
            }
        }
        ticking = false;
    }

    /* ------------------------------------------------------------------ */
    /* Overflow cleanup – removes ALL overflow: hidden locks from the DOM  */
    /* ------------------------------------------------------------------ */

    /**
     * Removes every overflow / position / top / width inline style that the
     * mobile sidebar overlay sets on <body> and <html>, and removes the
     * `sidebar-open` class that the CSS rule targets.
     *
     * This function is idempotent – clearing an already-empty style property or
     * removing a class that is not present are both safe no-ops, so it may be
     * called multiple times in quick succession without adverse effects (e.g.
     * when both the Escape-key handler here and the one in main_layout.php fire
     * for the same event).
     *
     * Call this whenever a mobile menu or overlay is closed so that scrolling
     * is always restored regardless of *how* the menu was dismissed.
     */
    function ensureScrollUnlocked() {
        var body = document.body;
        var html = document.documentElement;

        // Remove the class-based lock (CSS: body.sidebar-open { overflow: hidden; position: fixed; })
        body.classList.remove('sidebar-open');

        // Clear any inline styles that may have been applied directly
        body.style.overflow = '';
        body.style.position = '';
        body.style.top = '';
        body.style.width = '';

        // Also clear from <html> in case any library targets the root element
        html.style.overflow = '';
        html.style.position = '';
        html.style.top = '';
        html.style.width = '';
    }

    /* ------------------------------------------------------------------ */
    /* Initialisation                                                       */
    /* ------------------------------------------------------------------ */

    function init() {
        navbarBtn = document.getElementById('mobile-menu-btn');
        sidebarEl  = document.getElementById('sidebar');

        // Run once immediately so state is correct on page load
        lastScrollY = window.scrollY;
        updateScrolledState();

        // Passive listener keeps scroll performance optimal
        window.addEventListener('scroll', onScroll, { passive: true });

        // Attach overflow cleanup to every mechanism that can close the sidebar
        var overlay = document.getElementById('sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', ensureScrollUnlocked);
        }

        // Escape key – fires *after* the existing keydown handler in main_layout.php;
        // ensureScrollUnlocked() is idempotent so duplicate calls are safe.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                ensureScrollUnlocked();
            }
        });

        // Clean up any leftover lock if the page becomes visible again
        // (e.g. user navigates back with the sidebar still open)
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                if (sidebarEl && !sidebarEl.classList.contains('open')) {
                    ensureScrollUnlocked();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose utility for inline scripts or other modules that need to unlock scroll
    window.navbarScrollUtils = {
        ensureScrollUnlocked: ensureScrollUnlocked
    };

}());
