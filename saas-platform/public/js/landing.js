(function () {
    'use strict';

    // Register GSAP Plugins
    if (typeof gsap !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);

        // --- Hero Initial Load ---
        const heroTl = gsap.timeline({ defaults: { ease: "power4.out", duration: 1.2 } });
        
        heroTl.to('.hero .reveal-hidden', { 
            opacity: 1, 
            y: 0, 
            stagger: 0.15,
            delay: 0.2
        });

        // --- Global Reveal on Scroll ---
        const revealElements = document.querySelectorAll('.reveal-hidden:not(.hero .reveal-hidden)');
        
        revealElements.forEach((el) => {
            gsap.to(el, {
                scrollTrigger: {
                    trigger: el,
                    start: "top 90%",
                    toggleActions: "play none none none"
                },
                opacity: 1,
                y: 0,
                duration: 1,
                ease: "power3.out"
            });
        });

        // --- Specialized Animations ---
        
        // Floating effect for cards
        gsap.to('[data-gsap="float"]', {
            y: "-=15",
            duration: 2.5,
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut"
        });

        // Progress bar simulation in Business section
        gsap.from('.progress-bar', {
            scrollTrigger: {
                trigger: '.progress',
                start: "top 80%"
            },
            width: "0%",
            duration: 1.5,
            ease: "power2.out"
        });
    }

    // --- Smooth Scrolling ---
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                window.scrollTo({
                    top: target.offsetTop - 100,
                    behavior: 'smooth'
                });
            }
        });
    });

    // --- Theme Toggle ---
    const toggleBtn = document.getElementById('themeToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const root = document.documentElement;
            const current = root.getAttribute('data-bs-theme');
            const next = current === 'light' ? 'dark' : 'light';
            root.setAttribute('data-bs-theme', next);
            localStorage.setItem('tp_theme', next);
        });
    }

})();
