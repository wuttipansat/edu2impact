<?php
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
$sections = [
    'promo' => ['ประชาสัมพันธ์ (แบนเนอร์หน้าแรก)', 'หัวข้อ', [
        'type' => ['type' => 'fixed', 'value' => 'promo'],
        'title' => ['label' => 'หัวข้อ', 'required' => true, 'max' => 400],
        'item_date' => ['type' => 'date', 'label' => 'วันที่'],
        'label' => ['label' => 'ป้ายสั้นๆ เช่น ข่าว หรือ ประกาศ', 'max' => 100],
        'image_id' => ['type' => 'image', 'label' => 'รูป'],
        'url' => ['type' => 'url', 'label' => 'ลิงก์เมื่อคลิก (https://… ถ้ายังไม่มีให้ใส่ #)'],
        'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]]],
    'video' => ['Scoop (วิดีโอ)', 'หัวข้อ', [
        'type' => ['type' => 'fixed', 'value' => 'video'],
        'title' => ['label' => 'หัวข้อ', 'required' => true, 'max' => 400],
        'item_date' => ['type' => 'date', 'label' => 'วันที่'],
        'youtube' => ['type' => 'youtube', 'label' => 'ลิงก์ YouTube', 'required' => true],
        'image_id' => ['type' => 'image', 'label' => 'รูปหน้าปก', 'hint' => 'ไม่ใส่ก็ได้ ระบบจะใช้ภาพจาก YouTube'],
        'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]]],
];
$errors = []; $posted = null; $postedKind = ''; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedKind = isset($sections[in_str('kind')]) ? in_str('kind') : 'promo';
    $errors = admin_entity_post('home_items', $sections[$postedKind][2], 'type = ?', [$postedKind]);
    if (!$errors) { admin_go('home.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
$db = admin_db();
admin_head('แบนเนอร์หน้าแรก', 'home.php');
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
foreach ($sections as $kind => [$name, , $spec]) {
    $st = $db->prepare('SELECT * FROM home_items WHERE type = ? ORDER BY sort_order, id'); $st->execute([$kind]); $rows = $st->fetchAll();
    $hidden = ['kind' => $kind];
    echo '<h2 class="section">' . e($name) . '</h2>';
    $newOpen = $posted !== null && $postedKind === $kind && !$postedId;
    echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มรายการใหม่</summary><div>';
    admin_entity_form('home.php', $spec, null, $hidden, 'เพิ่มรายการ', $newOpen ? $posted : null);
    echo '</div></details>';
    if (!$rows) { echo '<p class="muted">ยังไม่มีรายการ</p>'; }
    $last = count($rows) - 1;
    foreach ($rows as $i => $r) {
        $mine = $posted !== null && $postedKind === $kind && $postedId === (int) $r['id'];
        admin_entity_row('home.php', $spec, $r, $r['title'], $i === 0, $i === $last, $hidden, $mine ? $posted : null, (string) ($r['item_date'] ?? ''));
    }
}
admin_foot();
