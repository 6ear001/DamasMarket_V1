<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();
$pdo = db();

/* ---------- Export ---------- */
if (isset($_GET['export'])) {
    $rows = $pdo->query('SELECT p.*, c.name AS cat FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.name COLLATE NOCASE')->fetchAll();
    $out = [['Name', 'Name (Arabisch/Türkisch)', 'Kategorie', 'Einheit', 'Preis', 'Herkunft', 'Beschreibung', 'Sichtbar']];
    foreach ($rows as $r) $out[] = [$r['name'], $r['name_alt'], $r['cat'], $r['unit'], price_input($r['price_cents'] === null ? null : (int)$r['price_cents']), $r['origin'], $r['description'], $r['visible'] ? 'ja' : 'nein'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="produkte-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $fh = fopen('php://output', 'w');
    foreach ($out as $line) fputcsv($fh, $line, ';', '"', '');
    exit;
}

/* ---------- Aktionen ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'toggle' && $id) { $pdo->prepare('UPDATE products SET visible = 1 - visible, updated_at = datetime(\'now\') WHERE id = ?')->execute([$id]); }
    elseif ($act === 'delete' && $id) {
        $img = $pdo->prepare('SELECT image FROM products WHERE id = ?'); $img->execute([$id]); delete_upload($img->fetchColumn() ?: null);
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash('Produkt gelöscht.');
    } elseif ($act === 'import') {
        if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) flash('Bitte eine CSV-Datei auswählen.', 'err');
        else {
            $raw = (string)file_get_contents($_FILES['csv']['tmp_name']);
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'); // Excel-Standard
            $lines = preg_split('/\r\n|\n|\r/', trim($raw));
            $delim = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
            $cats = []; foreach ($pdo->query('SELECT id, name FROM categories') as $c) $cats[mb_strtolower($c['name'])] = (int)$c['id'];
            $add = $upd = $skip = 0;
            $pdo->beginTransaction();
            foreach (array_slice($lines, 1) as $line) {
                if (trim($line) === '') continue;
                $f = str_getcsv($line, $delim, '"', '');
                $name = trim($f[0] ?? '');
                if ($name === '') { $skip++; continue; }
                $catName = trim($f[2] ?? '');
                $catId = null;
                if ($catName !== '') {
                    $k = mb_strtolower($catName);
                    if (!isset($cats[$k])) { $pdo->prepare('INSERT INTO categories (name, group_name, sort) VALUES (?, ?, 999)')->execute([$catName, 'Lebensmittel']); $cats[$k] = (int)$pdo->lastInsertId(); }
                    $catId = $cats[$k];
                }
                $unit = trim($f[3] ?? '');
                $vis = !in_array(mb_strtolower(trim($f[7] ?? 'ja')), ['nein', '0', 'no', 'false'], true) ? 1 : 0;
                $price = parse_price($f[4] ?? '');
                $ex = $pdo->prepare('SELECT id FROM products WHERE lower(name) = lower(?) AND lower(unit) = lower(?)'); $ex->execute([$name, $unit]);
                $eid = $ex->fetchColumn();
                $vals = [trim($f[1] ?? ''), $catId, $unit, $price, trim($f[5] ?? ''), trim($f[6] ?? ''), $vis];
                if ($eid) { $pdo->prepare("UPDATE products SET name_alt=?, category_id=?, unit=?, price_cents=?, origin=?, description=?, visible=?, updated_at=datetime('now') WHERE id=?")->execute([...$vals, $eid]); $upd++; }
                else { $pdo->prepare('INSERT INTO products (name_alt, category_id, unit, price_cents, origin, description, visible, name) VALUES (?,?,?,?,?,?,?,?)')->execute([...$vals, $name]); $add++; }
            }
            $pdo->commit();
            flash("Import fertig: $add neu, $upd aktualisiert" . ($skip ? ", $skip Zeilen ohne Namen übersprungen" : '') . '.');
        }
    }
    redirect(u('admin/produkte.php') . ($_POST['back'] ?? ''));
}

/* ---------- Liste ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$kat = (int)($_GET['kat'] ?? 0);
$filter = $_GET['filter'] ?? '';
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = '(p.name LIKE :q ESCAPE \'\\\' OR p.name_alt LIKE :q ESCAPE \'\\\')'; $args[':q'] = '%' . addcslashes($q, '%_\\') . '%'; }
if ($kat) { $where[] = 'p.category_id = :k'; $args[':k'] = $kat; }
if ($filter === 'ohne-bild') $where[] = "(p.image IS NULL OR p.image = '')";
if ($filter === 'versteckt') $where[] = 'p.visible = 0';
$w = implode(' AND ', $where);
$c = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $w"); $c->execute($args);
$pg = paginate((int)$c->fetchColumn(), 30, (int)($_GET['seite'] ?? 1));
$st = $pdo->prepare("SELECT p.*, c.name AS cat FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $w ORDER BY p.name COLLATE NOCASE LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$st->execute($args);
$rows = $st->fetchAll();
$omap = active_offer_map();
$back = query_with([]);

admin_head('Produkte', 'produkte');
?>
<div class="toolbar">
  <form method="get" class="inline-form">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Produkt suchen …">
    <select name="kat"><option value="">Alle Kategorien</option><?php foreach (categories(false) as $cat): ?><option value="<?= $cat['id'] ?>"<?= $kat === (int)$cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select>
    <select name="filter"><option value="">Alle</option><option value="ohne-bild"<?= $filter === 'ohne-bild' ? ' selected' : '' ?>>Ohne Bild</option><option value="versteckt"<?= $filter === 'versteckt' ? ' selected' : '' ?>>Versteckt</option></select>
    <button class="btn" type="submit">Filtern</button>
  </form>
  <a class="btn primary" href="produkt.php">+ Neues Produkt</a>
</div>

<div class="panel flush">
<?php if ($rows): ?>
<table class="tbl">
  <thead><tr><th></th><th>Produkt</th><th>Kategorie</th><th class="r">Preis</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['visible'] ? '' : 'dim' ?>">
      <td class="thumb"><?= $r['image'] ? '<img src="' . e(upload_url(thumb_of($r['image']))) . '" alt="">' : '<span class="noimg">–</span>' ?></td>
      <td data-l="Produkt"><a href="produkt.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['name']) ?></b></a><?php if ($r['name_alt']): ?> <span class="muted" dir="auto"><?= e($r['name_alt']) ?></span><?php endif; ?><br><small class="muted"><?= e($r['unit']) ?></small></td>
      <td data-l="Kategorie"><?= e($r['cat'] ?? '–') ?></td>
      <td class="r" data-l="Preis"><?= $r['price_cents'] !== null ? e(money((int)$r['price_cents'])) : '–' ?><?php if (isset($omap[$r['id']])): ?><br><span class="pill gold">Angebot <?= e(money((int)$omap[$r['id']]['price_cents'])) ?></span><?php endif; ?></td>
      <td data-l="Status"><?= $r['visible'] ? '<span class="pill ok">sichtbar</span>' : '<span class="pill">versteckt</span>' ?><?= $r['is_new'] ? ' <span class="pill new">neu</span>' : '' ?><?= !$r['in_stock'] ? ' <span class="pill bad">nicht verfügbar</span>' : '' ?><?= $r['is_demo'] ? ' <span class="pill">Beispiel</span>' : '' ?></td>
      <td class="acts">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
          <a class="btn sm" href="produkt.php?id=<?= (int)$r['id'] ?>">Bearbeiten</a>
          <button class="btn sm" name="action" value="toggle"><?= $r['visible'] ? 'Verstecken' : 'Zeigen' ?></button>
          <button class="btn sm danger" name="action" value="delete" data-confirm="„<?= e($r['name']) ?>“ endgültig löschen?">Löschen</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if ($pg['pages'] > 1): ?><nav class="pager"><?php if ($pg['page'] > 1): ?><a href="<?= e(query_with(['seite' => $pg['page'] - 1])) ?>">← Zurück</a><?php endif; ?> <span>Seite <?= $pg['page'] ?> / <?= $pg['pages'] ?> · <?= $pg['total'] ?> Produkte</span> <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e(query_with(['seite' => $pg['page'] + 1])) ?>">Weiter →</a><?php endif; ?></nav><?php endif; ?>
<?php else: ?>
  <p class="empty">Keine Produkte gefunden. <a href="produkt.php">Erstes Produkt anlegen</a> oder unten per CSV importieren.</p>
<?php endif; ?>
</div>

<details class="panel">
  <summary><b>Excel / CSV: Import & Export</b></summary>
  <p class="muted">Spalten (Semikolon-getrennt, erste Zeile = Überschrift): <code>Name; Name (Arabisch/Türkisch); Kategorie; Einheit; Preis; Herkunft; Beschreibung; Sichtbar</code>. Vorhandene Produkte (gleicher Name + Einheit) werden aktualisiert, neue Kategorien automatisch angelegt. Bilder werden einzeln am Produkt hochgeladen.</p>
  <form method="post" enctype="multipart/form-data" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="import">
    <input type="file" name="csv" accept=".csv,text/csv" required><button class="btn" type="submit">CSV importieren</button>
    <a class="btn" href="produkte.php?export=1">Alle Produkte als CSV laden</a></form>
</details>
<?php admin_foot();
