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
  window.adminForm = { markDirty: function () { dirty = true; }, markSaving: function () { saving = true; }, cancelSaving: function () { saving = false; } };
  if (guarded) {
    guarded.addEventListener('input', function () { dirty = true; });
    guarded.addEventListener('submit', function (event) { if (!event.defaultPrevented) { saving = true; } });
    window.addEventListener('beforeunload', function (event) {
      if (dirty && !saving) { event.preventDefault(); event.returnValue = ''; }
    });
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
