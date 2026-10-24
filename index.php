<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'home';
$pageTitle = 'Edu2Impact | จากงานวิจัยสู่การเปลี่ยนแปลง';
$byDate = static function (array $a, array $b): int { return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? '')); };
$research = sort_records(load_data('research'), true); // featured research first, then newest
$innovations = load_data('innovations'); usort($innovations, $byDate);
$workshops = load_data('workshops'); usort($workshops, $byDate);
$media = load_data('media'); usort($media, $byDate);
$tools = tool_records();
// Card sections show the three newest records of a dataset as equal cards that expand on hover or focus.
$cardSection = static fn (array $records, string $kind, string $id, string $heading, string $fallback): array =>
    ['type' => 'cards', 'kind' => $kind, 'id' => $id, 'heading' => $heading, 'fallback' => $fallback, 'tone' => '', 'records' => array_slice($records, 0, 3)];
// Pairs: a workshop and the research or innovation it builds on, from each workshop's "related" list.
$pairs = [];
foreach ($workshops as $workshop) {
    foreach (related_records('workshops', $workshop) as $source) { $pairs[] = ['source' => $source, 'workshop' => $workshop]; }
}
$pairs = array_slice($pairs, 0, 3);
// Latest: every public post of every type, newest first. Posts without a real date follow the dated ones.
// One main post and up to six more in the side list; nothing is invented to fill the list.
$feed = [];
foreach (['research' => $research, 'innovations' => $innovations, 'workshops' => $workshops, 'media' => $media] as $feedKind => $feedRecords) {
    foreach ($feedRecords as $feedRecord) { $feed[] = ['kind' => $feedKind, 'record' => $feedRecord, 'dated' => is_dated($feedRecord)]; }
}
usort($feed, static fn (array $a, array $b): int => ($b['dated'] <=> $a['dated']) ?: ($a['dated'] ? strcmp($b['record']['date'], $a['record']['date']) : 0));
$feed = array_slice($feed, 0, 7);
// Empty sections are left out. Light sections alternate white / tinted; the pairs section is always dark.
$homeSections = array_values(array_filter([
    $feed ? ['type' => 'latest', 'id' => 'latest', 'heading' => 'ผลงานใหม่ล่าสุด', 'tone' => ''] : null,
    $pairs ? ['type' => 'pairs', 'id' => 'from-research', 'heading' => 'From Innovation to Workshop', 'tone' => 'dark'] : null,
    $innovations ? $cardSection($innovations, 'innovations', 'innovations', 'นวัตกรรมการศึกษาล่าสุด', 'นวัตกรรม') : null,
    $tools ? ['type' => 'tools', 'id' => 'tools', 'heading' => 'เครื่องมือออนไลน์', 'tone' => ''] : null,
    $workshops ? $cardSection($workshops, 'workshops', 'workshops', 'อบรมและเวิร์กช็อป', 'เวิร์กช็อป') : null,
]));
$nextTone = 'white';
foreach ($homeSections as &$section) {
    if ($section['tone'] === 'dark') { continue; }
    $section['tone'] = $nextTone; $nextTone = $nextTone === 'white' ? 'light' : 'white';
}
unset($section);
$firstSection = $homeSections ? '#' . $homeSections[0]['id'] : 'research.php';

// Slide text comes from config/heroes.php so it matches the hero banner of the page each slide links to.
$heroSlides = array_map(static function (array $slide): array {
    $hero = hero_text($slide['key']);
    return $slide + ['eyebrow' => $hero['eyebrow'], 'title' => hero_title_html($hero), 'text' => $hero['intro']];
}, [
    ['key' => 'home', 'label' => 'หน้าแรก', 'image' => 'home-hero-campus.webp', 'href' => $firstSection, 'cta' => 'ดูผลงานใหม่ล่าสุด'],
    ['key' => 'research', 'label' => 'คลังความรู้', 'image' => 'hero-innovation.webp', 'href' => 'research.php', 'cta' => 'ดูงานวิจัย'],
    ['key' => 'innovations', 'label' => 'นวัตกรรม', 'image' => 'hero-impact.webp', 'href' => 'innovations.php', 'cta' => 'ดูนวัตกรรม'],
    ['key' => 'workshops', 'label' => 'บริการของเรา', 'image' => 'hero-resources.webp', 'href' => 'workshops.php', 'cta' => 'ดูเวิร์กช็อป'],
    ['key' => 'contact', 'label' => 'ติดต่อเรา', 'image' => 'hero-partners.webp', 'href' => 'contact.php', 'cta' => 'ติดต่อเรา'],
]);
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="home-page">
  <section class="home-hero hero-slider" data-slider aria-roledescription="สไลด์โชว์" aria-label="ไฮไลต์ของเว็บไซต์">
    <div class="hero-slides">
      <?php foreach ($heroSlides as $index => $slide): ?>
        <article class="hero-slide hero-slide--<?= e($slide['key']) ?><?= $index === 0 ? ' is-active' : '' ?>" data-slide="<?= $index ?>" style="background-image:url('assets/images/<?= e($slide['image']) ?>')"><img class="hero-slide-image" src="assets/images/<?= e($slide['image']) ?>" alt=""<?= $index === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>><div class="home-hero-overlay"></div><div class="container home-hero-inner"><div class="hero-copy"><p class="eyebrow"><?= e($slide['eyebrow']) ?></p><h1><?= $slide['title'] ?></h1><p class="lead"><?= thai_text($slide['text']) ?></p><a class="button hero-primary" href="<?= e($slide['href']) ?>"><?= e($slide['cta']) ?></a></div></div></article>
      <?php endforeach; ?>
    </div>
    <div class="hero-controls">
      <button class="hero-ctrl" type="button" data-slider-prev aria-label="สไลด์ก่อนหน้า"><?= icon_arrow('chevron-left') ?></button>
      <button class="hero-ctrl" type="button" data-slider-next aria-label="สไลด์ถัดไป"><?= icon_arrow('chevron-right') ?></button>
    </div>
    <p class="visually-hidden" role="status" aria-live="polite" data-slider-status>สไลด์ 1 จาก <?= count($heroSlides) ?></p>
  </section>
  <?php foreach ($homeSections as $block): $listHref = $block['type'] === 'latest' ? '' : ($block['type'] === 'cards' ? $block['kind'] . '.php' : ($block['type'] === 'tools' ? 'tools.php' : '')); ?>
  <section id="<?= e($block['id']) ?>" class="home-section home-section--<?= e($block['tone']) ?> home-section--<?= e($block['type']) ?>" aria-labelledby="<?= e($block['id']) ?>-title"><div class="container">
    <div class="home-section-heading"><h2 id="<?= e($block['id']) ?>-title"<?= $block['type'] === 'pairs' ? ' lang="en"' : '' ?>><?= e($block['heading']) ?></h2><?php if ($listHref !== ''): ?><a class="home-section-link" href="<?= e($listHref) ?>">ดูทั้งหมด<span class="visually-hidden"> <?= e($block['heading']) ?></span> <?= icon_arrow('up-right') ?></a><?php endif; ?></div>
<?php if ($block['type'] === 'latest'): $main = $feed[0]; $rest = array_slice($feed, 1); $mainRecord = $main['record']; $mainKey = $main['kind'] . '/' . $mainRecord['id']; ?>
    <div class="latest">
      <article class="latest-main">
        <a class="latest-main__media" href="<?= e(detail_url($main['kind'], $mainRecord['id'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(image_path($mainRecord) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></a>
        <div class="latest-main__body">
          <p class="latest-meta"><span class="latest-kind"><?= e(post_kind_label($main['kind'])) ?></span><?php if ($main['dated']): ?><time datetime="<?= e($mainRecord['date']) ?>"><?= e(thai_date($mainRecord['date'])) ?></time><?php endif; ?><?= visit_eye(visit_count($mainKey)) ?></p>
          <h3 class="latest-main__title"><a href="<?= e(detail_url($main['kind'], $mainRecord['id'])) ?>"><?= e($mainRecord['title']) ?></a></h3>
          <?php if (trim((string) ($mainRecord['summary'] ?? '')) !== ''): ?><p class="latest-main__summary"><?= e($mainRecord['summary']) ?></p><?php endif; ?>
        </div>
      </article>
<?php if ($rest): ?>
      <ol class="latest-list">
<?php foreach ($rest as $item): $r = $item['record']; $key = $item['kind'] . '/' . $r['id']; ?>
        <li class="latest-item">
          <p class="latest-meta"><span class="latest-kind"><?= e(post_kind_label($item['kind'])) ?></span><?php if ($item['dated']): ?><time datetime="<?= e($r['date']) ?>"><?= e(thai_date($r['date'])) ?></time><?php endif; ?><?= visit_eye(visit_count($key)) ?></p>
          <h3 class="latest-item__title"><a href="<?= e(detail_url($item['kind'], $r['id'])) ?>"><?= e($r['title']) ?></a></h3>
        </li>
<?php endforeach; ?>
      </ol>
<?php endif; ?>
    </div>
<?php elseif ($block['type'] === 'pairs'): ?>
    <p class="pairs-intro">งานวิจัยและนวัตกรรมที่เราพัฒนาต่อเป็นเวิร์กช็อป ให้ครูได้ลงมือทำจริง</p>
    <ul class="pair-list">
<?php foreach ($pairs as $pair): $src = $pair['source']; $w = $pair['workshop']; ?>
      <li class="pair">
        <a class="pair__src" href="<?= e(detail_url($src['kind'], $src['record']['id'])) ?>">
          <span class="pair__img"><img src="<?= e(image_path($src['record']) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></span>
          <span class="pair__text"><span class="pair__kind"><?= e(kind_label($src['kind'])) ?></span><span class="pair__title"><?= e($src['record']['title']) ?></span></span>
        </a>
        <a class="pair__ws" href="<?= e(detail_url('workshops', $w['id'])) ?>">
          <span class="pair__kind"><?= e(kind_label('workshops')) ?></span>
          <span class="pair__title"><?= e($w['title']) ?></span>
<?php if (trim((string) ($w['summary'] ?? '')) !== ''): ?>          <span class="pair__sum"><?= e($w['summary']) ?></span>
<?php endif; ?>          <span class="pair__go" aria-hidden="true"><?= icon_arrow('up-right') ?></span>
        </a>
      </li>
<?php endforeach; ?>
    </ul>
    <p class="pairs-cta"><a class="button" href="contact.php">ขอจัดเวิร์กช็อปที่โรงเรียนของคุณ <?= icon_arrow('up-right') ?></a></p>
<?php elseif ($block['type'] === 'tools'): ?>
    <div class="tool-list tool-list--home">
<?php foreach (array_slice($tools, 0, 3) as $tool): $record = $tool['record']; $kind = $tool['kind']; $url = tool_url($record); $external = str_starts_with($url, 'https://'); ?>
      <article class="tool-row">
        <div class="tool-row__body">
          <p class="lib-meta"><span><?= e(kind_label($kind)) ?></span></p>
          <h3><?= e($record['title']) ?></h3>
          <p class="tool-row__summary"><?= e($record['summary'] ?? '') ?></p>
          <p class="actions tool-row__actions"><a class="button" href="<?= e($url) ?>"<?= $external ? ' rel="noopener"' : '' ?>><?= e(trim((string) ($record['tool_label'] ?? '')) ?: 'เปิดเครื่องมือ') ?> <?= icon_arrow('up-right') ?></a><a class="text-link" href="<?= e(detail_url($kind, $record['id'])) ?>">ดูรายละเอียด<span class="visually-hidden"> <?= e($record['title']) ?></span></a></p>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <ul class="xcards" data-count="<?= count($block['records']) ?>">
<?php foreach ($block['records'] as $record): $href = detail_url($block['kind'], $record['id']); $category = trim((string) ($record['category'] ?? '')) ?: $block['fallback']; $summary = trim((string) ($record['summary'] ?? '')); ?>
      <li class="xcard">
        <img class="xcard__img" src="<?= e(image_path($record) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy">
        <div class="xcard__panel">
          <p class="xcard__meta"><span><?= e($category) ?></span><?php if (is_dated($record)): ?><span><?= e(thai_date($record['date'])) ?></span><?php endif; ?><?= visit_eye(visit_count($block['kind'] . '/' . $record['id'])) ?></p>
          <h3 class="xcard__title"><a class="xcard__link" href="<?= e($href) ?>"><?= e($record['title']) ?></a></h3>
          <?php if ($summary !== ''): ?><div class="xcard__more"><div><p class="xcard__summary"><?= e($summary) ?></p><span class="xcard__cta" aria-hidden="true">อ่านต่อ <?= icon_arrow('up-right') ?></span></div></div><?php endif; ?>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </div></section>
  <?php endforeach; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
