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
<link rel="stylesheet" href="assets/css/ku-overrides.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if ($currentPage === 'home'): ?><link rel="stylesheet" href="assets/css/home.css"><?php endif; ?>
<?php if ($currentPage === 'home'): ?><link rel="stylesheet" href="assets/css/home-overrides.css"><?php endif; ?>
<link rel="stylesheet" href="assets/css/modern-theme.css">
<link rel="stylesheet" href="assets/css/minimal-palette.css">
<script src="assets/js/main.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปเนื้อหา</a>
<header class="header"><div class="header-glow" aria-hidden="true"></div><div class="container header-inner">
<div class="brand-block"><a class="brand brand--image" href="index.php" aria-label="<?= e($site['name']) ?> หน้าแรก"><img class="brand-logo" src="assets/images/header-logo.png" alt="Faculty of Education"></a><span class="brand-caption">Research to Impact</span></div>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav" hidden>เมนู ☰</button>
<nav id="main-nav" class="nav" aria-label="เมนูหลัก">
<?php foreach ($nav as $item): ?>
<?php $active = $currentPage === $item['key'] || in_array($currentPage, array_column($item['children'] ?? [], 'key'), true); ?>
<?php if (!empty($item['children'])): ?>
<details class="nav-group"<?= $active ? ' data-active="true"' : '' ?>>
<summary><?= e($item['label']) ?></summary>
<div class="nav-submenu"><?php foreach ($item['children'] as $child): ?>
<a href="<?= e($child['file']) ?>"<?= $currentPage === $child['key'] ? ' aria-current="page"' : '' ?>><?= e($child['label']) ?></a>
<?php endforeach; ?></div></details>
<?php else: ?><a href="<?= e($item['file']) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a><?php endif; ?>
<?php endforeach; ?>
</nav></div></header>
