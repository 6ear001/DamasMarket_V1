<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'toggle' && $id) $pdo->prepare('UPDATE offers SET active = 1 - active WHERE id = ?')->execute([$id]);
    elseif ($act === 'delete' && $id) {
        $s = $pdo->prepare('SELECT image FROM offers WHERE id = ?'); $s->execute([$id]); delete_upload($s->fetchColumn() ?: null);
        $pdo->prepare('DELETE FROM offers WHERE id = ?')->execute([$id]); flash('Angebot gelöscht.');
    } elseif ($act === 'copy' && $id) {
        $pdo->prepare('INSERT INTO offers (product_id, title, description, image, unit_text, price_cents, old_price_cents, badge, valid_from, valid_to, active, sort) SELECT product_id, title, description, NULL, unit_text, price_cents, old_price_cents, badge, valid_from, valid_to, 0, sort FROM offers WHERE id = ?')->execute([$id]);
        flash('Kopie angelegt (zunächst inaktiv). Bild wird nicht kopiert – das Produktbild wird automatisch verwendet.');
        redirect(u('admin/angebot.php?id=' . $pdo->lastInsertId()));
    } elseif ($act === 'purge') {
        $n = $pdo->prepare('SELECT image FROM offers WHERE valid_to < ?'); $n->execute([today()]);
        foreach ($n->fetchAll(PDO::FETCH_COLUMN) as $img) delete_upload($img ?: null);
        $d = $pdo->prepare('DELETE FROM offers WHERE valid_to < ?'); $d->execute([today()]);
        flash($d->rowCount() . ' abgelaufene Angebote gelöscht.');
    }
    redirect(u('admin/angebote.php') . ($_POST['back'] ?? ''));
}

$filter = $_GET['filter'] ?? '';
$rows = $pdo->query('SELECT o.*, p.name AS pname, p.image AS pimage FROM offers o LEFT JOIN products p ON p.id = o.product_id ORDER BY o.valid_to IS NULL, o.valid_to DESC, o.sort, o.id DESC')->fetchAll();
$today = today();
$state = function (array $o) use ($today): array {
    if (!$o['active']) return ['inaktiv', ''];
    if ($o['valid_to'] && $o['valid_to'] < $today) return ['abgelaufen', 'bad'];
    if ($o['valid_from'] && $o['valid_from'] > $today) return ['geplant', 'gold'];
    return ['läuft', 'ok'];
};
$want = ['abgelaufen' => 'abgelaufen', 'laufend' => 'läuft', 'geplant' => 'geplant'][$filter] ?? null;
if ($want) $rows = array_values(array_filter($rows, fn($o) => $state($o)[0] === $want));
$hasExpired = (bool)array_filter($rows, fn($o) => $state($o)[0] === 'abgelaufen');

admin_head('Angebote', 'angebote');
?>
<div class="toolbar">
  <div class="chips">
    <a class="chip<?= !$filter ? ' on' : '' ?>" href="angebote.php">Alle</a>
    <a class="chip<?= $filter === 'laufend' ? ' on' : '' ?>" href="?filter=laufend">Laufend</a>
    <a class="chip<?= $filter === 'geplant' ? ' on' : '' ?>" href="?filter=geplant">Geplant</a>
    <a class="chip<?= $filter === 'abgelaufen' ? ' on' : '' ?>" href="?filter=abgelaufen">Abgelaufen</a>
  </div>
  <div class="btn-row">
    <?php if ($hasExpired): ?><form method="post"><?= csrf_field() ?><button class="btn danger" name="action" value="purge" data-confirm="Alle abgelaufenen Angebote löschen?">Abgelaufene aufräumen</button></form><?php endif; ?>
    <a class="btn primary" href="angebot.php">+ Neues Angebot</a>
  </div>
</div>
<div class="panel flush">
<?php if ($rows): ?>
<table class="tbl">
  <thead><tr><th></th><th>Angebot</th><th class="r">Preis</th><th>Zeitraum</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): [$label, $cls] = $state($o); $img = $o['image'] ?: $o['pimage']; ?>
    <tr class="<?= $label === 'abgelaufen' || $label === 'inaktiv' ? 'dim' : '' ?>">
      <td class="thumb"><?= $img ? '<img src="' . e(upload_url(thumb_of($img))) . '" alt="">' : '<span class="noimg">–</span>' ?></td>
      <td data-l="Angebot"><a href="angebot.php?id=<?= (int)$o['id'] ?>"><b><?= e($o['title']) ?></b></a><br><small class="muted"><?= e($o['unit_text']) ?><?= $o['pname'] ? ' · Produkt: ' . e($o['pname']) : '' ?></small></td>
      <td class="r" data-l="Preis"><b><?= e(money((int)$o['price_cents'])) ?></b><?php if ($o['old_price_cents']): ?><br><s class="muted"><?= e(money((int)$o['old_price_cents'])) ?></s><?php endif; ?></td>
      <td data-l="Zeitraum"><?= $o['valid_from'] ? e(date_de($o['valid_from'], true)) : 'sofort' ?> – <?= $o['valid_to'] ? e(date_de($o['valid_to'], true)) : 'offen' ?></td>
      <td data-l="Status"><span class="pill <?= $cls ?>"><?= e($label) ?></span></td>
      <td class="acts"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="back" value="<?= $filter ? '?filter=' . e($filter) : '' ?>">
        <a class="btn sm" href="angebot.php?id=<?= (int)$o['id'] ?>">Bearbeiten</a>
        <button class="btn sm" name="action" value="toggle"><?= $o['active'] ? 'Pausieren' : 'Aktivieren' ?></button>
        <button class="btn sm" name="action" value="copy">Kopieren</button>
        <button class="btn sm danger" name="action" value="delete" data-confirm="Angebot „<?= e($o['title']) ?>“ löschen?">Löschen</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?><p class="empty">Keine Angebote in dieser Ansicht. <a href="angebot.php">Neues Angebot anlegen</a></p><?php endif; ?>
</div>
<?php admin_foot();
