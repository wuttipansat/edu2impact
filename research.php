<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'research';
$listing = [
    'layout' => 'research', 'cta' => 'อ่านงานวิจัย',
    'page_title' => 'งานวิจัยและบทความ',
    'latest_heading' => 'ผลงานวิจัยล่าสุด', 'noun' => 'เรื่อง',
    'library_heading' => 'ผลงานวิจัยทั้งหมด',
    'fallback_category' => 'งานวิจัย', 'fallback_author' => 'ยังไม่ระบุผู้วิจัย',
];
require __DIR__ . '/includes/editorial-listing.php';
