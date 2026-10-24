<?php
// Home "ประชาสัมพันธ์" cards and "Scoop" videos. Both come from config/home.php ('promos', 'videos'); nothing is read from
// data/*.json or MySQL yet. Every helper drops entries it cannot use, so a bad row never breaks the page.

// 11-character video ID from a youtube.com/watch?v=, youtu.be/ or youtube.com/shorts/ (or /embed/) address, '' otherwise.
function youtube_video_id(string $url): string {
    $parts = parse_url(trim($url));
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) { return ''; }
    $host = strtolower(preg_replace('/^(www|m)\./i', '', (string) ($parts['host'] ?? '')));
    $path = (string) ($parts['path'] ?? '');
    $id = '';
    if ($host === 'youtu.be') { $id = trim($path, '/'); }
    elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
        if ($path === '/watch') { parse_str((string) ($parts['query'] ?? ''), $query); $id = is_string($query['v'] ?? null) ? $query['v'] : ''; }
        elseif (preg_match('~\A/(?:shorts|embed|live)/([^/]+)~', $path, $match)) { $id = $match[1]; }
    }
    return preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id) ? $id : '';
}

// Cards for the "ประชาสัมพันธ์" slider: title, date (Y-m-d, optional), label, image (assets/images/…), url.
function home_promos(): array {
    $cards = [];
    foreach ((require __DIR__ . '/../config/home.php')['promos'] ?? [] as $row) {
        if (!is_array($row) || trim((string) ($row['title'] ?? '')) === '') { continue; }
        $url = trim((string) ($row['url'] ?? ''));
        // Only on-site paths, anchors and https addresses are linked.
        if (!preg_match('~\A(#|https://|[a-z0-9][a-z0-9_./?=&%-]*\z)~i', $url) || preg_match('~\A[a-z]+:(?!//)~i', $url)) { $url = '#'; }
        $date = trim((string) ($row['date'] ?? ''));
        $cards[] = [
            'title' => trim((string) $row['title']),
            'date' => preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) ? $date : '',
            'label' => trim((string) ($row['label'] ?? '')),
            'image' => image_path(['image' => $row['image'] ?? '']) ?: 'assets/images/content-placeholder.png',
            'url' => $url === '' ? '#' : $url,
        ];
    }
    return $cards;
}

// Videos for the "Scoop" slider: title, youtube (full address), date (optional), image (optional local cover).
// Without a local cover the YouTube thumbnail is used (needs i.ytimg.com in the CSP img-src).
function home_videos(): array {
    $videos = [];
    foreach ((require __DIR__ . '/../config/home.php')['videos'] ?? [] as $row) {
        if (!is_array($row)) { continue; }
        $id = youtube_video_id((string) ($row['youtube'] ?? ''));
        if ($id === '' || trim((string) ($row['title'] ?? '')) === '') { continue; }
        $date = trim((string) ($row['date'] ?? ''));
        $videos[] = [
            'id' => $id,
            'title' => trim((string) $row['title']),
            'date' => preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) ? $date : '',
            'cover' => image_path(['image' => $row['image'] ?? '']) ?: 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
            'watch' => 'https://www.youtube.com/watch?v=' . $id,
        ];
    }
    return $videos;
}
