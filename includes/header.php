<?php
$pageTitle = $pageTitle ?? $site['name'];
$pageDescription = $pageDescription ?? $site['description'];
$currentPage = $currentPage ?? '';
try { $navUser = auth_user(); } catch (Throwable $exception) { auth_service_error($exception); }
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
<a class="button nav-cta" href="register-impact.php"<?= $currentPage === 'register' ? ' aria-current="page"' : '' ?>>Register Impact</a>
<?php if ($navUser): ?><a href="account.php"<?= $currentPage === 'account' ? ' aria-current="page"' : '' ?>>บัญชีของฉัน</a><?php else: ?><a href="login.php"<?= $currentPage === 'login' ? ' aria-current="page"' : '' ?>>Login</a><?php endif; ?>
</nav></div></header>
<?php if (!empty($site['demo_mode'])): ?><div class="demo-bar"><div class="container">เว็บไซต์ตัวอย่าง — ผลงานตัวอย่างยังไม่ใช่ข้อมูลที่ผ่านการตรวจสอบ</div></div><?php endif; ?>
