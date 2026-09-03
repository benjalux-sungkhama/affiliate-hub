<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    redirect('dashboard.php');
}
$err = '';
$ok  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $code  = trim($_POST['code'] ?? '');
    if ($name === '' || $email === '' || strlen($pass) < 6 || $code === '') {
        $err = 'กรอกข้อมูลให้ครบ และรหัสผ่านอย่างน้อย 6 ตัวอักษร';
    } else {
        [$success, $msg] = register_with_code($name, $email, $pass, $code);
        $success ? $ok = $msg : $err = $msg;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>สมัครสมาชิก · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="auth">
<form class="auth-card" method="post">
    <div class="auth-brand">
        <span class="brand-mark">A</span>
        <span class="brand-name" style="color:var(--ink)"><?= APP_NAME ?></span>
    </div>
    <h2>สมัครสมาชิก</h2>
    <p class="sub">ต้องมี <b>Access Code</b> จากแอดมิน</p>
    <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
    <?php if ($ok): ?>
        <div class="alert alert-ok"><?= e($ok) ?></div>
        <a class="btn btn-primary" style="width:100%;justify-content:center" href="<?= url('login.php') ?>">ไปหน้าเข้าสู่ระบบ</a>
    <?php else: ?>
        <?= csrf_field() ?>
        <label>ชื่อที่แสดง</label>
        <input name="name" required value="<?= e($_POST['name'] ?? '') ?>">
        <label>อีเมล</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
        <label>รหัสผ่าน (อย่างน้อย 6 ตัว)</label>
        <input type="password" name="password" required>
        <label>Access Code</label>
        <input name="code" required placeholder="เช่น AFH-START-2026" value="<?= e($_POST['code'] ?? '') ?>">
        <button class="btn btn-primary">สมัครสมาชิก</button>
        <div class="switch">มีบัญชีแล้ว? <a href="<?= url('login.php') ?>">เข้าสู่ระบบ</a></div>
    <?php endif; ?>
</form>
</body>
</html>
