<?php
declare(strict_types=1);

/* =====================================================================
   Gemeinsame Bausteine
   ===================================================================== */

function theme_css(): string {
    $bg = valid_color(setting('color_bg'), '#12291b');
    $ac = valid_color(setting('color_accent'), '#63c94a');
    $go = valid_color(setting('color_gold'), '#f0d88e');
    $cr = valid_color(setting('color_cream'), '#fef8e8');
    return ":root{--bg:$bg;--accent:$ac;--gold:$go;--cream:$cr;}";
}

function logo_svg(): string {
    return '<svg class="logo-mark" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        . '<path d="M33 60C27 46 25 28 31 6" fill="none" stroke="var(--gold-dark)" stroke-width="2.5" stroke-linecap="round"/>'
        . '<g class="leaf-a"><ellipse cx="22" cy="14" rx="4.2" ry="9" transform="rotate(-38 22 14)"/><ellipse cx="19" cy="27" rx="4.2" ry="9" transform="rotate(-48 19 27)"/><ellipse cx="22" cy="40" rx="4.2" ry="9" transform="rotate(-55 22 40)"/></g>'
        . '<g class="leaf-b"><ellipse cx="40" cy="18" rx="4.2" ry="9" transform="rotate(38 40 18)"/><ellipse cx="42" cy="31" rx="4.2" ry="9" transform="rotate(48 42 31)"/><ellipse cx="39" cy="44" rx="4.2" ry="9" transform="rotate(55 39 44)"/></g></svg>';
}

function logo_html(string $href): string {
    $logo = setting('logo');
    $name = trim(setting('shop_name')) ?: 'Damas Market';
    $tag = setting('tagline');
    if ($logo) {
        return '<a class="logo logo-img" href="' . e($href) . '"><img src="' . e(upload_url($logo)) . '" alt="' . e($name) . '"></a>';
    }
    $parts = preg_split('/\s+/', mb_strtoupper($name), 2);
    return '<a class="logo" href="' . e($href) . '" aria-label="' . e($name) . ' – Startseite">' . logo_svg()
        . '<span class="logo-text"><span class="logo-words"><span class="w1">' . e($parts[0]) . '</span>' . (isset($parts[1]) ? ' <span class="w2">' . e($parts[1]) . '</span>' : '') . '</span>'
        . ($tag ? '<span class="logo-tag">' . e($tag) . '</span>' : '') . '</span></a>';
}

/** Bild oder Platzhalter (Anfangsbuchstabe) */
function img_or_placeholder(?string $rel, string $alt, string $label, bool $thumb = true): string {
    if ($rel) {
        $src = $thumb ? thumb_of($rel) : $rel;
        // Großes Bild (Detailseite) sofort laden, Kartenbilder verzögert
        return '<img src="' . e(upload_url($src)) . '" alt="' . e($alt) . '" ' . ($thumb ? 'loading="lazy" decoding="async"' : 'fetchpriority="high"') . '>';
    }
    $hue = abs(crc32($label)) % 360;
    return '<div class="ph" style="--h:' . $hue . '" role="img" aria-label="' . e($alt) . '"><span>' . e(mb_strtoupper(mb_substr($label, 0, 1))) . '</span></div>';
}

function offer_card(array $o): string {
    $title = $o['title'];
    $img = $o['image'] ?: ($o['product_image'] ?? null);
    $pct = discount_percent($o['old_price_cents'] !== null ? (int)$o['old_price_cents'] : null, (int)$o['price_cents']);
    $badge = $o['badge'] ?: ($pct ? '−' . $pct . ' %' : 'Angebot');
    $future = $o['valid_from'] && $o['valid_from'] > today();
    $h = '<article class="card offer-card" data-name="' . e(mb_strtolower($title . ' ' . ($o['cat_name'] ?? ''))) . '" data-cat="' . (int)($o['cat_id'] ?? $o['category_id'] ?? 0) . '">';
    $h .= '<div class="card-img">' . img_or_placeholder($img, $title, $title) . '<span class="badge-sale' . ($future ? ' soon' : '') . '">' . e($future ? 'bald' : $badge) . '</span></div>';
    $h .= '<div class="card-body"><h3 class="card-title">' . ($o['product_id'] ? '<a href="' . e(u('produkt.php?id=' . (int)$o['product_id'])) . '">' . e($title) . '</a>' : e($title)) . '</h3>';
    if ($o['unit_text']) $h .= '<p class="muted small">' . e($o['unit_text']) . '</p>';
    if ($o['description']) $h .= '<p class="small card-desc">' . e($o['description']) . '</p>';
    $h .= '<div class="price-row"><span class="price">' . money((int)$o['price_cents']) . '</span>';
    if ($o['old_price_cents']) $h .= '<s class="old-price">' . money((int)$o['old_price_cents']) . '</s>';
    $h .= '</div><p class="validity small">' . e(validity_text($o['valid_from'], $o['valid_to'])) . '</p></div></article>';
    return $h;
}

function product_card(array $p, ?array $offer = null): string {
    $h = '<article class="card product-card"><a class="card-img" href="' . e(u('produkt.php?id=' . (int)$p['id'])) . '" tabindex="-1" aria-hidden="true">'
        . img_or_placeholder($p['image'], $p['name'], $p['name']);
    if ($offer) $h .= '<span class="badge-sale">Angebot</span>';
    elseif (!empty($p['is_new'])) $h .= '<span class="badge-new">Neu</span>';
    $h .= '</a><div class="card-body"><h3 class="card-title"><a href="' . e(u('produkt.php?id=' . (int)$p['id'])) . '">' . e($p['name']) . '</a></h3>';
    if ($p['name_alt']) $h .= '<p class="alt-name" dir="auto">' . e($p['name_alt']) . '</p>';
    if ($p['unit']) $h .= '<p class="muted small">' . e($p['unit']) . '</p>';
    $h .= '<div class="price-row">';
    if ($offer) $h .= '<span class="price">' . money((int)$offer['price_cents']) . '</span>' . ($offer['old_price_cents'] ? '<s class="old-price">' . money((int)$offer['old_price_cents']) . '</s>' : ($p['price_cents'] !== null ? '<s class="old-price">' . money((int)$p['price_cents']) . '</s>' : ''));
    elseif ($p['price_cents'] !== null) $h .= '<span class="price">' . money((int)$p['price_cents']) . '</span>';
    else $h .= '<span class="muted small">Preis im Markt</span>';
    $h .= '</div>';
    if (isset($p['in_stock']) && !(int)$p['in_stock']) $h .= '<p class="small out">Aktuell nicht verfügbar</p>';
    return $h . '</div></article>';
}

/* =====================================================================
   Öffentliche Seite
   ===================================================================== */

function site_head(string $title, string $active = '', string $desc = ''): void {
    $name = setting('shop_name') ?: 'Damas Market';
    $full = $title ? "$title – $name" : $name . ' – ' . setting('tagline');
    $desc = $desc ?: setting('meta_description');
    $nav = ['index.php' => ['start', 'Start'], 'angebote.php' => ['angebote', 'Angebote'], 'produkte.php' => ['produkte', 'Sortiment'], 'kontakt.php' => ['kontakt', 'Kontakt']];
    $st = open_status();
    ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($full) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($full) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<?php if (setting('logo')): ?><meta property="og:image" content="<?= e(upload_url(setting('logo'))) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e(valid_color(setting('color_bg'), '#12291b')) ?>">
<link rel="preload" href="<?= e(asset('fonts/barlow-condensed-latin-800-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<style nonce="<?= e(CSP_NONCE) ?>"><?= theme_css() ?></style>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</head>
<body>
<a class="skip" href="#main">Zum Inhalt springen</a>
<?php if (setting('announcement_on') === '1' && trim(setting('announcement')) !== ''): ?>
<div class="announce"><div class="wrap"><?= e(setting('announcement')) ?></div></div>
<?php endif; ?>
<header class="site-header">
  <div class="gold-line"></div>
  <div class="wrap header-in">
    <?= logo_html(u('index.php')) ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mainnav"><span class="sr">Menü</span><i></i><i></i><i></i></button>
    <nav id="mainnav" class="main-nav" aria-label="Hauptmenü">
      <?php foreach ($nav as $file => [$key, $label]): ?>
        <a href="<?= e(u($file)) ?>"<?= $active === $key ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <span class="status <?= $st['open'] ? 'is-open' : 'is-closed' ?>"><i></i><?= e($st['text']) ?></span>
    </nav>
  </div>
  <div class="gold-line"></div>
</header>
<main id="main">
<?php
}

function site_foot(): void {
    $name = setting('shop_name') ?: 'Damas Market';
    $h = hours();
    $addr = array_filter([setting('street'), trim(setting('zip') . ' ' . setting('city'))]);
    ?>
</main>
<footer class="site-footer">
  <div class="gold-line"></div>
  <div class="wrap foot-grid">
    <div>
      <?= logo_html(u('index.php')) ?>
      <p class="foot-text"><?= e(setting('tagline')) ?></p>
    </div>
    <div>
      <h2>Öffnungszeiten</h2>
      <?= hours_table($h, true) ?>
    </div>
    <div>
      <h2>Kontakt</h2>
      <address>
        <?php foreach ($addr as $l): ?><?= e($l) ?><br><?php endforeach; ?>
        <?php if (setting('phone')): ?><a href="tel:<?= e(preg_replace('/[^+\d]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a><br><?php endif; ?>
        <?php if (setting('email')): ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a><?php endif; ?>
      </address>
    </div>
    <div>
      <h2>Mehr</h2>
      <ul class="foot-links">
        <li><a href="<?= e(u('seite.php?s=ueber-uns')) ?>">Über uns</a></li>
        <li><a href="<?= e(u('seite.php?s=impressum')) ?>">Impressum</a></li>
        <li><a href="<?= e(u('seite.php?s=datenschutz')) ?>">Datenschutz</a></li>
        <?php foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'] as $k => $label): if (setting($k)): ?>
          <li><a href="<?= e(setting($k)) ?>" rel="noopener" target="_blank"><?= e($label) ?></a></li>
        <?php endif; endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="wrap copy">© <?= date('Y') ?> <?= e($name) ?></div>
</footer>
</body>
</html>
<?php
}

function hours_table(array $h, bool $compact = false): string {
    $todayKey = wd_key(new DateTimeImmutable('now'));
    $o = '<table class="hours' . ($compact ? ' compact' : '') . '"><tbody>';
    foreach (WEEKDAYS as $k => $label) {
        $o .= '<tr' . ($k === $todayKey ? ' class="today"' : '') . '><th scope="row">' . e($compact ? WD_SHORT[$k] : $label) . '</th><td>'
            . ($h[$k]['closed'] ? 'geschlossen' : e($h[$k]['open']) . ' – ' . e($h[$k]['close']) . ' Uhr') . '</td></tr>';
    }
    return $o . '</tbody></table>';
}

/* =====================================================================
   Admin-Bereich
   ===================================================================== */

function admin_head(string $title, string $active): void {
    $nav = [
        'dashboard' => ['index.php', 'Übersicht', '<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>'],
        'produkte' => ['produkte.php', 'Produkte', '<path d="M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8"/>'],
        'angebote' => ['angebote.php', 'Angebote', '<path d="M20 12l-8 8-9-9V3h8zM7.5 7.5h.01"/>'],
        'kategorien' => ['kategorien.php', 'Kategorien', '<path d="M4 6h16M4 12h16M4 18h10"/>'],
        'seiten' => ['seiten.php', 'Seiten', '<path d="M6 2h9l5 5v15H6zM14 2v6h6M9 13h8M9 17h8"/>'],
        'einstellungen' => ['einstellungen.php', 'Einstellungen', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>'],
        'konto' => ['konto.php', 'Konto & Backup', '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/>'],
    ];
    $name = setting('shop_name') ?: 'Damas Market';
    ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – Admin · <?= e($name) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style nonce="<?= e(CSP_NONCE) ?>"><?= theme_css() ?></style>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <div class="side-brand"><?= logo_svg() ?><span><b><?= e($name) ?></b><small>Admin-Bereich</small></span></div>
    <nav aria-label="Admin-Menü">
      <?php foreach ($nav as $key => [$file, $label, $icon]): ?>
        <a href="<?= e(u('admin/' . $file)) ?>"<?= $active === $key ? ' class="active" aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= e(u('index.php')) ?>" target="_blank" rel="noopener">Website ansehen ↗</a>
      <a href="<?= e(u('admin/logout.php')) ?>">Abmelden</a>
    </div>
  </aside>
  <div class="content">
    <div class="topbar">
      <button class="menu-btn" type="button" id="menuBtn" aria-controls="sidebar" aria-expanded="false">Menü</button>
      <h1><?= e($title) ?></h1>
    </div>
    <div class="page">
      <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="flash <?= e($type) ?>" role="status"><?= e($msg) ?></div>
      <?php endforeach; ?>
<?php
}

function admin_foot(): void {
    ?>
    </div>
  </div>
</div>
</body>
</html>
<?php
}

/** Kleine Hilfsfunktionen für Formulare */
function fld(string $label, string $name, string $value = '', array $o = []): string {
    $type = $o['type'] ?? 'text';
    $hint = isset($o['hint']) ? '<small class="hint">' . e($o['hint']) . '</small>' : '';
    $attr = '';
    foreach (['id', 'placeholder', 'maxlength', 'inputmode', 'step', 'min', 'max', 'autocomplete', 'pattern'] as $a) if (isset($o[$a])) $attr .= ' ' . $a . '="' . e((string)$o[$a]) . '"';
    if (!empty($o['required'])) $attr .= ' required';
    if (!empty($o['dir'])) $attr .= ' dir="' . e($o['dir']) . '"';
    if (!empty($o['rows'])) return '<label class="fld' . (isset($o['class']) ? ' ' . $o['class'] : '') . '"><span>' . e($label) . '</span><textarea name="' . e($name) . '" rows="' . (int)$o['rows'] . '"' . $attr . '>' . e($value) . '</textarea>' . $hint . '</label>';
    return '<label class="fld' . (isset($o['class']) ? ' ' . $o['class'] : '') . '"><span>' . e($label) . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $attr . '>' . $hint . '</label>';
}

function image_field(string $label, string $name, ?string $current, string $hint = ''): string {
    $h = '<div class="fld image-fld"><span>' . e($label) . '</span><div class="img-row">';
    $h .= '<div class="img-prev" data-for="' . e($name) . '">' . ($current ? '<img src="' . e(upload_url(thumb_of($current))) . '" alt="">' : '<em>kein Bild</em>') . '</div><div>';
    $h .= '<input type="file" name="' . e($name) . '" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="' . e($name) . '">';
    if ($current) $h .= '<label class="check"><input type="checkbox" name="' . e($name) . '_remove" value="1"> Bild entfernen</label>';
    $h .= ($hint ? '<small class="hint">' . e($hint) . '</small>' : '') . '</div></div></div>';
    return $h;
}

function checkbox(string $label, string $name, bool $checked, string $hint = ''): string {
    return '<label class="check"><input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '> <span>' . e($label) . ($hint ? ' <small class="hint">' . e($hint) . '</small>' : '') . '</span></label>';
}

/** Schlanke Seite für Login / Einrichtung */
function auth_head(string $title): void {
    $name = setting('shop_name') ?: 'Damas Market';
    ?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – <?= e($name) ?> Admin</title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"><style nonce="<?= e(CSP_NONCE) ?>"><?= theme_css() ?></style></head>
<body class="auth"><main class="auth-card"><div class="auth-brand"><?= logo_svg() ?><span><b><?= e($name) ?></b><small>Admin-Bereich</small></span></div>
<?php
}
function auth_foot(): void { echo "</main></body></html>"; }
