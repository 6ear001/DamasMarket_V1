<?php
// Produkte aus einer CSV-Datei importieren (Kommandozeile):  php tools/import_csv.php import/produkte.csv
// Dasselbe wie Admin → Produkte → „Excel / CSV: Import“, nur ohne Datei-Upload (praktisch in Codespaces).
declare(strict_types=1);
define('APP_DIR', dirname(__DIR__) . '/app');
define('PUBLIC_DIR', dirname(__DIR__) . '/public');
require APP_DIR . '/bootstrap.php';

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) { fwrite(STDERR, "Aufruf: php tools/import_csv.php import/produkte.csv\n"); exit(1); }
[$add, $upd, $skip] = import_products_csv((string)file_get_contents($file));
echo "Import fertig: $add neu, $upd aktualisiert" . ($skip ? ", $skip übersprungen" : '') . ".\n";
$s = image_stats();
echo "{$s['total']} Produkte insgesamt, {$s['remaining']} davon mit Barcode zum Bildersuchen.\n";
