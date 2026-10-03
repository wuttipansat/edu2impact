<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'partners';
$pageTitle = 'Partners | ' . $site['name'];
$partnerGroups = load_data('partners');
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--partners">
  <section class="page-hero page-hero--partners">
    <div class="container page-hero-content partner-hero-content">
      <div><p class="eyebrow">EDU2IMPACT / PARTNERS</p><h1>Impact is a team sport.</h1><p>งานวิจัยจะมีความหมายมากขึ้นเมื่อมีผู้คนและองค์กรร่วมกันทดลอง ใช้ เรียนรู้ และขยายผล</p><div class="actions"><a class="button hero-primary" href="contact.php">Start a conversation <span aria-hidden="true">↗</span></a></div></div>
      <div class="partner-hero-mark" aria-hidden="true"><span class="orbit orbit-one"></span><span class="orbit orbit-two"></span><span class="orbit orbit-three"></span><span class="partner-mark-core">2</span></div>
    </div>
  </section>

  <section class="container partner-section">
    <div class="partner-intro-grid"><div><span class="section-kicker">A SHARED PLATFORM</span><h2>เชื่อมคนที่มีคำถาม<br>กับคนที่มีคำตอบ</h2></div><div><p class="large-copy">Edu2Impact เปิดพื้นที่ให้ผู้วิจัย สถานศึกษา หน่วยงาน และชุมชนค้นพบโอกาสทำงานร่วมกัน โดยเริ่มจากปัญหาจริงและหลักฐานที่นำไปใช้ได้</p><a class="text-link" href="research.php">สำรวจผลงานวิจัย <span aria-hidden="true">↗</span></a></div></div>

    <section class="partner-ecosystem"><div class="partner-ecosystem-core"><span>EDU</span><strong>2</strong><small>IMPACT</small></div><div class="partner-ecosystem-copy"><span class="section-kicker">WHO PARTICIPATES?</span><h2>ผู้มีส่วนร่วมในระบบนิเวศ</h2><p>EDU2IMPACT ทำหน้าที่เป็นจุดเชื่อมระหว่างผู้สร้างความรู้ ผู้ใช้ และผู้สนับสนุนการขยายผล</p><div class="participant-chips"><?php foreach ($partnerGroups as $group): ?><span><?= e($group['title']) ?></span><?php endforeach; ?></div></div></section>

    <div class="partner-type-heading"><span class="section-kicker">PARTNER PATHWAYS</span><h2>แต่ละกลุ่มมีบทบาทอย่างไร</h2><p>เลือกเส้นทางที่ตรงกับบทบาทของคุณในระบบนิเวศการศึกษา</p></div>
    <div class="partner-type-grid">
    <?php foreach ($partnerGroups as $group): ?><article class="partner-type-card"><div class="partner-card-top"><span class="partner-number"><?= e($group['number']) ?></span><span class="partner-icon" aria-hidden="true"><?= e($group['icon']) ?></span></div><span class="partner-thai"><?= e($group['thai']) ?></span><h3><?= e($group['title']) ?></h3><p><?= e($group['description']) ?></p><a class="text-link" href="contact.php">ร่วมพูดคุย <span aria-hidden="true">↗</span></a></article><?php endforeach; ?>
    </div>

    <section class="collaboration-panel"><div><span class="section-kicker">FROM INTEREST TO ACTION</span><h2>ความร่วมมือเริ่มต้นได้จากคำถามหนึ่งข้อ</h2><p>ไม่ว่าคุณจะมีงานวิจัยที่ต้องการเผยแพร่ มีบริบทที่ต้องการทดลองใช้ หรือกำลังมองหาพันธมิตรใหม่ เราสามารถเริ่มจากการพูดคุยเพื่อหาจุดเชื่อมต่อร่วมกัน</p></div><ol class="collaboration-steps"><li><span>01</span><strong>Discover</strong><small>สำรวจปัญหาและองค์ความรู้</small></li><li><span>02</span><strong>Connect</strong><small>พบผู้วิจัยและผู้ใช้ที่เกี่ยวข้อง</small></li><li><span>03</span><strong>Build</strong><small>ออกแบบการทดลองใช้ร่วมกัน</small></li></ol><a class="button" href="contact.php">Request collaboration <span aria-hidden="true">↗</span></a></section>

    <div class="demo-note"><span class="demo-note-icon">i</span><p><strong>Demo directory</strong> หน้านี้แสดงประเภทของพันธมิตรที่ระบบรองรับ ข้อมูลชื่อองค์กร โครงการ และผลงานร่วมจะเพิ่มเมื่อมีการยืนยันข้อมูลจริง</p></div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
