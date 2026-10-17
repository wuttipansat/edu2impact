<?php
$record = find_record(load_data($kind), query_string('id'));
$currentPage = $kind;
if (!$record) {
    http_response_code(404);
    $pageTitle = 'ไม่พบรายการ | ' . $site['name'];
    require __DIR__ . '/header.php';
    ?><main id="main" class="page-shell page-shell--detail page-shell--<?= e($kind) ?>"><section class="container section empty-state"><p class="eyebrow">404 · NOT FOUND</p><h1>ไม่พบรายการที่ต้องการ</h1><p>รายการอาจถูกย้ายหรือลบแล้ว กรุณาเลือกจากรายการทั้งหมด</p><a class="button" href="<?= e($kind) ?>.php">กลับไปหน้ารายการ</a></section></main><?php
    require __DIR__ . '/footer.php';
    return;
}
$pageTitle = $record['title'] . ' | ' . $site['name'];
$pageDescription = $record['summary'];
$postStyles = true;
require __DIR__ . '/header.php';
?>
<main id="main" class="page-shell page-shell--detail page-shell--<?= e($kind) ?>"><section class="section container article-layout"><a class="text-link back-link" href="<?= e($kind) ?>.php"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg><span>กลับไปหน้ารายการ</span></a><article class="article-content"><p class="eyebrow"><?= e($record['category']) ?> / EDU2IMPACT</p><h1><?= e($record['title']) ?></h1><?php
$metaAuthor = trim((string) ($record['author'] ?? ''));
$metaDate = trim((string) ($record['date'] ?? ''));
$metaRead = reading_time($record['body'] ?? []);
$authorImage = $record['author_image'] ?? '';
$authorImage = (is_string($authorImage) && preg_match('~\Aassets/images/[a-zA-Z0-9_/-]+\.(?:png|jpg|jpeg|webp)\z~', $authorImage) && is_file(__DIR__ . '/../' . $authorImage)) ? $authorImage : '';
$initial = author_initial($metaAuthor);
if ($metaAuthor !== '' || $metaDate !== '' || $metaRead > 0): ?><div class="post-byline"><?php if ($metaAuthor !== ''): ?><div class="post-byline__item post-byline__author"><?php if ($authorImage): ?><img class="post-byline__avatar" src="<?= e($authorImage) ?>" alt="" width="40" height="40"><?php elseif ($initial !== ''): ?><span class="post-byline__avatar" aria-hidden="true"><?= e($initial) ?></span><?php else: ?><span class="post-byline__avatar" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span><?php endif; ?><span class="post-byline__text"><small>ผู้เขียน</small><strong><?= e($metaAuthor) ?></strong></span></div><?php endif; ?><?php if ($metaDate !== ''): ?><div class="post-byline__item"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/></svg><span class="visually-hidden">วันที่เผยแพร่ </span><time datetime="<?= e($metaDate) ?>"><?= e(thai_date($metaDate)) ?></time></div><?php endif; ?><?php if ($metaRead > 0): ?><div class="post-byline__item"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>อ่าน ~<?= (int) $metaRead ?> นาที</span></div><?php endif; ?></div><?php endif; ?><p class="lead"><?= e($record['summary']) ?></p><?php $image = image_path($record); if ($image): ?><img class="article-image" src="<?= e($image) ?>" alt="<?= e($record['title']) ?>"><?php endif; ?><?php $toolUrl = tool_url($record); if ($toolUrl): $external = str_starts_with($toolUrl, 'https://'); ?><p class="actions"><a class="button" href="<?= e($toolUrl) ?>"<?= $external ? ' rel="noopener"' : '' ?>><?= e(($record['tool_label'] ?? '') ?: 'เปิดใช้เครื่องมือ') ?> →</a></p><?php endif; ?><?php if (!empty($record['body'])): ?><div class="prose"><?php foreach ($record['body'] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?></div><?php endif; ?><?php
$facts = ['audience' => 'เหมาะกับ', 'subjects' => 'ใช้ได้กับกลุ่มสาระ', 'features' => 'ความสามารถหลัก'];
$hasFacts = false; foreach ($facts as $key => $label) { if (text_list($record, $key)) { $hasFacts = true; } }
if ($hasFacts): ?><dl class="record-facts"><?php foreach ($facts as $key => $label): $items = text_list($record, $key); if (!$items) { continue; } ?><div><dt><?= e($label) ?></dt><dd><ul><?php foreach ($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></dd></div><?php endforeach; ?></dl><?php endif; ?><?php
$references = text_list($record, 'references'); if ($references): ?><section class="record-references" aria-labelledby="refs-heading"><h2 id="refs-heading">อ้างอิง</h2><ul><?php foreach ($references as $reference): ?><li><?= e($reference) ?></li><?php endforeach; ?></ul></section><?php endif; ?></article></section></main>
<?php require __DIR__ . '/footer.php'; ?>
