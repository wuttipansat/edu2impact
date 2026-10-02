<?php
require __DIR__ . '/includes/auth-page.php';
auth_require(['executive', 'reviewer', 'admin']);
$currentPage = 'dashboard';
require __DIR__ . '/includes/section-page.php';
