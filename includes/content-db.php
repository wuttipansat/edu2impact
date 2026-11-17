<?php
// Content from MySQL (tables in database/content-schema.sql and database/updates/2026-10-27-01-structure.sql).
// load_data() in includes/functions.php calls content_db_load() only when private/database.php has
// 'content_source' => 'mysql'. Every function returns records in the same shape as data/*.json (and, for promos and
// videos, config/home.php), so pages do not need to know where the content came from.
//
// Failures (round 13): when MySQL is switched on, content no longer falls back to data/*.json, because the JSON copy
// falls behind as soon as content is edited in phpMyAdmin. If the database cannot be reached or a query fails,
// content_db_load() throws ContentUnavailable and the page answers 503 (see content_unavailable()).
// One exception keeps uploads safe: a table added by a later migration that has not been imported yet (MySQL error
// 1146, "table doesn't exist") makes that one dataset read its old file, and the error log says which migration to run.

final class ContentUnavailable extends RuntimeException {}

// The credentials file, the same one includes/db.php uses for the account pages.
function content_db_config(): ?array {
    static $config = false;
    if ($config !== false) { return $config; }
    $path = getenv('EDU2IMPACT_DB_CONFIG') ?: dirname(__DIR__) . '/private/database.php';
    $loaded = is_readable($path) ? require $path : null;
    $config = is_array($loaded) ? $loaded : null;
    return $config;
}

// 'mysql' or 'json'. EDU2IMPACT_DATA_SOURCE overrides the file (used by tools/compare_sources.php).
function content_source(): string {
    $env = getenv('EDU2IMPACT_DATA_SOURCE');
    if ($env === 'json' || $env === 'mysql') { return $env; }
    $config = content_db_config();
    return ($config['content_source'] ?? 'json') === 'mysql' ? 'mysql' : 'json';
}

// One connection per request. Null when it fails; the failure is logged once per request.
function content_pdo(): ?PDO {
    static $pdo = null, $failed = false;
    if ($pdo || $failed) { return $pdo; }
    $config = content_db_config();
    try {
        foreach (['host', 'name', 'user', 'pass'] as $key) {
            if (!isset($config[$key]) || !is_string($config[$key])) { throw new RuntimeException('Invalid database configuration'); }
        }
        $pdo = new PDO(
            'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 3,
            ]
        );
    } catch (Throwable $exception) {
        $failed = true;
        $pdo = null;
        error_log('edu2impact: MySQL unavailable (' . $exception->getMessage() . ')');
    }
    return $pdo;
}

// True for "table doesn't exist" (a migration not imported yet).
function content_db_missing_table(Throwable $exception): bool {
    return $exception instanceof PDOException && (($exception->errorInfo[1] ?? null) === 1146 || $exception->getCode() === '42S02');
}

// Records for one dataset. Null only when the dataset's table has not been created yet (read the old file instead).
// Throws ContentUnavailable when MySQL cannot be used.
function content_db_load(string $name): ?array {
    static $cache = [];
    if (array_key_exists($name, $cache)) { return $cache[$name]; }
    $pdo = content_pdo();
    if (!$pdo) { throw new ContentUnavailable('MySQL connection failed'); }
    try {
        $data = match ($name) {
            'site' => content_db_site($pdo),
            'events' => content_db_events($pdo),
            'promos', 'videos' => content_db_home_items($pdo, $name === 'promos' ? 'promo' : 'video'),
            'shop' => content_db_shop($pdo),
            'network' => content_db_network($pdo),
            'community' => content_db_community($pdo),
            'network-highlights' => content_db_network_highlights($pdo),
            'network-photos' => content_db_network_photos($pdo),
            'network-activities' => content_db_network_activities($pdo),
            default => content_db_posts($pdo, $name),
        };
    } catch (Throwable $exception) {
        if (content_db_missing_table($exception)) {
            $migration = in_array($name, ['network', 'community'], true) ? '2026-11-06-01-network.sql' : ($name === 'network-highlights' ? '2026-11-06-02-network-highlights.sql' : ($name === 'network-photos' ? '2026-11-16-03-network-photos.sql' : ($name === 'network-activities' ? '2026-11-16-05-network-activities.sql' : '2026-10-27-01-structure.sql')));
            error_log('edu2impact: table for ' . $name . ' not found, reading its file instead. Import database/updates/' . $migration . ' (' . $exception->getMessage() . ')');
            return $cache[$name] = null;
        }
        error_log('edu2impact: cannot read ' . $name . ' from MySQL (' . $exception->getMessage() . ')');
        throw new ContentUnavailable('Cannot read ' . $name, 0, $exception);
    }
    return $cache[$name] = $data;
}

function content_db_site(PDO $pdo): array {
    $data = [];
    foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
        $value = $row['setting_value'];
        $data[$row['setting_key']] = $value === 'true' ? true : ($value === 'false' ? false : (string) $value);
    }
    if ($data === []) { throw new RuntimeException('site_settings is empty'); }
    return $data;
}

// Events in file order. slug, featured and is_public exist after the round 13 migration; before it every event shows.
function content_db_events(PDO $pdo): array {
    $data = [];
    foreach ($pdo->query('SELECT * FROM events ORDER BY sort_order, id') as $row) {
        if (isset($row['is_public']) && (int) $row['is_public'] !== 1) { continue; }
        $event = [];
        if (($row['slug'] ?? null) !== null && $row['slug'] !== '') { $event['id'] = $row['slug']; }
        $event += ['title' => $row['title'], 'meta' => (string) $row['meta'], 'type' => (string) $row['type'], 'date' => (string) $row['event_date']];
        if (isset($row['featured'])) { $event['featured'] = (bool) $row['featured']; }
        $data[] = $event;
    }
    return $data;
}

// Public posts of one kind in the order of the old JSON file. Hidden posts (is_public = 0) are left out,
// just as load_data() leaves out "public": false.
function content_db_posts(PDO $pdo, string $kind): array {
    $links = [];
    $statement = $pdo->prepare('SELECT from_slug, to_kind, to_slug FROM post_links WHERE from_kind = ? ORDER BY sort_order');
    $statement->execute([$kind]);
    foreach ($statement as $row) {
        $links[$row['from_slug']][] = ['kind' => $row['to_kind'], 'id' => $row['to_slug']];
    }
    $statement = $pdo->prepare('SELECT * FROM posts WHERE kind = ? AND is_public = 1 ORDER BY sort_order, id');
    $statement->execute([$kind]);
    $records = [];
    foreach ($statement as $row) {
        $record = [
            'id' => $row['slug'],
            'title' => $row['title'],
            'summary' => (string) $row['summary'],
            'image' => content_db_image_ref($row),
            'date' => (string) $row['post_date'],
            'author' => (string) $row['author'],
            'featured' => (bool) $row['featured'],
            'public' => true,
            'body' => content_db_body(content_db_json($row['body']) ?? []),
        ];
        if ($row['category'] !== null) { $record['category'] = $row['category']; }
        if ($row['tool_url'] !== null) { $record['tool_url'] = $row['tool_url']; }
        if ($row['tool_label'] !== null) { $record['tool_label'] = $row['tool_label']; }
        foreach (['audience', 'subjects', 'features'] as $key) {
            $list = content_db_json($row[$key] ?? null);
            if ($list !== null) { $record[$key] = $list; }
        }
        if (!empty($row['download_id'])) { $record['download_url'] = 'media:' . (int) $row['download_id']; }
        if (($row['download_label'] ?? null) !== null && $row['download_label'] !== '') { $record['download_label'] = $row['download_label']; }
        $references = content_db_json($row['refs']);
        if ($references !== null) { $record['references'] = $references; }
        if (isset($links[$row['slug']])) { $record['related'] = $links[$row['slug']]; }
        $records[] = $record;
    }
    return $records;
}

// "media:<id>" when the row has an image_id, otherwise the old image path ('' when none).
function content_db_image_ref(array $row): string {
    if (!empty($row['image_id'])) { return 'media:' . (int) $row['image_id']; }
    return (string) ($row['image'] ?? '');
}

// Body blocks written as {"media": 12, "alt": "…", "caption": "…"} become {"image": "media:12", …}, the shape the
// detail page already reads. Paragraphs and old {"image": "assets/images/…"} blocks pass through unchanged.
function content_db_body(array $body): array {
    foreach ($body as $i => $block) {
        if (is_array($block) && isset($block['media']) && !isset($block['image'])) {
            $id = filter_var($block['media'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            unset($block['media']);
            $body[$i] = ['image' => $id ? 'media:' . $id : ''] + $block;
        }
    }
    return $body;
}

// "ประชาสัมพันธ์" (promo) and "Scoop" (video) rows in the shape of config/home.php.
function content_db_home_items(PDO $pdo, string $type): array {
    $statement = $pdo->prepare('SELECT * FROM home_items WHERE type = ? AND is_public = 1 ORDER BY sort_order, id');
    $statement->execute([$type]);
    $rows = [];
    foreach ($statement as $row) {
        $item = ['title' => $row['title']];
        if ($row['item_date'] !== null) { $item['date'] = (string) $row['item_date']; }
        if ($type === 'promo') {
            $item['label'] = (string) $row['label'];
            $item['image'] = content_db_image_ref($row);
            $item['url'] = (string) $row['url'];
        } else {
            $item['youtube'] = (string) $row['youtube'];
            $image = content_db_image_ref($row);
            if ($image !== '') { $item['image'] = $image; }
        }
        $rows[] = $item;
    }
    return $rows;
}

// Shop items in the shape of data/shop.json. A price that is a plain number becomes a number, so shop_price() formats it.
function content_db_shop(PDO $pdo): array {
    $items = [];
    foreach ($pdo->query('SELECT * FROM shop_items WHERE is_public = 1 ORDER BY sort_order, id') as $row) {
        $price = trim((string) $row['price']);
        if (preg_match('/\A[0-9]+\z/', $price)) { $price = (int) $price; }
        elseif (preg_match('/\A[0-9]+\.[0-9]+\z/', $price)) { $price = (float) $price; }
        $item = ['id' => $row['slug'], 'title' => $row['title'], 'summary' => (string) $row['summary'], 'image' => content_db_image_ref($row), 'public' => true];
        if ($price !== '') { $item['price'] = $price; }
        if ($row['order_url'] !== null && $row['order_url'] !== '') { $item['order_url'] = $row['order_url']; }
        $items[] = $item;
    }
    return $items;
}

// เครือข่ายวิจัยนานาชาติ (network_partners) in the shape of data/network.json. Keys are the column names.
function content_db_network(PDO $pdo): array {
    $records = [];
    foreach ($pdo->query('SELECT * FROM network_partners WHERE is_public = 1 ORDER BY sort_order, id') as $row) {
        $record = ['id' => (int) $row['id'], 'country_code' => (string) $row['country_code'], 'institution' => (string) $row['institution']];
        // summary and people arrive with 2026-11-16-01-network-tags.sql; before that the columns are simply absent.
        foreach (['institution_th', 'summary', 'people', 'description', 'url'] as $key) {
            if (isset($row[$key]) && $row[$key] !== '') { $record[$key] = (string) $row[$key]; }
        }
        $image = content_db_image_ref($row); // image_id / image arrive with the same migration
        if ($image !== '') { $record['image'] = $image; }
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($row['tags'] ?? '')))));
        if ($tags) { $record['tags'] = $tags; }
        $related = content_db_json($row['related'] ?? null);
        if ($related) { $record['related'] = $related; }
        $records[] = $record;
    }
    return $records;
}

// ภาพกิจกรรมกับเครือข่าย (network_photos) in the shape of data/network-photos.json: image (media:<id> or a path), caption,
// in_strip, and the activity the photo belongs to (activity_id, title, period, partner_id, institution) when there is one.
// Before 2026-11-16-05-network-activities.sql there is no activity_id / in_strip: every photo goes in the strip.
function content_db_network_photos(PDO $pdo): array {
    try {
        $rows = $pdo->query('SELECT ph.*, a.title, a.period, a.partner_id, p.institution FROM network_photos ph
            LEFT JOIN network_activities a ON a.id = ph.activity_id AND a.is_public = 1
            LEFT JOIN network_partners p ON p.id = a.partner_id AND p.is_public = 1
            WHERE ph.is_public = 1 ORDER BY ph.sort_order, ph.id')->fetchAll();
    } catch (PDOException $exception) {
        $rows = $pdo->query('SELECT * FROM network_photos WHERE is_public = 1 ORDER BY sort_order, id')->fetchAll();
    }
    $records = [];
    foreach ($rows as $row) {
        $image = content_db_image_ref($row);
        if ($image === '') { continue; }
        $record = ['image' => $image, 'caption' => (string) $row['caption'], 'in_strip' => (int) ($row['in_strip'] ?? 1) === 1];
        if (!empty($row['partner_id']) && isset($row['institution'])) {
            $record += ['activity_id' => (int) $row['activity_id'], 'title' => (string) $row['title'], 'period' => (string) ($row['period'] ?? ''), 'partner_id' => (int) $row['partner_id'], 'institution' => (string) $row['institution']];
        }
        $records[] = $record;
    }
    return $records;
}

// กิจกรรมของสถาบัน (network_activities) in the shape of data/network-activities.json: id, partner_id, direction,
// period, title, body, photos [{image, caption}] (every public photo of the activity, also those not in the strip).
function content_db_network_activities(PDO $pdo): array {
    $photos = [];
    foreach ($pdo->query('SELECT * FROM network_photos WHERE is_public = 1 AND activity_id IS NOT NULL ORDER BY id') as $row) {
        $image = content_db_image_ref($row);
        if ($image !== '') { $photos[(int) $row['activity_id']][] = ['image' => $image, 'caption' => (string) $row['caption']]; }
    }
    $records = [];
    foreach ($pdo->query('SELECT * FROM network_activities WHERE is_public = 1 ORDER BY sort_order, id') as $row) {
        $records[] = ['id' => (int) $row['id'], 'partner_id' => (int) $row['partner_id'], 'direction' => (string) $row['direction'],
            'period' => (string) ($row['period'] ?? ''), 'title' => (string) $row['title'], 'body' => (string) ($row['body'] ?? ''),
            'photos' => $photos[(int) $row['id']] ?? []];
    }
    return $records;
}

// นวัตกรรมสู่ชุมชน (community_projects) in the shape of data/community.json. project_year is the "year" key.
function content_db_community(PDO $pdo): array {
    $records = [];
    foreach ($pdo->query('SELECT * FROM community_projects WHERE is_public = 1 ORDER BY sort_order, id') as $row) {
        $record = ['province_code' => (string) $row['province_code'], 'title' => (string) $row['title']];
        foreach (['community', 'summary'] as $key) {
            if ($row[$key] !== null && $row[$key] !== '') { $record[$key] = (string) $row[$key]; }
        }
        if ($row['project_year'] !== null) { $record['year'] = (int) $row['project_year']; }
        $image = content_db_image_ref($row);
        if ($image !== '') { $record['image'] = $image; }
        $related = content_db_json($row['related'] ?? null);
        if ($related) { $record['related'] = $related; }
        $records[] = $record;
    }
    return $records;
}

// รูปแบบความร่วมมือ and คุณค่าที่เกิดกับหลักสูตรและนิสิต (network_highlights) in the shape of data/network-highlights.json.
function content_db_network_highlights(PDO $pdo): array {
    $records = [];
    foreach ($pdo->query('SELECT * FROM network_highlights WHERE is_public = 1 ORDER BY sort_order, id') as $row) {
        $record = ['kind' => (string) $row['kind'], 'title_th' => (string) $row['title_th'], 'icon' => (string) $row['icon']];
        if ($row['title_en'] !== null && $row['title_en'] !== '') { $record['title_en'] = (string) $row['title_en']; }
        $records[] = $record;
    }
    return $records;
}

// Word boundaries for thai_text() (tools/segment_thai.py writes them). Null when the table is missing; [] on other errors,
// because line breaking is cosmetic and must never take a page down.
function content_db_thai_breaks(): ?array {
    $pdo = content_pdo();
    if (!$pdo) { return []; }
    try {
        $map = [];
        foreach ($pdo->query('SELECT hash, offsets FROM thai_breaks') as $row) {
            $offsets = json_decode((string) $row['offsets'], true);
            if (is_array($offsets)) { $map[$row['hash']] = $offsets; }
        }
        return $map;
    } catch (Throwable $exception) {
        if (content_db_missing_table($exception)) { return null; }
        error_log('edu2impact: cannot read thai_breaks (' . $exception->getMessage() . ')');
        return [];
    }
}

function content_db_json(?string $text): ?array {
    if ($text === null || $text === '') { return null; }
    $value = json_decode($text, true);
    return is_array($value) ? $value : null;
}

// 503 page shown when MySQL is switched on but cannot be read. Anything already buffered is discarded, so a failure
// halfway through a page never shows half a page. Retry-After tells browsers and search engines to come back soon.
function content_unavailable(): never {
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 300');
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>ปิดปรับปรุงชั่วคราว | KUEDU2IMPACT</title><link rel="stylesheet" href="assets/css/theme.css"></head>'
        . '<body><main id="main" class="container section empty-state">'
        . '<h1>ปิดปรับปรุงชั่วคราว</h1><p>ระบบฐานข้อมูลขัดข้อง กรุณาลองใหม่อีกครั้งในอีกสักครู่</p></main></body></html>';
    exit;
}
