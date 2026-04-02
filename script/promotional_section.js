/**
 * JerseyFlow — Featured Products Section JS
 * File: featured_section.js
 *
 * Scroll-triggered staggered card reveal using IntersectionObserver.
 * Cards animate in as the section enters the viewport — improves
 * perceived performance since this section sits lower on the page.
 */

(function () {
  'use strict';

  const cards = document.querySelectorAll('.jf-feat-card');
  if (!cards.length) return;

  // If IntersectionObserver is not supported, just show all cards
  if (!('IntersectionObserver' in window)) {
    cards.forEach(card => card.style.opacity = '1');
    return;
  }

  // Initially pause the CSS animation so it only fires when visible
  cards.forEach(card => {
    card.style.animationPlayState = 'paused';
    card.style.opacity = '0';
  });

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const card = entry.target;

        // Let the CSS keyframe animation play
        card.style.opacity = '';
        card.style.animationPlayState = 'running';

        // Stop observing once revealed
        observer.unobserve(card);
      }
    });
  }, {
    threshold: 0.12,      // trigger when 12% of the card is visible
    rootMargin: '0px 0px -40px 0px'
  });

  cards.forEach(card => observer.observe(card));

})();