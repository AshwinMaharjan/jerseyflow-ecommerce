/**
 * JerseyFlow — about.js
 * Handles: scroll-reveal animations, stat counter animation
 */

'use strict';

/* ================================================================
   SCROLL REVEAL
================================================================ */

/**
 * Marks elements with the `.reveal` class as `.visible`
 * when they enter the viewport.
 */
function initScrollReveal() {
    const revealEls = document.querySelectorAll('.reveal');

    if (!revealEls.length) return;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target); // Fire once
                }
            });
        },
        {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px',
        }
    );

    revealEls.forEach((el) => observer.observe(el));
}

/* ================================================================
   STAT COUNTERS
================================================================ */

/**
 * Animates a number from 0 to `target` over `duration` ms.
 * @param {HTMLElement} el      - The element displaying the number
 * @param {number}      target  - The final value
 * @param {number}      duration - Animation duration in ms
 */
function animateCounter(el, target, duration) {
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed  = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Ease out cubic
        const eased = 1 - Math.pow(1 - progress, 3);

        el.textContent = Math.floor(eased * target);

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            el.textContent = target; // Ensure exact final value
        }
    }

    requestAnimationFrame(update);
}

/**
 * Triggers counter animations when the stats section
 * enters the viewport. Fires only once per counter.
 */
function initStatCounters() {
    const counterEls = document.querySelectorAll('.stat-item__number[data-target]');

    if (!counterEls.length) return;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const target   = parseInt(entry.target.dataset.target, 10);
                    const duration = 1800;
                    animateCounter(entry.target, target, duration);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.4 }
    );

    counterEls.forEach((el) => observer.observe(el));
}

/* ================================================================
   ADD REVEAL CLASS TO SECTIONS
================================================================ */

/**
 * Programmatically applies `.reveal` to key elements so the
 * PHP template stays clean of utility classes.
 */
function applyRevealClasses() {
    const targets = [
        '.who-we-are__image-wrap',
        '.who-we-are__text',
        '.mission__header',
        '.mission__pillar',
        '.feature-card',
        '.stat-item',
        '.vision__text',
        '.vision__milestones',
        '.milestone',
        '.cta__heading',
        '.cta__sub',
        '.cta__button',
    ];

    targets.forEach((selector) => {
        document.querySelectorAll(selector).forEach((el, i) => {
            el.classList.add('reveal');
            // Stagger siblings slightly
            el.style.transitionDelay = `${i * 0.07}s`;
        });
    });
}

/* ================================================================
   HERO PARALLAX (subtle)
================================================================ */

function initHeroParallax() {
    const heroContent = document.querySelector('.hero__content');
    if (!heroContent || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    let ticking = false;

    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                const scrollY = window.scrollY;
                heroContent.style.transform = `translateY(${scrollY * 0.2}px)`;
                heroContent.style.opacity   = `${1 - scrollY / 600}`;
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
}

/* ================================================================
   INIT
================================================================ */

document.addEventListener('DOMContentLoaded', () => {
    applyRevealClasses();
    initScrollReveal();
    initStatCounters();
    initHeroParallax();
});