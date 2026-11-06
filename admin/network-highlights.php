<?php
// รูปแบบและคุณค่าความร่วมมือ: ข้อความสั้นพร้อมไอคอนสองแถบใต้แผนที่โลกบน network-international.php
// kind 'model' = รูปแบบความร่วมมือ, 'value' = คุณค่าที่เกิดกับหลักสูตรและนิสิต (เรียงลำดับแยกกันในแต่ละกลุ่ม)
require_once dirname(__DIR__) . '/includes/admin/entities.php';
require_once dirname(__DIR__) . '/includes/network-icons.php';
$user = admin_boot();
const HIGHLIGHT_KINDS = ['model' => 'รูปแบบความร่วมมือ', 'value' => 'คุณค่าที่เกิดกับหลักสูตรและนิสิต'];
$postedKind = in_str('kind');
$kind = isset(HIGHLIGHT_KINDS[$postedKind]) ? $postedKind : 'model';
$spec = [
    'kind' => ['type' => 'fixed', 'value' => $kind],
    'title_th' => ['label' => 'ข้อความภาษาไทย', 'required' => true, 'max' => 300],
    'title_en' => ['label' => 'ข้อความภาษาอังกฤษ (ถ้ามี)', 'max' => 300],
    'icon' => ['type' => 'select', 'label' => 'ไอคอน', 'options' => network_icon_options(), 'required' => true, 'hint' => 'ดูหน้าตาของไอคอนทั้งหมดได้ที่ท้ายหน้านี้'],
    'is_public' => ['type' => 'flag', 'label' => 'แสดงบนเว็บ', 'default' => 1]];
$errors = []; $posted = null; $postedId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = admin_entity_post('network_highlights', $spec, 'kind = ?', [$kind]);
    if (!$errors) { admin_go('network-highlights.php'); }
    $posted = $_POST; $postedId = in_int('id');
}
try {
    $all = admin_db()->query('SELECT * FROM network_highlights ORDER BY sort_order, id')->fetchAll();
} catch (PDOException $e) {
    admin_fail('ยังไม่มีตาราง network_highlights ให้นำเข้า database/updates/2026-11-06-02-network-highlights.sql ด้วย phpMyAdmin ก่อน');
}
admin_head('รูปแบบและคุณค่าความร่วมมือ', 'network-highlights.php');
echo '<p class="muted">สองแถบข้อความใต้แผนที่โลกในหน้า เครือข่ายวิจัยและความร่วมมือนานาชาติ (<a href="../network-international.php" target="_blank" rel="noopener">ดูบนเว็บ</a>) เรียงลำดับด้วยปุ่ม ขึ้น/ลง แยกกันในแต่ละแถบ แถบที่ไม่มีรายการแสดงอยู่เลยจะไม่ขึ้นบนเว็บ</p>';
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
foreach (HIGHLIGHT_KINDS as $k => $heading) {
    $rows = array_values(array_filter($all, static fn(array $r): bool => $r['kind'] === $k));
    echo '<h2>' . e($heading) . '</h2>';
    $newOpen = $posted !== null && !$postedId && $kind === $k;
    echo '<details' . ($newOpen ? ' open' : '') . '><summary>+ เพิ่มรายการใหม่</summary><div>';
    admin_entity_form('network-highlights.php', $spec, null, ['kind' => $k], 'เพิ่มรายการ', $newOpen ? $posted : null);
    echo '</div></details>';
    if (!$rows) { echo '<p class="muted">ยังไม่มีรายการ</p>'; }
    $last = count($rows) - 1;
    foreach ($rows as $i => $r) {
        admin_entity_row('network-highlights.php', $spec, $r, ($i + 1) . '. ' . $r['title_th'], $i === 0, $i === $last, ['kind' => $k], $posted !== null && $postedId === (int) $r['id'] && $kind === $k ? $posted : null, (string) ($r['title_en'] ?? '') . ($r['title_en'] ? ' · ' : '') . (network_icon_options()[$r['icon']] ?? 'ไอคอนไม่รู้จัก'));
    }
}
echo '<h2>ไอคอนที่เลือกได้</h2><ul class="iconlist">';
foreach (network_icon_options() as $key => $label) { echo '<li>' . network_icon_svg($key, 24) . ' ' . e($label) . '</li>'; }
echo '</ul>';
admin_foot();
