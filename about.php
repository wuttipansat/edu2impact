<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'about'; $pageTitle = 'เกี่ยวกับเรา | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--about"><section class="page-hero page-hero--about"><div class="container page-hero-content"><p class="eyebrow">ABOUT EDU2IMPACT</p><h1>จากองค์ความรู้<br>สู่การใช้ประโยชน์</h1><p><?= e($site['description']) ?></p></div></section><section class="section container about-grid"><div class="about-intro"><span class="panel-kicker">OUR PURPOSE</span><h2>รู้จัก Edu2Impact</h2><p>Edu2Impact เป็นพื้นที่สำหรับเผยแพร่งานวิจัยและนวัตกรรมทางการศึกษา และเชื่อมโยงองค์ความรู้กับผู้ที่สนใจนำไปใช้ประโยชน์</p><p>หน้านี้เป็นข้อความตั้งต้นสำหรับพัฒนาเว็บไซต์ กรุณาเพิ่มพันธกิจ ประวัติ และรายละเอียดของหน่วยงานที่ได้รับการยืนยันก่อนเผยแพร่จริง</p></div><div class="purpose-list"><div><span>01</span><h3>รวบรวมองค์ความรู้</h3><p>นำเสนอผลงานอย่างเป็นระบบและเข้าถึงง่าย</p></div><div><span>02</span><h3>เชื่อมเครือข่าย</h3><p>สร้างช่องทางให้ผู้สนใจพบโอกาสความร่วมมือ</p></div><div><span>03</span><h3>สนับสนุนการนำไปใช้</h3><p>เชื่อมผลการศึกษากับบริบทการใช้งานจริง</p></div></div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
