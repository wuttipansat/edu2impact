// Fades in the cards of the two bands under the world map as they scroll into view (network-international.php).
// Without this file, or with reduced motion, every card is simply there.
(() => {
  'use strict';
  const band = document.querySelector('.netinfo');
  if (!band || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const cards = band.querySelectorAll('[data-reveal]');
  if (!cards.length) return;
  band.classList.add('is-armed');
  const seen = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const card = entry.target;
      const siblings = [...card.parentElement.children];
      card.style.setProperty('transition-delay', Math.min(siblings.indexOf(card), 5) * 70 + 'ms');
      card.classList.add('is-in');
      seen.unobserve(card);
    });
  }, { threshold: 0.2 });
  cards.forEach((card) => seen.observe(card));
})();
