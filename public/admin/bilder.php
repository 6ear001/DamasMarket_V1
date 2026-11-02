<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    session_write_close(); // blockiert sonst andere Seiten während der Suche
    $act = $_POST['action'] ?? '';
    header('Content-Type: application/json; charset=utf-8');
    @set_time_limit(120);
    if ($act === 'batch') { echo json_encode(image_batch(6) + ['stats' => image_stats()]); exit; }
    if ($act === 'retry') { image_reset_tried(); echo json_encode(['stats' => image_stats()]); exit; }
    http_response_code(400); echo json_encode(['error' => 'Unbekannte Aktion']); exit;
}

$s = image_stats();
admin_head('Produktbilder', 'bilder');
?>
<div class="cards4">
  <div class="stat"><b id="stWith"><?= $s['with_image'] ?></b><span>Produkte mit Bild</span><small>von <?= $s['total'] ?> gesamt</small></div>
  <div class="stat"><b id="stRemaining"><?= $s['remaining'] ?></b><span>noch zu suchen</span><small>haben einen Barcode</small></div>
  <div class="stat<?= $s['not_found'] ? ' warn' : '' ?>"><b id="stNone"><?= $s['not_found'] ?></b><span>ohne Treffer</span><small>bitte selbst hochladen</small></div>
  <div class="stat"><b id="stNoBc"><?= $s['no_barcode'] ?></b><span>ohne Barcode</span><small>keine Suche möglich</small></div>
</div>

<section class="panel" id="imgPanel" data-url="<?= e(u('admin/bilder.php')) ?>" data-csrf="<?= e(csrf_token()) ?>">
  <h2>Bilder automatisch suchen</h2>
  <p>Für jedes Produkt mit Barcode wird in der offenen Produktdatenbank <a href="https://world.openfoodfacts.org" target="_blank" rel="noopener">Open Food Facts</a> nach dem Verpackungsfoto gesucht. Gefundene Bilder werden geprüft, verkleinert und beim Produkt gespeichert. Bereits vorhandene Bilder werden <b>nicht</b> überschrieben.</p>
  <div class="btn-row">
    <button class="btn primary" id="btnStart" type="button"<?= $s['remaining'] ? '' : ' disabled' ?>>Suche starten</button>
    <button class="btn" id="btnStop" type="button" hidden>Anhalten</button>
    <button class="btn" id="btnRetry" type="button"<?= $s['not_found'] ? '' : ' disabled' ?>>Produkte ohne Treffer erneut suchen</button>
  </div>
  <div class="progress" id="progress" hidden><div class="bar" id="bar"></div></div>
  <p class="muted" id="status" role="status"></p>
  <div class="flash err" id="err" hidden></div>
  <ul class="log" id="log"></ul>
  <p class="hint">Die Suche läuft Schritt für Schritt (ca. 1 Sekunde pro Produkt, bei 400 Produkten ca. 7–10 Minuten). Das Fenster dabei offen lassen. Du kannst jederzeit anhalten und später weitermachen.</p>
</section>

<section class="panel">
  <h2>Gut zu wissen</h2>
  <ul class="plain">
    <li><b>Urheberrecht:</b> Die Bilder von Open Food Facts stehen unter der Lizenz <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener">CC BY-SA 4.0</a>. Die geforderte Namensnennung („Produktbilder: Open Food Facts“) wird automatisch im Seitenfuß der Website angezeigt, solange solche Bilder verwendet werden.</li>
    <li><b>Nicht jedes Produkt ist dabei:</b> Gerade arabische und türkische Marken fehlen teilweise. Diese Produkte findest du unter <a href="produkte.php?filter=ohne-bild">Produkte → Ohne Bild</a>; dort lädst du ein eigenes Foto hoch (z. B. mit dem Handy).</li>
    <li><b>Prüfen:</b> Schau die Bilder kurz durch. Ein falsches Bild ersetzt du, indem du beim Produkt ein eigenes hochlädst. Dann verschwindet auch der Quellenhinweis für dieses Produkt.</li>
    <li><b>Kein Barcode?</b> Barcodes, die mit „2“ beginnen, sind interne Waagen-/Kassencodes und haben keine Bilder in Datenbanken.</li>
  </ul>
</section>
<script src="<?= e(asset('js/bilder.js')) ?>" defer></script>
<?php admin_foot();
