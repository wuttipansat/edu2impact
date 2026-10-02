<?php
$record = find_record(load_data($kind), query_string('id'));
$currentPage = $kind;
if (!$record) {
    http_response_code(404);
    $pageTitle = 'ไม่พบรายการ | ' . $site['name'];
    require __DIR__ . '/header.php';
    ?><main id="main" class="container section empty-state"><p class="eyebrow">404 · NOT FOUND</p><h1>ไม่พบรายการที่ต้องการ</h1><p>รายการอาจถูกย้ายหรือลบแล้ว กรุณาเลือกจากรายการทั้งหมด</p><a class="button" href="<?= e($kind) ?>.php">กลับไปหน้ารายการ</a></main><?php
    require __DIR__ . '/footer.php';
    return;
}
$pageTitle = $record['title'] . ' | ' . $site['name'];
$pageDescription = $record['summary'];
require __DIR__ . '/header.php';
?>
<main id="main" class="container section article-layout"><a class="text-link" href="<?= e($kind) ?>.php">← กลับไปหน้ารายการ</a><article class="article-content"><p class="eyebrow"><?= e($record['category']) ?></p><h1><?= e($record['title']) ?></h1><?php if (!empty($record['date'])): ?><p class="small">วันที่ <?= e($record['date']) ?></p><?php endif; ?><?php if (!empty($record['author'])): ?><p class="small">โดย <?= e($record['author']) ?></p><?php endif; ?><p class="lead"><?= e($record['summary']) ?></p><?php $image = image_path($record); if ($image): ?><img class="article-image" src="<?= e($image) ?>" alt="<?= e($record['title']) ?>"><?php endif; ?><div class="prose"><?php foreach ($record['body'] ?? [] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?></div></article></main>
<?php require __DIR__ . '/footer.php'; ?>
