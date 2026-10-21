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
<?php if (!empty($metaRobots)): ?><meta name="robots" content="<?= e($metaRobots) ?>"><?php endif; ?>
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
<link rel="stylesheet" href="assets/css/main.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/main.css') ?>">
<link rel="stylesheet" href="assets/css/ku-overrides.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/ku-overrides.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if ($currentPage === 'home'): ?><link rel="stylesheet" href="assets/css/home.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/home.css') ?>"><?php endif; ?>
<?php if ($currentPage === 'home'): ?><link rel="stylesheet" href="assets/css/home-overrides.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/home-overrides.css') ?>"><?php endif; ?>
<link rel="stylesheet" href="assets/css/modern-theme.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/modern-theme.css') ?>">
<link rel="stylesheet" href="assets/css/minimal-palette.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/minimal-palette.css') ?>">
<link rel="stylesheet" href="assets/css/search.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/search.css') ?>">
<?php if (!empty($postStyles)): ?><link rel="stylesheet" href="assets/css/post-byline.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/post-byline.css') ?>"><?php endif; ?>
<?php if (!empty($listingStyles)): ?><link rel="stylesheet" href="assets/css/listing.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/listing.css') ?>"><?php endif; ?>
<link rel="stylesheet" href="assets/css/refinements.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/refinements.css') ?>">
<script src="assets/js/main.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">ข้ามไปเนื้อหา</a>
<header class="header"><div class="header-glow" aria-hidden="true"></div><div class="container header-inner">
<div class="brand-block"><a class="brand brand--image" href="index.php" aria-label="<?= e($site['name']) ?> หน้าแรก"><img class="brand-logo" src="assets/images/header-logo.png" alt="Faculty of Education"></a></div>
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
</nav>
<a class="header-search" href="search.php" aria-label="ค้นหา" aria-haspopup="dialog" aria-controls="site-search"<?= $currentPage === 'search' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></a>
</div></header>
<dialog id="site-search" class="search-dialog" aria-label="ค้นหาทั้งเว็บไซต์">
<div class="search-dialog__panel">
<div class="search-dialog__top">
<form class="search-dialog__form" method="get" action="search.php" role="search">
<label class="visually-hidden" for="site-search-q">ค้นหาทั้งเว็บไซต์</label>
<div class="search-box search-dialog__field"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input id="site-search-q" type="search" name="q" autocomplete="off" maxlength="100" placeholder="ค้นหางานวิจัย นวัตกรรม เวิร์กช็อป…"></div>
<button class="button search-dialog__submit" type="submit"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><span>ค้นหา</span></button>
</form>
<button class="search-dialog__close" type="button" aria-label="ปิด" data-search-close><span aria-hidden="true">×</span></button>
</div>
</div>
</dialog>
<?php if (!empty($site['demo_mode'])): ?><div class="demo-bar" role="note"><div class="container">ข้อมูลในเว็บไซต์นี้เป็นตัวอย่าง อยู่ระหว่างพัฒนา</div></div><?php endif; ?>
