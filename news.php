<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'news';
$pageTitle = 'News | ' . $site['name'];
$records = load_data('news');
$q = query_string('q');
$category = query_string('category');
$categories = array_values(array_unique(array_column($records, 'category')));
usort($records, static function ($a, $b) {
    return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
});
$filtered = array_values(array_filter($records, static function ($record) use ($q, $category) {
    $haystack = implode(' ', [
        $record['title'] ?? '', $record['summary'] ?? '', $record['category'] ?? '',
        $record['author'] ?? '', $record['date_label'] ?? ''
    ]);
    return ($category === '' || $category === ($record['category'] ?? ''))
        && ($q === '' || stripos($haystack, $q) !== false);
}));
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--news">
  <section class="page-hero page-hero--news page-hero--editorial">
    <div class="container page-hero-content editorial-hero-grid">
      <div><p class="eyebrow">EDU2IMPACT / NEWS</p><h1>Ideas in motion.</h1><p>ข่าวสาร กิจกรรม และองค์ความรู้ที่ช่วยให้เห็นว่างานวิจัยกำลังถูกพูดคุย ทดลอง และนำไปใช้ในบริบทจริงอย่างไร</p></div>
      <div class="hero-index"><span>01</span><small>NEWSROOM<br>EDU2IMPACT</small></div>
    </div>
  </section>

  <section class="container news-section editorial-section">
    <div class="section-intro-row">
      <div>
        <span class="section-kicker">LATEST UPDATES</span>
        <h2>ข่าวสารและองค์ความรู้</h2>
      </div>
      <div class="feed-total"><strong><?= count($records) ?></strong><span>updates<br>in demo feed</span></div>
    </div>

    <form class="modern-search" method="get" action="news.php" role="search">
      <div class="search-field"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg><input id="news-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search news, ideas, events..." maxlength="200"><kbd>Ctrl K</kbd></div>
      <div class="search-actions"><label class="visually-hidden" for="news-category">หมวดหมู่ข่าว</label><select id="news-category" name="category"><option value="">All categories</option><?php foreach ($categories as $option): ?><option value="<?= e($option) ?>"<?= $category === $option ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><button type="submit">Search <span aria-hidden="true">↗</span></button><?php if ($q !== '' || $category !== ''): ?><a class="search-reset" href="news.php">Reset</a><?php endif; ?></div>
    </form>

    <div class="feed-heading"><span>Latest updates</span><span><?= count($filtered) ?> results</span></div>
    <div class="news-list editorial-feed" aria-live="polite">
      <?php foreach ($filtered as $record): ?>
      <article class="news-item editorial-post">
        <a class="post-media" href="<?= e(detail_url('news', $record['id'])) ?>" aria-label="อ่าน <?= e($record['title']) ?>">
          <?php if (!empty($record['image'])): ?><img src="<?= e($record['image']) ?>" alt=""><?php else: ?><span class="media-placeholder"><span aria-hidden="true">✦</span><small>DEMO IMAGE</small></span><?php endif; ?>
        </a>
        <div class="news-item-body post-body"><div class="news-meta post-meta"><span class="placeholder-badge">DEMO PLACEHOLDER</span><span class="news-category"><?= e($record['category']) ?></span><span><?= e($record['date_label'] ?? $record['date']) ?></span></div><h3><a href="<?= e(detail_url('news', $record['id'])) ?>"><?= e($record['title']) ?></a></h3><p><?= e($record['summary']) ?></p><div class="post-footer"><span>By <?= e($record['author'] ?? 'Edu2Impact Team') ?></span><a class="post-link" href="<?= e(detail_url('news', $record['id'])) ?>">Read story <span aria-hidden="true">↗</span></a></div></div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php if (!$filtered): ?><div class="empty-state"><h2>ไม่พบข่าวที่ตรงกับการค้นหา</h2><p>ลองเปลี่ยนคำค้นหรือเลือกทุกหมวดหมู่</p><a class="button secondary" href="news.php">แสดงข่าวทั้งหมด</a></div><?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
