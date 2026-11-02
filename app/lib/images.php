<?php
declare(strict_types=1);

/**
 * Automatische Produktbilder per Barcode aus der offenen Produktdatenbank "Open Food Facts"
 * (https://world.openfoodfacts.org, Bilder unter CC BY-SA 4.0 – Namensnennung wird im Seitenfuß angezeigt).
 * Der Server braucht dafür Zugriff ins Internet (bei normalem Hosting der Fall).
 */

const IMAGE_CREDIT_OFF = 'Open Food Facts';

function off_base(): string { return rtrim(getenv('DAMAS_OFF_BASE') ?: 'https://world.openfoodfacts.org', '/'); }

/** Nur echte Handelsbarcodes (EAN-8, UPC-12, EAN-13/14). Barcodes mit 2 am Anfang sind interne Kassen-/Waagencodes. */
function barcode_searchable(string $bc): bool {
    return (bool)preg_match('/^(\d{8}|\d{12,14})$/', $bc) && $bc[0] !== '2';
}

/** Schreibweisen, unter denen ein Barcode in der Datenbank stehen kann */
function barcode_variants(string $bc): array {
    $v = [$bc];
    if (strlen($bc) === 12) $v[] = '0' . $bc;          // UPC-A → EAN-13
    if (strlen($bc) === 14 && $bc[0] === '0') $v[] = substr($bc, 1);
    return array_values(array_unique($v));
}

function off_host_ok(string $url): bool {
    $p = parse_url($url);
    if (!$p || empty($p['host']) || empty($p['scheme'])) return false;
    $host = strtolower($p['host']);
    $base = parse_url(off_base());
    if ($host === strtolower($base['host'] ?? '')) return true;               // Testbetrieb
    return $p['scheme'] === 'https' && ($host === 'openfoodfacts.org' || str_ends_with($host, '.openfoodfacts.org'));
}

/** @return array{0:int,1:string,2:string} [HTTP-Status (0 = keine Verbindung), Inhalt, Fehlertext] */
function http_get(string $url, int $timeout = 15): array {
    $ua = 'DamasMarketBilder/1.0 (Produktbilder fuer einen Paketshop; Kontakt ueber die Website)';
    $max = 8 * 1024 * 1024;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => $ua,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => $max]);
        $body = curl_exec($ch);
        $st = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $eff = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) return [0, '', $err ?: 'keine Verbindung'];
        if ($eff !== $url && !off_host_ok($eff)) return [0, '', 'Weiterleitung auf unerlaubten Server'];
        return [$st, (string)$body, ''];
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "User-Agent: $ua\r\n", 'ignore_errors' => true, 'follow_location' => 0]]);
    $body = @file_get_contents($url, false, $ctx, 0, $max + 1);
    if ($body === false) return [0, '', 'keine Verbindung (allow_url_fopen/OpenSSL prüfen)'];
    $st = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $st = (int)$m[1];
    return [$st, $body, ''];
}

/**
 * Sucht und speichert das Bild für ein Produkt (Zeile aus "products").
 * @return array{status:string,msg:string}  status: found | none | error (error = Netzwerkproblem, nicht als "ohne Treffer" merken)
 */
function fetch_product_image(array $p): array {
    foreach (barcode_variants((string)$p['barcode']) as $bc) {
        [$st, $body, $err] = http_get(off_base() . '/api/v2/product/' . $bc . '.json?fields=code,image_front_url,image_url');
        if ($st === 0) return ['status' => 'error', 'msg' => 'Keine Verbindung zu Open Food Facts: ' . $err];
        if ($st === 429 || $st >= 500) return ['status' => 'error', 'msg' => "Open Food Facts antwortet gerade nicht richtig (Status $st). Bitte später erneut versuchen."];
        if ($st !== 200) continue;
        $j = json_decode($body, true);
        $url = $j['product']['image_front_url'] ?? $j['product']['image_url'] ?? null;
        if (!is_string($url) || !off_host_ok($url)) continue;
        [$st2, $img, $err2] = http_get($url, 25);
        if ($st2 === 0) return ['status' => 'error', 'msg' => 'Bild konnte nicht geladen werden: ' . $err2];
        if ($st2 !== 200 || $img === '') continue;
        $tmp = tempnam(sys_get_temp_dir(), 'dm');
        file_put_contents($tmp, $img);
        try { $rel = process_image_file($tmp, 'products', 900, false, true); }
        catch (RuntimeException $e) { @unlink($tmp); continue; }
        @unlink($tmp);
        db()->prepare('UPDATE products SET image = ?, image_credit = ?, image_tried = ? WHERE id = ?')->execute([$rel, IMAGE_CREDIT_OFF, today(), $p['id']]);
        return ['status' => 'found', 'msg' => 'Bild gefunden'];
    }
    db()->prepare('UPDATE products SET image_tried = ? WHERE id = ?')->execute([today(), $p['id']]);
    return ['status' => 'none', 'msg' => 'kein Bild in der Datenbank'];
}

function image_remaining(): int {
    return (int)db()->query("SELECT COUNT(*) FROM products WHERE (image IS NULL OR image = '') AND barcode <> '' AND image_tried IS NULL")->fetchColumn();
}

function image_stats(): array {
    $q = fn(string $w) => (int)db()->query("SELECT COUNT(*) FROM products WHERE $w")->fetchColumn();
    return [
        'total' => $q('1=1'),
        'with_image' => $q("image IS NOT NULL AND image <> ''"),
        'auto' => $q("image_credit <> ''"),
        'no_barcode' => $q("(image IS NULL OR image = '') AND barcode = ''"),
        'not_found' => $q("(image IS NULL OR image = '') AND barcode <> '' AND image_tried IS NOT NULL"),
        'remaining' => image_remaining(),
    ];
}

/** Verarbeitet bis zu $limit Produkte. @return array{processed:int,found:int,none:int,skipped:int,error:?string,remaining:int,log:array} */
function image_batch(int $limit = 6): array {
    $rows = db()->query("SELECT * FROM products WHERE (image IS NULL OR image = '') AND barcode <> '' AND image_tried IS NULL ORDER BY id LIMIT " . max(1, $limit))->fetchAll();
    $r = ['processed' => 0, 'found' => 0, 'none' => 0, 'skipped' => 0, 'error' => null, 'remaining' => 0, 'log' => []];
    foreach ($rows as $p) {
        if (!barcode_searchable((string)$p['barcode'])) {
            db()->prepare('UPDATE products SET image_tried = ? WHERE id = ?')->execute([today(), $p['id']]);
            $r['skipped']++; $r['processed']++; $r['log'][] = [$p['name'], 'skipped', 'interner Barcode'];
            continue;
        }
        $res = fetch_product_image($p);
        if ($res['status'] === 'error') { $r['error'] = $res['msg']; break; }
        $r['processed']++; $r[$res['status']]++;
        $r['log'][] = [$p['name'], $res['status'], $res['msg']];
        usleep(((int)(getenv('DAMAS_OFF_DELAY_MS') !== false ? getenv('DAMAS_OFF_DELAY_MS') : 600)) * 1000); // höflich bleiben: max. ~100 Anfragen pro Minute
    }
    $r['remaining'] = image_remaining();
    return $r;
}

/** Sucht nach einem Fehlversuch erneut (z. B. weil Open Food Facts inzwischen neue Bilder hat). */
function image_reset_tried(): int {
    $st = db()->prepare("UPDATE products SET image_tried = NULL WHERE (image IS NULL OR image = '') AND image_tried IS NOT NULL");
    $st->execute();
    return $st->rowCount();
}

/** Sind irgendwo automatisch gefundene Bilder im Einsatz? (dann Namensnennung im Seitenfuß) */
function images_need_credit(): bool {
    static $r = null;
    return $r ??= (int)db()->query("SELECT COUNT(*) FROM products WHERE image_credit <> '' AND image <> '' AND visible = 1")->fetchColumn() > 0;
}
