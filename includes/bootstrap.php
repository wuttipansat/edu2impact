<?php
// The whole page is buffered, so a database failure halfway through can still answer a clean 503 page
// (content_unavailable() in includes/content-db.php) instead of half a page.
ob_start();
require_once __DIR__ . '/functions.php';
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https://i.ytimg.com; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self'; base-uri 'none'; object-src 'none'; frame-src https://www.youtube-nocookie.com https://www.google.com; frame-ancestors 'self'; form-action 'self'");
try {
    $site = load_data('site');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('ไม่สามารถโหลดข้อมูลเว็บไซต์ได้ กรุณาตรวจสอบ data/site.json');
}
$nav = require __DIR__ . '/../config/navigation.php';
