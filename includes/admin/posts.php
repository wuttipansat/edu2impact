<?php
// Posts for the admin: load, read the form, validate, save with revisions and an optimistic lock, render the form.
require_once __DIR__ . '/media-lib.php';
require_once dirname(__DIR__) . '/visits.php';

const POST_COLUMNS = ['kind', 'slug', 'title', 'summary', 'body', 'refs', 'audience', 'subjects', 'features', 'image', 'image_id', 'post_date',
    'author', 'category', 'tool_url', 'tool_label', 'download_id', 'download_label', 'featured', 'is_public', 'sort_order'];

function admin_post_blank(string $kind): array {
    return ['id' => null, 'kind' => $kind, 'slug' => '', 'title' => '', 'summary' => '', 'post_date' => date('Y-m-d'), 'author' => '', 'category' => '',
        'tool_url' => '', 'tool_label' => '', 'download_id' => null, 'download_label' => '', 'featured' => 0, 'is_public' => 0, 'sort_order' => null,
        'image_choice' => 'none', 'audience_text' => '', 'subjects_text' => '', 'features_text' => '', 'refs_text' => '', 'related' => [],
        'body' => [], 'content' => '', 'images' => [], 'to_top' => 0, 'was_public' => 0, 'upload_errors' => [], 'loaded_at' => '', 'force' => 0];
}

function admin_lines_to_list(string $text): array {
    $out = [];
    foreach (explode("\n", $text) as $line) { $line = trim(preg_replace('/\s+/u', ' ', $line) ?? ''); if ($line !== '') { $out[] = mb_substr($line, 0, 1000); } }
    return $out;
}
function admin_list_to_lines(?string $json): string {
    $list = json_decode((string) $json, true);
    return is_array($list) ? implode("\n", array_map('strval', $list)) : '';
}

// Row from the table -> form state
function admin_post_state(array $row, array $links): array {
    $p = admin_post_blank($row['kind']);
    foreach (['id', 'slug', 'title', 'summary', 'post_date', 'author', 'category', 'tool_url', 'tool_label', 'download_label', 'sort_order'] as $k) { $p[$k] = $row[$k] ?? $p[$k]; }
    foreach (['summary', 'author', 'category', 'tool_url', 'tool_label', 'download_label', 'post_date'] as $k) { $p[$k] = (string) ($row[$k] ?? ''); }
    $p['featured'] = (int) $row['featured']; $p['is_public'] = (int) $row['is_public'];
    $p['download_id'] = $row['download_id'] ? (int) $row['download_id'] : null;
    $p['image_choice'] = $row['image_id'] ? (string) (int) $row['image_id'] : (($row['image'] ?? '') !== '' ? 'keep' : 'none');
    $p['audience_text'] = admin_list_to_lines($row['audience']); $p['subjects_text'] = admin_list_to_lines($row['subjects']);
    $p['features_text'] = admin_list_to_lines($row['features']); $p['refs_text'] = admin_list_to_lines($row['refs']);
    $p['related'] = array_map(fn($l) => $l['to_kind'] . '/' . $l['to_slug'], $links);
    $p['loaded_at'] = (string) ($row['loaded_at'] ?? '');
    $blocks = [];
    $find = admin_db()->prepare('SELECT id FROM media WHERE filename = ? AND og_of IS NULL');
    foreach (json_decode((string) $row['body'], true) ?: [] as $b) {
        if (is_string($b)) { $blocks[] = ['t' => 'p', 'text' => $b]; continue; }
        if (!is_array($b)) { continue; }
        $media = isset($b['media']) ? (int) $b['media'] : 0;
        if (!$media && !empty($b['image']) && is_string($b['image'])) {
            if (preg_match('/\Amedia:(\d+)\z/', $b['image'], $m)) { $media = (int) $m[1]; }
            else { $find->execute([$b['image']]); $media = (int) $find->fetchColumn(); }
        }
        $blocks[] = ['t' => 'img', 'media' => $media ?: null, 'alt' => (string) ($b['alt'] ?? ''), 'caption' => (string) ($b['caption'] ?? '')];
    }
    $p['body'] = $blocks;
    [$p['content'], $p['images']] = admin_body_to_text($blocks);
    $p['was_public'] = $p['is_public'];
    return $p;
}

// Body blocks -> one text (paragraphs separated by a blank line, a picture is the line [รูป N]) and the picture list.
function admin_body_to_text(array $blocks): array {
    $parts = []; $images = []; $n = 0;
    foreach ($blocks as $b) {
        if ($b['t'] === 'p') { $parts[] = $b['text']; continue; }
        $n++; $images[] = ['n' => $n, 'media' => $b['media'], 'alt' => $b['alt'], 'caption' => $b['caption']];
        $parts[] = '[รูป ' . $n . ']';
    }
    return [implode("\n\n", $parts), $images];
}

// Text + pictures -> body blocks. Pictures the text never mentions go at the end. Returns [blocks, error|null].
function admin_body_from_text(string $text, array $images): array {
    $byN = []; foreach ($images as $im) { $byN[$im['n']] = $im; }
    $blocks = []; $used = [];
    foreach (preg_split('/\n[ \t]*\n/u', $text) ?: [] as $para) {
        $para = trim($para);
        if ($para === '') { continue; }
        if (preg_match('/\A\[รูป\s*(\d+)\]\z/u', $para, $m)) {
            $n = (int) $m[1];
            if (!isset($byN[$n])) { return [[], 'ในเนื้อหามี [รูป ' . $n . '] แต่ไม่มีรูปที่ ' . $n . ' ลบบรรทัดนั้นหรือเพิ่มรูป']; }
            if (isset($used[$n])) { continue; }
            $used[$n] = true; $blocks[] = ['t' => 'img'] + $byN[$n]; continue;
        }
        $blocks[] = ['t' => 'p', 'text' => $para];
    }
    foreach ($byN as $n => $im) { if (!isset($used[$n])) { $blocks[] = ['t' => 'img'] + $im; } }
    return [$blocks, null];
}

function admin_post_load(int $id): ?array {
    $db = admin_db();
    $st = $db->prepare('SELECT *, COALESCE(updated_at, created_at) AS loaded_at FROM posts WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { return null; }
    $l = $db->prepare('SELECT to_kind, to_slug FROM post_links WHERE from_kind = ? AND from_slug = ? ORDER BY sort_order');
    $l->execute([$row['kind'], $row['slug']]);
    return admin_post_state($row, $l->fetchAll());
}

// One uploaded picture/PDF -> media id. Returns [id|null, error|null]; "no file chosen" is [null, null].
function admin_post_upload(?array $file, string $alt = ''): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return [null, null]; }
    $prep = admin_media_prepare($file);
    if (is_string($prep)) { return [null, basename((string) ($file['name'] ?? '')) . ': ' . $prep]; }
    try { return [admin_media_store($prep, basename((string) $file['name']), $alt, (int) (auth_user()['id'] ?? 0) ?: null), null]; }
    catch (Throwable $e) { error_log('edu2impact admin upload: ' . $e->getMessage()); return [null, 'อัปโหลดไม่สำเร็จ: ฐานข้อมูลไม่รับไฟล์ (อาจใหญ่เกินไป)']; }
}
function admin_file_at(string $field, $i = null): ?array {
    $f = $_FILES[$field] ?? null;
    if (!$f || !isset($f['name'])) { return null; }
    if ($i === null) { return is_array($f['name']) ? null : $f; }
    if (!is_array($f['name']) || !isset($f['name'][$i])) { return null; }
    return ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
}

// Link name for a new post: English words of the title, else <kind>-<date>; made unique per kind with -2, -3.
function admin_unique_slug(string $kind, string $base, ?int $exceptId): string {
    $base = clean_slug($base); $slug = $base; $i = 1;
    $st = admin_db()->prepare('SELECT COUNT(*) FROM posts WHERE kind = ? AND slug = ? AND id <> ?');
    while (true) {
        $st->execute([$kind, $slug, (int) $exceptId]);
        if (!(int) $st->fetchColumn()) { return $slug; }
        $slug = mb_substr($base, 0, 110) . '-' . (++$i);
    }
}
function admin_auto_slug(string $kind, string $title): string {
    preg_match_all('/[A-Za-z0-9]+/', $title, $m);
    $base = mb_substr(strtolower(implode('-', $m[0])), 0, 80);
    return admin_unique_slug($kind, $base !== '' ? $base : $kind . '-' . date('Ymd'), null);
}

// POST -> form state (never trusted; validated later). $upload = false for preview (nothing is stored).
function admin_post_from_input(bool $upload = true): array {
    $kind = in_str('kind');
    $p = admin_post_blank(isset(ADMIN_KINDS[$kind]) ? $kind : '');
    $op = in_str('op');
    $p['id'] = in_int('id');
    $p['slug'] = in_line('slug', 120); $p['title'] = in_line('title', 400); $p['summary'] = in_str('summary', 4000);
    $p['post_date'] = in_str('post_date', 10); $p['author'] = in_line('author', 255); $p['category'] = in_line('category', 100);
    $p['tool_url'] = in_line('tool_url', 500); $p['tool_label'] = in_line('tool_label', 150);
    $p['download_id'] = in_int('download_id') ?: null; $p['download_label'] = in_line('download_label', 150);
    $p['featured'] = in_flag('featured'); $p['to_top'] = in_flag('to_top');
    $p['was_public'] = in_flag('was_public');
    $p['is_public'] = $op === 'publish' ? 1 : ($op === 'draft' ? 0 : $p['was_public']);
    $p['image_choice'] = in_str('cover', 12) ?: 'none';
    foreach (['audience', 'subjects', 'features', 'refs'] as $k) { $p[$k . '_text'] = in_str($k, 20000); }
    $p['related'] = [];
    foreach ((array) ($_POST['related'] ?? []) as $r) { if (is_string($r) && preg_match('~\A[a-z]+/[a-z0-9-]+\z~', $r)) { $p['related'][] = $r; } }
    $p['loaded_at'] = in_str('loaded_at', 30); $p['force'] = in_flag('force');
    $p['content'] = in_str('content', 200000);
    // pictures already in the post
    $images = []; $maxN = 0;
    foreach ((array) ($_POST['img'] ?? []) as $im) {
        if (!is_array($im)) { continue; }
        $clean = fn($k, $max) => isset($im[$k]) && is_string($im[$k]) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $im[$k]) ?? ''), 0, $max) : '';
        $n = (int) ($im['n'] ?? 0); if ($n < 1) { continue; }
        $maxN = max($maxN, $n);
        if (!empty($im['drop'])) { continue; }
        $images[] = ['n' => $n, 'media' => filter_var($im['media'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null, 'alt' => $clean('alt', 400), 'caption' => $clean('caption', 400)];
    }
    if ($upload) {
        // cover, PDF and up to three new pictures are stored right away, so a mistake elsewhere never loses them
        [$id, $err] = admin_post_upload(admin_file_at('cover_file'), in_line('cover_alt', 400));
        if ($err) { $p['upload_errors'][] = $err; } elseif ($id) { $p['image_choice'] = (string) $id; }
        [$id, $err] = admin_post_upload(admin_file_at('pdf_file'));
        if ($err) { $p['upload_errors'][] = $err; } elseif ($id) { $p['download_id'] = $id; }
        foreach ([1, 2, 3] as $slot) {
            $alt = isset($_POST['newalt'][$slot]) && is_string($_POST['newalt'][$slot]) ? mb_substr(trim($_POST['newalt'][$slot]), 0, 400) : '';
            $cap = isset($_POST['newcap'][$slot]) && is_string($_POST['newcap'][$slot]) ? mb_substr(trim($_POST['newcap'][$slot]), 0, 400) : '';
            [$id, $err] = admin_post_upload(admin_file_at('newimg', $slot), $alt);
            if ($err) { $p['upload_errors'][] = $err; } elseif ($id) { $images[] = ['n' => ++$maxN, 'media' => $id, 'alt' => $alt, 'caption' => $cap]; }
        }
    }
    $p['images'] = $images;
    [$p['body'], $p['body_error']] = admin_body_from_text($p['content'], $images);
    return $p;
}

// Returns [errors, dbValues]
function admin_post_prepare(array $p, ?int $id): array {
    $db = admin_db(); $errors = [];
    if (!isset(ADMIN_KINDS[$p['kind']])) { $errors['kind'] = 'เลือกประเภทของโพสต์'; }
    if ($p['title'] === '') { $errors['title'] = 'ต้องมีหัวข้อ'; }
    if (!empty($p['body_error'])) { $errors['content'] = $p['body_error']; }
    $slug = clean_slug($p['slug']);
    if ($slug === '' && $id) { $cs = $db->prepare('SELECT slug FROM posts WHERE id = ?'); $cs->execute([$id]); $slug = (string) $cs->fetchColumn(); }
    if ($slug === '' && isset(ADMIN_KINDS[$p['kind']])) { $slug = admin_auto_slug($p['kind'], $p['title']); }
    if ($slug === '' && $p['slug'] !== '') { $errors['slug'] = 'ที่อยู่ลิงก์ใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข และ -'; }
    if ($p['post_date'] !== '') { $d = DateTime::createFromFormat('Y-m-d', $p['post_date']); if (!$d || $d->format('Y-m-d') !== $p['post_date']) { $errors['post_date'] = 'วันที่ไม่ถูกต้อง'; } }
    foreach (['tool_url' => 'ลิงก์เครื่องมือ'] as $k => $label) { if (clean_url($p[$k]) === null) { $errors['tool_url'] = "$label ต้องขึ้นต้นด้วย https://"; } }
    if ($p['kind'] === 'innovations' && $p['category'] !== '') {
        $cats = require admin_root() . '/config/innovation-categories.php';
        if (!in_array($p['category'], $cats, true)) { $errors['category'] = 'เลือกหมวดจากรายการ'; }
    }
    $existsMedia = $db->prepare('SELECT mime FROM media WHERE id = ? AND og_of IS NULL');
    $imageId = null; $keepImage = false;
    if ($p['image_choice'] === 'keep') { $keepImage = true; }
    elseif ($p['image_choice'] !== 'none') {
        $imageId = (int) $p['image_choice']; $existsMedia->execute([$imageId]); $m = $existsMedia->fetchColumn();
        if (!$m || strpos($m, 'image/') !== 0) { $errors['cover'] = 'รูปปกที่เลือกไม่มีในคลังรูป'; $imageId = null; }
    }
    if ($p['download_id']) { $existsMedia->execute([$p['download_id']]); if ($existsMedia->fetchColumn() !== 'application/pdf') { $errors['download'] = 'ไฟล์ดาวน์โหลดต้องเป็น PDF'; } }
    $body = [];
    foreach ($p['body'] as $i => $b) {
        if ($b['t'] === 'p') { if ($b['text'] !== '') { $body[] = $b['text']; } continue; }
        if (!$b['media']) { $errors['content'] = 'มีรูปที่ยังไม่ได้เลือกไฟล์'; continue; }
        $existsMedia->execute([$b['media']]);
        if (strpos((string) $existsMedia->fetchColumn(), 'image/') !== 0) { $errors['content'] = 'มีรูปที่ไม่อยู่ในคลังรูป'; continue; }
        $block = ['media' => (int) $b['media']];
        if ($b['alt'] !== '') { $block['alt'] = $b['alt']; }
        if ($b['caption'] !== '') { $block['caption'] = $b['caption']; }
        $body[] = $block;
    }
    $json = fn(array $a) => $a ? json_encode(array_values($a), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $vals = [
        'kind' => $p['kind'], 'slug' => $slug, 'title' => $p['title'], 'summary' => $p['summary'] !== '' ? $p['summary'] : null,
        'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'refs' => $json(admin_lines_to_list($p['refs_text'])),
        'audience' => $json(admin_lines_to_list($p['audience_text'])), 'subjects' => $json(admin_lines_to_list($p['subjects_text'])),
        'features' => $json(admin_lines_to_list($p['features_text'])), 'post_date' => $p['post_date'] !== '' ? $p['post_date'] : null,
        'author' => $p['author'] !== '' ? $p['author'] : null, 'category' => $p['category'] !== '' ? $p['category'] : null,
        'tool_url' => $p['tool_url'] !== '' ? $p['tool_url'] : null, 'tool_label' => $p['tool_label'] !== '' ? $p['tool_label'] : null,
        'download_id' => $p['download_id'], 'download_label' => $p['download_label'] !== '' ? $p['download_label'] : null,
        'featured' => $p['featured'], 'is_public' => $p['is_public'], 'sort_order' => $p['sort_order'],
    ];
    if (!$keepImage) { $vals['image_id'] = $imageId; $vals['image'] = null; }
    return [$errors, $vals];
}

function admin_revision_add(int $postId, string $kind, string $slug, string $action, array $row, array $links): void {
    $user = auth_user();
    unset($row['updated_at'], $row['updated_by'], $row['loaded_at'], $row['created_at']);
    admin_db()->prepare('INSERT INTO post_revisions (post_id, kind, slug, action, snapshot, user_id, user_name) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$postId, $kind, $slug, $action, json_encode(['post' => $row, 'links' => $links], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), $user['id'] ?? null, $user['full_name'] ?? null]);
}

function admin_post_links(string $kind, string $slug): array {
    $st = admin_db()->prepare('SELECT to_kind, to_slug FROM post_links WHERE from_kind = ? AND from_slug = ? ORDER BY sort_order');
    $st->execute([$kind, $slug]);
    return $st->fetchAll();
}
function admin_post_set_links(string $kind, string $slug, array $links): void {
    $db = admin_db();
    $db->prepare('DELETE FROM post_links WHERE from_kind = ? AND from_slug = ?')->execute([$kind, $slug]);
    $ins = $db->prepare('INSERT IGNORE INTO post_links (from_kind, from_slug, to_kind, to_slug, sort_order) VALUES (?, ?, ?, ?, ?)');
    foreach (array_values($links) as $i => $l) { if ($l['to_kind'] . '/' . $l['to_slug'] !== $kind . '/' . $slug) { $ins->execute([$kind, $slug, $l['to_kind'], $l['to_slug'], $i]); } }
}

// Saves. Returns [id, errors]. $p['loaded_at'] must be what the form was opened with (optimistic lock) unless $p['force'].
function admin_post_save(array $p, ?int $id, string $action = ''): array {
    [$errors, $v] = admin_post_prepare($p, $id);
    if ($errors) { return [$id, $errors]; }
    $db = admin_db(); $user = auth_user();
    $links = [];
    foreach ($p['related'] as $r) { [$k, $s] = explode('/', $r, 2); if (isset(ADMIN_KINDS[$k])) { $links[] = ['to_kind' => $k, 'to_slug' => $s]; } }
    $db->beginTransaction();
    try {
        if ($id === null) {
            if ($v['sort_order'] === null) { $m = $db->prepare('SELECT COALESCE(MIN(sort_order), 1) - 1 FROM posts WHERE kind = ?'); $m->execute([$v['kind']]); $v['sort_order'] = (int) $m->fetchColumn(); }
            $v += ['image_id' => null, 'image' => null];
            $cols = array_keys($v);
            $db->prepare('INSERT INTO posts (' . implode(',', $cols) . ', updated_by) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ', ?)')->execute([...array_values($v), $user['id']]);
            $id = (int) $db->lastInsertId();
            admin_post_set_links($v['kind'], $v['slug'], $links);
            admin_revision_add($id, $v['kind'], $v['slug'], 'create', $v, $links);
            $db->commit();
            admin_log('post.create', 'posts#' . $id, $v['kind'] . '/' . $v['slug']);
            return [$id, []];
        }
        $st = $db->prepare('SELECT *, COALESCE(updated_at, created_at) AS loaded_at FROM posts WHERE id = ? FOR UPDATE');
        $st->execute([$id]); $cur = $st->fetch();
        if (!$cur) { $db->rollBack(); return [$id, ['db' => 'ไม่พบโพสต์นี้แล้ว']]; }
        if (!$p['force'] && $p['loaded_at'] !== (string) $cur['loaded_at']) {
            $db->rollBack();
            $who = $cur['updated_by'] ? ($db->query('SELECT full_name FROM users WHERE id = ' . (int) $cur['updated_by'])->fetchColumn() ?: '') : '';
            return [$id, ['LOCK' => ['when' => date('H:i', strtotime((string) $cur['loaded_at'])), 'who' => (string) $who]]];
        }
        $v['kind'] = $cur['kind'];  // the kind never changes after creation
        if (!empty($p['to_top'])) { $m = $db->prepare('SELECT COALESCE(MIN(sort_order), 1) - 1 FROM posts WHERE kind = ? AND id <> ?'); $m->execute([$cur['kind'], $id]); $v['sort_order'] = (int) $m->fetchColumn(); }
        elseif ($v['sort_order'] === null) { $v['sort_order'] = (int) $cur['sort_order']; }   // leave the position alone
        admin_revision_add($id, $cur['kind'], $cur['slug'], $action ?: 'update', $cur, admin_post_links($cur['kind'], $cur['slug']));
        $sets = []; foreach (array_keys($v) as $c) { $sets[] = "$c = ?"; }
        $db->prepare('UPDATE posts SET ' . implode(', ', $sets) . ', updated_by = ? WHERE id = ?')->execute([...array_values($v), $user['id'], $id]);
        if ($v['slug'] !== $cur['slug']) {   // keep links in both directions pointing at the renamed post
            $db->prepare('DELETE FROM post_links WHERE from_kind = ? AND from_slug = ?')->execute([$cur['kind'], $cur['slug']]);
            $db->prepare('UPDATE IGNORE post_links SET to_slug = ? WHERE to_kind = ? AND to_slug = ?')->execute([$v['slug'], $cur['kind'], $cur['slug']]);
            admin_views_move($cur['kind'] . '/' . $cur['slug'], $cur['kind'] . '/' . $v['slug']);
        }
        admin_post_set_links($cur['kind'], $v['slug'], $links);
        $db->commit();
        admin_log('post.update', 'posts#' . $id, $cur['kind'] . '/' . $v['slug'] . ($v['slug'] !== $cur['slug'] ? ' (เดิม ' . $cur['slug'] . ')' : ''));
        return [$id, []];
    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_kind_slug')) { return [$id, ['slug' => 'มีโพสต์ประเภทนี้ใช้ที่อยู่ลิงก์ “' . $v['slug'] . '” แล้ว ให้ใช้ชื่ออื่นในตัวเลือกเพิ่มเติม']]; }
        error_log('edu2impact admin post save: ' . $e->getMessage());
        return [$id, ['db' => 'บันทึกไม่สำเร็จ: ' . ($e->getCode() === '42000' || str_contains($e->getMessage(), 'denied') ? 'ผู้ใช้ฐานข้อมูลของหลังบ้านไม่มีสิทธิ์เขียน' : 'ข้อมูลบางอย่างฐานข้อมูลไม่รับ')]];
    }
}

// A renamed post keeps its view count: move the counter rows to the new page key. A missing table or missing right must
// never stop the save, so errors are only logged (MySQL does not abort the transaction for them).
function admin_views_move(string $oldKey, string $newKey): void {
    try {
        $db = admin_db();
        $db->prepare('UPDATE IGNORE post_views SET page_key = ? WHERE page_key = ?')->execute([$newKey, $oldKey]);
        $db->prepare('UPDATE IGNORE post_view_days SET page_key = ? WHERE page_key = ?')->execute([$newKey, $oldKey]);
    } catch (Throwable $e) { error_log('edu2impact admin: view counts not moved (' . $e->getMessage() . ')'); }
}

// Counts written by the old file counter (storage/visits.json) are copied into the tables once, the first time the
// admin list opens while post_views is still empty. Only post pages ("kind/slug") are taken.
function admin_views_import_legacy(PDO $db): void {
    if (defined('VISITS_FILE') || !is_file(visits_file())) { return; }
    try {
        if ((int) $db->query('SELECT COUNT(*) FROM post_views')->fetchColumn() > 0) { return; }
        $now = date('Y-m-d H:i:s');
        $add = $db->prepare('INSERT IGNORE INTO post_views (page_key, total, updated_at) VALUES (?, ?, ?)');
        $day = $db->prepare('INSERT IGNORE INTO post_view_days (page_key, day, views) VALUES (?, ?, ?)');
        foreach (visits_read()['pages'] as $key => $page) {
            if (!is_string($key) || !str_contains($key, '/') || (int) ($page['total'] ?? 0) <= 0) { continue; }
            $add->execute([$key, (int) $page['total'], $now]);
            foreach ((array) ($page['days'] ?? []) as $d => $n) { if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $d)) { $day->execute([$key, $d, (int) $n]); } }
        }
    } catch (Throwable $e) { error_log('edu2impact admin: old view counts not imported (' . $e->getMessage() . ')'); }
}

// Record in the shape the public detail page reads (used by preview).
function admin_post_record(array $p): array {
    [, $v] = admin_post_prepare($p, $p['id']);
    $body = json_decode((string) ($v['body'] ?? '[]'), true) ?: [];
    foreach ($body as $i => $b) { if (is_array($b) && isset($b['media'])) { $body[$i] = ['image' => 'media:' . (int) $b['media']] + array_diff_key($b, ['media' => 1]); } }
    $img = isset($v['image_id']) && $v['image_id'] ? 'media:' . (int) $v['image_id'] : '';
    if (!array_key_exists('image_id', $v) && $p['id']) { $o = admin_db()->prepare('SELECT image_id, image FROM posts WHERE id = ?'); $o->execute([$p['id']]); $o = $o->fetch(); $img = $o['image_id'] ? 'media:' . $o['image_id'] : (string) $o['image']; }
    $rec = ['id' => $v['slug'] ?: 'preview', 'title' => $v['title'] ?: '(ยังไม่มีหัวข้อ)', 'summary' => (string) $v['summary'], 'image' => $img, 'date' => (string) $v['post_date'],
        'author' => (string) $v['author'], 'featured' => (bool) $v['featured'], 'public' => true, 'body' => $body];
    foreach (['category', 'tool_url', 'tool_label'] as $k) { if ($v[$k] !== null) { $rec[$k] = $v[$k]; } }
    foreach (['audience', 'subjects', 'features'] as $k) { if ($v[$k] !== null) { $rec[$k] = json_decode($v[$k], true); } }
    if ($v['refs'] !== null) { $rec['references'] = json_decode($v['refs'], true); }
    if ($v['download_id']) { $rec['download_url'] = 'media:' . (int) $v['download_id']; }
    if ($v['download_label'] !== null) { $rec['download_label'] = $v['download_label']; }
    return $rec;
}
