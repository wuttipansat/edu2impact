<?php
// Not linked from the menu any more (see STRUCTURE.md). Kept so existing events can still be edited by typing the address.
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
$spec = [
    'title' => ['label' => 'ชื่อกิจกรรม', 'required' => true, 'max' => 400],
    'event_date' => ['type' => 'date', 'label' => 'วันที่', 'required' => true],
    'type' => ['label' => 'ชนิด (เช่น อบรม เสวนา)', 'max' => 100],
    'meta' => ['label' => 'รายละเอียดสั้นๆ (เวลา สถานที่)', 'max' => 400],
    'slug' => ['type' => 'slug', 'label' => 'ชื่อลิงก์ (ไม่บังคับ)'],
    'featured' => ['type' => 'flag', 'label' => 'กิจกรรมเด่น'],
    'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]];
$errors = []; $posted = null; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = admin_entity_post('events', $spec);
    if (!$errors) { admin_go('events.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
$rows = admin_db()->query('SELECT * FROM events ORDER BY sort_order, id')->fetchAll();
admin_head('กิจกรรม', 'events.php');
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
$newOpen = $posted !== null && !$postedId;
echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มกิจกรรมใหม่</summary><div>';
admin_entity_form('events.php', $spec, null, [], 'เพิ่มกิจกรรม', $newOpen ? $posted : null);
echo '</div></details>';
$last = count($rows) - 1;
foreach ($rows as $i => $r) { admin_entity_row('events.php', $spec, $r, $r['title'], $i === 0, $i === $last, [], $posted !== null && $postedId === (int) $r['id'] ? $posted : null, (string) $r['event_date']); }
admin_foot();
