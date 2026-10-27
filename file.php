<?php
// Sends one image or PDF from the MySQL table `media`: file.php?id=12&v=<hash prefix>[&og=1].
// Addresses are made by media_url() in includes/media-store.php; pages never build them by hand.
//
// - 404 for an unknown id, a type outside MEDIA_TYPES, a file used only by hidden posts/items, or while content comes
//   from data/*.json. 503 (with Retry-After) when MySQL cannot be reached.
// - ETag is the SHA-256 of the bytes; If-None-Match answers 304 without reading the bytes.
// - With v= matching the current bytes the answer may be cached for a year (the address changes when the file does);
//   without it, for five minutes.
// - og=1 on an image without a stored share twin draws a 1200x630 JPEG with GD (cropped to fill, or placed whole on the
//   theme's dark purple when it would have to be enlarged more than 1.4x, as tools/make_og_images.py does).
// - No page header, no visit counting.
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/media-store.php';

function media_fail(int $status): never {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if ($status === 503) { header('Retry-After: 300'); }
    echo $status === 503 ? 'ฐานข้อมูลขัดข้อง กรุณาลองใหม่อีกครั้ง' : 'ไม่พบไฟล์';
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { header('Allow: GET, HEAD'); media_fail(405); }
if (content_source() !== 'mysql') { media_fail(404); }
if (!content_pdo()) { media_fail(503); }

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
$row = $id ? media_row($id) : null;
if (!$row) { media_fail(404); }
try { $hidden = media_hidden_ids(); } catch (Throwable $exception) { error_log('edu2impact: file.php ' . $exception->getMessage()); media_fail(503); }
if (isset($hidden[$row['id']])) { media_fail(404); }

$og = ($_GET['og'] ?? '') === '1';
if ($og) {
    // A stored twin wins; otherwise draw one, if this server's GD can.
    $twin = null;
    foreach (media_rows() as $candidate) {
        if ($candidate['og_of'] === $row['id'] && $candidate['mime'] === 'image/jpeg') { $twin = $candidate; break; }
    }
    if ($twin) { $row = $twin; $og = false; }
    elseif (!media_can_draw_share($row['mime'])) { media_fail(404); }
}

$etag = '"' . $row['sha256'] . ($og ? '-og' : '') . '"';
$versioned = is_string($_GET['v'] ?? null) && $_GET['v'] !== '' && str_starts_with((string) $row['sha256'], $_GET['v']) && strlen($_GET['v']) >= 8;
header('ETag: ' . $etag);
header('Cache-Control: public, ' . ($versioned ? 'max-age=31536000, immutable' : 'max-age=300'));
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
$match = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
if ($match !== '' && ($match === '*' || in_array($etag, array_map('trim', explode(',', $match)), true))) {
    http_response_code(304);
    exit;
}

try {
    $statement = content_pdo()->prepare('SELECT data FROM media WHERE id = ?');
    $statement->execute([$row['id']]);
    $data = $statement->fetchColumn();
} catch (Throwable $exception) {
    error_log('edu2impact: file.php cannot read media ' . $row['id'] . ' (' . $exception->getMessage() . ')');
    media_fail(503);
}
if (!is_string($data) || $data === '') { media_fail(404); }

// Never trust the stored mime alone: the bytes must be the type they claim.
$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
if (!in_array($mime, MEDIA_TYPES, true)) { media_fail(404); }

if ($og) {
    $data = media_draw_share($data);
    if ($data === null) { media_fail(404); }
    $mime = 'image/jpeg';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));
if ($mime === 'application/pdf') {
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string) $row['filename'])) ?: 'document';
    if (!str_ends_with(strtolower($name), '.pdf')) { $name .= '.pdf'; }
    header('Content-Disposition: inline; filename="' . $name . '"');
} else {
    // An image can never run script, even if opened on its own.
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') { exit; }
echo $data;

// 1200x630 JPEG from image bytes, or null when GD cannot read them.
function media_draw_share(string $data): ?string {
    $source = @imagecreatefromstring($data);
    if (!$source) { return null; }
    $w = imagesx($source); $h = imagesy($source);
    $card = imagecreatetruecolor(1200, 630);
    $scale = max(1200 / $w, 630 / $h);
    if ($scale <= 1.4) {
        $cropW = (int) round(1200 / $scale); $cropH = (int) round(630 / $scale);
        imagecopyresampled($card, $source, 0, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), 1200, 630, $cropW, $cropH);
    } else {
        imagefill($card, 0, 0, imagecolorallocate($card, 44, 16, 56));
        $fit = min(1200 / $w, 630 / $h, 1.0);
        $newW = (int) round($w * $fit); $newH = (int) round($h * $fit);
        imagecopyresampled($card, $source, (int) ((1200 - $newW) / 2), (int) ((630 - $newH) / 2), 0, 0, $newW, $newH, $w, $h);
    }
    ob_start();
    imagejpeg($card, null, 82);
    return (string) ob_get_clean();
}
