<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'home';
$pageTitle = 'Edu2Impact | From research to impact';
$innovations = load_data('innovations');
$news = load_data('news');
$research = load_data('research');
$byDate = static function (array $a, array $b): int { return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? '')); };
usort($news, $byDate); usort($research, $byDate);
$latestNews = array_slice($news, 0, 3); $latestResearch = array_slice($research, 0, 3);
$events = [
    ['month' => 'NOV', 'day' => '12', 'type' => 'SEMINAR', 'title' => 'Research to Practice Forum', 'meta' => 'เวทีแลกเปลี่ยนงานวิจัยสู่การใช้จริง'],
    ['month' => 'DEC', 'day' => '05', 'type' => 'WORKSHOP', 'title' => 'Learning Design Lab', 'meta' => 'ห้องทดลองออกแบบการเรียนรู้ร่วมกับเครือข่าย'],
    ['month' => 'JAN', 'day' => '22', 'type' => 'CONFERENCE', 'title' => 'Edu2Impact Annual Meeting', 'meta' => 'พบงานวิจัย ผู้ใช้ และพันธมิตรในพื้นที่เดียวกัน'],
];
$heroSlides = [
    ['key' => 'home', 'label' => 'HOME', 'title' => 'From research to <em>real-world</em> impact.', 'text' => 'พื้นที่กลางที่ทำให้งานวิจัย องค์ความรู้ และผู้คนที่พร้อมนำไปใช้พบกันได้ง่ายขึ้น', 'image' => 'home-hero-campus.png', 'href' => '#latest', 'cta' => 'Explore the platform'],
    ['key' => 'news', 'label' => 'NEWS', 'title' => 'Ideas in <em>motion.</em>', 'text' => 'ติดตามข่าวสาร กิจกรรม และเรื่องราวการขับเคลื่อนความรู้จากเครือข่าย Edu2Impact', 'image' => 'hero-impact.png', 'href' => 'news.php', 'cta' => 'Explore News'],
    ['key' => 'research', 'label' => 'RESEARCH', 'title' => 'Research with a <em>way forward.</em>', 'text' => 'ค้นพบผลงานวิจัยล่าสุดที่ช่วยอธิบายปัญหาและเปิดโอกาสให้เกิดการนำไปใช้จริง', 'image' => 'hero-innovation.png', 'href' => 'research.php', 'cta' => 'Explore Research'],
    ['key' => 'partners', 'label' => 'PARTNERS', 'title' => 'Impact moves <em>together.</em>', 'text' => 'เชื่อมโยงนักวิจัย สถานศึกษา หน่วยงาน ชุมชน และภาคเอกชนให้ร่วมสร้างผลลัพธ์', 'image' => 'hero-partners.png', 'href' => 'partners.php', 'cta' => 'Meet Partners'],
    ['key' => 'about', 'label' => 'ABOUT', 'title' => 'A platform for <em>useful knowledge.</em>', 'text' => 'ทำความรู้จัก EDU2IMPACT และบทบาทของแพลตฟอร์มที่เชื่อมงานวิจัยกับการนำไปใช้', 'image' => 'hero-resources.png', 'href' => 'about.php', 'cta' => 'What is EDU2IMPACT?'],
];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="home-page">
  <section class="home-hero hero-slider" data-slider aria-label="Edu2Impact sections">
    <div class="hero-slides">
      <?php foreach ($heroSlides as $index => $slide): ?>
        <article class="hero-slide hero-slide--<?= e($slide['key']) ?><?= $index === 0 ? ' is-active' : '' ?>" data-slide="<?= $index ?>" style="background-image:url('assets/images/<?= e($slide['image']) ?>')"><img class="hero-slide-image" src="assets/images/<?= e($slide['image']) ?>" alt=""<?= $index === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>><div class="home-hero-overlay"></div><div class="container home-hero-inner"><div class="hero-copy"><p class="eyebrow">EDU2IMPACT / <?= e($slide['label']) ?></p><h1><?= $slide['title'] ?></h1><p class="lead"><?= e($slide['text']) ?></p><a class="button hero-primary" href="<?= e($slide['href']) ?>"><?= e($slide['cta']) ?></a></div></div></article>
      <?php endforeach; ?>
    </div>
    <button class="hero-arrow hero-arrow-prev" type="button" data-slider-prev aria-label="สไลด์ก่อนหน้า">‹</button><button class="hero-arrow hero-arrow-next" type="button" data-slider-next aria-label="สไลด์ถัดไป">›</button>
    <div class="hero-slider-controls" role="tablist" aria-label="เลือกหน้าเว็บไซต์"><?php foreach ($heroSlides as $index => $slide): ?><button class="hero-dot<?= $index === 0 ? ' is-active' : '' ?>" type="button" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="<?= e($slide['label']) ?>" data-slider-dot="<?= $index ?>"><span><?= e($slide['label']) ?></span></button><?php endforeach; ?></div>
  </section>
  <section id="latest" class="home-section home-section--news"><div class="container"><div class="home-section-heading"><div><span class="section-kicker">LATEST NEWS</span><h2>ข่าวล่าสุด</h2></div><a class="home-section-link" href="news.php">View all <span>↗</span></a></div><div class="home-news-grid"><?php foreach ($latestNews as $record): ?><article class="home-story-card"><a class="home-card-media" href="<?= e(detail_url('news', $record['id'])) ?>"><img src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></a><div class="home-card-body"><div class="post-meta"><span><?= e($record['category'] ?? 'News') ?></span><span><?= e($record['date'] ?? 'Coming soon') ?></span></div><h3><a href="<?= e(detail_url('news', $record['id'])) ?>"><?= e($record['title']) ?></a></h3><a class="read-link" href="<?= e(detail_url('news', $record['id'])) ?>">Read</a></div></article><?php endforeach; ?></div></div></section>
  <section class="home-section home-section--research"><div class="container"><div class="home-section-heading"><div><span class="section-kicker">LATEST RESEARCH</span><h2>ผลงานวิจัยล่าสุด</h2></div><a class="home-section-link" href="research.php">View all <span>↗</span></a></div><div class="home-research-grid"><?php foreach ($latestResearch as $record): ?><article class="home-research-card"><a class="home-card-media" href="<?= e(detail_url('research', $record['id'])) ?>"><img src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></a><div class="home-card-body"><div class="post-meta"><span><?= e($record['category'] ?? 'Research') ?></span><span><?= e($record['date'] ?? 'Coming soon') ?></span></div><h3><a href="<?= e(detail_url('research', $record['id'])) ?>"><?= e($record['title']) ?></a></h3><a class="read-link" href="<?= e(detail_url('research', $record['id'])) ?>">Read</a></div></article><?php endforeach; ?></div></div></section>
  <section class="home-section home-section--events"><div class="container"><div class="home-section-heading"><div><span class="section-kicker">MEET THE NETWORK</span><h2>การจัดงานประชุม / สัมมนา</h2></div><span class="home-section-note">Connect through shared practice</span></div><div class="event-list"><?php foreach ($events as $event): ?><article class="event-row"><div class="event-date"><strong><?= e($event['day']) ?></strong><span><?= e($event['month']) ?></span></div><div class="event-info"><span class="event-type"><?= e($event['type']) ?></span><h3><?= e($event['title']) ?></h3><p><?= e($event['meta']) ?></p></div><span class="event-arrow">↗</span></article><?php endforeach; ?></div></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
