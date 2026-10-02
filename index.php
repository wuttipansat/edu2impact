<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'home';
$pageTitle = 'Edu2Impact | เชื่อมงานวิจัยสู่การใช้ประโยชน์';
$research = load_data('research');
$news = load_data('news');
require __DIR__ . '/includes/header.php';
?>
<main id="main">
<section class="container hero"><div class="hero-copy"><p class="eyebrow">FROM EDUCATION TO IMPACT</p><h1>เชื่อมงานวิจัย<br>สู่การเปลี่ยนแปลง<br><em>ที่มีความหมาย</em></h1><p class="lead"><?= e($site['description']) ?></p><div class="actions"><a class="button" href="research.php">สำรวจงานวิจัย ↗</a><a class="button secondary" href="contact.php">ร่วมสร้างความร่วมมือ</a></div></div>
<aside class="hero-art"><div class="orbit" aria-hidden="true"><div class="orbit-center">e<span>2</span>i</div><span class="orbit-node node-a">องค์ความรู้</span><span class="orbit-node node-b">ความร่วมมือ</span><span class="orbit-node node-c">การนำไปใช้</span></div><div class="art-caption"><span class="eyebrow">OUR PURPOSE</span><h2>ความรู้ที่ส่งต่อได้<br>ผลลัพธ์ที่เกิดขึ้นจริง</h2><p>จากคำถามในห้องเรียน สู่แนวทางใหม่ที่พัฒนาต่อได้</p></div></aside></section>
<section class="search-band"><div class="container"><form class="search-form" action="research.php" method="get" role="search"><label for="home-query">ค้นพบองค์ความรู้ใหม่</label><div class="search-controls"><input id="home-query" type="search" name="q" placeholder="ค้นหางานวิจัยหรือคำสำคัญ…" maxlength="200"><button class="button" type="submit">ค้นหา</button></div></form></div></section>
<section class="section container"><div class="section-heading"><div><p class="eyebrow">EXPLORE OUR WORK</p><h2>งานวิจัยและนวัตกรรม</h2></div><a class="text-link" href="research.php">ดูงานวิจัยทั้งหมด ↗</a></div><div class="card-grid"><?php $count = 0; foreach ($research as $record) { if (!empty($record['featured'])) { render_card($record, 'research'); $count++; if ($count === 3) { break; } } } if ($count === 0): ?><p>ยังไม่มีผลงานแนะนำ</p><?php endif; ?></div></section>
<section class="mission-band"><div class="container mission-inner"><div><p class="eyebrow">KNOWLEDGE INTO ACTION</p><h2>เชื่อมคน เชื่อมความรู้<br>สร้างโอกาสใหม่ให้การศึกษา</h2></div><div><p>สำรวจองค์ความรู้ที่นำไปต่อยอดได้ แลกเปลี่ยนแนวคิด และร่วมพัฒนาแนวทางที่เหมาะกับบริบทของสถานศึกษาและชุมชน</p><a class="text-link" href="about.php">รู้จัก Edu2Impact ↗</a></div></div></section>
<section class="section container"><div class="section-heading"><div><p class="eyebrow">NEWS & KNOWLEDGE</p><h2>ข่าวสารและองค์ความรู้</h2></div><a class="text-link" href="news.php">ดูข่าวทั้งหมด ↗</a></div><div class="news-grid"><?php foreach (array_slice($news, 0, 2) as $record): ?><article class="news-row"><span class="eyebrow"><?= e($record['category']) ?></span><h3><a href="<?= e(detail_url('news', $record['id'])) ?>"><?= e($record['title']) ?></a></h3><p><?= e($record['summary']) ?></p><a class="text-link" href="<?= e(detail_url('news', $record['id'])) ?>">อ่านต่อ ↗</a></article><?php endforeach; ?><?php if (!$news): ?><p>ยังไม่มีข่าวสาร</p><?php endif; ?></div></section>
<section class="contact-band"><div class="container contact-inner"><div><p class="eyebrow">LET’S CREATE IMPACT TOGETHER</p><h2>เริ่มต้นความร่วมมือใหม่</h2><p>สำหรับสถานศึกษา นักวิจัย และหน่วยงานที่สนใจ</p></div><a class="button" href="contact.php">ติดต่อเรา ↗</a></div></section>
</main><?php require __DIR__ . '/includes/footer.php'; ?>
