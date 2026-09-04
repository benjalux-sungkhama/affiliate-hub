<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $st = $pdo->prepare('UPDATE users SET name=? WHERE id=?');
        $st->execute([$name, $u]);
        $_SESSION['name'] = $name;
        // เปลี่ยนรหัสผ่านถ้ากรอกมา
        if (($_POST['password'] ?? '') !== '') {
            if (strlen($_POST['password']) >= 6) {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                    ->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $u]);
                flash('อัปเดตโปรไฟล์ + เปลี่ยนรหัสผ่านแล้ว');
            } else {
                flash('รหัสผ่านต้องยาวอย่างน้อย 6 ตัว — อัปเดตชื่อแล้วแต่ไม่เปลี่ยนรหัส', 'err');
            }
        } else {
            flash('อัปเดตโปรไฟล์แล้ว');
        }
    } elseif ($action === 'ai') {
        set_setting($u, 'ai_provider', $_POST['ai_provider'] ?? 'qwen');
        set_setting($u, 'ai_api_key', trim($_POST['ai_api_key'] ?? ''));
        set_setting($u, 'ai_model', trim($_POST['ai_model'] ?? ''));
        flash('บันทึกการเชื่อมต่อ AI แล้ว');
    }
    redirect('settings.php');
}

$aiProvider = get_setting($u, 'ai_provider', 'qwen');
$aiKey = get_setting($u, 'ai_api_key', '');
$aiModel = get_setting($u, 'ai_model', '');

$__title = 'โปรไฟล์ & การเชื่อมต่อ AI';
$__active = 'settings';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3>โปรไฟล์</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <label>ชื่อที่แสดง</label>
            <input name="name" value="<?= e($me['name']) ?>">
            <label>อีเมล</label>
            <input value="<?= e($me['email']) ?>" disabled>
            <label>รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label>
            <input type="password" name="password" placeholder="อย่างน้อย 6 ตัว">
            <button class="btn btn-primary" style="margin-top:16px">บันทึกโปรไฟล์</button>
        </form>
    </div>

    <div class="card">
        <h3>การเชื่อมต่อ AI</h3>
        <p class="muted">ใส่ API Key เพื่อให้หน้า "ให้ AI คิดคอนเทนต์" เรียกโมเดลจริงได้ (เก็บแยกตามผู้ใช้)</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ai">
            <label>ผู้ให้บริการ</label>
            <select name="ai_provider">
                <?php foreach (['qwen' => 'Qwen', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'claude' => 'Anthropic Claude'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $aiProvider === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
            <label>API Key</label>
            <input name="ai_api_key" value="<?= e($aiKey) ?>" placeholder="วาง API Key ที่นี่">
            <label>โมเดล (ถ้ามี)</label>
            <input name="ai_model" value="<?= e($aiModel) ?>" placeholder="เช่น qwen-plus">
            <button class="btn btn-primary" style="margin-top:16px">บันทึกการเชื่อมต่อ AI</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <h3>การแยกข้อมูล (Data Isolation)</h3>
    <p class="muted">ทุกหน้าดึงข้อมูลด้วย <code>user_id</code> ของคุณเท่านั้น รวมถึงตาราง
        <code>content_formulas</code>, <code>content_formula_scenes</code>, <code>content_formula_usages</code>
        — ข้อมูลของผู้ใช้แต่ละคนแยกกัน 100%</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
