<?php
// Streams a gzip SQL backup of the content tables (no user accounts). Import it with phpMyAdmin > Import.
require_once dirname(__DIR__) . '/includes/admin/core.php';
$user = admin_boot(['admin']);
$db = admin_db();
const BACKUP_TABLES = ['media', 'posts', 'post_links', 'home_items', 'shop_items', 'events', 'site_settings', 'thai_breaks', 'post_revisions', 'admin_meta'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    session_write_close();
    @set_time_limit(0);
    while (ob_get_level() > 0) { ob_end_clean(); }
    $name = 'e2imedu-' . date('Ymd-His') . '.sql.gz';
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');
    $z = deflate_init(ZLIB_ENCODING_GZIP);
    $out = function (string $s, int $flag = ZLIB_NO_FLUSH) use ($z) { echo deflate_add($z, $s, $flag); };
    $out("-- KUEDU2IMPACT content backup " . date('Y-m-d H:i:s') . "\n-- Import: phpMyAdmin > e2imedu > Import. Uses REPLACE INTO, so it overwrites rows with the same id and never drops a table.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $rows = 0;
    foreach (BACKUP_TABLES as $t) {
        $cols = [];
        $c = $db->query("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION");
        foreach ($c->fetchAll() as $col) { $cols[$col['COLUMN_NAME']] = $col['DATA_TYPE']; }
        if (!$cols) { continue; }
        $out("-- $t\n");
        $q = $db->query('SELECT `' . implode('`,`', array_keys($cols)) . "` FROM `$t`");
        $prefix = "REPLACE INTO `$t` (`" . implode('`,`', array_keys($cols)) . '`) VALUES ';
        while ($r = $q->fetch(PDO::FETCH_NUM)) {
            $vals = []; $i = 0;
            foreach ($cols as $type) {
                $v = $r[$i++];
                if ($v === null) { $vals[] = 'NULL'; }
                elseif (in_array($type, ['blob', 'longblob', 'mediumblob', 'tinyblob', 'binary', 'varbinary'], true)) { $vals[] = $v === '' ? "''" : '0x' . bin2hex($v); }
                elseif (in_array($type, ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'], true)) { $vals[] = (string) (int) $v; }
                else { $vals[] = $db->quote((string) $v); }
            }
            $out($prefix . '(' . implode(',', $vals) . ");\n"); $rows++;
        }
        $q->closeCursor();
        $out("\n");
    }
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    $out("SET FOREIGN_KEY_CHECKS=1;\n-- end\n", ZLIB_FINISH);
    admin_meta_set('last_backup', date('Y-m-d H:i:s'));
    exit;
}
$last = admin_meta_get('last_backup');
admin_head('สำรองข้อมูล', 'backup.php');
?>
<p>ได้ไฟล์ <code>.sql.gz</code> ที่มีโพสต์ รูป PDF หน้าแรก ร้านค้า และตั้งค่าทั้งหมด <strong>ไม่รวมบัญชีผู้ใช้และรหัสผ่าน</strong></p>
<p>สำรองล่าสุด: <strong><?= e($last ? admin_time($last) : 'ยังไม่เคย') ?></strong></p>
<?php admin_form_open('backup.php'); ?><button class="btn btn--big" type="submit">ดาวน์โหลดไฟล์สำรอง</button></form>
<h2>ถ้าต้องการนำกลับ</h2>
<p class="hint">เปิด phpMyAdmin → เลือกฐานข้อมูล e2imedu → แท็บ Import → เลือกไฟล์นี้ (ไม่ต้องแตกไฟล์) ข้อมูลเดิมที่ตรงกันจะถูกเขียนทับ ไม่มีอะไรถูกลบ ถ้าไฟล์ใหญ่เกินที่ phpMyAdmin รับ ให้ใช้ Export ของ phpMyAdmin แทน</p>
<?php admin_foot();
