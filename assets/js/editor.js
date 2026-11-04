// Admin post editor: one panel with blocks (paragraph, heading H2-H4, picture, table), like the WordPress editor.
// Everything is plain text (no HTML is ever stored). Without JavaScript the page keeps the older text form (#legacy);
// this file swaps it for the blocks and sends the result as JSON in the hidden field body_json, which the server checks again.
(function () {
  'use strict';
  var form = document.getElementById('editform'), app = document.getElementById('blocks-app');
  if (!form || !app) { return; }
  var legacy = document.getElementById('legacy'), list = document.getElementById('blocks'), out = document.getElementById('body_json');
  var statusBox = document.getElementById('upload-status'), picker = document.getElementById('pick-images'), main = form.querySelector('.ed__main');
  var csrf = (form.querySelector('input[name=csrf]') || {}).value || '';
  var LIMITS = { rows: 100, cols: 12, cell: 500, upload: 1.5 * 1024 * 1024, side: 1920, max: 8 * 1024 * 1024, blocks: 1500 };
  var current = null, pending = 0, KEY = 'e2i-draft:' + (form.getAttribute('data-draft-key') || 'new');

  // ---- small helpers ----
  function h(tag, props, kids) {
    var node = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) {
      if (k === 'className') { node.className = props[k]; } else if (k === 'text') { node.textContent = props[k]; }
      else if (k.slice(0, 2) === 'on') { node.addEventListener(k.slice(2), props[k]); } else { node.setAttribute(k, props[k]); }
    });
    (kids || []).forEach(function (kid) { if (kid) { node.appendChild(kid); } });
    return node;
  }
  function button(label, title, fn, cls) {
    return h('button', { type: 'button', className: 'blk__btn' + (cls ? ' ' + cls : ''), text: label, title: title, 'aria-label': title, onclick: fn });
  }
  function clean(text) { return String(text).replace(/\s+/g, ' ').trim(); }
  function changed() { list.dispatchEvent(new Event('input', { bubbles: true })); }
  function fit(area) { area.style.height = 'auto'; area.style.height = (area.scrollHeight + 2) + 'px'; }
  function say(message, isError, undo) {
    var line = h('div', { text: message, className: isError ? 'err' : '' });
    if (undo) { line.appendChild(document.createTextNode(' ')); line.appendChild(h('button', { type: 'button', className: 'link', text: 'เลิกทำ', onclick: function () { undo(); line.remove(); statusBox.hidden = !statusBox.children.length; } })); }
    statusBox.appendChild(line); statusBox.hidden = false;
    while (statusBox.children.length > 5) { statusBox.removeChild(statusBox.firstChild); }
    return line;
  }
  function setCurrent(b) { if (current) { current.classList.remove('is-current'); } current = b; if (b) { b.classList.add('is-current'); } }
  function place(block, anchor) {
    var ref = anchor && anchor.parentNode === list ? anchor : current && current.parentNode === list ? current : null;
    if (ref) { ref.after(block); } else { list.appendChild(block); }
    return block;
  }
  function focusBlock(b, atEnd) {
    var f = b.querySelector('textarea,input[type=text],input:not([type])');
    if (!f) { return; }
    f.focus(); if (atEnd !== false && f.setSelectionRange) { var n = f.value.length; f.setSelectionRange(n, n); }
  }
  function ensureTail() {
    var last = list.lastElementChild;
    if (!last || last.getAttribute('data-t') !== 'p') { list.appendChild(makeP('')); }
  }
  function count() { return list.children.length; }

  // ---- blocks ----
  function wrap(type, body) {
    var b = h('div', { className: 'blk blk--' + type, 'data-t': type });
    b.appendChild(body);
    var bar = h('div', { className: 'blk__tools', role: 'group', 'aria-label': 'เครื่องมือของบล็อกนี้' });
    if (type === 'p' || type === 'h') {
      [[1, 'H2', 'ทำเป็นหัวข้อ H2'], [2, 'H3', 'ทำเป็นหัวข้อ H3'], [3, 'H4', 'ทำเป็นหัวข้อ H4']].forEach(function (l) {
        bar.appendChild(button(l[1], l[2], function () { convert(b, 'h', l[0]); }, b.getAttribute('data-level') == l[0] ? 'is-on' : ''));
      });
      if (type === 'h') { bar.appendChild(button('ย่อหน้า', 'ทำเป็นย่อหน้าธรรมดา', function () { convert(b, 'p'); })); }
    }
    bar.appendChild(button('▲', 'เลื่อนบล็อกขึ้น', function () { var prev = b.previousElementSibling; if (prev) { list.insertBefore(b, prev); keepFocus(b); changed(); } }));
    bar.appendChild(button('▼', 'เลื่อนบล็อกลง', function () { var next = b.nextElementSibling; if (next) { next.after(b); keepFocus(b); ensureTail(); changed(); } }));
    bar.appendChild(button('ลบ', 'ลบบล็อกนี้', function () { removeBlock(b); }, 'blk__btn--del'));
    b.appendChild(bar);
    b.addEventListener('focusin', function () { setCurrent(b); });
    return b;
  }
  function keepFocus(b) { var f = document.activeElement; if (f && b.contains(f)) { f.focus(); } else { focusBlock(b); } }
  function removeBlock(b) {
    var next = b.nextElementSibling, prev = b.previousElementSibling, type = b.getAttribute('data-t');
    var empty = (type === 'p' || type === 'h') && !(b.querySelector('textarea,input') || {}).value;
    b.remove(); if (current === b) { setCurrent(null); }
    ensureTail();
    if (!empty) {
      say('ลบ' + ({ p: 'ย่อหน้า', h: 'หัวข้อ', img: 'รูป', table: 'ตาราง' }[type]) + 'แล้ว', false, function () {
        if (next && next.parentNode === list) { list.insertBefore(b, next); } else { list.insertBefore(b, list.lastElementChild && list.lastElementChild.getAttribute('data-t') === 'p' && !list.lastElementChild.querySelector('textarea').value ? list.lastElementChild : null); }
        changed();
      });
    }
    var f = next || prev; if (f && f.parentNode === list) { focusBlock(f); }
    changed();
  }
  function convert(b, to, level) {
    var f = b.querySelector('textarea,input[type=text]'), text = f ? f.value : '';
    var nb = to === 'h' ? makeH(level, text) : makeP(text);
    b.replaceWith(nb); focusBlock(nb); changed();
  }
  // Markdown-style shortcut: a paragraph that starts with "# ", "## " or "### " becomes a heading (H2, H3, H4).
  function shortcut(text) { var m = /^(#{1,3})[ \t]+(\S[\s\S]*)$/.exec(text); return m ? [m[1].length, m[2]] : null; }
  function makeP(text) {
    var area = h('textarea', { className: 'blk__p', rows: 1, 'aria-label': 'ย่อหน้า', placeholder: 'พิมพ์เนื้อหา หรือกดปุ่ม + ด้านบนเพื่อเพิ่มหัวข้อ รูป ตาราง' });
    area.value = text || '';
    var b = wrap('p', area);
    requestAnimationFrame(function () { fit(area); });
    area.addEventListener('input', function () {
      var sc = shortcut(area.value);
      if (sc) { var hb = makeH(sc[0], sc[1]); b.replaceWith(hb); focusBlock(hb); changed(); return; }
      fit(area);
    });
    area.addEventListener('keydown', function (e) {
      if (e.isComposing || e.keyCode === 229) { return; }
      if (e.key === 'Enter') {
        e.preventDefault();
        var cut = area.selectionStart, rest = area.value.slice(area.selectionEnd);
        area.value = area.value.slice(0, cut).replace(/\s+$/, ''); fit(area);
        var nb = makeP(rest.replace(/^\s+/, '')); b.after(nb); nb.querySelector('textarea').focus(); nb.querySelector('textarea').setSelectionRange(0, 0); changed();
      } else if (e.key === 'Backspace' && area.selectionStart === 0 && area.selectionEnd === 0) {
        var prev = b.previousElementSibling; if (!prev) { return; }
        var pt = prev.getAttribute('data-t');
        if (pt === 'p' || pt === 'h') {
          e.preventDefault();
          var pf = prev.querySelector('textarea,input[type=text]'), join = pf.value.length;
          pf.value = pf.value + (pf.value && area.value ? ' ' : '') + area.value; if (pf.tagName === 'TEXTAREA') { fit(pf); }
          b.remove(); pf.focus(); pf.setSelectionRange(join + (join && area.value ? 1 : 0), join + (join && area.value ? 1 : 0)); ensureTail(); changed();
        } else if (!area.value) { e.preventDefault(); removeBlock(b); }
      }
    });
    return b;
  }
  function makeH(level, text) {
    level = Math.min(3, Math.max(1, +level || 1));
    var input = h('input', { type: 'text', className: 'blk__h blk__h--' + level, maxlength: 300, 'aria-label': 'หัวข้อ H' + (level + 1), placeholder: 'หัวข้อ H' + (level + 1) });
    input.value = text || '';
    var b = wrap('h', input); b.setAttribute('data-level', level);
    b.querySelectorAll('.blk__btn').forEach(function (x, i) { if (i === level - 1) { x.classList.add('is-on'); x.setAttribute('aria-pressed', 'true'); } });
    input.addEventListener('keydown', function (e) {
      if (e.isComposing || e.keyCode === 229) { return; }
      if (e.key === 'Enter') { e.preventDefault(); var nb = makeP(''); b.after(nb); focusBlock(nb); changed(); }
      else if (e.key === 'Backspace' && !input.value) { e.preventDefault(); convert(b, 'p'); }
    });
    return b;
  }
  function makeImg(d) {
    var img = h('img', { alt: '', className: 'blkimg__img' }); if (d.url) { img.src = d.url; }
    var alt = h('input', { type: 'text', maxlength: 400, 'aria-label': 'คำอธิบายรูป', placeholder: 'คำอธิบายรูป: ในรูปมีอะไร (สำหรับผู้ใช้โปรแกรมอ่านหน้าจอ)' });
    var cap = h('input', { type: 'text', maxlength: 400, 'aria-label': 'คำบรรยายใต้รูป', placeholder: 'คำบรรยายใต้รูป (ไม่บังคับ)' });
    alt.value = d.alt || ''; cap.value = d.caption || '';
    var note = h('p', { className: 'blkimg__note', role: 'status' });
    var fig = h('div', { className: 'blkimg' }, [img, h('div', { className: 'blkimg__f' }, [alt, cap, note])]);
    var b = wrap('img', fig);
    if (d.media) { b.setAttribute('data-media', d.media); }
    function warn() {
      if (b.getAttribute('data-state') === 'busy' || b.getAttribute('data-state') === 'failed') { return; }
      var none = clean(alt.value) === ''; alt.classList.toggle('bad', none);
      note.textContent = none ? 'ใส่คำอธิบายรูป ไม่เช่นนั้นรูปนี้จะไม่แสดงบนเว็บ' : ''; note.className = 'blkimg__note' + (none ? ' err' : '');
    }
    alt.addEventListener('input', warn);
    b.imageState = function (state, message) {
      b.setAttribute('data-state', state); note.textContent = message || ''; note.className = 'blkimg__note' + (state === 'failed' ? ' err' : '');
      if (state === 'ok') { warn(); }
    };
    if (d.uploading) { b.imageState('busy', d.name + ' · กำลังเตรียมรูป…'); } else { warn(); }
    return b;
  }

  // ---- tables ----
  function makeTable(d) {
    var rows = d.rows && d.rows.length ? d.rows.map(function (r) { return r.slice(); }) : [['', '', ''], ['', '', ''], ['', '', '']];
    var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 1);
    rows.forEach(function (r) { while (r.length < cols) { r.push(''); } });
    var table = h('table'), body = h('tbody'); table.appendChild(body);
    var head = h('input', { type: 'checkbox' }); head.checked = d.head !== false && (d.head || !d.rows);
    var cap = h('input', { type: 'text', maxlength: 400, 'aria-label': 'คำบรรยายใต้ตาราง', placeholder: 'คำบรรยายใต้ตาราง (ไม่บังคับ)' }); cap.value = d.caption || '';
    var note = h('p', { className: 'blkimg__note err', role: 'alert' });
    var box = h('div', { className: 'blktbl' });
    function read() { return Array.prototype.map.call(body.rows, function (tr) { return Array.prototype.map.call(tr.cells, function (td) { return td.firstChild.value; }); }); }
    function draw(data) {
      body.textContent = '';
      data.forEach(function (r, ri) {
        var tr = h('tr');
        r.forEach(function (v, ci) {
          var inp = h('input', { type: 'text', maxlength: LIMITS.cell, 'aria-label': 'แถว ' + (ri + 1) + ' คอลัมน์ ' + (ci + 1) }); inp.value = v;
          tr.appendChild(h('td', {}, [inp]));
        });
        body.appendChild(tr);
      });
      table.classList.toggle('has-head', head.checked);
    }
    function problem(r, c) {
      if (r > LIMITS.rows) { return 'ตารางใส่ได้ไม่เกิน ' + LIMITS.rows + ' แถว ลองแบ่งเป็นหลายตาราง'; }
      if (c > LIMITS.cols) { return 'ตารางใส่ได้ไม่เกิน ' + LIMITS.cols + ' คอลัมน์'; }
      return '';
    }
    function resize(addRow, addCol) {
      var data = read(), msg = problem(data.length + addRow, (data[0] || []).length + addCol);
      if (msg) { note.textContent = msg; return; }
      note.textContent = '';
      if (addRow > 0) { data.push(new Array((data[0] || ['']).length).fill('')); }
      if (addRow < 0 && data.length > 1) { data.pop(); }
      if (addCol > 0) { data.forEach(function (r) { r.push(''); }); }
      if (addCol < 0 && data[0].length > 1) { data.forEach(function (r) { r.pop(); }); }
      draw(data); changed();
    }
    var tools = h('div', { className: 'blktbl__tools' }, [
      button('+ แถว', 'เพิ่มแถวท้ายตาราง', function () { resize(1, 0); }),
      button('− แถว', 'ลบแถวสุดท้าย', function () { resize(-1, 0); }),
      button('+ คอลัมน์', 'เพิ่มคอลัมน์ท้ายตาราง', function () { resize(0, 1); }),
      button('− คอลัมน์', 'ลบคอลัมน์สุดท้าย', function () { resize(0, -1); }),
      h('label', { className: 'check' }, [head, document.createTextNode(' แถวแรกเป็นหัวตาราง')])
    ]);
    head.addEventListener('change', function () { table.classList.toggle('has-head', head.checked); changed(); });
    box.appendChild(h('div', { className: 'blktbl__scroll' }, [table])); box.appendChild(note); box.appendChild(tools); box.appendChild(cap);
    draw(rows);
    var b = wrap('table', box);
    b.tableData = function () { return { t: 'table', head: head.checked, rows: read(), caption: clean(cap.value) }; };
    // Enter goes down a row (adding one at the end); pasting several cells fills the grid from the cell under the cursor.
    body.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.isComposing || e.keyCode === 229 || e.target.tagName !== 'INPUT') { return; }
      e.preventDefault();
      var td = e.target.parentNode, tr = td.parentNode, c = td.cellIndex, next = tr.nextElementSibling;
      if (!next) { resize(1, 0); next = body.lastElementChild; }
      if (next) { next.cells[c].firstChild.focus(); }
    });
    body.addEventListener('paste', function (e) {
      var text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
      if (!text || !/[\t\n]/.test(text.replace(/\n$/, ''))) { return; }
      e.preventDefault(); e.stopPropagation();
      var grid = parseGrid(text), td = e.target.parentNode, r0 = td.parentNode.rowIndex - (table.tHead ? 1 : 0), c0 = td.cellIndex, data = read();
      var needRows = r0 + grid.length, needCols = c0 + grid.reduce(function (m, r) { return Math.max(m, r.length); }, 0);
      var msg = problem(needRows, Math.max(needCols, data[0].length));
      if (msg) { note.textContent = msg; return; }
      note.textContent = '';
      while (data.length < needRows) { data.push(new Array(data[0].length).fill('')); }
      var width = Math.max(needCols, data[0].length); data.forEach(function (r) { while (r.length < width) { r.push(''); } });
      grid.forEach(function (r, ri) { r.forEach(function (v, ci) { data[r0 + ri][c0 + ci] = v.slice(0, LIMITS.cell); }); });
      draw(data); changed();
    });
    return b;
  }
  function parseGrid(text) {
    text = text.replace(/\r\n?/g, '\n').replace(/\n+$/, '');
    var sep = text.indexOf('\t') >= 0 ? '\t' : '|';
    var rows = text.split('\n').filter(function (l) { return l.replace(/[ \t|]/g, '') !== '' && !(sep === '|' && /^[\s|:\-]+$/.test(l)); }).map(function (l) {
      if (sep === '|') { l = l.replace(/^\s*\|/, '').replace(/\|\s*$/, ''); }
      return l.split(sep).map(clean);
    });
    var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 0);
    rows.forEach(function (r) { while (r.length < cols) { r.push(''); } });
    return rows;
  }
  function htmlTableToGrid(html) {
    var doc = new DOMParser().parseFromString(html, 'text/html'), table = doc.querySelector('table');
    if (!table) { return null; }
    var rows = [];
    table.querySelectorAll('tr').forEach(function (tr) {
      var cells = [];
      tr.querySelectorAll('th,td').forEach(function (td) {
        cells.push(clean(td.textContent || ''));
        for (var s = 1; s < (parseInt(td.getAttribute('colspan'), 10) || 1); s++) { cells.push(''); }
      });
      if (cells.some(function (c) { return c !== ''; })) { rows.push(cells); }
    });
    var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 0);
    rows.forEach(function (r) { while (r.length < cols) { r.push(''); } });
    return rows.length ? rows : null;
  }
  function csvToGrid(raw) {
    raw = raw.replace(/^﻿/, '');
    var first = raw.split(/\r?\n/, 1)[0], counts = { ',': (first.match(/,/g) || []).length, ';': (first.match(/;/g) || []).length, '\t': (first.match(/\t/g) || []).length };
    var delim = counts['\t'] >= counts[','] && counts['\t'] >= counts[';'] ? '\t' : (counts[';'] > counts[','] ? ';' : ',');
    var rows = [], row = [], cell = '', quoted = false;
    for (var k = 0; k < raw.length; k++) {
      var ch = raw[k];
      if (quoted) { if (ch === '"') { if (raw[k + 1] === '"') { cell += '"'; k++; } else { quoted = false; } } else { cell += ch; } }
      else if (ch === '"') { quoted = true; }
      else if (ch === delim) { row.push(cell); cell = ''; }
      else if (ch === '\n' || ch === '\r') { if (ch === '\r' && raw[k + 1] === '\n') { k++; } row.push(cell); cell = ''; rows.push(row); row = []; }
      else { cell += ch; }
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    rows = rows.filter(function (r) { return r.some(function (c) { return c.trim() !== ''; }); }).map(function (r) { return r.map(clean); });
    var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 0);
    rows.forEach(function (r) { while (r.length < cols) { r.push(''); } });
    return rows;
  }
  function addGrid(rows, anchor) {
    var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 0), longest = 0;
    rows.forEach(function (r) { r.forEach(function (c) { longest = Math.max(longest, c.length); }); });
    var msg = rows.length > LIMITS.rows ? 'ตารางมี ' + rows.length + ' แถว ใส่ได้ไม่เกิน ' + LIMITS.rows + ' แถว ลองแบ่งเป็นหลายตาราง'
      : cols > LIMITS.cols ? 'ตารางมี ' + cols + ' คอลัมน์ ใส่ได้ไม่เกิน ' + LIMITS.cols + ' คอลัมน์'
      : longest > LIMITS.cell ? 'มีช่องที่ยาว ' + longest + ' ตัวอักษร ช่องหนึ่งใส่ได้ไม่เกิน ' + LIMITS.cell + ' ตัวอักษร' : '';
    if (msg) { say('วางตารางไม่ได้: ' + msg, true); return null; }
    var b = place(makeTable({ rows: rows, head: true }), anchor); ensureTail(); changed();
    say('สร้างตารางจาก ' + rows.length + ' แถวแล้ว แถวแรกตั้งเป็นหัวตาราง (ยกเลิกได้ใต้ตาราง)');
    return b;
  }

  // ---- pictures: shrunk in the browser, sent one at a time to upload.php ----
  function isImage(file) { return /^image\//.test(file.type) || /\.(jpe?g|png|webp|gif)$/i.test(file.name); }
  function loadBitmap(file) {
    if (window.createImageBitmap) { return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return createImageBitmap(file); }); }
    return new Promise(function (resolve, reject) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); }; img.onerror = reject; img.src = url;
    });
  }
  function prepareImage(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { return Promise.resolve(file); }
    return loadBitmap(file).then(function (bmp) {
      var w = bmp.width, hh = bmp.height, scale = Math.min(1, LIMITS.side / Math.max(w, hh));
      if (scale === 1 && file.size <= LIMITS.upload) { return file; }
      var canvas = document.createElement('canvas'); canvas.width = Math.round(w * scale); canvas.height = Math.round(hh * scale);
      canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
          if (blob && blob.type === 'image/webp') { return resolve(blob); }
          var flat = document.createElement('canvas'); flat.width = canvas.width; flat.height = canvas.height;
          var c2 = flat.getContext('2d'); c2.fillStyle = '#fff'; c2.fillRect(0, 0, flat.width, flat.height); c2.drawImage(canvas, 0, 0);
          flat.toBlob(function (jpeg) { resolve(jpeg || file); }, 'image/jpeg', 0.86);
        }, 'image/webp', 0.86);
      });
    }).then(function (blob) { return blob.size < file.size || blob === file ? blob : file; }, function () { return file; });
  }
  function sendImage(blob, name, onProgress) {
    return new Promise(function (resolve, reject) {
      var data = new FormData(); data.append('csrf', csrf); data.append('file', blob, name);
      var xhr = new XMLHttpRequest(); xhr.open('POST', 'upload.php'); xhr.responseType = 'text';
      xhr.upload.onprogress = function (e) { if (e.lengthComputable) { onProgress(Math.round(e.loaded / e.total * 100)); } };
      xhr.onload = function () {
        var res = null; try { res = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
        if (xhr.status === 200 && res && res.id) { resolve(res); }
        else if (res && res.error) { reject(new Error(res.error)); }
        else if (xhr.status === 403) { reject(new Error('หน้านี้หมดอายุ โหลดหน้าใหม่แล้วลองอีกครั้ง (เนื้อหาที่พิมพ์ไว้จะกู้คืนให้)')); }
        else if (xhr.status === 413) { reject(new Error('รูปใหญ่เกินที่เซิร์ฟเวอร์รับ ลองใช้รูปที่เล็กลง')); }
        else { reject(new Error('อัปโหลดไม่สำเร็จ (รหัส ' + xhr.status + ')')); }
      };
      xhr.onerror = function () { reject(new Error('อัปโหลดไม่สำเร็จ ตรวจสอบอินเทอร์เน็ตแล้วลองใหม่')); };
      xhr.send(data);
    });
  }
  // Placeholders appear at once, in the order the files were chosen; the uploads then run one after another.
  function addPictures(files) {
    var anchor = current, jobs = [];
    files.forEach(function (file) {
      var name = file.name || 'screenshot.png';
      var b = makeImg({ uploading: true, name: name }); place(b, anchor); anchor = b; jobs.push([file, name, b]);
    });
    ensureTail(); changed(); pending += jobs.length;
    return jobs.reduce(function (chain, job) {
      return chain.then(function () {
        var file = job[0], name = job[1], b = job[2];
        return prepareImage(file).then(function (blob) {
          if (blob.size > LIMITS.max) { throw new Error(name + ': ไฟล์ใหญ่เกิน 8 MB ลองใช้รูปที่เล็กลง'); }
          var ext = blob.type === 'image/webp' ? '.webp' : blob.type === 'image/jpeg' ? '.jpg' : (/\.[^.]+$/.exec(name) || ['.png'])[0];
          return sendImage(blob, name.replace(/\.[^.]+$/, '') + ext, function (pct) { b.imageState('busy', name + ' · กำลังอัปโหลด ' + pct + '%'); });
        }).then(function (res) {
          b.setAttribute('data-media', res.id); b.querySelector('img').src = res.url; b.imageState('ok');
          var alt = b.querySelector('input'); if (alt && !alt.value) { alt.focus(); }
        }).catch(function (error) {
          b.imageState('failed', error.message); b.classList.add('blk--error'); b.removeAttribute('data-media');
          say(error.message, true);
        }).then(function () { pending--; changed(); });
      });
    }, Promise.resolve());
  }

  // ---- data in / out ----
  function serialize() {
    var res = [];
    Array.prototype.forEach.call(list.children, function (b) {
      var t = b.getAttribute('data-t');
      if (t === 'p') { var v = b.querySelector('textarea').value.trim(); if (v) { res.push({ t: 'p', text: v }); } }
      else if (t === 'h') { var hv = clean(b.querySelector('input').value); if (hv) { res.push({ t: 'h', level: +b.getAttribute('data-level'), text: hv }); } }
      else if (t === 'img') {
        var m = +b.getAttribute('data-media'); if (!m) { return; }
        var f = b.querySelectorAll('input'); res.push({ t: 'img', media: m, url: b.querySelector('img').getAttribute('src') || '', alt: clean(f[0].value), caption: clean(f[1].value) });
      }
      else if (t === 'table') { res.push(b.tableData()); }
    });
    return res;
  }
  function load(blocks) {
    list.textContent = '';
    (blocks || []).forEach(function (d) {
      if (d.t === 'p') { list.appendChild(makeP(d.text)); }
      else if (d.t === 'h') { list.appendChild(makeH(d.level, d.text)); }
      else if (d.t === 'img') { list.appendChild(makeImg(d)); }
      else if (d.t === 'table') { list.appendChild(makeTable({ rows: d.rows, head: d.head, caption: d.caption })); }
    });
    ensureTail();
  }
  function forServer(blocks) { return blocks.map(function (b) { if (b.t === 'img') { return { t: 'img', media: b.media, alt: b.alt, caption: b.caption }; } return b; }); }

  // ---- add / paste / drop ----
  function addBlock(kind) {
    var anchor = current, b;
    if (kind === 'img') { picker.click(); return; }
    if (kind === 'p') { b = makeP(''); }
    else if (kind === 'table') { b = makeTable({}); }
    else { b = makeH(+kind.slice(1), ''); }
    place(b, anchor); ensureTail(); focusBlock(b); changed();
  }
  app.querySelectorAll('[data-add]').forEach(function (x) { x.addEventListener('click', function () { addBlock(x.getAttribute('data-add')); }); });
  picker.addEventListener('change', function () { var files = Array.prototype.filter.call(picker.files, isImage); picker.value = ''; if (files.length) { addPictures(files); } });

  function pasteParagraphs(area, text) {
    var lines = text.replace(/\r\n?/g, '\n').split('\n').map(function (l) { return l.replace(/\s+/g, ' ').trim(); }).filter(Boolean);
    var before = area.value.slice(0, area.selectionStart), after = area.value.slice(area.selectionEnd), b = area.closest('.blk');
    if (lines.length <= 1) { area.setRangeText(lines[0] || '', area.selectionStart, area.selectionEnd, 'end'); area.dispatchEvent(new Event('input', { bubbles: true })); return; }
    area.value = before + lines[0]; fit(area);
    var anchor = b, lastNode = null;
    lines.slice(1).forEach(function (line, i) {
      var isLast = i === lines.length - 2, sc = shortcut(line);
      var nb = sc ? makeH(sc[0], sc[1]) : makeP(line + (isLast ? '' : ''));
      if (isLast && after.trim() && !sc) { nb.querySelector('textarea').value = line + after; }
      anchor.after(nb); anchor = nb; lastNode = nb;
    });
    if (after.trim() && lastNode && lastNode.getAttribute('data-t') === 'h') { var tail = makeP(after.replace(/^\s+/, '')); lastNode.after(tail); }
    var first = shortcut(area.value); if (first) { area.dispatchEvent(new Event('input', { bubbles: true })); }
    ensureTail(); changed();
  }
  app.addEventListener('paste', function (e) {
    var data = e.clipboardData; if (!data) { return; }
    var html = data.getData('text/html'), plain = data.getData('text/plain');
    var pictures = Array.prototype.filter.call(data.files || [], isImage);
    var anchor = e.target.closest && e.target.closest('.blk');
    if (html && /<table[\s>]/i.test(html)) {
      var grid = htmlTableToGrid(html); if (grid) { e.preventDefault(); addGrid(grid, anchor || current); return; }
    }
    if (pictures.length && !plain) { e.preventDefault(); addPictures(pictures); return; }
    if (plain && /\t/.test(plain) && plain.replace(/\n$/, '').split(/\r?\n/).length >= 2 && plain.replace(/\n$/, '').split(/\r?\n/).every(function (l) { return l.indexOf('\t') >= 0; })) {
      e.preventDefault(); addGrid(parseGrid(plain), anchor || current); return;
    }
    if (e.target.tagName === 'TEXTAREA' && e.target.classList.contains('blk__p') && /\n/.test(plain.replace(/\n+$/, ''))) {
      e.preventDefault(); pasteParagraphs(e.target, plain);
    }
  });
  var depth = 0;
  function hasFiles(e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0; }
  main.addEventListener('dragenter', function (e) { if (hasFiles(e)) { e.preventDefault(); depth++; main.classList.add('dropping'); } });
  main.addEventListener('dragover', function (e) { if (hasFiles(e)) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
  main.addEventListener('dragleave', function () { depth = Math.max(0, depth - 1); if (!depth) { main.classList.remove('dropping'); } });
  main.addEventListener('drop', function (e) {
    if (!hasFiles(e)) { return; }
    e.preventDefault(); e.stopPropagation(); depth = 0; main.classList.remove('dropping');
    var pictures = [];
    Array.prototype.forEach.call(e.dataTransfer.files, function (file) {
      if (isImage(file)) { pictures.push(file); }
      else if (/\.(csv|tsv|txt)$/i.test(file.name) || /^text\//.test(file.type)) {
        var reader = new FileReader();
        reader.onload = function () { var g = csvToGrid(String(reader.result)); if (g.length) { addGrid(g, current); } else { say(file.name + ': ไม่พบข้อมูลตาราง', true); } };
        reader.readAsText(file, 'utf-8');
      } else { say(file.name + ': ลากได้เฉพาะรูปภาพและไฟล์ .csv (ไฟล์ Excel .xlsx ให้ก็อปตารางแล้ววางแทน)', true); }
    });
    if (pictures.length) { addPictures(pictures); }
  });
  ['dragover', 'drop'].forEach(function (name) { document.addEventListener(name, function (e) { if (hasFiles(e)) { e.preventDefault(); } }); });

  // ---- post type and category in the side column ----
  var kindFrom = form.getAttribute('data-kind-from') || '', catBox = document.getElementById('category-box');
  function selectedKind() { var r = form.querySelector('input[name=kind]:checked'); return r ? r.value : (form.querySelector('input[name=kind]') || {}).value; }
  function syncKind() { if (catBox) { var k = selectedKind(); catBox.hidden = !(k === 'research' || k === 'innovations'); } }
  form.querySelectorAll('input[name=kind]').forEach(function (r) { r.addEventListener('change', syncKind); }); syncKind();

  // ---- saved draft in this browser (restored when the page was closed or the login expired) ----
  function store(fn) { try { return fn(window.localStorage); } catch (e) { return null; } }
  var title = document.getElementById('title'), summary = document.getElementById('summary'), timer = null;
  function snapshot() { return JSON.stringify({ title: title.value, summary: summary ? summary.value : '', blocks: serialize() }); }
  var baseline = '';
  function saveDraft() {
    var now = snapshot(); if (now === baseline) { store(function (s) { s.removeItem(KEY); }); return; }
    store(function (s) { s.setItem(KEY, JSON.stringify({ at: Date.now(), data: now })); });
  }
  form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(saveDraft, 700); });
  form.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(saveDraft, 700); });

  // ---- start ----
  var initial = []; try { initial = JSON.parse(app.getAttribute('data-blocks') || '[]'); } catch (e) { initial = []; }
  legacy.hidden = true;
  legacy.querySelectorAll('input,textarea,select,button').forEach(function (x) { x.disabled = true; });
  app.hidden = false; out.disabled = false;
  load(initial);
  baseline = snapshot();
  var marker = store(function (s) { return JSON.parse(s.getItem('e2i-draft-submit') || 'null'); });
  if (/[?&]saved=1/.test(location.search) && marker && Date.now() - marker.at < 5 * 60 * 1000) {
    store(function (s) { s.removeItem('e2i-draft:' + marker.key); s.removeItem('e2i-draft-submit'); });
  }
  var draft = store(function (s) { return JSON.parse(s.getItem(KEY) || 'null'); });
  if (draft && draft.data && draft.data !== baseline) {
    var banner = document.getElementById('draft-banner');
    banner.appendChild(document.createTextNode('พบฉบับที่พิมพ์ค้างไว้ในเบราว์เซอร์นี้ (' + new Date(draft.at).toLocaleString('th-TH') + ') ยังไม่ได้บันทึกขึ้นเว็บ '));
    banner.appendChild(h('button', { type: 'button', className: 'btn btn--small', text: 'กู้คืน', onclick: function () {
      var d = JSON.parse(draft.data); title.value = d.title || ''; if (summary) { summary.value = d.summary || ''; }
      load(d.blocks); banner.hidden = true; changed(); say('กู้คืนฉบับที่พิมพ์ค้างไว้แล้ว ตรวจแล้วกดบันทึก');
    } }));
    banner.appendChild(document.createTextNode(' '));
    banner.appendChild(h('button', { type: 'button', className: 'btn btn--small btn--ghost', text: 'ทิ้ง', onclick: function () { store(function (s) { s.removeItem(KEY); }); banner.hidden = true; } }));
    banner.hidden = false;
  }

  // ---- submit ----
  form.addEventListener('submit', function (e) {
    var op = e.submitter && e.submitter.value, isPreview = op === 'preview';
    if (!isPreview && pending > 0) { e.preventDefault(); if (window.adminForm) { window.adminForm.cancelSaving(); } say('ยังอัปโหลดรูปไม่เสร็จ รอสักครู่แล้วกดบันทึกอีกครั้ง', true); return; }
    if (!isPreview && kindFrom && selectedKind() !== kindFrom) {
      var names = { research: 'งานวิจัย', innovations: 'นวัตกรรม' };
      if (!window.confirm('เปลี่ยนโพสต์นี้จาก' + (names[kindFrom] || kindFrom) + 'เป็น' + (names[selectedKind()] || selectedKind()) + '?\nลิงก์ของโพสต์จะเปลี่ยน ลิงก์เก่าจะพาไปที่ลิงก์ใหม่ให้เอง')) { e.preventDefault(); if (window.adminForm) { window.adminForm.cancelSaving(); } return; }
    }
    var blocks = serialize();
    if (blocks.length > LIMITS.blocks) { e.preventDefault(); if (window.adminForm) { window.adminForm.cancelSaving(); } say('เนื้อหามี ' + blocks.length + ' บล็อก มากเกินไป ลองแบ่งเป็นหลายโพสต์', true); return; }
    out.value = JSON.stringify(forServer(blocks));
    if (!isPreview) { saveDraft(); store(function (s) { s.setItem('e2i-draft-submit', JSON.stringify({ key: form.getAttribute('data-draft-key') || 'new', at: Date.now() })); }); }
  });
  main.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); var go = form.querySelector('.box--publish button[name=op]'); if (go) { go.click(); } }
  });
})();
