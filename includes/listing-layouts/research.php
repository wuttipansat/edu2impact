<?php
// Research: "Evidence library" - featured study, two compact picks, then a text-first list (no thumbnails).
// Uses variables from editorial-listing.php: $kind, $listing, $records, $records, $fallbackCategory, $fallbackAuthor.
$lead = $records[0] ?? null;
$picks = array_slice($records, 1, 2);
?>
<?php if ($lead): ?>
<div class="page-heading"><div><span class="section-kicker"><?= e($listing['latest_kicker']) ?></span><h2><?= e($listing['latest_heading']) ?></h2></div></div>
<div class="lib-top">
  <article class="lib-feature">
    <a class="lib-feature__media" href="<?= e(detail_url($kind, $lead['id'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(image_path($lead) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></a>
    <div class="lib-feature__body">
      <p class="lib-meta"><span><?= e($lead['category'] ?? $fallbackCategory) ?></span><span><?= e($lead['date'] ?? '') ?></span></p>
      <h3><a class="lib-stretch" href="<?= e(detail_url($kind, $lead['id'])) ?>"><?= e($lead['title']) ?></a></h3>
      <p class="lib-feature__summary"><?= e($lead['summary'] ?? '') ?></p>
      <p class="lib-author"><?= e($lead['author'] ?? $fallbackAuthor) ?></p>
      <a class="lib-cta" href="<?= e(detail_url($kind, $lead['id'])) ?>"><?= e($listing['cta']) ?> <?= icon_arrow() ?></a>
    </div>
  </article>
  <?php if ($picks): ?><div class="lib-picks"><?php foreach ($picks as $pick): ?>
    <article class="lib-pick">
      <div class="lib-pick__media" aria-hidden="true"><img src="<?= e(image_path($pick) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></div>
      <div class="lib-pick__body">
      <p class="lib-meta"><span><?= e($pick['category'] ?? $fallbackCategory) ?></span><span><?= e($pick['date'] ?? '') ?></span></p>
      <h3><a class="lib-stretch" href="<?= e(detail_url($kind, $pick['id'])) ?>"><?= e($pick['title']) ?></a></h3>
      <p class="lib-author"><?= e($pick['author'] ?? $fallbackAuthor) ?></p>
      </div>
    </article>
  <?php endforeach; ?></div><?php endif; ?>
</div>
<?php endif; ?>
<div class="page-heading page-heading--all"><div><span class="section-kicker"><?= e($listing['library_kicker']) ?></span><h2><?= e($listing['library_heading']) ?></h2></div><span class="item-count"><?= count($records) ?> <?= e($listing['noun']) ?></span></div>
<div class="lib-list" aria-live="polite">
<?php foreach ($records as $record): ?>
  <article class="lib-row">
    <p class="lib-meta"><span><?= e($record['category'] ?? $fallbackCategory) ?></span><span><?= e(substr((string) ($record['date'] ?? ''), 0, 4)) ?></span></p>
    <h3><a class="lib-stretch" href="<?= e(detail_url($kind, $record['id'])) ?>"><?= e($record['title']) ?></a></h3>
    <p class="lib-row__summary"><?= e($record['summary'] ?? '') ?></p>
    <p class="lib-author"><?= e($record['author'] ?? $fallbackAuthor) ?></p>
  </article>
<?php endforeach; ?>
</div>
<?php if (!$records): ?><p class="listing-none">ยังไม่มีรายการในขณะนี้</p><?php endif; ?>
