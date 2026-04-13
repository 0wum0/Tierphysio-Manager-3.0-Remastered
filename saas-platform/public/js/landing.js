(function () {
    'use strict';

    const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
    if ('IntersectionObserver' in window) {
        const revealObs = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        revealEls.forEach((el) => revealObs.observe(el));
    } else {
        revealEls.forEach((el) => el.classList.add('visible'));
    }

    const counters = document.querySelectorAll('[data-count]');
    function animateCount(el, target, suffix) {
        const duration = 1700;
        const start = performance.now();
        function frame(now) {
            const progress = Math.min((now - start) / duration, 1);
            el.textContent = Math.floor(target * progress) + suffix;
            if (progress < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    if (counters.length && 'IntersectionObserver' in window) {
        const countObs = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                const target = parseInt(el.dataset.count || '0', 10);
                const suffix = el.dataset.suffix || '+';
                if (target > 0) animateCount(el, target, suffix);
                countObs.unobserve(el);
            });
        }, { threshold: 0.45 });
        counters.forEach((el) => countObs.observe(el));
    }

    document.querySelectorAll('a[href^="#"]').forEach((a) => {
        a.addEventListener('click', (e) => {
            const href = a.getAttribute('href');
            if (!href || href.length <= 1) return;
            const target = document.querySelector(href);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    setTimeout(() => {
        const fill = document.querySelector('.mockup-progress-fill');
        if (fill) fill.style.width = '78%';
    }, 420);

    const toggleBtn = document.getElementById('themeToggle');
    const root = document.documentElement;

    function setTheme(theme) {
        root.setAttribute('data-bs-theme', theme);
        try {
            localStorage.setItem('tp_theme', theme);
        } catch (e) {}
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
            }
        }
    }

    if (toggleBtn) {
        const initial = root.getAttribute('data-bs-theme') || 'dark';
        setTheme(initial);
        toggleBtn.addEventListener('click', () => {
            const current = root.getAttribute('data-bs-theme') === 'light' ? 'light' : 'dark';
            setTheme(current === 'light' ? 'dark' : 'light');
        });
    }

    setTimeout(() => {
        document.querySelectorAll('.anim-fadeup').forEach((el) => {
            el.style.opacity = '1';
            el.style.transform = 'none';
            el.style.animation = 'none';
        });
    }, 1200);
})();
