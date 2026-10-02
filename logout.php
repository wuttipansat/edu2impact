<?php
require __DIR__ . '/includes/auth-page.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('กรุณาออกจากระบบผ่านปุ่มในหน้าบัญชี');
}
auth_check_csrf();
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000, 'path' => $params['path'],
    'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax',
]);
session_destroy();
auth_redirect('login.php');
