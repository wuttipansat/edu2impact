<?php
// นำเข้าข้อมูลเครือข่ายวิจัยนานาชาติ: ชุดข้อมูล (zip หรือ JSON กับรูป) และย้ายรูปจากโฟลเดอร์เว็บเข้าคลังรูป
// The work is in includes/admin/network-import.php. Admins only: it adds rows and pictures in bulk.
require_once dirname(__DIR__) . '/includes/admin/entities.php';
require_once dirname(__DIR__) . '/includes/admin/network-import.php';
$user = admin_boot(['admin']);
$notReady = network_import_ready();
$report = null; $folder = null; $errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $notReady === '') {
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'package') {
        [$json, $pictures, $errors] = network_import_collect();
        if (!$errors && $json === null) { $errors[] = 'ไม่พบไฟล์ network-import.json ในสิ่งที่อัปโหลด'; }
        if (!$errors) { $report = network_import_package($json, $pictures); $report['pictures_sent'] = count($pictures); }
    } elseif ($op === 'folder') {
        $folder = network_import_folder();
    }
}
$left = $notReady === '' ? network_import_folder_rows() : [];
$limit = min(admin_ini_bytes('upload_max_filesize'), admin_ini_bytes('post_max_size'));
admin_head('นำเข้าข้อมูลเครือข่าย', 'network-import.php');
if ($notReady !== '') { echo '<p class="notice notice--error" role="alert">' . e($notReady) . '</p>'; admin_foot(); exit; }
foreach ($errors as $err) { echo '<p class="notice notice--error" role="alert">' . e($err) . '</p>'; }

if ($report !== null) {
    $lines = [
        'สถาบันใหม่ ' . $report['partners_new'] . ' แห่ง / เติมข้อมูลที่ว่าง ' . $report['partners_filled'] . ' แห่ง / ใส่รูปการ์ด ' . $report['cards'] . ' แห่ง',
        'กิจกรรมใหม่ ' . $report['activities_new'] . ' รายการ',
        'รูปเข้าคลังรูป: เพิ่มใหม่ ' . $report['photos_new'] . ' / ย้ายจากโฟลเดอร์ ' . $report['photos_moved'] . ' / อัปเดต ' . $report['photos_updated'] . ' (ได้รับรูปในรอบนี้ ' . $report['pictures_sent'] . ' ไฟล์)',
    ];
    echo '<div class="notice" role="status"><strong>นำเข้าเสร็จ</strong><ul><li>' . implode('</li><li>', array_map('e', $lines)) . '</li></ul>';
    if ($report['waiting']) { echo '<p>ยังไม่ได้รับรูป ' . count($report['waiting']) . ' ไฟล์ อัปโหลดชุดถัดไปได้เลย ข้อมูลที่นำเข้าแล้วจะไม่ซ้ำ</p>'; }
    echo '</div>';
    foreach ($report['errors'] as $err) { echo '<p class="notice notice--error">' . e($err) . '</p>'; }
}
if ($folder !== null) {
    [$moved, $missing, $folderErrors] = $folder;
    echo '<p class="notice" role="status">ย้ายรูปเข้าคลังรูปแล้ว ' . (int) $moved . ' รูป' . ($left ? ' เหลืออีก ' . count($left) . ' รูป กดปุ่มอีกครั้งเพื่อทำต่อ' : ' ครบแล้ว') . '</p>';
    if ($missing) { echo '<p class="notice notice--error">ไม่พบไฟล์ในโฟลเดอร์ ' . count($missing) . ' รูป เช่น ' . e(implode(', ', array_slice($missing, 0, 3))) . ' ใช้การนำเข้าชุดข้อมูลด้านล่างแทนได้</p>'; }
    foreach ($folderErrors as $err) { echo '<p class="notice notice--error">' . e($err) . '</p>'; }
}
?>
<h2>ย้ายรูปจากโฟลเดอร์เข้าคลังรูป</h2>
<p>รูปการ์ดและรูปกิจกรรมที่ใส่ด้วยไฟล์ SQL ยังอ้างไฟล์ในโฟลเดอร์ <code>assets/images/network/</code> ย้ายเข้าคลังรูปแล้วจะแก้ เปลี่ยน หรือลบได้จากหน้า admin และติดไปกับไฟล์สำรองข้อมูล</p>
<?php if ($left): ?>
<p>ยังอยู่ในโฟลเดอร์ <strong><?= count($left) ?></strong> รูป ทำทีละ <?= NETWORK_IMPORT_FOLDER_BATCH ?> รูปต่อการกดหนึ่งครั้ง เพื่อไม่ให้เซิร์ฟเวอร์ทำงานนานเกินไป</p>
<?php admin_form_open('network-import.php'); ?><input type="hidden" name="op" value="folder"><button class="btn" type="submit">ย้ายรูปเข้าคลังรูป</button></form>
<?php else: ?>
<p class="muted">ไม่มีรูปที่อ้างไฟล์ในโฟลเดอร์แล้ว ลบโฟลเดอร์ <code>assets/images/network/</code> บนเซิร์ฟเวอร์ได้ถ้าต้องการ</p>
<?php endif; ?>

<h2>นำเข้าชุดข้อมูล</h2>
<p>ชุดข้อมูลคือไฟล์ <code>network-import.json</code> กับรูปของมัน ส่งเป็น zip ไฟล์เดียว หรือเลือกไฟล์ JSON พร้อมรูปทั้งหมดก็ได้ ชุดใหญ่แบ่งส่งหลายรอบได้ แต่ละรอบใช้ JSON ตัวเดิม</p>
<ul class="hint">
  <li>สถาบันที่ยังไม่มีจะถูกเพิ่ม สถาบันที่มีแล้วจะเติมเฉพาะช่องที่ยังว่าง ข้อมูลที่แก้ในหน้า admin ไว้แล้วไม่ถูกทับ</li>
  <li>กิจกรรมที่มีชื่อซ้ำกับของสถาบันเดิมจะไม่ถูกเพิ่มซ้ำ รูปเดียวกันเก็บในคลังรูปครั้งเดียว</li>
  <li>เซิร์ฟเวอร์นี้รับการส่งได้ครั้งละไม่เกิน <strong><?= e(number_format($limit / 1048576, 1)) ?> MB</strong><?= class_exists('ZipArchive') ? '' : ' และแตกไฟล์ zip ไม่ได้ ให้ใช้ช่องเลือกหลายไฟล์' ?></li>
</ul>
<?php admin_form_open('network-import.php', 'class="narrow" enctype="multipart/form-data"'); ?>
  <input type="hidden" name="op" value="package">
<?php if (class_exists('ZipArchive')): ?>
  <label>ไฟล์ zip<input type="file" name="package" accept=".zip,application/zip"></label>
  <p class="hint">หรือ</p>
<?php endif; ?>
  <label>ไฟล์ JSON และรูป (เลือกได้หลายไฟล์)<input type="file" name="files[]" multiple accept=".json,application/json,image/webp,image/jpeg,image/png"></label>
  <button class="btn" type="submit">นำเข้า</button>
</form>
<?php admin_foot();
