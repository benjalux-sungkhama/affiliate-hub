<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    redirect('dashboard.php');
}
$msg = '';
$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $step = $_POST['step'] ?? 'request';

    if ($step === 'request') {
        $email = trim($_POST['email'] ?? '');
        $st = db()->prepare('SELECT id FROM users WHERE email=? AND is_active=1');
        $st->execute([$email]);
        if ($id = $st->fetchColumn()) {
            $token = bin2hex(random_bytes(16));
            $exp = date('Y-m-d H:i:s', time() + 3600);
            $up = db()->prepare('UPDATE users SET reset_token=?, reset_expires=? WHERE id=?');
            $up->execute([$token, $exp, $id]);
            // สภาพแวดล้อม XAMPP มักยังไม่ตั้งค่าอีเมล — แสดงลิงก์รีเซ็ตให้ใช้ได้ทันที
            $msg = 'สร้างลิงก์รีเซ็ตแล้ว (ในระบบจริงจะส่งเข้าอีเมล) ใช้แบบฟอร์มด้านล่างเพื่อตั้งรหัสใหม่';
        } else {
            $msg = 'ถ้ามีอีเมลนี้ในระบบ จะได้รับลิงก์รีเซ็ต';
        }
    } elseif ($step === 'reset') {
        $token = trim($_POST['token'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $st = db()->prepare('SELECT id FROM users WHERE reset_token=? AND reset_expires > NOW()');
        $st->execute([$token]);
        if (($id = $st->fetchColumn()) && strlen($pass) >= 6) {
            $up = db()->prepare('UPDATE users SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE id=?');
            $up->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('ตั้งรหัสผ่านใหม่สำเร็จ เข้าสู่ระบบได้เลย');
            redirect('login.php');
        }
        $msg = 'โทเคนไม่ถูกต้อง/หมดอายุ หรือรหัสผ่านสั้นเกินไป (อย่างน้อย 6 ตัว)';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ลืมรหัสผ่าน · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="auth">
<div class="auth-card">
    <h2>ลืมรหัสผ่าน</h2>
    <?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="request">
        <label>อีเมลที่สมัครไว้</label>
        <input type="email" name="email" required>
        <button class="btn btn-primary">ขอลิงก์รีเซ็ต</button>
    </form>

    <hr style="margin:22px 0;border:0;border-top:1px solid var(--line)">

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="reset">
        <label>โทเคนรีเซ็ต</label>
        <input name="token" value="<?= e($token) ?>" required>
        <label>รหัสผ่านใหม่</label>
        <input type="password" name="password" required>
        <button class="btn btn-primary">ตั้งรหัสผ่านใหม่</button>
    </form>
    <div class="switch"><a href="<?= url('login.php') ?>">กลับไปเข้าสู่ระบบ</a></div>
</div>
</body>
</html>
