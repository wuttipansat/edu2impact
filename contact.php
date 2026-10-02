<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'contact'; $pageTitle = 'ติดต่อเรา | ' . $site['name'];
require __DIR__ . '/includes/header.php';
$email = $site['email'] ?? '';
?>
<main id="main"><section class="page-hero"><div class="container"><p class="eyebrow">CONNECT WITH US</p><h1>เริ่มต้นความร่วมมือใหม่</h1><p>สำหรับสถานศึกษา นักวิจัย และหน่วยงานที่สนใจ</p></div></section><section class="section container about-grid"><div><h2><?= e($site['name']) ?></h2><p><?= e($site['university']) ?></p><p>ติดต่อเพื่อสอบถามข้อมูลเกี่ยวกับงานวิจัย นวัตกรรม และโอกาสในการทำงานร่วมกัน</p></div><div class="contact-box"><h3>ช่องทางติดต่อ</h3><dl><dt>อีเมล</dt><dd><?php if (filter_var($email, FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php else: ?>รอเพิ่มอีเมลของหน่วยงาน<?php endif; ?></dd><dt>โทรศัพท์</dt><dd><?= e(!empty($site['phone']) ? $site['phone'] : 'รอเพิ่มหมายเลขโทรศัพท์') ?></dd><dt>ที่อยู่</dt><dd><?= e(!empty($site['address']) ? $site['address'] : 'รอเพิ่มที่อยู่ของหน่วยงาน') ?></dd></dl></div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
