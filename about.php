<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'about'; $pageTitle = 'About | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--about"><?php render_page_hero('about'); ?><section class="container editorial-canvas about-canvas"><div class="about-definition"><span class="section-kicker">WHAT IS EDU2IMPACT?</span><h2>แพลตฟอร์มที่เชื่อมงานวิจัยกับการนำไปใช้จริง</h2><p>EDU2IMPACT คือพื้นที่กลางสำหรับค้นพบผลงานวิจัย เข้าใจหลักฐาน และเชื่อมต่อกับนักวิจัยหรือพันธมิตรที่สามารถนำความรู้นั้นไปทดลองใช้ในบริบทจริง</p></div></section></main><?php require __DIR__ . '/includes/footer.php'; ?>
