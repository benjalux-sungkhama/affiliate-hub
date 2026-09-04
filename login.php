<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    redirect('dashboard.php');
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (attempt_login($email, $pass)) {
        redirect('dashboard.php');
    }
    $err = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="auth">
<form class="auth-card" method="post">
    <div class="auth-brand">
        <span class="brand-mark">A</span>
        <span class="brand-name" style="color:var(--ink)"><?= APP_NAME ?></span>
    </div>
    <h2>เข้าสู่ระบบ</h2>
    <p class="sub">การตลาด · ไลฟ์ · จัดส่ง ในระบบเดียว</p>
    <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>อีเมล</label>
    <input type="email" name="email" required autofocus placeholder="you@example.com">
    <label>รหัสผ่าน</label>
    <input type="password" name="password" required placeholder="••••••••">
    <button class="btn btn-primary">เข้าสู่ระบบ</button>
    <div class="switch">
        ยังไม่มีบัญชี? <a href="<?= url('register.php') ?>">สมัครด้วย Access Code</a><br>
        <a href="<?= url('forgot-password.php') ?>">ลืมรหัสผ่าน?</a>
    </div>
</form>
</body>
</html>
