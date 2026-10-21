<?php
// Keys match dataset names: includes/detail.php sets $currentPage = $kind and links back to $kind.php.
return [
 ['label' => 'Home', 'file' => 'index.php', 'key' => 'home'],
 ['label' => 'Research', 'file' => 'research.php', 'key' => 'research'],
 ['label' => 'Innovation', 'file' => 'innovations.php', 'key' => 'innovations'],
 ['label' => 'Workshop', 'file' => 'workshops.php', 'key' => 'workshops'],
// Partners hidden from header nav (page still reachable at partners.php)
 // ['label' => 'Partners', 'file' => 'partners.php', 'key' => 'partners'],
 ['label' => 'About', 'file' => 'about.php', 'key' => 'about'],
];
