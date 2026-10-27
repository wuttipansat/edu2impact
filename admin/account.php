<?php
require_once dirname(__DIR__) . '/includes/admin/core.php';
$user = admin_boot();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $stmt = admin_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = (string) $stmt->fetchColumn();
    $new = auth_post('password');
    if (!password_verify(auth_post('current'), $hash)) { admin_flash('รหัสผ่านปัจจุบันไม่ถูกต้อง', 'error'); }
    elseif (!auth_password_valid($new)) { admin_flash('รหัสผ่านใหม่ต้องยาว 12–72 ตัวอักษร', 'error'); }
    elseif ($new !== auth_post('password2')) { admin_flash('รหัสผ่านใหม่สองช่องไม่ตรงกัน', 'error'); }
    else {
        $newHash = admin_password_hash($new);
        admin_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $user['id']]);
        $_SESSION['password_version'] = hash('sha256', $newHash); // other sessions stop working
        session_regenerate_id(true);
        admin_log('password.change', 'users#' . $user['id']);
        admin_flash('เปลี่ยนรหัสผ่านแล้ว อุปกรณ์อื่นที่ล็อกอินอยู่จะถูกออกจากระบบ');
    }
    admin_go('account.php');
}
admin_head('บัญชีของฉัน', 'account.php');
echo '<p>' . e($user['full_name']) . ' · ' . e($user['email']) . ' · ' . ($user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'บรรณาธิการ') . '</p>';
admin_form_open('account.php', 'class="narrow"'); ?>
<label>รหัสผ่านปัจจุบัน<input type="password" name="current" autocomplete="current-password" required></label>
<label>รหัสผ่านใหม่ (12–72 ตัวอักษร)<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<label>รหัสผ่านใหม่อีกครั้ง<input type="password" name="password2" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<button class="btn" type="submit">เปลี่ยนรหัสผ่าน</button></form>
<?php admin_foot();
