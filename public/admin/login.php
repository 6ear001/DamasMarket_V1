<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';

if (!has_users()) redirect(u('admin/setup.php'));
if (current_user()) redirect(u('admin/index.php'));
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (login_blocked()) {
        $err = 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([trim((string)($_POST['username'] ?? ''))]);
        $row = $st->fetch();
        $ok = password_verify((string)($_POST['password'] ?? ''), $row['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuabcdefghijklmnopqrstuvwxyz01234'); // gleiche Laufzeit, auch bei unbekanntem Benutzer
        if ($row && $ok) {
            login_clear();
            login_user((int)$row['id']);
            redirect(u('admin/index.php'));
        }
        login_failed();
        $err = 'Benutzername oder Passwort ist falsch.';
    }
}
auth_head('Anmelden');
?>
<h1>Anmelden</h1>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="stack-form">
  <?= csrf_field() ?>
  <?= fld('Benutzername', 'username', '', ['required' => true, 'autocomplete' => 'username']) ?>
  <?= fld('Passwort', 'password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
  <button class="btn primary" type="submit">Anmelden</button>
</form>
<p class="muted small"><a href="<?= e(u('index.php')) ?>">← zur Website</a></p>
<?php auth_foot();
