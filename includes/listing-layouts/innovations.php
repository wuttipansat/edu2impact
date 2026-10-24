<?php
// Innovation: "Showcase gallery" - search first, then a card grid. One link per card (title, stretched).
?>
<nav class="category-filter" aria-label="หมวดนวัตกรรม"><ul>
  <li><a href="innovations.php"<?= $categorySlug === '' ? ' aria-current="page"' : '' ?>>นวัตกรรมทั้งหมด</a></li>
<?php foreach (innovation_categories() as $slug => $name): ?>
  <li><a href="innovations.php?category=<?= e($slug) ?>"<?= $categorySlug === $slug ? ' aria-current="page"' : '' ?>><?= e($name) ?></a></li>
<?php endforeach; ?>
</ul></nav>
<div class="show-grid" aria-live="polite">
<?php foreach ($records as $record): ?>
  <article class="show-card">
    <div class="show-card__media"><img src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"><span class="show-card__badge"><?= e($record['category'] ?? $fallbackCategory) ?></span></div>
    <div class="show-card__body">
      <h3><a class="show-card__link" href="<?= e(detail_url($kind, $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
      <p><?= e($record['summary'] ?? '') ?></p>
      <?php $views = visit_eye(visit_count($kind . '/' . $record['id'])); if ($views !== ''): ?><p class="show-card__views"><?= $views ?></p><?php endif; ?>
      <span class="show-card__cta" aria-hidden="true"><?= e($listing['cta']) ?> <?= icon_arrow() ?></span>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if (!$records && $categorySlug !== ''): ?>
<div class="empty-guide"><h2>หมวด<?= e($categoryName) ?>ยังไม่มีนวัตกรรม</h2><p>เรากำลังรวบรวมผลงานในหมวดนี้ ระหว่างนี้ดูนวัตกรรมหมวดอื่น หรือติดต่อทีมงานหากต้องการร่วมพัฒนา</p><p class="actions"><a class="button" href="innovations.php">ดูนวัตกรรมทั้งหมด</a><a class="text-link" href="contact.php">ติดต่อเรา</a></p></div>
<?php elseif (!$records): ?><p class="listing-none">ยังไม่มีรายการในขณะนี้</p><?php endif; ?>
