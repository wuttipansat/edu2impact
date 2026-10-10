<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'innovations';
$listing = [
    'layout' => 'innovations', 'cta' => 'ดูนวัตกรรม',
    'page_title' => 'Innovation',
    'latest_kicker' => 'LATEST INNOVATION', 'latest_heading' => 'นวัตกรรมล่าสุด', 'noun' => 'innovations',
    'library_kicker' => 'INNOVATION LIBRARY', 'library_heading' => 'นวัตกรรมทั้งหมด',
    'fallback_category' => 'Innovation', 'fallback_author' => 'Team to be added',
];
require __DIR__ . '/includes/editorial-listing.php';
