<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();
$pdo = db();
const CORE_PAGES = ['impressum', 'datenschutz', 'ueber-uns'];

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['s'] ?? 'impressum'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? 'save';
    if ($act === 'new') {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') { flash('Bitte einen Seitentitel eingeben.', 'err'); redirect(u('admin/seiten.php')); }
        $base = slugify($title); $s = $base; $i = 2;
        while (page_row($s)) $s = $base . '-' . $i++;
        $pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?,?,?)')->execute([$s, $title, '']);
        flash('Seite angelegt.'); redirect(u('admin/seiten.php?s=' . $s));
    }
    $slug = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['slug'] ?? ''));
    if ($act === 'delete' && !in_array($slug, CORE_PAGES, true)) { $pdo->prepare('DELETE FROM pages WHERE slug = ?')->execute([$slug]); flash('Seite gelöscht.'); redirect(u('admin/seiten.php')); }
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') { flash('Der Titel darf nicht leer sein.', 'err'); redirect(u('admin/seiten.php?s=' . $slug)); }
    $pdo->prepare("UPDATE pages SET title = ?, content = ?, updated_at = datetime('now') WHERE slug = ?")->execute([$title, trim((string)($_POST['content'] ?? '')), $slug]);
    flash('Seite gespeichert.'); redirect(u('admin/seiten.php?s=' . $slug));
}
$pages = $pdo->query('SELECT slug, title FROM pages ORDER BY CASE slug WHEN \'impressum\' THEN 0 WHEN \'datenschutz\' THEN 1 WHEN \'ueber-uns\' THEN 2 ELSE 3 END, title')->fetchAll();
$cur = page_row($slug) ?: page_row('impressum');
admin_head('Seiten', 'seiten');
?>
<div class="chips tabs">
  <?php foreach ($pages as $p): ?><a class="chip<?= $p['slug'] === $cur['slug'] ? ' on' : '' ?>" href="seiten.php?s=<?= e($p['slug']) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
</div>
<form method="post" class="form">
  <?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($cur['slug']) ?>">
  <section class="panel">
    <h2><?= e($cur['title']) ?></h2>
    <?php if (in_array($cur['slug'], ['impressum', 'datenschutz'], true)): ?><div class="flash warn">Pflichtseite: Bitte alle Angaben in [eckigen Klammern] ausfüllen und den Text rechtlich prüfen lassen, bevor die Website öffentlich geht.</div><?php endif; ?>
    <?= fld('Titel', 'title', $cur['title'], ['maxlength' => 80, 'required' => true]) ?>
    <?= fld('Inhalt', 'content', $cur['content'], ['rows' => 18, 'hint' => 'Formatierung: „## Überschrift“, leere Zeile = neuer Absatz, „- “ = Aufzählung, **fett**. Web- und E-Mail-Adressen werden automatisch zu Links.']) ?>
    <p class="small"><a href="<?= e(u('seite.php?s=' . $cur['slug'])) ?>" target="_blank" rel="noopener">Seite ansehen ↗</a></p>
  </section>
  <div class="savebar">
    <button class="btn primary" type="submit" name="action" value="save">Speichern</button>
    <?php if (!in_array($cur['slug'], CORE_PAGES, true)): ?><button class="btn danger right" name="action" value="delete" formnovalidate data-confirm="Diese Seite löschen?">Seite löschen</button><?php endif; ?>
  </div>
</form>
<form method="post" class="panel inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="new">
  <label class="fld grow"><span>Neue Seite anlegen</span><input type="text" name="title" placeholder="z. B. Lieferservice" maxlength="80"></label><button class="btn" type="submit">Anlegen</button>
</form>
<p class="muted small">Neue Seiten sind unter <code>seite.php?s=…</code> erreichbar. Impressum, Datenschutz und „Über uns“ sind bereits im Footer verlinkt.</p>
<?php admin_foot();
