<?php
// Site-wide search: index, scoring, highlighting and excerpts. Used by search.php.
// Content comes from data/*.json, config/search-pages.php, config/partner-groups.php and config/heroes.php.

const SEARCH_MAX_LENGTH = 100;
const SEARCH_GROUP_LIMIT = 20;

// Result groups in display order: type => [label, listing page or '', listing page name].
function search_groups() {
    return [
        'research' => ['label' => 'งานวิจัย', 'page' => 'research.php', 'page_name' => 'Research'],
        'innovations' => ['label' => 'นวัตกรรม', 'page' => 'innovations.php', 'page_name' => 'Innovation'],
        'workshops' => ['label' => 'เวิร์กช็อป', 'page' => 'workshops.php', 'page_name' => 'Workshop'],
        'events' => ['label' => 'กิจกรรม', 'page' => '', 'page_name' => ''],
        'pages' => ['label' => 'หน้าเว็บไซต์', 'page' => '', 'page_name' => ''],
    ];
}

// Trim, collapse whitespace, drop control characters and cut to SEARCH_MAX_LENGTH characters.
function search_normalise($q) {
    $q = is_string($q) ? $q : '';
    if (!mb_check_encoding($q, 'UTF-8')) { return ''; }
    $q = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $q) ?? '';
    $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');
    return trim(mb_substr($q, 0, SEARCH_MAX_LENGTH));
}

function search_terms($q) {
    return $q === '' ? [] : array_values(array_unique(explode(' ', $q)));
}

// Plain text from a string or a (nested) list of strings, e.g. a record body made of paragraphs.
function search_text($value) {
    if (is_array($value)) { return search_join($value); }
    return is_scalar($value) ? trim((string) $value) : '';
}
function search_join(array $parts) {
    return implode(' ', array_filter(array_map('search_text', $parts), static fn (string $part): bool => $part !== ''));
}

function search_index() {
    static $index;
    if ($index !== null) { return $index; }
    $index = [];
    foreach (['research', 'innovations', 'workshops'] as $kind) {
        foreach (load_data($kind) as $record) {
            if (!isset($record['id'], $record['title'])) { continue; }
            $index[] = [
                'type' => $kind,
                'title' => (string) $record['title'],
                'summary' => search_text($record['summary'] ?? ''),
                // Optional lists (e.g. innovations with a web tool) count as keywords.
                'keywords' => search_join([$record['audience'] ?? [], $record['subjects'] ?? [], $record['features'] ?? []]),
                'text' => search_join([$record['title'], $record['summary'] ?? '', $record['category'] ?? '', $record['author'] ?? '', body_text($record['body'] ?? []), $record['audience'] ?? [], $record['subjects'] ?? [], $record['features'] ?? []]),
                'body' => search_text(body_text($record['body'] ?? [])),
                'url' => detail_url($kind, $record['id']),
                'date' => (string) ($record['date'] ?? ''),
                'category' => (string) ($record['category'] ?? ''),
            ];
        }
    }
    foreach (load_data('events') as $event) {
        if (!isset($event['title'])) { continue; }
        $index[] = [
            'type' => 'events',
            'title' => (string) $event['title'],
            'summary' => (string) ($event['meta'] ?? ''),
            'keywords' => '',
            'text' => search_join([$event['title'], $event['meta'] ?? '', $event['type'] ?? '']),
            'body' => '',
            'url' => 'index.php#events',
            'date' => (string) ($event['date'] ?? ''),
            'category' => (string) ($event['type'] ?? ''),
        ];
    }
    foreach (require __DIR__ . '/../config/search-pages.php' as $page) {
        $hero = $page['hero'] !== '' ? hero_text($page['hero']) : [];
        $index[] = [
            'type' => 'pages',
            'title' => $page['title'],
            'summary' => $page['summary'],
            'keywords' => $page['keywords'],
            'text' => search_join([$page['title'], $page['summary'], $page['keywords'], $hero['eyebrow'] ?? '', $hero['title'] ?? '', $hero['intro'] ?? '']),
            'body' => '',
            'url' => $page['url'],
            'date' => '',
            'category' => '',
        ];
    }
    foreach (require __DIR__ . '/../config/partner-groups.php' as $i => [$english, $thai, $description]) {
        $index[] = [
            'type' => 'pages',
            'title' => 'Partners: ' . $english . ' · ' . $thai,
            'summary' => $description,
            'keywords' => 'partners พันธมิตร ' . $english . ' ' . $thai,
            'text' => search_join([$english, $thai, $description]),
            'body' => '',
            'url' => 'partners.php#partner-' . ($i + 1),
            'date' => '',
            'category' => 'Partners',
        ];
    }
    return $index;
}

function search_contains($haystack, $term) {
    return $term !== '' && mb_stripos((string) $haystack, $term, 0, 'UTF-8') !== false;
}

// Returns ['q' => normalised query, 'total' => n, 'groups' => [type => list of entries]], best first.
function search_run($q) {
    $q = search_normalise($q);
    $terms = search_terms($q);
    $groups = array_fill_keys(array_keys(search_groups()), []);
    if (!$terms) { return ['q' => $q, 'total' => 0, 'groups' => $groups]; }
    $total = 0;
    foreach (search_index() as $entry) {
        $all = search_join([$entry['title'], $entry['category'], $entry['keywords'], $entry['summary'], $entry['text']]);
        $score = 0;
        foreach ($terms as $term) {
            if (!search_contains($all, $term)) { continue 2; } // every term must match (AND)
            if (search_contains($entry['title'], $term)) { $score += 10; }
            if (search_contains($entry['category'], $term) || search_contains($entry['keywords'], $term)) { $score += 5; }
            if (search_contains($entry['summary'], $term)) { $score += 3; }
            if (search_contains($entry['text'], $term)) { $score += 1; }
        }
        if (search_contains($entry['title'], $q)) { $score += 10; }
        $entry['score'] = $score;
        $groups[$entry['type']][] = $entry;
        $total++;
    }
    foreach ($groups as &$entries) {
        usort($entries, static fn (array $a, array $b): int => [$b['score'], $b['date'], $a['title']] <=> [$a['score'], $a['date'], $b['title']]);
    }
    unset($entries);
    return ['q' => $q, 'total' => $total, 'groups' => $groups];
}

// Escaped text with every match of every term wrapped in <mark>. Safe for direct output.
function search_highlight($text, $q) {
    $text = (string) $text;
    $ranges = [];
    foreach (search_terms(search_normalise($q)) as $term) {
        $length = mb_strlen($term, 'UTF-8');
        $offset = 0;
        while (($pos = mb_stripos($text, $term, $offset, 'UTF-8')) !== false) {
            $ranges[] = [$pos, $pos + $length];
            $offset = $pos + $length;
        }
    }
    if (!$ranges) { return e($text); }
    sort($ranges);
    $merged = [];
    foreach ($ranges as [$start, $end]) {
        $last = count($merged) - 1;
        if ($last >= 0 && $start <= $merged[$last][1]) { $merged[$last][1] = max($merged[$last][1], $end); }
        else { $merged[] = [$start, $end]; }
    }
    $html = '';
    $cursor = 0;
    foreach ($merged as [$start, $end]) {
        $html .= e(mb_substr($text, $cursor, $start - $cursor, 'UTF-8')) . '<mark>' . e(mb_substr($text, $start, $end - $start, 'UTF-8')) . '</mark>';
        $cursor = $end;
    }
    return $html . e(mb_substr($text, $cursor, null, 'UTF-8'));
}

// About 140 characters around the first match in the summary or body; falls back to the summary.
function search_excerpt(array $entry, $q, $length = 140) {
    $terms = search_terms(search_normalise($q));
    foreach ([$entry['summary'], $entry['body']] as $source) {
        $source = trim(preg_replace('/\s+/u', ' ', (string) $source) ?? '');
        if ($source === '') { continue; }
        $first = null;
        foreach ($terms as $term) {
            $pos = mb_stripos($source, $term, 0, 'UTF-8');
            if ($pos !== false && ($first === null || $pos < $first)) { $first = $pos; }
        }
        if ($first !== null) { return search_cut($source, max(0, $first - 40), $length); }
    }
    return search_cut(trim((string) $entry['summary']), 0, $length);
}

function search_cut($text, $start, $length) {
    $size = mb_strlen($text, 'UTF-8');
    if ($size <= $length) { return $text; }
    $start = min($start, max(0, $size - $length));
    // Do not start on a Thai vowel or tone mark that belongs to the previous letter.
    while ($start > 0 && preg_match('/^\p{M}/u', mb_substr($text, $start, 1, 'UTF-8'))) { $start--; }
    $end = $start + $length;
    while ($end < $size && preg_match('/^\p{M}/u', mb_substr($text, $end, 1, 'UTF-8'))) { $end++; }
    return ($start > 0 ? '…' : '') . trim(mb_substr($text, $start, $end - $start, 'UTF-8')) . ($end < $size ? '…' : '');
}

// Up to $limit distinct categories from the three datasets, for suggestion chips.
function search_suggestions($limit = 6) {
    $seen = [];
    foreach (['research', 'innovations', 'workshops'] as $kind) {
        foreach (load_data($kind) as $record) {
            $category = trim((string) ($record['category'] ?? ''));
            if ($category !== '' && !isset($seen[mb_strtolower($category, 'UTF-8')])) { $seen[mb_strtolower($category, 'UTF-8')] = $category; }
        }
    }
    return array_slice(array_values($seen), 0, $limit);
}
