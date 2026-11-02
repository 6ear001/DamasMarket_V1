<?php
declare(strict_types=1);

/* ---------- Ausgabe & URLs ---------- */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function u(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

function asset(string $path): string {
    $f = PUBLIC_DIR . '/assets/' . $path;
    return u('assets/' . $path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

function upload_url(?string $rel): string { return $rel ? u('uploads/' . $rel) : ''; }

/** Vorschaubild-Pfad zu 'products/abc.webp' → 'products/abc_t.webp' */
function thumb_of(?string $rel): ?string {
    if (!$rel) return null;
    $t = preg_replace('/(\.[a-z0-9]+)$/i', '_t$1', $rel);
    return is_file(UPLOAD_DIR . '/' . $t) ? $t : $rel;
}

function redirect(string $to): never {
    header('Location: ' . $to);
    exit;
}

/* ---------- Geld, Datum ---------- */
function money(?int $cents): string {
    if ($cents === null) return '';
    return number_format($cents / 100, 2, ',', '.') . ' €';
}

/** "1,99" / "1.99" / "2" → 199 | null bei leer/ungültig */
function parse_price(?string $s): ?int {
    $s = trim((string)$s);
    if ($s === '') return null;
    $s = str_replace(['€', ' '], '', $s);
    if (preg_match('/^\d{1,3}(\.\d{3})+,\d{1,2}$/', $s)) $s = str_replace('.', '', $s); // 1.234,50
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s) || (float)$s < 0 || (float)$s > 99999) return null;
    return (int)round((float)$s * 100);
}

function price_input(?int $cents): string { return $cents === null ? '' : number_format($cents / 100, 2, ',', ''); }

function discount_percent(?int $old, ?int $new): ?int {
    if (!$old || $new === null || $new >= $old) return null;
    $p = (int)round(($old - $new) / $old * 100);
    return $p >= 1 ? $p : null;
}

const WEEKDAYS = ['mo' => 'Montag', 'di' => 'Dienstag', 'mi' => 'Mittwoch', 'do' => 'Donnerstag', 'fr' => 'Freitag', 'sa' => 'Samstag', 'so' => 'Sonntag'];
const WD_SHORT = ['mo' => 'Mo.', 'di' => 'Di.', 'mi' => 'Mi.', 'do' => 'Do.', 'fr' => 'Fr.', 'sa' => 'Sa.', 'so' => 'So.'];

function wd_key(DateTimeInterface $d): string { return array_keys(WEEKDAYS)[(int)$d->format('N') - 1]; }

function valid_date(?string $s): ?string {
    $s = trim((string)$s);
    if ($s === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

/** '2026-11-08' → 'So., 08.11.' */
function date_de(?string $iso, bool $withYear = false): string {
    $d = $iso ? DateTime::createFromFormat('Y-m-d', $iso) : null;
    if (!$d) return '';
    return WD_SHORT[wd_key($d)] . ', ' . $d->format('d.m.') . ($withYear ? $d->format('Y') : '');
}

function today(): string { return date('Y-m-d'); }

/** Text für die Gültigkeit eines Angebots */
function validity_text(?string $from, ?string $to): string {
    if ($from && $from > today()) return 'ab ' . date_de($from) . ($to ? ' bis ' . date_de($to) : '');
    if ($to) return 'gültig bis ' . date_de($to);
    return 'solange der Vorrat reicht';
}

/* ---------- Farben ---------- */
function valid_color(?string $c, string $fallback): string {
    return (is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c)) ? strtolower($c) : $fallback;
}

/* ---------- Text: einfaches Markdown (sicher) ---------- */
function text_to_html(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') return '';
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace_callback('~\bhttps?://[^\s<]+~i', fn($m) => '<a href="' . $m[0] . '" rel="noopener nofollow" target="_blank">' . $m[0] . '</a>', $s);
        $s = preg_replace('/\b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/', '<a href="mailto:$1">$1</a>', $s);
        return $s;
    };
    $out = [];
    foreach (preg_split("/\n{2,}/", $text) as $block) {
        $lines = explode("\n", $block);
        if (preg_match('/^(#{1,3})\s+(.+)$/', $lines[0], $m) && count($lines) === 1) {
            $lvl = max(2, strlen($m[1])); // Seitentitel ist h1
            $out[] = "<h$lvl>" . $inline($m[2]) . "</h$lvl>";
        } elseif (preg_match('/^[-*]\s+/', $lines[0])) {
            $out[] = '<ul>' . implode('', array_map(fn($l) => '<li>' . $inline(preg_replace('/^[-*]\s+/', '', $l)) . '</li>', $lines)) . '</ul>';
        } else {
            $out[] = '<p>' . implode('<br>', array_map($inline, $lines)) . '</p>';
        }
    }
    return implode("\n", $out);
}

function slugify(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'ç' => 'c', 'ş' => 's', 'ğ' => 'g', 'ı' => 'i', 'é' => 'e', 'è' => 'e']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'n-a';
}

/* ---------- Flash-Meldungen ---------- */
function flash(string $msg, string $type = 'ok'): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start_safe();
    $_SESSION['flash'][] = [$type, $msg];
}
function flashes(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) return [];
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Öffnungszeiten ---------- */
function default_hours(): array {
    $h = [];
    foreach (array_keys(WEEKDAYS) as $k) $h[$k] = ['closed' => false, 'open' => '09:00', 'close' => '20:00'];
    $h['so']['closed'] = true;
    return $h;
}

function hours(): array {
    $saved = json_decode(setting('hours'), true);
    $h = default_hours();
    if (is_array($saved)) foreach ($h as $k => $_) {
        if (isset($saved[$k]) && is_array($saved[$k])) {
            $h[$k]['closed'] = !empty($saved[$k]['closed']);
            foreach (['open', 'close'] as $f) if (isset($saved[$k][$f]) && preg_match('/^\d{2}:\d{2}$/', $saved[$k][$f])) $h[$k][$f] = $saved[$k][$f];
        }
    }
    return $h;
}

/** @return array{open:bool,text:string} */
function open_status(?DateTimeImmutable $now = null): array {
    $now ??= new DateTimeImmutable('now');
    $h = hours();
    $k = wd_key($now);
    $t = $now->format('H:i');
    $d = $h[$k];
    if (!$d['closed'] && $t >= $d['open'] && $t < $d['close']) return ['open' => true, 'text' => 'Jetzt geöffnet · bis ' . $d['close'] . ' Uhr'];
    if (!$d['closed'] && $t < $d['open']) return ['open' => false, 'text' => 'Geschlossen · öffnet heute um ' . $d['open'] . ' Uhr'];
    for ($i = 1; $i <= 7; $i++) {
        $n = $now->modify("+$i day");
        $nk = wd_key($n);
        if (!$h[$nk]['closed']) return ['open' => false, 'text' => 'Geschlossen · öffnet ' . ($i === 1 ? 'morgen' : WEEKDAYS[$nk]) . ' um ' . $h[$nk]['open'] . ' Uhr'];
    }
    return ['open' => false, 'text' => 'Geschlossen'];
}

/* ---------- Sonstiges ---------- */
function paginate(int $total, int $perPage, int $page): array {
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}

function query_with(array $override): string {
    $q = array_merge($_GET, $override);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '?';
}

function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
