<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'workshops';
$listing = [
    'layout' => 'workshops', 'cta' => 'ดูรายละเอียด',
    'page_title' => 'Workshop',
    'latest_kicker' => 'UPCOMING WORKSHOPS', 'latest_heading' => 'เวิร์กช็อปล่าสุด', 'noun' => 'workshops',
    'library_kicker' => 'WORKSHOP LIBRARY', 'library_heading' => 'เวิร์กช็อปทั้งหมด',
    'fallback_category' => 'Workshop', 'fallback_author' => 'Facilitator to be added',
];
require __DIR__ . '/includes/editorial-listing.php';
