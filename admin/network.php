<?php
// เครือข่ายวิจัยและความร่วมมือนานาชาติ: หนึ่งแถวต่อหนึ่งสถาบัน แสดงบน network-international.php (แผนที่โลก)
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
$countries = require dirname(__DIR__) . '/config/countries.php';
$options = array_map(static fn(array $c): string => $c[0], $countries);
$spec = [
    'country_code' => ['type' => 'select', 'label' => 'ประเทศ', 'options' => $options, 'required' => true],
    'institution' => ['label' => 'ชื่อสถาบันหรือหน่วยงาน', 'required' => true, 'max' => 300],
    'institution_th' => ['label' => 'ชื่อภาษาไทย (ถ้ามี)', 'max' => 300],
    'description' => ['type' => 'textarea', 'label' => 'รายละเอียดความร่วมมือ'],
    'url' => ['type' => 'url', 'label' => 'เว็บไซต์ (https://…)'],
    'related' => ['type' => 'posts', 'kinds' => ['research', 'innovations'], 'label' => 'งานวิจัยหรือนวัตกรรมที่เกี่ยวข้อง', 'hint' => 'ไม่บังคับ แสดงเป็นการ์ดลิงก์ในแผงรายละเอียดของประเทศนี้'],
    'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]];
$derive = static fn(array $v): array => $v + ['country_name_th' => $countries[$v['country_code']][0]];
$errors = []; $posted = null; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = admin_entity_post('network_partners', $spec, '1=1', [], $derive);
    if (!$errors) { admin_go('network.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
try {
    $rows = admin_db()->query('SELECT * FROM network_partners ORDER BY sort_order, id')->fetchAll();
} catch (PDOException $e) {
    admin_fail('ยังไม่มีตาราง network_partners ให้นำเข้า database/updates/2026-11-06-01-network.sql ด้วย phpMyAdmin ก่อน');
}
admin_head('เครือข่ายวิจัยและความร่วมมือนานาชาติ', 'network.php');
echo '<p class="muted">แต่ละแถวคือหนึ่งสถาบัน ประเทศหนึ่งเพิ่มได้หลายสถาบัน ประเทศที่มีรายการจะถูกระบายสีบนแผนที่โลก (<a href="../network-international.php" target="_blank" rel="noopener">ดูบนเว็บ</a>)</p>';
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
$newOpen = $posted !== null && !$postedId;
echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มสถาบันใหม่</summary><div>';
admin_entity_form('network.php', $spec, null, [], 'เพิ่มสถาบัน', $newOpen ? $posted : null);
echo '</div></details>';
if (!$rows) { echo '<p class="muted">ยังไม่มีรายการ</p>'; }
$last = count($rows) - 1;
foreach ($rows as $i => $r) { admin_entity_row('network.php', $spec, $r, $r['institution'], $i === 0, $i === $last, [], $posted !== null && $postedId === (int) $r['id'] ? $posted : null, (string) ($countries[$r['country_code']][0] ?? $r['country_name_th'])); }
admin_foot();
