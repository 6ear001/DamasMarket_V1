<?php
declare(strict_types=1);

/**
 * Produkt-Import aus CSV (Semikolon oder Komma, UTF-8 oder Windows-1252/Excel).
 * Spalten: Name; Name (Arabisch/Türkisch); Kategorie; Einheit; Preis; Herkunft; Beschreibung; Sichtbar; Barcode; Bereich
 * Vorhandene Produkte werden aktualisiert (gleicher Barcode, sonst gleicher Name + Einheit), neue angelegt.
 * @return array{0:int,1:int,2:int} [neu, aktualisiert, übersprungen]
 */
function import_products_csv(string $raw): array {
    $pdo = db();
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'); // Excel-Standard
    $h = fopen('php://memory', 'w+');
    fwrite($h, $raw); rewind($h);
    $first = (string)fgets($h);
    $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    rewind($h);
    fgetcsv($h, 0, $delim, '"', ''); // Überschriftenzeile
    $cats = []; foreach ($pdo->query('SELECT id, name FROM categories') as $c) $cats[mb_strtolower($c['name'])] = (int)$c['id'];
    $add = $upd = $skip = 0;
    $pdo->beginTransaction();
    while (($f = fgetcsv($h, 0, $delim, '"', '')) !== false) {
        if ($f === [null]) continue; // Leerzeile
        $name = trim($f[0] ?? '');
        if ($name === '') { $skip++; continue; }
        $catName = trim($f[2] ?? '');
        $catId = null;
        if ($catName !== '') {
            $k = mb_strtolower($catName);
            if (!isset($cats[$k])) {
                $grp = in_array(trim($f[9] ?? ''), GROUPS, true) ? trim($f[9]) : 'Lebensmittel';
                $pdo->prepare('INSERT INTO categories (name, group_name, sort) VALUES (?, ?, 999)')->execute([$catName, $grp]);
                $cats[$k] = (int)$pdo->lastInsertId();
            }
            $catId = $cats[$k];
        }
        $unit = trim($f[3] ?? '');
        $vis = !in_array(mb_strtolower(trim($f[7] ?? 'ja')), ['nein', '0', 'no', 'false'], true) ? 1 : 0;
        $price = parse_price($f[4] ?? '');
        $barcode = preg_replace('/\D/', '', (string)($f[8] ?? ''));
        $eid = false;
        if ($barcode !== '') { $ex = $pdo->prepare('SELECT id FROM products WHERE barcode = ?'); $ex->execute([$barcode]); $eid = $ex->fetchColumn(); }
        if (!$eid) { // ohne Barcode-Treffer: gleicher Name + Einheit (Zeilen ohne Barcode passen auch auf Produkte, die schon einen haben)
            $ex = $pdo->prepare('SELECT id FROM products WHERE lower(name) = lower(?) AND lower(unit) = lower(?) AND (? = \'\' OR barcode = \'\' OR barcode = ?)');
            $ex->execute([$name, $unit, $barcode, $barcode]); $eid = $ex->fetchColumn();
        }
        $vals = [trim($f[1] ?? ''), $catId, $unit, $price, trim($f[5] ?? ''), trim($f[6] ?? ''), $vis, $barcode];
        if ($eid) { $pdo->prepare("UPDATE products SET name_alt=?, category_id=?, unit=?, price_cents=?, origin=?, description=?, visible=?, barcode=COALESCE(NULLIF(?, ''), barcode), name=?, updated_at=datetime('now') WHERE id=?")->execute([...$vals, $name, $eid]); $upd++; }
        else { $pdo->prepare('INSERT INTO products (name_alt, category_id, unit, price_cents, origin, description, visible, barcode, name) VALUES (?,?,?,?,?,?,?,?,?)')->execute([...$vals, $name]); $add++; }
    }
    fclose($h);
    $pdo->commit();
    return [$add, $upd, $skip];
}
