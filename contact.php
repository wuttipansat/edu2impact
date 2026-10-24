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
<main id="main" class="page-shell page-shell--contact"><section class="page-hero page-hero--contact"><div class="container page-hero-content"><?php $hero = hero_text('contact'); ?><p class="eyebrow"><?= e($hero['eyebrow']) ?></p><h1><?= e($hero['title']) ?></h1><p><?= thai_text($hero['intro']) ?></p></div></section><section class="section container contact-section" aria-labelledby="contact-details-heading">
  <h2 id="contact-details-heading" class="visually-hidden">ช่องทางติดต่อ</h2>
  <?php if ($hasEmail || $phone !== '' || $address !== ''): ?>
  <ul class="contact-cards">
    <?php if ($phone !== ''): ?>
    <li class="contact-card">
      <span class="contact-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></span>
      <span class="contact-card__label">โทรศัพท์</span>
      <a class="contact-card__value contact-card__link" href="tel:<?= e($phoneHref) ?>"><?= e($phone) ?></a>
    </li>
    <?php endif; ?>
    <?php if ($hasEmail): ?>
    <li class="contact-card">
      <span class="contact-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></span>
      <span class="contact-card__label">อีเมล</span>
      <a class="contact-card__value contact-card__link" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
    </li>
    <?php endif; ?>
    <?php if ($address !== ''): ?>
    <li class="contact-card">
      <span class="contact-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
      <span class="contact-card__label">ที่อยู่</span>
      <a class="contact-card__value contact-card__link" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($address)) ?>" target="_blank" rel="noopener"><?= e($address) ?></a>
    </li>
    <?php endif; ?>
  </ul>
  <?php else: ?>
  <p class="contact-empty">ยังไม่ได้เพิ่มอีเมลและเบอร์โทรศัพท์ในเว็บไซต์ ระหว่างนี้ติดต่อผ่านคณะศึกษาศาสตร์ มหาวิทยาลัยเกษตรศาสตร์ได้</p>
  <?php endif; ?>
</section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
