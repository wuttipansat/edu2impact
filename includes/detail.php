<?php
$record = find_record(load_data($kind), query_string('id'));
$currentPage = $kind;
if (!$record) {
    http_response_code(404);
    $pageTitle = 'ไม่พบรายการ | ' . $site['name'];
    require __DIR__ . '/header.php';
    ?><main id="main" class="page-shell page-shell--detail page-shell--<?= e($kind) ?>"><section class="container section empty-state"><h1>ไม่พบรายการที่ต้องการ</h1><p>ลิงก์นี้อาจเก่าหรือรายการถูกนำออกแล้ว เลือกจากรายการทั้งหมดแทนได้</p><a class="button" href="<?= e($kind) ?>.php">กลับไปหน้ารายการ</a></section></main><?php
    require __DIR__ . '/footer.php';
    return;
}
if ($kind === 'innovations' && innovation_category_slug($record['category'] ?? '') !== '') { $navKey = 'innovations-' . innovation_category_slug($record['category']); }
$pageTitle = $record['title'] . ' | ' . $site['name'];
$pageDescription = $record['summary'];
$postStyles = true;
$visitKey = $kind . '/' . $record['id'];
$pagePath = detail_url($kind, $record['id']);
$ogType = 'article';
$ogImage = image_path($record) ?: null;
require __DIR__ . '/header.php';
?>
<main id="main" class="page-shell page-shell--detail page-shell--<?= e($kind) ?>"><section class="section container article-layout"><a class="text-link back-link" href="<?= e($kind) ?>.php"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg><span>กลับไปหน้ารายการ</span></a><article class="article-content"><?php $eyebrowCategory = trim((string) ($record['category'] ?? '')); ?><?php $sectionNames = ['research' => 'งานวิจัยและบทความ', 'innovations' => 'นวัตกรรมการศึกษา', 'workshops' => 'อบรมและเวิร์กช็อป', 'media' => 'สื่อและคู่มือ', 'news' => 'ข่าวสาร']; ?><p class="eyebrow"><?= e($sectionNames[$kind] ?? '') ?><?= $eyebrowCategory !== '' ? ' / ' . e($eyebrowCategory) : '' ?></p><h1><?= e($record['title']) ?></h1><?php
$metaDate = trim((string) ($record['date'] ?? ''));
$metaRead = reading_time($record['body'] ?? []);
$metaVisits = visit_eye(visit_count($visitKey));
if ($metaDate !== '' || $metaRead > 0 || $metaVisits !== ''): ?><div class="post-byline"><?php if ($metaDate !== ''): ?><div class="post-byline__item"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/></svg><span class="visually-hidden">วันที่เผยแพร่ </span><time datetime="<?= e($metaDate) ?>"><?= e(thai_date($metaDate)) ?></time></div><?php endif; ?><?php if ($metaRead > 0): ?><div class="post-byline__item"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>อ่าน ~<?= (int) $metaRead ?> นาที</span></div><?php endif; ?><?php if ($metaVisits !== ''): ?><div class="post-byline__item"><?= $metaVisits ?></div><?php endif; ?></div><?php endif; ?><p class="lead"><?= thai_text($record['summary']) ?></p><?php $image = image_path($record); if ($image): ?><img class="article-image" src="<?= e($image) ?>" alt="<?= e($record['title']) ?>"><?php endif; ?><?php $toolUrl = tool_url($record); if ($toolUrl): $external = str_starts_with($toolUrl, 'https://'); ?><p class="actions"><a class="button" href="<?= e($toolUrl) ?>"<?= $external ? ' rel="noopener"' : '' ?>><?= e(($record['tool_label'] ?? '') ?: 'เปิดใช้เครื่องมือ') ?> <?= icon_arrow() ?></a></p><?php endif; ?><?php if (!empty($record['body'])): ?><div class="prose"><?php foreach ($record['body'] as $block): if (is_string($block)): ?><p><?= thai_text($block) ?></p><?php elseif ($blockImage = body_image($block)): $caption = is_string($block['caption'] ?? null) ? trim($block['caption']) : ''; ?><figure class="prose-figure"><?php [$blockWidth, $blockHeight] = image_size($blockImage); ?><img src="<?= e($blockImage) ?>" alt="<?= e(body_image_alt($block)) ?>"<?= $blockWidth > 0 ? ' width="' . $blockWidth . '" height="' . $blockHeight . '"' : '' ?> loading="lazy"><?php if ($caption !== ''): ?><figcaption><?= e($caption) ?></figcaption><?php endif; ?></figure><?php endif; endforeach; ?></div><?php endif; ?><?php
$facts = ['audience' => 'เหมาะกับ', 'subjects' => 'ใช้ได้กับกลุ่มสาระ', 'features' => 'ความสามารถหลัก'];
$hasFacts = false; foreach ($facts as $key => $label) { if (text_list($record, $key)) { $hasFacts = true; } }
if ($hasFacts): ?><dl class="record-facts"><?php foreach ($facts as $key => $label): $items = text_list($record, $key); if (!$items) { continue; } ?><div><dt><?= e($label) ?></dt><dd><ul><?php foreach ($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></dd></div><?php endforeach; ?></dl><?php endif; ?><?php
if ($kind === 'workshops'): ?><p class="actions workshop-contact"><a class="button" href="contact.php">ติดต่อทีมงาน</a></p><?php endif; ?><?php
require_once __DIR__ . '/share.php'; render_share(detail_url($kind, $record['id']), $record['title']);
$linked = linked_records($kind, $record); if ($linked): ?><section class="record-related" aria-label="งานที่เกี่ยวข้อง"><ul class="related-list"><?php foreach ($linked as $link): $target = $link['record']; ?><li><a class="related-card" href="<?= e(detail_url($link['kind'], $target['id'])) ?>"><span class="related-card__kind"><?= e(kind_label($link['kind'])) ?></span><strong class="related-card__title"><?= e($target['title']) ?></strong><?php if (trim((string) ($target['summary'] ?? '')) !== ''): ?><span class="related-card__summary"><?= e($target['summary']) ?></span><?php endif; ?></a></li><?php endforeach; ?></ul></section><?php endif; ?><?php
$team = author_people($record['author'] ?? '');
if ($team):
    $teamLabels = ['research' => 'คณะผู้จัดทำ', 'innovations' => 'คณะผู้จัดทำ', 'media' => 'คณะผู้จัดทำ', 'workshops' => 'วิทยากร']; ?><section class="record-team" aria-labelledby="team-heading"><h2 id="team-heading"><?= e($teamLabels[$kind] ?? 'ผู้เขียน') ?></h2><ul><?php foreach ($team as $person): ?><li><span class="record-team__role"><?= e($person['title']) ?></span><span class="record-team__name"><?php foreach (preg_split('/\s+/u', $person['name']) as $i => $word): ?><?= $i ? ' ' : '' ?><span class="tw"><?= e($word) ?></span><?php endforeach; ?></span></li><?php endforeach; ?></ul></section><?php
endif;
$references = text_list($record, 'references'); if ($references): ?><section class="record-references" aria-labelledby="refs-heading"><h2 id="refs-heading">อ้างอิง</h2><ul><?php foreach ($references as $reference): ?><li><?= e($reference) ?></li><?php endforeach; ?></ul></section><?php endif; ?><?php
$downloadUrl = download_url($record); if ($downloadUrl): $downloadLabel = trim((string) ($record['download_label'] ?? '')) ?: 'ดาวน์โหลด PDF'; $downloadSize = file_size_label($downloadUrl); ?><section class="record-download" aria-labelledby="download-heading"><h2 id="download-heading">เอกสารฉบับเต็ม</h2><p class="actions"><a class="button" href="<?= e($downloadUrl) ?>" download type="application/pdf"><?= e($downloadLabel) ?><?= $downloadSize !== '' ? ' (' . e($downloadSize) . ')' : '' ?></a></p></section><?php endif; ?></article></section></main>
<?php require __DIR__ . '/footer.php'; ?>
