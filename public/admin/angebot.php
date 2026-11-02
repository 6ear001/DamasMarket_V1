<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
$o = ['product_id' => (int)($_GET['produkt'] ?? 0) ?: null, 'title' => '', 'description' => '', 'image' => null, 'unit_text' => '', 'price_cents' => null, 'old_price_cents' => null, 'badge' => '', 'valid_from' => $monday, 'valid_to' => $sunday, 'active' => 1, 'sort' => 0];
if ($id) {
    $s = $pdo->prepare('SELECT * FROM offers WHERE id = ?'); $s->execute([$id]);
    $o = $s->fetch() ?: null;
    if (!$o) { flash('Angebot nicht gefunden.', 'err'); redirect(u('admin/angebote.php')); }
}
$priceIn = price_input($o['price_cents'] === null ? null : (int)$o['price_cents']);
$oldIn = price_input($o['old_price_cents'] === null ? null : (int)$o['old_price_cents']);
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int)($_POST['product_id'] ?? 0) ?: null;
    $prod = null;
    if ($pid) { $s = $pdo->prepare('SELECT * FROM products WHERE id = ?'); $s->execute([$pid]); $prod = $s->fetch() ?: null; if (!$prod) $pid = null; }
    $priceIn = trim((string)($_POST['price'] ?? '')); $oldIn = trim((string)($_POST['old_price'] ?? ''));
    $price = parse_price($priceIn); $old = parse_price($oldIn);
    $from = valid_date($_POST['valid_from'] ?? ''); $to = valid_date($_POST['valid_to'] ?? '');
    $title = trim((string)($_POST['title'] ?? '')) ?: ($prod['name'] ?? '');
    if ($title === '') $err[] = 'Bitte einen Titel eingeben oder ein Produkt wählen.';
    if ($price === null) $err[] = 'Bitte den Angebotspreis eingeben (z. B. 2,49).';
    if ($oldIn !== '' && $old === null) $err[] = 'Der alte Preis ist ungültig.';
    if ($old !== null && $price !== null && $old <= $price) $err[] = 'Der alte Preis sollte höher als der Angebotspreis sein (oder leer lassen).';
    if ($from && $to && $to < $from) $err[] = 'Das Ende liegt vor dem Beginn.';
    $image = $o['image'];
    if (!$err) {
        try {
            $up = handle_image_upload('image', 'offers');
            if ($up) { delete_upload($image); $image = $up; }
            elseif (!empty($_POST['image_remove'])) { delete_upload($image); $image = null; }
        } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); }
    }
    $cols = ['product_id' => $pid, 'title' => $title, 'description' => trim((string)($_POST['description'] ?? '')), 'image' => $image, 'unit_text' => trim((string)($_POST['unit_text'] ?? '')),
        'price_cents' => $price, 'old_price_cents' => $old, 'badge' => trim((string)($_POST['badge'] ?? '')), 'valid_from' => $from, 'valid_to' => $to,
        'active' => isset($_POST['active']) ? 1 : 0, 'sort' => (int)($_POST['sort'] ?? 0)];
    $o = array_merge($o, $cols);
    if (!$err) {
        if ($id) $pdo->prepare('UPDATE offers SET ' . implode(', ', array_map(fn($c) => "$c = :$c", array_keys($cols))) . ' WHERE id = :id')->execute($cols + ['id' => $id]);
        else $pdo->prepare('INSERT INTO offers (' . implode(',', array_keys($cols)) . ') VALUES (' . implode(',', array_map(fn($c) => ":$c", array_keys($cols))) . ')')->execute($cols);
        flash('Angebot „' . $title . '“ gespeichert.');
        redirect(isset($_POST['save_new']) ? u('admin/angebot.php') : u('admin/angebote.php'));
    }
}

$products = $pdo->query('SELECT id, name, unit, price_cents, image FROM products ORDER BY name COLLATE NOCASE')->fetchAll();
admin_head($id ? 'Angebot bearbeiten' : 'Neues Angebot', 'angebote');
foreach ($err as $m) echo '<div class="flash err">' . e($m) . '</div>';
?>
<form method="post" enctype="multipart/form-data" class="form" id="offerForm">
  <?= csrf_field() ?>
  <div class="two-col wide-left">
    <section class="panel">
      <h2>Angebot</h2>
      <label class="fld"><span>Produkt aus dem Sortiment (optional)</span>
        <select name="product_id" id="productSelect"><option value="">– freies Angebot ohne Produkt –</option>
          <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-unit="<?= e($p['unit']) ?>" data-price="<?= e(price_input($p['price_cents'] === null ? null : (int)$p['price_cents'])) ?>"<?= (int)$o['product_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?><?= $p['unit'] ? ' (' . e($p['unit']) . ')' : '' ?></option><?php endforeach; ?>
        </select><small class="hint">Name, Inhalt und alter Preis werden übernommen – du kannst sie anpassen.</small></label>
      <?= fld('Titel', 'title', $o['title'], ['maxlength' => 120, 'id' => 'f-title', 'placeholder' => 'z. B. Basmati Reis']) ?>
      <div class="row2">
        <?= fld('Inhalt / Menge', 'unit_text', $o['unit_text'], ['maxlength' => 60, 'placeholder' => 'z. B. 1 kg']) ?>
        <?= fld('Aktions-Banderole (optional)', 'badge', $o['badge'], ['maxlength' => 20, 'placeholder' => 'z. B. 2 für 5 €', 'hint' => 'Leer = automatisch „−x %“']) ?>
      </div>
      <div class="row2">
        <?= fld('Angebotspreis in €', 'price', $priceIn, ['inputmode' => 'decimal', 'placeholder' => '2,79', 'required' => true]) ?>
        <?= fld('Alter Preis in € (durchgestrichen)', 'old_price', $oldIn, ['inputmode' => 'decimal', 'placeholder' => '3,49']) ?>
      </div>
      <?= fld('Kurztext (optional)', 'description', $o['description'], ['rows' => 2, 'maxlength' => 200]) ?>
    </section>
    <section class="panel">
      <h2>Zeitraum & Bild</h2>
      <div class="row2">
        <?= fld('Gültig ab', 'valid_from', (string)$o['valid_from'], ['type' => 'date']) ?>
        <?= fld('Gültig bis', 'valid_to', (string)$o['valid_to'], ['type' => 'date']) ?>
      </div>
      <p class="hint">Vorgabe: diese Woche (Mo–So). Leer lassen = ohne Zeitgrenze. Nach Ablauf verschwindet das Angebot automatisch von der Website.</p>
      <?= image_field('Eigenes Bild (optional)', 'image', $o['image'], 'Ohne eigenes Bild wird das Produktbild verwendet.') ?>
      <div class="checks"><?= checkbox('Angebot aktiv', 'active', (bool)$o['active']) ?></div>
      <?= fld('Reihenfolge (kleine Zahl = weiter oben)', 'sort', (string)$o['sort'], ['type' => 'number', 'step' => '1']) ?>
    </section>
  </div>
  <div class="savebar">
    <button class="btn primary" type="submit">Speichern</button>
    <?php if (!$id): ?><button class="btn" type="submit" name="save_new" value="1">Speichern & weiteres Angebot</button><?php endif; ?>
    <a class="btn" href="angebote.php">Abbrechen</a>
  </div>
</form>
<?php admin_foot();
