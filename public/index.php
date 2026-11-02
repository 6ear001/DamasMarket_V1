<?php
require __DIR__ . '/_boot.php';

$offers = active_offers(8);
$omap = active_offer_map();
$new = db()->query('SELECT * FROM products WHERE visible = 1 AND is_new = 1 ORDER BY id DESC LIMIT 8')->fetchAll();
$counts = [];
foreach (db()->query('SELECT c.group_name g, COUNT(p.id) n FROM categories c JOIN products p ON p.category_id = c.id AND p.visible = 1 WHERE c.visible = 1 GROUP BY c.group_name') as $r) $counts[$r['g']] = (int)$r['n'];
$addr = trim(implode(', ', array_filter([setting('street'), trim(setting('zip') . ' ' . setting('city'))])));
$mapUrl = setting('map_url') ?: ($addr ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr) : '');
$heroImg = setting('hero_image');

$icons = [
    'Lebensmittel' => '<path d="M5 3v8a3 3 0 0 0 3 3v7M8 3v6M11 3v8a3 3 0 0 1-3 3M17 3c-2 2-3 5-3 8h3v10"/>',
    'Getränke' => '<path d="M6 3h12l-1.5 17a1 1 0 0 1-1 .9h-7a1 1 0 0 1-1-.9zM6.5 9h11"/>',
    'Haushaltswaren' => '<path d="M4 10h16l-1.5 9a1 1 0 0 1-1 .8h-11a1 1 0 0 1-1-.8zM8 10V6a4 4 0 0 1 8 0v4"/>',
    'Geschenkartikel' => '<path d="M3 9h18v4H3zM5 13v8h14v-8M12 9v12M12 9c-2-4-6-4-5-1.5C7.5 9 12 9 12 9zM12 9c2-4 6-4 5-1.5C16.5 9 12 9 12 9z"/>',
];

site_head('', 'start');
?>
<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-text">
      <p class="eyebrow"><?= e(setting('tagline')) ?></p>
      <h1><?= e(setting('hero_title')) ?></h1>
      <p class="lead"><?= e(setting('hero_text')) ?></p>
      <div class="btn-row">
        <a class="btn btn-accent" href="<?= e(u('angebote.php')) ?>">Aktuelle Angebote</a>
        <a class="btn btn-ghost" href="<?= e(u('produkte.php')) ?>">Sortiment ansehen</a>
      </div>
    </div>
    <div class="hero-art<?= $heroImg ? ' has-img' : '' ?>">
      <?php if ($heroImg): ?><img src="<?= e(upload_url($heroImg)) ?>" alt="" fetchpriority="high"><?php else: ?>
        <div class="hero-badge"><?= logo_svg() ?><strong><?= count($offers) ?: '—' ?></strong><span><?= count($offers) === 1 ? 'Angebot' : 'Angebote' ?> diese Woche</span></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section wrap">
  <div class="section-head"><h2>Angebote der Woche</h2><a class="more" href="<?= e(u('angebote.php')) ?>">Alle Angebote →</a></div>
  <?php if ($offers): ?>
    <div class="grid"><?php foreach ($offers as $o) echo offer_card($o); ?></div>
  <?php else: ?>
    <p class="empty">Aktuell gibt es keine Angebote – schau bald wieder vorbei.</p>
  <?php endif; ?>
</section>

<section class="section wrap">
  <div class="section-head"><h2>Unser Sortiment</h2></div>
  <div class="tiles">
    <?php foreach (GROUPS as $g): if (!($counts[$g] ?? 0) && array_sum($counts)) continue; /* leere Bereiche ausblenden */ ?>
      <a class="tile" href="<?= e(u('produkte.php?bereich=' . rawurlencode($g))) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$g] ?></svg>
        <span class="tile-name"><?= e($g) ?></span>
        <span class="tile-count"><?= (int)($counts[$g] ?? 0) ?> Produkte</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($new): ?>
<section class="section wrap">
  <div class="section-head"><h2>Neu bei uns</h2><a class="more" href="<?= e(u('produkte.php')) ?>">Zum Sortiment →</a></div>
  <div class="grid"><?php foreach ($new as $p) echo product_card($p, $omap[$p['id']] ?? null); ?></div>
</section>
<?php endif; ?>

<section class="section wrap visit">
  <div class="visit-card">
    <h2>Besuch uns im Markt</h2>
    <?php if ($addr): ?><p class="visit-addr"><?= e($addr) ?></p><?php endif; ?>
    <p class="status-big <?= open_status()['open'] ? 'is-open' : 'is-closed' ?>"><i></i><?= e(open_status()['text']) ?></p>
    <div class="btn-row">
      <?php if ($mapUrl): ?><a class="btn btn-accent" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener">Route planen</a><?php endif; ?>
      <a class="btn btn-outline" href="<?= e(u('kontakt.php')) ?>">Kontakt & Öffnungszeiten</a>
    </div>
  </div>
  <div class="visit-hours"><h3>Öffnungszeiten</h3><?= hours_table(hours()) ?></div>
</section>
<?php site_foot();
