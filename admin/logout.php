<?php
require_once dirname(__DIR__) . '/includes/admin/core.php';
admin_boot([], false);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    auth_check_csrf();
    if (auth_user()) { admin_log('logout'); }
    auth_clear();
}
admin_go('login.php');
