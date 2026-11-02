<?php
declare(strict_types=1);

/** Beispieldaten zum Ausprobieren (alles mit is_demo = 1, lässt sich mit einem Klick wieder entfernen). */
function demo_load(): int {
    $pdo = db();
    $cat = [];
    foreach ($pdo->query('SELECT id, name FROM categories') as $r) $cat[$r['name']] = (int)$r['id'];
    $items = [
        // name, name_alt, Kategorie, Einheit, Preis(ct), Herkunft, Beschreibung, neu
        ['Basmati Reis', 'أرز بسمتي', 'Reis, Bulgur & Getreide', '1 kg', 349, 'Indien', 'Langkorn-Reis, locker und aromatisch – ideal zu Fleischgerichten und Pilaw.', 0],
        ['Bulgur grob', 'برغل خشن', 'Reis, Bulgur & Getreide', '1 kg', 229, 'Türkei', 'Grober Bulgur für Pilaw und Köfte.', 0],
        ['Rote Linsen', 'عدس أحمر', 'Linsen & Hülsenfrüchte', '500 g', 189, 'Türkei', '', 0],
        ['Kichererbsen getrocknet', 'حمص', 'Linsen & Hülsenfrüchte', '1 kg', 329, 'Türkei', '', 0],
        ['Tahin Sesampaste', 'طحينة', 'Öle, Oliven & Eingelegtes', '450 g', 459, 'Libanon', 'Cremige Sesampaste für Hummus, Saucen und Desserts.', 1],
        ['Natives Olivenöl extra', 'زيت زيتون', 'Öle, Oliven & Eingelegtes', '1 l', 1199, 'Syrien', 'Kaltgepresst, fruchtig-mild.', 0],
        ['Gefüllte Weinblätter', 'ورق عنب', 'Öle, Oliven & Eingelegtes', '400 g', 299, 'Türkei', '', 0],
        ['Za\'atar Gewürzmischung', 'زعتر', 'Gewürze & Kräuter', '200 g', 279, 'Libanon', 'Thymian, Sesam und Sumak – klassisch zu Fladenbrot mit Olivenöl.', 1],
        ['Sumak', 'سماق', 'Gewürze & Kräuter', '100 g', 199, 'Türkei', '', 0],
        ['Baharat 7-Gewürze', 'بهارات', 'Gewürze & Kräuter', '100 g', 229, 'Syrien', '', 0],
        ['Feta Hirtenkäse', 'جبنة', 'Milchprodukte & Käse', '400 g', 349, 'Türkei', 'Würziger Käse in Lake.', 0],
        ['Sucuk (halal)', 'سجق', 'Fleisch & Wurst (halal)', '250 g', 399, 'Deutschland', 'Würzige Knoblauchwurst, halal.', 0],
        ['Baklava gemischt', 'بقلاوة', 'Süßwaren & Gebäck', '500 g', 1099, 'Türkei', 'Mit Pistazien und Walnüssen.', 1],
        ['Halva Vanille', 'حلاوة', 'Süßwaren & Gebäck', '400 g', 449, 'Libanon', '', 0],
        ['Türkischer Schwarztee', 'شاي', 'Tee & Kaffee', '500 g', 649, 'Türkei', '', 0],
        ['Mokka-Kaffee', 'قهوة', 'Tee & Kaffee', '250 g', 549, 'Türkei', 'Fein gemahlen für Cezve und Ibrik.', 0],
        ['Ayran', 'عيران', 'Ayran, Joghurtdrinks & Wasser', '1 l', 129, 'Türkei', '', 0],
        ['Granatapfelsirup', 'دبس الرمان', 'Softdrinks & Säfte', '500 ml', 379, 'Syrien', '', 0],
        ['Medjool Datteln', 'تمر مجدول', 'Obst & Gemüse', '500 g', 599, 'Jordanien', 'Groß, weich und natürlich süß.', 1],
        ['Pistazien geröstet', 'فستق', 'Obst & Gemüse', '250 g', 899, 'Türkei', '', 0],
        ['Cezve Kaffeekännchen', 'ركوة', 'Tee- & Kaffeezubehör', '', 1299, '', 'Traditionelles Kännchen für türkischen Kaffee.', 0],
        ['Teegläser 6er-Set', 'أكواب شاي', 'Tee- & Kaffeezubehör', '6 Stück', 899, '', '', 0],
        ['Geschenkbox Baklava & Tee', '', 'Geschenkboxen', '', 1990, 'Türkei', 'Liebevoll verpackt – ideal als Mitbringsel.', 1],
    ];
    $ins = $pdo->prepare('INSERT INTO products (name, name_alt, category_id, unit, price_cents, origin, description, is_new, is_demo, sort) VALUES (?,?,?,?,?,?,?,?,1,?)');
    $ids = [];
    $pdo->beginTransaction();
    foreach ($items as $i => [$n, $alt, $c, $unit, $price, $origin, $desc, $new]) {
        $ins->execute([$n, $alt, $cat[$c] ?? null, $unit, $price, $origin, $desc, $new, ($i + 1) * 10]);
        $ids[$n] = (int)$pdo->lastInsertId();
    }
    $from = date('Y-m-d', strtotime('monday this week'));
    $to = date('Y-m-d', strtotime('sunday this week'));
    $nextFrom = date('Y-m-d', strtotime('monday next week'));
    $nextTo = date('Y-m-d', strtotime('sunday next week'));
    $off = $pdo->prepare('INSERT INTO offers (product_id, title, unit_text, price_cents, old_price_cents, badge, valid_from, valid_to, sort, is_demo) VALUES (?,?,?,?,?,?,?,?,?,1)');
    $offers = [
        ['Basmati Reis', 'Basmati Reis', '1 kg', 279, 349, '', $from, $to],
        ['Tahin Sesampaste', 'Tahin Sesampaste', '450 g', 399, 459, '', $from, $to],
        ['Baklava gemischt', 'Baklava gemischt', '500 g', 899, 1099, '', $from, $to],
        ['Feta Hirtenkäse', 'Feta Hirtenkäse', '400 g', 299, 349, '', $from, $to],
        ['Medjool Datteln', 'Medjool Datteln', '500 g', 499, 599, '2 für 9 €', $from, $to],
        ['Natives Olivenöl extra', 'Natives Olivenöl extra', '1 l', 999, 1199, '', $from, $to],
        ['Ayran', 'Ayran 6er-Pack', '6 × 1 l', 649, 774, '', $from, $to],
        ['Mokka-Kaffee', 'Mokka-Kaffee', '250 g', 449, 549, '', $nextFrom, $nextTo],
    ];
    foreach ($offers as $i => [$pn, $title, $unit, $p, $old, $badge, $f, $t]) $off->execute([$ids[$pn] ?? null, $title, $unit, $p, $old, $badge, $f, $t, ($i + 1) * 10]);
    $pdo->commit();
    return count($items);
}

function demo_remove(): int {
    $pdo = db();
    $pdo->prepare('DELETE FROM offers WHERE is_demo = 1')->execute();
    $st = $pdo->prepare('DELETE FROM products WHERE is_demo = 1');
    $st->execute();
    return $st->rowCount();
}
