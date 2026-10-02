<?php
$pages = require __DIR__ . '/../config/pages.php';
$page = $pages[$currentPage];
$pageTitle = $page['title'] . ' | ' . $site['name'];
require __DIR__ . '/header.php';
?>
<main id="main"><section class="page-hero"><div class="container"><p class="eyebrow">EDU2IMPACT</p><h1><?= e($page['title']) ?></h1><p><?= e($page['intro']) ?></p></div></section>
<section class="section container">
<?php if (!empty($page['stages'])): ?><ol class="journey" aria-label="ลำดับขั้นตอน"><?php foreach ($page['stages'] as $stage): ?><li><?= e($stage) ?></li><?php endforeach; ?></ol><?php endif; ?>
<?php foreach ($page['sections'] ?? [] as $section): ?><section class="content-panel"><h2><?= e($section[0]) ?></h2><p><?= e($section[1]) ?></p></section><?php endforeach; ?>
<nav class="section-links" aria-label="หน้าที่เกี่ยวข้อง"><?php foreach ($page['links'] ?? [] as $url => $label): ?><a class="text-link" href="<?= e($url) ?>"><?= e($label) ?> ↗</a><?php endforeach; ?></nav>
</section></main><?php require __DIR__ . '/footer.php'; ?>
