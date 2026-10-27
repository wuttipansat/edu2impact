<?php
// Visit counter. Server-side only: no cookies, no scripts, no third parties, and no IP address or
// User-Agent is ever stored.
//
// A visit is one visitor opening one page once per day (Thai time). To avoid counting repeats, the day's
// visitors are kept as short keyed hashes (HMAC of IP + User-Agent + page + date, with a secret). The list
// is replaced when the date changes, so nothing that identifies a visitor outlives the day.
//
// storage/visits.json:
//   { "pages": { "<pageKey>": { "total": N, "days": { "YYYY-MM-DD": N } } },
//     "site":  { "total": N, "days": { … } },          // distinct visitors per day, whole site
//     "today": { "date": "YYYY-MM-DD", "seen": [hash, …] } }
// Per-day counts are kept for 90 days. If the file cannot be read or written the page still loads and
// nothing is shown or counted.
//
// Round 15: when content comes from MySQL (private/database.php 'content_source' => 'mysql') the counts live in the
// tables post_views, post_view_days and post_view_seen (database/updates/2026-10-28-01-views.sql) instead, because
// hosted PHP often cannot write storage/. Only post pages are counted there (page key "<kind>/<slug>"). The file code
// below stays as the fallback when MySQL is not used, the tables are missing or the database user may not write.

require_once __DIR__ . '/content-db.php';

const VISITS_KEEP_DAYS = 90;
const VISITS_BOT_PATTERN = '~bot|crawl|spider|slurp|preview|facebookexternalhit|facebot|whatsapp|telegram|curl|wget|python-requests|python-urllib|headless|lighthouse|pingdom|uptime|monitor|scanner|httpclient|go-http-client|java/|libwww~i';

// Connection used for counting, or null to use the file. 'counter_user'/'counter_pass' in private/database.php give the
// counter its own database user; otherwise the public site's user is used. Tests that define VISITS_FILE use the file.
function visits_db(?Throwable $failure = null): ?PDO {
    static $pdo = false;
    if ($failure !== null) { $pdo = null; return null; }
    if ($pdo !== false) { return $pdo; }
    $pdo = null;
    if (defined('VISITS_FILE') || content_source() !== 'mysql') { return null; }
    $config = content_db_config();
    if (is_string($config['counter_user'] ?? null) && is_string($config['counter_pass'] ?? null) && $config['counter_user'] !== '') {
        try {
            $pdo = new PDO('mysql:host=' . ($config['host'] ?? 'localhost') . ';dbname=' . ($config['name'] ?? '') . ';charset=utf8mb4', $config['counter_user'], $config['counter_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]);
        } catch (Throwable $exception) { visits_warn('counter database user cannot connect'); $pdo = null; }
        return $pdo;
    }
    return $pdo = content_pdo();
}

// The database could not be used for counting (table missing, no write right, connection lost): log once a day and
// use the file from now on in this request.
function visits_db_failed(Throwable $exception): void {
    visits_db($exception);
    $code = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
    visits_warn('MySQL counter failed (' . ($code === 1146 ? 'run database/updates/2026-10-28-01-views.sql' : ($code === 1142 || $code === 1143 ? 'the database user may not write post_views, post_view_days, post_view_seen' : get_class($exception) . ' ' . $code)) . ')');
}

// 'ok', 'file' (MySQL not used), 'unavailable', 'missing' (tables not imported) or 'denied' (user cannot write). For the admin page.
function visits_db_status(): string {
    if (content_source() !== 'mysql') { return 'file'; }
    $pdo = visits_db();
    if (!$pdo) { return 'unavailable'; }
    try {
        $pdo->query('SELECT 1 FROM post_views LIMIT 1');
        $pdo->query('SELECT 1 FROM post_view_days LIMIT 1');
        $pdo->query('SELECT 1 FROM post_view_seen LIMIT 1');
    } catch (Throwable $exception) { return 'missing'; }
    try {
        // These change nothing (WHERE 1 = 0) but MySQL still checks the write rights.
        $pdo->exec("INSERT INTO post_views (page_key, total) SELECT 'x', 0 FROM DUAL WHERE 1 = 0");
        $pdo->exec('UPDATE post_views SET total = total WHERE 1 = 0');
        $pdo->exec("INSERT INTO post_view_days (page_key, day, views) SELECT 'x', CURDATE(), 0 FROM DUAL WHERE 1 = 0");
        $pdo->exec('UPDATE post_view_days SET views = views WHERE 1 = 0');
        $pdo->exec('DELETE FROM post_view_days WHERE 1 = 0');
        $pdo->exec("INSERT INTO post_view_seen (hash, day) SELECT 'x', CURDATE() FROM DUAL WHERE 1 = 0");
        $pdo->exec('DELETE FROM post_view_seen WHERE 1 = 0');
    } catch (Throwable $exception) { return 'denied'; }
    return 'ok';
}

function visits_file(): string {
    return defined('VISITS_FILE') ? VISITS_FILE : __DIR__ . '/../storage/visits.json';
}

// Make sure the storage folder exists and can be written. A folder created here gets its own .htaccess
// (Apache 2.4 "Require all denied"); on other servers, block /storage in the server configuration.
function visits_prepare(): bool {
    $file = visits_file();
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return visits_warn('cannot create ' . $dir); }
    if (!is_file($dir . '/.htaccess') && !defined('VISITS_FILE')) {
        @file_put_contents($dir . '/.htaccess', "# Counter data must never be served.\nRequire all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    if (is_file($file) ? !is_writable($file) : !is_writable($dir)) { return visits_warn($file . ' is not writable by PHP'); }
    return true;
}

// Log a problem to the PHP error log at most once a day, then report "not recorded" (false).
function visits_warn(string $message): bool {
    $marker = sys_get_temp_dir() . '/edu2impact-visits-warned-' . visits_today();
    if (!is_file($marker)) {
        @file_put_contents($marker, $message);
        error_log('[edu2impact visit counter] ' . $message . ' - visits are not being counted. Run: php tools/check_visits.php');
    }
    return false;
}

// Secret for the visitor hashes: config/visits.local.php (not committed, see config/visits.example.php),
// otherwise a random one is created once in storage/.visits-secret.
function visits_secret(): string {
    static $secret = null;
    if ($secret !== null) { return $secret; }
    $local = __DIR__ . '/../config/visits.local.php';
    if (is_file($local)) {
        $config = require $local;
        if (is_array($config) && is_string($config['secret'] ?? null) && strlen($config['secret']) >= 16) { return $secret = $config['secret']; }
    }
    // MySQL mode: derived from the database password, so nothing has to be written anywhere.
    $db = visits_db() ? content_db_config() : null;
    if (is_array($db) && is_string($db['pass'] ?? null) && strlen($db['pass']) >= 8) {
        return $secret = hash_hmac('sha256', 'edu2impact-visits|' . ($db['name'] ?? ''), $db['pass']);
    }
    $path = dirname(visits_file()) . '/.visits-secret';
    $stored = is_file($path) ? trim((string) @file_get_contents($path)) : '';
    if (strlen($stored) < 32) {
        $stored = bin2hex(random_bytes(32));
        @file_put_contents($path, $stored . "\n", LOCK_EX);
    }
    return $secret = $stored;
}

function visits_today(?DateTimeImmutable $now = null): string {
    return ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('Y-m-d');
}

// Read the counters. Returns the decoded structure, or an empty one when the file is missing or damaged.
function visits_read(): array {
    $empty = ['pages' => [], 'site' => ['total' => 0, 'days' => []], 'today' => ['date' => '', 'seen' => []]];
    $json = @file_get_contents(visits_file());
    $data = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($data) || !is_array($data['pages'] ?? null)) { return $empty; }
    $data['site'] = is_array($data['site'] ?? null) ? $data['site'] : $empty['site'];
    $data['today'] = is_array($data['today'] ?? null) ? $data['today'] : $empty['today'];
    return $data;
}

// Should this request be counted? $ctx can override the request for tests:
// method, ip, ua, purpose, now (DateTimeImmutable), status.
function visits_countable(array $ctx): bool {
    if (($ctx['method'] ?? 'GET') !== 'GET') { return false; }
    if (($ctx['status'] ?? 200) !== 200) { return false; } // 404s and redirects are not visits
    if (stripos((string) ($ctx['purpose'] ?? ''), 'prefetch') !== false) { return false; }
    $ua = trim((string) ($ctx['ua'] ?? ''));
    if ($ua === '' || preg_match(VISITS_BOT_PATTERN, $ua)) { return false; }
    return trim((string) ($ctx['ip'] ?? '')) !== '';
}

// Count one visit to $pageKey. Returns true when the visit was new and counted.
function record_visit(string $pageKey, ?array $ctx = null): bool {
    if ($pageKey === '' || defined('ADMIN_PREVIEW')) { return false; } // the admin preview is never a visit
    $ctx ??= [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'purpose' => ($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_X_MOZ'] ?? ''),
        'status' => http_response_code() ?: 200,
    ];
    if (!visits_countable($ctx)) { return false; }
    try {
        $date = visits_today($ctx['now'] ?? null);
        $secret = visits_secret();
        $hash = static fn (string $scope): string => substr(hash_hmac('sha256', $ctx['ip'] . '|' . $ctx['ua'] . '|' . $scope . '|' . $date, $secret), 0, 20);
        $pageHash = $hash('page:' . $pageKey);
        $siteHash = $hash('site');

        if ($pdo = visits_db()) {
            try { return visits_record_db($pdo, $pageKey, $date, $pageHash); }
            catch (Throwable $exception) { visits_db_failed($exception); }
        }
        if (!visits_prepare()) { return false; }
        $handle = @fopen(visits_file(), 'c+');
        if (!$handle) { return visits_warn('cannot open ' . visits_file()); }
        try {
            if (!flock($handle, LOCK_EX)) { return false; }
            $raw = stream_get_contents($handle);
            $data = $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($data) || !is_array($data['pages'] ?? null)) {
                $data = ['pages' => [], 'site' => ['total' => 0, 'days' => []], 'today' => ['date' => $date, 'seen' => []]];
            }
            $data['site'] = is_array($data['site'] ?? null) ? $data['site'] : ['total' => 0, 'days' => []];
            if (($data['today']['date'] ?? '') !== $date || !is_array($data['today']['seen'] ?? null)) {
                $data['today'] = ['date' => $date, 'seen' => []];
            }
            $seen = array_flip($data['today']['seen']);
            $counted = false; $pageCounted = false;
            if (!isset($seen[$pageHash])) {
                $data['today']['seen'][] = $pageHash;
                $page = $data['pages'][$pageKey] ?? ['total' => 0, 'days' => []];
                $page['total'] = (int) ($page['total'] ?? 0) + 1;
                $page['days'][$date] = (int) ($page['days'][$date] ?? 0) + 1;
                $data['pages'][$pageKey] = $page;
                $counted = $pageCounted = true;
            }
            if (!isset($seen[$siteHash])) {
                $data['today']['seen'][] = $siteHash;
                $data['site']['total'] = (int) ($data['site']['total'] ?? 0) + 1;
                $data['site']['days'][$date] = (int) ($data['site']['days'][$date] ?? 0) + 1;
                $counted = true;
            }
            if ($counted) {
                $cutoff = (new DateTimeImmutable($date))->modify('-' . VISITS_KEEP_DAYS . ' days')->format('Y-m-d');
                foreach ($data['pages'] as $key => $page) {
                    $data['pages'][$key]['days'] = array_filter($page['days'] ?? [], static fn ($day) => $day >= $cutoff, ARRAY_FILTER_USE_KEY);
                }
                $data['site']['days'] = array_filter($data['site']['days'] ?? [], static fn ($day) => $day >= $cutoff, ARRAY_FILTER_USE_KEY);
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                fflush($handle);
            }
            flock($handle, LOCK_UN);
            return $pageCounted;
        } finally {
            fclose($handle);
        }
    } catch (Throwable $exception) {
        return false;
    }
}

// MySQL mode: one visitor opening one post once per day. INSERT IGNORE on the unique hash decides who is new, so two
// requests at the same moment can never both count. Returns true when this visit was counted.
function visits_record_db(PDO $pdo, string $pageKey, string $date, string $pageHash): bool {
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $seen = $pdo->prepare('INSERT IGNORE INTO post_view_seen (hash, day) VALUES (?, ?)');
        $seen->execute([$pageHash, $date]);
        $counted = $seen->rowCount() === 1;
        if ($counted) {
            $pdo->prepare('INSERT INTO post_views (page_key, total, updated_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE total = total + 1, updated_at = VALUES(updated_at)')->execute([$pageKey, $now]);
            $pdo->prepare('INSERT INTO post_view_days (page_key, day, views) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE views = views + 1')->execute([$pageKey, $date]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
    if (random_int(1, 100) === 1) { // no cron on the host: tidy up now and then
        $pdo->prepare('DELETE FROM post_view_seen WHERE day < ?')->execute([$date]);
        $pdo->prepare('DELETE FROM post_view_days WHERE day < ?')->execute([(new DateTimeImmutable($date))->modify('-' . VISITS_KEEP_DAYS . ' days')->format('Y-m-d')]);
    }
    return $counted;
}

// Total visits to one page, or 0.
function visit_count(string $pageKey): int {
    if ($pdo = visits_db()) {
        try {
            $stmt = $pdo->prepare('SELECT total FROM post_views WHERE page_key = ?');
            $stmt->execute([$pageKey]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $exception) { visits_db_failed($exception); }
    }
    return (int) (visits_read()['pages'][$pageKey]['total'] ?? 0);
}

// Total visits to every counted page (file mode: distinct visitors per day on the whole site, added up), or 0.
function visit_total(): int {
    if ($pdo = visits_db()) {
        try { return (int) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM post_views')->fetchColumn(); }
        catch (Throwable $exception) { visits_db_failed($exception); }
    }
    return (int) (visits_read()['site']['total'] ?? 0);
}

// "1,234 ครั้ง" text, or '' when there is nothing real to show.
function visit_label(int $count): string {
    return $count > 0 ? number_format($count) . ' ครั้ง' : '';
}

// Eye icon + count, e.g. [eye] 1,234; '' when the count is 0. Screen readers hear "<$spoken> 1,234 ครั้ง"
// ("เข้าชม ..." by default); $shown is an optional visible word before the number (the footer uses "ทั้งหมด").
function visit_eye(int $count, string $spoken = 'เข้าชม', string $shown = ''): string {
    if ($count <= 0) { return ''; }
    $n = number_format($count);
    $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    return '<span class="views" title="จำนวนการเข้าชม"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>'
        . '<span aria-hidden="true">' . ($shown !== '' ? $h($shown) . ' ' : '') . $n . '</span><span class="visually-hidden">' . $h($spoken) . ' ' . $n . ' ครั้ง</span></span>';
}
