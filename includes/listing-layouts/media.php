<?php
// Media and guides: a plain list, newest first. With no records the page points to where content exists today.
// Uses variables from editorial-listing.php: $kind, $listing, $records, $fallbackCategory.
?>
<?php if ($records): ?>
<div class="page-heading"><div><h2>สื่อและคู่มือทั้งหมด</h2></div><span class="item-count"><strong><?= count($records) ?></strong> <?= e($listing['noun']) ?></span></div>
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
  <h2>ยังไม่มีสื่อและคู่มือในตอนนี้</h2>
  <p>ระหว่างนี้ คุณอ่านงานวิจัยและบทความ หรือดูนวัตกรรมการศึกษาได้เลย</p>
  <p class="actions"><a class="button" href="research.php">ไปที่งานวิจัยและบทความ</a><a class="button secondary" href="innovations.php">ดูนวัตกรรมการศึกษา</a></p>
</div>
<?php endif; ?>
