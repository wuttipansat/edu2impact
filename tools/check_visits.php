<?php
// Checks that the visit counter can work on this server. Run from the project folder:  php tools/check_visits.php
// Prints one line per check and what to do about anything that fails. Exit code 1 if something is wrong.
require __DIR__ . '/../includes/visits.php';

$problems = 0;
$report = static function (bool $ok, string $label, string $fix = '') use (&$problems): void {
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . "\n";
    if (!$ok) { $problems++; if ($fix !== '') { echo '      วิธีแก้: ' . $fix . "\n"; } }
};

$file = visits_file();
$dir = dirname($file);
$report(is_dir($dir), 'โฟลเดอร์ storage/ มีอยู่', 'สร้างโฟลเดอร์ ' . $dir . ' (mkdir storage) แล้วให้ผู้ใช้ที่รัน PHP เขียนได้');
$report(is_dir($dir) && is_writable($dir), 'PHP เขียนในโฟลเดอร์ storage/ ได้', 'chmod 775 storage และให้เจ้าของเป็นผู้ใช้เดียวกับ PHP (เช่น www-data หรือ apache)');
if (is_file($file)) {
    $json = json_decode((string) @file_get_contents($file), true);
    $report(is_array($json), 'อ่าน visits.json ได้และเป็น JSON ที่ถูกต้อง', 'ลบไฟล์ ' . $file . ' แล้วให้ระบบสร้างใหม่ (ยอดจะเริ่มจาก 0)');
    $report(is_writable($file), 'PHP เขียน visits.json ได้', 'chmod 664 ' . $file);
} else {
    echo "note  ยังไม่มี visits.json (จะถูกสร้างเมื่อมีผู้เข้าชมครั้งแรก)\n";
}
$local = __DIR__ . '/../config/visits.local.php';
$config = is_file($local) ? require $local : null;
$hasLocal = is_array($config) && is_string($config['secret'] ?? null) && strlen($config['secret']) >= 16;
$hasAuto = is_file($dir . '/.visits-secret') && strlen(trim((string) @file_get_contents($dir . '/.visits-secret'))) >= 32;
$report($hasLocal || $hasAuto, 'ตั้ง secret แล้ว (config/visits.local.php หรือ storage/.visits-secret)', 'คัดลอก config/visits.example.php เป็น config/visits.local.php แล้วใส่ข้อความสุ่มยาวอย่างน้อย 16 ตัวอักษร');
if (!$hasLocal && $hasAuto) { echo "note  ใช้ secret ที่สร้างอัตโนมัติใน storage/.visits-secret (ควรตั้งใน config/visits.local.php เพื่อไม่ให้หายตอน deploy)\n"; }
$report(is_file($dir . '/.htaccess'), 'มี storage/.htaccess กันการเปิดไฟล์ผ่านเว็บ (Apache)', 'ถ้าใช้ Nginx ให้เพิ่ม location /storage { deny all; } ในค่าตั้งเซิร์ฟเวอร์');
$siteUrl = trim((string) (load_site_url_for_check() ?? ''));
echo $siteUrl !== '' ? "ok    site_url ตั้งแล้ว: $siteUrl\n" : "note  ยังไม่ได้ตั้ง site_url ใน data/site.json (ปุ่มแชร์ใช้ที่อยู่ของหน้าที่เปิดอยู่ แต่รูปตัวอย่างตอนแชร์จะไม่ขึ้น)\n";

echo $problems === 0 ? "\nพร้อมใช้งาน\n" : "\nพบ $problems ข้อที่ต้องแก้ ตัวนับจะข้ามการนับไปจนกว่าจะแก้\n";
exit($problems === 0 ? 0 : 1);

function load_site_url_for_check(): ?string {
    $data = json_decode((string) @file_get_contents(__DIR__ . '/../data/site.json'), true);
    return is_array($data) && is_string($data['site_url'] ?? null) ? $data['site_url'] : null;
}
