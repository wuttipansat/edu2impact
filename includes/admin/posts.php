<?php
// Posts for the admin: load, read the form, validate, save with revisions and an optimistic lock, render the form.
require_once __DIR__ . '/media-lib.php';
require_once dirname(__DIR__) . '/visits.php';
require_once dirname(__DIR__) . '/functions.php';

// Kinds that can be switched into each other, and the kinds that have a category.
const ADMIN_KINDS_SWITCHABLE = ['research', 'innovations'];
const ADMIN_KINDS_WITH_CATEGORY = ['research', 'innovations'];
const POST_COLUMNS = ['kind', 'slug', 'title', 'summary', 'body', 'refs', 'audience', 'subjects', 'features', 'image', 'image_id', 'post_date',
    'author', 'category', 'tool_url', 'tool_label', 'download_id', 'download_label', 'featured', 'is_public', 'sort_order'];

function admin_post_blank(string $kind): array {
    return ['id' => null, 'kind' => $kind, 'slug' => '', 'title' => '', 'summary' => '', 'post_date' => date('Y-m-d'), 'author' => '', 'category' => '',
        'tool_url' => '', 'tool_label' => '', 'download_id' => null, 'download_label' => '', 'featured' => 0, 'is_public' => 0, 'sort_order' => null,
        'image_choice' => 'none', 'audience_text' => '', 'subjects_text' => '', 'features_text' => '', 'refs_text' => '', 'related' => [],
        'body' => [], 'content' => '', 'images' => [], 'tables' => [], 'to_top' => 0, 'was_public' => 0, 'upload_errors' => [], 'loaded_at' => '', 'force' => 0];
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
        if ($h = body_heading($b)) { $blocks[] = ['t' => 'h', 'level' => $h[0], 'text' => $h[1]]; continue; }
        if ($t = body_table($b)) {
            $blocks[] = ['t' => 'table', 'head' => $t['head'] !== null, 'rows' => array_merge($t['head'] ? [$t['head']] : [], $t['rows']), 'caption' => $t['caption']];
            continue;
        }
        $media = isset($b['media']) ? (int) $b['media'] : 0;
        if (!$media && !empty($b['image']) && is_string($b['image'])) {
            if (preg_match('/\Amedia:(\d+)\z/', $b['image'], $m)) { $media = (int) $m[1]; }
            else { $find->execute([$b['image']]); $media = (int) $find->fetchColumn(); }
        }
        $blocks[] = ['t' => 'img', 'media' => $media ?: null, 'alt' => (string) ($b['alt'] ?? ''), 'caption' => (string) ($b['caption'] ?? '')];
    }
    $p['body'] = $blocks;
    [$p['content'], $p['images'], $p['tables']] = admin_body_to_text($blocks);
    $p['was_public'] = $p['is_public'];
    return $p;
}

// Body blocks -> one text, the picture list and the table list. In the text a paragraph is separated from the next by a blank
// line, a heading is a line that starts with "# ", "## " or "### ", a picture is the line [รูป N] and a table is [ตาราง N].
function admin_body_to_text(array $blocks): array {
    $parts = []; $images = []; $tables = []; $n = 0; $m = 0;
    foreach ($blocks as $b) {
        if ($b['t'] === 'p') { $parts[] = $b['text']; continue; }
        if ($b['t'] === 'h') { $parts[] = str_repeat('#', $b['level']) . ' ' . trim(preg_replace('/\s+/u', ' ', $b['text']) ?? ''); continue; }
        if ($b['t'] === 'table') {
            $m++;
            $rows = array_map(static fn (array $r) => implode("\t", array_map(static fn ($c) => trim(preg_replace('/\s+/u', ' ', (string) $c) ?? ''), $r)), $b['rows']);
            $tables[] = ['n' => $m, 'head' => $b['head'], 'text' => implode("\n", $rows), 'caption' => $b['caption']];
            $parts[] = '[ตาราง ' . $m . ']'; continue;
        }
        $n++; $images[] = ['n' => $n, 'media' => $b['media'], 'alt' => $b['alt'], 'caption' => $b['caption']];
        $parts[] = '[รูป ' . $n . ']';
    }
    return [implode("\n\n", $parts), $images, $tables];
}

const ADMIN_TABLE_MAX_ROWS = 100;
const ADMIN_TABLE_MAX_COLS = 12;
const ADMIN_TABLE_MAX_CELL = 500;
// One table's text (a row per line; cells separated by Tab, or by | when there is no Tab) -> [rows, error|null].
// Never cuts anything off silently: too many rows, columns or characters is an error in plain Thai.
function admin_table_parse(int $n, string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $sep = str_contains($text, "\t") ? "\t" : '|';
    $rows = [];
    foreach (explode("\n", $text) as $line) {
        if (trim($line, " \t|") === '') { continue; }
        if ($sep === '|') {
            if (preg_match('/\A[\s|:\-]+\z/', $line)) { continue; }   // the --- | --- line of a Markdown table
            $line = preg_replace('/\A\s*\|/', '', $line) ?? $line; $line = preg_replace('/\|\s*\z/', '', $line) ?? $line;
        }
        $rows[] = array_map(static fn ($c) => trim(preg_replace('/\s+/u', ' ', $c) ?? ''), explode($sep, $line));
    }
    if (!$rows) { return [[], 'ตารางที่ ' . $n . ' ยังว่างอยู่ พิมพ์ข้อมูลหรือวางตารางที่ก็อปมา หรือติ๊ก “เอาตารางนี้ออก”'];}
    if (count($rows) > ADMIN_TABLE_MAX_ROWS) { return [[], 'ตารางที่ ' . $n . ' มี ' . count($rows) . ' แถว ใส่ได้ไม่เกิน ' . ADMIN_TABLE_MAX_ROWS . ' แถว ลองแบ่งเป็นหลายตาราง']; }
    $cols = max(array_map('count', $rows));
    if ($cols > ADMIN_TABLE_MAX_COLS) { return [[], 'ตารางที่ ' . $n . ' มี ' . $cols . ' คอลัมน์ ใส่ได้ไม่เกิน ' . ADMIN_TABLE_MAX_COLS . ' คอลัมน์']; }
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $cell) {
            if (mb_strlen($cell) > ADMIN_TABLE_MAX_CELL) { return [[], 'ตารางที่ ' . $n . ' แถวที่ ' . ($r + 1) . ' ช่องที่ ' . ($c + 1) . ' ยาว ' . mb_strlen($cell) . ' ตัวอักษร ช่องหนึ่งใส่ได้ไม่เกิน ' . ADMIN_TABLE_MAX_CELL . ' ตัวอักษร']; }
        }
        $rows[$r] = array_pad($row, $cols, '');
    }
    return [$rows, null];
}

// The block editor (JavaScript) sends the whole body as JSON: a list of
//   {"t":"p","text"} | {"t":"h","level":1-3,"text"} | {"t":"img","media":id,"alt","caption"} | {"t":"table","head":bool,"rows":[[…]],"caption"}
// Nothing is trusted: sizes, levels and table limits are checked here (in plain Thai), media ids are checked later by admin_post_prepare.
// Returns [blocks, error|null] in the same shape as admin_body_from_text().
const ADMIN_BODY_MAX_BLOCKS = 1500;
function admin_body_from_json(string $json): array {
    $list = json_decode($json, true);
    if (!is_array($list) || !array_is_list($list)) { return [[], 'อ่านเนื้อหาจากตัวแก้ไขไม่ได้ ลองโหลดหน้านี้ใหม่ (ข้อความที่พิมพ์ไว้อาจยังอยู่ในฉบับร่างของเบราว์เซอร์)']; }
    if (count($list) > ADMIN_BODY_MAX_BLOCKS) { return [[], 'เนื้อหายาวเกินไป (เกิน ' . ADMIN_BODY_MAX_BLOCKS . ' บล็อก) ลองแบ่งเป็นหลายโพสต์']; }
    $line = static fn ($v, int $max): string => is_string($v) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $v) ?? ''), 0, $max) : '';
    $blocks = []; $tableNo = 0;
    foreach ($list as $b) {
        if (!is_array($b)) { continue; }
        $t = $b['t'] ?? '';
        if ($t === 'p') {
            $text = is_string($b['text'] ?? null) ? trim(str_replace(["\r\n", "\r"], "\n", $b['text'])) : '';
            if ($text !== '') { $blocks[] = ['t' => 'p', 'text' => mb_substr($text, 0, 20000)]; }
        } elseif ($t === 'h') {
            $text = $line($b['text'] ?? '', 300); $level = (int) ($b['level'] ?? 1);
            if ($text !== '') { $blocks[] = ['t' => 'h', 'level' => min(3, max(1, $level)), 'text' => $text]; }
        } elseif ($t === 'img') {
            $media = filter_var($b['media'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            if ($media) { $blocks[] = ['t' => 'img', 'media' => $media, 'alt' => $line($b['alt'] ?? '', 400), 'caption' => $line($b['caption'] ?? '', 400)]; }
        } elseif ($t === 'table') {
            $tableNo++;
            $text = '';
            foreach ((array) ($b['rows'] ?? []) as $row) {
                if (!is_array($row)) { continue; }
                $cells = array_map(static fn ($c) => is_scalar($c) ? str_replace(["\t", "\r", "\n"], ' ', (string) $c) : '', array_values($row));
                $text .= implode("\t", $cells) . "\n";
            }
            if (trim(str_replace("\t", '', $text)) === '') { continue; }   // an empty table is dropped
            [$rows, $err] = admin_table_parse($tableNo, $text);
            if ($err !== null) { return [[], $err]; }
            $blocks[] = ['t' => 'table', 'head' => !empty($b['head']), 'rows' => $rows, 'caption' => $line($b['caption'] ?? '', 400)];
        }
    }
    return [$blocks, null];
}

// Text + pictures + tables -> body blocks. Pictures and tables the text never mentions go at the end. Returns [blocks, error|null].
// A label is found anywhere in the text, not only on a line of its own, and is forgiving about how it was typed:
// [รูป 1], [รูป1], [ รูป 1 ], [รูปที่ 1], Thai digits [รูป ๑], full-width brackets, and the same for [ตาราง N]. Text around a label
// stays as paragraphs before and after it, so "[รูป 1] [รูป 2]" on one line gives two pictures in a row.
// A line that starts with "# ", "## " or "### " is a heading of its own (levels 1-3); "####" or "#word" is ordinary text.
const ADMIN_LABEL = '/[\[［]\s*(รูป|ตาราง)(?:ที่)?\s*([0-9๐-๙]+)\s*[\]］]/u';
function admin_body_from_text(string $text, array $images, array $tables = []): array {
    $byN = []; foreach ($images as $im) { $byN[$im['n']] = $im; }
    $byT = [];
    foreach ($tables as $t) {
        if (isset($byT[$t['n']]) || trim((string) $t['text']) === '') { continue; }
        [$rows, $err] = admin_table_parse((int) $t['n'], (string) $t['text']);
        if ($err !== null) { return [[], $err]; }
        $head = !empty($t['head']) ? array_shift($rows) : null;
        $byT[$t['n']] = ['t' => 'table', 'head' => $head !== null, 'rows' => $head !== null ? array_merge([$head], $rows) : $rows, 'caption' => (string) ($t['caption'] ?? '')];
    }
    $blocks = []; $used = []; $usedT = [];
    foreach (preg_split('/\n[ \t]*\n/u', $text) ?: [] as $para) {
        $lines = preg_split('/^(#{1,3})[ \t]+(\S.*)$/mu', $para, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$para];
        $segments = [];   // [kind, value]: text | heading
        foreach ($lines as $i => $piece) {
            if ($i % 3 === 0) { $segments[] = ['text', $piece]; }
            elseif ($i % 3 === 1) { $level = strlen($piece); }
            else { $segments[] = ['h', [$level, trim(preg_replace('/\s+/u', ' ', $piece) ?? '')]]; }
        }
        foreach ($segments as [$kind, $value]) {
            if ($kind === 'h') { $blocks[] = ['t' => 'h', 'level' => $value[0], 'text' => mb_substr($value[1], 0, 300)]; continue; }
            $pieces = preg_split(ADMIN_LABEL, $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$value];
            for ($i = 0; $i < count($pieces); $i += 3) {
                $piece = trim($pieces[$i]);
                if ($piece !== '') { $blocks[] = ['t' => 'p', 'text' => $piece]; }
                if (!isset($pieces[$i + 2])) { break; }
                $isTable = $pieces[$i + 1] === 'ตาราง';
                $n = (int) strtr($pieces[$i + 2], ['๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4', '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9']);
                if ($isTable) {
                    if (!isset($byT[$n])) { return [[], 'ในเนื้อหามี [ตาราง ' . $n . '] แต่โพสต์นี้ยังไม่มีตารางที่ ' . $n . ' ใส่ข้อมูลตารางในส่วน “ตารางในเนื้อหา” ใต้ช่องเนื้อหา แล้วกดบันทึกอีกครั้ง (หรือลบป้ายนั้นออก)']; }
                    if (!isset($usedT[$n])) { $usedT[$n] = true; $blocks[] = $byT[$n]; }
                    continue;
                }
                if (!isset($byN[$n])) {
                    return [[], 'ในเนื้อหามี [รูป ' . $n . '] แต่โพสต์นี้ยังไม่มีรูปที่ ' . $n . ' ให้เลือกไฟล์ที่ช่อง "รูปใหม่" ใต้ช่องเนื้อหา แล้วกดบันทึกอีกครั้ง (หรือลบป้ายนั้นออก)'];
                }
                if (isset($used[$n])) { continue; }
                $used[$n] = true; $blocks[] = ['t' => 'img'] + $byN[$n];
            }
        }
    }
    foreach ($byN as $n => $im) { if (!isset($used[$n])) { $blocks[] = ['t' => 'img'] + $im; } }
    foreach ($byT as $n => $t) { if (!isset($usedT[$n])) { $blocks[] = $t; } }
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
    $tables = [];
    foreach ((array) ($_POST['tbl'] ?? []) as $t) {
        if (!is_array($t) || !isset($t['n']) || (int) $t['n'] < 1 || !empty($t['drop']) || !is_string($t['text'] ?? null) || trim($t['text']) === '') { continue; }
        $tables[] = ['n' => (int) $t['n'], 'head' => !empty($t['head']), 'text' => isset($t['text']) && is_string($t['text']) ? mb_substr($t['text'], 0, 100000) : '',
            'caption' => isset($t['caption']) && is_string($t['caption']) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $t['caption']) ?? ''), 0, 400) : ''];
    }
    $p['tables'] = $tables;
    $json = in_str('body_json', 3000000);
    if ($json !== '') {   // the block editor
        [$p['body'], $p['body_error']] = admin_body_from_json($json);
        [$p['content'], $p['images'], $p['tables']] = admin_body_to_text($p['body']);
        $p['block_editor'] = 1;
        return $p;
    }
    [$p['body'], $p['body_error']] = admin_body_from_text($p['content'], $images, $tables);
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
    $withCategory = in_array($p['kind'], ADMIN_KINDS_WITH_CATEGORY, true);   // research and innovations share one list of categories
    $category = $withCategory ? $p['category'] : '';
    if ($category !== '') {
        $cats = require admin_root() . '/config/innovation-categories.php';
        if (!in_array($category, $cats, true)) {
            if ($p['kind'] === 'innovations') { $errors['category'] = 'เลือกหมวดจากรายการ'; }
            else { $category = ''; }   // old text such as "Learning" is dropped when a research post is saved
        }
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
        if ($b['t'] === 'h') { if ($b['text'] !== '') { $body[] = ['heading' => $b['level'], 'text' => $b['text']]; } continue; }
        if ($b['t'] === 'table') {
            $rows = $b['rows']; $head = $b['head'] ? array_shift($rows) : null;
            $body[] = ['table' => ['head' => $head, 'rows' => $rows, 'caption' => $b['caption']]]; continue;
        }
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
        'author' => $p['author'] !== '' ? $p['author'] : null, 'category' => $category !== '' ? $category : null,
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
        // Only research and innovations can be switched into each other; every other kind stays as it is.
        $switch = $v['kind'] !== $cur['kind'] && in_array($v['kind'], ADMIN_KINDS_SWITCHABLE, true) && in_array($cur['kind'], ADMIN_KINDS_SWITCHABLE, true);
        if (!$switch) { $v['kind'] = $cur['kind']; }
        else { $v['slug'] = admin_unique_slug($v['kind'], $v['slug'], $id); $p['to_top'] = 1; }   // a taken link name gets -2, -3; the post goes first in its new kind
        if (!empty($p['to_top'])) { $m = $db->prepare('SELECT COALESCE(MIN(sort_order), 1) - 1 FROM posts WHERE kind = ? AND id <> ?'); $m->execute([$v['kind'], $id]); $v['sort_order'] = (int) $m->fetchColumn(); }
        elseif ($v['sort_order'] === null) { $v['sort_order'] = (int) $cur['sort_order']; }   // leave the position alone
        admin_revision_add($id, $cur['kind'], $cur['slug'], $action ?: 'update', $cur, admin_post_links($cur['kind'], $cur['slug']));
        $sets = []; foreach (array_keys($v) as $c) { $sets[] = "$c = ?"; }
        $db->prepare('UPDATE posts SET ' . implode(', ', $sets) . ', updated_by = ? WHERE id = ?')->execute([...array_values($v), $user['id'], $id]);
        if ($v['slug'] !== $cur['slug'] || $v['kind'] !== $cur['kind']) {   // keep links and view counts with the post when it is renamed or switched
            $db->prepare('DELETE FROM post_links WHERE from_kind = ? AND from_slug = ?')->execute([$cur['kind'], $cur['slug']]);
            $db->prepare('UPDATE IGNORE post_links SET to_kind = ?, to_slug = ? WHERE to_kind = ? AND to_slug = ?')->execute([$v['kind'], $v['slug'], $cur['kind'], $cur['slug']]);
            admin_views_move($cur['kind'] . '/' . $cur['slug'], $v['kind'] . '/' . $v['slug']);
        }
        admin_post_set_links($v['kind'], $v['slug'], $links);
        $db->commit();
        admin_log('post.update', 'posts#' . $id, $v['kind'] . '/' . $v['slug'] . ($v['slug'] !== $cur['slug'] ? ' (เดิม ' . $cur['slug'] . ')' : ''));
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


// ---- Form pieces shared by the page and the <template>s the script clones ($i and $n may be the placeholders __I__ and __N__) ----
function admin_pic_row($i, $n, string $thumb, string $alt = '', string $caption = '', $media = ''): string {
    $i = e((string) $i); $n = e((string) $n);
    return '<div class="pic" data-pic><div>' . $thumb . '</div><div><strong>[รูป ' . $n . ']</strong>'
        . '<input type="hidden" name="img[' . $i . '][n]" value="' . $n . '"><input type="hidden" name="img[' . $i . '][media]" value="' . e((string) $media) . '">'
        . '<label>คำอธิบายรูป <small>บอกว่าในรูปมีอะไร สำหรับผู้ใช้โปรแกรมอ่านหน้าจอ ถ้าเว้นว่าง รูปจะไม่แสดงบนเว็บ</small><input name="img[' . $i . '][alt]" value="' . e($alt) . '" maxlength="400" data-alt></label>'
        . '<label>คำบรรยายใต้รูป (ไม่บังคับ)<input name="img[' . $i . '][caption]" value="' . e($caption) . '" maxlength="400"></label>'
        . '<label class="check"><input type="checkbox" name="img[' . $i . '][drop]" value="1"> เอารูปนี้ออกจากโพสต์</label></div></div>';
}
function admin_table_box($i, $n, string $text = '', bool $head = true, string $caption = ''): string {
    $i = e((string) $i); $n = e((string) $n);
    return '<div class="tblbox" data-table><strong>[ตาราง ' . $n . ']</strong> '
        . '<input type="hidden" name="tbl[' . $i . '][n]" value="' . $n . '">'
        . '<label>ข้อมูลตาราง <small>หนึ่งแถวต่อหนึ่งบรรทัด คั่นช่องด้วยปุ่ม Tab หรือเครื่องหมาย | ก็อปจาก Excel หรือ Google Sheets มาวางได้เลย</small>'
        . '<textarea name="tbl[' . $i . '][text]" rows="5" wrap="off" class="tblbox__text" data-table-text>' . e($text) . '</textarea></label>'
        . '<div class="tblprev" data-table-preview aria-live="polite"></div>'
        . '<label class="check"><input type="checkbox" name="tbl[' . $i . '][head]" value="1"' . ($head ? ' checked' : '') . ' data-table-head> แถวแรกเป็นหัวตาราง</label>'
        . '<label>คำบรรยายใต้ตาราง (ไม่บังคับ)<input name="tbl[' . $i . '][caption]" value="' . e($caption) . '" maxlength="400"></label>'
        . '<label class="check"><input type="checkbox" name="tbl[' . $i . '][drop]" value="1"> เอาตารางนี้ออกจากโพสต์</label></div>';
}
