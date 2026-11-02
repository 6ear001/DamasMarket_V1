<?php
require __DIR__ . '/_boot.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['s'] ?? ''));
$page = $slug ? page_row($slug) : null;
if (!$page) {
    http_response_code(404);
    site_head('Nicht gefunden', '');
    echo '<section class="section wrap"><h1>Seite nicht gefunden</h1><p><a href="' . e(u('index.php')) . '">Zur Startseite</a></p></section>';
    site_foot();
    exit;
}
site_head($page['title'], '');
?>
<section class="page-head"><div class="wrap"><h1><?= e($page['title']) ?></h1></div></section>
<section class="section wrap"><div class="prose narrow"><?= text_to_html($page['content']) ?></div></section>
<?php site_foot();
