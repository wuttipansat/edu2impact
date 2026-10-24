(() => {
  // Header menu. Desktop: dropdown groups opened by click / Enter / Space (never hover alone), one at a time,
  // closed by Esc (focus returns to the group), a click outside, or Tab leaving the group.
  // Up to 1100px: the nav is a full-screen accordion behind the menu button; the page behind it does not scroll.
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.getElementById('main-nav');
  if (!toggle || !nav) return;
  const header = document.querySelector('.header');
  const label = toggle.querySelector('.menu-toggle__label');
  const groups = [...nav.querySelectorAll('details')];
  const mobile = window.matchMedia('(max-width: 1100px)');
  const summaryOf = group => group.querySelector('summary');
  const linksOf = group => [...group.querySelectorAll('.nav-submenu a')];
  function mark(group) { summaryOf(group).setAttribute('aria-expanded', String(group.open)); }
  function closeGroups() { groups.forEach(group => { group.open = false; mark(group); }); }
  function setMenu(open) {
    nav.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    if (label) label.textContent = open ? 'ปิด' : 'เมนู';
    if (!open) closeGroups();
    document.documentElement.classList.toggle('menu-open', open && mobile.matches);
  }
  function sync() {
    toggle.hidden = !mobile.matches;
    setMenu(!mobile.matches);
  }
  toggle.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
  groups.forEach(group => {
    mark(group);
    group.addEventListener('toggle', () => {
      mark(group);
      if (group.open) groups.forEach(other => { if (other !== group) { other.open = false; mark(other); } });
    });
    group.addEventListener('focusout', event => {
      if (!mobile.matches && group.open && event.relatedTarget && !group.contains(event.relatedTarget)) { group.open = false; }
    });
    group.addEventListener('keydown', event => {
      const links = linksOf(group);
      const summary = summaryOf(group);
      const at = links.indexOf(document.activeElement);
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!group.open) group.open = true;
        (links[at + 1] || links[0])?.focus();
      } else if (event.key === 'ArrowUp' && at >= 0) {
        event.preventDefault();
        (at === 0 ? summary : links[at - 1]).focus();
      } else if (event.key === 'Home' && at >= 0) { event.preventDefault(); links[0].focus(); }
      else if (event.key === 'End' && at >= 0) { event.preventDefault(); links[links.length - 1].focus(); }
    });
  });
  document.addEventListener('click', event => { if (!nav.contains(event.target)) closeGroups(); });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const open = groups.find(group => group.open);
    if (open) { open.open = false; summaryOf(open).focus(); }
    else if (mobile.matches && !nav.hidden) { setMenu(false); toggle.focus(); }
  });
  nav.addEventListener('click', event => { if (mobile.matches && event.target.closest('a')) setMenu(false); });
  mobile.addEventListener('change', sync);
  sync();

  // Small screens: the sticky header slides away while reading down the page and returns on the first
  // scroll up. It stays put while the menu is open or focus is inside it. Layout never moves.
  if (header) {
    const small = window.matchMedia('(max-width: 760px)');
    let last = window.scrollY;
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      const busy = toggle.getAttribute('aria-expanded') === 'true' || header.contains(document.activeElement);
      const hide = small.matches && !busy && y > last && y > 120;
      header.classList.toggle('header--hidden', hide);
      header.classList.toggle('header--scrolled', y > 24);
      last = y;
    }, { passive: true });
    header.addEventListener('focusin', () => header.classList.remove('header--hidden'));
    header.classList.toggle('header--scrolled', window.scrollY > 24);
  }
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
  // Home hero slider: previous / next buttons and arrow keys. Slides change only when the visitor
  // asks for it; nothing moves on its own, so no pause control is needed (WCAG 2.2.2).
  // A status line tells screen readers which slide is showing.
  const slider = document.querySelector('[data-slider]');
  if (!slider) return;
  const slides = [...slider.querySelectorAll('[data-slide]')];
  const previous = slider.querySelector('[data-slider-prev]');
  const next = slider.querySelector('[data-slider-next]');
  const status = slider.querySelector('[data-slider-status]');
  let current = 0;

  function show(index, announce) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', String(!active));
    });
    if (status && announce) status.textContent = `สไลด์ ${current + 1} จาก ${slides.length}`;
  }

  previous?.addEventListener('click', () => show(current - 1, true));
  next?.addEventListener('click', () => show(current + 1, true));
  slider.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') show(current - 1, true);
    if (event.key === 'ArrowRight') show(current + 1, true);
  });
  show(0, false);
})();

(() => {
  // Site search: header icon opens the dialog; Ctrl/⌘ K and "/" too. On search.php they focus the page's own field.
  // Without <dialog> support the icon stays a plain link to search.php.
  const trigger = document.querySelector('.header-search');
  const dialog = document.getElementById('site-search');
  const pageInput = document.getElementById('search-page-q');
  const canDialog = !!dialog && typeof dialog.showModal === 'function';
  const dialogInput = dialog && dialog.querySelector('input[name="q"]');
  function open() {
    if (pageInput) { pageInput.focus(); pageInput.select(); return true; }
    if (!canDialog || !dialogInput) return false;
    if (!dialog.open) { dialog.showModal(); document.documentElement.classList.add('search-open'); }
    dialogInput.focus(); dialogInput.select();
    return true;
  }
  trigger?.addEventListener('click', event => { if (open()) event.preventDefault(); });
  if (canDialog) {
    dialog.addEventListener('close', () => { document.documentElement.classList.remove('search-open'); trigger?.focus(); });
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.querySelector('[data-search-close]')?.addEventListener('click', () => dialog.close());
    // Keep Tab inside the dialog (some browsers let it reach the browser UI).
    dialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const items = [...dialog.querySelectorAll('input, button, a[href]')].filter(item => !item.disabled && item.offsetParent !== null);
      if (!items.length) return;
      const first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  }
  document.addEventListener('keydown', event => {
    const key = event.key.toLowerCase();
    if ((event.ctrlKey || event.metaKey) && key === 'k') { if (open()) event.preventDefault(); return; }
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
    const target = event.target;
    if (target instanceof Element && (target.closest('input, textarea, select') || target.isContentEditable)) return;
    if (open()) event.preventDefault();
  });
})();

(() => {
  // Floating "back to top" button: appears after about one screen of scrolling. Without JavaScript it stays
  // visible and works as a plain #top link. Focus moves to the start of the page content afterwards.
  const button = document.querySelector('.to-top');
  if (!button) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  button.setAttribute('data-js', '');
  function update() { button.classList.toggle('is-visible', window.scrollY > window.innerHeight * 0.9); }
  window.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  update();
  button.addEventListener('click', event => {
    event.preventDefault();
    window.scrollTo({ top: 0, behavior: reduced.matches ? 'auto' : 'smooth' });
    const main = document.getElementById('main');
    if (main) { main.setAttribute('tabindex', '-1'); main.focus({ preventScroll: true }); }
  });
})();

(() => {
  // Share buttons without "site_url": complete the links with the address of the page being read
  // (the page itself plus its ?id=, nothing else) and reveal them. Plain links; no third-party script.
  const boxes = document.querySelectorAll('[data-share-client]');
  if (!boxes.length || !/^https?:$/.test(window.location.protocol)) return;
  const here = new URL(window.location.href);
  const id = here.searchParams.get('id');
  const page = here.origin + here.pathname + (id ? '?id=' + encodeURIComponent(id) : '');
  boxes.forEach(box => {
    box.querySelectorAll('a[data-share]').forEach(link => {
      link.href = link.dataset.share === 'facebook'
        ? 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(page)
        : 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(page) + '&text=' + encodeURIComponent(link.dataset.title || '');
    });
    box.hidden = false;
  });
})();
