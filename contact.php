<?php
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'contact'; $pageTitle = 'ติดต่อเรา | ' . $site['name'];
require __DIR__ . '/includes/header.php';
$email = $site['email'] ?? '';
$hasEmail = (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
$phone = trim((string) ($site['phone'] ?? ''));
$phoneHref = preg_replace('/[^\d+]/', '', $phone);
$address = trim((string) ($site['address'] ?? ''));
?>
<main id="main" class="page-shell page-shell--contact"><section class="page-hero page-hero--contact"><div class="container page-hero-content"><p class="eyebrow">CONNECT WITH US</p><h1>เริ่มต้นความร่วมมือใหม่</h1><p>สำหรับสถานศึกษา นักวิจัย และหน่วยงานที่สนใจ</p></div></section><section class="section container contact-section" aria-labelledby="contact-details-heading">
  <h2 id="contact-details-heading" class="visually-hidden">ช่องทางติดต่อ</h2>
  <ul class="contact-cards">
    <li class="contact-card">
      <span class="contact-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></span>
      <span class="contact-card__label">อีเมล</span>
      <?php if ($hasEmail): ?><a class="contact-card__value contact-card__link" href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php else: ?><span class="contact-card__value contact-card__value--pending">รอเพิ่มอีเมลของหน่วยงาน</span><?php endif; ?>
    </li>
    <li class="contact-card">
      <span class="contact-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></span>
      <span class="contact-card__label">โทรศัพท์</span>
      <?php if ($phone !== ''): ?><a class="contact-card__value contact-card__link" href="tel:<?= e($phoneHref) ?>"><?= e($phone) ?></a><?php else: ?><span class="contact-card__value contact-card__value--pending">รอเพิ่มหมายเลขโทรศัพท์</span><?php endif; ?>
    </li>
    <li class="contact-card">
      <span class="contact-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
      <span class="contact-card__label">ที่อยู่</span>
      <?php if ($address !== ''): ?><a class="contact-card__value contact-card__link" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($address)) ?>" target="_blank" rel="noopener"><?= e($address) ?></a><?php else: ?><span class="contact-card__value contact-card__value--pending">รอเพิ่มที่อยู่ของหน่วยงาน</span><?php endif; ?>
    </li>
  </ul>
</section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
