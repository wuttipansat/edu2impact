<?php
// นวัตกรรมสู่ชุมชน: หนึ่งแถวต่อหนึ่งโครงการ แสดงบน network-community.php (แผนที่ประเทศไทย)
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
$provinces = require dirname(__DIR__) . '/config/thai-provinces.php';
$options = array_map(static fn(array $p): string => $p[0], $provinces);
asort($options, SORT_LOCALE_STRING);
$spec = [
    'province_code' => ['type' => 'select', 'label' => 'จังหวัด', 'options' => $options, 'required' => true],
    'title' => ['label' => 'ชื่อโครงการ', 'required' => true, 'max' => 400],
    'community' => ['label' => 'ชุมชนหรือหน่วยงานที่ร่วมงาน', 'max' => 300],
    'summary' => ['type' => 'textarea', 'label' => 'สรุปสั้นๆ'],
    'project_year' => ['type' => 'int', 'label' => 'ปี พ.ศ. (เช่น 2568)', 'min' => 2500, 'max' => 2700],
    'image_id' => ['type' => 'image', 'label' => 'รูป'],
    'related' => ['type' => 'posts', 'kinds' => ['innovations', 'workshops'], 'label' => 'นวัตกรรมหรือเวิร์กช็อปที่เกี่ยวข้อง', 'hint' => 'ไม่บังคับ แสดงเป็นการ์ดลิงก์ในแผงรายละเอียดของจังหวัดนี้'],
    'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]];
$derive = static fn(array $v): array => $v + ['province_name_th' => $provinces[$v['province_code']][0]];
$errors = []; $posted = null; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = admin_entity_post('community_projects', $spec, '1=1', [], $derive);
    if (!$errors) { admin_go('community.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
try {
    $rows = admin_db()->query('SELECT * FROM community_projects ORDER BY sort_order, id')->fetchAll();
} catch (PDOException $e) {
    admin_fail('ยังไม่มีตาราง community_projects ให้นำเข้า database/updates/2026-11-06-01-network.sql ด้วย phpMyAdmin ก่อน');
}
admin_head('นวัตกรรมสู่ชุมชน', 'community.php');
echo '<p class="muted">แต่ละแถวคือหนึ่งโครงการ จังหวัดหนึ่งเพิ่มได้หลายโครงการ จังหวัดที่มีรายการจะถูกระบายสีบนแผนที่ประเทศไทย (<a href="../network-community.php" target="_blank" rel="noopener">ดูบนเว็บ</a>)</p>';
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
$newOpen = $posted !== null && !$postedId;
echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มโครงการใหม่</summary><div>';
admin_entity_form('community.php', $spec, null, [], 'เพิ่มโครงการ', $newOpen ? $posted : null);
echo '</div></details>';
if (!$rows) { echo '<p class="muted">ยังไม่มีรายการ</p>'; }
$last = count($rows) - 1;
foreach ($rows as $i => $r) { admin_entity_row('community.php', $spec, $r, $r['title'], $i === 0, $i === $last, [], $posted !== null && $postedId === (int) $r['id'] ? $posted : null, (string) ($provinces[$r['province_code']][0] ?? $r['province_name_th'])); }
admin_foot();
