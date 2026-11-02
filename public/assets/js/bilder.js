(function () {
  'use strict';
  var panel = document.getElementById('imgPanel');
  if (!panel) return;
  var url = panel.getAttribute('data-url'), csrf = panel.getAttribute('data-csrf');
  var $ = function (id) { return document.getElementById(id); };
  var running = false, startRemaining = 0, found = 0, none = 0, skipped = 0;

  function post(action) {
    var fd = new FormData();
    fd.append('_csrf', csrf); fd.append('action', action);
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('Serverfehler (' + r.status + ')');
      return r.json();
    });
  }
  function stats(s) {
    $('stWith').textContent = s.with_image; $('stRemaining').textContent = s.remaining; $('stNone').textContent = s.not_found; $('stNoBc').textContent = s.no_barcode;
    $('btnRetry').disabled = !s.not_found;
  }
  function addLog(entries) {
    var ul = $('log');
    entries.forEach(function (e) {
      var li = document.createElement('li');
      li.className = e[1];
      li.textContent = (e[1] === 'found' ? '✓ ' : e[1] === 'none' ? '– ' : '· ') + e[0] + (e[1] === 'found' ? '' : '  (' + e[2] + ')');
      ul.insertBefore(li, ul.firstChild);
    });
    while (ul.children.length > 60) ul.removeChild(ul.lastChild);
  }
  function stop(msg) {
    running = false;
    $('btnStop').hidden = true; $('btnStart').hidden = false;
    $('btnStart').disabled = false;
    $('status').textContent = msg || '';
  }
  function step() {
    if (!running) return stop('Angehalten. Gefunden: ' + found + ', ohne Treffer: ' + none + '.');
    post('batch').then(function (r) {
      found += r.found; none += r.none; skipped += r.skipped;
      addLog(r.log); stats(r.stats);
      var done = startRemaining - r.remaining;
      $('bar').style.width = (startRemaining ? Math.min(100, Math.round(done / startRemaining * 100)) : 100) + '%';
      $('status').textContent = 'Fortschritt: ' + done + ' von ' + startRemaining + ' · gefunden: ' + found + ' · ohne Treffer: ' + none + (skipped ? ' · ohne gültigen Barcode: ' + skipped : '');
      if (r.error) { $('err').hidden = false; $('err').textContent = r.error; return stop('Abgebrochen. ' + found + ' Bilder wurden bereits gespeichert.'); }
      if (r.remaining === 0 || r.processed === 0) { $('bar').style.width = '100%'; return stop('Fertig! ' + found + ' Bilder gefunden, ' + none + ' ohne Treffer.'); }
      step();
    }).catch(function (e) { $('err').hidden = false; $('err').textContent = e.message; stop('Abgebrochen.'); });
  }

  $('btnStart').addEventListener('click', function () {
    $('err').hidden = true; $('log').innerHTML = '';
    startRemaining = parseInt($('stRemaining').textContent, 10) || 0;
    found = none = skipped = 0; running = true;
    $('btnStart').hidden = true; $('btnStop').hidden = false; $('progress').hidden = false; $('bar').style.width = '0%';
    step();
  });
  $('btnStop').addEventListener('click', function () { running = false; $('status').textContent = 'Wird angehalten …'; });
  $('btnRetry').addEventListener('click', function () {
    post('retry').then(function (r) { stats(r.stats); $('btnStart').disabled = !r.stats.remaining; $('status').textContent = r.stats.remaining + ' Produkte werden erneut gesucht. „Suche starten“ klicken.'; });
  });
})();
