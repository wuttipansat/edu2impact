<?php
// All content files are public information. Never store credentials in data/.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function load_data($name) {
    $allowed = ['site', 'research', 'innovations', 'news'];
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
    $routes = ['research' => 'research-detail.php', 'innovations' => 'innovation-detail.php', 'news' => 'news-detail.php'];
    return $routes[$kind] . '?id=' . rawurlencode($id);
}
function render_card($record, $kind) {
    $image = image_path($record);
    ?>
    <article class="project-card">
      <a class="card-image" href="<?= e(detail_url($kind, $record['id'])) ?>" aria-label="<?= e($record['title']) ?>">
        <?php if ($image): ?><img src="<?= e($image) ?>" alt="" loading="lazy" width="640" height="360"><?php else: ?>
        <span class="abstract-art" aria-hidden="true"><span></span><span></span><span></span></span>
        <?php endif; ?>
      </a>
      <div class="card-body">
        <div class="eyebrow"><?= e($record['category']) ?></div>
        <h3><a href="<?= e(detail_url($kind, $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
        <p><?= e($record['summary']) ?></p>
        <a class="text-link" href="<?= e(detail_url($kind, $record['id'])) ?>">อ่านรายละเอียด <span aria-hidden="true">↗</span></a>
      </div>
    </article>
    <?php
}
