<?php
require_once dirname(__DIR__) . '/includes/admin/core.php';
$user = admin_boot(['admin']);
$db = admin_db();
$fields = ['name' => ['ชื่อเว็บ', 200], 'university' => ['หน่วยงาน', 200], 'tagline' => ['คำขวัญ', 300], 'description' => ['คำอธิบายเว็บ (แสดงในผลค้นหา Google)', 600],
    'email' => ['อีเมลติดต่อ', 254], 'phone' => ['โทรศัพท์', 100], 'address' => ['ที่อยู่', 500], 'site_url' => ['ที่อยู่เว็บ เช่น https://www.edu2impact.edu.ku.ac.th (ไม่ต้องมี / ท้าย)', 200]];
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_str('op') === 'breaks') {
    $text = isset($_FILES['sql']['tmp_name']) && is_uploaded_file($_FILES['sql']['tmp_name']) && filesize($_FILES['sql']['tmp_name']) < 8388608 ? (string) file_get_contents($_FILES['sql']['tmp_name']) : '';
    $n = preg_match_all("/\\(\\s*'([0-9a-f]{40})'\\s*,\\s*'(\\[[0-9,]*\\])'\\s*\\)/", $text, $m, PREG_SET_ORDER);
    if (!$n) { admin_flash('ไม่พบข้อมูลตัดคำในไฟล์ (ต้องเป็นไฟล์จาก tools/segment_thai.py --sql)', 'error'); }
    else {
        $up = $db->prepare('INSERT INTO thai_breaks (hash, offsets) VALUES (?, ?) ON DUPLICATE KEY UPDATE offsets = VALUES(offsets)');
        $db->beginTransaction(); foreach ($m as $row) { $up->execute([$row[1], $row[2]]); } $db->commit();
        admin_log('thai_breaks.import', 'thai_breaks', $n . ' rows'); admin_flash("นำเข้า $n ข้อความแล้ว");
    }
    admin_go('settings.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $new = [];
    foreach ($fields as $k => [$label, $max]) { $new[$k] = in_line($k, $max); }
    if ($new['name'] === '') { $errors[] = 'ชื่อเว็บจำเป็นต้องกรอก'; }
    if ($new['email'] !== '' && !filter_var($new['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'อีเมลไม่ถูกต้อง'; }
    if ($new['site_url'] !== '' && !preg_match('~\Ahttps://[^\s/]+(?:/[^\s?#]*)?\z~', $new['site_url'])) { $errors[] = 'ที่อยู่เว็บต้องขึ้นต้นด้วย https://'; }
    $new['demo_mode'] = in_flag('demo_mode') ? 'true' : 'false';
    if (!$errors) {
        $db->beginTransaction();
        $old = $db->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $up = $db->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $changed = [];
        foreach ($new as $k => $v) { if (($old[$k] ?? null) !== $v) { $up->execute([$k, $k === "site_url" ? rtrim($v, "/") : $v]); $changed[] = $k; } }
        $db->commit();
        admin_log('settings.update', 'site_settings', implode(', ', $changed));
        admin_flash($changed ? 'บันทึกแล้ว' : 'ไม่มีอะไรเปลี่ยน');
        admin_go('settings.php');
    }
}
$cur = $db->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
admin_head('ตั้งค่าเว็บ', 'settings.php');
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }
admin_form_open('settings.php', 'class="narrow"');
foreach ($fields as $k => [$label, $max]) { echo '<label>' . e($label) . '<input name="' . $k . '" value="' . e((string) ($cur[$k] ?? '')) . '" maxlength="' . $max . '"></label>'; }
echo '<label class="check"><input type="checkbox" name="demo_mode" value="1"' . (($cur['demo_mode'] ?? '') === 'true' ? ' checked' : '') . '> แสดงป้ายว่าเว็บนี้เป็นข้อมูลตัวอย่าง</label><button class="btn" type="submit">บันทึก</button></form>';

// Thai line breaks: the SQL file made by tools/segment_thai.py is read as text and its rows are inserted. It is never executed.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || in_str('op') !== 'breaks') {
    echo '<details class="more-details"><summary>ตัดคำภาษาไทย (ไม่บังคับ)</summary><div><p class="hint">ปกติไม่ต้องทำ ถ้าอยากให้ข้อความขึ้นบรรทัดใหม่ตามคำ ให้รัน <code>python3 tools/segment_thai.py --sql</code> บนเครื่องแล้วอัปโหลดไฟล์ที่ได้ (ตอนนี้มี ' . (int) $db->query('SELECT COUNT(*) FROM thai_breaks')->fetchColumn() . ' ข้อความ)</p>';
    admin_form_open('settings.php', 'class="narrow" enctype="multipart/form-data"');
    echo '<input type="hidden" name="op" value="breaks"><label>ไฟล์ thai-breaks.sql<input type="file" name="sql" accept=".sql,text/plain" required></label><button class="btn btn--ghost" type="submit">นำเข้า</button></form></div></details>';
}
admin_foot();
