// Admin helpers. Every page works without this file; it only adds small conveniences.
(function () {
  'use strict';
  // data-confirm="…" on a form asks before it is sent.
  document.addEventListener('submit', function (event) {
    var message = event.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) { event.preventDefault(); }
  });
  // Warn when leaving a changed post form without saving.
  var guarded = document.querySelector('form[data-dirty-guard]'), dirty = false, saving = false;
  if (guarded) {
    guarded.addEventListener('input', function () { dirty = true; });
    guarded.addEventListener('submit', function (event) { if (!event.defaultPrevented) { saving = true; } });
    window.addEventListener('beforeunload', function (event) {
      if (dirty && !saving) { event.preventDefault(); event.returnValue = ''; }
    });
  }
  var content = document.getElementById('content');

  // ---- Post editor: headings, pictures and tables in the content box (everything here is optional; the form works without it) ----
  if (content && guarded) {
    document.querySelectorAll('[data-js]').forEach(function (el) { el.hidden = false; });
    var statusBox = document.getElementById('upload-status'), pending = 0;
    var csrf = (guarded.querySelector('input[name=csrf]') || {}).value || '';
    var LIMITS = { rows: 100, cols: 12, cell: 500, upload: 1.5 * 1024 * 1024, side: 1920, max: 8 * 1024 * 1024 };

    // Puts a label (or text) on its own paragraph at the cursor. A box that was never clicked has no cursor, so the end is used.
    function insertBlock(tag) {
      var focused = document.activeElement === content, value = content.value;
      var start = focused ? content.selectionStart : value.length, end = focused ? content.selectionEnd : value.length;
      var before = value.slice(0, start).replace(/\s+$/, ''), after = value.slice(end).replace(/^\s+/, '');
      content.value = (before ? before + '\n\n' : '') + tag + (after ? '\n\n' + after : '\n');
      var pos = (before ? before.length + 2 : 0) + tag.length + 1;
      content.focus(); content.setSelectionRange(pos, pos);
      content.dispatchEvent(new Event('input', { bubbles: true }));
    }
    document.addEventListener('click', function (event) {
      var button = event.target.closest ? event.target.closest('[data-insert]') : null;
      if (button) { insertBlock(button.getAttribute('data-insert')); }
    });

    // Heading buttons: add, change or remove "# " at the start of the cursor's line.
    function toggleHeading(level) {
      var value = content.value, pos = content.selectionStart || 0;
      var from = value.lastIndexOf('\n', pos - 1) + 1, to = value.indexOf('\n', pos); if (to < 0) { to = value.length; }
      var line = value.slice(from, to), mark = new Array(level + 1).join('#') + ' ';
      var current = /^(#{1,3})[ \t]+/.exec(line);
      var plain = current ? line.slice(current[0].length) : line;
      var next = current && current[1].length === level ? plain : mark + plain;
      content.value = value.slice(0, from) + next + value.slice(to);
      var caret = from + next.length;
      content.focus(); content.setSelectionRange(caret, caret);
      content.dispatchEvent(new Event('input', { bubbles: true }));
    }
    document.querySelectorAll('[data-heading]').forEach(function (button) {
      button.addEventListener('click', function () { toggleHeading(+button.getAttribute('data-heading')); });
    });

    function say(message, isError) {
      var line = document.createElement('div'); line.textContent = message; if (isError) { line.className = 'err'; }
      statusBox.appendChild(line); statusBox.hidden = false; return line;
    }

    // ---- pictures ----
    function isImage(file) { return /^image\//.test(file.type) || /\.(jpe?g|png|webp|gif)$/i.test(file.name); }
    function loadBitmap(file) {
      if (window.createImageBitmap) { return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return createImageBitmap(file); }); }
      return new Promise(function (resolve, reject) {
        var img = new Image(), url = URL.createObjectURL(file);
        img.onload = function () { URL.revokeObjectURL(url); resolve(img); }; img.onerror = reject; img.src = url;
      });
    }
    // Shrinks a big picture in the browser (longest side 1920 px, WebP or JPEG) so the host's upload limit is not hit.
    function prepareImage(file) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { return Promise.resolve(file); }   // GIF and anything unusual go as they are
      return loadBitmap(file).then(function (bmp) {
        var w = bmp.width, h = bmp.height, scale = Math.min(1, LIMITS.side / Math.max(w, h));
        if (scale === 1 && file.size <= LIMITS.upload) { return file; }
        var canvas = document.createElement('canvas'); canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
        var ctx = canvas.getContext('2d'); ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
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
    function sendImage(blob, name, line) {
      return new Promise(function (resolve, reject) {
        var form = new FormData(); form.append('csrf', csrf); form.append('file', blob, name);
        var xhr = new XMLHttpRequest(); xhr.open('POST', 'upload.php'); xhr.responseType = 'text';
        xhr.upload.onprogress = function (e) { if (e.lengthComputable) { line.textContent = name + ' · กำลังอัปโหลด ' + Math.round(e.loaded / e.total * 100) + '%'; } };
        xhr.onload = function () {
          var data = null; try { data = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
          if (xhr.status === 200 && data && data.id) { resolve(data); }
          else if (data && data.error) { reject(new Error(data.error)); }
          else if (xhr.status === 403) { reject(new Error('หน้านี้หมดอายุ บันทึกเนื้อหาที่พิมพ์ไว้ แล้วโหลดหน้าใหม่ก่อนลองอีกครั้ง')); }
          else if (xhr.status === 413) { reject(new Error('รูปใหญ่เกินที่เซิร์ฟเวอร์รับ ลองใช้รูปที่เล็กลง')); }
          else { reject(new Error('อัปโหลดไม่สำเร็จ (รหัส ' + xhr.status + ')')); }
        };
        xhr.onerror = function () { reject(new Error('อัปโหลดไม่สำเร็จ ตรวจสอบอินเทอร์เน็ตแล้วลองใหม่')); };
        xhr.send(form);
      });
    }
    function addPicture(data, name) {
      var holder = document.getElementById('pics'), tpl = document.getElementById('pic-template');
      var i = +holder.getAttribute('data-next-index'), n = +holder.getAttribute('data-next-n');
      holder.setAttribute('data-next-index', i + 1); holder.setAttribute('data-next-n', n + 1);
      var box = document.createElement('div'); box.innerHTML = tpl.innerHTML.replace(/__I__/g, i).replace(/__N__/g, n);
      var row = box.firstElementChild;
      row.querySelector('[data-thumb]').src = data.url;
      row.querySelector('input[name$="[media]"]').value = data.id;
      var alt = row.querySelector('[data-alt]'); alt.placeholder = 'ใส่คำอธิบายรูปนี้ (ถ้าเว้นว่าง รูปจะไม่แสดงบนเว็บ)'; alt.classList.add('bad');
      alt.addEventListener('input', function () { alt.classList.toggle('bad', alt.value.trim() === ''); });
      row.querySelectorAll('[data-insert]').forEach(function (b) { b.hidden = false; });
      holder.appendChild(row); insertBlock('[รูป ' + n + ']');
      dirty = true;
      return n;
    }
    function handleImages(files) {
      return files.reduce(function (chain, file) {
        return chain.then(function () {
          var name = file.name || 'screenshot.png', line = say(name + ' · กำลังเตรียมรูป…'); pending++;
          return prepareImage(file).then(function (blob) {
            if (blob.size > LIMITS.max) { throw new Error(name + ': ไฟล์ใหญ่เกิน 8 MB ลองใช้รูปที่เล็กลง'); }
            var outName = name.replace(/\.[^.]+$/, '') + (blob.type === 'image/webp' ? '.webp' : blob.type === 'image/jpeg' ? '.jpg' : /\.[^.]+$/.exec(name) || '.png');
            return sendImage(blob, outName, line);
          }).then(function (data) {
            var n = addPicture(data, name); line.textContent = name + ' · อัปโหลดแล้ว เป็น [รูป ' + n + '] อย่าลืมใส่คำอธิบายรูปในรายการ “รูปในเนื้อหา”';
          }).catch(function (error) { line.textContent = error.message; line.className = 'err'; }).then(function () { pending--; });
        });
      }, Promise.resolve());
    }

    // ---- tables ----
    function cleanCell(text) { return String(text).replace(/\s+/g, ' ').trim(); }
    function parseTableText(text) {
      text = text.replace(/\r\n?/g, '\n');
      var sep = text.indexOf('\t') >= 0 ? '\t' : '|', rows = [];
      text.split('\n').forEach(function (line) {
        if (line.replace(/[ \t|]/g, '') === '') { return; }
        if (sep === '|') { if (/^[\s|:\-]+$/.test(line)) { return; } line = line.replace(/^\s*\|/, '').replace(/\|\s*$/, ''); }
        rows.push(line.split(sep).map(cleanCell));
      });
      return rows;
    }
    function tableProblem(rows) {
      var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 0), longest = 0;
      rows.forEach(function (r) { r.forEach(function (c) { longest = Math.max(longest, c.length); }); });
      if (rows.length > LIMITS.rows) { return 'ตารางมี ' + rows.length + ' แถว ใส่ได้ไม่เกิน ' + LIMITS.rows + ' แถว ลองแบ่งเป็นหลายตาราง'; }
      if (cols > LIMITS.cols) { return 'ตารางมี ' + cols + ' คอลัมน์ ใส่ได้ไม่เกิน ' + LIMITS.cols + ' คอลัมน์'; }
      if (longest > LIMITS.cell) { return 'มีช่องที่ยาว ' + longest + ' ตัวอักษร ช่องหนึ่งใส่ได้ไม่เกิน ' + LIMITS.cell + ' ตัวอักษร'; }
      return '';
    }
    function renderPreview(box) {
      var out = box.querySelector('[data-table-preview]'), text = box.querySelector('[data-table-text]').value;
      var head = box.querySelector('[data-table-head]').checked;
      out.textContent = ''; var rows = parseTableText(text); if (!rows.length) { return; }
      var problem = tableProblem(rows);
      if (problem) { var warn = document.createElement('p'); warn.className = 'err'; warn.textContent = problem; out.appendChild(warn); }
      var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 0);
      var wrap = document.createElement('div'); wrap.className = 'tblprev__scroll';
      var table = document.createElement('table'), body = document.createElement('tbody');
      rows.slice(0, 20).forEach(function (r, ri) {
        var tr = document.createElement('tr');
        for (var c = 0; c < cols; c++) { var cell = document.createElement(head && ri === 0 ? 'th' : 'td'); cell.textContent = r[c] || ''; tr.appendChild(cell); }
        (head && ri === 0 ? table.appendChild(document.createElement('thead')) : body).appendChild(tr);
      });
      table.appendChild(body); wrap.appendChild(table); out.appendChild(wrap);
      if (rows.length > 20) { var more = document.createElement('p'); more.className = 'hint'; more.textContent = 'แสดงตัวอย่าง 20 แถวแรก จากทั้งหมด ' + rows.length + ' แถว'; out.appendChild(more); }
    }
    var previewTimer = null;
    function schedulePreview(box) { clearTimeout(previewTimer); previewTimer = setTimeout(function () { renderPreview(box); }, 150); }
    guarded.addEventListener('input', function (event) { var box = event.target.closest && event.target.closest('[data-table]'); if (box) { schedulePreview(box); } });
    guarded.addEventListener('change', function (event) { var box = event.target.closest && event.target.closest('[data-table]'); if (box) { renderPreview(box); } });
    document.querySelectorAll('[data-table]').forEach(renderPreview);
    function newTableBox() {
      var holder = document.getElementById('tables'), tpl = document.getElementById('table-template');
      var i = +holder.getAttribute('data-next-index'), n = +holder.getAttribute('data-next-n');
      holder.setAttribute('data-next-index', i + 1); holder.setAttribute('data-next-n', n + 1);
      var box = document.createElement('div'); box.innerHTML = tpl.innerHTML.replace(/__I__/g, i).replace(/__N__/g, n);
      var el = box.firstElementChild; el.querySelectorAll('[data-insert]').forEach(function (b) { b.hidden = false; });
      holder.appendChild(el); return el;
    }
    function freeTableBox() {
      var boxes = document.querySelectorAll('#tables [data-table]');
      for (var k = 0; k < boxes.length; k++) { if (boxes[k].querySelector('[data-table-text]').value.trim() === '') { return boxes[k]; } }
      return newTableBox();
    }
    document.getElementById('add-table').addEventListener('click', function () { newTableBox().querySelector('textarea').focus(); });
    function addTable(text) {
      var box = freeTableBox(), area = box.querySelector('[data-table-text]');
      area.value = text; box.querySelector('[data-table-head]').checked = true; renderPreview(box);
      var n = box.querySelector('input[name$="[n]"]').value;
      box.querySelectorAll('[data-insert]').forEach(function (b) { b.hidden = false; });
      insertBlock('[ตาราง ' + n + ']'); dirty = true;
      var rows = parseTableText(text), problem = tableProblem(rows);
      say('สร้าง [ตาราง ' + n + '] จาก ' + rows.length + ' แถวแล้ว' + (problem ? ' แต่ ' + problem : ' ตรวจตัวอย่างใต้ช่องเนื้อหา'), !!problem);
    }
    function htmlTableToText(html) {
      var doc = new DOMParser().parseFromString(html, 'text/html'), table = doc.querySelector('table');
      if (!table) { return ''; }
      var lines = [];
      table.querySelectorAll('tr').forEach(function (tr) {
        var cells = [];
        tr.querySelectorAll('th,td').forEach(function (td) {
          cells.push(cleanCell(td.textContent || ''));
          for (var s = 1; s < (parseInt(td.getAttribute('colspan'), 10) || 1); s++) { cells.push(''); }
        });
        if (cells.some(function (c) { return c !== ''; })) { lines.push(cells.join('\t')); }
      });
      return lines.join('\n');
    }
    // A comma / semicolon / tab separated file -> rows joined by Tab
    function csvToText(raw) {
      raw = raw.replace(/^﻿/, '');
      var first = raw.split(/\r?\n/, 1)[0], delim = '\t';
      var counts = { ',': (first.match(/,/g) || []).length, ';': (first.match(/;/g) || []).length, '\t': (first.match(/\t/g) || []).length };
      delim = counts['\t'] >= counts[','] && counts['\t'] >= counts[';'] ? '\t' : (counts[';'] > counts[','] ? ';' : ',');
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
      return rows.filter(function (r) { return r.some(function (c) { return c.trim() !== ''; }); })
        .map(function (r) { return r.map(cleanCell).join('\t'); }).join('\n');
    }
    function looksTabular(text) {
      var lines = text.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; });
      return lines.length >= 2 && lines.every(function (l) { return l.indexOf('\t') >= 0; });
    }

    // ---- paste and drop on the content box ----
    content.addEventListener('paste', function (event) {
      var data = event.clipboardData; if (!data) { return; }
      var html = data.getData('text/html');
      if (html && /<table[\s>]/i.test(html)) { var fromHtml = htmlTableToText(html); if (fromHtml) { event.preventDefault(); addTable(fromHtml); return; } }
      var plain = data.getData('text/plain');
      if (plain && looksTabular(plain)) { event.preventDefault(); addTable(plain.replace(/\r\n?/g, '\n').split('\n').map(function (l) { return l.split('\t').map(cleanCell).join('\t'); }).join('\n').trim()); return; }
      var pictures = Array.prototype.filter.call(data.files || [], isImage);
      if (pictures.length) { event.preventDefault(); handleImages(pictures); }
    });
    var drop = document.getElementById('drop'), depth = 0;
    function hasFiles(event) { return event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types || [], 'Files') >= 0; }
    drop.addEventListener('dragenter', function (event) { if (hasFiles(event)) { event.preventDefault(); depth++; drop.classList.add('dropping'); } });
    drop.addEventListener('dragover', function (event) { if (hasFiles(event)) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; } });
    drop.addEventListener('dragleave', function () { depth = Math.max(0, depth - 1); if (!depth) { drop.classList.remove('dropping'); } });
    drop.addEventListener('drop', function (event) {
      if (!hasFiles(event)) { return; }
      event.preventDefault(); event.stopPropagation(); depth = 0; drop.classList.remove('dropping');
      var files = Array.prototype.slice.call(event.dataTransfer.files), pictures = [];
      files.forEach(function (file) {
        if (isImage(file)) { pictures.push(file); }
        else if (/\.(csv|tsv|txt)$/i.test(file.name) || /^text\//.test(file.type)) {
          var reader = new FileReader();
          reader.onload = function () { var text = csvToText(String(reader.result)); if (text) { addTable(text); } else { say(file.name + ': ไม่พบข้อมูลตาราง', true); } };
          reader.readAsText(file, 'utf-8');
        } else { say(file.name + ': ลากได้เฉพาะรูปภาพและไฟล์ .csv (ไฟล์ Excel .xlsx ให้ก็อปตารางแล้ววางแทน)', true); }
      });
      if (pictures.length) { handleImages(pictures); }
    });
    // A file dropped anywhere else on the page must not make the browser leave the form.
    ['dragover', 'drop'].forEach(function (name) { document.addEventListener(name, function (event) { if (hasFiles(event)) { event.preventDefault(); } }); });
    guarded.addEventListener('submit', function (event) {
      if (pending > 0) { event.preventDefault(); saving = false; say('ยังอัปโหลดรูปไม่เสร็จ รอสักครู่แล้วกดบันทึกอีกครั้ง', true); }
    }, true);
  }
  // Show the chosen picture under a file box before it is uploaded.
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
    var box = document.getElementById(input.getAttribute('data-preview'));
    if (!box) { return; }
    input.addEventListener('change', function () {
      box.textContent = ''; box.hidden = true;
      var file = input.files && input.files[0];
      if (!file) { return; }
      var name = document.createElement('div'); name.textContent = file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)';
      box.appendChild(name);
      if (file.type.indexOf('image/') === 0 && window.URL) {
        var img = document.createElement('img'); img.className = 'thumb'; img.alt = ''; img.src = URL.createObjectURL(file); box.appendChild(img);
      }
      box.hidden = false;
    });
  });
  // Filter a list (pictures, related posts) by the text typed in the box above it.
  document.querySelectorAll('input[data-filter]').forEach(function (box) {
    var list = document.querySelector('[data-filter-list="' + box.getAttribute('data-filter') + '"]');
    if (!list) { return; }
    box.addEventListener('input', function () {
      var q = box.value.toLowerCase();
      list.querySelectorAll('[data-filter-text]').forEach(function (item) {
        item.hidden = q !== '' && item.getAttribute('data-filter-text').indexOf(q) === -1;
      });
    });
  });
})();
