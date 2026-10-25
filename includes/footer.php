<?php if (($currentPage ?? '') !== 'contact'): $closing = hero_text('contact'); ?><section class="closing-cta" aria-labelledby="closing-cta-title"><div class="container closing-cta__inner"><h2 id="closing-cta-title"><?= e($closing['title']) ?></h2><p><?= thai_text($closing['intro']) ?></p><a class="closing-cta__link" href="contact.php">ติดต่อเรา</a></div></section><?php endif; ?>
<?php
// Sitemap columns come from config/navigation.php so the footer always matches the header menu.
// Contact details are shown only when site.json has them.
$footerGroups = array_values(array_filter($nav, static fn (array $item): bool => !empty($item['children'])));
$footerEmail = filter_var($site['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $site['email'] : '';
$footerPhone = trim((string) ($site['phone'] ?? ''));
$footerAddress = trim((string) ($site['address'] ?? ''));
?><footer class="footer">
<div class="container footer-main">
<div class="footer-top-row">
<div class="footer-intro"><a class="brand footer-brand" href="index.php" aria-label="<?= e($site['name']) ?> หน้าแรก">kuedu<span>2</span>impact</a></div>
<?php if ($footerEmail !== '' || $footerPhone !== '' || $footerAddress !== ''): ?><ul class="footer-contact-list"><?php if ($footerAddress !== ''): ?><li><?= e($footerAddress) ?></li><?php endif; ?><?php if ($footerPhone !== ''): ?><li><?= e($footerPhone) ?></li><?php endif; ?><?php if ($footerEmail !== ''): ?><li><?= e($footerEmail) ?></li><?php endif; ?></ul><?php endif; ?>
</div>
<nav class="footer-nav" aria-label="แผนผังเว็บไซต์"><?php foreach ($footerGroups as $group): ?><div class="footer-col"><h2 class="footer-col__title"><?= e($group['label']) ?></h2><ul class="footer-links"><?php foreach ($group['children'] as $child): ?><li><a href="<?= e($child['file']) ?>"<?= ($navKey ?? $currentPage ?? '') === $child['key'] ? ' aria-current="page"' : '' ?>><?= e($child['label']) ?></a></li><?php endforeach; ?></ul></div><?php endforeach; ?></nav>
</div>
<div class="footer-bar"><div class="container footer-bar__inner"><span class="footer-copy">© <?= date('Y') ?> <?= e($site['name']) ?>, <?= e($site['university']) ?></span></div></div>
</footer>
<a class="to-top" href="#top" aria-label="กลับขึ้นด้านบน"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg></a>
</body></html>
