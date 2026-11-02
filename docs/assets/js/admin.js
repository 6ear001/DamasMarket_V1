(function () {
  'use strict';

  // Sicherheitsabfrage bei „Löschen“ & Co.
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
  });

  // Mobiles Menü
  var btn = document.getElementById('menuBtn'), side = document.getElementById('sidebar');
  if (btn && side) {
    btn.addEventListener('click', function () {
      var open = side.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (side.classList.contains('open') && !side.contains(e.target) && e.target !== btn) { side.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  }

  // Bildvorschau
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var box = document.querySelector('.img-prev[data-for="' + inp.getAttribute('data-preview') + '"]');
      var f = inp.files && inp.files[0];
      if (!box || !f || !/^image\//.test(f.type)) return;
      var rd = new FileReader();
      rd.onload = function () {
        var img = document.createElement('img');
        img.alt = '';
        img.src = rd.result; // data:-URL (von der Sicherheitsrichtlinie erlaubt)
        box.innerHTML = '';
        box.appendChild(img);
      };
      rd.readAsDataURL(f);
    });
  });

  // Angebot: Felder aus dem Produkt übernehmen
  var sel = document.getElementById('productSelect');
  if (sel) {
    var form = document.getElementById('offerForm');
    var title = form.querySelector('[name=title]'), unit = form.querySelector('[name=unit_text]'), old = form.querySelector('[name=old_price]');
    sel.addEventListener('change', function () {
      var o = sel.options[sel.selectedIndex];
      if (!o || !o.value) return;
      if (!title.value) title.value = o.getAttribute('data-name') || '';
      if (!unit.value) unit.value = o.getAttribute('data-unit') || '';
      if (!old.value) old.value = o.getAttribute('data-price') || '';
    });
  }

  // Meldungen ausblenden
  document.querySelectorAll('.flash.ok').forEach(function (f) { setTimeout(function () { f.style.display = 'none'; }, 6000); });
})();
