<?php
require_once dirname(__DIR__) . '/includes/admin/core.php';
$user = admin_boot(['admin']);
$db = admin_db();
$page = max(1, get_int('page') ?? 1); $per = 100;
$act = preg_replace('/[^a-z._]/', '', get_str('action'));
$where = $act !== '' ? 'WHERE action LIKE ?' : ''; $args = $act !== '' ? [$act . '%'] : [];
$total = $db->prepare("SELECT COUNT(*) FROM admin_log $where"); $total->execute($args); $total = (int) $total->fetchColumn();
$st = $db->prepare("SELECT * FROM admin_log $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per)); $st->execute($args);
admin_head('บันทึกการใช้งาน', 'log.php', ['wide' => true]);
?><form class="toolbar" method="get"><label>ขึ้นต้นด้วย<input name="action" value="<?= e($act) ?>" placeholder="เช่น post. media. login"></label><button class="btn btn--ghost" type="submit">กรอง</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>เมื่อ</th><th>ใคร</th><th>ทำอะไร</th><th>กับ</th><th>รายละเอียด</th></tr></thead><tbody>
<?php foreach ($st as $r): ?><tr><td><?= e(admin_time($r['created_at'])) ?></td><td><?= e((string) $r['user_name']) ?></td><td><?= e($r['action']) ?></td><td><?= e((string) $r['target']) ?></td><td><?= e((string) $r['detail']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&amp;action=<?= e($act) ?>">← ใหม่กว่า</a> <?php endif; if ($page * $per < $total): ?><a href="?page=<?= $page + 1 ?>&amp;action=<?= e($act) ?>">เก่ากว่า →</a><?php endif; ?> <span class="hint"><?= $total ?> รายการ ไม่เก็บ IP และรหัสผ่าน</span></p>
<?php admin_foot();
