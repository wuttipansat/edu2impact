<?php
// Tests for the MySQL view counter (includes/visits.php, tables from database/updates/2026-10-28-01-views.sql).
// LOCAL TEST DATABASE ONLY: it empties post_views, post_view_days and post_view_seen.
//   php tools/test_visits_db.php                                   (uses private/database.php, content_source mysql)
//   EDU2IMPACT_DB_CONFIG=/path/db.php php tools/test_visits_db.php
// The database user in the config needs SELECT, INSERT, UPDATE, DELETE on the three tables.
require __DIR__ . '/../includes/visits.php';

$failures = 0;
function check(string $name, bool $ok): void { global $failures; echo ($ok ? 'ok    ' : 'FAIL  ') . $name . "\n"; if (!$ok) { $failures++; } }
if (content_source() !== 'mysql' || !($pdo = content_pdo())) { exit("FAIL  content_source must be mysql and the database reachable\n"); }
foreach (['post_view_seen', 'post_view_days', 'post_views'] as $t) { $pdo->exec("DELETE FROM $t"); }

$browser = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36';
$day1 = new DateTimeImmutable('2026-10-24 10:00:00', new DateTimeZone('Asia/Bangkok'));
$day2 = new DateTimeImmutable('2026-10-25 10:00:00', new DateTimeZone('Asia/Bangkok'));
$visit = static fn (array $over = []): array => $over + ['method' => 'GET', 'ip' => '203.0.113.7', 'ua' => $browser, 'now' => $day1, 'status' => 200];
$key = 'research/test-post';

check('database mode is on', visits_db() !== null && visits_db_status() === 'ok');
check('unknown post reads as zero', visit_count($key) === 0 && visit_total() === 0);
check('first visit is counted and shows 1', record_visit($key, $visit()) === true && visit_count($key) === 1);
check('same visitor, same post, same day counts once', record_visit($key, $visit()) === false && visit_count($key) === 1);
check('same visitor on another post counts for that post', record_visit('research/other', $visit()) === true && visit_count('research/other') === 1 && visit_count($key) === 1);
check('another visitor counts', record_visit($key, $visit(['ip' => '203.0.113.8'])) === true && visit_count($key) === 2);
check('a different browser on the same address counts', record_visit($key, $visit(['ua' => $browser . ' Edg/126'])) === true && visit_count($key) === 3);
check('a new day counts the same visitor again', record_visit($key, $visit(['now' => $day2])) === true && visit_count($key) === 4);
check('total adds up every post', visit_total() === 5);
foreach (['Googlebot/2.1', 'facebookexternalhit/1.1', 'curl/8.5.0', 'Mozilla/5.0 (compatible; bingbot/2.0)', ''] as $i => $ua) {
    check('not counted: UA "' . $ua . '"', record_visit('research/bots', $visit(['ua' => $ua, 'ip' => '198.51.100.' . (20 + $i)])) === false);
}
check('POST, HEAD, prefetch, 404 and redirect are not counted',
    record_visit('research/bots', $visit(['method' => 'POST', 'ip' => '198.51.100.1'])) === false
    && record_visit('research/bots', $visit(['method' => 'HEAD', 'ip' => '198.51.100.2'])) === false
    && record_visit('research/bots', $visit(['purpose' => 'prefetch', 'ip' => '198.51.100.3'])) === false
    && record_visit('research/bots', $visit(['status' => 404, 'ip' => '198.51.100.4'])) === false
    && record_visit('research/bots', $visit(['status' => 301, 'ip' => '198.51.100.5'])) === false);
check('nothing was counted for the bots post', visit_count('research/bots') === 0);
check('per-day rows match', (int) $pdo->query("SELECT views FROM post_view_days WHERE page_key = '$key' AND day = '2026-10-24'")->fetchColumn() === 3
    && (int) $pdo->query("SELECT views FROM post_view_days WHERE page_key = '$key' AND day = '2026-10-25'")->fetchColumn() === 1);

$dump = json_encode([$pdo->query('SELECT * FROM post_view_seen')->fetchAll(), $pdo->query('SELECT * FROM post_views')->fetchAll(), $pdo->query('SELECT * FROM post_view_days')->fetchAll()]);
check('no IP address or User-Agent text is stored', strpos($dump, '203.0.113') === false && stripos($dump, 'Mozilla') === false && stripos($dump, 'Chrome') === false);
check('seen rows are 20-character hashes', (int) $pdo->query("SELECT COUNT(*) FROM post_view_seen WHERE hash NOT REGEXP '^[0-9a-f]{20}$'")->fetchColumn() === 0);
check('no file was written for the counts', !is_file(__DIR__ . '/../storage/visits.json'));

// Old rows are removed now and then (1 in 100 visits). Force it by many visits and check the old day is gone.
$pdo->exec("INSERT IGNORE INTO post_view_seen (hash, day) VALUES ('0000000000000000old1', '2026-10-01')");
$pdo->exec("INSERT IGNORE INTO post_view_days (page_key, day, views) VALUES ('research/test-post', '2025-01-01', 9)");
for ($i = 0; $i < 600; $i++) { record_visit('research/cleanup', $visit(['ip' => '192.0.2.' . ($i % 250), 'ua' => $browser . ' n' . $i, 'now' => new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'))])); }
check('old hashes and days older than 90 are cleaned up on their own',
    (int) $pdo->query("SELECT COUNT(*) FROM post_view_seen WHERE day = '2026-10-01'")->fetchColumn() === 0
    && (int) $pdo->query("SELECT COUNT(*) FROM post_view_days WHERE day = '2025-01-01'")->fetchColumn() === 0);

// 50 requests at the same moment, 50 different visitors: the count must be exactly 50.
$pdo->exec('DELETE FROM post_view_seen'); $pdo->exec('DELETE FROM post_view_days'); $pdo->exec('DELETE FROM post_views');
$code = 'require ' . var_export(__DIR__ . '/../includes/visits.php', true) . '; $n = (int) $argv[1];'
    . 'record_visit("research/race", ["method" => "GET", "ip" => "10.1.0." . $n, "ua" => "Mozilla/5.0 Chrome/126", "status" => 200]);';
$procs = [];
for ($n = 1; $n <= 50; $n++) { $procs[] = proc_open([PHP_BINARY, '-r', $code, (string) $n], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); }
foreach ($procs as $proc) { proc_close($proc); }
check('50 simultaneous visitors give exactly 50', visit_count('research/race') === 50);
$twice = [];
for ($n = 1; $n <= 20; $n++) { for ($k = 0; $k < 2; $k++) { $twice[] = proc_open([PHP_BINARY, '-r', str_replace('"10.1.0." . $n', '"10.2.0." . ($n % 10)', $code), (string) $n], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); } }
foreach ($twice as $proc) { proc_close($proc); }
check('the same 10 visitors sent 4 times each add only 10', visit_count('research/race') === 60);

// Admin preview is never a visit.
define('ADMIN_PREVIEW', true);
check('admin preview is not counted', record_visit('research/preview', $visit(['ip' => '203.0.113.99'])) === false && visit_count('research/preview') === 0);

foreach (['post_view_seen', 'post_view_days', 'post_views'] as $t) { $pdo->exec("DELETE FROM $t"); }
echo $failures ? "\n$failures FAILED\n" : "\nall passed\n";
exit($failures ? 1 : 0);
