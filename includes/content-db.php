<?php
// Content from MySQL (tables in database/content-schema.sql).
// load_data() in includes/functions.php calls content_db_load() only when private/database.php has
// 'content_source' => 'mysql'. Every function returns records in the same shape as data/*.json,
// so pages do not need to know where the content came from.
// If the database cannot be reached, content_db_load() returns null and load_data() reads the JSON files.

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
        error_log('edu2impact: MySQL unavailable, using data/*.json instead (' . $exception->getMessage() . ')');
    }
    return $pdo;
}

// Records for one dataset, or null when MySQL cannot be used.
function content_db_load(string $name): ?array {
    static $cache = [];
    if (array_key_exists($name, $cache)) { return $cache[$name]; }
    $pdo = content_pdo();
    if (!$pdo) { return null; }
    try {
        if ($name === 'site') {
            $data = [];
            foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
                $value = $row['setting_value'];
                $data[$row['setting_key']] = $value === 'true' ? true : ($value === 'false' ? false : (string) $value);
            }
            if ($data === []) { throw new RuntimeException('site_settings is empty'); }
        } elseif ($name === 'events') {
            $data = [];
            foreach ($pdo->query('SELECT title, meta, type, event_date FROM events ORDER BY sort_order, id') as $row) {
                $data[] = ['title' => $row['title'], 'meta' => (string) $row['meta'], 'type' => (string) $row['type'], 'date' => (string) $row['event_date']];
            }
        } else {
            $data = content_db_posts($pdo, $name);
        }
    } catch (Throwable $exception) {
        error_log('edu2impact: cannot read ' . $name . ' from MySQL, using JSON (' . $exception->getMessage() . ')');
        return null;
    }
    return $cache[$name] = $data;
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
            'image' => (string) $row['image'],
            'date' => (string) $row['post_date'],
            'author' => (string) $row['author'],
            'featured' => (bool) $row['featured'],
            'public' => true,
            'body' => content_db_json($row['body']) ?? [],
        ];
        if ($row['category'] !== null) { $record['category'] = $row['category']; }
        if ($row['tool_url'] !== null) { $record['tool_url'] = $row['tool_url']; }
        if ($row['tool_label'] !== null) { $record['tool_label'] = $row['tool_label']; }
        $references = content_db_json($row['refs']);
        if ($references !== null) { $record['references'] = $references; }
        if (isset($links[$row['slug']])) { $record['related'] = $links[$row['slug']]; }
        $records[] = $record;
    }
    return $records;
}

function content_db_json(?string $text): ?array {
    if ($text === null || $text === '') { return null; }
    $value = json_decode($text, true);
    return is_array($value) ? $value : null;
}
