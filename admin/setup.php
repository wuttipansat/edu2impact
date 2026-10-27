<?php
// First administrator. Works only while no admin exists and private/database.php has a setup_token.
require_once dirname(__DIR__) . '/includes/admin/core.php';
admin_boot([], false);
$token = admin_config()['setup_token'] ?? '';
if (!is_string($token) || strlen($token) < 24 || admin_count_admins() > 0) { http_response_code(404); exit('ไม่พบหน้านี้'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    auth_check_csrf();
    if (!auth_throttle('admin-setup')) { http_response_code(429); $error = 'ลองบ่อยเกินไป รอ 15 นาที'; }
    elseif (!hash_equals($token, auth_post('token'))) { $error = 'รหัสตั้งต้นไม่ถูกต้อง'; }
    else {
        $name = in_line('name', 200); $email = strtolower(in_str('email', 254)); $pass = auth_post('password');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'กรอกชื่อและอีเมลให้ถูกต้อง'; }
        elseif (!auth_password_valid($pass)) { $error = 'รหัสผ่านต้องยาว 12–72 ตัวอักษร'; }
        elseif ($pass !== auth_post('password2')) { $error = 'รหัสผ่านสองช่องไม่ตรงกัน'; }
        else {
            try {
                admin_db()->prepare("INSERT INTO users (full_name, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, 'admin', 'active', NOW())")
                    ->execute([$name, $email, admin_password_hash($pass)]);
            } catch (PDOException $e) {
                // Email already used by an existing (non-admin) account: promote it only if the password matches nothing; refuse instead.
                $error = 'อีเมลนี้มีบัญชีอยู่แล้ว ให้ใช้อีเมลอื่น หรือเปลี่ยน role ใน phpMyAdmin เป็น admin';
            }
            if ($error === '') {
                admin_log('setup', 'users', $email);
                admin_flash('สร้างผู้ดูแลระบบแล้ว ลบ setup_token ออกจาก private/database.php แล้วเข้าสู่ระบบ');
                admin_go('login.php');
            }
        }
    }
}
admin_head('ตั้งค่าผู้ดูแลระบบคนแรก');
if ($error) { echo '<p class="notice notice--error" role="alert">' . e($error) . '</p>'; }
admin_form_open('setup.php', 'class="narrow"'); ?>
<label>รหัสตั้งต้น (setup_token ใน private/database.php)<input type="password" name="token" autocomplete="off" required></label>
<label>ชื่อ<input name="name" required></label>
<label>อีเมล<input type="email" name="email" required></label>
<label>รหัสผ่าน (12–72 ตัวอักษร)<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<label>รหัสผ่านอีกครั้ง<input type="password" name="password2" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<button class="btn" type="submit">สร้างผู้ดูแลระบบ</button></form>
<?php admin_foot();
