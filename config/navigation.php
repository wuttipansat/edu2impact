<?php
// Header menu and footer sitemap (includes/header.php, includes/footer.php).
// Top level is either a link (`file`) or a group (`children`, each with `label`, `file`, `key`).
// A child `key` must equal that page's $currentPage; a group is active when any child is.
// includes/detail.php sets $currentPage = $kind, so detail pages keep their group active as well.
// Header: a group is also active when $currentPage is in its `also` list. Pages that belong to a child under another
// key set $navKey (see innovations.php, includes/detail.php).
$innovationMenu = [];
foreach (require __DIR__ . '/innovation-categories.php' as $slug => $name) {
 $innovationMenu[] = ['label' => $name, 'file' => 'innovations.php?category=' . $slug, 'key' => 'innovations-' . $slug];
}
return [
 ['label' => 'หน้าแรก', 'file' => 'index.php', 'key' => 'home'],
 ['label' => 'คลังความรู้', 'key' => 'group-knowledge', 'children' => [
  ['label' => 'งานวิจัยและบทความ', 'file' => 'research.php', 'key' => 'research'],
  ['label' => 'สื่อและคู่มือ', 'file' => 'media.php', 'key' => 'media'],
  ['label' => 'เครื่องมือออนไลน์', 'file' => 'tools.php', 'key' => 'tools'],
 ]],
 // One child per category from config/innovation-categories.php (built below). `also` keeps the group active
 // on the page that lists every innovation.
 ['label' => 'นวัตกรรม', 'key' => 'group-innovation', 'also' => ['innovations'], 'children' => $innovationMenu],
 ['label' => 'บริการของเรา', 'key' => 'group-services', 'children' => [
  ['label' => 'อบรมและเวิร์กช็อป', 'file' => 'workshops.php', 'key' => 'workshops'],
  ['label' => 'ร้านค้า', 'file' => 'shop.php', 'key' => 'shop'],
 ]],
 ['label' => 'เกี่ยวกับเรา', 'key' => 'group-about', 'children' => [
  ['label' => 'เกี่ยวกับเรา', 'file' => 'about.php', 'key' => 'about'],
  ['label' => 'ติดต่อเรา', 'file' => 'contact.php', 'key' => 'contact'],
 ]],
];
