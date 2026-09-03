<?php
require_once __DIR__ . '/auth.php';
require_login();
$__title = $__title ?? APP_NAME;
$__active = $__active ?? '';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($__title) ?> · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main">
        <header class="topbar">
            <button class="menu-toggle" onclick="document.body.classList.toggle('nav-open')">☰</button>
            <h1 class="page-title"><?= e($__title) ?></h1>
            <div class="topbar-user">
                <span class="hello">สวัสดี, <b><?= e($_SESSION['name'] ?? '') ?></b></span>
                <?php if (is_admin()): ?><span class="badge badge-purple">แอดมิน</span><?php endif; ?>
                <a class="btn btn-ghost btn-sm" href="<?= url('logout.php') ?>">ออกจากระบบ</a>
            </div>
        </header>
        <div class="content">
            <?php foreach ((array)flash() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
            <?php endforeach; ?>
