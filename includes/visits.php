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

const VISITS_KEEP_DAYS = 90;
const VISITS_BOT_PATTERN = '~bot|crawl|spider|slurp|preview|facebookexternalhit|facebot|whatsapp|telegram|curl|wget|python-requests|python-urllib|headless|lighthouse|pingdom|uptime|monitor|scanner|httpclient|go-http-client|java/|libwww~i';

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
    if ($pageKey === '') { return false; }
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

// Total visits to one page, or 0.
function visit_count(string $pageKey): int {
    return (int) (visits_read()['pages'][$pageKey]['total'] ?? 0);
}

// Total visits to the whole site (distinct visitors per day, added up), or 0.
function visit_total(): int {
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
