(function () {
  'use strict';

  var showpw = document.getElementById('showpw');
  if (showpw) {
    showpw.addEventListener('change', function () {
      document.getElementById('password').type = showpw.checked ? 'text' : 'password';
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  var qr = document.getElementById('qr');
  if (qr && window.qrcode) {
    var code = window.qrcode(0, 'M');
    code.addData(qr.getAttribute('data-url'));
    code.make();
    var draw = function (canvas, px) {
      var n = code.getModuleCount(), quiet = 4, cell = Math.floor(px / (n + quiet * 2));
      var size = cell * (n + quiet * 2);
      canvas.width = size; canvas.height = size;
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, size, size);
      ctx.fillStyle = '#000000';
      for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) {
        if (code.isDark(r, c)) ctx.fillRect((c + quiet) * cell, (r + quiet) * cell, cell, cell);
      }
    };
    draw(qr, 440);
    var dl = document.getElementById('dl-qr');
    if (dl) dl.addEventListener('click', function () {
      var big = document.createElement('canvas');
      draw(big, 1200);
      var a = document.createElement('a');
      a.href = big.toDataURL('image/png');
      a.download = 'programme-qr.png';
      document.body.appendChild(a); a.click(); a.remove();
    });
  }

  var copyBtn = document.getElementById('copy-link');
  if (copyBtn) copyBtn.addEventListener('click', function () {
    var input = document.getElementById('prog-url');
    var done = function () { copyBtn.textContent = 'Copied'; setTimeout(function () { copyBtn.textContent = 'Copy link'; }, 1800); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(input.value).then(done, function () { input.select(); document.execCommand('copy'); done(); });
    } else { input.select(); document.execCommand('copy'); done(); }
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
