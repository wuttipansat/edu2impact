<?php
// ภาพกิจกรรมกับเครือข่าย: แถบรูปที่เลื่อนต่อเนื่องใต้การ์ดสถาบันบน network-international.php (หนึ่งแถวต่อหนึ่งรูป)
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
try {
    admin_db()->query('SELECT activity_id, in_strip FROM network_photos LIMIT 0');
    $activities = admin_db()->query('SELECT a.id, a.title, p.institution FROM network_activities a JOIN network_partners p ON p.id = a.partner_id ORDER BY p.sort_order, p.id, a.sort_order, a.id')->fetchAll();
} catch (PDOException $e) {
    admin_fail('ยังไม่มีตาราง network_activities หรือคอลัมน์ activity_id, in_strip ให้นำเข้า database/updates/2026-11-16-05-network-activities.sql ด้วย phpMyAdmin ก่อน');
}
$activityOptions = [];
foreach ($activities as $a) { $activityOptions[(string) $a['id']] = $a['institution'] . ': ' . $a['title']; }
$activityTitle = static fn ($id): string => $activityOptions[(string) $id] ?? '';
$spec = [
    'image_id' => ['type' => 'image', 'label' => 'รูป', 'required' => true, 'hint' => 'รูปแนวนอนหรือแนวตั้งก็ได้ แถบจะจัดความสูงให้เท่ากัน'],
    'activity_id' => ['type' => 'select', 'label' => 'กิจกรรม', 'options' => $activityOptions, 'hint' => 'รูปนี้มาจากกิจกรรมไหน เมื่อชี้รูปในแถบจะขึ้นชื่อกิจกรรมและสถาบัน และรูปจะไปอยู่ในหน้าของสถาบันนั้นด้วย'],
    'caption' => ['label' => 'คำบรรยายภาพ', 'required' => true, 'max' => 300, 'hint' => 'บอกว่าเป็นกิจกรรมอะไร กับสถาบันไหน เช่น นิสิตแลกเปลี่ยนจาก Chiba University ทำกิจกรรมที่โรงเรียนสาธิต มก. ใช้เป็นข้อความแทนภาพสำหรับผู้ใช้โปรแกรมอ่านหน้าจอ'],
    'in_strip' => ['type' => 'flag', 'label' => 'แสดงในแถบภาพเลื่อน', 'default' => 1, 'hint' => 'เอาออกสำหรับโปสเตอร์ ภาพหน้าจอ หรือภาพที่มีตัวหนังสือมาก รูปจะยังอยู่ในหน้าของสถาบัน'],
    'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]];
$errors = []; $posted = null; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = admin_entity_post('network_photos', $spec);
    if (!$errors) { admin_go('network-photos.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
try {
    $rows = admin_db()->query('SELECT * FROM network_photos ORDER BY sort_order, id')->fetchAll();
} catch (PDOException $e) {
    admin_fail('ยังไม่มีตาราง network_photos ให้นำเข้า database/updates/2026-11-16-03-network-photos.sql ด้วย phpMyAdmin ก่อน');
}
admin_head('ภาพกิจกรรมกับเครือข่าย', 'network-photos.php');
echo '<p class="muted">รูปในแถบที่เลื่อนต่อเนื่องใต้การ์ดสถาบันในหน้า เครือข่ายวิจัยและความร่วมมือนานาชาติ (<a href="../network-international.php" target="_blank" rel="noopener">ดูบนเว็บ</a>) ควรมีอย่างน้อย 6 รูปในแถบเพื่อให้เต็มจอ ถ้ามี 10 รูปขึ้นไปจะแบ่งเป็นสองแถวที่เลื่อนสวนทางกัน รูปที่ผูกกับกิจกรรมจะแสดงในหน้าของสถาบันด้วย</p>';
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
$newOpen = $posted !== null && !$postedId;
echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มรูปใหม่</summary><div>';
admin_entity_form('network-photos.php', $spec, null, [], 'เพิ่มรูป', $newOpen ? $posted : null);
echo '</div></details>';
if (!$rows) { echo '<p class="muted">ยังไม่มีรูป</p>'; }
$last = count($rows) - 1;
foreach ($rows as $i => $r) {
    $sub = trim(($r['activity_id'] ? $activityTitle($r['activity_id']) : 'ยังไม่ผูกกับกิจกรรม') . ((int) $r['in_strip'] === 1 ? '' : ' / ไม่อยู่ในแถบภาพเลื่อน'));
    admin_entity_row('network-photos.php', $spec, $r, $r['caption'], $i === 0, $i === $last, [], $posted !== null && $postedId === (int) $r['id'] ? $posted : null, $sub);
}
admin_foot();
