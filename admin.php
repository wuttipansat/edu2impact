<?php
require __DIR__ . '/includes/auth-page.php';
auth_require(['reviewer', 'admin']);
$currentPage = 'admin';
require __DIR__ . '/includes/section-page.php';
