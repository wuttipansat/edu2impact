(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.getElementById('main-nav');
  if (!toggle || !nav) return;
  const groups = [...nav.querySelectorAll('details')];
  const mobile = window.matchMedia('(max-width: 1100px)');
  function closeGroups() { groups.forEach(group => { group.open = false; }); }
  function sync() {
    toggle.hidden = !mobile.matches;
    nav.hidden = mobile.matches;
    toggle.setAttribute('aria-expanded', 'false');
    closeGroups();
  }
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    nav.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    if (!open) closeGroups();
  });
  groups.forEach(group => group.addEventListener('toggle', () => {
    if (group.open) groups.forEach(other => { if (other !== group) other.open = false; });
  }));
  document.addEventListener('click', event => { if (!nav.contains(event.target)) closeGroups(); });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const open = groups.find(group => group.open);
    if (open) { open.open = false; open.querySelector('summary').focus(); }
    else if (mobile.matches && !nav.hidden) {
      nav.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  });
  nav.addEventListener('click', event => { if (mobile.matches && event.target.closest('a')) sync(); });
  mobile.addEventListener('change', sync);
  sync();
})();

(() => {
  document.querySelectorAll('[data-auto-carousel]').forEach(carousel => {
    const track = carousel.querySelector('.carousel-track');
    const cards = [...carousel.querySelectorAll('.carousel-card')];
    const prev = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');
    if (!track || cards.length < 2) return;
    let current = 0; let timer; let paused = false;
    const visible = () => window.innerWidth < 760 ? 1 : 2;
    const max = () => Math.max(0, cards.length - visible());
    function render(index) { current = index > max() ? 0 : index < 0 ? max() : index; const gap = parseFloat(getComputedStyle(track).gap) || 18; track.style.transform = `translate3d(-${current * (cards[0].getBoundingClientRect().width + gap)}px,0,0)`; }
    function schedule() { clearInterval(timer); if (!paused) timer = setInterval(() => render(current + 1), 4200); }
    prev?.addEventListener('click', () => { render(current - 1); schedule(); }); next?.addEventListener('click', () => { render(current + 1); schedule(); });
    carousel.addEventListener('mouseenter', () => { paused = true; schedule(); }); carousel.addEventListener('mouseleave', () => { paused = false; schedule(); });
    carousel.addEventListener('focusin', () => { paused = true; schedule(); }); carousel.addEventListener('focusout', e => { if (!carousel.contains(e.relatedTarget)) { paused = false; schedule(); } });
    window.addEventListener('resize', () => render(current)); render(0); schedule();
  });
})();

(() => {
  const input = document.querySelector('.compact-search input[type="search"]');
  if (!input) return;
  document.addEventListener('keydown', event => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); input.focus(); input.select(); } });
})();

(() => {
  const slider = document.querySelector('[data-slider]');
  if (!slider) return;
  const slides = [...slider.querySelectorAll('[data-slide]')];
  const dots = [...slider.querySelectorAll('[data-slider-dot]')];
  const previous = slider.querySelector('[data-slider-prev]');
  const next = slider.querySelector('[data-slider-next]');
  let current = 0;
  let timer;
  let paused = false;

  function show(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', String(!active));
    });
    dots.forEach((dot, i) => {
      const active = i === current;
      dot.classList.toggle('is-active', active);
      dot.setAttribute('aria-selected', String(active));
    });
  }

  function schedule() {
    window.clearInterval(timer);
    if (!paused) timer = window.setInterval(() => show(current + 1), 7000);
  }

  previous?.addEventListener('click', () => { show(current - 1); schedule(); });
  next?.addEventListener('click', () => { show(current + 1); schedule(); });
  dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i); schedule(); }));
  slider.addEventListener('mouseenter', () => { paused = true; schedule(); });
  slider.addEventListener('mouseleave', () => { paused = false; schedule(); });
  slider.addEventListener('focusin', () => { paused = true; schedule(); });
  slider.addEventListener('focusout', event => { if (!slider.contains(event.relatedTarget)) { paused = false; schedule(); } });
  slider.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') { show(current - 1); schedule(); }
    if (event.key === 'ArrowRight') { show(current + 1); schedule(); }
  });
  show(0);
  schedule();
})();
