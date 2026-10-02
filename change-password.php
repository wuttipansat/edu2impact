<?php
require __DIR__ . '/includes/auth-page.php';
$user = auth_require();
$error = '';
$success = !empty($_SESSION['password_changed']);
unset($_SESSION['password_changed']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_check_csrf();
    try {
        $password = auth_post('new_password');
        $old = auth_post('current_password');
        if (!auth_throttle('password', $user['email'])) {
            http_response_code(429);
            $error = 'ลองหลายครั้งเกินไป กรุณารอ 15 นาที';
        } elseif (!auth_password_valid($password) || $password !== auth_post('password_confirm')) {
            $error = 'รหัสผ่านใหม่ต้องยาว 12–72 ไบต์ และช่องยืนยันต้องตรงกัน';
        } else {
            $stmt = auth_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $oldHash = $stmt->fetchColumn();
            if (strlen($old) > 72 || strpos($old, "\0") !== false || !password_verify($old, $oldHash)) {
                $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = auth_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
                $stmt->execute([$hash, $user['id'], $oldHash]);
                if ($stmt->rowCount() !== 1) { throw new RuntimeException('Account changed concurrently'); }
                session_regenerate_id(true);
                $_SESSION['password_version'] = hash('sha256', $hash);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $_SESSION['password_changed'] = true;
                auth_redirect('change-password.php');
            }
        }
    } catch (Throwable $exception) { auth_service_error($exception); }
}
$currentPage = 'account';
$pageTitle = 'เปลี่ยนรหัสผ่าน | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container section auth-container"><h1>เปลี่ยนรหัสผ่าน</h1>
<?php if ($success): ?><p role="status">เปลี่ยนรหัสผ่านแล้ว การเข้าสู่ระบบในอุปกรณ์อื่นจะสิ้นสุดเมื่อใช้งานครั้งถัดไป</p><?php endif; ?>
<?php if ($error): ?><p class="auth-message" role="alert"><?= e($error) ?></p><?php endif; ?>
<form class="auth-form content-panel" method="post" action="change-password.php"><?php auth_csrf_field(); ?>
<label for="current">รหัสผ่านปัจจุบัน</label><input id="current" type="password" name="current_password" autocomplete="current-password" maxlength="72" required>
<label for="new">รหัสผ่านใหม่ (12–72 ไบต์)</label><input id="new" type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required>
<label for="confirm">ยืนยันรหัสผ่านใหม่</label><input id="confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="72" required>
<button class="button" type="submit">บันทึกรหัสผ่านใหม่</button></form><a class="text-link" href="account.php">กลับหน้าบัญชี</a></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
