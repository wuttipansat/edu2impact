<?php
// กิจกรรมกับสถาบันในเครือข่ายวิจัยนานาชาติ: หนึ่งแถวต่อหนึ่งกิจกรรม แสดงเป็นไทม์ไลน์บน network-partner.php
// รูปของกิจกรรมเพิ่มที่ admin/network-photos.php (เลือกกิจกรรมในช่อง "กิจกรรม")
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
try {
    $partners = admin_db()->query('SELECT id, institution FROM network_partners ORDER BY sort_order, id')->fetchAll();
} catch (PDOException $e) {
    admin_fail('ยังไม่มีตาราง network_partners ให้นำเข้า database/updates/2026-11-06-01-network.sql ด้วย phpMyAdmin ก่อน');
}
$partnerOptions = [];
foreach ($partners as $p) { $partnerOptions[(string) $p['id']] = $p['institution']; }
$spec = [
    'partner_id' => ['type' => 'select', 'label' => 'สถาบัน', 'options' => $partnerOptions, 'required' => true],
    'period' => ['label' => 'ช่วงเวลา', 'max' => 100, 'hint' => 'เขียนแบบที่จะแสดงบนเว็บ เช่น 15–22 พ.ย. 2568 หรือ ก.ย. ทุกปี เว้นว่างได้ถ้าไม่ทราบ'],
    'title' => ['label' => 'ชื่อกิจกรรม', 'required' => true, 'max' => 200, 'hint' => 'สั้น ๆ ไม่เกินหนึ่งบรรทัด แสดงบนรูปเมื่อชี้และเป็นหัวข้อในหน้าสถาบัน'],
    'body' => ['type' => 'textarea', 'label' => 'รายละเอียด', 'hint' => 'เล่าว่าใครทำอะไร ที่ไหน ด้วยทุนอะไร'],
    'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]];
$errors = []; $posted = null; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = admin_entity_post('network_activities', $spec);
    if (!$errors) { admin_go('network-activities.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
try {
    $rows = admin_db()->query('SELECT a.*, p.institution, (SELECT COUNT(*) FROM network_photos ph WHERE ph.activity_id = a.id) AS photos FROM network_activities a JOIN network_partners p ON p.id = a.partner_id ORDER BY a.sort_order, a.id')->fetchAll();
} catch (PDOException $e) {
    admin_fail('ยังไม่มีตาราง network_activities ให้นำเข้า database/updates/2026-11-16-05-network-activities.sql ด้วย phpMyAdmin ก่อน');
}
admin_head('กิจกรรมกับเครือข่าย', 'network-activities.php');
echo '<p class="muted">แต่ละแถวคือหนึ่งกิจกรรมกับสถาบันในเครือข่าย แสดงเป็นไทม์ไลน์ในหน้าของสถาบันนั้น และการ์ดสถาบันจะมีลิงก์ "ดูกิจกรรมทั้งหมด" ใส่รูปของกิจกรรมได้ที่ <a href="network-photos.php">ภาพกิจกรรมกับเครือข่าย</a></p>';
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
$newOpen = $posted !== null && !$postedId;
echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มกิจกรรมใหม่</summary><div>';
admin_entity_form('network-activities.php', $spec, null, [], 'เพิ่มกิจกรรม', $newOpen ? $posted : null);
echo '</div></details>';
if (!$rows) { echo '<p class="muted">ยังไม่มีกิจกรรม</p>'; }
$last = count($rows) - 1;
foreach ($rows as $i => $r) {
    $sub = $r['institution'] . ($r['period'] ? ' / ' . $r['period'] : '') . ' / รูป ' . (int) $r['photos'];
    admin_entity_row('network-activities.php', $spec, $r, $r['title'], $i === 0, $i === $last, [], $posted !== null && $postedId === (int) $r['id'] ? $posted : null, $sub);
}
admin_foot();
