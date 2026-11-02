<?php
declare(strict_types=1);

/**
 * Bild-Upload: prüft den echten Dateityp, lädt das Bild über GD neu (entfernt Schadcode/Metadaten),
 * verkleinert es und legt zusätzlich ein Vorschaubild an.
 * @return string|null  relativer Pfad (z. B. "products/ab12.webp") oder null, wenn nichts hochgeladen wurde
 * @throws RuntimeException mit verständlicher Meldung
 */
function handle_image_upload(string $field, string $subdir, int $maxW = 1400, bool $keepAlpha = false, bool $thumb = true): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'Das Bild ist zu groß (max. 8 MB).' : 'Upload fehlgeschlagen (Fehlercode ' . (int)$f['error'] . ').');
    }
    if ($f['size'] > 8 * 1024 * 1024) throw new RuntimeException('Das Bild ist zu groß (max. 8 MB).');
    if (!is_uploaded_file($f['tmp_name']) && PHP_SAPI !== 'cli') throw new RuntimeException('Ungültiger Upload.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) throw new RuntimeException('Bitte ein Bild als JPG, PNG, WebP oder GIF hochladen.');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) throw new RuntimeException('Das Bild ist ungültig oder hat zu viele Pixel.');

    $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$img) throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $ex = @exif_read_data($f['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$ex['Orientation'] ?? 0] ?? 0;
        if ($rot) { $r = imagerotate($img, $rot, 0); if ($r) { imagedestroy($img); $img = $r; } }
    }

    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Der Ordner uploads ist nicht beschreibbar.');
    $useWebp = !$keepAlpha && function_exists('imagewebp');
    $ext = $keepAlpha ? 'png' : ($useWebp ? 'webp' : 'jpg');
    $base = bin2hex(random_bytes(8));
    $save = function ($src, int $w, string $path) use ($keepAlpha, $useWebp): void {
        $sw = imagesx($src); $sh = imagesy($src);
        $nw = min($w, $sw); $nh = (int)round($sh * $nw / $sw);
        $dst = imagecreatetruecolor($nw, $nh);
        if ($keepAlpha) { imagealphablending($dst, false); imagesavealpha($dst, true); imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127)); }
        else imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
        $ok = $keepAlpha ? imagepng($dst, $path, 6) : ($useWebp ? imagewebp($dst, $path, 82) : imagejpeg($dst, $path, 85));
        imagedestroy($dst);
        if (!$ok) throw new RuntimeException('Das Bild konnte nicht gespeichert werden.');
        @chmod($path, 0644);
    };
    $save($img, $maxW, "$dir/$base.$ext");
    if ($thumb) $save($img, 480, "$dir/{$base}_t.$ext");
    imagedestroy($img);
    return "$subdir/$base.$ext";
}

function handle_pdf_upload(string $field, string $subdir): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('PDF-Upload fehlgeschlagen (zu groß? max. 10 MB).');
    if ($f['size'] > 10 * 1024 * 1024) throw new RuntimeException('Das PDF ist zu groß (max. 10 MB).');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 5);
    if ($mime !== 'application/pdf' || $head !== '%PDF-') throw new RuntimeException('Bitte eine PDF-Datei hochladen.');
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Der Ordner uploads ist nicht beschreibbar.');
    $name = 'prospekt-' . date('Ymd') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name") && !(PHP_SAPI === 'cli' && copy($f['tmp_name'], "$dir/$name"))) throw new RuntimeException('Das PDF konnte nicht gespeichert werden.');
    @chmod("$dir/$name", 0644);
    return "$subdir/$name";
}

/** Löscht eine hochgeladene Datei samt Vorschaubild – nur innerhalb von uploads/. */
function delete_upload(?string $rel): void {
    if (!$rel || !preg_match('~^[a-z]+/[a-z0-9_.-]+$~i', $rel)) return;
    foreach ([$rel, preg_replace('/(\.[a-z0-9]+)$/i', '_t$1', $rel)] as $r) {
        $p = UPLOAD_DIR . '/' . $r;
        if (is_file($p)) @unlink($p);
    }
}
