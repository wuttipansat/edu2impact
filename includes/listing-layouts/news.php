<?php
// News: a plain list, newest first. With no records the page says so and points to workshops.
// Uses variables from editorial-listing.php: $kind, $listing, $records, $fallbackCategory.
usort($records, static fn (array $a, array $b): int => strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? '')));
?>
<?php if ($records): ?>
<div class="page-heading"><div><h2>ข่าวทั้งหมด</h2></div><span class="item-count"><strong><?= count($records) ?></strong> <?= e($listing['noun']) ?></span></div>
<div class="lib-list" aria-live="polite">
<?php foreach ($records as $record): ?>
  <article class="lib-row">
    <p class="lib-meta"><span><?= e($record['category'] ?? $fallbackCategory) ?></span><?php if (is_dated($record)): ?><span><?= e(thai_date($record['date'])) ?></span><?php endif; ?><?= visit_eye(visit_count($kind . '/' . $record['id'])) ?></p>
    <h3><a class="lib-stretch" href="<?= e(detail_url($kind, $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
    <p class="lib-row__summary"><?= e($record['summary'] ?? '') ?></p>
  </article>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-guide">
  <h2>ยังไม่มีข่าวในตอนนี้</h2>
  <p>ระหว่างนี้ ดูหัวข้อเวิร์กช็อปที่ทีมเคยจัดได้</p>
  <p class="actions"><a class="button" href="workshops.php">ไปที่อบรมและเวิร์กช็อป</a></p>
</div>
<?php endif; ?>
