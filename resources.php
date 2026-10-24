<?php
// Tools and guides: built from existing records, nothing to maintain separately.
// #tools  = every research, innovation or workshop record with a working `tool_url`.
// #guides = every record with a downloadable PDF (`download_url`).
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'resources'; $pageTitle = 'เครื่องมือออนไลน์ สื่อและคู่มือ | ' . $site['name']; $listingStyles = true;
$tools = []; $guides = [];
foreach (['research', 'innovations', 'workshops'] as $kind) {
    foreach (load_data($kind) as $record) {
        if (!is_array($record) || !isset($record['id'], $record['title'])) { continue; }
        if (tool_url($record) !== '') { $tools[] = ['kind' => $kind, 'record' => $record]; }
        if (download_url($record) !== '') { $guides[] = ['kind' => $kind, 'record' => $record]; }
    }
}
$byDate = static fn ($a, $b) => strcmp((string) ($b['record']['date'] ?? ''), (string) ($a['record']['date'] ?? ''));
usort($tools, $byDate); usort($guides, $byDate);
$sections = [
    ['id' => 'tools', 'kicker' => 'ONLINE TOOLS', 'heading' => 'เครื่องมือออนไลน์', 'items' => $tools, 'link' => 'tool_url', 'label' => 'tool_label', 'default' => 'เปิดใช้เครื่องมือ',
     'empty' => 'ยังไม่มีเครื่องมือออนไลน์ในขณะนี้'],
    ['id' => 'guides', 'kicker' => 'GUIDES & DOWNLOADS', 'heading' => 'สื่อและคู่มือ', 'items' => $guides, 'link' => 'download_url', 'label' => 'download_label', 'default' => 'ดาวน์โหลด PDF',
     'empty' => 'ยังไม่มีเอกสารหรือคู่มือให้ดาวน์โหลดในขณะนี้ ระหว่างนี้ดูแนวทางการนำไปใช้ในห้องเรียนได้จากโพสต์เวิร์กช็อป'],
];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--resources">
  <?php render_page_hero('resources'); ?>
  <?php foreach ($sections as $section): ?>
  <section id="<?= e($section['id']) ?>" class="container editorial-canvas resources-canvas" aria-labelledby="<?= e($section['id']) ?>-title">
    <div class="page-heading"><div><span class="section-kicker"><?= e($section['kicker']) ?></span><h2 id="<?= e($section['id']) ?>-title"><?= e($section['heading']) ?></h2></div><span class="item-count"><?= count($section['items']) ?> รายการ</span></div>
    <?php if ($section['items']): ?>
    <div class="show-grid">
      <?php foreach ($section['items'] as $item): $record = $item['record']; $target = $section['link'] === 'tool_url' ? tool_url($record) : download_url($record); $external = str_starts_with($target, 'https://'); ?>
      <article class="show-card">
        <div class="show-card__media"><img src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"><span class="show-card__badge"><?= e(kind_label($item['kind'])) ?></span></div>
        <div class="show-card__body">
          <h3><a class="show-card__link" href="<?= e(detail_url($item['kind'], $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
          <p><?= e($record['summary'] ?? '') ?></p>
          <a class="resource-action" href="<?= e($target) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= e(trim((string) ($record[$section['label']] ?? '')) ?: $section['default']) ?> <?= icon_arrow('up-right') ?><?= $external ? '<span class="visually-hidden"> (เปิดในแท็บใหม่)</span>' : '' ?></a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="listing-none"><?= e($section['empty']) ?></p>
    <?php if ($section['id'] === 'guides'): ?><p><a class="resource-action resource-action--inline" href="workshops.php">ไปที่อบรมและเวิร์กช็อป <?= icon_arrow() ?></a></p><?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</main><?php require __DIR__ . '/includes/footer.php'; ?>
