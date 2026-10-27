<?php
// Shows the form's current (unsaved) values on the real public detail page. Nothing is written; visits are not counted.
require_once dirname(__DIR__) . '/includes/admin/posts.php';
$user = admin_boot();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { admin_go('posts.php'); }
define('ADMIN_PREVIEW', true);
$p = admin_post_from_input(false);
$id = in_int('id');
if ($id) { $k = admin_db()->prepare('SELECT kind FROM posts WHERE id = ?'); $k->execute([$id]); $p['kind'] = $k->fetchColumn() ?: $p['kind']; $p['id'] = $id; }
$GLOBALS['admin_preview_record'] = admin_post_record($p);
if (!isset(ADMIN_KINDS[$p['kind']])) { $p['kind'] = 'research'; }
$kind = $p['kind'];
chdir(dirname(__DIR__));
// The public page runs from the site root; images are fetched through the admin endpoint (hidden/unused files included).
ob_start(function ($html) {
    $html = preg_replace('~(<head[^>]*>)~i', '$1<base href="../">', $html, 1);
    $html = preg_replace_callback('~file\.php\?id=(\d+)(?:&amp;|&)v=([0-9a-f]+)(?:&amp;|&)?(?:og=1)?~', fn($m) => basename(__DIR__) . '/media-img.php?id=' . $m[1], $html);
    $html = preg_replace('~file\.php\?id=(\d+)~', basename(__DIR__) . '/media-img.php?id=$1', $html);
    $banner = '<div class="admin-preview-bar">ตัวอย่างที่ยังไม่บันทึก · ปิดแท็บนี้เพื่อกลับไปแก้ไข</div>';
    $html = preg_replace('~</head>~i', '<link rel="stylesheet" href="assets/css/admin-preview.css"></head>', $html, 1);
    return preg_replace('~(<body[^>]*>)~i', '$1' . $banner, $html, 1);
});
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/detail.php';
