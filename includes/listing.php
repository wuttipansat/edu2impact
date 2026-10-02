<?php
$currentPage = $kind;
$pageTitle = $heading . ' | ' . $site['name'];
$records = load_data($kind);
$q = query_string('q');
$category = query_string('category');
$categories = array_values(array_unique(array_column($records, 'category')));
$filtered = array_values(array_filter($records, function ($record) use ($q, $category) {
    $haystack = $record['title'] . ' ' . $record['summary'] . ' ' . $record['category'];
    return ($category === '' || $category === $record['category']) && ($q === '' || stripos($haystack, $q) !== false);
}));
require __DIR__ . '/header.php';
?>
<main id="main"><section class="page-hero"><div class="container"><p class="eyebrow"><?= e($eyebrow) ?></p><h1><?= e($heading) ?></h1><p>ค้นหาและสำรวจองค์ความรู้ที่คุณสนใจ</p></div></section>
<section class="section container"><form class="listing-search" method="get" action="<?= e($kind) ?>.php" role="search"><div><label for="query">คำค้นหา</label><input id="query" name="q" type="search" value="<?= e($q) ?>" placeholder="ค้นหาหัวข้อหรือคำสำคัญ" maxlength="200"></div><div><label for="category">หมวดหมู่</label><select id="category" name="category"><option value="">ทุกหมวดหมู่</option><?php foreach ($categories as $option): ?><option value="<?= e($option) ?>"<?= $option === $category ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><button class="button" type="submit">ค้นหา</button><a class="reset-link" href="<?= e($kind) ?>.php">ล้างตัวกรอง</a></form><p class="small result-count">พบ <?= count($filtered) ?> รายการ</p>
<div class="card-grid"><?php foreach ($filtered as $record) { render_card($record, $kind); } ?></div>
<?php if (!$filtered): ?><div class="empty-state"><h2>ไม่พบรายการที่ตรงกับการค้นหา</h2><p>ลองใช้คำค้นอื่น หรือเลือกทุกหมวดหมู่</p><a class="button secondary" href="<?= e($kind) ?>.php">แสดงรายการทั้งหมด</a></div><?php endif; ?>
</section></main><?php require __DIR__ . '/footer.php'; ?>
