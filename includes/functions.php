<?php
// All content files are public information. Never store credentials in data/.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function load_data($name) {
    $allowed = ['site', 'research', 'innovations', 'workshops', 'events'];
    if (!in_array($name, $allowed, true)) { throw new RuntimeException('Invalid dataset'); }
    $text = file_get_contents(__DIR__ . '/../data/' . $name . '.json');
    if ($text === false) { throw new RuntimeException('Cannot read dataset'); }
    $data = json_decode($text, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON data');
    }
    return $data;
}
function query_string($key) {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
}
function find_record($records, $id) {
    foreach ($records as $record) {
        if (isset($record['id']) && $record['id'] === $id) { return $record; }
    }
    return null;
}
function image_path($record) {
    $path = $record['image'] ?? '';
    // Local image assets only; no traversal, external URLs or executable formats.
    if (is_string($path) && preg_match('~\Aassets/images/[a-zA-Z0-9_/-]+\.(?:png|jpg|jpeg|webp)\z~', $path)
        && is_file(__DIR__ . '/../' . $path)) { return $path; }
    return '';
}
// Optional link to a web tool: https:// URLs or a local apps/<name>/ folder that exists.
function tool_url($record) {
    $url = $record['tool_url'] ?? '';
    if (!is_string($url) || $url === '') { return ''; }
    if (preg_match('~\Ahttps://[^\s<>"]+\z~', $url)) { return $url; }
    if (preg_match('~\Aapps/[a-z0-9-]+/\z~', $url) && is_file(__DIR__ . '/../' . $url . 'index.html')) { return $url; }
    return '';
}
// Optional PDF download: a local assets/files/<name>.pdf that exists. No external URLs, no traversal.
function download_url($record) {
    $url = $record['download_url'] ?? '';
    if (!is_string($url) || !preg_match('~\Aassets/files/[a-z0-9-]+\.pdf\z~', $url)) { return ''; }
    return is_file(__DIR__ . '/../' . $url) ? $url : '';
}
// Human-readable size of a local file, e.g. "590 KB" or "1.2 MB".
function file_size_label($path) {
    $bytes = is_file(__DIR__ . '/../' . $path) ? filesize(__DIR__ . '/../' . $path) : 0;
    if ($bytes <= 0) { return ''; }
    if ($bytes >= 1048576) { return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB'; }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}
// Optional list of non-empty strings (audience, subjects, features, references).
function text_list($record, $key) {
    $value = $record[$key] ?? [];
    if (!is_array($value)) { return []; }
    return array_values(array_filter($value, static fn ($item) => is_string($item) && trim($item) !== ''));
}
function detail_url($kind, $id) {
    $routes = ['research' => 'research-detail.php', 'innovations' => 'innovation-detail.php', 'workshops' => 'workshop-detail.php'];
    return $routes[$kind] . '?id=' . rawurlencode($id);
}

function hero_text($key) {
    static $heroes;
    if ($heroes === null) { $heroes = require __DIR__ . '/../config/heroes.php'; }
    return $heroes[$key];
}
// Title with the highlight wrapped in <em> (everything escaped); used by the home slider.
function hero_title_html($hero) {
    $pos = strpos($hero['title'], $hero['highlight']);
    if ($pos === false) { return e($hero['title']); }
    return e(substr($hero['title'], 0, $pos)) . '<em>' . e($hero['highlight']) . '</em>' . e(substr($hero['title'], $pos + strlen($hero['highlight'])));
}
function render_page_hero($key) {
    $hero = hero_text($key);
    ?><section class="page-hero page-hero--editorial"><div class="container page-hero-content"><p class="eyebrow"><?= e($hero['eyebrow']) ?></p><h1><?= e($hero['title']) ?></h1><p><?= e($hero['intro']) ?></p></div></section><?php
}

// ---- Listing layouts ----
// Day, month, Thai weekday and year of a Y-m-d date; null when the value is not a valid date.
function listing_date_parts($value) {
    $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
    if (!$date) { return null; }
    $weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    return ['day' => $date->format('d'), 'month' => strtoupper($date->format('M')), 'weekday' => 'วัน' . $weekdays[(int) $date->format('w')], 'year' => $date->format('Y')];
}
// Today in Bangkok as Y-m-d (used to split upcoming and past workshops).
function listing_today() {
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
}

// "2026-10-15" -> "15 ต.ค. 2569" (Buddhist era). Returns the input unchanged if it is not a valid Y-m-d date.
function thai_date($iso) {
    $iso = (string) $iso;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    if (!$d || $d->format('Y-m-d') !== $iso) { return $iso; }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int) $d->format('j') . ' ' . $months[(int) $d->format('n') - 1] . ' ' . ((int) $d->format('Y') + 543);
}
// Estimated reading minutes for Thai text (~500 characters per minute, minimum 1). 0 when there is no body.
function reading_time($body) {
    if (!is_array($body)) { return 0; }
    $chars = 0;
    foreach ($body as $paragraph) {
        if (is_string($paragraph)) { $chars += mb_strlen(preg_replace('/\s+/u', '', $paragraph)); }
    }
    return $chars > 0 ? max(1, (int) ceil($chars / 500)) : 0;
}
// First letter for an avatar; skips Thai leading vowels (เ แ โ ใ ไ) that cannot stand alone.
function author_initial($name) {
    $name = preg_replace('/^[^\p{L}\p{N}]+/u', '', trim((string) $name));
    $name = preg_replace('/^[เแโใไ]+/u', '', $name);
    return $name === '' ? '' : mb_strtoupper(mb_substr($name, 0, 1));
}
