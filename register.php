<?php
require __DIR__ . '/includes/auth-page.php';
if ($accountUser) { auth_redirect('account.php'); }
$error = '';
$name = '';
$email = '';
$success = !empty($_SESSION['registration_submitted']);
unset($_SESSION['registration_submitted']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_check_csrf();
    $name = trim(auth_post('full_name'));
    $email = strtolower(trim(auth_post('email')));
    $password = auth_post('password');
    try {
        if (!auth_throttle('register')) {
            http_response_code(429);
            header('Retry-After: 900');
            $error = 'ส่งคำขอหลายครั้งเกินไป กรุณารอ 15 นาที';
        } elseif (!preg_match('/^.{1,200}$/us', $name)) {
            $error = 'กรุณากรอกชื่อ–นามสกุลไม่เกิน 200 ตัวอักษร';
        } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'กรุณากรอกอีเมลให้ถูกต้อง';
        } elseif (!auth_password_valid($password)) {
            $error = 'รหัสผ่านต้องยาว 12–72 ไบต์ แนะนำใช้ตัวอักษรอังกฤษ ตัวเลข และสัญลักษณ์';
        } elseif ($password !== auth_post('password_confirm')) {
            $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            try {
                // Never accept role/status from the browser.
                $stmt = auth_db()->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'researcher', 'pending')");
                $stmt->execute([$name, $email, $hash]);
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) { throw $exception; }
                // Same response for an existing address; preserve the old account.
            }
            $_SESSION['registration_submitted'] = true;
            auth_redirect('register.php');
        }
    } catch (Throwable $exception) { auth_service_error($exception); }
}
$currentPage = 'login';
$pageTitle = 'สมัครสมาชิก | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container section auth-container"><p class="eyebrow">JOIN EDU2IMPACT</p><h1>สมัครสมาชิก</h1><p>บัญชีใหม่เป็นผู้วิจัยและต้องรอผู้ดูแลอนุมัติก่อนเข้าสู่ระบบ</p>
<?php if ($success): ?><div class="content-panel" role="status"><h2>รับคำขอแล้ว</h2><p>หากอีเมลนี้ยังไม่มีบัญชี ระบบได้สร้างบัญชีรออนุมัติ กรุณาติดต่อฝ่ายวิจัยเพื่อยืนยันตัวตน หากเคยสมัครแล้วให้ใช้บัญชีเดิม</p><a class="button" href="login.php">ไปหน้า Login</a></div>
<?php else: ?>
<?php if ($error): ?><p class="auth-message" role="alert"><?= e($error) ?></p><?php endif; ?>
<form class="auth-form content-panel" method="post" action="register.php">
<?php auth_csrf_field(); ?>
<label for="full-name">ชื่อ–นามสกุล</label><input id="full-name" name="full_name" autocomplete="name" maxlength="200" value="<?= e($name) ?>" required>
<label for="email">อีเมล</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="<?= e($email) ?>" required>
<label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="password-help" required><p id="password-help" class="small">ใช้รหัสผ่าน 12–72 ไบต์ ตัวอักษรภาษาไทยใช้หลายไบต์ต่อหนึ่งตัวอักษร</p>
<label for="confirm">ยืนยันรหัสผ่าน</label><input id="confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
<button class="button" type="submit">สมัครสมาชิก</button></form>
<?php endif; ?>
<p>มีบัญชีแล้ว? <a class="text-link" href="login.php">เข้าสู่ระบบ</a></p></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
