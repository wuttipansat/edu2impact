<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'innovations';
$listing = [
    'layout' => 'innovations', 'cta' => 'ดูนวัตกรรม',
    'page_title' => 'นวัตกรรมการศึกษา',
    'latest_heading' => 'นวัตกรรมล่าสุด', 'noun' => 'รายการ',
    'library_heading' => 'นวัตกรรมทั้งหมด',
    'fallback_category' => 'นวัตกรรม', 'fallback_author' => 'ยังไม่ระบุทีมผู้พัฒนา',
];
require __DIR__ . '/includes/editorial-listing.php';
