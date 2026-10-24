<?php
// Checks that MySQL holds the same content as data/*.json, so switching content_source changes nothing on the pages.
// Usage: php tools/compare_sources.php   (reads private/database.php, or the file in EDU2IMPACT_DB_CONFIG)
// Exit code 0 when every dataset matches, 1 otherwise.
require __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/content-db.php';

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

if (!content_pdo()) { fwrite(STDERR, "ต่อ MySQL ไม่ได้ ตรวจ private/database.php\n"); exit(1); }
$failed = 0;
foreach (['site', 'research', 'innovations', 'workshops', 'media', 'news', 'events'] as $name) {
    putenv('EDU2IMPACT_DATA_SOURCE=json');
    $json = normalise(load_data($name));
    $mysql = content_db_load($name);
    if ($mysql === null) { echo "FAIL  $name: อ่านจาก MySQL ไม่ได้\n"; $failed++; continue; }
    $mysql = normalise($mysql);
    if ($name !== 'site') {
        // public is true for every record load_data() returns, whichever source it came from.
        $strip = static fn (array $records): array => array_map(static function ($r) { unset($r['public']); return $r; }, $records);
        $json = $strip($json); $mysql = $strip($mysql);
    }
    if ($json === $mysql) {
        echo 'ok    ' . $name . ($name === 'site' ? '' : ' (' . count($json) . ' รายการ)') . "\n";
        continue;
    }
    $failed++;
    echo "FAIL  $name ไม่ตรงกัน\n";
    $ids = static fn (array $records): array => array_map(static fn ($r) => $r['id'] ?? ($r['title'] ?? '?'), $records);
    if ($name !== 'site' && $ids($json) !== $ids($mysql)) {
        echo '      JSON:  ' . implode(', ', $ids($json)) . "\n      MySQL: " . implode(', ', $ids($mysql)) . "\n";
        continue;
    }
    foreach ($json as $i => $record) {
        if (($mysql[$i] ?? null) === $record) { continue; }
        $keys = is_array($record) ? array_unique(array_merge(array_keys($record), array_keys($mysql[$i] ?? []))) : [];
        $diff = array_filter($keys, static fn ($k) => ($record[$k] ?? null) !== ($mysql[$i][$k] ?? null));
        echo '      ' . ($record['id'] ?? $i) . ': ' . implode(', ', $diff) . "\n";
    }
}
echo $failed === 0 ? "\nMySQL ตรงกับ data/*.json ทุกชุด\n" : "\nไม่ตรงกัน $failed ชุด\n";
exit($failed === 0 ? 0 : 1);
