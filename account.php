<?php
require __DIR__ . '/includes/auth-page.php';
$user = auth_require();
$currentPage = 'account';
$pageTitle = 'บัญชีของฉัน | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container section"><p class="eyebrow">MY ACCOUNT</p><h1>สวัสดี <?= e($user['full_name']) ?></h1>
<div class="content-panel"><dl><dt>อีเมล</dt><dd><?= e($user['email']) ?></dd><dt>สิทธิ์</dt><dd><?= e($user['role']) ?></dd><dt>สถานะ</dt><dd>อนุมัติแล้ว</dd></dl><a class="text-link" href="change-password.php">เปลี่ยนรหัสผ่าน</a></div>
<h2>พื้นที่ทำงาน</h2><div class="section-links">
<?php if (in_array($user['role'], ['researcher','reviewer','admin'], true)): ?><a class="button" href="register-impact.php">Register Impact</a><a class="button secondary" href="update-impact.php">Update Impact / Scale</a><?php endif; ?>
<?php if (in_array($user['role'], ['executive','reviewer','admin'], true)): ?><a class="button secondary" href="dashboard.php">Dashboard</a><?php endif; ?>
<?php if (in_array($user['role'], ['reviewer','admin'], true)): ?><a class="button secondary" href="admin.php">Admin & Verification</a><?php endif; ?>
<?php if ($user['role'] === 'admin'): ?><a class="button" href="admin-users.php">จัดการบัญชีผู้ใช้</a><?php endif; ?>
</div><p class="small">ระบบบัญชีพร้อมใช้งาน ส่วนการบันทึก Impact และ Dashboard ยังอยู่ระหว่างพัฒนา</p>
<form method="post" action="logout.php"><?php auth_csrf_field(); ?><button class="button secondary" type="submit">ออกจากระบบ</button></form></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
