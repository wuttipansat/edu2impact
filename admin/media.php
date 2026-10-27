<?php
require_once dirname(__DIR__) . '/includes/admin/media-lib.php';
$user = admin_boot();
$db = admin_db();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = in_str('op'); $id = in_int('id');
    try {
        if ($op === 'upload') {
            $files = $_FILES['files'] ?? null; $alt = in_line('alt', 400); $done = 0;
            if ($files && is_array($files['name'])) {
                foreach ($files['name'] as $i => $name) {
                    if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) { continue; }
                    $p = admin_media_prepare(['name' => $name, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i]]);
                    if (is_string($p)) { admin_flash(basename((string) $name) . ': ' . $p, 'error'); continue; }
                    $newId = admin_media_store($p, basename((string) $name), $alt, (int) $user['id']);
                    admin_log('media.upload', 'media#' . $newId, basename((string) $name));
                    $done++;
                }
            }
            if ($done) { admin_flash("อัปโหลดแล้ว $done ไฟล์"); }
            elseif (empty($_SESSION['flash'])) { admin_flash('ยังไม่ได้เลือกไฟล์ (หรือไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับ)', 'error'); }
        } elseif ($op === 'meta' && $id) {
            $alt = in_line('alt', 400);
            $db->prepare('UPDATE media SET alt_default = ?, original_name = ? WHERE id = ? AND og_of IS NULL')->execute([$alt !== '' ? $alt : null, in_line('original_name', 255) ?: null, $id]);
            admin_log('media.meta', 'media#' . $id); admin_flash('บันทึกแล้ว');
        } elseif ($op === 'replace' && $id) {
            $p = admin_media_prepare($_FILES['file'] ?? []);
            $cur = $db->prepare('SELECT mime FROM media WHERE id = ? AND og_of IS NULL'); $cur->execute([$id]); $old = $cur->fetchColumn();
            if (is_string($p)) { admin_flash($p, 'error'); }
            elseif (!$old) { admin_flash('ไม่พบไฟล์', 'error'); }
            elseif (($old === 'application/pdf') !== ($p['mime'] === 'application/pdf')) { admin_flash('ต้องเป็นไฟล์ชนิดเดียวกัน (รูปแทนรูป, PDF แทน PDF)', 'error'); }
            else { admin_media_replace($id, $p, basename((string) ($_FILES['file']['name'] ?? ''))); admin_log('media.replace', 'media#' . $id); admin_flash('เปลี่ยนไฟล์แล้ว ทุกที่ที่ใช้ไฟล์นี้เปลี่ยนตาม'); }
        } elseif ($op === 'delete' && $id) {
            $use = admin_media_usage();
            if (!empty($use[$id])) { admin_flash('ลบไม่ได้ ไฟล์นี้ยังถูกใช้อยู่ที่: ' . implode(', ', array_slice($use[$id], 0, 3)), 'error'); }
            else {
                $db->beginTransaction();
                $db->prepare('DELETE FROM media WHERE og_of = ?')->execute([$id]);
                $n = $db->prepare('DELETE FROM media WHERE id = ? AND og_of IS NULL'); $n->execute([$id]);
                $db->commit();
                admin_log('media.delete', 'media#' . $id); admin_flash($n->rowCount() ? 'ลบแล้ว' : 'ไม่พบไฟล์');
            }
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log('edu2impact admin media: ' . $e->getMessage());
        admin_flash('ทำรายการไม่สำเร็จ: ' . (str_contains($e->getMessage(), 'max_allowed_packet') ? 'ไฟล์ใหญ่เกินที่ฐานข้อมูลรับได้' : 'ฐานข้อมูลปฏิเสธคำสั่ง (ดูสิทธิ์ของผู้ใช้ DB)'), 'error');
    }
    admin_go('media.php' . ($id && $op !== 'delete' && $op !== 'upload' ? '?id=' . $id : ''));
}

$one = get_int('id');
if ($one) {
    $rows = array_values(array_filter(admin_media_list(), fn($r) => (int) $r['id'] === $one));
    if (!$rows) { admin_fail('ไม่พบไฟล์', 404); }
    $r = $rows[0]; $use = admin_media_usage()[$one] ?? [];
    admin_head('ไฟล์ #' . $one, 'media.php');
    echo '<p><a href="media.php">← กลับคลังรูป</a></p><div>';
    if (strpos($r['mime'], 'image/') === 0) { echo '<img class="thumb thumb--large" src="' . admin_media_img($r) . '" alt="">'; }
    else { echo '<p><a href="' . admin_media_img($r) . '" target="_blank" rel="noopener">เปิดไฟล์ PDF</a></p>'; }
    echo '<p class="hint">' . e($r['filename']) . ' · ' . e($r['mime']) . ($r['width'] ? ' · ' . (int) $r['width'] . '×' . (int) $r['height'] : '') . ' · ' . e(admin_bytes((int) $r['bytes'])) . ' · อัปโหลด ' . e(admin_time($r['created_at'])) . '</p></div>';
    admin_form_open('media.php', 'class="narrow"'); ?><input type="hidden" name="op" value="meta"><input type="hidden" name="id" value="<?= $one ?>">
    <label>ชื่อไฟล์ (เห็นเฉพาะในหลังบ้าน)<input name="original_name" value="<?= e((string) $r['original_name']) ?>" maxlength="255"></label>
    <label>คำอธิบายรูป <small>บอกว่าในรูปเห็นอะไร เพื่อให้คนที่มองไม่เห็นรูปเข้าใจ (ใช้เมื่อโพสต์ไม่ได้ใส่คำอธิบายเอง)</small><input name="alt" value="<?= e((string) $r['alt_default']) ?>" maxlength="400"></label>
    <button class="btn" type="submit">บันทึก</button></form>
    <?php admin_form_open('media.php', 'class="narrow" enctype="multipart/form-data"'); ?><input type="hidden" name="op" value="replace"><input type="hidden" name="id" value="<?= $one ?>">
    <label>เปลี่ยนเป็นไฟล์ใหม่ <small>ทุกโพสต์ที่ใช้ไฟล์นี้จะเปลี่ยนตามอัตโนมัติ</small><input type="file" name="file" accept="image/*,application/pdf" required></label><button class="btn btn--ghost" type="submit">เปลี่ยนไฟล์</button></form>
    <h2>ไฟล์นี้ถูกใช้ที่ไหนบ้าง</h2><?php if ($use): ?><ul><?php foreach ($use as $u): ?><li><?= e($u) ?></li><?php endforeach; ?></ul><?php else: ?><p>ยังไม่มีที่ไหนใช้ไฟล์นี้</p>
    <?php admin_form_open('media.php', 'data-confirm="ลบไฟล์นี้ถาวร?"'); ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= $one ?>"><button class="btn btn--danger" type="submit">ลบไฟล์</button></form><?php endif;
    admin_foot(); exit;
}

$type = in_array(get_str('type'), ['image', 'pdf'], true) ? get_str('type') : 'all';
$q = mb_substr(get_str('q'), 0, 100);
$rows = admin_media_list($type, $q);
$use = admin_media_usage();
admin_head('คลังรูป', 'media.php', ['wide' => true]);
admin_form_open('media.php', 'class="narrow" enctype="multipart/form-data"'); ?><input type="hidden" name="op" value="upload">
<label>เลือกรูปหรือ PDF (เลือกได้หลายไฟล์ ไฟล์ละไม่เกิน 8 MB) <small>ระบบย่อรูปและปรับให้เหมาะกับเว็บให้เอง</small><input type="file" name="files[]" multiple accept="image/*,application/pdf" required></label>
<label>คำอธิบายรูป (ไม่ใส่ก็ได้) <small>ใช้กับทุกไฟล์ที่เลือกครั้งนี้</small><input name="alt" maxlength="400"></label>
<button class="btn" type="submit">อัปโหลด</button></form>
<form class="toolbar" method="get"><label>ค้นหา<input type="search" name="q" value="<?= e($q) ?>"></label>
<label>ชนิด<select name="type"><option value="all">ทั้งหมด</option><option value="image"<?= $type === 'image' ? ' selected' : '' ?>>รูป</option><option value="pdf"<?= $type === 'pdf' ? ' selected' : '' ?>>PDF</option></select></label><button class="btn btn--ghost" type="submit">ค้นหา</button></form>
<p class="hint"><?= count($rows) ?> ไฟล์</p>
<div class="grid"><?php foreach ($rows as $r): $n = count($use[(int) $r['id']] ?? []); ?><figure>
<a href="media.php?id=<?= (int) $r['id'] ?>"><?php if (strpos($r['mime'], 'image/') === 0): ?><img src="<?= admin_media_img($r) ?>" alt="<?= e((string) $r['alt_default']) ?>" loading="lazy"><?php else: ?><strong>PDF</strong><?php endif; ?></a>
<figcaption>#<?= (int) $r['id'] ?> <?= e(basename($r['filename'])) ?><br><?= e(admin_bytes((int) $r['bytes'])) ?> · <?= $n ? "ใช้ $n ที่" : '<span class="tag">ยังไม่ได้ใช้</span>' ?></figcaption></figure><?php endforeach; ?></div>
<?php admin_foot();
