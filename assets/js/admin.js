// Admin helpers. Every page works without this file; it only adds small conveniences.
(function () {
  'use strict';
  // data-confirm="…" on a form asks before it is sent.
  document.addEventListener('submit', function (event) {
    var message = event.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) { event.preventDefault(); }
  });
  // Warn when leaving a changed post form without saving.
  var guarded = document.querySelector('form[data-dirty-guard]');
  if (guarded) {
    var dirty = false, saving = false;
    guarded.addEventListener('input', function () { dirty = true; });
    guarded.addEventListener('submit', function () { saving = true; });
    window.addEventListener('beforeunload', function (event) {
      if (dirty && !saving) { event.preventDefault(); event.returnValue = ''; }
    });
  }
  // "แทรกตรงเคอร์เซอร์": puts [รูป N] on its own line in the text, where the cursor is.
  var content = document.getElementById('content');
  document.querySelectorAll('[data-insert]').forEach(function (button) {
    if (!content) { return; }
    button.hidden = false;
    button.addEventListener('click', function () {
      var start = content.selectionStart || 0, end = content.selectionEnd || 0, value = content.value;
      var before = value.slice(0, start).replace(/\s+$/, ''), after = value.slice(end).replace(/^\s+/, '');
      var tag = button.getAttribute('data-insert');
      content.value = (before ? before + '\n\n' : '') + tag + (after ? '\n\n' + after : '\n');
      var pos = (before ? before.length + 2 : 0) + tag.length + 1;
      content.focus(); content.setSelectionRange(pos, pos);
      content.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
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
