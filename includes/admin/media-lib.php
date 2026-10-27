<?php
// Picture and PDF handling for the admin: validate, re-encode, store in MySQL (table media), usage map, delete guard.
require_once __DIR__ . '/core.php';

const ADMIN_UPLOAD_MAX = 8388608;   // 8 MB per file before processing
const ADMIN_IMAGE_MAX_SIDE = 1920;

// Returns ['data'=>, 'mime'=>, 'width'=>, 'height'=>, 'ext'=>] or a string (Thai error message).
function admin_media_prepare(array $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) { return 'ไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับได้ (' . ini_get('upload_max_filesize') . ')'; }
        return $code === UPLOAD_ERR_NO_FILE ? 'ยังไม่ได้เลือกไฟล์' : 'อัปโหลดไม่สำเร็จ (รหัส ' . (int) $code . ')';
    }
    if (!is_uploaded_file($file['tmp_name']) && !defined('ADMIN_TESTING')) { return 'ไฟล์ไม่ถูกต้อง'; }
    if (filesize($file['tmp_name']) > ADMIN_UPLOAD_MAX) { return 'ไฟล์ใหญ่เกิน 8 MB'; }
    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false || $bytes === '') { return 'อ่านไฟล์ไม่ได้'; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);   // never trust the browser's type or the file name
    if ($mime === 'application/pdf') {
        if (strncmp($bytes, '%PDF-', 5) !== 0) { return 'ไฟล์ PDF ไม่ถูกต้อง'; }
        return ['data' => $bytes, 'mime' => $mime, 'width' => null, 'height' => null, 'ext' => 'pdf'];
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) { return 'รองรับเฉพาะรูป JPG, PNG, WebP, GIF และไฟล์ PDF'; }
    if (!function_exists('imagecreatefromstring')) { return 'เซิร์ฟเวอร์ไม่มี GD จึงประมวลผลรูปไม่ได้'; }
    $info = @getimagesizefromstring($bytes);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 50000000) { return 'รูปใหญ่หรือเสียหายเกินไป'; }
    $img = @imagecreatefromstring($bytes);
    if (!$img) { return 'อ่านรูปไม่ได้'; }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {   // honour camera rotation, then the EXIF is dropped by re-encoding
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
        $rotate = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
        if ($rotate) { $r = imagerotate($img, $rotate, 0); if ($r) { $img = $r; } }
    }
    $w = imagesx($img); $h = imagesy($img);
    $scale = min(1.0, ADMIN_IMAGE_MAX_SIDE / max($w, $h));
    if ($scale < 1.0) {
        $img2 = imagescale($img, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
        if ($img2) { $img = $img2; $w = imagesx($img); $h = imagesy($img); }
    }
    imagealphablending($img, false); imagesavealpha($img, true);
    ob_start();
    if (function_exists('imagewebp')) { imagewebp($img, null, 82); $out = 'image/webp'; $ext = 'webp'; }
    else {
        $flat = imagecreatetruecolor($w, $h); imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true); imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
        imagejpeg($flat, null, 85); $out = 'image/jpeg'; $ext = 'jpg';
    }
    $data = (string) ob_get_clean();
    if ($data === '') { return 'แปลงรูปไม่สำเร็จ'; }
    return ['data' => $data, 'mime' => $out, 'width' => $w, 'height' => $h, 'ext' => $ext];
}

// 1200x630 JPEG share card (same rule as file.php: crop to fill, or place whole on dark purple when it would be enlarged >1.4x).
function admin_share_card(string $data): ?string {
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
        $nw = (int) round($w * $fit); $nh = (int) round($h * $fit);
        imagecopyresampled($card, $source, (int) ((1200 - $nw) / 2), (int) ((630 - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    }
    ob_start(); imagejpeg($card, null, 82);
    return (string) ob_get_clean();
}

function admin_media_filename(string $origName, string $ext, string $data): string {
    $base = pathinfo($origName, PATHINFO_FILENAME);
    $base = clean_slug($base) ?: 'file';
    return 'uploads/' . date('Y-m') . '/' . substr(hash('sha256', $data), 0, 8) . '-' . mb_substr($base, 0, 60) . '.' . $ext;
}

// Stores a processed file (and, for pictures, its share twin). Same bytes already stored -> that row is reused. Returns the media id.
function admin_media_store(array $p, string $origName, string $alt, ?int $userId): int {
    $db = admin_db();
    $hash = hash('sha256', $p['data']);
    $same = $db->prepare('SELECT id FROM media WHERE sha256 = ? AND og_of IS NULL LIMIT 1');
    $same->execute([$hash]);
    if ($id = $same->fetchColumn()) { return (int) $id; }
    $db->beginTransaction();
    try {
        $ins = $db->prepare('INSERT INTO media (filename, original_name, mime, width, height, alt_default, data, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->bindValue(1, admin_media_filename($origName, $p['ext'], $p['data']));
        $ins->bindValue(2, mb_substr($origName, 0, 255));
        $ins->bindValue(3, $p['mime']);
        $ins->bindValue(4, $p['width'], $p['width'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $ins->bindValue(5, $p['height'], $p['height'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $ins->bindValue(6, $alt !== '' ? mb_substr($alt, 0, 400) : null, $alt !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $ins->bindValue(7, $p['data'], PDO::PARAM_LOB);
        $ins->bindValue(8, $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $ins->execute();
        $id = (int) $db->lastInsertId();
        admin_media_twin($id, $p, $db);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
    return $id;
}

function admin_media_twin(int $id, array $p, PDO $db): void {
    if (strpos($p['mime'], 'image/') !== 0) { return; }
    $card = admin_share_card($p['data']);
    if ($card === null) { return; }
    $name = 'uploads/og/' . substr(hash('sha256', $p['data']), 0, 12) . '-' . $id . '.jpg';
    $ins = $db->prepare('INSERT INTO media (filename, mime, width, height, og_of, data) VALUES (?, ?, 1200, 630, ?, ?)');
    $ins->bindValue(1, $name); $ins->bindValue(2, 'image/jpeg'); $ins->bindValue(3, $id, PDO::PARAM_INT); $ins->bindValue(4, $card, PDO::PARAM_LOB);
    $ins->execute();
}

// Keeps the same id (so every post that uses it changes at once); regenerates the share twin.
function admin_media_replace(int $id, array $p, string $origName): void {
    $db = admin_db();
    $db->beginTransaction();
    try {
        $up = $db->prepare('UPDATE media SET mime = ?, width = ?, height = ?, original_name = ?, data = ? WHERE id = ? AND og_of IS NULL');
        $up->bindValue(1, $p['mime']); $up->bindValue(2, $p['width'], $p['width'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $up->bindValue(3, $p['height'], $p['height'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $up->bindValue(4, mb_substr($origName, 0, 255)); $up->bindValue(5, $p['data'], PDO::PARAM_LOB); $up->bindValue(6, $id, PDO::PARAM_INT);
        $up->execute();
        $db->prepare('DELETE FROM media WHERE og_of = ?')->execute([$id]);
        admin_media_twin($id, $p, $db);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $e;
    }
}

// id => list of human-readable places that use the file. Twins count as their parent's.
function admin_media_usage(): array {
    $db = admin_db();
    $use = [];
    $add = function ($id, string $label) use (&$use) { if ($id) { $use[(int) $id][] = $label; } };
    foreach ($db->query('SELECT id, kind, slug, title, image_id, download_id, body FROM posts') as $r) {
        $label = ADMIN_KINDS[$r['kind']] . ': ' . $r['title'];
        $add($r['image_id'], $label . ' (รูปปก)');
        $add($r['download_id'], $label . ' (ไฟล์ดาวน์โหลด)');
        foreach (json_decode((string) $r['body'], true) ?: [] as $b) {
            if (is_array($b) && !empty($b['media'])) { $add($b['media'], $label . ' (ในเนื้อหา)'); }
        }
    }
    foreach ($db->query('SELECT title, image_id, type FROM home_items') as $r) { $add($r['image_id'], 'หน้าแรก: ' . $r['title']); }
    foreach ($db->query('SELECT title, image_id FROM shop_items') as $r) { $add($r['image_id'], 'ร้านค้า: ' . $r['title']); }
    return $use;
}

function admin_media_list(string $type = 'all', string $q = ''): array {
    $where = 'og_of IS NULL'; $args = [];
    if ($type === 'image') { $where .= " AND mime LIKE 'image/%'"; }
    if ($type === 'pdf') { $where .= " AND mime = 'application/pdf'"; }
    if ($q !== '') { $where .= ' AND (filename LIKE ? OR original_name LIKE ? OR alt_default LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; $args = [$like, $like, $like]; }
    $stmt = admin_db()->prepare("SELECT id, filename, original_name, mime, width, height, alt_default, bytes, LEFT(sha256, 8) AS v, created_at FROM media WHERE $where ORDER BY id DESC");
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function admin_media_img(array $row): string { return 'media-img.php?id=' . (int) $row['id'] . '&amp;v=' . e((string) $row['v']); }

// Picture chooser: a filterable grid of radio buttons (thumbnails). $exclude = ids shown elsewhere.
function admin_media_pics(string $name, ?int $current, string $type = 'image', array $exclude = []): void {
    $rows = array_filter(admin_media_list($type), fn($r) => !in_array((int) $r['id'], $exclude, true));
    if (!$rows) { echo '<p class="muted">ยังไม่มีไฟล์ในคลัง</p>'; return; }
    echo '<label class="muted">ค้นหาจากชื่อไฟล์<input type="search" data-filter="pics"></label><div class="pics pics--scroll" data-filter-list="pics">';
    foreach ($rows as $r) {
        echo '<label data-filter-text="' . e(strtolower($r['filename'] . ' ' . $r['alt_default'])) . '"><input type="radio" name="' . e($name) . '" value="' . (int) $r['id'] . '"' . ($current === (int) $r['id'] ? ' checked' : '') . '>';
        echo strpos($r['mime'], 'image/') === 0 ? '<img src="' . admin_media_img($r) . '" alt="" loading="lazy">' : '<span class="tag">PDF</span>';
        echo '<span>' . e(basename($r['filename'])) . '</span></label>';
    }
    echo '</div>';
}
