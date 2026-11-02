<?php
require __DIR__ . '/_boot.php';

$q = trim((string)($_GET['q'] ?? ''));
$bereich = in_array($_GET['bereich'] ?? '', GROUPS, true) ? $_GET['bereich'] : '';
$kat = (int)($_GET['kat'] ?? 0);
$page = (int)($_GET['seite'] ?? 1);
$used = array_flip(db()->query('SELECT DISTINCT category_id FROM products WHERE visible = 1 AND category_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
$groupsUsed = [];
foreach (categories() as $c) if (isset($used[$c['id']])) $groupsUsed[$c['group_name']] = true;
$allCats = array_values(array_filter(categories(), fn($c) => isset($used[$c['id']])));
$visibleCats = array_values(array_filter($allCats, fn($c) => !$bereich || $c['group_name'] === $bereich));

$where = ['p.visible = 1', '(c.id IS NULL OR c.visible = 1)'];
$args = [];
if ($bereich) { $where[] = 'c.group_name = :g'; $args[':g'] = $bereich; }
if ($kat) { $where[] = 'p.category_id = :k'; $args[':k'] = $kat; }
if ($q !== '') {
    $where[] = '(p.name LIKE :q ESCAPE \'\\\' OR p.name_alt LIKE :q ESCAPE \'\\\' OR p.description LIKE :q ESCAPE \'\\\' OR c.name LIKE :q ESCAPE \'\\\')';
    $args[':q'] = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%';
}
$w = implode(' AND ', $where);
$cnt = db()->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $w");
$cnt->execute($args);
$pg = paginate((int)$cnt->fetchColumn(), 24, $page);
$st = db()->prepare("SELECT p.* FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $w ORDER BY p.sort, p.name COLLATE NOCASE LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$st->execute($args);
$products = $st->fetchAll();
$omap = active_offer_map();

site_head('Sortiment', 'produkte', 'Unser Sortiment: arabische und türkische Lebensmittel, Getränke, Haushaltswaren und Geschenkartikel.');
?>
<section class="page-head">
  <div class="wrap">
    <h1>Unser Sortiment</h1>
    <p class="lead">Das alles führen wir – <?= (int)$pg['total'] ?> <?= $pg['total'] === 1 ? 'Produkt' : 'Produkte' ?><?= $q !== '' ? ' zu „' . e($q) . '“' : '' ?>.</p>
  </div>
</section>

<section class="section wrap">
  <form class="filterbar" method="get" action="<?= e(u('produkte.php')) ?>" role="search">
    <label class="search"><span class="sr">Produkt suchen</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Produkt suchen, z. B. Tahin, Bulgur …" autocomplete="off"></label>
    <?php if ($bereich): ?><input type="hidden" name="bereich" value="<?= e($bereich) ?>"><?php endif; ?>
    <?php if ($kat): ?><input type="hidden" name="kat" value="<?= $kat ?>"><?php endif; ?>
    <button class="btn btn-dark" type="submit">Suchen</button>
  </form>

  <div class="chips tabs" role="group" aria-label="Bereich">
    <a class="chip<?= !$bereich ? ' on' : '' ?>" href="<?= e(u('produkte.php') . query_with(['bereich' => '', 'kat' => '', 'seite' => ''])) ?>">Alle Bereiche</a>
    <?php foreach (GROUPS as $g): if (!isset($groupsUsed[$g]) && $bereich !== $g) continue; ?><a class="chip<?= $bereich === $g ? ' on' : '' ?>" href="<?= e(u('produkte.php') . query_with(['bereich' => $g, 'kat' => '', 'seite' => ''])) ?>"><?= e($g) ?></a><?php endforeach; ?>
  </div>
  <?php if ($visibleCats): ?>
  <div class="chips sub" role="group" aria-label="Kategorie">
    <a class="chip<?= !$kat ? ' on' : '' ?>" href="<?= e(u('produkte.php') . query_with(['kat' => '', 'seite' => ''])) ?>">Alle</a>
    <?php foreach ($visibleCats as $c): ?><a class="chip<?= $kat === (int)$c['id'] ? ' on' : '' ?>" href="<?= e(u('produkte.php') . query_with(['kat' => $c['id'], 'seite' => ''])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($products): ?>
    <div class="grid"><?php foreach ($products as $p) echo product_card($p, $omap[$p['id']] ?? null); ?></div>
    <?php if ($pg['pages'] > 1): ?>
    <nav class="pager" aria-label="Seiten">
      <?php if ($pg['page'] > 1): ?><a href="<?= e(u('produkte.php') . query_with(['seite' => $pg['page'] - 1])) ?>">← Zurück</a><?php endif; ?>
      <span>Seite <?= $pg['page'] ?> von <?= $pg['pages'] ?></span>
      <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e(u('produkte.php') . query_with(['seite' => $pg['page'] + 1])) ?>">Weiter →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
  <?php else: ?>
    <p class="empty">Nichts gefunden. Probiere einen anderen Suchbegriff oder <a href="<?= e(u('produkte.php')) ?>">zeige alle Produkte</a>.</p>
  <?php endif; ?>
</section>
<?php site_foot();
