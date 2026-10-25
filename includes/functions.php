<?php
// All content files are public information. Never store credentials in data/.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function load_data($name) {
    $allowed = ['site', 'research', 'innovations', 'workshops', 'media', 'news', 'events', 'shop'];
    if (!in_array($name, $allowed, true)) { throw new RuntimeException('Invalid dataset'); }
    // Content can live in MySQL (see includes/content-db.php). When it is switched on but cannot be read,
    // the JSON files below are used, so the site keeps working.
    require_once __DIR__ . '/content-db.php';
    // 'shop' stays in data/shop.json: the posts table has no price column yet.
    if ($name !== 'shop' && content_source() === 'mysql') {
        $fromDatabase = content_db_load($name);
        if ($fromDatabase !== null) { return $fromDatabase; }
    }
    $text = file_get_contents(__DIR__ . '/../data/' . $name . '.json');
    if ($text === false) { throw new RuntimeException('Cannot read dataset'); }
    $data = json_decode($text, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON data');
    }
    // "public": false hides a record everywhere: listings, detail pages (404), home, search and related cards.
    // A record without the key counts as public, so older content keeps showing.
    if ($name !== 'site') {
        $data = array_values(array_filter($data, static fn ($record): bool => !is_array($record) || ($record['public'] ?? true) !== false));
    }
    return $data;
}
// Price for display: a number becomes "฿1,200"; text such as "ราคา 1,200 บาท" is shown as written; empty gives ''.
function shop_price($value) {
    if (is_int($value) || is_float($value)) { return '฿' . number_format($value, $value == (int) $value ? 0 : 2); }
    return trim((string) $value);
}
// Order link of a product: only http(s) addresses are used, anything else sends the visitor to the contact page.
function shop_order_url($record) {
    $url = trim((string) ($record['order_url'] ?? ''));
    return preg_match('~^https?://~i', $url) ? $url : '';
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
// Public address of the site from data/site.json ("https://…", no trailing slash), or '' when not set.
function site_url(): string {
    static $url = null;
    if ($url === null) {
        $value = '';
        try { $value = trim((string) (load_data('site')['site_url'] ?? '')); } catch (Throwable $exception) { $value = ''; }
        $url = preg_match('~\Ahttps://[^\s/]+(?:/[^\s?#]*)?\z~', $value) ? rtrim($value, '/') : '';
    }
    return $url;
}
// Absolute address for a site path such as "research.php" or "assets/images/x.webp". '' while site_url is not set,
// so nothing ever points at a guessed domain.
function absolute_url(string $path): string {
    $base = site_url();
    return $base === '' ? '' : $base . '/' . ltrim($path, '/');
}
// Share image for Facebook, LINE and X: the 1200x630 JPEG twin made by tools/make_og_images.py
// (assets/images/og/<name>.jpg) when it exists, otherwise the default card. Never a WebP.
function share_image_path(?string $cover): string {
    if ($cover !== null && preg_match('~\Aassets/images/([a-zA-Z0-9_-]+)\.(?:png|jpg|jpeg|webp)\z~', $cover, $match)
        && is_file(__DIR__ . '/../assets/images/og/' . $match[1] . '.jpg')) {
        return 'assets/images/og/' . $match[1] . '.jpg';
    }
    return 'assets/images/og-default.jpg';
}
function image_path($record) {
    $path = $record['image'] ?? '';
    // Local image assets only; no traversal, external URLs or executable formats.
    if (is_string($path) && preg_match('~\Aassets/images/[a-zA-Z0-9_/-]+\.(?:png|jpg|jpeg|webp)\z~', $path)
        && is_file(__DIR__ . '/../' . $path)) { return $path; }
    return '';
}
// A body item is a paragraph (string) or an image block {"image": "assets/images/…", "alt": "…", "caption": "…"}.
// Returns the image path when the block is a valid local image, otherwise ''.
function body_image($block) {
    if (!is_array($block) || !is_string($block['alt'] ?? null) || trim($block['alt']) === '') { return ''; }
    return image_path($block);
}
// Paragraphs and image captions of a body as text (for search); image paths and alt text are left out.
function body_text($body) {
    if (!is_array($body)) { return is_string($body) ? [$body] : []; }
    $text = [];
    foreach ($body as $block) {
        if (is_string($block)) { $text[] = $block; }
        elseif (is_array($block) && is_string($block['caption'] ?? null)) { $text[] = $block['caption']; }
    }
    return $text;
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
// Newest first by date; with $featuredFirst, records marked "featured": true come before the rest.
function sort_records(array $records, bool $featuredFirst = false): array {
    usort($records, static function (array $a, array $b) use ($featuredFirst): int {
        if ($featuredFirst && ($fa = !empty($a['featured'])) !== ($fb = !empty($b['featured']))) { return $fa ? -1 : 1; }
        return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? ''));
    });
    return $records;
}
// ---- Dates and links between records ----
// True when the record has a real Y-m-d date. Workshop records with "date": "" are posts (articles, not sessions).
function is_dated($record) {
    $value = is_array($record) ? ($record['date'] ?? '') : '';
    if (!is_string($value) || $value === '') { return false; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}
// Thai label for a linkable dataset; '' for anything else.
function kind_label($kind) {
    $labels = ['research' => 'งานวิจัย', 'innovations' => 'นวัตกรรม', 'workshops' => 'เวิร์กช็อป'];
    return $labels[$kind] ?? '';
}
// Label for any post type shown in a mixed feed (the home page "latest" list); '' for anything else.
function post_kind_label($kind) {
    $labels = ['research' => 'งานวิจัย', 'innovations' => 'นวัตกรรม', 'workshops' => 'เวิร์กช็อป', 'media' => 'สื่อและคู่มือ', 'news' => 'ข่าวสาร'];
    return $labels[$kind] ?? '';
}
// Records of a linkable dataset, loaded once per request.
function linkable_records($kind) {
    static $cache = [];
    if (kind_label($kind) === '') { return []; }
    return $cache[$kind] ??= load_data($kind);
}
// What a record links to through its optional "related" list: [['kind' => …, 'record' => […]], …] in list order.
// Unknown kinds, missing targets, links to the record itself and duplicates are skipped.
function related_records($kind, $record) {
    $items = is_array($record['related'] ?? null) ? $record['related'] : [];
    $selfId = (string) ($record['id'] ?? '');
    $found = []; $seen = [];
    foreach ($items as $item) {
        $targetKind = is_array($item) && is_string($item['kind'] ?? null) ? $item['kind'] : '';
        $targetId = is_array($item) && is_string($item['id'] ?? null) ? $item['id'] : '';
        $key = $targetKind . '/' . $targetId;
        if (kind_label($targetKind) === '' || $targetId === '' || isset($seen[$key]) || ($targetKind === $kind && $targetId === $selfId)) { continue; }
        $target = find_record(linkable_records($targetKind), $targetId);
        if (!$target) { continue; }
        $seen[$key] = true;
        $found[] = ['kind' => $targetKind, 'record' => $target];
    }
    return $found;
}
// Links in both directions: what this record links to, then workshops that link to it.
// Workshops hold the links, so research and innovation pages list their workshops without extra data.
function linked_records($kind, $record) {
    $found = related_records($kind, $record);
    $seen = [];
    foreach ($found as $item) { $seen[$item['kind'] . '/' . $item['record']['id']] = true; }
    $selfId = (string) ($record['id'] ?? '');
    if ($selfId === '') { return $found; }
    foreach (linkable_records('workshops') as $workshop) {
        $workshopId = (string) ($workshop['id'] ?? '');
        if ($workshopId === '' || isset($seen['workshops/' . $workshopId]) || ($kind === 'workshops' && $workshopId === $selfId)) { continue; }
        foreach (related_records('workshops', $workshop) as $item) {
            if ($item['kind'] === $kind && $item['record']['id'] === $selfId) {
                $seen['workshops/' . $workshopId] = true;
                $found[] = ['kind' => 'workshops', 'record' => $workshop];
                break;
            }
        }
    }
    return $found;
}
function detail_url($kind, $id) {
    $routes = ['research' => 'research-detail.php', 'innovations' => 'innovation-detail.php', 'workshops' => 'workshop-detail.php', 'media' => 'media-detail.php', 'news' => 'news-detail.php'];
    return $routes[$kind] . '?id=' . rawurlencode($id);
}

// Innovation categories (config/innovation-categories.php): slug => name, and the slug of a category name ('' when none).
function innovation_categories() {
    static $categories;
    if ($categories === null) { $categories = require __DIR__ . '/../config/innovation-categories.php'; }
    return $categories;
}
function innovation_category_slug($name) {
    $slug = array_search(trim((string) $name), innovation_categories(), true);
    return $slug === false ? '' : (string) $slug;
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
function render_page_hero($key, array $override = []) {
    $hero = $override + hero_text($key);
    ?><section class="page-hero page-hero--editorial"><div class="container page-hero-content"><p class="eyebrow"><?= e($hero['eyebrow']) ?></p><h1><?= e($hero['title']) ?></h1><p><?= thai_text($hero['intro']) ?></p></div></section><?php
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

// Split an author line ("รศ. ดร.ก ข, ดร.ค ง และ จ ฉ") into people: [['title' => 'รศ. ดร.', 'name' => 'ก ข'], ...].
// The title is the run of academic abbreviations at the start (รศ. ผศ. ศ. ดร. Prof. Dr. Assoc. Asst.).
function author_people($line): array {
    $line = trim((string) $line);
    if ($line === '') { return []; }
    $parts = preg_split('/\s*,\s*|\s+และ\s+/u', $line);
    $people = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') { continue; }
        $title = '';
        if (preg_match('/^((?:(?:รศ|ผศ|ศ|ดร|Prof|Dr|Assoc|Asst)\.\s*)+)(.+)$/u', $part, $m)) { $title = trim($m[1]); $part = trim($m[2]); }
        $people[] = ['title' => $title, 'name' => $part];
    }
    return $people;
}
// Short form for cards and lists: one or two people in full, otherwise the first person and how many more.
function author_short($line): string {
    $people = author_people($line);
    $n = count($people);
    if ($n <= 2) { return trim((string) $line); }
    $first = $people[0];
    return trim($first['title'] . $first['name']) . ' และอีก ' . ($n - 1) . ' คน';
}

// Bold stroked arrow icon used wherever the site previously printed an arrow glyph.
// $dir: right | up-right | chevron-right | chevron-left
function icon_arrow(string $dir = 'right'): string
{
    $paths = [
        'right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'up-right' => '<path d="M7 17 17 7"/><path d="M8 7h9v9"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
    ];
    $d = $paths[$dir] ?? $paths['right'];
    return '<svg class="icon-arrow icon-arrow--' . $dir . '" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

// Thai line breaking. Browsers split unfamiliar Thai words (loanwords, names) mid-word, so
// tools/segment_thai.py stores word boundaries per paragraph in data/thai-breaks.json, keyed
// by a hash of the text. Here each Thai word becomes a no-wrap span with <wbr> between words,
// so lines can only break between words. Text without an entry (not yet segmented, or edited
// since) is escaped as plain text, exactly as before.
function thai_breaks(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $text = @file_get_contents(__DIR__ . '/../data/thai-breaks.json');
        $data = $text === false ? null : json_decode($text, true);
        if (is_array($data) && is_array($data['paragraphs'] ?? null)) { $map = $data['paragraphs']; }
        // Without this line a broken file (for example leftover merge markers) turns Thai line breaking off silently.
        else { error_log('thai-breaks: data/thai-breaks.json cannot be read, Thai lines will break by browser default. Run: python3 tools/segment_thai.py'); }
    }
    return $map;
}
function thai_text(string $text): string
{
    $offsets = thai_breaks()[sha1($text)] ?? null;
    if (!is_array($offsets)) { return e($text); }
    $tokens = [];
    $start = 0;
    foreach ($offsets as $end) {
        if (!is_int($end) || $end <= $start) { return e($text); }
        $tokens[] = mb_substr($text, $start, $end - $start, 'UTF-8');
        $start = $end;
    }
    $tokens[] = mb_substr($text, $start, null, 'UTF-8');
    $isThai = static fn (string $char): bool => preg_match('/^[\x{0E00}-\x{0E7F}]$/u', $char) === 1;
    $html = '';
    foreach ($tokens as $i => $token) {
        if ($token === '') { continue; }
        // Words longer than a line could hold are left wrappable so they never overflow.
        $wrap = !ctype_space($token) && preg_match('/[\x{0E00}-\x{0E7F}]/u', $token) && mb_strlen($token, 'UTF-8') <= 40;
        $html .= $wrap ? '<span class="tw">' . e($token) . '</span>' : e($token);
        $next = $tokens[$i + 1] ?? '';
        // Allow a break only between two Thai words, never before punctuation such as ) or ".
        if ($next !== '' && $isThai(mb_substr($token, -1, 1, 'UTF-8')) && $isThai(mb_substr($next, 0, 1, 'UTF-8'))) {
            $html .= '<wbr>';
        }
    }
    return $html;
}
