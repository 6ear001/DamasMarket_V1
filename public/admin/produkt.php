<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$p = ['name' => '', 'name_alt' => '', 'category_id' => (int)($_GET['kat'] ?? 0) ?: null, 'description' => '', 'origin' => '', 'unit' => '', 'barcode' => '', 'price_cents' => null, 'image' => null, 'image_credit' => '', 'visible' => 1, 'is_new' => 0, 'in_stock' => 1, 'sort' => 0];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?'); $st->execute([$id]);
    $p = $st->fetch() ?: null;
    if (!$p) { flash('Produkt nicht gefunden.', 'err'); redirect(u('admin/produkte.php')); }
}
$err = [];
$priceIn = price_input($p['price_cents'] === null ? null : (int)$p['price_cents']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && $id) {
        delete_upload($p['image']);
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash('Produkt gelöscht.'); redirect(u('admin/produkte.php'));
    }
    $new = [
        'name' => trim((string)($_POST['name'] ?? '')), 'name_alt' => trim((string)($_POST['name_alt'] ?? '')),
        'category_id' => (int)($_POST['category_id'] ?? 0) ?: null, 'description' => trim((string)($_POST['description'] ?? '')),
        'origin' => trim((string)($_POST['origin'] ?? '')), 'unit' => trim((string)($_POST['unit'] ?? '')),
        'barcode' => preg_replace('/\s+/', '', (string)($_POST['barcode'] ?? '')),
        'visible' => isset($_POST['visible']) ? 1 : 0, 'is_new' => isset($_POST['is_new']) ? 1 : 0, 'in_stock' => isset($_POST['in_stock']) ? 1 : 0,
        'sort' => (int)($_POST['sort'] ?? 0),
    ];
    $priceIn = trim((string)($_POST['price'] ?? ''));
    $price = parse_price($priceIn);
    if ($new['name'] === '') $err[] = 'Bitte einen Namen eingeben.';
    if ($priceIn !== '' && $price === null) $err[] = 'Der Preis ist ungültig (z. B. 2,49).';
    if ($new['barcode'] !== '' && !preg_match('/^\d{6,20}$/', $new['barcode'])) $err[] = 'Der Barcode darf nur aus Ziffern bestehen.';
    $image = $p['image']; $credit = $p['image_credit'];
    if (!$err) {
        try {
            $up = handle_image_upload('image', 'products');
            if ($up) { delete_upload($image); $image = $up; $credit = ''; }   // eigenes Bild: kein Fremd-Quellenhinweis mehr
            elseif (!empty($_POST['image_remove'])) { delete_upload($image); $image = null; $credit = ''; }
        } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); }
    }
    $p = array_merge($p, $new, ['price_cents' => $price, 'image' => $image, 'image_credit' => $credit]);
    if (!$err) {
        $cols = array_merge($new, ['price_cents' => $price, 'image' => $image, 'image_credit' => $credit, 'image_tried' => $image ? today() : null]);
        if ($id) {
            $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($cols)));
            $pdo->prepare("UPDATE products SET $set, updated_at = datetime('now') WHERE id = :id")->execute(array_merge($cols, ['id' => $id]));
        } else {
            $pdo->prepare('INSERT INTO products (' . implode(',', array_keys($cols)) . ') VALUES (' . implode(',', array_map(fn($c) => ":$c", array_keys($cols))) . ')')->execute($cols);
            $id = (int)$pdo->lastInsertId();
        }
        flash('„' . $new['name'] . '“ gespeichert.');
        redirect(isset($_POST['save_new']) ? u('admin/produkt.php?kat=' . (int)$new['category_id']) : u('admin/produkte.php'));
    }
}

$cats = categories(false);
admin_head($id ? 'Produkt bearbeiten' : 'Neues Produkt', 'produkte');
foreach ($err as $m) echo '<div class="flash err">' . e($m) . '</div>';
?>
<form method="post" enctype="multipart/form-data" class="form">
  <?= csrf_field() ?>
  <div class="two-col wide-left">
    <section class="panel">
      <h2>Produkt</h2>
      <?= fld('Name', 'name', $p['name'], ['required' => true, 'maxlength' => 120, 'placeholder' => 'z. B. Basmati Reis']) ?>
      <?= fld('Name in Arabisch / Türkisch (optional)', 'name_alt', $p['name_alt'], ['maxlength' => 120, 'dir' => 'auto', 'placeholder' => 'أرز بسمتي']) ?>
      <div class="row2">
        <label class="fld"><span>Kategorie</span><select name="category_id"><option value="">– ohne –</option>
          <?php $g = null; foreach ($cats as $c): if ($g !== $c['group_name']) { if ($g !== null) echo '</optgroup>'; echo '<optgroup label="' . e($c['group_name']) . '">'; $g = $c['group_name']; } ?>
            <option value="<?= $c['id'] ?>"<?= (int)$p['category_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?><?= $c['visible'] ? '' : ' (versteckt)' ?></option>
          <?php endforeach; if ($g !== null) echo '</optgroup>'; ?></select></label>
        <?= fld('Inhalt / Einheit', 'unit', $p['unit'], ['placeholder' => 'z. B. 500 g', 'maxlength' => 40]) ?>
      </div>
      <div class="row2">
        <?= fld('Preis in € (leer = „Preis im Markt“)', 'price', $priceIn, ['inputmode' => 'decimal', 'placeholder' => '2,49']) ?>
        <?= fld('Herkunft', 'origin', $p['origin'], ['placeholder' => 'z. B. Türkei', 'maxlength' => 60]) ?>
      </div>
      <?= fld('Barcode / EAN (für die automatische Bildsuche)', 'barcode', $p['barcode'], ['inputmode' => 'numeric', 'maxlength' => 20, 'placeholder' => '4260193516047']) ?>
      <?= fld('Beschreibung (optional)', 'description', $p['description'], ['rows' => 5, 'hint' => 'Leere Zeile = neuer Absatz. „- “ am Zeilenanfang = Aufzählung. **fett**']) ?>
    </section>
    <section class="panel">
      <h2>Bild & Sichtbarkeit</h2>
      <?= image_field('Produktbild', 'image', $p['image'], 'JPG, PNG oder WebP, am besten quadratisch oder 4:3 mit hellem Hintergrund. Wird automatisch verkleinert.' . ($p['image_credit'] ? ' Dieses Bild stammt automatisch von ' . $p['image_credit'] . ' – ein eigener Upload ersetzt es.' : '')) ?>
      <div class="checks">
        <?= checkbox('Auf der Website sichtbar', 'visible', (bool)$p['visible']) ?>
        <?= checkbox('Als „Neu“ markieren', 'is_new', (bool)$p['is_new']) ?>
        <?= checkbox('Im Markt verfügbar', 'in_stock', (bool)$p['in_stock'], '(sonst Hinweis „aktuell nicht verfügbar“)') ?>
      </div>
      <?= fld('Reihenfolge (kleine Zahl = weiter oben)', 'sort', (string)$p['sort'], ['type' => 'number', 'step' => '1']) ?>
    </section>
  </div>
  <div class="savebar">
    <button class="btn primary" type="submit">Speichern</button>
    <?php if (!$id): ?><button class="btn" type="submit" name="save_new" value="1">Speichern & nächstes Produkt</button><?php endif; ?>
    <a class="btn" href="produkte.php">Abbrechen</a>
    <?php if ($id): ?><button class="btn danger right" type="submit" name="action" value="delete" formnovalidate data-confirm="Dieses Produkt endgültig löschen?">Löschen</button><?php endif; ?>
  </div>
</form>
<?php admin_foot();
