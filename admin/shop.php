<?php
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
$spec = [
    'title' => ['label' => 'ชื่อสินค้า', 'required' => true, 'max' => 400],
    'slug' => ['type' => 'slug', 'label' => 'ชื่อลิงก์ (ภาษาอังกฤษ ตัวเลข และ - เท่านั้น)', 'required' => true],
    'summary' => ['type' => 'textarea', 'label' => 'รายละเอียด'],
    'price' => ['label' => 'ราคา', 'hint' => 'ใส่ตัวเลข เช่น 350 หรือข้อความ เช่น ติดต่อสอบถาม', 'max' => 100],
    'image_id' => ['type' => 'image', 'label' => 'รูป'],
    'order_url' => ['type' => 'url', 'label' => 'ลิงก์สั่งซื้อ (https://…)'],
    'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]];
$errors = []; $posted = null; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = admin_entity_post('shop_items', $spec);
    if (!$errors) { admin_go('shop.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
$rows = admin_db()->query('SELECT * FROM shop_items ORDER BY sort_order, id')->fetchAll();
admin_head('ร้านค้า', 'shop.php');
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
$newOpen = $posted !== null && !$postedId;
echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มสินค้าใหม่</summary><div>';
admin_entity_form('shop.php', $spec, null, [], 'เพิ่มสินค้า', $newOpen ? $posted : null);
echo '</div></details>';
if (!$rows) { echo '<p class="muted">ยังไม่มีสินค้า</p>'; }
$last = count($rows) - 1;
foreach ($rows as $i => $r) { admin_entity_row('shop.php', $spec, $r, $r['title'], $i === 0, $i === $last, [], $posted !== null && $postedId === (int) $r['id'] ? $posted : null, (string) $r['price']); }
admin_foot();
