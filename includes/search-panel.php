<?php
// Search panel shared by the listing pages. Plain GET, works without JavaScript.
// Expects: $kind, $listing, $q, $category, $sort, $categories (list), $categoryCounts (map), $matchCount, $filtered.
$here = static fn (array $over = []): string => listing_url($kind, array_merge(['q' => $q, 'category' => $category, 'sort' => $sort], $over));
$inputId = $kind . '-search';
?>
<section class="search-panel" aria-label="<?= e($listing['search_label']) ?>">
  <form class="search-panel__form" method="get" action="<?= e($kind) ?>.php" role="search">
    <label class="visually-hidden" for="<?= e($inputId) ?>"><?= e($listing['search_label']) ?></label>
    <div class="search-panel__field">
      <svg class="search-panel__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input id="<?= e($inputId) ?>" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($listing['placeholder']) ?>" maxlength="200" autocomplete="off">
      <?php if ($q !== ''): ?><a class="search-panel__clear" href="<?= e($here(['q' => ''])) ?>" aria-label="ล้างคำค้น"><span aria-hidden="true">×</span></a><?php endif; ?>
      <kbd class="search-panel__kbd" aria-hidden="true">Ctrl K</kbd>
    </div>
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
    <?php if ($sort !== 'new'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <button class="search-panel__submit" type="submit"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><span>ค้นหา</span></button>
  </form>
  <nav class="search-panel__chips" aria-label="หมวดหมู่">
    <ul>
      <li><a class="chip" href="<?= e($here(['category' => ''])) ?>"<?= $category === '' ? ' aria-current="true"' : '' ?>>ทั้งหมด <span class="chip__n">· <?= (int) $matchCount ?></span></a></li>
      <?php foreach ($categories as $option): ?>
      <li><a class="chip" href="<?= e($here(['category' => $option])) ?>"<?= $category === $option ? ' aria-current="true"' : '' ?>><?= e($option) ?> <span class="chip__n">· <?= (int) ($categoryCounts[$option] ?? 0) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <div class="search-panel__bar">
    <p class="search-panel__status" role="status">พบ <strong><?= count($filtered) ?></strong> รายการ
      <?php if ($q !== ''): ?><a class="filter-chip" href="<?= e($here(['q' => ''])) ?>" aria-label="ลบตัวกรองคำค้น <?= e($q) ?>">“<?= e($q) ?>” <span aria-hidden="true">×</span></a><?php endif; ?>
      <?php if ($category !== ''): ?><a class="filter-chip" href="<?= e($here(['category' => ''])) ?>" aria-label="ลบตัวกรองหมวดหมู่ <?= e($category) ?>"><?= e($category) ?> <span aria-hidden="true">×</span></a><?php endif; ?>
    </p>
    <form class="sort-form" method="get" action="<?= e($kind) ?>.php">
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
      <label for="<?= e($kind) ?>-sort">เรียงตาม</label>
      <span class="sort-select"><select id="<?= e($kind) ?>-sort" name="sort" data-auto-submit><option value="new"<?= $sort === 'new' ? ' selected' : '' ?>>ใหม่ล่าสุด</option><option value="old"<?= $sort === 'old' ? ' selected' : '' ?>>เก่าสุด</option></select></span>
      <noscript><button class="sort-apply" type="submit">ใช้</button></noscript>
    </form>
  </div>
</section>
