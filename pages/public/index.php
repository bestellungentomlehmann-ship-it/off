<?php
/**
 * Public landing page with accessible hero section.
 *
 * Accessible features:
 *  - Skip-to-content link for keyboard / screen-reader users
 *  - Background video is decorative: aria-hidden="true", muted; JS calls .play()
 *    only when prefers-reduced-motion is NOT set (handled via JS matchMedia check)
 *  - Video has a <track kind="descriptions"> placeholder so the element is valid
 *  - All interactive elements have visible focus styles
 *  - Color-contrast ratios meet WCAG 2.1 AA
 *  - Landmark roles (<header>, <main>, <footer>) are explicit
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a0f1e">
    <meta name="description" content="IBC – International Business Club. Die führende studentische Unternehmensberatung. Entdecke Projekte, Events und deine Möglichkeiten bei uns.">

    <title>IBC – International Business Club</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?php echo asset('assets/img/flaticon.webp'); ?>">

    <!-- Bootstrap 5 for responsive utilities -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">

    <!-- IBC design tokens & Tailwind utilities -->
    <link rel="stylesheet" href="<?php echo asset('assets/css/theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/tailwind.css'); ?>">

    <style>
        /* ── Reset ─────────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--ibc-font-family);
            background: #0a0f1e;
            color: #f1f5f9;
            overflow-x: hidden;
        }

        /* ── Skip-to-content (accessibility) ──────────────────────────── */
        .skip-link {
            position: absolute;
            top: -100%;
            left: 1rem;
            z-index: 9999;
            padding: 0.75rem 1.25rem;
            background: var(--ibc-green);
            color: #fff;
            font-weight: 700;
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            text-decoration: none;
            transition: top var(--transition-fast);
        }
        .skip-link:focus { top: 0; outline: 3px solid #fff; outline-offset: 2px; }

        /* ── Hero wrapper ──────────────────────────────────────────────── */
        .hero {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow: hidden;
            isolation: isolate;          /* creates new stacking context */
        }

        /* ── Background video ──────────────────────────────────────────── */
        /*
         * DOM structure rationale:
         *  .hero-video-wrapper  – positions the video absolutely behind all content
         *    <video>            – decorative (aria-hidden), covers full viewport via
         *                         object-fit: cover, muted + playsinline for autoplay
         *                         policy compliance, loop for seamless playback
         *    .hero-video-poster – <img> fallback shown while video loads / is paused
         *                         (reduced-motion) or when the browser cannot play
         */
        .hero-video-wrapper {
            position: absolute;
            inset: 0;
            z-index: -2;
            overflow: hidden;
        }

        .hero-video-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            /* Hardware-accelerated compositing layer */
            will-change: transform;
            transform: translateZ(0);
        }

        /* Static poster image – replaces video when JS is off or motion is reduced */
        .hero-video-poster {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            z-index: 0;
            /* Hidden by default; JS reveals it for reduced-motion users */
            display: none;
        }

        /* ── Dark gradient overlay (ensures text contrast ≥ 4.5:1) ───── */
        .hero-overlay {
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                linear-gradient(
                    to bottom,
                    rgba(10, 15, 30, 0.55)  0%,
                    rgba(10, 15, 30, 0.40) 40%,
                    rgba(10, 15, 30, 0.70) 80%,
                    rgba(10, 15, 30, 0.90) 100%
                );
        }

        /* Subtle animated grid texture (purely decorative, pointer-events: none) */
        .hero-grid-texture {
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 56px 56px;
            /* Fade in once page is ready */
            opacity: 0;
            animation: fadeIn 1.2s 0.4s ease forwards;
        }

        /* ── Animated accent orbs (decorative, aria-hidden) ───────────── */
        .hero-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(72px);
            pointer-events: none;
            will-change: transform, opacity;
        }
        .hero-orb--green {
            width: clamp(300px, 45vw, 700px);
            height: clamp(300px, 45vw, 700px);
            background: radial-gradient(circle, rgba(0,166,81,0.25) 0%, transparent 70%);
            top: -15%;
            right: -10%;
            animation: floatOrb 14s ease-in-out infinite alternate;
        }
        .hero-orb--blue {
            width: clamp(250px, 40vw, 600px);
            height: clamp(250px, 40vw, 600px);
            background: radial-gradient(circle, rgba(0,102,179,0.22) 0%, transparent 70%);
            bottom: -10%;
            left: -8%;
            animation: floatOrb 18s ease-in-out infinite alternate-reverse;
        }
        .hero-orb--accent {
            width: clamp(180px, 25vw, 400px);
            height: clamp(180px, 25vw, 400px);
            background: radial-gradient(circle, rgba(118,75,162,0.18) 0%, transparent 70%);
            top: 40%;
            left: 20%;
            animation: floatOrb 22s ease-in-out infinite alternate;
            animation-delay: -6s;
        }

        @keyframes floatOrb {
            0%   { transform: translate(0,    0)    scale(1);    opacity: 0.7; }
            50%  { transform: translate(3%,  -4%)   scale(1.07); opacity: 1;   }
            100% { transform: translate(-2%,  3%)   scale(0.95); opacity: 0.8; }
        }

        /* ── Hero content ──────────────────────────────────────────────── */
        .hero-content {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 820px;
            padding: clamp(1.5rem, 5vw, 3rem) clamp(1rem, 4vw, 2rem);
            /* Stagger children via CSS custom properties */
        }

        /* Entrance animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0);    }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 1rem;
            border: 1px solid rgba(0,166,81,0.4);
            border-radius: 2rem;
            background: rgba(0,166,81,0.12);
            color: #6ee7a0;
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            animation: slideUp 0.7s 0.2s ease both;
        }
        .hero-badge__dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--ibc-green);
            /* Pulse — respects reduced motion via JS */
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1;   transform: scale(1);    }
            50%       { opacity: 0.5; transform: scale(1.4); }
        }

        .hero-headline {
            font-size: clamp(2.25rem, 6vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: #ffffff;
            margin-bottom: 1.25rem;
            animation: slideUp 0.7s 0.35s ease both;
        }
        .hero-headline mark {
            /* Reset browser default yellow highlight */
            background-color: transparent;
            /* Solid colour is the universal baseline (always visible) */
            color: #00a651;
            /* Gradient text for capable browsers */
            background-image: linear-gradient(135deg, #00a651 0%, #33b872 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }
        /* Only apply fill transparency when background-clip: text is supported */
        @supports (-webkit-background-clip: text) or (background-clip: text) {
            .hero-headline mark {
                -webkit-text-fill-color: transparent;
            }
        }

        .hero-subheadline {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            font-weight: 400;
            line-height: 1.65;
            color: rgba(241,245,249,0.8);
            max-width: 620px;
            margin: 0 auto 2.5rem;
            animation: slideUp 0.7s 0.5s ease both;
        }

        /* ── CTA buttons ───────────────────────────────────────────────── */
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            animation: slideUp 0.7s 0.65s ease both;
        }

        .btn-hero-primary,
        .btn-hero-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            transition:
                transform 200ms ease,
                box-shadow 200ms ease,
                background 200ms ease;
            /* Explicit focus-visible ring for keyboard users */
            outline: none;
        }
        .btn-hero-primary:focus-visible,
        .btn-hero-secondary:focus-visible {
            outline: 3px solid var(--ibc-green-light);
            outline-offset: 3px;
        }

        .btn-hero-primary {
            background: var(--ibc-green);
            color: #fff;
            border: 2px solid transparent;
            box-shadow: 0 4px 20px rgba(0,166,81,0.35);
        }
        .btn-hero-primary:hover {
            background: var(--ibc-green-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0,166,81,0.5);
        }
        .btn-hero-primary:active { transform: translateY(0); }

        .btn-hero-secondary {
            background: rgba(255,255,255,0.07);
            color: #f1f5f9;
            border: 1.5px solid rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
        }
        .btn-hero-secondary:hover {
            background: rgba(255,255,255,0.13);
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .btn-hero-secondary:active { transform: translateY(0); }

        /* ── Scroll-indicator ──────────────────────────────────────────── */
        .hero-scroll-indicator {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.375rem;
            color: rgba(241,245,249,0.45);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            animation: fadeIn 1s 1.2s ease both;
            cursor: default;
        }
        .hero-scroll-indicator__mouse {
            width: 24px;
            height: 38px;
            border: 2px solid rgba(241,245,249,0.3);
            border-radius: 12px;
            display: flex;
            justify-content: center;
            padding-top: 6px;
        }
        .hero-scroll-indicator__wheel {
            width: 4px;
            height: 8px;
            background: rgba(241,245,249,0.5);
            border-radius: 2px;
            animation: scrollWheel 1.8s ease-in-out infinite;
        }
        @keyframes scrollWheel {
            0%   { opacity: 1;   transform: translateY(0); }
            80%  { opacity: 0;   transform: translateY(10px); }
            100% { opacity: 0;   transform: translateY(0); }
        }

        /* ── Stats strip ───────────────────────────────────────────────── */
        .hero-stats {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: clamp(1.5rem, 4vw, 3.5rem);
            margin-top: 3.5rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            animation: slideUp 0.7s 0.8s ease both;
        }
        .hero-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
        }
        .hero-stat__value {
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }
        .hero-stat__value span {
            color: var(--ibc-green);
        }
        .hero-stat__label {
            font-size: 0.8125rem;
            color: rgba(241,245,249,0.55);
            font-weight: 500;
            letter-spacing: 0.03em;
            text-align: center;
        }

        /* ── Reduced-motion: disable all decorative animations ─────────── */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ── Mobile tweaks ─────────────────────────────────────────────── */
        @media (max-width: 480px) {
            .hero-actions { flex-direction: column; align-items: stretch; }
            .btn-hero-primary, .btn-hero-secondary { justify-content: center; }
            .hero-stats { gap: 1.25rem; }
        }
    </style>
</head>

<body>

    <!-- ════════════════════════════════════════════════════════════════════
         SKIP LINK – first focusable element on the page (WCAG 2.4.1)
         ════════════════════════════════════════════════════════════════════ -->
    <a class="skip-link" href="#main-content">Zum Hauptinhalt springen</a>

    <!-- ════════════════════════════════════════════════════════════════════
         HEADER / NAV (minimal; extend as needed)
         ════════════════════════════════════════════════════════════════════ -->
    <header role="banner" style="
        position: fixed; top: 0; left: 0; right: 0; z-index: 100;
        display: flex; justify-content: space-between; align-items: center;
        padding: 1rem clamp(1rem, 4vw, 2.5rem);
        background: rgba(10,15,30,0.0);
        transition: background 300ms ease, backdrop-filter 300ms ease;
    " id="site-header">

        <!-- Logo -->
        <a href="<?php echo BASE_URL; ?>" aria-label="IBC – Zur Startseite">
            <img
                src="<?php echo asset('assets/img/ibc_logo_original_navbar.webp'); ?>"
                alt="IBC Logo"
                width="120"
                height="40"
                loading="eager"
                decoding="async"
                style="height: 2.5rem; width: auto;">
        </a>

        <!-- Primary nav -->
        <nav aria-label="Hauptnavigation">
            <a
                href="<?php echo BASE_URL; ?>/pages/auth/login.php"
                class="btn-hero-primary"
                style="padding: 0.5rem 1.25rem; font-size: 0.9rem;">
                <!-- Login icon -->
                <svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                Login
            </a>
        </nav>
    </header>

    <!-- ════════════════════════════════════════════════════════════════════
         HERO SECTION
         ════════════════════════════════════════════════════════════════════ -->
    <main id="main-content">
        <section
            class="hero"
            aria-labelledby="hero-headline"
            aria-describedby="hero-subheadline">

            <!-- ── Background video ──────────────────────────────────────
                 Accessibility notes:
                 • aria-hidden="true"  – video is purely decorative; screen readers
                   must not announce it.
                 • muted              – required by browsers to allow autoplay.
                 • playsinline        – prevents iOS from opening the system player.
                 • loop               – seamless looping for ambient effect.
                 • disablepictureinpicture – prevents PiP overlay distracting users.
                 • preload="none"     – deferred until JS decides to play, so initial
                   page load does not waste bandwidth on users with reduced motion.
                 • poster             – shown while video loads; also the visible
                   fallback for <noscript> / no-video browsers (set to a real image
                   in production).
                 ──────────────────────────────────────────────────────── -->
            <div class="hero-video-wrapper" aria-hidden="true">
                <video
                    id="hero-video"
                    class="hero-bg-video"
                    muted
                    playsinline
                    loop
                    disablepictureinpicture
                    preload="none"
                    poster="<?php echo asset('assets/img/ibc_logo_original.webp'); ?>"
                    aria-hidden="true"
                    tabindex="-1">
                    <!--
                        Add real video sources in production.
                        WebM (VP9) first – best compression; MP4 (H.264) as fallback.
                    -->
                    <source
                        src="<?php echo asset('assets/video/hero-background.webm'); ?>"
                        type="video/webm">
                    <source
                        src="<?php echo asset('assets/video/hero-background.mp4'); ?>"
                        type="video/mp4">
                    <!--
                        <track> element is required for accessible video even when
                        the video is decorative; kind="descriptions" with an empty
                        src satisfies validators without presenting content to AT.
                    -->
                    <track kind="descriptions" src="" srclang="de" label="Keine Audiodeskription – dekoratives Video">
                </video>

                <!-- Static poster fallback: shown via JS when prefers-reduced-motion
                     is active OR when the browser cannot play the video. -->
                <img
                    class="hero-video-poster"
                    id="hero-video-poster"
                    src="<?php echo asset('assets/img/ibc_logo_original.webp'); ?>"
                    alt=""
                    aria-hidden="true"
                    role="presentation"
                    loading="eager"
                    decoding="async">
            </div>

            <!-- Gradient overlay (contrast protection) -->
            <div class="hero-overlay" aria-hidden="true"></div>

            <!-- Decorative grid texture -->
            <div class="hero-grid-texture" aria-hidden="true"></div>

            <!-- Decorative colour orbs -->
            <div class="hero-orb hero-orb--green"  aria-hidden="true"></div>
            <div class="hero-orb hero-orb--blue"   aria-hidden="true"></div>
            <div class="hero-orb hero-orb--accent" aria-hidden="true"></div>

            <!-- ── Hero content ─────────────────────────────────────── -->
            <div class="hero-content">

                <!-- Badge -->
                <div class="hero-badge" aria-hidden="true">
                    <span class="hero-badge__dot"></span>
                    Studentische Unternehmensberatung
                </div>

                <!-- Main heading (h1, id used by aria-labelledby) -->
                <h1 class="hero-headline" id="hero-headline">
                    Gestalte deine Zukunft<br>
                    mit <mark>IBC</mark>
                </h1>

                <!-- Sub-headline (id used by aria-describedby) -->
                <p class="hero-subheadline" id="hero-subheadline">
                    Der International Business Club vereint ambitionierte Studierende,
                    spannende Beratungsprojekte und ein starkes Netzwerk – alles an
                    einem Ort.
                </p>

                <!-- CTA buttons -->
                <div class="hero-actions" role="group" aria-label="Einstieg">
                    <a
                        href="<?php echo BASE_URL; ?>/pages/auth/login.php"
                        class="btn-hero-primary">
                        <!-- Arrow icon -->
                        <svg aria-hidden="true" focusable="false" width="18" height="18"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                        Zum Intranet
                    </a>
                    <a
                        href="<?php echo BASE_URL; ?>/pages/public/alumni_recovery.php"
                        class="btn-hero-secondary">
                        Alumni-Bereich
                    </a>
                </div>

                <!-- Stats strip -->
                <div class="hero-stats" aria-label="IBC in Zahlen">
                    <div class="hero-stat">
                        <span class="hero-stat__value">200<span>+</span></span>
                        <span class="hero-stat__label">Aktive Mitglieder</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat__value">15<span>+</span></span>
                        <span class="hero-stat__label">Jahre Erfahrung</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat__value">50<span>+</span></span>
                        <span class="hero-stat__label">Beratungsprojekte</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat__value">500<span>+</span></span>
                        <span class="hero-stat__label">Alumni-Netzwerk</span>
                    </div>
                </div>
            </div>

            <!-- Scroll indicator (aria-hidden: purely decorative) -->
            <div class="hero-scroll-indicator" aria-hidden="true">
                <div class="hero-scroll-indicator__mouse">
                    <div class="hero-scroll-indicator__wheel"></div>
                </div>
                <span>Scroll</span>
            </div>

        </section><!-- /hero -->
    </main><!-- /main-content -->

    <!-- ════════════════════════════════════════════════════════════════════
         FOOTER
         ════════════════════════════════════════════════════════════════════ -->
    <footer role="contentinfo" style="
        background: rgba(10,15,30,0.95);
        border-top: 1px solid rgba(255,255,255,0.06);
        padding: 1.5rem clamp(1rem,4vw,2.5rem);
        text-align: center;
        color: rgba(241,245,249,0.4);
        font-size: 0.8125rem;">
        &copy; <?php echo date('Y'); ?> IBC – International Business Club. Alle Rechte vorbehalten.
    </footer>

    <!-- ════════════════════════════════════════════════════════════════════
         SCRIPTS
         ════════════════════════════════════════════════════════════════════ -->
    <script>
    (function () {
        'use strict';

        /* ── Reduced-motion check ─────────────────────────────────────── *
         * If the OS/browser reports prefers-reduced-motion: reduce, we:    *
         *  1. Hide the <video> element (already has no src until played)    *
         *  2. Show the static poster <img> instead                          *
         *  3. Disable the pulsing dot in the badge                          *
         * ──────────────────────────────────────────────────────────────── */
        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var video  = document.getElementById('hero-video');
        var poster = document.getElementById('hero-video-poster');

        if (prefersReducedMotion) {
            // Deactivate video; show poster instead
            if (video)  { video.style.display  = 'none'; }
            if (poster) { poster.style.display = 'block'; }

            // Stop badge dot pulsing
            var dot = document.querySelector('.hero-badge__dot');
            if (dot) { dot.style.animation = 'none'; }
        } else {
            // Attempt to play the video; if sources are missing / unsupported,
            // fall back gracefully to the poster image
            if (video) {
                video.play().catch(function () {
                    video.style.display  = 'none';
                    if (poster) { poster.style.display = 'block'; }
                });
            }
        }

        /* ── Navbar scroll-glass effect ──────────────────────────────── */
        var header = document.getElementById('site-header');
        var ticking = false;

        function updateHeader() {
            if (!header) return;
            if (window.scrollY > 40) {
                header.style.background       = 'rgba(10,15,30,0.85)';
                header.style.backdropFilter   = 'blur(16px)';
                header.style.webkitBackdropFilter = 'blur(16px)';
                header.style.boxShadow        = '0 1px 0 rgba(255,255,255,0.06)';
            } else {
                header.style.background       = 'rgba(10,15,30,0.0)';
                header.style.backdropFilter   = 'none';
                header.style.webkitBackdropFilter = 'none';
                header.style.boxShadow        = 'none';
            }
            ticking = false;
        }

        if (!prefersReducedMotion) {
            window.addEventListener('scroll', function () {
                if (!ticking) {
                    requestAnimationFrame(updateHeader);
                    ticking = true;
                }
            }, { passive: true });
        }

    }());
    </script>

</body>
</html>
