<?php
require __DIR__ . '/_boot.php';

$offers = active_offers();
$soon = upcoming_offers(8);
$cats = [];
foreach ($offers as $o) if (!empty($o['cat_id'])) $cats[(int)$o['cat_id']] = $o['cat_name'];
asort($cats);
$to = null;
foreach ($offers as $o) if ($o['valid_to'] && (!$to || $o['valid_to'] > $to)) $to = $o['valid_to'];
$flyer = setting('flyer_pdf');

site_head('Angebote', 'angebote', 'Aktuelle Angebote im ' . setting('shop_name') . ' – Lebensmittel, Getränke und mehr zu Aktionspreisen.');
?>
<section class="page-head">
  <div class="wrap">
    <h1>Unsere Angebote</h1>
    <p class="lead"><?= $offers ? count($offers) . ' aktuelle ' . (count($offers) === 1 ? 'Angebot' : 'Angebote') . ($to ? ' · gültig bis ' . e(date_de($to)) : '') : 'Gerade laufen keine Angebote.' ?></p>
    <?php if ($flyer): ?><a class="btn btn-accent" href="<?= e(upload_url($flyer)) ?>" target="_blank" rel="noopener"><?= e(setting('flyer_title')) ?> (PDF)</a><?php endif; ?>
  </div>
</section>

<section class="section wrap">
  <?php if ($offers): ?>
  <div class="filterbar" id="offerFilter">
    <label class="search"><span class="sr">Angebote durchsuchen</span><input type="search" id="offerSearch" placeholder="Angebot suchen …" autocomplete="off"></label>
    <?php if (count($cats) > 1): ?>
    <div class="chips" role="group" aria-label="Kategorie">
      <button type="button" class="chip on" data-cat="0">Alle</button>
      <?php foreach ($cats as $id => $name): ?><button type="button" class="chip" data-cat="<?= $id ?>"><?= e($name) ?></button><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="grid" id="offerGrid"><?php foreach ($offers as $o) echo offer_card($o); ?></div>
  <p class="empty" id="offerEmpty" hidden>Kein Angebot gefunden.</p>
  <?php else: ?>
  <p class="empty">Aktuell gibt es keine Angebote – schau bald wieder vorbei oder stöbere im <a href="<?= e(u('produkte.php')) ?>">Sortiment</a>.</p>
  <?php endif; ?>
</section>

<?php if ($soon): ?>
<section class="section wrap">
  <div class="section-head"><h2>Demnächst</h2></div>
  <div class="grid"><?php foreach ($soon as $o) echo offer_card($o); ?></div>
</section>
<?php endif; ?>
<?php site_foot();
