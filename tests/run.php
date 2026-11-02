<?php
// Ausführen mit:  php tests/run.php
declare(strict_types=1);
define('APP_DIR', dirname(__DIR__) . '/app');
define('PUBLIC_DIR', dirname(__DIR__) . '/public');
define('BASE_URL', '');
define('IS_HTTPS', false);
$tmp = sys_get_temp_dir() . '/damas-test-' . bin2hex(random_bytes(4));
putenv('DAMAS_DATA_DIR=' . $tmp);
require APP_DIR . '/bootstrap.php';

$n = 0;
function check(string $name, $actual, $expected): void {
    global $n;
    if ($actual !== $expected) { fwrite(STDERR, "FEHLER – $name\n  erwartet: " . var_export($expected, true) . "\n  erhalten: " . var_export($actual, true) . "\n"); exit(1); }
    $n++; echo "ok - $name\n";
}

check('Preis parsen: Komma', parse_price('2,49'), 249);
check('Preis parsen: Punkt', parse_price('2.5'), 250);
check('Preis parsen: Tausender', parse_price('1.234,50 €'), 123450);
check('Preis parsen: leer', parse_price('  '), null);
check('Preis parsen: Text', parse_price('abc'), null);
check('Preis parsen: negativ', parse_price('-1'), null);
check('Preis parsen: 0,1 + 0,2 Rundung', parse_price('1,15'), 115);
check('Preis anzeigen', money(1234), '12,34 €');
check('Preis anzeigen 1.000', money(100000), '1.000,00 €');
check('Preis-Eingabe', price_input(250), '2,50');
check('Rabatt', discount_percent(349, 279), 20);
check('Rabatt: kein Vorteil', discount_percent(100, 100), null);
check('Rabatt: ohne alten Preis', discount_percent(null, 100), null);
check('Datum prüfen ok', valid_date('2026-02-28'), '2026-02-28');
check('Datum prüfen 30. Feb', valid_date('2026-02-30'), null);
check('Datum prüfen Müll', valid_date('morgen'), null);
check('Datum deutsch', date_de('2026-11-08'), 'So., 08.11.');
check('Farbe ok', valid_color('#AABBCC', '#000000'), '#aabbcc');
check('Farbe Angriff', valid_color('red;}</style><script>', '#12291b'), '#12291b');
check('Slug', slugify('Über uns & Café'), 'ueber-uns-cafe');
check('Markdown: XSS wird escaped', text_to_html('<script>x</script>'), '<p>&lt;script&gt;x&lt;/script&gt;</p>');
check('Markdown: Überschrift ab h2', text_to_html('## Titel'), '<h2>Titel</h2>');
check('Markdown: Liste', text_to_html("- a\n- b"), '<ul><li>a</li><li>b</li></ul>');
check('Markdown: javascript-Link wird nicht verlinkt', str_contains(text_to_html('javascript:alert(1)'), '<a '), false);

// Öffnungszeiten (Standard: Mo–Sa 09–20, So zu)
$mo = new DateTimeImmutable('2026-11-02 12:00'); // Montag
check('Wochentag', wd_key($mo), 'mo');
check('geöffnet', open_status($mo), ['open' => true, 'text' => 'Jetzt geöffnet · bis 20:00 Uhr']);
check('vor Öffnung', open_status(new DateTimeImmutable('2026-11-02 07:30'))['text'], 'Geschlossen · öffnet heute um 09:00 Uhr');
check('nach Schluss', open_status(new DateTimeImmutable('2026-11-02 20:00'))['text'], 'Geschlossen · öffnet morgen um 09:00 Uhr');
check('Samstagabend → Montag', open_status(new DateTimeImmutable('2026-11-07 21:00'))['text'], 'Geschlossen · öffnet Montag um 09:00 Uhr');
check('Sonntag', open_status(new DateTimeImmutable('2026-11-08 12:00'))['text'], 'Geschlossen · öffnet morgen um 09:00 Uhr');

// Datenbank, Angebote
$pdo = db();
check('Kategorien vorbelegt', count(categories()) >= 12, true);
check('Pflichtseiten vorhanden', page_row('impressum') !== null && page_row('datenschutz') !== null, true);
check('keine Angebote am Anfang', active_offers(), []);
demo_load();
$cnt = (int)$pdo->query('SELECT COUNT(*) FROM offers')->fetchColumn();
check('Demo: Angebote angelegt', $cnt, 8);
check('Demo: laufende Angebote (7 diese Woche, 1 nächste Woche)', count(active_offers()), 7);
check('Demo: kommende Angebote', count(upcoming_offers()), 1);
$pdo->exec("INSERT INTO offers (title, price_cents, valid_to) VALUES ('alt', 100, '2020-01-01'), ('inaktiv', 100, NULL)");
$pdo->exec("UPDATE offers SET active = 0 WHERE title = 'inaktiv'");
check('abgelaufen/inaktiv nicht aktiv', count(active_offers()), 7);
$pdo->exec("INSERT INTO offers (title, price_cents) VALUES ('ohne Zeitgrenze', 100)");
check('ohne Zeitgrenze ist aktiv', count(active_offers()), 8);
check('Demo entfernen', demo_remove(), 23);
check('Eigene Angebote bleiben', (int)$pdo->query('SELECT COUNT(*) FROM offers')->fetchColumn(), 3);
set_setting('shop_name', 'Test Markt');
check('Einstellung speichern', setting('shop_name'), 'Test Markt');
check('Einstellung Standardwert', setting('color_accent'), '#63c94a');
check('Passwort zu kurz', password_problem('abc1') !== null, true);
check('Passwort ok', password_problem('Sicher12345x'), null);

// aufräumen
array_map('unlink', glob($tmp . '/*.*') ?: []);
@rmdir($tmp . '/sessions'); @unlink($tmp . '/setup-code.txt'); @rmdir($tmp);
echo "\n$n Tests bestanden\n";
