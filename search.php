<?php
// Site-wide search results. Plain GET (?q=…); the header icon and dialog link here.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/search.php';
$result = search_run(query_string('q'));
$q = $result['q'];
$groups = search_groups();
$currentPage = 'search';
$metaRobots = 'noindex';
$pageTitle = ($q === '' ? 'ค้นหา' : '“' . $q . '” – ค้นหา') . ' | ' . $site['name'];
$quickLinks = ['research.php' => 'Research', 'innovations.php' => 'Innovation', 'workshops.php' => 'Workshop', 'partners.php' => 'Partners', 'about.php' => 'About'];
$searchIcon = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--search">
  <?php render_page_hero('search'); ?>
  <section class="container editorial-canvas search-page">
    <form class="search-page__form" method="get" action="search.php" role="search">
      <label class="visually-hidden" for="search-page-q">ค้นหาทั้งเว็บไซต์</label>
      <div class="search-box"><?= $searchIcon ?><input id="search-page-q" type="search" name="q" value="<?= e($q) ?>" maxlength="<?= SEARCH_MAX_LENGTH ?>" autocomplete="off" placeholder="ค้นหางานวิจัย นวัตกรรม เวิร์กช็อป…"></div>
      <button class="button search-page__submit" type="submit">ค้นหา</button>
    </form>

<?php if ($q === ''): ?>
    <div class="search-message">
      <h2>พิมพ์คำที่ต้องการค้นหา</h2>
      <p>ค้นหาได้ทั้งงานวิจัย นวัตกรรม เวิร์กช็อป กิจกรรม และทุกหน้าในเว็บไซต์</p>
      <?php $suggestions = search_suggestions(); if ($suggestions): ?>
      <p class="search-message__label" id="search-suggest-label">ลองค้นหา</p>
      <ul class="search-suggest" aria-labelledby="search-suggest-label"><?php foreach ($suggestions as $word): ?><li><a href="search.php?q=<?= e(rawurlencode($word)) ?>"><?= e($word) ?></a></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <nav class="search-quick" aria-label="ลิงก์ด่วน"><?php foreach ($quickLinks as $href => $label): ?><a href="<?= e($href) ?>"><?= e($label) ?> →</a><?php endforeach; ?></nav>
    </div>
<?php elseif ($result['total'] === 0): ?>
    <div class="search-message" role="status">
      <h2>ไม่พบผลลัพธ์สำหรับ “<?= e($q) ?>”</h2>
      <p>ลองใช้คำที่สั้นลง ตรวจการสะกด หรือค้นหาเป็นภาษาอังกฤษ/ไทยอีกแบบ</p>
      <nav class="search-quick" aria-label="ลิงก์ด่วน"><?php foreach ($quickLinks as $href => $label): ?><a href="<?= e($href) ?>"><?= e($label) ?> →</a><?php endforeach; ?></nav>
    </div>
<?php else: ?>
    <p class="search-status" role="status">พบ <strong><?= (int) $result['total'] ?></strong> รายการสำหรับ “<?= e($q) ?>”</p>
    <nav class="search-jump" aria-label="ไปยังกลุ่มผลลัพธ์"><ul>
      <?php foreach ($groups as $type => $group): if (!$result['groups'][$type]) { continue; } ?>
      <li><a href="#results-<?= e($type) ?>"><?= e($group['label']) ?> <span>· <?= count($result['groups'][$type]) ?></span></a></li>
      <?php endforeach; ?>
    </ul></nav>
    <?php foreach ($groups as $type => $group): $entries = $result['groups'][$type]; if (!$entries) { continue; } ?>
    <section class="search-group" id="results-<?= e($type) ?>" aria-labelledby="results-<?= e($type) ?>-title">
      <h2 id="results-<?= e($type) ?>-title"><?= e($group['label']) ?> <span class="search-group__count"><?= count($entries) ?></span></h2>
      <ol class="search-results">
        <?php foreach (array_slice($entries, 0, SEARCH_GROUP_LIMIT) as $entry): ?>
        <li class="search-result">
          <p class="search-result__meta"><span class="search-result__type"><?= e($group['label']) ?></span><?php if ($entry['category'] !== ''): ?><span><?= e($entry['category']) ?></span><?php endif; ?><?php if ($entry['date'] !== ''): ?><span><?= e($entry['date']) ?></span><?php endif; ?></p>
          <h3><a href="<?= e($entry['url']) ?>"><?= search_highlight($entry['title'], $q) ?></a></h3>
          <?php $excerpt = search_excerpt($entry, $q); if ($excerpt !== ''): ?><p class="search-result__excerpt"><?= search_highlight($excerpt, $q) ?></p><?php endif; ?>
          <p class="search-result__url"><?= e($entry['url']) ?></p>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php if (count($entries) > SEARCH_GROUP_LIMIT && $group['page'] !== ''): ?><a class="search-group__more" href="<?= e($group['page']) ?>">ดูทั้งหมดในหน้า <?= e($group['page_name']) ?> →</a><?php endif; ?>
    </section>
    <?php endforeach; ?>
<?php endif; ?>
  </section>
</main><?php require __DIR__ . '/includes/footer.php'; ?>
