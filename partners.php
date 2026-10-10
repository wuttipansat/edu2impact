<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'partners'; $pageTitle = 'Partners | ' . $site['name'];
$groups = require __DIR__ . '/config/partner-groups.php';
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--partners">
  <?php render_page_hero('partners'); ?>
  <section class="container editorial-canvas partners-canvas"><div class="page-heading"><div><span class="section-kicker">PARTNER NETWORK</span><h2>ผู้มีส่วนร่วม</h2></div><span class="item-count">6 groups</span></div><p class="section-intro">เครือข่ายของ EDU2IMPACT ครอบคลุมผู้สร้างความรู้ ผู้ใช้ความรู้ และผู้สนับสนุนการขยายผล</p><div class="partner-grid"><?php foreach ($groups as $index => $group): ?><article class="partner-card" id="partner-<?= $index + 1 ?>"><span class="partner-number">0<?= $index + 1 ?></span><span class="partner-thai"><?= e($group[1]) ?></span><h3><?= e($group[0]) ?></h3><p><?= e($group[2]) ?></p></article><?php endforeach; ?></div></section>
</main><?php require __DIR__ . '/includes/footer.php'; ?>
