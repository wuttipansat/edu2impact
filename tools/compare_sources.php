<?php
// Checks that MySQL holds the same content as the files (data/*.json, config/home.php, data/thai-breaks.json), so
// switching content_source changes nothing on the pages.
// Images and PDFs are compared by the SHA-256 of their bytes: a file in assets/ on one side and a row of the media table
// on the other count as equal when the bytes are identical.
// Usage: php tools/compare_sources.php   (reads private/database.php, or the file in EDU2IMPACT_DB_CONFIG)
// Exit code 0 when every dataset matches, 1 otherwise.
require __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/content-db.php';
require_once __DIR__ . '/../includes/media-store.php';

// Empty values count as absent, so "category": "" in JSON equals a NULL column.
function normalise($value) {
    if (!is_array($value)) { return $value; }
    $out = [];
    foreach ($value as $key => $item) {
        if ($item === '' || $item === null || $item === []) { continue; }
        $out[$key] = normalise($item);
    }
    if (array_keys($out) !== range(0, count($out) - 1)) { ksort($out); }
    return $out;
}

// "sha256:<hash>" for an image/PDF reference on either side, "MISSING:<ref>" when nothing is behind it.
function file_fingerprint($ref): string {
    if (!is_string($ref) || $ref === '') { return ''; }
    putenv('EDU2IMPACT_DATA_SOURCE=mysql');
    $row = media_row($ref);
    if ($row) { return 'sha256:' . $row['sha256']; }
    $path = __DIR__ . '/../' . $ref;
    return !str_contains($ref, '..') && is_file($path) ? 'sha256:' . hash_file('sha256', $path) : 'MISSING:' . $ref;
}

function fingerprint_record($record) {
    if (!is_array($record)) { return $record; }
    foreach (['image', 'download_url'] as $key) {
        if (isset($record[$key])) { $record[$key] = file_fingerprint($record[$key]); }
    }
    if (isset($record['body']) && is_array($record['body'])) {
        foreach ($record['body'] as $i => $block) {
            if (is_array($block) && isset($block['image'])) { $record['body'][$i]['image'] = file_fingerprint($block['image']); }
        }
    }
    return $record;
}

putenv('EDU2IMPACT_DATA_SOURCE=mysql');
if (!content_pdo()) { fwrite(STDERR, "ต่อ MySQL ไม่ได้ ตรวจ private/database.php\n"); exit(1); }
$failed = 0;
foreach (['site', 'research', 'innovations', 'workshops', 'media', 'news', 'events', 'shop', 'promos', 'videos'] as $name) {
    putenv('EDU2IMPACT_DATA_SOURCE=json');
    $json = load_data($name);
    try { $mysql = content_db_load($name); } catch (ContentUnavailable $exception) { $mysql = false; }
    if ($mysql === false) { echo "FAIL  $name: อ่านจาก MySQL ไม่ได้\n"; $failed++; continue; }
    if ($mysql === null) { echo "FAIL  $name: ยังไม่มีตารางใน MySQL (รัน database/updates/2026-10-27-01-structure.sql)\n"; $failed++; continue; }
    if ($name !== 'site') {
        $json = array_map('fingerprint_record', $json);
        $mysql = array_map('fingerprint_record', $mysql);
    }
    $json = normalise($json);
    $mysql = normalise($mysql);
    if ($name !== 'site') {
        // public is true for every record load_data() returns, whichever source it came from.
        $strip = static fn (array $records): array => array_map(static function ($r) { unset($r['public']); return $r; }, $records);
        $json = $strip($json); $mysql = $strip($mysql);
    }
    $missing = [];
    array_walk_recursive($mysql, static function ($v) use (&$missing) { if (is_string($v) && str_starts_with($v, 'MISSING:')) { $missing[] = substr($v, 8); } });
    if ($json === $mysql && !$missing) {
        echo 'ok    ' . $name . ($name === 'site' ? '' : ' (' . count($json) . ' รายการ)') . "\n";
        continue;
    }
    $failed++;
    echo "FAIL  $name ไม่ตรงกัน\n";
    if ($missing) { echo '      ไม่พบไฟล์: ' . implode(', ', array_unique($missing)) . "\n"; }
    $ids = static fn (array $records): array => array_map(static fn ($r) => $r['id'] ?? ($r['title'] ?? '?'), $records);
    if ($name !== 'site' && $ids($json) !== $ids($mysql)) {
        echo '      ไฟล์:  ' . implode(', ', $ids($json)) . "\n      MySQL: " . implode(', ', $ids($mysql)) . "\n";
        continue;
    }
    foreach ($json as $i => $record) {
        if (($mysql[$i] ?? null) === $record) { continue; }
        $keys = is_array($record) ? array_unique(array_merge(array_keys($record), array_keys($mysql[$i] ?? []))) : [];
        $diff = array_filter($keys, static fn ($k) => ($record[$k] ?? null) !== ($mysql[$i][$k] ?? null));
        echo '      ' . (is_array($record) ? ($record['id'] ?? $record['title'] ?? $i) : $i) . ': ' . implode(', ', $diff) . "\n";
    }
}

// Thai line breaks: every paragraph in data/thai-breaks.json must be in the thai_breaks table with the same offsets.
$file = json_decode((string) @file_get_contents(__DIR__ . '/../data/thai-breaks.json'), true)['paragraphs'] ?? [];
$table = content_db_thai_breaks();
if ($table === null) { echo "FAIL  thai_breaks: ยังไม่มีตาราง\n"; $failed++; }
else {
    $absent = array_diff_key($file, $table);
    $changed = array_filter(array_intersect_key($file, $table), static fn ($offsets, $hash) => $table[$hash] !== $offsets, ARRAY_FILTER_USE_BOTH);
    if (!$absent && !$changed) { echo 'ok    thai_breaks (' . count($file) . ' ย่อหน้า' . (count($table) > count($file) ? ', MySQL มีเพิ่มอีก ' . (count($table) - count($file)) : '') . ")\n"; }
    else { echo 'FAIL  thai_breaks: ขาด ' . count($absent) . ' ย่อหน้า, ไม่ตรง ' . count($changed) . " ย่อหน้า\n"; $failed++; }
}

echo $failed === 0 ? "\nMySQL ตรงกับไฟล์ทุกชุด รวมรูปทุกไฟล์\n" : "\nไม่ตรงกัน $failed ชุด\n";
exit($failed === 0 ? 0 : 1);
