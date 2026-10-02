<?php
require __DIR__ . '/includes/auth-page.php';
$user = auth_require(['admin']);
$error = '';
$notice = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        auth_check_csrf();
        $id = filter_var(auth_post('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $status = auth_post('status');
        if (!$id || (int) $id === (int) $user['id'] || !in_array($status, ['active', 'suspended', 'pending'], true)) {
            $error = 'คำขอไม่ถูกต้อง หรือพยายามเปลี่ยนสถานะบัญชีตนเอง';
        } else {
            // Admin accounts are deliberately excluded to prevent accidental lockout.
            $stmt = auth_db()->prepare("UPDATE users SET status = ? WHERE id = ? AND role <> 'admin'");
            $stmt->execute([$status, $id]);
            $_SESSION['admin_notice'] = $stmt->rowCount() ? 'ปรับสถานะบัญชีแล้ว' : 'ไม่มีการเปลี่ยนแปลง บัญชีอาจเป็น Admin หรือมีสถานะนี้อยู่แล้ว';
            auth_redirect('admin-users.php');
        }
    }
    $filter = query_string('status');
    if (!in_array($filter, ['pending','active','suspended'], true)) { $filter = 'pending'; }
    $page = max(1, min(100000, (int) query_string('page')));
    $offset = ($page - 1) * 30;
    $stmt = auth_db()->prepare('SELECT id, full_name, email, role, status, created_at FROM users WHERE status = ? ORDER BY id DESC LIMIT 31 OFFSET ' . $offset);
    $stmt->execute([$filter]);
    $rows = $stmt->fetchAll();
    $more = count($rows) > 30;
    $rows = array_slice($rows, 0, 30);
} catch (Throwable $exception) { auth_service_error($exception); }
$currentPage = 'account';
$pageTitle = 'จัดการบัญชีผู้ใช้ | ' . $site['name'];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container section"><h1>จัดการบัญชีผู้ใช้</h1><p>ตรวจสอบตัวตนกับผู้สมัครก่อนอนุมัติ บัญชี Admin และการเปลี่ยนสิทธิ์จัดการผ่านผู้ดูแลฐานข้อมูลเท่านั้น</p>
<?php if ($notice): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-message" role="alert"><?= e($error) ?></p><?php endif; ?>
<nav class="section-links" aria-label="สถานะบัญชี"><?php foreach (['pending'=>'รออนุมัติ','active'=>'ใช้งานได้','suspended'=>'ระงับ'] as $key=>$label): ?><a href="admin-users.php?status=<?= e($key) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<?php if (!$rows): ?><p>ไม่มีบัญชีในรายการนี้</p><?php endif; ?>
<?php foreach ($rows as $row): ?><article class="content-panel"><h2><?= e($row['full_name']) ?></h2><p><?= e($row['email']) ?> · <?= e($row['role']) ?> · <?= e($row['status']) ?></p><p class="small">สมัครเมื่อ <?= e($row['created_at']) ?></p>
<?php if ($row['role'] !== 'admin' && (int) $row['id'] !== (int) $user['id']): ?>
<form class="actions" method="post" action="admin-users.php"><?php auth_csrf_field(); ?><input type="hidden" name="user_id" value="<?= e($row['id']) ?>">
<?php if ($row['status'] !== 'active'): ?><button class="button" name="status" value="active" type="submit">อนุมัติ / เปิดใช้งาน</button><?php endif; ?>
<?php if ($row['status'] !== 'suspended'): ?><button class="button secondary" name="status" value="suspended" type="submit">ระงับบัญชี</button><?php endif; ?>
<?php if ($row['status'] !== 'pending'): ?><button class="button secondary" name="status" value="pending" type="submit">รอตรวจสอบใหม่</button><?php endif; ?></form>
<?php endif; ?></article><?php endforeach; ?>
<nav class="section-links" aria-label="หน้ารายการ"><?php if ($page > 1): ?><a href="admin-users.php?status=<?= e($filter) ?>&amp;page=<?= $page - 1 ?>">หน้าก่อนหน้า</a><?php endif; ?><?php if ($more): ?><a href="admin-users.php?status=<?= e($filter) ?>&amp;page=<?= $page + 1 ?>">หน้าถัดไป</a><?php endif; ?></nav>
<a class="text-link" href="account.php">กลับหน้าบัญชี</a></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
