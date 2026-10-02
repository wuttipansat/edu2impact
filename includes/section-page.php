<?php
$pages = require __DIR__ . '/../config/pages.php';
$page = $pages[$currentPage];
$pageTitle = $page['title'] . ' | ' . $site['name'];
require __DIR__ . '/header.php';
?>
<main id="main"><section class="page-hero"><div class="container"><p class="eyebrow">EDU2IMPACT</p><h1><?= e($page['title']) ?></h1><p><?= e($page['intro']) ?></p></div></section>
<section class="section container">
<?php if (!empty($page['stages'])): ?><ol class="journey" aria-label="ลำดับขั้นตอน"><?php foreach ($page['stages'] as $stage): ?><li><?= e($stage) ?></li><?php endforeach; ?></ol><?php endif; ?>
<?php if (!empty($page['private'])): ?>
<div class="content-panel"><h2>ส่วนงานนี้กำลังพัฒนา</h2><p>คุณเข้าสู่ระบบแล้ว แต่แบบฟอร์มบันทึก Impact การตรวจสอบผลงาน และ Dashboard ยังไม่เปิดให้บริการ กรุณาติดต่อฝ่ายวิจัยหากต้องการแจ้งการนำผลงานไปใช้</p><a class="button" href="contact.php">ติดต่อฝ่ายวิจัย</a></div>
<?php if ($currentPage === 'admin' && $accountUser['role'] === 'admin'): ?><p><a class="button" href="admin-users.php">จัดการบัญชีผู้ใช้</a></p><?php endif; ?>
<?php else: ?>
<?php foreach ($page['sections'] ?? [] as $section): ?><section class="content-panel"><h2><?= e($section[0]) ?></h2><p><?= e($section[1]) ?></p></section><?php endforeach; ?>
<nav class="section-links" aria-label="หน้าที่เกี่ยวข้อง"><?php foreach ($page['links'] ?? [] as $url => $label): ?><a class="text-link" href="<?= e($url) ?>"><?= e($label) ?> ↗</a><?php endforeach; ?></nav>
<?php endif; ?>
</section></main><?php require __DIR__ . '/footer.php'; ?>
