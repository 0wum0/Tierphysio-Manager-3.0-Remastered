(function () {
    'use strict';

    // Register GSAP Plugins
    if (typeof gsap !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);

        // --- Hero Animations ---
        const heroTl = gsap.timeline({ defaults: { ease: "power4.out", duration: 1.2 } });

        heroTl.from('[data-gsap="fade-in"]', { opacity: 0, y: -20, stagger: 0.1 })
              .from('[data-gsap="skew-up"]', { opacity: 0, y: 100, skewY: 10 }, "-=0.8")
              .from('[data-gsap="fade-up"]', { opacity: 0, y: 40, stagger: 0.2 }, "-=1")
              .from('[data-gsap="float"]', { opacity: 0, scale: 0.9, x: 50 }, "-=1.2");

        // Floating Animation for Panel
        gsap.to('[data-gsap="float"]', {
            y: "-=20",
            duration: 3,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut"
        });

        // --- Scroll Reveals ---
        const reveals = document.querySelectorAll('[data-gsap="reveal"]');
        reveals.forEach((el) => {
            gsap.from(el, {
                scrollTrigger: {
                    trigger: el,
                    start: "top 85%",
                    toggleActions: "play none none none"
                },
                opacity: 0,
                y: 50,
                duration: 1,
                ease: "power3.out"
            });
        });

        // Staggered Bento Reveal
        gsap.from('.bento', {
            scrollTrigger: {
                trigger: '.bento-grid',
                start: "top 80%"
            },
            opacity: 0,
            y: 30,
            stagger: 0.15,
            duration: 0.8,
            ease: "back.out(1.7)"
        });
    }

    // --- Counters ---
    const counters = document.querySelectorAll('[data-count]');
    function animateCount(el, target) {
        let obj = { val: 0 };
        gsap.to(obj, {
            val: target,
            duration: 2,
            ease: "power2.out",
            onUpdate: function() {
                el.innerText = Math.round(obj.val);
            },
            scrollTrigger: {
                trigger: el,
                start: "top 90%"
            }
        });
    }

    counters.forEach(counter => {
        const target = parseInt(counter.dataset.count);
        animateCount(counter, target);
    });

    // --- Smooth Scrolling ---
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                window.scrollTo({
                    top: target.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });

    // --- Theme Toggle Integration (Minimal, mainly for persistence) ---
    const toggleBtn = document.getElementById('themeToggle');
    const root = document.documentElement;

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const current = root.getAttribute('data-bs-theme');
            const next = current === 'light' ? 'dark' : 'light';
            root.setAttribute('data-bs-theme', next);
            localStorage.setItem('tp_theme', next);
        });
    // --- Special: Mockup Progress ---
    setTimeout(() => {
        const fill = document.querySelector('.mockup-progress-fill');
        if (fill) fill.style.width = '78%';
    }, 1000);

})();
