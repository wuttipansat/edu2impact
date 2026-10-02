<?php
require __DIR__ . '/includes/auth-page.php';
auth_require(['researcher', 'reviewer', 'admin']);
$currentPage = 'register';
require __DIR__ . '/includes/section-page.php';
