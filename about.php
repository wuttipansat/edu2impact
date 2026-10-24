<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'about'; $pageTitle = 'เกี่ยวกับเรา | ' . $site['name'];
// Counts come straight from the data files, so they stay true as content is added.
$counts = [];
foreach (['research' => 'งานวิจัย', 'innovations' => 'นวัตกรรม', 'workshops' => 'เวิร์กช็อป', 'media' => 'สื่อและคู่มือ'] as $kind => $label) {
    $n = count(load_data($kind));
    if ($n > 0) { $counts[] = ['n' => $n, 'label' => $label, 'href' => $kind . '.php']; }
}
$steps = [
    ['title' => 'อ่านงานวิจัย', 'text' => 'ทุกเรื่องสรุปว่าศึกษาอะไร พบอะไร และนำไปใช้ในห้องเรียนอย่างไร', 'href' => 'research.php', 'cta' => 'ดูงานวิจัย'],
    ['title' => 'ลองใช้นวัตกรรม', 'text' => 'ชุดกิจกรรม แบบจำลอง และเครื่องมือออนไลน์ที่ทดลองใช้กับผู้เรียนได้', 'href' => 'innovations.php', 'cta' => 'ดูนวัตกรรม'],
    ['title' => 'เรียนรู้ผ่านการอบรม', 'text' => 'เวิร์กช็อปแบบลงมือทำ ติดต่อทีมเพื่อขอให้ไปจัดที่โรงเรียนได้', 'href' => 'workshops.php', 'cta' => 'ดูเวิร์กช็อป'],
];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--about"><?php render_page_hero('about'); ?>
<section class="about-intro" aria-labelledby="about-intro-title"><div class="container about-intro__grid">
  <div class="about-intro__text"><h2 id="about-intro-title">เว็บไซต์นี้มีไว้ทำอะไร</h2><p>EDU2IMPACT เก็บงานวิจัย นวัตกรรม และเวิร์กช็อปด้านการศึกษาของมหาวิทยาลัยเกษตรศาสตร์ไว้ในที่เดียว ครูและโรงเรียนอ่านสรุปงานวิจัย เปิดใช้เครื่องมือออนไลน์ หรือติดต่อทีมเพื่อขอนำงานชิ้นใดชิ้นหนึ่งไปใช้ได้</p></div>
<?php if ($counts): ?>  <ul class="about-counts" aria-label="จำนวนเนื้อหาในเว็บไซต์"><?php foreach ($counts as $c): ?><li><a href="<?= e($c['href']) ?>"><strong><?= (int) $c['n'] ?></strong><span><?= e($c['label']) ?></span></a></li><?php endforeach; ?></ul>
<?php endif; ?>
</div></section>
<section class="about-steps" aria-labelledby="about-steps-title"><div class="container">
  <h2 id="about-steps-title">ใช้งานอย่างไร</h2>
  <ol class="about-steps__list"><?php foreach ($steps as $step): ?><li class="about-step"><h3><?= e($step['title']) ?></h3><p><?= e($step['text']) ?></p><a href="<?= e($step['href']) ?>"><?= e($step['cta']) ?><span class="visually-hidden"> <?= e($step['title']) ?></span> <?= icon_arrow('up-right') ?></a></li><?php endforeach; ?></ol>
</div></section>
</main><?php require __DIR__ . '/includes/footer.php'; ?>
