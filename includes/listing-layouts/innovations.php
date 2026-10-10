<?php
// Innovation: "Showcase gallery" - search first, then a card grid. One link per card (title, stretched).
?>
<div class="show-grid" aria-live="polite">
<?php foreach ($records as $record): ?>
  <article class="show-card">
    <div class="show-card__media"><img src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"><span class="show-card__badge"><?= e($record['category'] ?? $fallbackCategory) ?></span></div>
    <div class="show-card__body">
      <h3><a class="show-card__link" href="<?= e(detail_url($kind, $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
      <p><?= e($record['summary'] ?? '') ?></p>
      <span class="show-card__cta" aria-hidden="true"><?= e($listing['cta']) ?> →</span>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if (!$records): ?><p class="listing-none">ยังไม่มีรายการในขณะนี้</p><?php endif; ?>
