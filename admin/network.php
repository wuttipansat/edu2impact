<?php
// เครือข่ายวิจัยและความร่วมมือนานาชาติ: หนึ่งแถวต่อหนึ่งสถาบัน แสดงบน network-international.php (แผนที่โลก)
require_once dirname(__DIR__) . '/includes/admin/entities.php';
$user = admin_boot();
$countries = require dirname(__DIR__) . '/config/countries.php';
require_once dirname(__DIR__) . '/includes/network.php';
foreach (NETWORK_NO_MAP as $code => $label) { $countries[$code] = [$label[0], $label[1], '']; } // AsTEN: a card, not a place on the map
$options = array_map(static fn(array $c): string => $c[0], $countries);
foreach (NETWORK_NO_MAP as $code => $label) { $options[$code] = $label[1] . ' (ไม่ปักบนแผนที่)'; }
$tagOptions = require dirname(__DIR__) . '/config/network-tags.php';
$spec = [
    'country_code' => ['type' => 'select', 'label' => 'ประเทศ', 'options' => $options, 'required' => true],
    'institution' => ['label' => 'ชื่อสถาบันหรือหน่วยงาน', 'required' => true, 'max' => 300],
    'institution_th' => ['label' => 'ชื่อภาษาไทย (ถ้ามี)', 'max' => 300],
    'tags' => ['type' => 'tags', 'label' => 'รูปแบบความร่วมมือ', 'options' => $tagOptions, 'hint' => 'เลือกได้หลายแบบ แสดงเป็นแท็กบนการ์ดและใช้กรองรายการบนเว็บ'],
    'summary' => ['label' => 'สรุปหนึ่งประโยค', 'max' => 200, 'hint' => "หนึ่งประโยค บอกชื่อโครงการ ชื่อคน หรือปี อย่าใช้คำกว้าง ๆ อย่าง 'เครือข่ายวิชาการ'"],
    'people' => ['label' => 'ผู้ประสานงานหรือนักวิชาการ', 'max' => 300, 'hint' => 'คั่นชื่อด้วยจุลภาค เช่น Prof. Dr. Anthony Clarke, Prof. Dr. Samia Khan'],
    'description' => ['type' => 'textarea', 'label' => 'รายละเอียดเพิ่มเติม', 'hint' => 'หนึ่งบรรทัดต่อหนึ่งข้อ บนเว็บแสดงไม่เกิน 3 ข้อแรก'],
    'url' => ['type' => 'url', 'label' => 'เว็บไซต์ (https://…)'],
    'image_id' => ['type' => 'image', 'label' => 'รูปประกอบการ์ด', 'hint' => 'ไม่บังคับ แสดงเต็มฝั่งซ้ายของการ์ด ใช้รูปแนวตั้งหรือจัตุรัส เช่น อาคาร บรรยากาศกิจกรรม ถ้าไม่ใส่จะเป็นพื้นม่วงเข้ม'],
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
if ($rows && (!array_key_exists('tags', $rows[0]) || !array_key_exists('image_id', $rows[0]))) {
    admin_fail('ยังไม่มีคอลัมน์ tags, summary, people, image_id ให้นำเข้า database/updates/2026-11-16-01-network-tags.sql ด้วย phpMyAdmin ก่อน');
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
