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
