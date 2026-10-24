<?php
// Shop: products from data/shop.json (id, title, summary, image, price, order_url, public).
// Products stay in the JSON file, not MySQL, until the posts table gets a price column.
require __DIR__ . '/includes/bootstrap.php';
$currentPage = 'shop';
$pageTitle = 'ร้านค้า | ' . $site['name'];
$listingStyles = true;
$products = load_data('shop');
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="page-shell page-shell--shop">
  <?php render_page_hero('shop'); ?>
  <section class="container editorial-canvas editorial-canvas--shop">
<?php if ($products): ?>
    <div class="page-heading"><div><h2>สินค้าทั้งหมด</h2></div><span class="item-count"><strong><?= count($products) ?></strong> รายการ</span></div>
    <div class="shop-grid">
<?php foreach ($products as $product): $price = shop_price($product['price'] ?? ''); $orderUrl = shop_order_url($product); ?>
      <article class="shop-card">
        <div class="shop-card__media"><img src="<?= e(image_path($product) ?: 'assets/images/content-placeholder.png') ?>" alt="" loading="lazy"></div>
        <div class="shop-card__body">
          <h3><?= e($product['title'] ?? '') ?></h3>
<?php if (trim((string) ($product['summary'] ?? '')) !== ''): ?>          <p class="shop-card__summary"><?= e($product['summary']) ?></p>
<?php endif; ?>
          <div class="shop-card__foot">
<?php if ($price !== ''): ?>            <p class="shop-card__price"><?= e($price) ?></p>
<?php endif; ?>
<?php if ($orderUrl !== ''): ?>            <a class="button" href="<?= e($orderUrl) ?>" target="_blank" rel="noopener noreferrer">สั่งซื้อ<span class="visually-hidden"> <?= e($product['title'] ?? '') ?> (เปิดในแท็บใหม่)</span></a>
<?php else: ?>            <a class="button" href="contact.php">สอบถามการสั่งซื้อ<span class="visually-hidden"> <?= e($product['title'] ?? '') ?></span></a>
<?php endif; ?>
          </div>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-guide">
      <h2>สินค้ากำลังจะมาเร็วๆ นี้</h2>
      <p>เรากำลังจัดชุดสื่อและอุปกรณ์ที่พัฒนาจากงานวิจัยและนวัตกรรม หากสนใจเป็นพิเศษ แจ้งทีมงานได้เลย</p>
      <p class="actions"><a class="button" href="contact.php">ติดต่อเรา</a></p>
    </div>
<?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
