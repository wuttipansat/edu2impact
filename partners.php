<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'partners'; $pageTitle = 'Partners | ' . $site['name'];
$groups = [
    ['Schools & Teachers','โรงเรียนและครู','ทดลองใช้ความรู้ในห้องเรียนและพัฒนาการเรียนรู้ร่วมกัน'],
    ['Government','หน่วยงานภาครัฐ','เชื่อมหลักฐานกับนโยบายและระบบการศึกษา'],
    ['Communities','ชุมชน','ร่วมออกแบบการใช้ความรู้ให้เหมาะกับบริบทพื้นที่'],
    ['Universities','มหาวิทยาลัยและนักวิจัย','เผยแพร่ผลงานและต่อยอดความร่วมมือ'],
    ['Private Sector','ภาคเอกชน','สนับสนุนการพัฒนาและขยายผลนวัตกรรม'],
    ['International Network','เครือข่ายนานาชาติ','แลกเปลี่ยนความรู้และโอกาสข้ามบริบท']
];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--partners">
  <section class="page-hero page-hero--editorial"><div class="container page-hero-content"><p class="eyebrow">EDU2IMPACT / PARTNERS</p><h1>Partners that move research forward.</h1><p>เห็นภาพชัดเจนว่าใครมีส่วนร่วมกับการทำให้งานวิจัยถูกนำไปใช้และขยายผล</p></div></section>
  <section class="container editorial-canvas partners-canvas"><div class="page-heading"><div><span class="section-kicker">PARTNER NETWORK</span><h2>ผู้มีส่วนร่วม</h2></div><span class="item-count">6 groups</span></div><p class="section-intro">เครือข่ายของ EDU2IMPACT ครอบคลุมผู้สร้างความรู้ ผู้ใช้ความรู้ และผู้สนับสนุนการขยายผล</p><div class="partner-grid"><?php foreach ($groups as $index => $group): ?><article class="partner-card"><span class="partner-number">0<?= $index + 1 ?></span><span class="partner-thai"><?= e($group[1]) ?></span><h3><?= e($group[0]) ?></h3><p><?= e($group[2]) ?></p></article><?php endforeach; ?></div></section>
</main><?php require __DIR__ . '/includes/footer.php'; ?>
