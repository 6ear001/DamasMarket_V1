<?php
// Nachbau von Open Food Facts für Tests (php -S 127.0.0.1:9001 tests/mock_off.php):
//  gerade letzte Ziffer  -> Bild vorhanden;  ungerade -> "nicht gefunden"
//  Barcode 4251861800230 -> Bild-URL liefert HTML statt Bild;  Barcode 5601537806032 -> Bild-URL zeigt auf fremden Server
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/api/v2/product/(\d+)\.json$~', $uri, $m)) {
    $bc = $m[1];
    header('Content-Type: application/json');
    $last = (int)substr($bc, -1);
    if ($bc === '5601537806032') { echo json_encode(['status' => 1, 'product' => ['image_front_url' => 'https://evil.example.com/x.jpg']]); exit; }
    if ($bc === '4251861800230' || ($last % 2 === 0 && strlen($bc) !== 12)) { echo json_encode(['status' => 1, 'product' => ['image_front_url' => "http://127.0.0.1:9001/img/$bc.jpg"]]); exit; }
    http_response_code(404); echo json_encode(['status' => 0]); exit;
}
if (preg_match('~^/img/(\d+)\.jpg$~', $uri, $m)) {
    if ($m[1] === '4251861800230') { header('Content-Type: image/jpeg'); echo '<html>kein Bild</html>'; exit; }
    $im = imagecreatetruecolor(1000, 1400); $h = crc32($m[1]);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagefilledrectangle($im, 200, 300, 800, 1100, imagecolorallocate($im, $h & 255, ($h >> 8) & 255, ($h >> 16) & 255));
    header('Content-Type: image/jpeg'); imagejpeg($im); exit;
}
http_response_code(404);
