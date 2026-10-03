<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'research';
$pageTitle = 'Research | ' . $site['name'];
$records = load_data('research');
$featured = array_values(array_filter($records, static fn ($record) => !empty($record['featured'])));
$q = query_string('q');
$category = query_string('category');
$year = query_string('year');
$categories = array_values(array_unique(array_column($records, 'category')));
$years = array_values(array_unique(array_column($records, 'year')));
rsort($years);
$filtered = array_values(array_filter($records, static function ($record) use ($q, $category, $year) {
    $haystack = implode(' ', [
        $record['title'] ?? '', $record['summary'] ?? '', $record['category'] ?? '',
        $record['researcher'] ?? '', implode(' ', $record['tags'] ?? [])
    ]);
    return ($category === '' || $category === ($record['category'] ?? ''))
        && ($year === '' || $year === ($record['year'] ?? ''))
        && ($q === '' || stripos($haystack, $q) !== false);
}));
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--research">
  <section class="page-hero page-hero--research page-hero--editorial">
    <div class="container page-hero-content editorial-hero-grid">
      <div><p class="eyebrow">EDU2IMPACT / RESEARCH</p><h1>Research with a way forward.</h1><p>ค้นพบผลงานวิจัยที่ช่วยอธิบายปัญหา สร้างแนวทางใหม่ และเปิดโอกาสให้เกิดการนำไปใช้จริง</p></div>
      <div class="hero-index"><span>02</span><small>RESEARCH LIBRARY<br>EDU2IMPACT</small></div>
    </div>
  </section>

  <section class="container research-section">
    <div class="section-intro-row research-heading">
      <div><span class="section-kicker">RESEARCH HIGHLIGHTS</span><h2>ผลงานวิจัยยอดนิยม</h2></div>
      <div class="feed-total"><strong><?= count($featured) ?></strong><span>featured<br>works</span></div>
    </div>

    <section class="research-carousel" data-carousel tabindex="0" aria-label="ผลงานวิจัยยอดนิยม">
      <div class="research-carousel-viewport"><div class="research-carousel-track">
      <?php foreach ($featured as $record): ?>
        <article class="research-card research-card--<?= e($record['accent'] ?? 'amber') ?>">
          <a class="research-card-media" href="<?= e(detail_url('research', $record['id'])) ?>" aria-label="อ่าน <?= e($record['title']) ?>">
            <?php if (!empty($record['image'])): ?><img src="<?= e($record['image']) ?>" alt=""><?php else: ?><span class="media-placeholder"><span aria-hidden="true">✦</span><small>DEMO IMAGE</small></span><?php endif; ?>
          </a>
          <div class="research-card-body"><div class="research-meta"><span class="placeholder-badge">DEMO PLACEHOLDER</span><span><?= e($record['category']) ?></span></div><h3><a href="<?= e(detail_url('research', $record['id'])) ?>"><?= e($record['title']) ?></a></h3><p><?= e($record['summary']) ?></p><div class="research-card-footer"><span><?= e($record['published_date'] ?? $record['year']) ?></span><a class="post-link" href="<?= e(detail_url('research', $record['id'])) ?>">View <span aria-hidden="true">↗</span></a></div></div>
        </article>
      <?php endforeach; ?>
      </div></div>
      <button class="research-carousel-arrow research-carousel-arrow--prev" type="button" data-carousel-prev aria-label="ผลงานวิจัยก่อนหน้า">‹</button><button class="research-carousel-arrow research-carousel-arrow--next" type="button" data-carousel-next aria-label="ผลงานวิจัยถัดไป">›</button>
      <div class="research-carousel-status"><span data-carousel-current>01</span><span class="status-line"></span><span><?= str_pad((string) count($featured), 2, '0', STR_PAD_LEFT) ?></span></div>
    </section>

    <div class="section-intro-row research-all-heading"><div><span class="section-kicker">RESEARCH LIBRARY</span><h2>ผลงานวิจัยทั้งหมด</h2></div><div class="feed-total"><strong><?= count($records) ?></strong><span>works in<br>demo library</span></div></div>
    <form class="modern-search research-search" method="get" action="research.php" role="search">
      <div class="search-field"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg><input id="research-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search research, topics, authors..." maxlength="200"><kbd>Ctrl K</kbd></div>
      <div class="search-actions"><label class="visually-hidden" for="research-category">สาขางานวิจัย</label><select id="research-category" name="category"><option value="">All fields</option><?php foreach ($categories as $option): ?><option value="<?= e($option) ?>"<?= $category === $option ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><label class="visually-hidden" for="research-year">ปีงานวิจัย</label><select id="research-year" name="year"><option value="">All years</option><?php foreach ($years as $option): ?><option value="<?= e($option) ?>"<?= $year === $option ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><button type="submit">Search <span aria-hidden="true">↗</span></button><?php if ($q !== '' || $category !== '' || $year !== ''): ?><a class="search-reset" href="research.php">Reset</a><?php endif; ?></div>
    </form>

    <div class="feed-heading"><span>All research</span><span><?= count($filtered) ?> results</span></div>
    <div class="research-list editorial-feed" aria-live="polite">
    <?php foreach ($filtered as $record): ?>
      <article class="research-list-item editorial-post">
        <a class="post-media" href="<?= e(detail_url('research', $record['id'])) ?>" aria-label="อ่าน <?= e($record['title']) ?>">
          <?php if (!empty($record['image'])): ?><img src="<?= e($record['image']) ?>" alt=""><?php else: ?><span class="media-placeholder"><span aria-hidden="true">✦</span><small>DEMO IMAGE</small></span><?php endif; ?>
        </a>
        <div class="research-list-body post-body"><div class="research-meta post-meta"><span class="placeholder-badge">DEMO PLACEHOLDER</span><span><?= e($record['category']) ?></span><span><?= e($record['published_date'] ?? $record['year']) ?></span></div><h3><a href="<?= e(detail_url('research', $record['id'])) ?>"><?= e($record['title']) ?></a></h3><p><?= e($record['summary']) ?></p><div class="post-footer"><span>By <?= e($record['researcher'] ?? 'Edu2Impact Research') ?></span><a class="post-link" href="<?= e(detail_url('research', $record['id'])) ?>">Read research <span aria-hidden="true">↗</span></a></div></div>
      </article>
    <?php endforeach; ?>
    </div>
    <?php if (!$filtered): ?><div class="empty-state"><h2>ไม่พบผลงานวิจัย</h2><p>ลองเปลี่ยนคำค้นหรือเลือกตัวกรองอื่น</p><a class="button secondary" href="research.php">แสดงผลงานทั้งหมด</a></div><?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
