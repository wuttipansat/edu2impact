<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'about';
$pageTitle = 'About | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--about">
  <section class="page-hero page-hero--about">
    <div class="container page-hero-content about-hero-content"><p class="eyebrow">ABOUT EDU2IMPACT</p><h1>From research<br>to <em>real-world</em> impact.</h1><p>พื้นที่กลางสำหรับทำให้งานวิจัย องค์ความรู้ และผู้คนที่พร้อมนำไปใช้พบกันได้ง่ายขึ้น</p></div>
  </section>

  <section class="container about-section about-section--compact">
    <div class="about-definition"><div><span class="section-kicker">WHAT IS EDU2IMPACT?</span><h2>แพลตฟอร์มที่เชื่อมงานวิจัยกับการนำไปใช้จริง</h2></div><p class="large-copy">EDU2IMPACT ช่วยให้ผู้คนค้นพบผลงานวิจัย เข้าใจหลักฐาน และเชื่อมต่อกับนักวิจัยหรือพันธมิตรที่สามารถนำความรู้นั้นไปทดลองใช้ในบริบทจริง</p></div>
    <div class="about-points"><article><span>01</span><h3>Discover</h3><p>ค้นหางานวิจัย ข่าวสาร และองค์ความรู้ที่เกี่ยวข้อง</p></article><article><span>02</span><h3>Connect</h3><p>เชื่อมผู้วิจัย โรงเรียน หน่วยงาน ชุมชน และภาคเอกชน</p></article><article><span>03</span><h3>Move forward</h3><p>มองเห็นเส้นทางจากความรู้ไปสู่การทดลองใช้และผลกระทบ</p></article></div>
    <div class="about-demo-note"><span class="placeholder-badge">DEMO WEB APP</span><p>เว็บไซต์รุ่นนี้เป็นต้นแบบสำหรับทดลองโครงสร้างข้อมูลและประสบการณ์ใช้งาน ก่อนเชื่อมต่อข้อมูลจริงที่ผ่านการตรวจสอบ</p><div class="about-cta-links"><a href="research.php"><span>Explore Research ↗</span></a><a href="partners.php"><span>Meet Participants ↗</span></a></div></div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
