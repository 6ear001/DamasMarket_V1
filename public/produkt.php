<?php
require __DIR__ . '/_boot.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT p.*, c.name AS cat_name, c.id AS cat_id, c.group_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.visible = 1');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    site_head('Nicht gefunden', 'produkte');
    echo '<section class="section wrap"><h1>Produkt nicht gefunden</h1><p><a href="' . e(u('produkte.php')) . '">Zurück zum Sortiment</a></p></section>';
    site_foot();
    exit;
}
$offer = active_offer_map()[$id] ?? null;
$rel = db()->prepare('SELECT * FROM products WHERE visible = 1 AND category_id = ? AND id <> ? ORDER BY RANDOM() LIMIT 4');
$rel->execute([(int)$p['category_id'], $id]);
$related = $rel->fetchAll();
$omap = active_offer_map();

site_head($p['name'], 'produkte', trim($p['description']) ?: $p['name'] . ' bei ' . setting('shop_name'));
?>
<section class="section wrap">
  <p class="crumbs"><a href="<?= e(u('produkte.php')) ?>">Sortiment</a><?php if ($p['cat_name']): ?> › <a href="<?= e(u('produkte.php?kat=' . (int)$p['cat_id'])) ?>"><?= e($p['cat_name']) ?></a><?php endif; ?></p>
  <article class="detail">
    <div class="detail-img"><?= img_or_placeholder($p['image'], $p['name'], $p['name'], false) ?>
      <?php if ($offer): ?><span class="badge-sale">Angebot</span><?php elseif ($p['is_new']): ?><span class="badge-new">Neu</span><?php endif; ?></div>
    <div class="detail-body">
      <h1><?= e($p['name']) ?></h1>
      <?php if ($p['name_alt']): ?><p class="alt-name big" dir="auto"><?= e($p['name_alt']) ?></p><?php endif; ?>
      <div class="price-row big">
        <?php if ($offer): ?><span class="price"><?= money((int)$offer['price_cents']) ?></span><?php $old = $offer['old_price_cents'] ?: $p['price_cents']; if ($old): ?><s class="old-price"><?= money((int)$old) ?></s><?php endif; ?>
        <?php elseif ($p['price_cents'] !== null): ?><span class="price"><?= money((int)$p['price_cents']) ?></span>
        <?php else: ?><span class="muted">Preis im Markt</span><?php endif; ?>
      </div>
      <?php if ($offer): ?><p class="validity"><?= e(validity_text($offer['valid_from'], $offer['valid_to'])) ?></p><?php endif; ?>
      <dl class="facts">
        <?php if ($p['unit']): ?><dt>Inhalt</dt><dd><?= e($p['unit']) ?></dd><?php endif; ?>
        <?php if ($p['origin']): ?><dt>Herkunft</dt><dd><?= e($p['origin']) ?></dd><?php endif; ?>
        <?php if ($p['cat_name']): ?><dt>Kategorie</dt><dd><?= e($p['cat_name']) ?></dd><?php endif; ?>
        <dt>Verfügbarkeit</dt><dd><?= (int)$p['in_stock'] ? 'im Markt erhältlich' : 'aktuell nicht verfügbar' ?></dd>
      </dl>
      <?php if (trim($p['description'])): ?><div class="prose"><?= text_to_html($p['description']) ?></div><?php endif; ?>
      <p class="muted small">Alle Preise in Euro inkl. MwSt. Solange der Vorrat reicht – Preis- und Sortimentsänderungen vorbehalten.</p>
    </div>
  </article>
  <?php if ($related): ?>
    <div class="section-head"><h2>Das könnte dir auch gefallen</h2></div>
    <div class="grid"><?php foreach ($related as $r) echo product_card($r, $omap[$r['id']] ?? null); ?></div>
  <?php endif; ?>
</section>
<?php site_foot();
