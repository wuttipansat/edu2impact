<?php
require __DIR__ . '/includes/bootstrap.php';
$kind = 'news';
$listing = [
    'layout' => 'news', 'cta' => 'อ่านข่าว',
    'page_title' => 'ข่าวสาร',
    'noun' => 'ข่าว',
    'fallback_category' => 'ข่าวสาร', 'fallback_author' => '',
];
require __DIR__ . '/includes/editorial-listing.php';
