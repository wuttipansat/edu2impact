<?php
// Header menu and footer sitemap (includes/header.php, includes/footer.php).
// Top level is either a link (`file`) or a group (`children`, each with `label`, `file`, `key`).
// A child `key` must equal that page's $currentPage; a group is active when any child is.
// includes/detail.php sets $currentPage = $kind, so detail pages keep their group active as well.
// "บริการของเรา" holds one page for now. It stays a dropdown so the menu keeps one shape and new services
// have a place to go.
return [
 ['label' => 'หน้าแรก', 'file' => 'index.php', 'key' => 'home'],
 ['label' => 'คลังความรู้', 'key' => 'group-knowledge', 'children' => [
  ['label' => 'งานวิจัยและบทความ', 'file' => 'research.php', 'key' => 'research'],
  ['label' => 'สื่อและคู่มือ', 'file' => 'media.php', 'key' => 'media'],
 ]],
 ['label' => 'นวัตกรรม', 'key' => 'group-innovation', 'children' => [
  ['label' => 'เครื่องมือออนไลน์', 'file' => 'tools.php', 'key' => 'tools'],
  ['label' => 'นวัตกรรมการศึกษา', 'file' => 'innovations.php', 'key' => 'innovations'],
 ]],
 ['label' => 'บริการของเรา', 'key' => 'group-services', 'children' => [
  ['label' => 'อบรมและเวิร์กช็อป', 'file' => 'workshops.php', 'key' => 'workshops'],
 ]],
 ['label' => 'เกี่ยวกับเรา', 'key' => 'group-about', 'children' => [
  ['label' => 'เกี่ยวกับเรา', 'file' => 'about.php', 'key' => 'about'],
  ['label' => 'ข่าวสาร', 'file' => 'news.php', 'key' => 'news'],
  ['label' => 'ติดต่อเรา', 'file' => 'contact.php', 'key' => 'contact'],
 ]],
];
