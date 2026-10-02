<?php
$pageTitle = $pageTitle ?? $site['name'];
$pageDescription = $pageDescription ?? $site['description'];
$currentPage = $currentPage ?? '';
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($pageDescription) ?>">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/main.css">
<?php if ($currentPage === 'home'): ?><link rel="stylesheet" href="assets/css/home.css"><?php endif; ?>
<script src="assets/js/main.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปเนื้อหา</a>
<header class="header"><div class="container header-inner">
<a class="brand" href="index.php" aria-label="<?= e($site['name']) ?> หน้าแรก">edu<span>2</span>impact<span class="brand-dot">.</span></a>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav" hidden>เมนู ☰</button>
<nav id="main-nav" class="nav" aria-label="เมนูหลัก">
<?php foreach ($nav as $item): ?><a href="<?= e($item['file']) ?>"<?= $currentPage === $item['key'] ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a><?php endforeach; ?>
</nav></div></header>