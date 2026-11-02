<?php
declare(strict_types=1);

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $dir = DATA_DIR . '/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    session_name('damas_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => (BASE_URL ?: '') . '/', 'secure' => IS_HTTPS, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_start();
}

function has_users(): bool { return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0; }

function current_user(): ?array {
    session_start_safe();
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return null;
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 28800) { session_destroy(); return null; }
    $_SESSION['last'] = time();
    $st = db()->prepare('SELECT id, username FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect(u('admin/login.php'));
    return $u;
}

function login_user(int $id): void {
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    $_SESSION['last'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void {
    session_start_safe();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time() - 3600, $p['path'], '', $p['secure'], true); }
    session_destroy();
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    session_start_safe();
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(400);
        exit('Ungültige oder abgelaufene Anfrage. Bitte die Seite neu laden und es noch einmal versuchen.');
    }
}

/* ---------- Login-Bremse ---------- */
const LOGIN_MAX = 6;
const LOGIN_WINDOW = 900;

function login_blocked(): bool {
    $pdo = db();
    $pdo->prepare('DELETE FROM login_attempts WHERE ts < ?')->execute([time() - LOGIN_WINDOW]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts >= ?');
    $st->execute([client_ip(), time() - LOGIN_WINDOW]);
    return (int)$st->fetchColumn() >= LOGIN_MAX;
}
function login_failed(): void { db()->prepare('INSERT INTO login_attempts (ip, ts) VALUES (?, ?)')->execute([client_ip(), time()]); }
function login_clear(): void { db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]); }

function password_problem(string $pw): ?string {
    if (mb_strlen($pw) < 10) return 'Das Passwort muss mindestens 10 Zeichen lang sein.';
    if (!preg_match('/\d/', $pw) || !preg_match('/[A-Za-z]/', $pw)) return 'Bitte Buchstaben und Zahlen verwenden.';
    return null;
}

/** Installationscode: liegt in data/setup-code.txt (nur wer Dateizugriff hat, kann den Admin anlegen) */
function setup_code(): string {
    $f = DATA_DIR . '/setup-code.txt';
    if (!is_file($f)) { file_put_contents($f, bin2hex(random_bytes(4))); @chmod($f, 0640); }
    return trim((string)file_get_contents($f));
}
function setup_code_clear(): void { @unlink(DATA_DIR . '/setup-code.txt'); }
