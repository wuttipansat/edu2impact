<?php
require __DIR__ . '/includes/auth-page.php';
if ($accountUser) { auth_redirect('account.php'); }
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_check_csrf();
    $email = strtolower(trim(auth_post('email')));
    $password = auth_post('password');
    try {
        if (!auth_throttle('login', substr($email, 0, 254))) {
            http_response_code(429);
            header('Retry-After: 900');
            $error = 'ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 15 นาที';
        } else {
            $row = false;
            if (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = auth_db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $row = $stmt->fetch();
            }
            // Valid bcrypt dummy prevents the fast path for unknown accounts.
            $hash = $row ? $row['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid = strlen($password) <= 72 && strpos($password, "\0") === false && password_verify($password, $hash);
            if (!$row || !$valid || $row['status'] !== 'active') {
                $error = 'เข้าสู่ระบบไม่ได้ โปรดตรวจอีเมล รหัสผ่าน และการอนุมัติบัญชีกับผู้ดูแล';
            } else {
                if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = auth_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
                    $stmt->execute([$hash, $row['id'], $row['password_hash']]);
                    if ($stmt->rowCount() !== 1) { throw new RuntimeException('Account changed concurrently'); }
                }
                auth_clear();
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['password_version'] = hash('sha256', $hash);
                $_SESSION['signed_in'] = time();
                $_SESSION['last_seen'] = time();
                $stmt = auth_db()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$row['id']]);
                auth_redirect('account.php');
            }
        }
    } catch (Throwable $exception) { auth_service_error($exception); }
}
$currentPage = 'login';
$pageTitle = 'Login | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container section auth-container"><p class="eyebrow">EDU2IMPACT ACCOUNT</p><h1>เข้าสู่ระบบ</h1><p>สำหรับบัญชีที่ได้รับอนุมัติแล้ว</p>
<?php if ($error): ?><p class="auth-message" role="alert"><?= e($error) ?></p><?php endif; ?>
<form class="auth-form content-panel" method="post" action="login.php">
<?php auth_csrf_field(); ?>
<label for="email">อีเมล</label><input id="email" name="email" type="email" autocomplete="username" maxlength="254" value="<?= e($email) ?>" required>
<label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
<button class="button" type="submit">เข้าสู่ระบบ</button></form>
<p>ยังไม่มีบัญชี? <a class="text-link" href="register.php">สมัครสมาชิก</a></p><p class="small">หากลืมรหัสผ่าน กรุณาติดต่อผู้ดูแล ระบบยังไม่รองรับการรีเซ็ตรหัสผ่านทางอีเมล</p><a class="text-link" href="contact.php">ช่องทางติดต่อ</a></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
