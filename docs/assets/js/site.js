(function () {
  'use strict';

  // Mobiles Menü
  var btn = document.querySelector('.nav-toggle');
  var nav = document.getElementById('mainnav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Angebote: Live-Filter (Suche + Kategorie)
  var grid = document.getElementById('offerGrid');
  if (grid) {
    var search = document.getElementById('offerSearch');
    var empty = document.getElementById('offerEmpty');
    var chips = document.querySelectorAll('#offerFilter .chip');
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.offer-card'));
    var cat = '0';
    var apply = function () {
      var q = (search.value || '').toLowerCase().trim();
      var shown = 0;
      cards.forEach(function (c) {
        var ok = (cat === '0' || c.getAttribute('data-cat') === cat) && (!q || c.getAttribute('data-name').indexOf(q) !== -1);
        c.hidden = !ok;
        if (ok) shown++;
      });
      empty.hidden = shown !== 0;
    };
    search.addEventListener('input', apply);
    chips.forEach(function (ch) {
      ch.addEventListener('click', function () {
        chips.forEach(function (x) { x.classList.remove('on'); });
        ch.classList.add('on');
        cat = ch.getAttribute('data-cat');
        apply();
      });
    });
  }
})();
