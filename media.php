<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'media';
$listing = [
    'layout' => 'media', 'cta' => 'เปิดอ่าน',
    'page_title' => 'สื่อและคู่มือ',
    'noun' => 'รายการ',
    'fallback_category' => 'สื่อและคู่มือ', 'fallback_author' => '',
];
require __DIR__ . '/includes/editorial-listing.php';
