<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
$me = require_login();
$pdo = db();
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'account') {
        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?'); $st->execute([$me['id']]);
        $user = trim((string)($_POST['username'] ?? ''));
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$st->fetchColumn())) $err[] = 'Das aktuelle Passwort stimmt nicht.';
        elseif (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $user)) $err[] = 'Benutzername: 3–40 Zeichen (Buchstaben, Zahlen, . _ -).';
        elseif ($new !== '' && ($new !== (string)($_POST['new_password2'] ?? ''))) $err[] = 'Die neuen Passwörter sind nicht gleich.';
        elseif ($new !== '' && ($pp = password_problem($new))) $err[] = $pp;
        else {
            try {
                $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$user, $me['id']]);
                if ($new !== '') $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
                session_regenerate_id(true);
                flash('Zugangsdaten gespeichert.'); redirect(u('admin/konto.php'));
            } catch (PDOException $ex) { $err[] = 'Dieser Benutzername ist schon vergeben.'; }
        }
    } elseif ($act === 'backup_db') {
        $tmp = DATA_DIR . '/backup-' . bin2hex(random_bytes(4)) . '.sqlite';
        $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $tmp) . "'");
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="damas-datenbank-' . date('Y-m-d') . '.sqlite"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp); @unlink($tmp); exit;
    } elseif ($act === 'backup_zip' && class_exists('ZipArchive')) {
        $tmp = tempnam(sys_get_temp_dir(), 'damas');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $base = realpath(UPLOAD_DIR);
        if ($base) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && !str_ends_with($f->getFilename(), '.htaccess')) $zip->addFile($f->getPathname(), 'uploads/' . substr($f->getPathname(), strlen($base) + 1));
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="damas-bilder-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp); @unlink($tmp); exit;
    }
}
admin_head('Konto & Backup', 'konto');
foreach ($err as $m) echo '<div class="flash err">' . e($m) . '</div>';
?>
<div class="two-col">
  <form method="post" class="panel form"><?= csrf_field() ?><input type="hidden" name="action" value="account">
    <h2>Zugangsdaten</h2>
    <?= fld('Benutzername', 'username', $_POST['username'] ?? $me['username'], ['required' => true, 'autocomplete' => 'username']) ?>
    <?= fld('Neues Passwort (leer lassen = nicht ändern)', 'new_password', '', ['type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'Mind. 10 Zeichen, Buchstaben und Zahlen']) ?>
    <?= fld('Neues Passwort wiederholen', 'new_password2', '', ['type' => 'password', 'autocomplete' => 'new-password']) ?>
    <?= fld('Aktuelles Passwort (zur Bestätigung)', 'current_password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="btn primary" type="submit">Speichern</button>
  </form>
  <section class="panel">
    <h2>Backup</h2>
    <p class="muted">Sichere regelmäßig deine Daten – zum Beispiel einmal im Monat und vor größeren Änderungen. Zum Wiederherstellen die Datei <code>damas.sqlite</code> in <code>app/data/</code> ersetzen und den Ordner <code>uploads</code> zurückkopieren.</p>
    <form method="post" class="btn-row stack"><?= csrf_field() ?>
      <button class="btn" name="action" value="backup_db">Datenbank herunterladen (Produkte, Angebote, Texte)</button>
      <?php if (class_exists('ZipArchive')): ?><button class="btn" name="action" value="backup_zip">Alle hochgeladenen Bilder als ZIP</button><?php endif; ?>
    </form>
    <p class="small muted">Die Datenbank-Datei enthält auch das verschlüsselte Admin-Passwort – bitte nicht weitergeben.</p>
  </section>
</div>
<?php admin_foot();
