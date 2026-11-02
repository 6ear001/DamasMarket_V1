<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';

if (has_users()) redirect(u('admin/login.php'));
$err = '';
$code = setup_code();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = trim((string)($_POST['username'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    if (!hash_equals($code, trim((string)($_POST['code'] ?? '')))) $err = 'Der Installationscode stimmt nicht.';
    elseif (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $user)) $err = 'Benutzername: 3–40 Zeichen (Buchstaben, Zahlen, . _ -).';
    elseif ($pw !== (string)($_POST['password2'] ?? '')) $err = 'Die beiden Passwörter sind nicht gleich.';
    elseif ($p = password_problem($pw)) $err = $p;
    else {
        db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')->execute([$user, password_hash($pw, PASSWORD_DEFAULT)]);
        setup_code_clear();
        login_user((int)db()->lastInsertId());
        flash('Willkommen! Dein Admin-Zugang ist eingerichtet. Als Nächstes unter „Einstellungen“ Adresse, Öffnungszeiten und Farben eintragen.');
        redirect(u('admin/index.php'));
    }
}
auth_head('Einrichtung');
?>
<h1>Erste Einrichtung</h1>
<p class="muted">Lege deinen Admin-Zugang an. Den <b>Installationscode</b> findest du in der Datei <code>app/data/setup-code.txt</code> auf dem Server (per FTP oder Dateimanager des Hosters).</p>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<form method="post" autocomplete="off" class="stack-form">
  <?= csrf_field() ?>
  <?= fld('Installationscode', 'code', '', ['required' => true, 'autocomplete' => 'off']) ?>
  <?= fld('Benutzername', 'username', $_POST['username'] ?? 'admin', ['required' => true, 'autocomplete' => 'username']) ?>
  <?= fld('Passwort (mind. 10 Zeichen, Buchstaben + Zahlen)', 'password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
  <?= fld('Passwort wiederholen', 'password2', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
  <button class="btn primary" type="submit">Zugang anlegen</button>
</form>
<?php auth_foot();
