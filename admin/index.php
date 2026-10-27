<?php
require_once dirname(__DIR__) . '/includes/admin/posts.php';
$user = admin_boot();
$db = admin_db();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = in_str('op'); $id = in_int('id');
    $st = $db->prepare('SELECT * FROM posts WHERE id = ?'); $st->execute([(int) $id]); $row = $st->fetch();
    if ($row && in_array($op, ['show', 'hide'], true)) {
        admin_revision_add((int) $id, $row['kind'], $row['slug'], 'visibility', $row, admin_post_links($row['kind'], $row['slug']));
        $db->prepare('UPDATE posts SET is_public = ?, updated_by = ? WHERE id = ?')->execute([$op === 'show' ? 1 : 0, $user['id'], $id]);
        if ($op === 'hide') { $_SESSION['undo'] = ['id' => (int) $id, 'title' => $row['title']]; }
        else { admin_flash('เผยแพร่ “' . $row['title'] . '” แล้ว'); }
    }
    admin_go('index.php' . (preg_match('/\A[A-Za-z0-9=&%_.+-]*\z/', in_str('qs')) && in_str('qs') !== '' ? '?' . in_str('qs') : ''));
}
$kind = isset(ADMIN_KINDS[get_str('kind')]) ? get_str('kind') : '';
$q = mb_substr(get_str('q'), 0, 100);
$all = get_str('all') === '1';
$where = ['1=1']; $args = [];
if ($kind) { $where[] = 'p.kind = ?'; $args[] = $kind; }
if ($q !== '') { $where[] = 'p.title LIKE ?'; $args[] = '%' . addcslashes($q, '%_\\') . '%'; }
$total = $db->prepare('SELECT COUNT(*) FROM posts p WHERE ' . implode(' AND ', $where)); $total->execute($args); $total = (int) $total->fetchColumn();
$st = $db->prepare('SELECT p.id, p.kind, p.slug, p.title, p.is_public, p.image_id, COALESCE(p.updated_at, p.created_at) AS changed, LEFT(m.sha256, 8) AS v
    FROM posts p LEFT JOIN media m ON m.id = p.image_id WHERE ' . implode(' AND ', $where) . ' ORDER BY changed DESC, p.id DESC' . ($all ? '' : ' LIMIT 50'));
$st->execute($args); $rows = $st->fetchAll();
$qs = http_build_query(array_filter(['kind' => $kind, 'q' => $q, 'all' => $all ? '1' : '']));
$warn = '';
if (!auth_https()) { $warn = 'หน้านี้ไม่ได้เปิดผ่าน HTTPS ไม่ควรล็อกอินจากที่นี่'; }
elseif (admin_is_admin($user) && !empty(admin_config()['setup_token'])) { $warn = 'ลบบรรทัด setup_token ออกจากไฟล์ private/database.php (ใช้แค่ครั้งแรก)'; }
admin_head('โพสต์ทั้งหมด', 'home', ['wide' => true]);
if ($warn) { echo '<p class="notice notice--error" role="alert">' . e($warn) . '</p>'; }
?>
<p><a class="btn btn--big" href="post-edit.php">+ สร้างโพสต์ใหม่</a></p>
<form class="toolbar" method="get" action="index.php"><label>ค้นหาจากหัวข้อ<input type="search" name="q" value="<?= e($q) ?>"></label><?php if ($kind): ?><input type="hidden" name="kind" value="<?= e($kind) ?>"><?php endif; ?><button class="btn btn--ghost" type="submit">ค้นหา</button></form>
<p class="filters"><?php
$link = fn($k, $label) => ($k === $kind ? '<strong>' . e($label) . '</strong>' : '<a href="index.php?' . e(http_build_query(array_filter(['kind' => $k, 'q' => $q]))) . '">' . e($label) . '</a>');
echo $link('', 'ทั้งหมด'); foreach (ADMIN_KINDS as $k => $label) { echo $link($k, $label); } ?></p>
<?php if (!$rows): ?><p>ยังไม่มีโพสต์<?= $q !== '' ? ' ที่ตรงกับคำค้น' : '' ?></p><?php endif; ?>
<ul class="posts"><?php foreach ($rows as $r): ?><li class="post">
<?php if ($r['image_id']): ?><img class="post__img" src="media-img.php?id=<?= (int) $r['image_id'] ?>&amp;v=<?= e((string) $r['v']) ?>" alt="" loading="lazy"><?php else: ?><span class="post__img"></span><?php endif; ?>
<div><a class="post__title" href="post-edit.php?id=<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a>
<div class="post__meta"><?= e(ADMIN_KINDS[$r['kind']] ?? $r['kind']) ?> · <?= $r['is_public'] ? '<span class="tag tag--live">เผยแพร่แล้ว</span>' : '<span class="tag">ร่าง (ยังไม่แสดงบนเว็บ)</span>' ?> · แก้ล่าสุด <?= e(admin_time($r['changed'])) ?></div></div>
<div class="inline"><a class="btn btn--small btn--ghost" href="post-edit.php?id=<?= (int) $r['id'] ?>">แก้ไข</a>
<?php if ($r['is_public']): ?><a class="btn btn--small btn--ghost" href="../<?= e(detail_url($r['kind'], $r['slug'])) ?>" target="_blank" rel="noopener">ดูบนเว็บ</a><?php endif; ?></div></li><?php endforeach; ?></ul>
<?php if (!$all && $total > count($rows)): ?><p><a href="index.php?<?= e(http_build_query(array_filter(['kind' => $kind, 'q' => $q, 'all' => '1']))) ?>">แสดงทั้งหมด (<?= $total ?> โพสต์)</a></p><?php endif; ?>
<p class="more"><strong>จัดการอื่นๆ</strong><br><a href="media.php">คลังรูป</a><a href="home.php">แบนเนอร์หน้าแรก</a><a href="shop.php">ร้านค้า</a>
<?php if (admin_is_admin($user)): ?><a href="settings.php">ตั้งค่าเว็บ</a><a href="users.php">ผู้ใช้</a><a href="backup.php">สำรองข้อมูล</a><?php endif; ?></p>
<?php admin_foot();
