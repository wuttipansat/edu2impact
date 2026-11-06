// Interactive maps on network-international.php (world) and network-community.php (Thailand).
// The page works without this file: areas with records are already coloured by the server and the full list is below the
// map. This script adds the tooltip, the detail panel, zoom/pan, region buttons and the #CODE links (search results use them).
// The CSP forbids inline styles written as attributes, so everything visual is a class or a CSSOM property (el.style.x).
(() => {
  'use strict';
  const root = document.querySelector('[data-netmap]');
  if (!root) return;
  const svg = root.querySelector('svg.netmap__svg');
  const canvas = root.querySelector('.netmap__canvas');
  const tip = root.querySelector('.netmap__tip');
  const panel = root.querySelector('.netmap__panel');
  const detail = root.querySelector('.netmap__detail');
  const tools = root.querySelector('.netmap__tools');
  if (!svg || !canvas || !panel || !detail) return; // empty state: only the grey map and the message are shown

  const NS = 'http://www.w3.org/2000/svg';
  const unit = root.dataset.unit || '';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const home = (svg.dataset.vb || svg.getAttribute('viewBox')).split(/\s+/).map(Number); // [x, y, width, height]
  const aspect = home[3] / home[2];
  const MAX_ZOOM = 12;
  let view = home.slice();
  let frame = 0;
  let lastTrigger = null;
  let openCode = '';

  // The list entries keep their ids (#TH-50) for visitors without JavaScript. Here they are renamed so that the browser's
  // own jump to the fragment cannot pull the page down to the list when the link is meant to open the map.
  const listItems = {};
  document.querySelectorAll('.netarea').forEach((section) => {
    const code = section.id;
    const title = section.querySelector('.netarea__title');
    section.id = 'list-' + code;
    if (title) { title.id = 'list-' + code + '-title'; section.setAttribute('aria-labelledby', title.id); }
    listItems[code] = section;
  });

  // ---------- Interactive areas ----------
  const targets = {}; // code -> element that takes focus and clicks (the shape, or a ring for tiny countries)
  const markers = document.createElementNS(NS, 'g');
  markers.setAttribute('class', 'netmap__markers');
  const overlay = document.createElementNS(NS, 'g');
  overlay.setAttribute('class', 'netmap__overlay');
  const focusLayer = document.createElementNS(NS, 'g');
  svg.append(markers, overlay, focusLayer);

  svg.querySelectorAll('path.is-active').forEach((path) => {
    const code = path.dataset.code;
    let target = path;
    if (path.dataset.small === '1') {
      target = document.createElementNS(NS, 'circle');
      target.setAttribute('class', 'netmap__marker ' + [...path.classList].find((c) => /^is-l\d$/.test(c)));
      target.setAttribute('cx', path.dataset.cx);
      target.setAttribute('cy', path.dataset.cy);
      target.dataset.code = code;
      target.dataset.th = path.dataset.th;
      target.dataset.count = path.dataset.count;
      markers.append(target);
    }
    target.setAttribute('tabindex', '0');
    target.setAttribute('role', 'button');
    target.setAttribute('aria-label', path.dataset.th + ': ' + path.dataset.count + ' ' + unit);
    target.setAttribute('aria-controls', panel.id);
    target.setAttribute('aria-expanded', 'false');
    targets[code] = { el: target, path };
  });

  const areaOf = (node) => {
    const el = node.closest ? node.closest('[data-code]') : null;
    return el && targets[el.dataset.code] ? el.dataset.code : '';
  };

  // ---------- Tooltip ----------
  const showTip = (code, x, y) => {
    const { path } = targets[code];
    tip.textContent = path.dataset.th + ' · ' + path.dataset.count + ' ' + unit;
    tip.hidden = false;
    const width = canvas.clientWidth;
    tip.style.left = Math.max(8, Math.min(width - 8, x)) + 'px';
    tip.style.top = Math.max(8, y) + 'px';
  };
  const hideTip = () => { tip.hidden = true; };
  svg.addEventListener('pointermove', (event) => {
    if (drag && drag.moved) return;
    const code = areaOf(event.target);
    if (!code) return hideTip();
    const box = canvas.getBoundingClientRect();
    showTip(code, event.clientX - box.left, event.clientY - box.top);
  });
  svg.addEventListener('pointerleave', hideTip);
  // Neighbouring shapes are drawn later and would cover half of a stroke, so the focus ring is a copy drawn on top.
  const ring = (code) => {
    focusLayer.replaceChildren();
    if (!code) return;
    const { el, path } = targets[code];
    [['netmap__focusring', 0], ['netmap__focusring netmap__focusring--ink', 1]].forEach(([cls]) => {
      const copy = el === path ? document.createElementNS(NS, 'path') : document.createElementNS(NS, 'circle');
      if (el === path) copy.setAttribute('d', path.getAttribute('d'));
      else ['cx', 'cy', 'r'].forEach((a) => copy.setAttribute(a, el.getAttribute(a)));
      copy.setAttribute('class', cls);
      focusLayer.append(copy);
    });
  };
  svg.addEventListener('focusin', (event) => {
    const code = areaOf(event.target);
    if (!code) return;
    ensureVisible(targets[code].el);
    ring(code);
    const box = canvas.getBoundingClientRect();
    const rect = targets[code].el.getBoundingClientRect();
    showTip(code, rect.left + rect.width / 2 - box.left, rect.top - box.top - 6);
  });
  svg.addEventListener('focusout', () => { hideTip(); ring(''); });

  // ---------- Detail panel ----------
  const select = (code) => {
    Object.entries(targets).forEach(([key, t]) => {
      t.el.setAttribute('aria-expanded', key === code ? 'true' : 'false');
      t.path.classList.toggle('is-selected', key === code);
      t.el.classList.toggle('is-selected', key === code);
    });
    overlay.replaceChildren();
    if (code) {
      const outline = document.createElementNS(NS, 'path');
      outline.setAttribute('d', targets[code].path.getAttribute('d'));
      outline.setAttribute('class', 'netmap__outline');
      overlay.append(outline);
    }
  };

  const open = (code, options = {}) => {
    const source = listItems[code];
    if (!source || !targets[code]) return;
    const clone = source.cloneNode(true);
    clone.removeAttribute('id');
    clone.removeAttribute('aria-labelledby');
    const heading = clone.querySelector('.netarea__title');
    if (heading) heading.removeAttribute('id');
    detail.replaceChildren(clone);
    panel.classList.add('is-open');
    root.classList.add('has-selection');
    openCode = code;
    select(code);
    if (options.hash !== false) history.replaceState(null, '', '#' + code);
    if (options.focus !== false) { lastTrigger = targets[code].el; panel.focus({ preventScroll: true }); }
  };

  const close = (restoreFocus = true) => {
    if (!openCode) return;
    panel.classList.remove('is-open');
    root.classList.remove('has-selection');
    detail.replaceChildren();
    select('');
    openCode = '';
    history.replaceState(null, '', location.pathname + location.search);
    if (restoreFocus && lastTrigger) lastTrigger.focus({ preventScroll: true });
  };

  let suppressClick = false;
  svg.addEventListener('click', (event) => {
    if (suppressClick) { suppressClick = false; return; }
    const code = areaOf(event.target);
    if (code) open(code);
  });
  svg.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    const code = areaOf(event.target);
    if (!code) return;
    event.preventDefault();
    open(code);
  });
  panel.querySelector('.netmap__close').addEventListener('click', () => close());
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && openCode && !document.querySelector('dialog[open]')) { event.preventDefault(); close(); }
  });

  // ---------- View: zoom, pan, fit ----------
  const clamp = (v) => {
    const width = Math.min(home[2], Math.max(home[2] / MAX_ZOOM, v[2]));
    const height = width * aspect;
    const x = Math.min(home[0] + home[2] - width, Math.max(home[0], v[0]));
    const y = Math.min(home[1] + home[3] - height, Math.max(home[1], v[1]));
    return [x, y, width, height];
  };

  const applyView = () => {
    svg.setAttribute('viewBox', view.map((n) => +n.toFixed(2)).join(' '));
    const zoomed = view[2] < home[2] - 0.5;
    canvas.classList.toggle('is-zoomed', zoomed);
    // Rings stay the same size on screen however far the map is zoomed.
    const px = canvas.clientWidth || 1;
    const radius = (7 * view[2]) / px;
    markers.querySelectorAll('circle').forEach((c) => { c.setAttribute('r', radius.toFixed(2)); });
    focusLayer.querySelectorAll('circle').forEach((c) => { c.setAttribute('r', (radius * 1.25).toFixed(2)); });
    markers.style.setProperty('stroke-width', ((1.5 * view[2]) / px).toFixed(2));
  };

  const goTo = (target) => {
    cancelAnimationFrame(frame);
    const to = clamp(target);
    if (reduceMotion.matches) { view = to; applyView(); return; }
    const from = view.slice();
    const start = performance.now();
    const step = (now) => {
      const k = Math.min(1, (now - start) / 280);
      const ease = 1 - Math.pow(1 - k, 3);
      view = from.map((v, i) => v + (to[i] - v) * ease);
      applyView();
      if (k < 1) frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
  };

  const zoomBy = (factor) => {
    const width = view[2] * factor;
    goTo([view[0] + (view[2] - width) / 2, view[1] + (view[3] - width * aspect) / 2, width]);
  };

  // Smallest box around some shapes (and the rings of tiny countries), padded, in the map's aspect ratio.
  const fit = (elements) => {
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    elements.forEach((el) => {
      const b = el.getBBox();
      x0 = Math.min(x0, b.x); y0 = Math.min(y0, b.y); x1 = Math.max(x1, b.x + b.width); y1 = Math.max(y1, b.y + b.height);
    });
    if (!isFinite(x0)) return home;
    const pad = Math.max(x1 - x0, (y1 - y0) / aspect) * 0.14 + 6;
    let width = x1 - x0 + 2 * pad, height = y1 - y0 + 2 * pad;
    if (width * aspect < height) width = height / aspect; else height = width * aspect;
    width = Math.max(width, home[2] / MAX_ZOOM);
    height = width * aspect;
    return [(x0 + x1) / 2 - width / 2, (y0 + y1) / 2 - height / 2, width, height];
  };

  const elementsOf = (code) => (targets[code].el === targets[code].path ? [targets[code].path] : [targets[code].path, targets[code].el]);

  // Pan so that a focused ring or shape is on screen (keyboard users tab to areas that may be outside the zoomed view).
  function ensureVisible(el) {
    if (view[2] >= home[2] - 0.5) return;
    const b = el.getBBox();
    const cx = b.x + b.width / 2, cy = b.y + b.height / 2;
    const inside = cx > view[0] + view[2] * 0.06 && cx < view[0] + view[2] * 0.94 && cy > view[1] + view[3] * 0.06 && cy < view[1] + view[3] * 0.94;
    if (!inside) goTo([cx - view[2] / 2, cy - view[3] / 2, view[2]]);
  }

  // Drag to pan once the map is zoomed.
  let drag = null;
  canvas.addEventListener('pointerdown', (event) => {
    if (event.button !== 0 || !canvas.classList.contains('is-zoomed')) return;
    drag = { x: event.clientX, y: event.clientY, view: view.slice(), moved: false, id: event.pointerId };
  });
  canvas.addEventListener('pointermove', (event) => {
    if (!drag) return;
    const dx = event.clientX - drag.x, dy = event.clientY - drag.y;
    if (!drag.moved && Math.hypot(dx, dy) > 5) {
      drag.moved = true;
      hideTip();
      canvas.setPointerCapture(drag.id);
      canvas.classList.add('is-dragging');
    }
    if (!drag.moved) return;
    cancelAnimationFrame(frame);
    const scale = drag.view[2] / svg.getBoundingClientRect().width;
    view = clamp([drag.view[0] - dx * scale, drag.view[1] - dy * scale, drag.view[2]]);
    applyView();
  });
  const endDrag = () => {
    if (drag && drag.moved) { suppressClick = true; setTimeout(() => { suppressClick = false; }, 0); }
    canvas.classList.remove('is-dragging');
    drag = null;
  };
  canvas.addEventListener('pointerup', endDrag);
  canvas.addEventListener('pointercancel', endDrag);

  // ---------- Tools: region buttons and zoom ----------
  if (tools) {
    const chips = [...tools.querySelectorAll('[data-region]')];
    const setRegion = (slug) => {
      chips.forEach((chip) => {
        const on = chip.dataset.region === slug;
        chip.classList.toggle('is-on', on);
        chip.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      svg.querySelectorAll('path[data-code]').forEach((path) => {
        path.classList.toggle('is-dimmed', slug !== '' && path.dataset.region !== slug);
      });
      if (slug === '') return goTo(home);
      const inRegion = [...svg.querySelectorAll('path[data-region="' + slug + '"]')];
      // World: fit the countries that have records. Thailand: fit the whole region.
      const withData = inRegion.filter((p) => p.classList.contains('is-active'));
      const shapes = root.dataset.netmap === 'world' ? withData : inRegion;
      goTo(fit(shapes.flatMap((p) => (targets[p.dataset.code] ? elementsOf(p.dataset.code) : [p]))));
    };
    chips.forEach((chip) => chip.addEventListener('click', () => setRegion(chip.dataset.region)));
    tools.querySelectorAll('[data-zoom]').forEach((button) => {
      button.addEventListener('click', () => {
        const action = button.dataset.zoom;
        if (action === 'in') zoomBy(0.6);
        else if (action === 'out') zoomBy(1 / 0.6);
        else setRegion('');
      });
    });
    tools.hidden = false;
  }

  // ---------- #CODE links (from search results or a shared address) ----------
  const openFromHash = (first) => {
    let code = '';
    try { code = decodeURIComponent(location.hash.slice(1)); } catch (error) { return; }
    if (!listItems[code] || !targets[code]) return;
    open(code, { focus: false, hash: false });
    goTo(fit(elementsOf(code)));
    const bring = () => root.scrollIntoView({ block: 'start', behavior: 'auto' });
    if (first) { bring(); window.addEventListener('load', bring, { once: true }); }
  };
  window.addEventListener('hashchange', () => openFromHash(false));

  if ('ResizeObserver' in window) new ResizeObserver(applyView).observe(canvas);
  applyView();
  root.classList.add('is-ready');
  openFromHash(true);
})();
