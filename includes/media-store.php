<?php
// Images and PDFs stored in the MySQL table `media` (database/updates/2026-10-27-01-structure.sql).
//
// A record names a file in one of two ways:
//   "media:12"                    row 12 of the media table (what content-db.php returns for posts.image_id, {"media": 12}
//                                 body blocks, home_items.image_id, shop_items.image_id and posts.download_id)
//   "assets/images/x.webp"        the old path. When a media row has exactly that filename (the migration keeps the old
//                                 paths as filenames) the row is used, otherwise the file on disk, as before.
// Either way the page gets an address it can put in src/href: file.php?id=12&v=<first 16 hex of the SHA-256>, or the
// file path. The v= part changes whenever the bytes change, so file.php can let browsers cache for a year.
//
// Nothing here reads the bytes except media_fill_missing() for rows whose mime/width/height were left empty in phpMyAdmin.
require_once __DIR__ . '/content-db.php';

const MEDIA_TYPES = ['image/webp', 'image/jpeg', 'image/png', 'application/pdf'];

// Every media row without its data, keyed by id; [] while content comes from JSON or the table does not exist yet.
function media_rows(): array {
    static $rows = null;
    if ($rows !== null) { return $rows; }
    $rows = [];
    if (content_source() !== 'mysql' || !($pdo = content_pdo())) { return $rows; }
    try {
        foreach ($pdo->query('SELECT id, filename, mime, width, height, alt_default, og_of, sha256, bytes FROM media ORDER BY id') as $row) {
            $row['id'] = (int) $row['id'];
            $row['og_of'] = $row['og_of'] === null ? null : (int) $row['og_of'];
            $rows[$row['id']] = $row;
        }
    } catch (Throwable $exception) {
        // Before the round 13 migration the table is missing: old paths keep working from disk.
        if (!content_db_missing_table($exception)) { error_log('edu2impact: cannot read media (' . $exception->getMessage() . ')'); }
        return $rows = [];
    }
    media_fill_missing($pdo, $rows);
    return $rows;
}

// Rows added in phpMyAdmin with only the file chosen: work out mime, width and height from the bytes, for this request only
// (the public site's database user can only SELECT). Filling the columns in phpMyAdmin saves this work.
function media_fill_missing(PDO $pdo, array &$rows): void {
    $ids = [];
    foreach ($rows as $id => $row) {
        if ($row['mime'] === null || $row['mime'] === '' || ($row['width'] === null && $row['mime'] !== 'application/pdf')) { $ids[] = $id; }
    }
    if (!$ids) { return; }
    $statement = $pdo->prepare('SELECT data FROM media WHERE id = ?');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($ids as $id) {
        $statement->execute([$id]);
        $data = $statement->fetchColumn();
        $statement->closeCursor();
        if (!is_string($data)) { continue; }
        $rows[$id]['mime'] = (string) $finfo->buffer($data);
        if (str_starts_with($rows[$id]['mime'], 'image/') && ($size = @getimagesizefromstring($data))) {
            $rows[$id]['width'] = $size[0];
            $rows[$id]['height'] = $size[1];
        }
    }
}

function media_by_filename(string $filename): ?array {
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (media_rows() as $row) { $index[$row['filename']] = $row; }
    }
    return $index[$filename] ?? null;
}

// The media row a reference points at ("media:12", 12, or an old path stored as a filename), or null.
function media_row($ref): ?array {
    if (is_int($ref) || (is_string($ref) && preg_match('/\A(?:media:)?([1-9][0-9]{0,9})\z/', $ref))) {
        $id = is_int($ref) ? $ref : (int) preg_replace('/\D/', '', $ref);
        $row = media_rows()[$id] ?? null;
    } elseif (is_string($ref) && $ref !== '') {
        $row = media_by_filename($ref);
    } else {
        $row = null;
    }
    return $row && in_array($row['mime'], MEDIA_TYPES, true) ? $row : null;
}

function media_url(array $row, bool $og = false): string {
    return 'file.php?id=' . $row['id'] . ($og ? '&og=1' : '') . '&v=' . substr((string) $row['sha256'], 0, 16);
}

// The row behind an address made by media_url(), or null for anything else.
function media_row_from_url(string $url): ?array {
    return preg_match('/\Afile\.php\?id=([1-9][0-9]{0,9})(?:&|\z)/', $url, $match) ? media_row((int) $match[1]) : null;
}

// Address of an image reference for src="…": a media row (image types only) or a local file, '' when neither exists.
function media_image_src($ref): string {
    if (!is_string($ref) && !is_int($ref)) { return ''; }
    $row = media_row($ref);
    if ($row) { return str_starts_with($row['mime'], 'image/') ? media_url($row) : ''; }
    if (is_string($ref) && preg_match('~\Aassets/images/[a-zA-Z0-9_/-]+\.(?:png|jpg|jpeg|webp)\z~', $ref)
        && is_file(__DIR__ . '/../' . $ref)) { return $ref; }
    return '';
}

// [width, height] of an address returned by media_image_src(); [0, 0] when unknown.
function media_image_size(string $src): array {
    static $sizes = [];
    if (isset($sizes[$src])) { return $sizes[$src]; }
    $row = media_row_from_url($src);
    if ($row) { return $sizes[$src] = [(int) $row['width'], (int) $row['height']]; }
    if ($src !== '' && !str_contains($src, '..') && is_file(__DIR__ . '/../' . $src)) {
        $size = @getimagesize(__DIR__ . '/../' . $src);
        if ($size) { return $sizes[$src] = [(int) $size[0], (int) $size[1]]; }
    }
    return $sizes[$src] = [0, 0];
}

// The 1200x630 JPEG share twin of a cover row: a stored twin (og_of), else one file.php draws with GD, else null.
function media_share_src(array $cover): ?string {
    foreach (media_rows() as $row) {
        if ($row['og_of'] === $cover['id'] && $row['mime'] === 'image/jpeg') { return media_url($row); }
    }
    if (media_can_draw_share($cover['mime'])) { return media_url($cover, true); }
    return null;
}

// True when GD on this server can read the cover's format and write JPEG.
function media_can_draw_share(string $mime): bool {
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) { return false; }
    $types = imagetypes();
    return match ($mime) {
        'image/jpeg' => (bool) ($types & IMG_JPG),
        'image/png' => (bool) ($types & IMG_PNG) && (bool) ($types & IMG_JPG),
        'image/webp' => defined('IMG_WEBP') && (bool) ($types & IMG_WEBP) && (bool) ($types & IMG_JPG),
        default => false,
    };
}

// Ids of media used only by hidden things (posts with is_public = 0, hidden home items or shop items), plus admin uploads nobody public uses yet.
// file.php answers 404 for these; a file shared with anything public is still served. Share twins follow their cover.
function media_hidden_ids(): array {
    $pdo = content_pdo();
    if (!$pdo) { return []; }
    $public = []; $hidden = [];
    $note = static function ($ref, bool $isPublic) use (&$public, &$hidden): void {
        $row = media_row($ref);
        if (!$row) { return; }
        if ($isPublic) { $public[$row['id']] = true; } else { $hidden[$row['id']] = true; }
    };
    try {
        foreach ($pdo->query('SELECT * FROM posts') as $post) {
            $isPublic = (int) $post['is_public'] === 1;
            $note(isset($post['image_id']) ? (int) $post['image_id'] : 0, $isPublic);
            $note((string) $post['image'], $isPublic);
            if (isset($post['download_id'])) { $note((int) $post['download_id'], $isPublic); }
            foreach (content_db_json($post['body']) ?? [] as $block) {
                if (is_array($block)) { $note($block['media'] ?? ($block['image'] ?? ''), $isPublic); }
            }
        }
        foreach (['home_items', 'shop_items'] as $table) {
            foreach ($pdo->query('SELECT image_id, image, is_public FROM ' . $table) as $item) {
                $note((int) $item['image_id'], (int) $item['is_public'] === 1);
                $note((string) $item['image'], (int) $item['is_public'] === 1);
            }
        }
    } catch (Throwable $exception) {
        if (!content_db_missing_table($exception)) { throw $exception; }
    }
    $ids = array_diff_key($hidden, $public);
    // A file uploaded in the admin that no public record uses yet (not attached, or attached only to a draft) stays private.
    // Rows from the migration have uploaded_by NULL and are not touched. Before the admin migration the column does not exist.
    try {
        foreach ($pdo->query('SELECT id FROM media WHERE uploaded_by IS NOT NULL AND og_of IS NULL') as $upload) {
            if (!isset($public[(int) $upload['id']])) { $ids[(int) $upload['id']] = true; }
        }
    } catch (Throwable $exception) {
        if (!content_db_missing_table($exception) && !str_contains($exception->getMessage(), 'uploaded_by')) { throw $exception; }
    }
    foreach (media_rows() as $row) {
        if ($row['og_of'] !== null && isset($ids[$row['og_of']])) { $ids[$row['id']] = true; }
    }
    return $ids;
}
