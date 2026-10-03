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
<main id="main" class="page-shell page-shell--detail page-shell--<?= e($kind) ?>"><section class="section container article-layout"><a class="text-link back-link" href="<?= e($kind) ?>.php">← กลับไปหน้ารายการ</a><article class="article-content"><p class="eyebrow"><?= e($record['category']) ?> / EDU2IMPACT</p><h1><?= e($record['title']) ?></h1><?php if (!empty($record['date'])): ?><p class="small">วันที่ <?= e($record['date']) ?></p><?php endif; ?><?php if (!empty($record['author'])): ?><p class="small">โดย <?= e($record['author']) ?></p><?php endif; ?><p class="lead"><?= e($record['summary']) ?></p><?php $image = image_path($record); if ($image): ?><img class="article-image" src="<?= e($image) ?>" alt="<?= e($record['title']) ?>"><?php endif; ?><div class="prose"><?php foreach ($record['body'] ?? [] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?></div><?php if ($kind === 'innovations'): ?>
<section><h2>Innovation Profile</h2><ol class="journey"><li>Problem</li><li>Innovation</li><li>Evidence</li><li>Adoption</li><li>Scale</li><li>Impact</li></ol>
<div class="profile-grid">
<?php foreach (['problem'=>'Problem Addressed', 'target_users'=>'Target Users', 'evidence'=>'Research Evidence', 'readiness'=>'Readiness Level', 'adoption'=>'Adoption & Scale', 'impact'=>'Impact', 'ip_status'=>'IP / Enterprise Status'] as $field => $label): ?>
<section class="content-panel"><h3><?= e($label) ?></h3><p><?= e($record[$field] ?? 'ยังไม่มีข้อมูลที่เผยแพร่') ?></p></section>
<?php endforeach; ?></div>
<div class="actions"><a class="button" href="request-use.php?id=<?= e(rawurlencode($record['id'])) ?>">Use / Pilot / Collaborate / License</a><a class="button secondary" href="resources.php">Resources & Downloads</a></div></section>
<?php endif; ?></article></section></main>
<?php require __DIR__ . '/footer.php'; ?>
