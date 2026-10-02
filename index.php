<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'home';
$pageTitle = 'Edu2Impact | From Research to Use, Scale & Impact';
$innovations = load_data('innovations');
require __DIR__ . '/includes/header.php';
?>
<main id="main">
<section class="home-hero hero-slider" data-slider aria-label="Edu2Impact highlights">
  <div class="hero-slides">
    <article class="hero-slide hero-slide-home is-active" data-slide="0">
      <div class="home-hero-overlay"></div><div class="container home-hero-inner">
        <div class="hero-copy"><p class="eyebrow">WELCOME TO EDU2IMPACT</p><h1>From Research<br>to <em>Real-World</em><br>Impact</h1><p class="lead"><?= e($site['description']) ?></p><div class="actions"><a class="button hero-primary" href="explore-impact.php">Explore Impact</a><a class="button hero-outline" href="innovations.php">Find Innovation</a></div><div class="hero-stats"><div><strong><?= e(count($innovations)) ?></strong><span>Innovations in portfolio</span></div><div><strong>—</strong><span>Verified impact records</span></div><div><strong>—</strong><span>Partner organizations</span></div></div></div>
      </div>
    </article>
    <article class="hero-slide hero-slide-impact" data-slide="1">
      <div class="home-hero-overlay"></div><div class="container home-hero-inner">
        <div class="hero-copy"><p class="eyebrow">EXPLORE IMPACT</p><h1>Research That<br>Creates <em>Real Change</em></h1><p class="lead">ติดตามการนำงานวิจัยและนวัตกรรมไปใช้ในโรงเรียน ชุมชน และหน่วยงานต่าง ๆ พร้อมหลักฐานที่ตรวจสอบได้</p><div class="actions"><a class="button hero-primary" href="explore-impact.php">Explore Impact</a><a class="button hero-outline" href="impact-stories.php">View Impact Stories</a></div></div>
      </div>
    </article>
    <article class="hero-slide hero-slide-innovation" data-slide="2">
      <div class="home-hero-overlay"></div><div class="container home-hero-inner">
        <div class="hero-copy"><p class="eyebrow">INNOVATION PORTFOLIO</p><h1>Ideas Ready<br>for <em>Practical Use</em></h1><p class="lead">ค้นพบนวัตกรรมจากงานวิจัย พร้อมข้อมูลปัญหา ผู้ใช้ หลักฐาน และโอกาสในการนำไปทดลองใช้หรือขยายผล</p><div class="actions"><a class="button hero-primary" href="innovations.php">Find Innovation</a></div></div>
      </div>
    </article>
    <article class="hero-slide hero-slide-partners" data-slide="3">
      <div class="home-hero-overlay"></div><div class="container home-hero-inner">
        <div class="hero-copy"><p class="eyebrow">PARTNERS &amp; USERS</p><h1>Build Impact<br><em>Together</em></h1><p class="lead">เชื่อมโยงนักวิจัย ผู้ใช้ หน่วยงานภาครัฐ ชุมชน มหาวิทยาลัย และภาคเอกชน เพื่อเปลี่ยนความรู้ให้เกิดการใช้จริง</p><div class="actions"><a class="button hero-primary" href="partners.php">Meet Our Partners</a><a class="button hero-outline" href="contact.php">Start a Conversation</a></div></div>
      </div>
    </article>
    <article class="hero-slide hero-slide-resources" data-slide="4">
      <div class="home-hero-overlay"></div><div class="container home-hero-inner">
        <div class="hero-copy"><p class="eyebrow">RESEARCH &amp; RESOURCES</p><h1>Knowledge<br>You Can <em>Use</em></h1><p class="lead">เข้าถึงงานวิจัย หลักฐาน และทรัพยากรที่ช่วยให้การตัดสินใจ การทดลองใช้ และการขยายผลทำได้อย่างมีข้อมูล</p><div class="actions"><a class="button hero-primary" href="research.php">Explore Research</a><a class="button hero-outline" href="resources.php">Browse Resources</a></div></div>
      </div>
    </article>
  </div>
  <button class="hero-arrow hero-arrow-prev" type="button" data-slider-prev aria-label="สไลด์ก่อนหน้า">‹</button><button class="hero-arrow hero-arrow-next" type="button" data-slider-next aria-label="สไลด์ถัดไป">›</button>
  <div class="hero-slider-controls" role="tablist" aria-label="เลือกหัวข้อ Hero"><button class="hero-dot is-active" type="button" role="tab" aria-selected="true" aria-label="Home" data-slider-dot="0"><span>Home</span></button><button class="hero-dot" type="button" role="tab" aria-selected="false" aria-label="Impact" data-slider-dot="1"><span>Impact</span></button><button class="hero-dot" type="button" role="tab" aria-selected="false" aria-label="Innovation" data-slider-dot="2"><span>Innovation</span></button><button class="hero-dot" type="button" role="tab" aria-selected="false" aria-label="Partners" data-slider-dot="3"><span>Partners</span></button><button class="hero-dot" type="button" role="tab" aria-selected="false" aria-label="Resources" data-slider-dot="4"><span>Resources</span></button></div>
</section>
</main><?php require __DIR__ . '/includes/footer.php'; ?>
