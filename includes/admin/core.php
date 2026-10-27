<?php
// Shared bootstrap for the admin (pages live in admin/, reached at /admin/ and protected by login).
// Closed to the web by includes/.htaccess. Every admin page calls admin_boot() first.
require_once dirname(__DIR__) . '/auth.php';

const ADMIN_ROLES = ['admin', 'editor'];
const ADMIN_KINDS = [
    'research' => 'งานวิจัย', 'innovations' => 'นวัตกรรม', 'workshops' => 'เวิร์กช็อป',
    'media' => 'สื่อ',
];

function admin_root(): string { return dirname(__DIR__, 2); }

// Own CSP: no inline script or style, nothing from other sites (plain system fonts).
function admin_headers(): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; font-src 'self'; script-src 'self'; base-uri 'self'; object-src 'none'; frame-src 'self'; frame-ancestors 'self'; form-action 'self'");
}

function admin_config(): array {
    $path = getenv('EDU2IMPACT_DB_CONFIG') ?: admin_root() . '/private/database.php';
    $config = is_readable($path) ? require $path : [];
    return is_array($config) ? $config : [];
}

// Opens the session, checks the user and (for POST) the CSRF token. $roles = [] means "no login needed" (login, setup).
function admin_boot(array $roles = ADMIN_ROLES, bool $login = true): ?array {
    admin_headers();
    auth_private_transport();
    try {
        auth_start();
        admin_db();
        if (!$login) { return null; }
        $user = auth_user();
        if (!$user || !in_array($user['role'], ADMIN_ROLES, true)) { auth_redirect('login.php'); }
        if ($roles && !in_array($user['role'], $roles, true)) {
            http_response_code(403);
            admin_head('ไม่มีสิทธิ์', '');
            echo '<p class="notice notice--error">บัญชีนี้ไม่มีสิทธิ์ใช้หน้านี้ (ต้องเป็นผู้ดูแลระบบ)</p>';
            admin_foot();
            exit;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {   // PHP throws everything away when the upload is bigger than post_max_size
                admin_fail('ไฟล์ที่เลือกมีขนาดรวมใหญ่เกินที่เซิร์ฟเวอร์รับได้ (' . ini_get('post_max_size') . ') ลองอัปโหลดทีละน้อยไฟล์ หรือย่อรูปก่อน ยังไม่ได้บันทึกอะไร', 413);
            }
            auth_check_csrf();
        }
        return $user;
    } catch (PDOException $exception) {
        auth_service_error($exception);
    }
}

function admin_db(): PDO { return auth_db(); }

function admin_is_admin(?array $user): bool { return $user && $user['role'] === 'admin'; }

function admin_flash(string $message, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $message]; }

function admin_go(string $target): never { auth_redirect($target); }

// The activity log page was removed. Calls stay in the code as a no-op so nothing else has to change.
function admin_log(string $action, string $target = '', string $detail = ''): void {}

function admin_meta_get(string $key): ?string {
    $stmt = admin_db()->prepare('SELECT meta_value FROM admin_meta WHERE meta_key = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : $v;
}
function admin_meta_set(string $key, string $value): void {
    admin_db()->prepare('INSERT INTO admin_meta (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)')->execute([$key, $value]);
}

// Reading input
function in_str(string $name, int $max = 0): string {
    $v = isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
    $v = str_replace("\0", '', $v);
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = trim($v);
    return $max > 0 ? mb_substr($v, 0, $max) : $v;
}
function in_line(string $name, int $max = 255): string { return trim(preg_replace('/\s+/u', ' ', in_str($name)) ?? '') === '' ? '' : mb_substr(trim(preg_replace('/\s+/u', ' ', in_str($name)) ?? ''), 0, $max); }
function in_int(string $name): ?int {
    $v = filter_var($_POST[$name] ?? null, FILTER_VALIDATE_INT);
    return $v === false ? null : $v;
}
function in_flag(string $name): int { return !empty($_POST[$name]) ? 1 : 0; }
function in_date(string $name): ?string {
    $v = in_str($name);
    if ($v === '') { return null; }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : null;
}
function get_int(string $name): ?int {
    $v = filter_var($_GET[$name] ?? null, FILTER_VALIDATE_INT);
    return $v === false || $v < 1 ? null : $v;
}
function get_str(string $name): string { return isset($_GET[$name]) && is_string($_GET[$name]) ? trim($_GET[$name]) : ''; }

// Only https links, relative site paths and apps/ folders may be stored in a link field.
function clean_url(string $url): ?string {
    $url = trim($url);
    if ($url === '') { return ''; }
    if (preg_match('~\Ahttps://[^\s<>"\']+\z~i', $url)) { return $url; }
    if (preg_match('~\A(?!//)[A-Za-z0-9_\-./?=&%#]+\z~', $url) && !str_contains($url, '..')) { return $url; }
    return null;
}
function clean_slug(string $slug): string {
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim(mb_substr($slug, 0, 120), '-');
}

// Layout: one line at the top, no menu. Everything else is reached from the post list.
function admin_head(string $title, string $active = '', array $opts = []): void {
    $user = auth_user();
    $flash = $_SESSION['flash'] ?? [];
    $undo = $_SESSION['undo'] ?? null;
    unset($_SESSION['flash'], $_SESSION['undo']);
    ?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title><?= e($title) ?> · หลังบ้าน</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<script src="../assets/js/admin.js" defer></script></head>
<body><a class="skip" href="#main">ข้ามไปเนื้อหา</a>
<header class="top"><a class="top__name" href="index.php">หลังบ้าน KUEDU2IMPACT</a>
<?php if ($user): ?><span class="top__user"><a href="account.php"><?= e($user['full_name']) ?></a>
<form method="post" action="logout.php"><?php auth_csrf_field(); ?><button class="link" type="submit">ออกจากระบบ</button></form></span><?php endif; ?></header>
<main id="main" class="page<?= !empty($opts['wide']) ? ' page--wide' : '' ?>">
<?php if ($active !== 'home' && $user): ?><p class="back"><a href="index.php">← กลับหน้ารายการโพสต์</a></p><?php endif; ?>
<h1><?= e($title) ?></h1>
<?php foreach ($flash as [$type, $message]): ?><p class="notice notice--<?= $type === 'ok' ? 'ok' : 'error' ?>" role="<?= $type === 'ok' ? 'status' : 'alert' ?>"><?= e($message) ?></p><?php endforeach;
    if ($undo): ?><form class="notice notice--ok inline" method="post" action="index.php"><?php auth_csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $undo['id'] ?>"><input type="hidden" name="op" value="show">
<span>ซ่อน “<?= e($undo['title']) ?>” แล้ว</span> <button class="btn btn--small" type="submit">เลิกทำ</button></form><?php endif;
}

function admin_foot(): void { echo '</main></body></html>'; }

function admin_form_open(string $action = '', string $extra = ''): void {
    echo '<form method="post" action="' . e($action) . '"' . ($extra !== '' ? ' ' . $extra : '') . '>';
    auth_csrf_field();
}

function admin_time(?string $ts): string { return $ts ? date('d/m/Y H:i', strtotime($ts)) : '–'; }

function admin_bytes(int $b): string {
    if ($b >= 1048576) { return number_format($b / 1048576, 1) . ' MB'; }
    return number_format(max(1, (int) round($b / 1024))) . ' KB';
}

function admin_count_admins(): int {
    return (int) admin_db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
}

function admin_password_hash(string $p): string { return password_hash($p, PASSWORD_DEFAULT); }

function admin_fail(string $message, int $code = 400): never {
    http_response_code($code);
    admin_head('เกิดข้อผิดพลาด', '');
    echo '<p class="notice notice--error" role="alert">' . e($message) . '</p><p><a href="index.php">กลับหน้าภาพรวม</a></p>';
    admin_foot();
    exit;
}
