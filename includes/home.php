<?php
// Home page helpers (index.php). Everything is read from data/*.json through load_data(), so hidden
// ("public": false) records never appear. Nothing here invents dates, counts, rankings or impact figures.

function home_settings(): array {
    static $settings;
    return $settings ??= require __DIR__ . '/../config/home.php';
}

// Every research, innovation and workshop record as ['kind' => …, 'record' => […], 'order' => n],
// where `order` is the record's position in its JSON file (used to keep source order for undated posts).
function home_pool(): array {
    static $pool;
    if ($pool !== null) { return $pool; }
    $pool = [];
    foreach (['research', 'innovations', 'workshops'] as $kind) {
        foreach (linkable_records($kind) as $i => $record) {
            if (!is_array($record) || !isset($record['id'], $record['title'])) { continue; }
            $pool[] = ['kind' => $kind, 'record' => $record, 'order' => $i];
        }
    }
    return $pool;
}

// Dated records newest first; undated records after them in their file order (dates are never guessed).
// With $featuredFirst, records marked "featured": true come first.
function home_sort(array $items, bool $featuredFirst = false): array {
    $kindOrder = ['research' => 0, 'innovations' => 1, 'workshops' => 2];
    usort($items, static function (array $a, array $b) use ($featuredFirst, $kindOrder): int {
        if ($featuredFirst && ($fa = !empty($a['record']['featured'])) !== ($fb = !empty($b['record']['featured']))) { return $fa ? -1 : 1; }
        $da = is_dated($a['record']) ? $a['record']['date'] : '';
        $db = is_dated($b['record']) ? $b['record']['date'] : '';
        if ($da !== $db) { return $da === '' ? 1 : ($db === '' ? -1 : strcmp($db, $da)); }
        return [$kindOrder[$a['kind']], $a['order']] <=> [$kindOrder[$b['kind']], $b['order']];
    });
    return $items;
}

// Resolve [['kind' => …, 'id' => …], …] against the pool; unknown, hidden or repeated references are skipped.
function home_resolve(array $refs): array {
    $found = []; $seen = [];
    foreach ($refs as $ref) {
        $kind = is_array($ref) ? (string) ($ref['kind'] ?? '') : '';
        $id = is_array($ref) ? (string) ($ref['id'] ?? '') : '';
        if (isset($seen[$kind . '/' . $id])) { continue; }
        foreach (home_pool() as $item) {
            if ($item['kind'] === $kind && $item['record']['id'] === $id) { $found[] = $item; $seen[$kind . '/' . $id] = true; break; }
        }
    }
    return $found;
}

// Items not already used, so the same post is not repeated inside one section.
function home_without(array $items, array $used): array {
    $keys = array_flip(array_map(static fn ($i) => $i['kind'] . '/' . $i['record']['id'], $used));
    return array_values(array_filter($items, static fn ($i) => !isset($keys[$i['kind'] . '/' . $i['record']['id']])));
}

function home_url(array $item): string { return detail_url($item['kind'], $item['record']['id']); }

// "นวัตกรรม" or "นวัตกรรม · Learning" (category is optional; workshops have none).
function home_label(array $item): string {
    $category = trim((string) ($item['record']['category'] ?? ''));
    return kind_label($item['kind']) . ($category !== '' ? ' · ' . $category : '');
}

// Thai date for a dated record, '' otherwise.
function home_date(array $item): string { return is_dated($item['record']) ? thai_date($item['record']['date']) : ''; }

// <img> for a record (or any local image path), with intrinsic width/height to prevent layout shift.
// Images sit next to a linked title, so they are decorative (alt="") unless $alt is given.
function home_img($source, string $class = '', bool $eager = false, string $alt = ''): string {
    $path = is_array($source) ? image_path($source) : (string) $source;
    if ($path === '') { $path = 'assets/images/content-placeholder.png'; }
    static $sizes = [];
    $size = $sizes[$path] ??= (@getimagesize(__DIR__ . '/../' . $path) ?: [0, 0]);
    $dims = $size[0] > 0 ? ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"' : '';
    $loading = $eager ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"';
    return '<img' . ($class !== '' ? ' class="' . e($class) . '"' : '') . ' src="' . e($path) . '" alt="' . e($alt) . '"' . $dims . $loading . '>';
}

// Short summary for cards: whole text when short, otherwise cut at a word/space boundary.
function home_excerpt(array $item, int $limit = 140): string {
    $text = trim(preg_replace('/\s+/u', ' ', (string) ($item['record']['summary'] ?? '')));
    if (mb_strlen($text) <= $limit) { return $text; }
    $cut = mb_substr($text, 0, $limit);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== false && $space > $limit * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
}

// Hero: configured picks, or featured records first then the newest; always three different posts when available.
function home_hero(): array {
    $picks = home_resolve(home_settings()['hero'] ?? []);
    $rest = home_without(home_sort(home_pool(), true), $picks);
    return array_slice(array_merge($picks, $rest), 0, 3);
}

// "เนื้อหาแนะนำ": configured editors' picks, or featured records and records with an online tool.
function home_recommended(int $limit = 5): array {
    $picks = home_resolve(home_settings()['recommended'] ?? []);
    if (!$picks) {
        $picks = array_values(array_filter(home_sort(home_pool(), true), static fn ($i) => !empty($i['record']['featured']) || tool_url($i['record']) !== ''));
    }
    return array_slice($picks, 0, $limit);
}

// Topic chips with a working destination and, for search topics, the number of matching records.
function home_topics(): array {
    $settings = home_settings();
    $topics = [];
    foreach ($settings['topics'] ?? [] as $topic) {
        $count = null; $url = (string) ($topic['url'] ?? '');
        if (isset($topic['query']) && $topic['query'] !== '') {
            $result = search_run($topic['query']);
            $count = count($result['groups']['research'] ?? []) + count($result['groups']['innovations'] ?? []) + count($result['groups']['workshops'] ?? []);
            $url = $count > 0 ? 'search.php?q=' . rawurlencode($result['q']) : (string) ($settings['topic_fallback'] ?? 'research.php');
        } elseif (isset($topic['kind'])) {
            $count = count(linkable_records($topic['kind']));
        }
        if ($url === '') { continue; }
        $topics[] = ['label' => $topic['label'], 'thai' => $topic['thai'] ?? '', 'url' => $url, 'count' => $count, 'icon' => $topic['icon'] ?? 'book'];
    }
    return $topics;
}

// Events from data/events.json dated today or later (Bangkok time), soonest first, at most $limit.
function home_upcoming_events(int $limit = 3): array {
    $today = listing_today();
    $events = array_values(array_filter(load_data('events'), static fn ($e) => is_array($e) && is_dated($e) && $e['date'] >= $today && trim((string) ($e['title'] ?? '')) !== ''));
    usort($events, static fn ($a, $b) => strcmp($a['date'], $b['date']));
    return array_slice($events, 0, $limit);
}

// Workshop status: upcoming / held on a date / no single date (a workshop that can be requested).
function home_workshop_status(array $record): array {
    if (!is_dated($record)) { return ['class' => 'request', 'text' => 'เปิดให้ขอจัด']; }
    return $record['date'] >= listing_today()
        ? ['class' => 'upcoming', 'text' => 'กำลังจะจัด · ' . thai_date($record['date'])]
        : ['class' => 'past', 'text' => 'จัดแล้ว · ' . thai_date($record['date'])];
}

// "ผลงานที่นำไปใช้": research or innovation posts that were turned into a workshop (documented through
// the workshops' `related` links). These are applications, not verified impact figures.
function home_applications(int $limit = 2): array {
    $pairs = [];
    foreach (linkable_records('workshops') as $workshop) {
        $sources = array_values(array_filter(related_records('workshops', $workshop), static fn ($r) => $r['kind'] !== 'workshops'));
        if (!$sources) { continue; }
        $pairs[] = ['workshop' => ['kind' => 'workshops', 'record' => $workshop], 'sources' => $sources];
        if (count($pairs) >= $limit) { break; }
    }
    return $pairs;
}

function home_topic_icon(string $name): string {
    $paths = [
        'atom' => '<circle cx="12" cy="12" r="1.6"/><ellipse cx="12" cy="12" rx="9" ry="3.6"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(120 12 12)"/>',
        'chip' => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9.5 9.5h5v5h-5z"/><path d="M9 3v3M15 3v3M9 18v3M15 18v3M3 9h3M3 15h3M18 9h3M18 15h3"/>',
        'teacher' => '<path d="M3 4h18v11H3z"/><path d="M8 19h8M12 15v4"/><path d="m7 11 3-3 2 2 4-4"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7M8 11h5"/>',
        'bulb' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.6.5 1 1.3 1 2.2h5.2c0-.9.4-1.7 1-2.2A6 6 0 0 0 12 3z"/>',
        'flag' => '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths['book']) . '</svg>';
}
