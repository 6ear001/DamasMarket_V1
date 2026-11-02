<?php
// Produktbilder per Barcode suchen (Kommandozeile):  php tools/fetch_images.php [--retry]
// Nützlich, wenn der Server viele Produkte auf einmal bearbeiten soll. Im Admin geht dasselbe unter „Produktbilder“.
declare(strict_types=1);
define('APP_DIR', dirname(__DIR__) . '/app');
define('PUBLIC_DIR', dirname(__DIR__) . '/public');
require APP_DIR . '/bootstrap.php';

if (in_array('--retry', $argv, true)) echo image_reset_tried() . " Produkte werden erneut gesucht.\n";
$tot = ['found' => 0, 'none' => 0, 'skipped' => 0];
$s = image_stats();
echo "{$s['total']} Produkte, {$s['with_image']} mit Bild, {$s['remaining']} noch zu suchen.\n";
while (true) {
    $r = image_batch(10);
    foreach ($tot as $k => $_) $tot[$k] += $r[$k];
    foreach ($r['log'] as [$name, $st, $msg]) echo str_pad($st, 8) . $name . ($st === 'found' ? '' : "  ($msg)") . "\n";
    if ($r['error']) { fwrite(STDERR, "\nABBRUCH: {$r['error']}\n"); exit(1); }
    if ($r['remaining'] === 0 || $r['processed'] === 0) break;
}
echo "\nFertig: {$tot['found']} Bilder gefunden, {$tot['none']} ohne Treffer, {$tot['skipped']} ohne gültigen Barcode.\n";
