<?php
require_once __DIR__ . '/functions.php';

function auth_https() {
    // Do not trust client-supplied X-Forwarded-* headers.
    return (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}
function auth_start() {
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('edu2impact_session');
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $path = $path === '/' || $path === '.' ? '/' : rtrim($path, '/') . '/';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => $path, 'secure' => auth_https(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    if (!session_start()) { throw new RuntimeException('Cannot start session'); }
}
function auth_db() {
    static $connection;
    if (!$connection) {
        require __DIR__ . '/db.php';
        $connection = $pdo;
    }
    return $connection;
}
function auth_redirect($target) {
    header('Location: ' . $target, true, 303);
    exit;
}
function auth_post($name) {
    return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
}
function auth_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function auth_csrf_field() {
    echo '<input type="hidden" name="csrf" value="' . e(auth_token()) . '">';
}
function auth_check_csrf() {
    if (!hash_equals(auth_token(), auth_post('csrf'))) {
        http_response_code(403);
        exit('แบบฟอร์มหมดอายุ กรุณากลับไปโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}
function auth_clear() {
    $_SESSION = [];
    session_regenerate_id(true);
}
function auth_user() {
    static $checked = false;
    static $user = null;
    if ($checked) { return $user; }
    $checked = true;
    if (empty($_SESSION['user_id'])) { return null; }
    $now = time();
    if ($now - (int) ($_SESSION['last_seen'] ?? 0) > 1800
        || $now - (int) ($_SESSION['signed_in'] ?? 0) > 28800) {
        auth_clear();
        return null;
    }
    // Re-read role/status on each request so suspension takes effect immediately.
    $stmt = auth_db()->prepare('SELECT id, full_name, email, role, status, created_at, password_hash FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active' || !hash_equals(hash('sha256', $row['password_hash']), (string) ($_SESSION['password_version'] ?? ''))) { auth_clear(); return null; }
    $_SESSION['last_seen'] = $now;
    unset($row['password_hash']);
    $user = $row;
    return $user;
}
function auth_require($roles = []) {
    $user = auth_user();
    if (!$user) { auth_redirect('login.php'); }
    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
    return $user;
}
function auth_service_error($exception) {
    // Log only class/code, never SQL parameters, credentials or passwords.
    error_log('EDU2Impact auth: ' . get_class($exception) . ' code=' . $exception->getCode());
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    exit('<h1>ระบบบัญชีไม่พร้อมใช้งานชั่วคราว</h1><p>กรุณาลองใหม่ หรือติดต่อผู้ดูแลให้ตรวจการเชื่อมต่อและนำเข้า auth-schema.sql</p>');
}
function auth_private_transport() {
    // Local development is allowed over HTTP. Production login requires HTTPS.
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!auth_https() && !in_array($address, ['127.0.0.1', '::1'], true)) {
        http_response_code(400);
        exit('กรุณาเปิดหน้านี้ผ่าน HTTPS ก่อนใช้งานระบบบัญชี หากโฮสต์ใช้ reverse proxy ให้ผู้ดูแลตั้งค่า HTTPS ที่เว็บเซิร์ฟเวอร์');
    }
}
function auth_throttle($action, $email = '') {
    $db = auth_db();
    $now = time();
    $buckets = [[hash('sha256', $action . ':ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')), $action === 'register' ? 5 : 30]];
    if ($email !== '') { $buckets[] = [hash('sha256', $action . ':email:' . $email), 10]; }
    $allowed = true;
    // Count before verification. Stored in MySQL, not a bypassable browser session.
    $db->beginTransaction();
    try {
        $upsert = $db->prepare('INSERT INTO auth_rate_limits (bucket_key, attempts, expires_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = IF(expires_at <= ?, 1, attempts + 1), expires_at = IF(expires_at <= ?, ?, expires_at)');
        $read = $db->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket_key = ?');
        foreach ($buckets as $bucket) {
            $upsert->execute([$bucket[0], $now + 900, $now, $now, $now + 900]);
            $read->execute([$bucket[0]]);
            if ((int) $read->fetchColumn() > $bucket[1]) { $allowed = false; }
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $exception;
    }
    if (random_int(1, 100) === 1) {
        $clean = $db->prepare('DELETE FROM auth_rate_limits WHERE expires_at < ?');
        $clean->execute([$now - 86400]);
    }
    return $allowed;
}
function auth_password_valid($password) {
    return strlen($password) >= 12 && strlen($password) <= 72 && strpos($password, "\0") === false;
}
