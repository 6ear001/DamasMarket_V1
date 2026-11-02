<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'demo_load') { $n = demo_load(); flash("$n Beispiel-Produkte und Beispiel-Angebote wurden angelegt."); }
    if (($_POST['action'] ?? '') === 'demo_remove') { $n = demo_remove(); flash("Beispieldaten entfernt ($n Produkte)."); }
    redirect(u('admin/index.php'));
}

$cnt = fn(string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $s = $pdo->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); })();
$products = $cnt('SELECT COUNT(*) FROM products');
$visible = $cnt('SELECT COUNT(*) FROM products WHERE visible = 1');
$noImage = $cnt("SELECT COUNT(*) FROM products WHERE visible = 1 AND (image IS NULL OR image = '')");
$demo = $cnt('SELECT COUNT(*) FROM products WHERE is_demo = 1') + $cnt('SELECT COUNT(*) FROM offers WHERE is_demo = 1');
$running = count(active_offers());
$ending = $cnt('SELECT COUNT(*) FROM offers WHERE active = 1 AND valid_to BETWEEN ? AND ?', [today(), date('Y-m-d', strtotime('+2 days'))]);
$expired = $cnt('SELECT COUNT(*) FROM offers WHERE valid_to < ?', [today()]);

$imp = page_row('impressum');
$todo = [
    ['Adresse und Telefon eintragen', setting('street') !== '' && setting('phone') !== '', 'einstellungen.php#kontakt'],
    ['Öffnungszeiten prüfen', setting('hours') !== '', 'einstellungen.php#zeiten'],
    ['Logo hochladen (optional – sonst wird das Standard-Logo gezeigt)', setting('logo') !== '', 'einstellungen.php#design'],
    ['Produkte anlegen', $products > 0 && $products > $demo, 'produkt.php'],
    ['Impressum ausfüllen (Pflicht!)', $imp && !str_contains($imp['content'], '['), 'seiten.php?s=impressum'],
    ['Datenschutzerklärung prüfen (Pflicht!)', ($p = page_row('datenschutz')) && !str_contains($p['content'], '[') , 'seiten.php?s=datenschutz'],
];

admin_head('Übersicht', 'dashboard');
?>
<div class="cards4">
  <a class="stat" href="produkte.php"><b><?= $visible ?></b><span>sichtbare Produkte</span><small>von <?= $products ?> gesamt</small></a>
  <a class="stat" href="angebote.php"><b><?= $running ?></b><span>laufende Angebote</span><small><?= $ending ? "$ending enden in 2 Tagen" : 'alles im grünen Bereich' ?></small></a>
  <a class="stat<?= $noImage ? ' warn' : '' ?>" href="produkte.php?filter=ohne-bild"><b><?= $noImage ?></b><span>Produkte ohne Bild</span><small>Bilder verkaufen besser</small></a>
  <a class="stat<?= $expired ? ' warn' : '' ?>" href="angebote.php?filter=abgelaufen"><b><?= $expired ?></b><span>abgelaufene Angebote</span><small>zum Aufräumen</small></a>
</div>

<div class="two-col">
  <section class="panel">
    <h2>Schnellzugriff</h2>
    <div class="btn-row">
      <a class="btn primary" href="produkt.php">+ Neues Produkt</a>
      <a class="btn primary" href="angebot.php">+ Neues Angebot</a>
      <a class="btn" href="einstellungen.php">Einstellungen</a>
      <a class="btn" href="<?= e(u('index.php')) ?>" target="_blank" rel="noopener">Website ansehen ↗</a>
    </div>
    <?php if ($products === 0 || $demo): ?>
    <h3>Beispieldaten</h3>
    <p class="muted">Zum Ausprobieren kannst du Beispiel-Produkte und -Angebote laden – und später mit einem Klick wieder entfernen.</p>
    <form method="post" class="btn-row"><?= csrf_field() ?>
      <?php if ($products === 0): ?><button class="btn" name="action" value="demo_load">Beispieldaten laden</button><?php endif; ?>
      <?php if ($demo): ?><button class="btn danger" name="action" value="demo_remove" data-confirm="Alle Beispiel-Produkte und -Angebote löschen? (Eigene Daten bleiben erhalten.)">Beispieldaten entfernen</button><?php endif; ?>
    </form>
    <?php endif; ?>
  </section>
  <section class="panel">
    <h2>Erste Schritte</h2>
    <ul class="todo">
      <?php foreach ($todo as [$label, $done, $link]): ?>
        <li class="<?= $done ? 'done' : '' ?>"><a href="<?= e($link) ?>"><?= $done ? '✓' : '○' ?> <?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php admin_foot();
