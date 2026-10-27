<?php
require_once dirname(__DIR__) . '/includes/admin/core.php';
$user = admin_boot(['admin']);
$db = admin_db();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = in_str('op');
    $id = in_int('id');
    if ($op === 'create') {
        $name = in_line('name', 200); $email = strtolower(in_str('email', 254)); $role = in_str('role'); $pass = auth_post('password');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ADMIN_ROLES, true)) { admin_flash('กรอกชื่อ อีเมล และบทบาทให้ถูกต้อง', 'error'); }
        elseif (!auth_password_valid($pass)) { admin_flash('รหัสผ่านต้องยาว 12–72 ตัวอักษร', 'error'); }
        else {
            try {
                $db->prepare("INSERT INTO users (full_name, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, ?, 'active', NOW())")->execute([$name, $email, admin_password_hash($pass), $role]);
                admin_log('user.create', 'users#' . $db->lastInsertId(), $email . ' ' . $role);
                admin_flash('สร้างผู้ใช้แล้ว');
            } catch (PDOException $e) { admin_flash('อีเมลนี้มีบัญชีอยู่แล้ว', 'error'); }
        }
    } elseif ($id && in_array($op, ['update', 'reset'], true)) {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?'); $stmt->execute([$id]); $target = $stmt->fetch();
        if (!$target) { admin_flash('ไม่พบผู้ใช้', 'error'); }
        elseif ($op === 'reset') {
            $pass = auth_post('password');
            if (!auth_password_valid($pass)) { admin_flash('รหัสผ่านต้องยาว 12–72 ตัวอักษร', 'error'); }
            else { $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([admin_password_hash($pass), $id]); admin_log('user.reset', 'users#' . $id); admin_flash('ตั้งรหัสผ่านใหม่แล้ว ผู้ใช้ถูกออกจากระบบทุกอุปกรณ์'); }
        } else {
            $role = in_str('role'); $status = in_str('status');
            $demotes = $target['role'] === 'admin' && $target['status'] === 'active' && ($role !== 'admin' || $status !== 'active');
            if (!in_array($role, ['admin', 'editor'], true) || !in_array($status, ['active', 'suspended'], true)) { admin_flash('ค่าไม่ถูกต้อง', 'error'); }
            elseif ($demotes && admin_count_admins() <= 1) { admin_flash('ต้องมีผู้ดูแลระบบที่ใช้งานอยู่อย่างน้อย 1 คน', 'error'); }
            elseif ((int) $id === (int) $user['id'] && $demotes) { admin_flash('เปลี่ยนบทบาทหรือระงับบัญชีตัวเองไม่ได้', 'error'); }
            else { $db->prepare('UPDATE users SET role = ?, status = ? WHERE id = ?')->execute([$role, $status, $id]); admin_log('user.update', 'users#' . $id, "$role $status"); admin_flash('บันทึกแล้ว'); }
        }
    }
    admin_go('users.php');
}
admin_head('ผู้ใช้หลังบ้าน', 'users.php');
$rows = $db->query("SELECT id, full_name, email, role, status, last_login_at FROM users WHERE role IN ('admin','editor') ORDER BY id")->fetchAll();
?>
<?php foreach ($rows as $r): ?><div class="row"><p><strong><?= e($r['full_name']) ?></strong><br><span class="muted"><?= e($r['email']) ?> · เข้าล่าสุด <?= e(admin_time($r['last_login_at'])) ?></span></p>
<?php admin_form_open('users.php', 'class="inline"'); ?><input type="hidden" name="op" value="update"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
<select name="role" aria-label="บทบาทของ <?= e($r['full_name']) ?>"><option value="admin"<?= $r['role'] === 'admin' ? ' selected' : '' ?>>ผู้ดูแลระบบ</option><option value="editor"<?= $r['role'] === 'editor' ? ' selected' : '' ?>>บรรณาธิการ</option></select>
<select name="status" aria-label="สถานะของ <?= e($r['full_name']) ?>"><option value="active"<?= $r['status'] === 'active' ? ' selected' : '' ?>>ใช้งานได้</option><option value="suspended"<?= $r['status'] !== 'active' ? ' selected' : '' ?>>ระงับ</option></select>
<button class="btn btn--small" type="submit">บันทึก</button></form>
<details class="edit"><summary>ตั้งรหัสผ่านใหม่ให้คนนี้</summary><div><?php admin_form_open('users.php', 'class="inline"'); ?><input type="hidden" name="op" value="reset"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" placeholder="อย่างน้อย 12 ตัวอักษร" aria-label="รหัสผ่านใหม่ของ <?= e($r['full_name']) ?>" required><button class="btn btn--small" type="submit">ตั้งรหัสผ่านใหม่</button></form></div></details></div>
<?php endforeach; ?>
<h2>เพิ่มผู้ใช้ใหม่</h2>
<?php admin_form_open('users.php', 'class="narrow"'); ?><input type="hidden" name="op" value="create">
<label>ชื่อ<input name="name" required></label><label>อีเมล<input type="email" name="email" required></label>
<label>บทบาท<select name="role"><option value="editor">บรรณาธิการ (เขียนและแก้โพสต์)</option><option value="admin">ผู้ดูแลระบบ (ทำได้ทุกอย่าง รวมถึงจัดการผู้ใช้)</option></select></label>
<label>รหัสผ่านเริ่มต้น (12–72 ตัวอักษร)<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<button class="btn" type="submit">เพิ่มผู้ใช้</button></form>
<?php admin_foot();
