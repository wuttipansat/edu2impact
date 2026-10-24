<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'workshops';
$listing = [
    'layout' => 'workshops', 'cta' => 'ดูรายละเอียด',
    'page_title' => 'อบรมและเวิร์กช็อป',
    'latest_heading' => 'เวิร์กช็อปล่าสุด', 'noun' => 'รายการ',
    'library_heading' => 'เวิร์กช็อปทั้งหมด',
    'fallback_category' => 'เวิร์กช็อป', 'fallback_author' => 'ยังไม่ระบุวิทยากร',
];
require __DIR__ . '/includes/editorial-listing.php';
