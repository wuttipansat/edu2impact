<?php
require_once dirname(__DIR__) . '/includes/admin/core.php';
admin_boot([], false);
$error = '';
if (auth_user() && in_array(auth_user()['role'], ADMIN_ROLES, true)) { admin_go('index.php'); }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    auth_check_csrf();
    $email = strtolower(in_str('email', 254));
    $password = auth_post('password');
    $generic = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    if (!auth_throttle('admin-login', $email)) {
        http_response_code(429);
        $error = 'พยายามเข้าสู่ระบบบ่อยเกินไป กรุณารอ 15 นาทีแล้วลองใหม่';
    } else {
        $stmt = admin_db()->prepare("SELECT * FROM users WHERE email = ? AND role IN ('admin','editor')");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        // Always run password_verify so a missing account takes as long as a wrong password.
        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsalt$2y$10$abcdefghijklmnopqrstuv';
        $ok = password_verify($password, $hash) && $row && $row['status'] === 'active';
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['id'];
            $_SESSION['signed_in'] = $_SESSION['last_seen'] = time();
            $_SESSION['password_version'] = hash('sha256', $row['password_hash']);
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                admin_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([admin_password_hash($password), $row['id']]);
            }
            admin_db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);
            admin_log('login', 'users#' . $row['id']);
            admin_go('index.php');
        }
        $error = $generic;
    }
}
admin_head('เข้าสู่ระบบ');
if ($error) { echo '<p class="notice notice--error" role="alert">' . e($error) . '</p>'; }
admin_form_open('login.php', 'class="narrow"'); ?>
<label>อีเมล<input type="email" name="email" autocomplete="username" required autofocus></label>
<label>รหัสผ่าน<input type="password" name="password" autocomplete="current-password" required></label>
<button class="btn" type="submit">เข้าสู่ระบบ</button></form>
<?php admin_foot();
