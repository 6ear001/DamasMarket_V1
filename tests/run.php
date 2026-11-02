<?php
// Ausführen mit:  php tests/run.php
declare(strict_types=1);
define('APP_DIR', dirname(__DIR__) . '/app');
define('PUBLIC_DIR', dirname(__DIR__) . '/public');
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
check('Kategorien vorbelegt', count(categories()) >= 20, true);
check('Spalten ergänzt (Barcode, Bildquelle)', count(array_intersect(['barcode', 'image_credit', 'image_tried'], array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(), 'name'))), 3);
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

// CSV-Import (Windows-1252 wie bei deutschem Excel, Zeilenumbruch in Beschreibung, Dublette, Bereich)
$csv = "Name;Name (Arabisch/Türkisch);Kategorie;Einheit;Preis;Herkunft;Beschreibung;Sichtbar;Barcode;Bereich\r\n"
     . "Süßer Tee;;Neue Kategorie;500 g;2,50;;\"Zeile 1\nZeile 2\";ja;4000000000011;Getränke\r\n"
     . "Zweites;;Neue Kategorie;1 kg;1.234,50;;;nein;;Lebensmittel\r\n"
     . "Süßer Tee;;Neue Kategorie;500 g;3,00;;;ja;4000000000011;Getränke\r\n\r\n";
check('Import: neu/aktualisiert/übersprungen', import_products_csv(mb_convert_encoding($csv, 'Windows-1252', 'UTF-8')), [2, 1, 0]);
$row = $pdo->query("SELECT p.*, c.group_name g FROM products p JOIN categories c ON c.id = p.category_id WHERE p.barcode = '4000000000011'")->fetch();
check('Import: Umlaute aus Windows-1252', $row['name'], 'Süßer Tee');
check('Import: Preis aktualisiert', (int)$row['price_cents'], 300);
check('Import: neue Kategorie im richtigen Bereich', $row['g'], 'Getränke');
check('Import: Preis mit Tausenderpunkt', (int)$pdo->query("SELECT price_cents FROM products WHERE name = 'Zweites'")->fetchColumn(), 123450);
check('Import: unsichtbar', (int)$pdo->query("SELECT visible FROM products WHERE name = 'Zweites'")->fetchColumn(), 0);
check('Import: zweiter Lauf ohne Doppelte', import_products_csv($csv), [0, 3, 0]);
$pdo->exec("DELETE FROM products WHERE name IN ('Süßer Tee', 'Zweites')");

// Barcodes / Bildsuche
check('Barcode EAN-13 gültig', barcode_searchable('4260193516047'), true);
check('Barcode EAN-8 gültig', barcode_searchable('40170725'), true);
check('Barcode intern (2…) nicht suchen', barcode_searchable('2609800000001'), false);
check('Barcode intern 12-stellig', barcode_searchable('260730123936'), false);
check('Barcode zu kurz', barcode_searchable('12345'), false);
check('Barcode mit Buchstaben', barcode_searchable('42601935160AB'), false);
check('UPC-12 → auch EAN-13 versuchen', barcode_variants('548810059202'), ['548810059202', '0548810059202']);
check('EAN-13 nur eine Variante', barcode_variants('4260193516047'), ['4260193516047']);
check('Host: Open Food Facts erlaubt', off_host_ok('https://images.openfoodfacts.org/images/products/1.jpg'), true);
check('Host: fremder Server verboten', off_host_ok('https://evil.example.com/x.jpg'), false);
check('Host: Lookalike verboten', off_host_ok('https://openfoodfacts.org.evil.com/x.jpg'), false);
check('Host: http verboten', off_host_ok('http://images.openfoodfacts.org/x.jpg'), false);
check('Host: interne Adresse verboten', off_host_ok('https://169.254.169.254/latest'), false);
check('Quelle: ohne Bilder keine Namensnennung', images_need_credit(), false);

// aufräumen
array_map('unlink', glob($tmp . '/*.*') ?: []);
@rmdir($tmp . '/sessions'); @unlink($tmp . '/setup-code.txt'); @rmdir($tmp);
echo "\n$n Tests bestanden\n";
