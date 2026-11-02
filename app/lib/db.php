<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0750, true)) {
        http_response_code(500);
        exit('Ordner "data" fehlt und kann nicht angelegt werden. Bitte Schreibrechte prüfen.');
    }
    $new = !is_file(DATA_DIR . '/damas.sqlite');
    $pdo = new PDO('sqlite:' . DATA_DIR . '/damas.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    if ($new) { @chmod(DATA_DIR . '/damas.sqlite', 0640); seed_defaults($pdo); }
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '');
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')));
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, group_name TEXT NOT NULL DEFAULT 'Lebensmittel',
        sort INTEGER NOT NULL DEFAULT 0, visible INTEGER NOT NULL DEFAULT 1);
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, name_alt TEXT NOT NULL DEFAULT '',
        category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
        description TEXT NOT NULL DEFAULT '', origin TEXT NOT NULL DEFAULT '', unit TEXT NOT NULL DEFAULT '',
        price_cents INTEGER, image TEXT, visible INTEGER NOT NULL DEFAULT 1, is_new INTEGER NOT NULL DEFAULT 0,
        in_stock INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0, is_demo INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')));
    CREATE INDEX IF NOT EXISTS idx_products_cat ON products(category_id);
    CREATE TABLE IF NOT EXISTS offers (
        id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
        title TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', image TEXT, unit_text TEXT NOT NULL DEFAULT '',
        price_cents INTEGER NOT NULL, old_price_cents INTEGER, badge TEXT NOT NULL DEFAULT '',
        valid_from TEXT, valid_to TEXT, active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0,
        is_demo INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')));
    CREATE TABLE IF NOT EXISTS pages (
        slug TEXT PRIMARY KEY, title TEXT NOT NULL, content TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT (datetime('now')));
    CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, ts INTEGER NOT NULL);
    ");
    // Spätere Erweiterungen (auch für bestehende Datenbanken)
    add_column($pdo, 'products', 'barcode', "TEXT NOT NULL DEFAULT ''");
    add_column($pdo, 'products', 'image_credit', "TEXT NOT NULL DEFAULT ''");
    add_column($pdo, 'products', 'image_tried', 'TEXT');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode)');
}

function add_column(PDO $pdo, string $table, string $col, string $def): void {
    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    if (!in_array($col, $cols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
}

function seed_defaults(PDO $pdo): void {
    $cats = [
        ['Reis, Bulgur & Getreide', 'Lebensmittel'], ['Nudeln & Teigwaren', 'Lebensmittel'], ['Linsen & Hülsenfrüchte', 'Lebensmittel'],
        ['Konserven & Fertiggerichte', 'Lebensmittel'], ['Öle, Fette & Ghee', 'Lebensmittel'], ['Oliven & Eingelegtes', 'Lebensmittel'],
        ['Tomatenmark & Pasten', 'Lebensmittel'], ['Gewürze & Kräuter', 'Lebensmittel'], ['Milchprodukte & Käse', 'Lebensmittel'],
        ['Fleisch & Wurst (halal)', 'Lebensmittel'], ['Brot & Backwaren', 'Lebensmittel'], ['Backen & Zutaten', 'Lebensmittel'],
        ['Marmelade & Desserts', 'Lebensmittel'], ['Süßwaren & Snacks', 'Lebensmittel'], ['Tee & Kaffee', 'Lebensmittel'],
        ['Obst & Gemüse', 'Lebensmittel'], ['Weitere Lebensmittel', 'Lebensmittel'],
        ['Softdrinks & Säfte', 'Getränke'], ['Energy Drinks', 'Getränke'], ['Ayran, Joghurtdrinks & Wasser', 'Getränke'],
        ['Küche & Haushalt', 'Haushaltswaren'], ['Tee- & Kaffeezubehör', 'Haushaltswaren'],
        ['Geschenkboxen', 'Geschenkartikel'], ['Dekoration & Mitbringsel', 'Geschenkartikel'],
    ];
    $st = $pdo->prepare('INSERT INTO categories (name, group_name, sort) VALUES (?,?,?)');
    foreach ($cats as $i => [$n, $g]) $st->execute([$n, $g, ($i + 1) * 10]);

    $pg = $pdo->prepare('INSERT INTO pages (slug, title, content) VALUES (?,?,?)');
    $pg->execute(['impressum', 'Impressum', "## Angaben gemäß § 5 DDG\n\n**Damas Market**\n[Inhaber/in – Vor- und Nachname]\n[Straße und Hausnummer]\n[PLZ Ort]\n\n## Kontakt\n\nTelefon: [Telefonnummer]\nE-Mail: [E-Mail-Adresse]\n\n## Umsatzsteuer-ID\n\nUmsatzsteuer-Identifikationsnummer gemäß § 27 a UStG: [USt-IdNr.]\n\n## Hinweis\n\nBitte diesen Text vollständig ausfüllen und rechtlich prüfen lassen, bevor die Seite öffentlich geschaltet wird."]);
    $pg->execute(['datenschutz', 'Datenschutzerklärung', "## Datenschutz auf einen Blick\n\nDiese Website speichert keine Daten von Besuchern in Cookies und bindet keine externen Dienste (z. B. Google Fonts, Karten oder Tracking) ein. Der Server speichert technisch bedingt Zugriffsdaten (IP-Adresse, Zeitpunkt) in Logdateien des Hosting-Anbieters.\n\n## Verantwortlicher\n\n[Name und Anschrift wie im Impressum]\n\n## Hinweis\n\nBitte diesen Text rechtlich prüfen und ergänzen (Hosting-Anbieter, Kontaktaufnahme per Telefon/E-Mail/WhatsApp, Rechte der Betroffenen)."]);
    $pg->execute(['ueber-uns', 'Über uns', "## Willkommen bei Damas Market\n\nBei uns findest du arabische und türkische Lebensmittel, Getränke, Haushaltswaren und Geschenkartikel – frisch, authentisch und zu fairen Preisen.\n\n[Diesen Text im Admin-Bereich unter „Seiten“ anpassen.]"]);
}

/* ---------- Einstellungen ---------- */
function default_settings(): array {
    return [
        'shop_name' => 'Damas Market', 'tagline' => 'Arabische & Türkische Lebensmittel',
        'hero_title' => 'Frisch, authentisch, günstig', 'hero_text' => 'Entdecke unsere aktuellen Angebote und das große Sortiment an arabischen und türkischen Spezialitäten.',
        'announcement_on' => '0', 'announcement' => '',
        'street' => '', 'zip' => '', 'city' => '', 'phone' => '', 'email' => '', 'whatsapp' => '', 'map_url' => '',
        'instagram' => '', 'facebook' => '', 'tiktok' => '',
        'hours' => '', 'contact_text' => '',
        'color_bg' => '#12291b', 'color_accent' => '#63c94a', 'color_gold' => '#f0d88e', 'color_cream' => '#fef8e8',
        'logo' => '', 'hero_image' => '', 'flyer_pdf' => '', 'flyer_title' => 'Aktueller Prospekt als PDF',
        'meta_description' => 'Damas Market – arabische und türkische Lebensmittel, Getränke, Haushaltswaren und Geschenkartikel. Aktuelle Angebote und das komplette Sortiment.',
    ];
}

function settings_all(bool $refresh = false): array {
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = default_settings();
        foreach (db()->query('SELECT key, value FROM settings') as $r) $cache[$r['key']] = $r['value'];
    }
    return $cache;
}

function setting(string $key): string { return (string)(settings_all()[$key] ?? ''); }

function set_setting(string $key, string $value): void {
    db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
    settings_all(true);
}

/* ---------- Abfragen ---------- */
const ACTIVE_OFFER_SQL = "o.active = 1 AND (o.valid_from IS NULL OR o.valid_from <= :today) AND (o.valid_to IS NULL OR o.valid_to >= :today)";

function active_offers(int $limit = 0, ?int $categoryId = null): array {
    $sql = "SELECT o.*, p.name AS product_name, p.image AS product_image, p.category_id AS cat_id, c.name AS cat_name
            FROM offers o LEFT JOIN products p ON p.id = o.product_id LEFT JOIN categories c ON c.id = p.category_id
            WHERE " . ACTIVE_OFFER_SQL . " AND (p.id IS NULL OR p.visible = 1)
            ORDER BY o.sort ASC, o.id DESC" . ($limit ? " LIMIT $limit" : '');
    $st = db()->prepare($sql);
    $st->execute([':today' => today()]);
    return $st->fetchAll();
}

function upcoming_offers(int $limit = 8): array {
    $st = db()->prepare("SELECT o.*, p.name AS product_name, p.image AS product_image, c.name AS cat_name
        FROM offers o LEFT JOIN products p ON p.id = o.product_id LEFT JOIN categories c ON c.id = p.category_id
        WHERE o.active = 1 AND o.valid_from > :today AND (p.id IS NULL OR p.visible = 1) ORDER BY o.valid_from, o.sort LIMIT $limit");
    $st->execute([':today' => today()]);
    return $st->fetchAll();
}

/** product_id => Angebot (aktuell) */
function active_offer_map(): array {
    $m = [];
    foreach (active_offers() as $o) if ($o['product_id'] && !isset($m[$o['product_id']])) $m[(int)$o['product_id']] = $o;
    return $m;
}

function categories(bool $onlyVisible = true): array {
    return db()->query('SELECT * FROM categories ' . ($onlyVisible ? 'WHERE visible = 1 ' : '') . 'ORDER BY sort, name')->fetchAll();
}

const GROUPS = ['Lebensmittel', 'Getränke', 'Haushaltswaren', 'Geschenkartikel'];

function page_row(string $slug): ?array {
    $st = db()->prepare('SELECT * FROM pages WHERE slug = ?');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}
