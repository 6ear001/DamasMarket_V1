<?php
require __DIR__ . '/_boot.php';

$addr = trim(implode(', ', array_filter([setting('street'), trim(setting('zip') . ' ' . setting('city'))])));
$mapUrl = setting('map_url') ?: ($addr ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr) : '');
$wa = preg_replace('/\D/', '', setting('whatsapp'));
$tel = preg_replace('/[^+\d]/', '', setting('phone'));

site_head('Kontakt & Öffnungszeiten', 'kontakt');
?>
<section class="page-head"><div class="wrap"><h1>Kontakt & Öffnungszeiten</h1>
  <p class="status-big <?= open_status()['open'] ? 'is-open' : 'is-closed' ?>"><i></i><?= e(open_status()['text']) ?></p></div></section>
<section class="section wrap contact-grid">
  <div class="panel">
    <h2>So erreichst du uns</h2>
    <address>
      <strong><?= e(setting('shop_name')) ?></strong><br>
      <?php if (setting('street')): ?><?= e(setting('street')) ?><br><?php endif; ?>
      <?php if (trim(setting('zip') . setting('city'))): ?><?= e(trim(setting('zip') . ' ' . setting('city'))) ?><br><?php endif; ?>
    </address>
    <?php if (!$addr && !setting('phone') && !setting('email')): ?><p class="muted">Die Kontaktdaten werden im Admin-Bereich unter „Einstellungen“ eingetragen.</p><?php endif; ?>
    <div class="btn-row stack">
      <?php if ($tel): ?><a class="btn btn-dark" href="tel:<?= e($tel) ?>">Anrufen: <?= e(setting('phone')) ?></a><?php endif; ?>
      <?php if ($wa): ?><a class="btn btn-accent" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp schreiben</a><?php endif; ?>
      <?php if (setting('email')): ?><a class="btn btn-outline" href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a><?php endif; ?>
      <?php if ($mapUrl): ?><a class="btn btn-outline" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener">Route planen ↗</a><?php endif; ?>
    </div>
    <?php if (trim(setting('contact_text'))): ?><div class="prose"><?= text_to_html(setting('contact_text')) ?></div><?php endif; ?>
  </div>
  <div class="panel">
    <h2>Öffnungszeiten</h2>
    <?= hours_table(hours()) ?>
  </div>
</section>
<?php site_foot();
