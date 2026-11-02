<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['delete'])) {
        $id = (int)$_POST['delete'];
        $n = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?'); $n->execute([$id]);
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        flash('Kategorie gelöscht. ' . ((int)$n->fetchColumn() ? 'Die zugehörigen Produkte sind jetzt „ohne Kategorie“.' : ''));
    } else {
        $upd = $pdo->prepare('UPDATE categories SET name = ?, group_name = ?, sort = ?, visible = ? WHERE id = ?');
        foreach (($_POST['cat'] ?? []) as $id => $c) {
            $name = trim((string)($c['name'] ?? ''));
            if ($name === '') continue;
            $group = in_array($c['group'] ?? '', GROUPS, true) ? $c['group'] : GROUPS[0];
            $upd->execute([$name, $group, (int)($c['sort'] ?? 0), isset($c['visible']) ? 1 : 0, (int)$id]);
        }
        $nn = trim((string)($_POST['new']['name'] ?? ''));
        if ($nn !== '') {
            $group = in_array($_POST['new']['group'] ?? '', GROUPS, true) ? $_POST['new']['group'] : GROUPS[0];
            $pdo->prepare('INSERT INTO categories (name, group_name, sort, visible) VALUES (?,?,?,1)')->execute([$nn, $group, (int)($_POST['new']['sort'] ?? 999)]);
        }
        flash('Kategorien gespeichert.');
    }
    redirect(u('admin/kategorien.php'));
}
$cats = categories(false);
$counts = [];
foreach ($pdo->query('SELECT category_id, COUNT(*) n FROM products GROUP BY category_id') as $r) $counts[(int)$r['category_id']] = (int)$r['n'];
$opts = fn(string $sel) => implode('', array_map(fn($g) => '<option' . ($g === $sel ? ' selected' : '') . '>' . e($g) . '</option>', GROUPS));
admin_head('Kategorien', 'kategorien');
?>
<p class="muted">Die vier Bereiche (Lebensmittel, Getränke, Haushaltswaren, Geschenkartikel) erscheinen auf der Startseite. Kategorien gliedern das Sortiment darunter.</p>
<form method="post" class="panel flush">
  <?= csrf_field() ?>
  <table class="tbl">
    <thead><tr><th>Name</th><th>Bereich</th><th>Reihenfolge</th><th>Sichtbar</th><th class="r">Produkte</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): $i = (int)$c['id']; ?>
      <tr>
        <td data-l="Name"><input type="text" name="cat[<?= $i ?>][name]" value="<?= e($c['name']) ?>" maxlength="60" required></td>
        <td data-l="Bereich"><select name="cat[<?= $i ?>][group]"><?= $opts($c['group_name']) ?></select></td>
        <td data-l="Reihenfolge"><input type="number" name="cat[<?= $i ?>][sort]" value="<?= (int)$c['sort'] ?>" step="1" class="narrow"></td>
        <td data-l="Sichtbar"><input type="checkbox" name="cat[<?= $i ?>][visible]" value="1"<?= $c['visible'] ? ' checked' : '' ?>></td>
        <td class="r" data-l="Produkte"><a href="produkte.php?kat=<?= $i ?>"><?= $counts[$i] ?? 0 ?></a></td>
        <td class="acts"><button class="btn sm danger" name="delete" value="<?= $i ?>" formnovalidate data-confirm="Kategorie „<?= e($c['name']) ?>“ löschen?">Löschen</button></td>
      </tr>
    <?php endforeach; ?>
      <tr class="new-row">
        <td data-l="Neu"><input type="text" name="new[name]" placeholder="+ Neue Kategorie" maxlength="60"></td>
        <td><select name="new[group]"><?= $opts(GROUPS[0]) ?></select></td>
        <td><input type="number" name="new[sort]" value="999" step="1" class="narrow"></td><td></td><td></td><td></td>
      </tr>
    </tbody>
  </table>
  <div class="savebar inner"><button class="btn primary" type="submit">Speichern</button></div>
</form>
<?php admin_foot();
