(function () {
  'use strict';

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  var rows = Array.prototype.slice.call(document.querySelectorAll('#tbl tbody tr'));
  var chips = document.getElementById('chips');
  var q = document.getElementById('q');
  var count = document.getElementById('count');
  var none = document.getElementById('none');
  if (!rows.length || !chips || !q) return;

  chips.hidden = false;
  var filter = 'all';

  function apply() {
    var term = q.value.trim().toLowerCase();
    var shown = 0;
    rows.forEach(function (tr) {
      var ok = (filter === 'all' || tr.getAttribute('data-status') === filter) &&
               (term === '' || tr.getAttribute('data-hay').indexOf(term) !== -1);
      tr.hidden = !ok;
      if (ok) shown++;
    });
    count.textContent = 'Showing ' + shown + ' of ' + rows.length;
    if (none) none.hidden = shown !== 0;
  }

  chips.addEventListener('click', function (e) {
    var b = e.target.closest('.chip');
    if (!b) return;
    filter = b.getAttribute('data-filter');
    chips.querySelectorAll('.chip').forEach(function (c) { c.classList.toggle('is-on', c === b); });
    apply();
  });
  q.addEventListener('input', apply);
})();
