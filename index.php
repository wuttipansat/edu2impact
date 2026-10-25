<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/rails.php';
$currentPage = 'home';
$pageTitle = 'KUEDU2IMPACT | จากงานวิจัยสู่การเปลี่ยนแปลง';
$byDate = static function (array $a, array $b): int { return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? '')); };
$research = sort_records(load_data('research'), true); // featured research first, then newest
$innovations = load_data('innovations'); usort($innovations, $byDate);
$workshops = load_data('workshops'); usort($workshops, $byDate);
$media = load_data('media'); usort($media, $byDate);
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
$promos = home_promos();
$videos = home_videos();
$facultyLinks = home_faculty_links();
$homeSections = array_values(array_filter([
    $promos ? ['type' => 'promos', 'id' => 'promos', 'heading' => 'ประชาสัมพันธ์', 'tone' => ''] : null,
    $feed ? ['type' => 'latest', 'id' => 'latest', 'heading' => 'ผลงานใหม่ล่าสุด', 'tone' => ''] : null,
    $videos ? ['type' => 'videos', 'id' => 'scoop', 'heading' => 'Scoop', 'tone' => ''] : null,
    $pairs ? ['type' => 'pairs', 'id' => 'from-research', 'heading' => 'From Innovation to Workshop', 'tone' => 'dark'] : null,
    $innovations ? $cardSection($innovations, 'innovations', 'innovations', 'นวัตกรรมการศึกษาล่าสุด', 'นวัตกรรม') : null,
    $workshops ? $cardSection($workshops, 'workshops', 'workshops', 'อบรมและเวิร์กช็อป', 'เวิร์กช็อป') : null,
    $facultyLinks ? ['type' => 'sites', 'id' => 'faculty-sites', 'heading' => 'เว็บไซต์ของคณะ', 'tone' => ''] : null,
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
  <?php foreach ($homeSections as $block): $listHref = $block['type'] === 'latest' ? '' : ($block['type'] === 'cards' ? $block['kind'] . '.php' : ''); ?>
  <section id="<?= e($block['id']) ?>" class="home-section home-section--<?= e($block['tone']) ?> home-section--<?= e($block['type']) ?>" aria-labelledby="<?= e($block['id']) ?>-title"><div class="container">
    <div class="home-section-heading"><h2 id="<?= e($block['id']) ?>-title"<?= in_array($block['type'], ['pairs', 'videos'], true) ? ' lang="en"' : '' ?>><?= e($block['heading']) ?></h2><?php if ($listHref !== ''): ?><a class="home-section-link" href="<?= e($listHref) ?>">ดูทั้งหมด<span class="visually-hidden"> <?= e($block['heading']) ?></span> <?= icon_arrow('up-right') ?></a><?php endif; ?></div>
<?php if ($block['type'] === 'promos'): $promoTotal = count($promos); ?>
  </div>
    <div class="stage stage--promo" data-stage role="region" aria-roledescription="carousel" aria-label="ประชาสัมพันธ์">
      <div class="stage__viewport">
        <div class="stage__track" data-stage-track>
<?php foreach ($promos as $i => $promo): $promoLive = $promo['url'] !== '#'; ?>
          <div class="stage__item" role="group" aria-roledescription="สไลด์" aria-label="<?= $i + 1 ?> จาก <?= $promoTotal ?>" data-title="<?= e($promo['title']) ?>">
<?php if ($promoLive): ?>
            <a class="banner" href="<?= e($promo['url']) ?>" aria-label="<?= e($promo['title']) ?>"><img src="<?= e($promo['image']) ?>" alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async"></a>
<?php else: ?>
            <div class="banner"><img src="<?= e($promo['image']) ?>" alt="<?= e($promo['title']) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async"></div>
<?php endif; ?>
<?php if ($promo['date'] !== ''): ?>            <p class="stage__caption"><time datetime="<?= e($promo['date']) ?>"><?= e(thai_date($promo['date'])) ?></time></p>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
        <div class="stage__ctrls" hidden><button class="hero-ctrl stage__ctrl" type="button" data-stage-prev aria-label="ประชาสัมพันธ์ก่อนหน้า"><?= icon_arrow('chevron-left') ?></button><button class="hero-ctrl stage__ctrl" type="button" data-stage-next aria-label="ประชาสัมพันธ์ถัดไป"><?= icon_arrow('chevron-right') ?></button></div>
      </div>
      <p class="visually-hidden" data-stage-count aria-live="polite">สไลด์ 1 จาก <?= $promoTotal ?>: <?= e($promos[0]['title']) ?></p>
    </div>
<?php elseif ($block['type'] === 'videos'): $videoTotal = count($videos); ?>
  </div>
    <div class="stage stage--video" data-stage data-stage-play role="region" aria-roledescription="carousel" aria-label="Scoop วิดีโอ">
      <div class="stage__viewport">
        <div class="stage__track" data-stage-track>
<?php foreach ($videos as $i => $video): ?>
          <div class="stage__item" role="group" aria-roledescription="สไลด์" aria-label="<?= $i + 1 ?> จาก <?= $videoTotal ?>" data-title="<?= e($video['title']) ?>">
            <div class="clip__frame"><a class="clip__play" href="<?= e($video['watch']) ?>" target="_blank" rel="noopener noreferrer" data-video-id="<?= e($video['id']) ?>" data-video-title="<?= e($video['title']) ?>" aria-label="เล่นวิดีโอ: <?= e($video['title']) ?>"><img src="<?= e($video['cover']) ?>" alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async"><span class="clip__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" focusable="false"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg></span></a></div>
            <div class="stage__caption"><h3 class="clip__title"><?= e($video['title']) ?></h3>
<?php if ($video['date'] !== ''): ?>              <p class="clip__date"><time datetime="<?= e($video['date']) ?>"><?= e(thai_date($video['date'])) ?></time></p>
<?php endif; ?>            </div>
          </div>
<?php endforeach; ?>
        </div>
        <div class="stage__ctrls" hidden><button class="hero-ctrl stage__ctrl" type="button" data-stage-prev aria-label="วิดีโอก่อนหน้า"><?= icon_arrow('chevron-left') ?></button><button class="hero-ctrl stage__ctrl" type="button" data-stage-next aria-label="วิดีโอถัดไป"><?= icon_arrow('chevron-right') ?></button></div>
      </div>
      <p class="visually-hidden" data-stage-count aria-live="polite">วิดีโอ 1 จาก <?= $videoTotal ?>: <?= e($videos[0]['title']) ?></p>
    </div>
<?php elseif ($block['type'] === 'sites'): ?>
    <ul class="site-links">
<?php foreach ($facultyLinks as $link): ?>
      <li><a class="site-link" href="<?= e($link['url']) ?>" target="_blank" rel="noopener"><span class="site-link__name"><?= e($link['title']) ?></span><span class="site-link__host"><?= e($link['note'] !== '' ? $link['note'] : $link['host']) ?></span><span class="site-link__go" aria-hidden="true"><?= icon_arrow('up-right') ?></span><span class="visually-hidden"> (เปิดในแท็บใหม่)</span></a></li>
<?php endforeach; ?>
    </ul>
<?php elseif ($block['type'] === 'latest'): $main = $feed[0]; $rest = array_slice($feed, 1); $mainRecord = $main['record']; $mainKey = $main['kind'] . '/' . $mainRecord['id']; ?>
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
  <?php if (!in_array($block['type'], ['promos', 'videos'], true)): ?></div><?php endif; ?></section>
  <?php endforeach; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
