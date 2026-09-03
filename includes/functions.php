<?php
/**
 * ฟังก์ชันช่วยเหลือทั่วไป
 */
require_once __DIR__ . '/../config/db.php';

/** escape สำหรับ HTML */
function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** สร้าง URL ภายในระบบ */
function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/** redirect ไปหน้าอื่น */
function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** จัดรูปแบบเงินบาท */
function money($n): string
{
    return number_format((float)$n, 2);
}

/** flash message เก็บใน session */
function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
        return;
    }
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $out;
}

/** ตรวจ CSRF token */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        die('เซสชันหมดอายุ (CSRF) — โปรดกลับไปโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

/** แทนที่ตัวแปร {{ }} ในสูตรคอนเทนต์ */
function fill_variables(string $text, array $vars): string
{
    return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($vars) {
        return $vars[$m[1]] ?? $m[0];
    }, $text);
}

/** หาตัวแปร {{ }} ที่ยังไม่ถูกเติมค่า */
function missing_variables(string $text, array $vars): array
{
    preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $m);
    $missing = [];
    foreach ($m[1] as $key) {
        if (empty($vars[$key]) && !in_array($key, $missing, true)) {
            $missing[] = $key;
        }
    }
    return $missing;
}

/** อ่านค่า setting ของผู้ใช้ */
function get_setting(int $userId, string $key, $default = null)
{
    $st = db()->prepare('SELECT svalue FROM settings WHERE user_id=? AND skey=?');
    $st->execute([$userId, $key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}

/** เขียนค่า setting ของผู้ใช้ */
function set_setting(int $userId, string $key, $value): void
{
    $st = db()->prepare(
        'INSERT INTO settings (user_id, skey, svalue) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)'
    );
    $st->execute([$userId, $key, $value]);
}

/** ป้ายสีสถานะ */
function status_badge(string $status): string
{
    $map = [
        'draft' => ['ฉบับร่าง', 'gray'], 'queued' => ['เข้าคิว', 'blue'],
        'published' => ['เผยแพร่แล้ว', 'green'], 'failed' => ['ล้มเหลว', 'red'],
        'new' => ['ใหม่', 'blue'], 'packed' => ['แพ็คแล้ว', 'purple'],
        'shipped' => ['ส่งแล้ว', 'orange'], 'delivered' => ['ส่งสำเร็จ', 'green'],
        'returned' => ['ตีกลับ', 'red'], 'cancelled' => ['ยกเลิก', 'gray'],
        'preparing' => ['เตรียมของ', 'gray'], 'picked_up' => ['รับพัสดุแล้ว', 'blue'],
        'in_transit' => ['กำลังส่ง', 'orange'], 'open' => ['เปิดรอบ', 'blue'],
        'handed' => ['ส่งมอบแล้ว', 'purple'], 'settled' => ['เคลียร์เงินแล้ว', 'green'],
    ];
    [$label, $color] = $map[$status] ?? [$status, 'gray'];
    return '<span class="badge badge-' . $color . '">' . e($label) . '</span>';
}
