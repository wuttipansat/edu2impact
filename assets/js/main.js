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
