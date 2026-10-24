<?php
// Share links for a detail page: Facebook and X, plain links (no SDK, no tracking scripts).
// With "site_url" in data/site.json the links are built here from that address. Without it the buttons stay hidden
// until main.js fills in the address of the page being viewed (the server never guesses a domain from the request).
// Usage: render_share($path, $title) with the page's path, e.g. "research-detail.php?id=…".
function render_share(string $path, string $title): void {
    $url = absolute_url($path);
    $client = $url === ''; // no site_url: main.js completes the links in the browser
    $links = [
        'Facebook' => ['key' => 'facebook', 'href' => 'https://www.facebook.com/sharer/sharer.php' . ($client ? '' : '?u=' . rawurlencode($url)),
            'icon' => 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z'],
        'X' => ['key' => 'x', 'href' => 'https://twitter.com/intent/tweet' . ($client ? '' : '?url=' . rawurlencode($url) . '&text=' . rawurlencode($title)),
            'icon' => 'M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z'],
    ];
    ?><section class="share" aria-labelledby="share-title"<?= $client ? ' data-share-client hidden' : '' ?>><h2 id="share-title" class="share__title">แชร์เรื่องนี้</h2><ul class="share__list"><?php foreach ($links as $name => $link): ?><li><a class="share__link" href="<?= e($link['href']) ?>"<?= $client ? ' data-share="' . e($link['key']) . '" data-title="' . e($title) . '"' : '' ?> target="_blank" rel="noopener noreferrer" aria-label="แชร์ไปที่ <?= e($name) ?>"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="<?= e($link['icon']) ?>"/></svg><span><?= e($name) ?></span></a></li><?php endforeach; ?></ul></section><?php
}
