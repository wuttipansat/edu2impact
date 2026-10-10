<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'research';
$listing = [
    'layout' => 'research', 'cta' => 'อ่านงานวิจัย',
    'page_title' => 'Research',
    'latest_kicker' => 'LATEST RESEARCH', 'latest_heading' => 'ผลงานวิจัยล่าสุด', 'noun' => 'works',
    'library_kicker' => 'RESEARCH LIBRARY', 'library_heading' => 'ผลงานวิจัยทั้งหมด',
    'fallback_category' => 'Research', 'fallback_author' => 'Researcher to be added',
];
require __DIR__ . '/includes/editorial-listing.php';
