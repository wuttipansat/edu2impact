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
require __DIR__ . '/header.php';
?>
<main id="main" class="page-shell page-shell--detail page-shell--<?= e($kind) ?>"><section class="section container article-layout"><a class="text-link back-link" href="<?= e($kind) ?>.php">← กลับไปหน้ารายการ</a><article class="article-content"><p class="eyebrow"><?= e($record['category']) ?> / EDU2IMPACT</p><h1><?= e($record['title']) ?></h1><?php if (!empty($record['date'])): ?><p class="small">วันที่ <?= e($record['date']) ?></p><?php endif; ?><?php if (!empty($record['author'])): ?><p class="small">โดย <?= e($record['author']) ?></p><?php endif; ?><p class="lead"><?= e($record['summary']) ?></p><?php $image = image_path($record); if ($image): ?><img class="article-image" src="<?= e($image) ?>" alt="<?= e($record['title']) ?>"><?php endif; ?><?php $toolUrl = tool_url($record); if ($toolUrl): $external = str_starts_with($toolUrl, 'https://'); ?><p class="actions"><a class="button" href="<?= e($toolUrl) ?>"<?= $external ? ' rel="noopener"' : '' ?>><?= e(($record['tool_label'] ?? '') ?: 'เปิดใช้เครื่องมือ') ?> →</a></p><?php endif; ?><?php if (!empty($record['body'])): ?><div class="prose"><?php foreach ($record['body'] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?></div><?php endif; ?><?php
$facts = ['audience' => 'เหมาะกับ', 'subjects' => 'ใช้ได้กับกลุ่มสาระ', 'features' => 'ความสามารถหลัก'];
$hasFacts = false; foreach ($facts as $key => $label) { if (text_list($record, $key)) { $hasFacts = true; } }
if ($hasFacts): ?><dl class="record-facts"><?php foreach ($facts as $key => $label): $items = text_list($record, $key); if (!$items) { continue; } ?><div><dt><?= e($label) ?></dt><dd><ul><?php foreach ($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></dd></div><?php endforeach; ?></dl><?php endif; ?><?php
$references = text_list($record, 'references'); if ($references): ?><section class="record-references" aria-labelledby="refs-heading"><h2 id="refs-heading">อ้างอิง</h2><ul><?php foreach ($references as $reference): ?><li><?= e($reference) ?></li><?php endforeach; ?></ul></section><?php endif; ?></article></section></main>
<?php require __DIR__ . '/footer.php'; ?>
