<?php
// Tests for includes/visits.php. Uses a temporary file, never the real storage/visits.json.
// Run: php tools/test_visits.php
$tmp = sys_get_temp_dir() . '/edu2impact-visits-test-' . getmypid();
@mkdir($tmp);
define('VISITS_FILE', $tmp . '/visits.json');
require __DIR__ . '/../includes/visits.php';

$failures = 0;
function check(string $name, bool $ok): void { global $failures; echo ($ok ? 'ok    ' : 'FAIL  ') . $name . "\n"; if (!$ok) { $failures++; } }
$browser = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36';
$day1 = new DateTimeImmutable('2026-10-24 10:00:00', new DateTimeZone('Asia/Bangkok'));
$day2 = new DateTimeImmutable('2026-10-25 10:00:00', new DateTimeZone('Asia/Bangkok'));
$visit = static fn (array $over = []): array => $over + ['method' => 'GET', 'ip' => '203.0.113.7', 'ua' => $browser, 'now' => $day1, 'status' => 200];

check('missing file reads as zero', visit_count('home') === 0 && visit_total() === 0 && visit_label(0) === '');
check('first visit is counted', record_visit('home', $visit()) === true);
check('same visitor, same page, same day counts once', record_visit('home', $visit()) === false && visit_count('home') === 1);
check('same visitor on another page counts for that page', record_visit('research', $visit()) === true && visit_count('research') === 1);
check('site total counts the visitor once per day', visit_total() === 1);
check('another visitor counts', record_visit('home', $visit(['ip' => '203.0.113.8'])) === true && visit_count('home') === 2 && visit_total() === 2);
check('a new day counts again', record_visit('home', $visit(['now' => $day2])) === true && visit_count('home') === 3 && visit_total() === 3);
foreach (['Googlebot/2.1 (+http://www.google.com/bot.html)', 'facebookexternalhit/1.1', 'Twitterbot/1.0', 'curl/8.5.0', 'Mozilla/5.0 (compatible; bingbot/2.0)', 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)', ''] as $ua) {
    check('not counted: UA "' . $ua . '"', record_visit('about', $visit(['ua' => $ua, 'ip' => '198.51.100.' . rand(1, 250)])) === false);
}
check('POST is not counted', record_visit('about', $visit(['method' => 'POST', 'ip' => '198.51.100.9'])) === false);
check('HEAD is not counted', record_visit('about', $visit(['method' => 'HEAD', 'ip' => '198.51.100.10'])) === false);
check('prefetch is not counted', record_visit('about', $visit(['purpose' => 'prefetch', 'ip' => '198.51.100.11'])) === false);
check('404 and redirects are not counted', record_visit('about', $visit(['status' => 404, 'ip' => '198.51.100.12'])) === false && record_visit('about', $visit(['status' => 301, 'ip' => '198.51.100.13'])) === false);
check('nothing was counted for about', visit_count('about') === 0);

$raw = (string) file_get_contents(VISITS_FILE);
check('file holds no IP address', strpos($raw, '203.0.113') === false && strpos($raw, '198.51.100') === false);
check('file holds no User-Agent text', stripos($raw, 'Mozilla') === false && stripos($raw, 'Chrome') === false);
$data = json_decode($raw, true);
check("only today's hashes are kept", ($data['today']['date'] ?? '') === '2026-10-25' && count($data['today']['seen']) === 2);

file_put_contents(VISITS_FILE, '');
check('empty file does not break reading', visit_count('home') === 0 && visit_total() === 0);
file_put_contents(VISITS_FILE, '{not json');
check('damaged file does not break reading', visit_count('home') === 0);
check('damaged file is replaced by a fresh count', record_visit('home', $visit(['ip' => '203.0.113.50'])) === true && visit_count('home') === 1);
unlink(VISITS_FILE);
// Each probe runs in its own PHP process with its own temp folder and error log, so nothing touches the real storage/.
$probeRun = static function (string $file, int $calls) use ($tmp): array {
    $dir = $tmp . '/probe-' . md5($file);
    @mkdir($dir);
    $code = 'define("VISITS_FILE", ' . var_export($file, true) . '); require ' . var_export(__DIR__ . '/../includes/visits.php', true) . '; $r = []; '
        . 'for ($i = 0; $i < ' . $calls . '; $i++) { $r[] = record_visit("home", ["method" => "GET", "ip" => "203.0.113.1", "ua" => "Mozilla/5.0 x", "status" => 200]); } echo json_encode($r);';
    $out = shell_exec('TMPDIR=' . escapeshellarg($dir) . ' php -d display_errors=0 -d error_log=' . escapeshellarg($dir . '/php.log') . ' -r ' . escapeshellarg($code) . ' 2>&1');
    $log = is_file($dir . '/php.log') ? trim((string) file_get_contents($dir . '/php.log')) : '';
    return [trim((string) $out), $log === '' ? 0 : count(explode("\n", $log)), $log];
};
[$out, $logLines, $log] = $probeRun('/proc/nonexistent/visits.json', 2);
check('unwritable location is skipped quietly (page output unaffected)', $out === '[false,false]');
check('unwritable location is logged once, naming the problem', $logLines === 1 && strpos($log, 'visit counter') !== false && strpos($log, 'check_visits.php') !== false);
[$out] = $probeRun($tmp . '/new-folder/deeper/visits.json', 1);
check('a missing storage folder is created', $out === '[true]' && is_file($tmp . '/new-folder/deeper/visits.json'));

// Share buttons: with no site_url the links are completed in the browser; with one they are built here.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/share.php';
ob_start(); render_share('research-detail.php?id=demo', 'ชื่อเรื่อง'); $shareHtml = ob_get_clean();
if (site_url() === '') {
    check('no site_url: share buttons are hidden until main.js completes them', strpos($shareHtml, 'data-share-client hidden') !== false && strpos($shareHtml, 'data-share="facebook"') !== false && strpos($shareHtml, 'data-share="x"') !== false);
} else {
    check('site_url set: share links carry the absolute address', strpos($shareHtml, 'sharer.php?u=' . rawurlencode(site_url() . '/research-detail.php?id=demo')) !== false && strpos($shareHtml, 'data-share-client') === false);
}
check('share links are icon-only: no visible title or app names, Thai aria-label on each',strpos($shareHtml, 'share__title') === false && strpos($shareHtml, 'แชร์เรื่องนี้') === false && strpos($shareHtml, '<span>') === false && strpos($shareHtml, 'aria-label="แชร์"') !== false);
check('share links open in a new tab safely, with Thai labels', substr_count($shareHtml, 'target="_blank" rel="noopener noreferrer"') === 2 && strpos($shareHtml, 'aria-label="แชร์ไปที่ Facebook"') !== false && strpos($shareHtml, 'aria-label="แชร์ไปที่ X"') !== false);
check('eye icon shows the count, and nothing at 0', visit_eye(0) === '' && strpos(visit_eye(1234), '1,234') !== false && strpos(visit_eye(1234), 'เข้าชม 1,234 ครั้ง') !== false);

// Old days are dropped after 90 days.
$old = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('Asia/Bangkok'));
record_visit('home', $visit(['now' => $old, 'ip' => '203.0.113.60']));
record_visit('home', $visit(['now' => $day2, 'ip' => '203.0.113.61']));
$days = json_decode((string) file_get_contents(VISITS_FILE), true)['pages']['home']['days'] ?? [];
check('days older than 90 are dropped', !isset($days['2026-01-01']) && isset($days['2026-10-25']));

$wipe = static function (string $dir) use (&$wipe): void { foreach (array_merge(glob($dir . '/*') ?: [], glob($dir . '/.*') ?: []) as $item) { if (is_dir($item)) { if (!in_array(basename($item), ['.', '..'], true)) { $wipe($item); @rmdir($item); } } else { @unlink($item); } } };
$wipe($tmp);
@rmdir($tmp);
echo $failures === 0 ? "\nAll visit tests passed.\n" : "\n$failures test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
