<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'home';
$pageTitle = 'Edu2Impact | Research to Impact';
$research = load_data('research');
$byDate = static function (array $a, array $b): int { return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? '')); };
$research = sort_records($research, true); // featured research first, then newest
$innovations = load_data('innovations'); usort($innovations, $byDate);
$workshops = load_data('workshops'); usort($workshops, $byDate);
// Home sections that share the Latest Research card layout: three newest records each.
$homeSections = [
    ['kind' => 'research', 'id' => 'latest', 'kicker' => 'LATEST RESEARCH', 'heading' => 'ผลงานวิจัยล่าสุด', 'fallback' => 'Research', 'records' => array_slice($research, 0, 3)],
    ['kind' => 'innovations', 'id' => '', 'kicker' => 'LATEST INNOVATION', 'heading' => 'นวัตกรรมล่าสุด', 'fallback' => 'Innovation', 'records' => array_slice($innovations, 0, 3)],
    ['kind' => 'workshops', 'id' => '', 'kicker' => 'WORKSHOPS', 'heading' => 'เวิร์กช็อป', 'fallback' => 'Workshop', 'records' => array_slice($workshops, 0, 3)],
];
// Upcoming events from data/events.json: today or later (Bangkok time), soonest first, max 3.

// Slide text comes from config/heroes.php so it matches the hero banner of the page each slide links to.
$heroSlides = array_map(static function (array $slide): array {
    $hero = hero_text($slide['key']);
    return $slide + ['eyebrow' => $hero['eyebrow'], 'title' => hero_title_html($hero), 'text' => $hero['intro']];
}, [
    ['key' => 'home', 'label' => 'HOME', 'image' => 'home-hero-campus.webp', 'href' => '#latest', 'cta' => 'Explore'],
    ['key' => 'research', 'label' => 'RESEARCH', 'image' => 'hero-innovation.webp', 'href' => 'research.php', 'cta' => 'View research'],
    ['key' => 'innovations', 'label' => 'INNOVATION', 'image' => 'hero-impact.webp', 'href' => 'innovations.php', 'cta' => 'View innovations'],
    ['key' => 'workshops', 'label' => 'WORKSHOP', 'image' => 'hero-resources.webp', 'href' => 'workshops.php', 'cta' => 'View workshops'],
    ['key' => 'contact', 'label' => 'CONTACT', 'image' => 'hero-partners.webp', 'href' => 'contact.php', 'cta' => 'Contact us'],
]);
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="home-page">
  <section class="home-hero hero-slider" data-slider aria-label="Edu2Impact sections">
    <div class="hero-slides">
      <?php foreach ($heroSlides as $index => $slide): ?>
        <article class="hero-slide hero-slide--<?= e($slide['key']) ?><?= $index === 0 ? ' is-active' : '' ?>" data-slide="<?= $index ?>" style="background-image:url('assets/images/<?= e($slide['image']) ?>')"><img class="hero-slide-image" src="assets/images/<?= e($slide['image']) ?>" alt=""<?= $index === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>><div class="home-hero-overlay"></div><div class="container home-hero-inner"><div class="hero-copy"><p class="eyebrow"><?= e($slide['eyebrow']) ?></p><h1><?= $slide['title'] ?></h1><p class="lead"><?= e($slide['text']) ?></p><a class="button hero-primary" href="<?= e($slide['href']) ?>"><?= e($slide['cta']) ?></a></div></div></article>
      <?php endforeach; ?>
    </div>
    <button class="hero-arrow hero-arrow-prev" type="button" data-slider-prev aria-label="สไลด์ก่อนหน้า"><?= icon_arrow('chevron-left') ?></button><button class="hero-arrow hero-arrow-next" type="button" data-slider-next aria-label="สไลด์ถัดไป"><?= icon_arrow('chevron-right') ?></button>
    <div class="hero-slider-controls" role="tablist" aria-label="เลือกหน้าเว็บไซต์"><?php foreach ($heroSlides as $index => $slide): ?><button class="hero-dot<?= $index === 0 ? ' is-active' : '' ?>" type="button" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="<?= e($slide['label']) ?>" data-slider-dot="<?= $index ?>"><span><?= e($slide['label']) ?></span></button><?php endforeach; ?></div>
  </section>
  <?php foreach ($homeSections as $block): ?><section<?= $block['id'] ? ' id="' . e($block['id']) . '"' : '' ?> class="home-section home-section--research home-section--<?= e($block['kind']) ?>"><div class="container"><div class="home-section-heading"><div><span class="section-kicker"><?= e($block['kicker']) ?></span><h2><?= e($block['heading']) ?></h2></div><a class="home-section-link" href="<?= e($block['kind']) ?>.php">View all <?= icon_arrow('up-right') ?></a></div><div class="home-research-grid"><?php foreach ($block['records'] as $record): ?><article class="home-research-card"><a class="home-card-media" href="<?= e(detail_url($block['kind'], $record['id'])) ?>"><img src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></a><div class="home-card-body"><?php $cardCategory = trim((string) ($record['category'] ?? '')) ?: $block['fallback']; $cardDate = is_dated($record) ? $record['date'] : (array_key_exists('date', $record) ? '' : 'Coming soon'); ?><div class="post-meta"><span><?= e($cardCategory) ?></span><?php if ($cardDate !== ''): ?><span><?= e($cardDate) ?></span><?php endif; ?></div><h3><a href="<?= e(detail_url($block['kind'], $record['id'])) ?>"><?= e($record['title']) ?></a></h3><a class="read-link" href="<?= e(detail_url($block['kind'], $record['id'])) ?>">Read</a></div></article><?php endforeach; ?></div></div></section><?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
