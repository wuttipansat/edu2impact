<?php
// Online tools: every research or innovation record that has a tool people can open (tool_url).
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'tools';
$pageTitle = 'เครื่องมือออนไลน์ | ' . $site['name'];
$listingStyles = true;
$tools = tool_records();
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--tools">
  <?php render_page_hero('tools'); ?>
  <section class="container editorial-canvas editorial-canvas--tools">
<?php if ($tools): ?>
    <div class="page-heading"><div><h2>เครื่องมือทั้งหมด</h2></div><span class="item-count"><strong><?= count($tools) ?></strong> รายการ</span></div>
    <div class="tool-list">
<?php foreach ($tools as $tool): $record = $tool['record']; $kind = $tool['kind']; $url = tool_url($record); $external = str_starts_with($url, 'https://'); ?>
      <article class="tool-row">
        <a class="tool-row__media" href="<?= e(detail_url($kind, $record['id'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></a>
        <div class="tool-row__body">
          <p class="lib-meta"><span><?= e(kind_label($kind)) ?></span><?php if (is_dated($record)): ?><span><?= e(thai_date($record['date'])) ?></span><?php endif; ?></p>
          <h3><?= e($record['title']) ?></h3>
          <p class="tool-row__summary"><?= e($record['summary'] ?? '') ?></p>
          <p class="actions tool-row__actions"><a class="button" href="<?= e($url) ?>"<?= $external ? ' rel="noopener"' : '' ?>><?= e(trim((string) ($record['tool_label'] ?? '')) ?: 'เปิดเครื่องมือ') ?> <?= icon_arrow('up-right') ?></a><a class="text-link" href="<?= e(detail_url($kind, $record['id'])) ?>">ดูรายละเอียด</a></p>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-guide">
      <h2>ยังไม่มีเครื่องมือออนไลน์ในตอนนี้</h2>
      <p>ดูนวัตกรรมการศึกษาที่ใช้ในห้องเรียนระหว่างรอเครื่องมือใหม่</p>
      <p class="actions"><a class="button" href="innovations.php">ไปที่นวัตกรรมการศึกษา</a></p>
    </div>
<?php endif; ?>
    <p class="tools-all"><a class="text-link" href="innovations.php">นวัตกรรมทั้งหมด</a></p>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
