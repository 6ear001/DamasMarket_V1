<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
require_login();

const BRAND = ['color_bg' => '#12291b', 'color_accent' => '#63c94a', 'color_gold' => '#f0d88e', 'color_cream' => '#fef8e8'];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $t = fn(string $k, int $max = 200) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $url = function (string $k) use ($t): string {
        $v = $t($k, 300);
        return ($v === '' || preg_match('~^https?://~i', $v)) ? $v : 'https://' . $v;
    };
    $vals = [
        'shop_name' => $t('shop_name', 60) ?: 'Damas Market', 'tagline' => $t('tagline', 80),
        'hero_title' => $t('hero_title', 80), 'hero_text' => $t('hero_text', 300),
        'announcement_on' => isset($_POST['announcement_on']) ? '1' : '0', 'announcement' => $t('announcement', 200),
        'street' => $t('street', 80), 'zip' => $t('zip', 10), 'city' => $t('city', 60), 'phone' => $t('phone', 30), 'email' => $t('email', 100),
        'whatsapp' => $t('whatsapp', 30), 'map_url' => $url('map_url'), 'instagram' => $url('instagram'), 'facebook' => $url('facebook'), 'tiktok' => $url('tiktok'),
        'contact_text' => $t('contact_text', 1500), 'meta_description' => $t('meta_description', 250), 'flyer_title' => $t('flyer_title', 60) ?: 'Aktueller Prospekt als PDF',
    ];
    if ($vals['email'] !== '' && !filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Die E-Mail-Adresse ist ungültig.';
    foreach (BRAND as $k => $def) $vals[$k] = valid_color($_POST[$k] ?? '', $def);
    if (!empty($_POST['reset_colors'])) $vals = array_merge($vals, BRAND);

    $hours = [];
    foreach (array_keys(WEEKDAYS) as $d) {
        $o = (string)($_POST['h'][$d]['open'] ?? '09:00'); $c = (string)($_POST['h'][$d]['close'] ?? '20:00');
        $hours[$d] = ['closed' => isset($_POST['h'][$d]['closed']), 'open' => preg_match('/^\d{2}:\d{2}$/', $o) ? $o : '09:00', 'close' => preg_match('/^\d{2}:\d{2}$/', $c) ? $c : '20:00'];
        if (!$hours[$d]['closed'] && $hours[$d]['close'] <= $hours[$d]['open']) $err[] = WEEKDAYS[$d] . ': Die Schließzeit muss nach der Öffnungszeit liegen.';
    }
    $vals['hours'] = json_encode($hours);

    if (!$err) {
        try {
            foreach (['logo' => ['logo', 'brand', 600, true], 'hero_image' => ['hero_image', 'brand', 1600, false]] as $key => [$field, $dir, $w, $alpha]) {
                $up = handle_image_upload($field, $dir, $w, $alpha, false);
                if ($up) { delete_upload(setting($key)); $vals[$key] = $up; }
                elseif (!empty($_POST[$field . '_remove'])) { delete_upload(setting($key)); $vals[$key] = ''; }
            }
            $pdf = handle_pdf_upload('flyer', 'flyer');
            if ($pdf) { delete_upload(setting('flyer_pdf')); $vals['flyer_pdf'] = $pdf; }
            elseif (!empty($_POST['flyer_remove'])) { delete_upload(setting('flyer_pdf')); $vals['flyer_pdf'] = ''; }
        } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); }
    }
    if (!$err) {
        foreach ($vals as $k => $v) set_setting($k, (string)$v);
        flash('Einstellungen gespeichert.');
        redirect(u('admin/einstellungen.php'));
    }
}

$H = hours();
$S = fn(string $k) => $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$k]) && is_string($_POST[$k]) ? $_POST[$k] : setting($k);
admin_head('Einstellungen', 'einstellungen');
foreach ($err as $m) echo '<div class="flash err">' . e($m) . '</div>';
?>
<form method="post" enctype="multipart/form-data" class="form">
  <?= csrf_field() ?>
  <nav class="anchors"><a href="#allgemein">Allgemein</a><a href="#startseite">Startseite</a><a href="#kontakt">Kontakt</a><a href="#zeiten">Öffnungszeiten</a><a href="#design">Design & Logo</a><a href="#prospekt">Prospekt & SEO</a></nav>

  <section class="panel" id="allgemein"><h2>Allgemein</h2>
    <div class="row2"><?= fld('Name des Marktes', 'shop_name', $S('shop_name'), ['maxlength' => 60, 'required' => true]) ?>
      <?= fld('Untertitel / Slogan', 'tagline', $S('tagline'), ['maxlength' => 80]) ?></div>
    <?= checkbox('Hinweis-Banner ganz oben anzeigen', 'announcement_on', $S('announcement_on') === '1') ?>
    <?= fld('Text des Banners', 'announcement', $S('announcement'), ['maxlength' => 200, 'placeholder' => 'z. B. Am 3. Oktober geschlossen · Neu: frische Datteln eingetroffen!']) ?>
  </section>

  <section class="panel" id="startseite"><h2>Startseite</h2>
    <?= fld('Überschrift', 'hero_title', $S('hero_title'), ['maxlength' => 80]) ?>
    <?= fld('Einleitungstext', 'hero_text', $S('hero_text'), ['rows' => 3, 'maxlength' => 300]) ?>
    <?= image_field('Titelbild (optional)', 'hero_image', setting('hero_image'), 'Querformat, z. B. Foto vom Markt oder Obsttheke. Ohne Bild wird eine Angebots-Plakette gezeigt.') ?>
  </section>

  <section class="panel" id="kontakt"><h2>Kontakt & Adresse</h2>
    <?= fld('Straße und Hausnummer', 'street', $S('street'), ['maxlength' => 80, 'autocomplete' => 'off']) ?>
    <div class="row2"><?= fld('PLZ', 'zip', $S('zip'), ['maxlength' => 10]) ?><?= fld('Ort', 'city', $S('city'), ['maxlength' => 60]) ?></div>
    <div class="row3"><?= fld('Telefon', 'phone', $S('phone'), ['type' => 'tel', 'maxlength' => 30]) ?><?= fld('WhatsApp-Nummer', 'whatsapp', $S('whatsapp'), ['maxlength' => 30, 'placeholder' => '+49 170 1234567', 'hint' => 'mit Ländervorwahl']) ?><?= fld('E-Mail', 'email', $S('email'), ['type' => 'email', 'maxlength' => 100]) ?></div>
    <?= fld('Link für „Route planen“ (optional)', 'map_url', $S('map_url'), ['maxlength' => 300, 'hint' => 'Leer = automatisch aus der Adresse. Eigener Google-Maps-Link ist genauer.']) ?>
    <div class="row3"><?= fld('Instagram-Link', 'instagram', $S('instagram'), ['maxlength' => 300]) ?><?= fld('Facebook-Link', 'facebook', $S('facebook'), ['maxlength' => 300]) ?><?= fld('TikTok-Link', 'tiktok', $S('tiktok'), ['maxlength' => 300]) ?></div>
    <?= fld('Zusatztext auf der Kontaktseite (optional)', 'contact_text', $S('contact_text'), ['rows' => 3, 'maxlength' => 1500, 'hint' => 'z. B. Parkplätze, Anfahrt, Lieferung']) ?>
  </section>

  <section class="panel" id="zeiten"><h2>Öffnungszeiten</h2>
    <table class="tbl hours-edit"><tbody>
    <?php foreach (WEEKDAYS as $d => $label): $row = $_SERVER['REQUEST_METHOD'] === 'POST' ? ['closed' => isset($_POST['h'][$d]['closed']), 'open' => $_POST['h'][$d]['open'] ?? $H[$d]['open'], 'close' => $_POST['h'][$d]['close'] ?? $H[$d]['close']] : $H[$d]; ?>
      <tr><th scope="row"><?= e($label) ?></th>
        <td><input type="time" name="h[<?= $d ?>][open]" value="<?= e($row['open']) ?>"> – <input type="time" name="h[<?= $d ?>][close]" value="<?= e($row['close']) ?>"></td>
        <td><label class="check"><input type="checkbox" name="h[<?= $d ?>][closed]" value="1"<?= $row['closed'] ? ' checked' : '' ?>> geschlossen</label></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <p class="hint">Die Seite zeigt automatisch „Jetzt geöffnet“ bzw. „Geschlossen – öffnet morgen um …“.</p>
  </section>

  <section class="panel" id="design"><h2>Design & Logo</h2>
    <?= image_field('Logo (optional)', 'logo', setting('logo'), 'Wird auf dunkelgrünem Hintergrund gezeigt – also helle/farbige Schrift, PNG mit transparentem Hintergrund, am besten Querformat. Ohne Logo wird automatisch ein Schriftzug mit Zweig erzeugt.') ?>
    <div class="colors">
      <?php foreach (['color_bg' => 'Hintergrund (dunkel)', 'color_accent' => 'Akzent (grün)', 'color_gold' => 'Gold (Linien, Banderolen)', 'color_cream' => 'Creme (Schrift auf Dunkel)'] as $k => $label): ?>
        <label class="fld color"><span><?= e($label) ?></span><input type="color" name="<?= $k ?>" value="<?= e(valid_color($S($k), BRAND[$k])) ?>"></label>
      <?php endforeach; ?>
    </div>
    <?= checkbox('Farben auf die Marken-Farben zurücksetzen (beim Speichern)', 'reset_colors', false) ?>
  </section>

  <section class="panel" id="prospekt"><h2>Prospekt & Suchmaschinen</h2>
    <?php if (setting('flyer_pdf')): ?><p>Aktueller Prospekt: <a href="<?= e(upload_url(setting('flyer_pdf'))) ?>" target="_blank" rel="noopener">PDF ansehen ↗</a> <?= checkbox('entfernen', 'flyer_remove', false) ?></p><?php endif; ?>
    <label class="fld"><span>Prospekt als PDF hochladen (max. 10 MB)</span><input type="file" name="flyer" accept="application/pdf"></label>
    <?= fld('Beschriftung des Buttons', 'flyer_title', $S('flyer_title'), ['maxlength' => 60]) ?>
    <?= fld('Beschreibung für Google (ca. 150 Zeichen)', 'meta_description', $S('meta_description'), ['rows' => 2, 'maxlength' => 250]) ?>
  </section>

  <div class="savebar"><button class="btn primary" type="submit">Einstellungen speichern</button></div>
</form>
<?php admin_foot();
