/* ═══════════════════════════════════════════════════════════════
   TheraPano Landing Page – Scripts
   ════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    // ── Scroll Reveal ─────────────────────────────────────────────
    const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
    if ('IntersectionObserver' in window) {
        const revealObs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    revealObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.12 });
        revealEls.forEach(el => revealObs.observe(el));
    } else {
        revealEls.forEach(el => el.classList.add('visible'));
    }

    // ── Zähler Animation ──────────────────────────────────────────
    function animateCount(el, target, suffix) {
        let start = 0;
        const duration = 1800;
        const step = 16;
        const increment = target / (duration / step);
        const timer = setInterval(() => {
            start += increment;
            if (start >= target) {
                start = target;
                clearInterval(timer);
            }
            el.textContent = Math.floor(start) + suffix;
        }, step);
    }

    if ('IntersectionObserver' in window) {
        const countObs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    const el = e.target;
                    const target = parseInt(el.dataset.count);
                    const suffix = el.dataset.suffix || '+';
                    if (target) animateCount(el, target, suffix);
                    countObs.unobserve(el);
                }
            });
        }, { threshold: 0.5 });
        document.querySelectorAll('[data-count]').forEach(el => countObs.observe(el));
    }

    // ── Smooth Scroll ─────────────────────────────────────────────
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ── Mockup Progress Animation ─────────────────────────────────
    setTimeout(() => {
        const fill = document.querySelector('.mockup-progress-fill');
        if (fill) fill.style.width = '72%';
    }, 500);

    // ── Animations Fallback: force visible after 1.2s ─────────────
    setTimeout(() => {
        document.querySelectorAll('.anim-fadeup').forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'none';
            el.style.animation = 'none';
        });
    }, 1200);

})();
