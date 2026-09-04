<?php
/**
 * การยืนยันตัวตน + session + การแยกข้อมูลด้วย user_id
 */
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ล็อกอินด้วย email + password */
function attempt_login(string $email, string $password): bool
{
    $st = db()->prepare('SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid']  = (int)$u['id'];
        $_SESSION['role'] = $u['role'];
        $_SESSION['name'] = $u['name'];
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['uid']);
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** user_id ปัจจุบัน — ใช้ filter ทุก query เพื่อแยกข้อมูล 100% */
function uid(): int
{
    return (int)($_SESSION['uid'] ?? 0);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    static $u = null;
    if ($u === null) {
        $st = db()->prepare('SELECT * FROM users WHERE id=?');
        $st->execute([uid()]);
        $u = $st->fetch() ?: null;
    }
    return $u;
}

/** บังคับให้ล็อกอินก่อน */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/** บังคับสิทธิ์แอดมิน */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('เฉพาะแอดมินเท่านั้น');
    }
}

/**
 * สมัครสมาชิกด้วย Access Code
 * @return array [bool ok, string message]
 */
function register_with_code(string $name, string $email, string $password, string $code): array
{
    $pdo = db();
    // ตรวจ access code
    $st = $pdo->prepare('SELECT * FROM access_codes WHERE code=? AND is_active=1 LIMIT 1');
    $st->execute([$code]);
    $ac = $st->fetch();
    if (!$ac) {
        return [false, 'Access Code ไม่ถูกต้องหรือถูกปิดใช้งาน'];
    }
    if ($ac['expires_at'] !== null && $ac['expires_at'] < date('Y-m-d')) {
        return [false, 'Access Code หมดอายุแล้ว'];
    }
    if ($ac['used_count'] >= $ac['max_uses']) {
        return [false, 'Access Code ถูกใช้ครบจำนวนแล้ว'];
    }
    // ตรวจอีเมลซ้ำ
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE email=?');
    $chk->execute([$email]);
    if ($chk->fetchColumn()) {
        return [false, 'อีเมลนี้ถูกใช้สมัครแล้ว'];
    }

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO users (name,email,password_hash,role,label) VALUES (?,?,?,?,?)'
        );
        $ins->execute([
            $name, $email, password_hash($password, PASSWORD_DEFAULT), 'user', $ac['label'],
        ]);
        $upd = $pdo->prepare('UPDATE access_codes SET used_count = used_count + 1 WHERE id=?');
        $upd->execute([$ac['id']]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        return [false, 'สมัครไม่สำเร็จ: ' . $ex->getMessage()];
    }
    return [true, 'สมัครสำเร็จ! เข้าสู่ระบบได้เลย'];
}
