// Interactive maps on network-international.php (world) and network-community.php (Thailand).
// The page works without this file: areas with records are already coloured by the server and the full list is below
// the map. This script turns the map into a full-width stage: the view fills the screen, zoom (buttons, pinch,
// Ctrl/⌘ + wheel), drag, region buttons, a tooltip, a detail panel that zooms to the chosen area, #CODE links, and one
// opening sequence when the map first comes into view (arcs from Bangkhen on the world map, provinces lighting up from
// north to south on the map of Thailand). prefers-reduced-motion skips every animation.
// The CSP forbids inline style attributes, so visuals are classes or CSSOM properties (el.style.x), never setAttribute('style').
(() => {
  'use strict';
  const root = document.querySelector('[data-netmap]');
  if (!root) return;
  const svg = root.querySelector('svg.netmap__svg');
  const canvas = root.querySelector('.netmap__canvas');
  const tip = root.querySelector('.netmap__tip');
  const panel = root.querySelector('.netmap__panel');
  const detail = root.querySelector('.netmap__detail');
  if (!svg || !canvas) return;

  const NS = 'http://www.w3.org/2000/svg';
  const isWorld = root.dataset.netmap === 'world';
  const unit = root.dataset.unit || '';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const wide = window.matchMedia('(min-width: 901px)');
  const bounds = (svg.dataset.vb || svg.getAttribute('viewBox')).split(/\s+/).map(Number); // the map's own box
  const origin = (svg.dataset.origin || '').split(/\s+/).map(Number);
  const MAX_ZOOM = isWorld ? 14 : 8;
  const el = (name, cls) => { const node = document.createElementNS(NS, name); if (cls) node.setAttribute('class', cls); return node; };

  root.classList.add('is-ready'); // gives the canvas its full-screen height before the view is measured
  svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

  // Thailand's own box (the provinces only), so the first view can fill the stage with the country instead of the
  // neighbouring countries and sea around it. The map file's full box stays the limit for dragging and zooming out.
  let country = null;
  if (!isWorld) {
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    svg.querySelectorAll('path[data-code^="TH-"]').forEach((path) => {
      try {
        const b = path.getBBox();
        x0 = Math.min(x0, b.x); y0 = Math.min(y0, b.y); x1 = Math.max(x1, b.x + b.width); y1 = Math.max(y1, b.y + b.height);
      } catch (error) { /* not rendered */ }
    });
    if (isFinite(x0) && x1 > x0 && y1 > y0) country = [x0, y0, x1 - x0, y1 - y0];
  }

  // full: the whole map file in the stage's proportions. home: where the page starts (the whole world; Thailand alone).
  // On wide screens Thailand sits right of centre, clear of the counts and buttons on the left and the zoom buttons.
  const stageBox = () => {
    const w = canvas.clientWidth || 1, h = canvas.clientHeight || 1;
    const aspect = h / w;
    const fit = (box, pad) => {
      const width = Math.max(box[2], box[3] / aspect) * pad;
      return [box[0] + box[2] / 2 - width / 2, box[1] + box[3] / 2 - (width * aspect) / 2, width, width * aspect];
    };
    const full = fit(bounds, 1.04);
    let home = full.slice();
    if (country) {
      home = fit(country, 1.06);
      if (w > 900) home[0] -= home[2] * 0.07;
      home[0] = Math.max(full[0], Math.min(full[0] + full[2] - home[2], home[0]));
      home[1] = Math.max(full[1], Math.min(full[1] + full[3] - home[3], home[1]));
    }
    return { full, home, aspect };
  };

  if (!panel || !detail) {
    // Empty state: nothing to point at, but the grey map still has to fit the stage (and label sizes need --u).
    const fit = () => {
      const { home: box } = stageBox();
      svg.setAttribute('viewBox', box.map((n) => +n.toFixed(2)).join(' '));
      root.style.setProperty('--u', (box[2] / (canvas.clientWidth || 1)).toFixed(4));
    };
    fit();
    if ('ResizeObserver' in window) new ResizeObserver(fit).observe(canvas);
    return;
  }

  // The list entries keep their ids (#TH-50) for visitors without JavaScript. They are renamed here so that the browser's
  // own jump to the fragment cannot pull the page down to the list when the link is meant to open the map.
  const listItems = {};
  document.querySelectorAll('.netarea').forEach((section) => {
    const code = section.id;
    const title = section.querySelector('.netarea__title');
    section.id = 'list-' + code;
    if (title) { title.id = 'list-' + code + '-title'; section.setAttribute('aria-labelledby', title.id); }
    listItems[code] = section;
  });

  // ---------- Layers drawn by the script (in paint order) ----------
  const arcLayer = el('g', 'netmap__arcs');
  const bubbleLayer = el('g', 'netmap__bubbles');
  const markerLayer = el('g', 'netmap__markers');
  const originLayer = el('g', 'netmap__origin');
  const overlay = el('g', 'netmap__overlay');
  const focusLayer = el('g', 'netmap__focus');
  svg.append(arcLayer, bubbleLayer, markerLayer, originLayer, overlay, focusLayer);

  const targets = {}; // code -> { el: element that takes focus and clicks, path, arc, bubble, cx, cy, count }
  svg.querySelectorAll('path.is-active').forEach((path) => {
    const code = path.dataset.code;
    const level = [...path.classList].find((c) => /^is-l\d$/.test(c)) || 'is-l1';
    const t = { path, el: path, cx: +path.dataset.cx, cy: +path.dataset.cy, count: +path.dataset.count, level };
    if (path.dataset.small === '1') { // a ring for countries too small to point at
      t.el = el('circle', 'netmap__marker ' + level);
      t.el.setAttribute('cx', t.cx); t.el.setAttribute('cy', t.cy);
      t.el.dataset.code = code;
      markerLayer.append(t.el);
    }
    t.el.setAttribute('tabindex', '0');
    t.el.setAttribute('role', 'button');
    t.el.setAttribute('aria-label', path.dataset.th + ': ' + t.count + ' ' + unit);
    t.el.setAttribute('aria-controls', panel.id);
    t.el.setAttribute('aria-expanded', 'false');
    targets[code] = t;
  });
  const codes = Object.keys(targets);

  if (origin.length === 2 && isFinite(origin[0])) {
    const halo = el('circle', 'netmap__origin-halo');
    const dot = el('circle', 'netmap__origin-dot');
    [halo, dot].forEach((c) => { c.setAttribute('cx', origin[0]); c.setAttribute('cy', origin[1]); });
    originLayer.append(halo, dot);
  }

  if (isWorld && origin.length === 2) {
    // One arc from Bangkhen to each partner country, bent away from the straight line so the arcs fan out.
    codes.forEach((code) => {
      const t = targets[code];
      const [ox, oy] = origin;
      const dx = t.cx - ox, dy = t.cy - oy, dist = Math.hypot(dx, dy);
      if (dist < 4) return; // Thailand itself
      let nx = -dy / dist, ny = dx / dist;
      if (ny > 0) { nx = -nx; ny = -ny; } // always bow upwards (north on the map)
      const bend = Math.min(dist * 0.32, 120);
      const qx = (ox + t.cx) / 2 + nx * bend, qy = (oy + t.cy) / 2 + ny * bend;
      t.arc = el('path', 'netmap__arc');
      t.arc.setAttribute('d', `M${ox} ${oy}Q${qx.toFixed(1)} ${qy.toFixed(1)} ${t.cx} ${t.cy}`);
      t.arc.dataset.code = code;
      t.end = el('circle', 'netmap__arc-end');
      t.end.setAttribute('cx', t.cx); t.end.setAttribute('cy', t.cy);
      arcLayer.append(t.arc, t.end);
    });
  } else if (!isWorld) {
    // Circles sized by the number of projects (area grows with the count), with the number inside from two up.
    codes.forEach((code) => {
      const t = targets[code];
      const g = el('g', 'netmap__bubble ' + t.level);
      const c = el('circle');
      c.setAttribute('cx', t.cx); c.setAttribute('cy', t.cy);
      c.dataset.r = (6 + 4.2 * Math.sqrt(t.count)).toFixed(1); // radius on screen, in px
      g.append(c);
      if (t.count > 1) {
        const n = el('text');
        n.setAttribute('x', t.cx); n.setAttribute('y', t.cy);
        n.textContent = String(t.count);
        g.append(n);
      }
      t.bubble = g;
      bubbleLayer.append(g);
    });
  }

  const areaOf = (node) => {
    const hit = node && node.closest ? node.closest('[data-code]') : null;
    return hit && targets[hit.dataset.code] ? hit.dataset.code : '';
  };

  // ---------- View: full-stage box, zoom, pan ----------
  let full, home, view, aspect = 1, frame = 0;
  let dest = null; // where the current zoom is heading, so a panel reopened mid-animation remembers the right view
  const measure = () => {
    const w = canvas.clientWidth || 1;
    const stage = stageBox();
    aspect = stage.aspect;
    full = stage.full;
    home = stage.home;
    if (isWorld && w < 700 && origin.length === 2) {
      // Phones: start closer, around Southeast Asia, instead of a thin strip of the whole world.
      const zw = full[2] / 2.2;
      home = clamp([origin[0] - zw * 0.45, origin[1] - (zw * aspect) / 2, zw]);
    }
  };
  function clamp(v) {
    const width = Math.min(full[2], Math.max(full[2] / MAX_ZOOM, v[2]));
    const height = width * aspect;
    const x = Math.min(full[0] + full[2] - width, Math.max(full[0], v[0]));
    const y = Math.min(full[1] + full[3] - height, Math.max(full[1], v[1]));
    return [x, y, width, height];
  }
  const zoomed = () => view[2] < home[2] - 0.5 || Math.abs(view[0] - home[0]) > 0.5 || Math.abs(view[1] - home[1]) > 0.5;

  const applyView = () => {
    svg.setAttribute('viewBox', view.map((n) => +n.toFixed(2)).join(' '));
    canvas.classList.toggle('is-zoomed', view[2] < full[2] - 0.5);
    // Rings, arcs and the origin stay the same size on screen however far the map is zoomed.
    const unitPx = view[2] / (canvas.clientWidth || 1);
    markerLayer.querySelectorAll('circle').forEach((c) => c.setAttribute('r', (7 * unitPx).toFixed(2)));
    focusLayer.querySelectorAll('circle').forEach((c) => c.setAttribute('r', (9 * unitPx).toFixed(2)));
    arcLayer.querySelectorAll('.netmap__arc-end').forEach((c) => c.setAttribute('r', (2.6 * unitPx).toFixed(2)));
    originLayer.querySelectorAll('.netmap__origin-dot').forEach((c) => c.setAttribute('r', (5 * unitPx).toFixed(2)));
    originLayer.querySelectorAll('.netmap__origin-halo').forEach((c) => c.setAttribute('r', (12 * unitPx).toFixed(2)));
    root.style.setProperty('--u', unitPx.toFixed(4)); // CSS multiplies stroke widths by this
    arcLayer.style.setProperty('stroke-width', (1.6 * unitPx).toFixed(3));
    markerLayer.style.setProperty('stroke-width', (1.5 * unitPx).toFixed(3));
    originLayer.style.setProperty('stroke-width', (2 * unitPx).toFixed(3));
    if (!isWorld) {
      bubbleLayer.style.setProperty('stroke-width', (1.5 * unitPx).toFixed(3));
      bubbleLayer.querySelectorAll('circle').forEach((c) => c.setAttribute('r', (c.dataset.r * unitPx).toFixed(2)));
    }
  };

  const goTo = (target, instant) => {
    cancelAnimationFrame(frame);
    const to = clamp(target);
    dest = to;
    if (instant || reduceMotion.matches) { view = to; applyView(); return; }
    const from = view.slice();
    const start = performance.now();
    const duration = 520;
    const step = (now) => {
      const k = Math.min(1, (now - start) / duration);
      const ease = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
      // Interpolate the centre and the log of the width, so zooming feels even at every scale.
      const fw = Math.exp(Math.log(from[2]) + (Math.log(to[2]) - Math.log(from[2])) * ease);
      const fcx = from[0] + from[2] / 2 + (to[0] + to[2] / 2 - from[0] - from[2] / 2) * ease;
      const fcy = from[1] + from[3] / 2 + (to[1] + to[3] / 2 - from[1] - from[3] / 2) * ease;
      view = [fcx - fw / 2, fcy - (fw * aspect) / 2, fw, fw * aspect];
      applyView();
      if (k < 1) frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
  };

  const zoomAt = (factor, sx, sy, instant) => {
    // sx, sy: point on the canvas (0..1) that stays put while zooming
    const width = Math.min(full[2], Math.max(full[2] / MAX_ZOOM, view[2] * factor));
    const px = view[0] + view[2] * sx, py = view[1] + view[3] * sy;
    goTo([px - width * sx, py - width * aspect * sy, width], instant);
  };

  // Smallest box around some shapes, padded, in the stage's proportions. With the panel open on a wide screen, the
  // box is placed in the part of the map the panel does not cover.
  const fit = (shapes, besidePanel) => {
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    shapes.forEach((s) => {
      const b = s.getBBox();
      x0 = Math.min(x0, b.x); y0 = Math.min(y0, b.y); x1 = Math.max(x1, b.x + b.width); y1 = Math.max(y1, b.y + b.height);
    });
    if (!isFinite(x0)) return home;
    const bw = x1 - x0, bh = y1 - y0;
    const share = besidePanel && wide.matches ? Math.max(0.35, 1 - (panel.offsetWidth + 48) / (canvas.clientWidth || 1)) : 1;
    // Leave the area some surroundings: never closer than a sixth of the world map, or about a third of the map of Thailand.
    let width = Math.max((bw * 2.4) / share, (bh * 2.4) / aspect, full[2] / (isWorld ? 6 : 2.6));
    width = Math.min(width, full[2]);
    const cx = (x0 + x1) / 2, cy = (y0 + y1) / 2;
    // Phones: the panel is a sheet over the lower part of the screen, so the area goes in the middle of what stays visible.
    let up = 0.5;
    if (besidePanel && !wide.matches) {
      const r = canvas.getBoundingClientRect();
      const top = Math.max(r.top, 0), bottom = Math.min(r.bottom, window.innerHeight - panel.offsetHeight);
      up = bottom > top ? Math.min(0.5, Math.max(0.08, ((top + bottom) / 2 - r.top) / r.height)) : 0.2;
    }
    return [cx - (width * share) / 2, cy - width * aspect * up, width];
  };
  const shapesOf = (code) => {
    const t = targets[code];
    return t.el === t.path ? [t.path] : [t.path, t.el];
  };

  // Pan so that a focused area is on screen (keyboard users tab to areas outside the zoomed view).
  const ensureVisible = (code) => {
    const t = targets[code];
    const inside = t.cx > view[0] + view[2] * 0.05 && t.cx < view[0] + view[2] * 0.95 && t.cy > view[1] + view[3] * 0.05 && t.cy < view[1] + view[3] * 0.95;
    if (!inside) goTo([t.cx - view[2] / 2, t.cy - view[3] / 2, view[2]]);
  };

  // ---------- Pointer: drag, pinch, Ctrl/⌘ + wheel ----------
  const pointers = new Map();
  let drag = null, pinch = null, suppressClick = false;
  canvas.addEventListener('pointerdown', (event) => {
    if (event.button !== 0 && event.pointerType === 'mouse') return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (pointers.size === 2) {
      const [a, b] = [...pointers.values()];
      pinch = { dist: Math.hypot(a.x - b.x, a.y - b.y), view: view.slice() };
      drag = null;
    } else if (pointers.size === 1 && canvas.classList.contains('is-zoomed')) {
      drag = { x: event.clientX, y: event.clientY, view: view.slice(), moved: false, id: event.pointerId };
    }
  });
  canvas.addEventListener('pointermove', (event) => {
    if (!pointers.has(event.pointerId)) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    const box = canvas.getBoundingClientRect();
    if (pinch && pointers.size === 2) {
      const [a, b] = [...pointers.values()];
      const dist = Math.hypot(a.x - b.x, a.y - b.y) || 1;
      view = pinch.view.slice();
      zoomAt(pinch.dist / dist, ((a.x + b.x) / 2 - box.left) / box.width, ((a.y + b.y) / 2 - box.top) / box.height, true);
      suppressClick = true;
      return;
    }
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
    dest = null;
    const scale = drag.view[2] / box.width;
    view = clamp([drag.view[0] - dx * scale, drag.view[1] - dy * scale, drag.view[2]]);
    applyView();
  });
  const endPointer = (event) => {
    pointers.delete(event.pointerId);
    if (pointers.size < 2) pinch = null;
    if (drag && drag.moved) suppressClick = true;
    if (suppressClick) setTimeout(() => { suppressClick = false; }, 0);
    canvas.classList.remove('is-dragging');
    drag = null;
  };
  canvas.addEventListener('pointerup', endPointer);
  canvas.addEventListener('pointercancel', endPointer);
  canvas.addEventListener('wheel', (event) => {
    if (!event.ctrlKey && !event.metaKey) return; // plain scrolling keeps scrolling the page
    event.preventDefault();
    const box = canvas.getBoundingClientRect();
    zoomAt(Math.exp(event.deltaY * 0.01), (event.clientX - box.left) / box.width, (event.clientY - box.top) / box.height, true);
  }, { passive: false });

  // ---------- Tooltip and hover ----------
  let hot = '';
  const setHot = (code) => {
    if (code === hot) return;
    if (hot && targets[hot]) { targets[hot].path.classList.remove('is-hot'); if (targets[hot].arc) targets[hot].arc.classList.remove('is-hot'); }
    hot = code;
    root.classList.toggle('has-hot', !!code);
    if (code) { targets[code].path.classList.add('is-hot'); if (targets[code].arc) targets[code].arc.classList.add('is-hot'); }
  };
  const showTip = (code, x, y) => {
    const t = targets[code];
    const names = [...listItems[code].querySelectorAll('.netitem__title')].map((n) => n.textContent.trim());
    const title = document.createElement('strong');
    title.textContent = t.path.dataset.th;
    const count = document.createElement('span');
    count.className = 'netmap__tip-count';
    count.textContent = t.count + ' ' + unit;
    const list = document.createElement('span');
    list.className = 'netmap__tip-list';
    list.textContent = names.slice(0, 2).join('\n') + (names.length > 2 ? '\nและอีก ' + (names.length - 2) + ' ' + unit : '');
    tip.replaceChildren(title, count, list);
    tip.hidden = false;
    const w = canvas.clientWidth;
    tip.style.left = Math.max(12, Math.min(w - 12, x)) + 'px';
    tip.style.top = Math.max(12, y) + 'px';
    tip.classList.toggle('is-below', y < 140);
  };
  const hideTip = () => { tip.hidden = true; setHot(''); };
  svg.addEventListener('pointermove', (event) => {
    if ((drag && drag.moved) || pinch || event.pointerType === 'touch') return;
    const code = areaOf(event.target);
    if (!code) return hideTip();
    const box = canvas.getBoundingClientRect();
    setHot(code);
    showTip(code, event.clientX - box.left, event.clientY - box.top);
  });
  svg.addEventListener('pointerleave', hideTip);

  // Neighbouring shapes are drawn later and would cover half of a stroke, so the focus ring is a copy drawn on top.
  const ring = (code) => {
    focusLayer.replaceChildren();
    if (!code) return;
    const t = targets[code];
    ['netmap__focusring', 'netmap__focusring netmap__focusring--ink'].forEach((cls) => {
      const copy = t.el === t.path ? el('path', cls) : el('circle', cls);
      if (t.el === t.path) copy.setAttribute('d', t.path.getAttribute('d'));
      else { copy.setAttribute('cx', t.cx); copy.setAttribute('cy', t.cy); }
      focusLayer.append(copy);
    });
    applyView();
  };
  svg.addEventListener('focusin', (event) => {
    const code = areaOf(event.target);
    if (!code) return;
    ensureVisible(code);
    ring(code);
    setHot(code);
    const box = canvas.getBoundingClientRect();
    const rect = targets[code].el.getBoundingClientRect();
    showTip(code, rect.left + rect.width / 2 - box.left, rect.top - box.top);
  });
  svg.addEventListener('focusout', () => { hideTip(); ring(''); });

  // ---------- Detail panel ----------
  let openCode = '', lastTrigger = null, viewBefore = null;
  const select = (code) => {
    codes.forEach((key) => {
      const t = targets[key];
      const on = key === code;
      t.el.setAttribute('aria-expanded', on ? 'true' : 'false');
      t.path.classList.toggle('is-selected', on);
      if (t.arc) t.arc.classList.toggle('is-selected', on);
    });
    root.classList.toggle('has-selection', !!code);
    overlay.replaceChildren();
    if (code) {
      const outline = el('path', 'netmap__outline');
      outline.setAttribute('d', targets[code].path.getAttribute('d'));
      overlay.append(outline);
    }
  };

  const open = (code, options = {}) => {
    const source = listItems[code];
    if (!source || !targets[code]) return;
    const clone = source.cloneNode(true);
    clone.removeAttribute('id');
    clone.removeAttribute('aria-labelledby');
    clone.querySelectorAll('[id]').forEach((n) => n.removeAttribute('id'));
    detail.replaceChildren(clone);
    detail.scrollTop = 0;
    panel.scrollTop = 0;
    if (!openCode) viewBefore = (dest || view).slice();
    openCode = code;
    panel.classList.add('is-open');
    select(code);
    goTo(fit(shapesOf(code), true));
    if (options.hash !== false) history.replaceState(null, '', '#' + code);
    if (options.focus !== false) { lastTrigger = targets[code].el; panel.focus({ preventScroll: true }); }
  };

  const close = (restoreFocus = true) => {
    if (!openCode) return;
    panel.classList.remove('is-open');
    detail.replaceChildren();
    select('');
    openCode = '';
    if (viewBefore) goTo(viewBefore);
    viewBefore = null;
    history.replaceState(null, '', location.pathname + location.search);
    if (restoreFocus && lastTrigger) lastTrigger.focus({ preventScroll: true });
  };

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

  // ---------- Region buttons and zoom buttons ----------
  const chips = [...root.querySelectorAll('.netmap__chip')];
  const setRegion = (slug) => {
    chips.forEach((chip) => {
      const on = chip.dataset.region === slug;
      chip.classList.toggle('is-on', on);
      chip.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    svg.querySelectorAll('path[data-code]').forEach((path) => path.classList.toggle('is-dimmed', slug !== '' && path.dataset.region !== slug));
    codes.forEach((code) => {
      const t = targets[code];
      const off = slug !== '' && t.path.dataset.region !== slug;
      [t.arc, t.end, t.bubble, t.el === t.path ? null : t.el].forEach((n) => n && n.classList.toggle('is-dimmed', off));
    });
    if (slug === '') return goTo(home);
    const inRegion = [...svg.querySelectorAll('path[data-region="' + slug + '"]')];
    // World: fit the countries that have records. Thailand: fit the whole region.
    const shapes = isWorld ? inRegion.filter((p) => targets[p.dataset.code]).flatMap((p) => shapesOf(p.dataset.code)) : inRegion;
    goTo(fit(shapes, !!openCode));
  };
  chips.forEach((chip) => chip.addEventListener('click', () => setRegion(chip.dataset.region)));
  root.querySelectorAll('[data-zoom]').forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.dataset.zoom;
      if (action === 'in') zoomAt(0.6, 0.5, 0.5);
      else if (action === 'out') zoomAt(1 / 0.6, 0.5, 0.5);
      else { if (openCode) close(false); setRegion(''); }
    });
  });
  root.querySelectorAll('.netmap__tools').forEach((n) => { n.hidden = false; });

  // ---------- The opening sequence: once, when the map first comes into view ----------
  const settle = () => {
    codes.forEach((code) => {
      const t = targets[code];
      t.path.classList.remove('is-pending');
      if (t.el !== t.path) t.el.classList.remove('is-pending');
      [t.arc, t.end, t.bubble].forEach((n) => n && n.classList.remove('is-pending'));
    });
    root.classList.remove('is-intro');
  };
  const intro = () => {
    if (reduceMotion.matches || !codes.length || typeof Element.prototype.animate !== 'function') return settle();
    root.classList.add('is-intro');
    if (isWorld) {
      const order = codes.filter((c) => targets[c].arc).sort((a, b) => targets[a].arc.getTotalLength() - targets[b].arc.getTotalLength());
      const gap = Math.min(120, 1500 / Math.max(1, order.length));
      order.forEach((code, i) => {
        const t = targets[code];
        const length = t.arc.getTotalLength();
        t.arc.style.strokeDasharray = String(length);
        t.arc.classList.remove('is-pending');
        const run = t.arc.animate([{ strokeDashoffset: length }, { strokeDashoffset: 0 }], {
          duration: Math.min(1300, 600 + length * 1.4), delay: 200 + i * gap, easing: 'cubic-bezier(.35,.1,.2,1)', fill: 'both' });
        run.onfinish = () => {
          t.arc.style.strokeDasharray = '';
          run.cancel();
          t.path.classList.remove('is-pending');
          if (t.el !== t.path) t.el.classList.remove('is-pending');
          t.end.classList.remove('is-pending');
          t.end.animate([{ transform: 'scale(4)', opacity: 0 }, { transform: 'scale(1)', opacity: 1 }], { duration: 420, easing: 'ease-out' });
        };
      });
      setTimeout(settle, 200 + order.length * gap + 1500); // anything left (Thailand itself) lights up at the end
    } else {
      const order = codes.slice().sort((a, b) => targets[a].cy - targets[b].cy); // north to south
      const gap = Math.min(110, 1300 / Math.max(1, order.length));
      order.forEach((code, i) => {
        const t = targets[code];
        const delay = 250 + i * gap;
        setTimeout(() => t.path.classList.remove('is-pending'), delay);
        t.bubble.classList.remove('is-pending');
        t.bubble.animate([{ transform: 'scale(0)', opacity: 0 }, { transform: 'scale(1.18)', opacity: 1, offset: 0.7 }, { transform: 'scale(1)', opacity: 1 }],
          { duration: 520, delay: delay + 120, easing: 'ease-out', fill: 'backwards' });
      });
      setTimeout(settle, 250 + order.length * gap + 700);
    }
  };

  // ---------- Start ----------
  measure();
  view = home.slice();
  applyView();

  let code = '';
  try { code = decodeURIComponent(location.hash.slice(1)); } catch (error) { code = ''; }
  const fromLink = !!(listItems[code] && targets[code]);
  const bring = () => (wide.matches ? root : canvas).scrollIntoView({ block: 'start' });
  if (fromLink) {
    settle();
    bring();
    open(code, { focus: false, hash: false });
    window.addEventListener('load', bring, { once: true });
  } else {
    codes.forEach((c) => {
      const t = targets[c];
      t.path.classList.add('is-pending');
      if (t.el !== t.path) t.el.classList.add('is-pending');
      [t.arc, t.end, t.bubble].forEach((n) => n && n.classList.add('is-pending'));
    });
    if ('IntersectionObserver' in window) {
      const seen = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) { seen.disconnect(); intro(); }
      }, { threshold: 0.35 });
      seen.observe(canvas);
    } else intro();
  }

  window.addEventListener('hashchange', () => {
    let next = '';
    try { next = decodeURIComponent(location.hash.slice(1)); } catch (error) { return; }
    if (listItems[next] && targets[next]) { settle(); bring(); open(next, { focus: false, hash: false }); }
  });

  if ('ResizeObserver' in window) {
    let last = canvas.clientWidth + 'x' + canvas.clientHeight;
    new ResizeObserver(() => {
      const size = canvas.clientWidth + 'x' + canvas.clientHeight;
      if (size === last) return;
      last = size;
      const wasHome = !zoomed();
      const cx = view[0] + view[2] / 2, cy = view[1] + view[3] / 2, w = view[2];
      measure();
      cancelAnimationFrame(frame);
      dest = null;
      view = wasHome ? home.slice() : clamp([cx - w / 2, cy - (w * aspect) / 2, w]);
      applyView();
    }).observe(canvas);
  }
})();
