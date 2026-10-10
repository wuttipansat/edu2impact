<?php // Shown by every listing layout when the current search/filter has no results. Expects $kind, $q, $category. ?>
<div class="listing-empty" role="status">
  <h2><?= $q !== '' ? 'ไม่พบรายการที่ตรงกับ “' . e($q) . '”' : 'ไม่พบรายการในหมวดนี้' ?></h2>
  <p>ลองใช้คำที่สั้นลง ตรวจการสะกด หรือเลือกหมวดหมู่อื่น</p>
  <a class="button" href="<?= e($kind) ?>.php">ล้างตัวกรอง</a>
</div>
