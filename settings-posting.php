<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $st = db()->prepare(
            'INSERT INTO post_schedules (user_id,platform_id,day_of_week,post_time) VALUES (?,?,?,?)'
        );
        $st->execute([
            $u, ($_POST['platform_id'] ?? '') !== '' ? (int)$_POST['platform_id'] : null,
            (int)($_POST['day_of_week'] ?? 1), ($_POST['post_time'] ?? '18:00'),
        ]);
        flash('เพิ่มช่วงเวลาโพสต์แล้ว');
    } elseif ($action === 'delete') {
        $st = db()->prepare('DELETE FROM post_schedules WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['id'], $u]);
        flash('ลบแล้ว');
    } elseif ($action === 'toggle') {
        $st = db()->prepare('UPDATE post_schedules SET is_active=1-is_active WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['id'], $u]);
    } elseif ($action === 'automation') {
        $mode = in_array($_POST['automation'] ?? 'off', ['off', 'simulate', 'live'], true) ? $_POST['automation'] : 'off';
        set_setting($u, 'automation', $mode);
        flash('บันทึกโหมด automation แล้ว: ' . $mode);
    }
    redirect('settings-posting.php');
}

$automation = get_setting($u, 'automation', 'off');

$platforms = db()->query('SELECT * FROM platforms ORDER BY id')->fetchAll();
$st = db()->prepare(
    'SELECT s.*, p.name platform FROM post_schedules s
     LEFT JOIN platforms p ON p.id=s.platform_id
     WHERE s.user_id=? ORDER BY s.day_of_week, s.post_time'
);
$st->execute([$u]);
$rows = $st->fetchAll();

$__title = 'ตั้งค่าการโพสต์ & ช่วงเวลา';
$__active = 'posting';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3>+ เพิ่มช่วงเวลาโพสต์อัตโนมัติ</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label>แพลตฟอร์ม</label>
            <select name="platform_id">
                <option value="">ทุกแพลตฟอร์ม</option>
                <?php foreach ($platforms as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-row">
                <div>
                    <label>วันในสัปดาห์</label>
                    <select name="day_of_week">
                        <?php foreach ($days as $i => $d): ?>
                            <option value="<?= $i ?>"><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>เวลา</label>
                    <input type="time" name="post_time" value="18:00">
                </div>
            </div>
            <button class="btn btn-primary" style="margin-top:14px">เพิ่มช่วงเวลา</button>
        </form>
    </div>
    <div class="card">
        <h3>⚙️ ระบบเผยแพร่อัตโนมัติ (Automation)</h3>
        <p class="muted">เมื่อเปิด ตัวรันเบื้องหลังจะหยิบโพสต์สถานะ <b>"เข้าคิว"</b> ที่ถึงเวลา มาเผยแพร่ให้เอง
            (ต้องตั้ง Windows Task Scheduler ให้รัน <code>cron/scheduler.php</code> — ดู <code>docs/AUTOMATION.md</code>)</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="automation">
            <label>โหมด</label>
            <select name="automation">
                <option value="off" <?= $automation === 'off' ? 'selected' : '' ?>>ปิด — กดเผยแพร่เองในหน้าโพสต์</option>
                <option value="simulate" <?= $automation === 'simulate' ? 'selected' : '' ?>>ทดสอบ (simulate) — เดินคิวอัตโนมัติ แต่ไม่ยิง API จริง</option>
                <option value="live" <?= $automation === 'live' ? 'selected' : '' ?>>ใช้งานจริง (live) — ยิง API แพลตฟอร์มจริง (ต้องมี Access Token)</option>
            </select>
            <p class="hint">โหมด live ต้องเชื่อมบัญชีพร้อมใส่ Page ID + Access Token ที่หน้า <b>แพลตฟอร์ม & บัญชี</b> ก่อน</p>
            <button class="btn btn-primary" style="margin-top:14px">บันทึกโหมด</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <h3>คำแนะนำเวลาโพสต์</h3>
    <p class="muted">ช่วงเข้าถึงสูงของไทยโดยทั่วไป: <b>11:30–13:00</b> และ <b>18:00–21:00</b>
        ตั้งช่วงเวลาให้ตรงกลุ่มเป้าหมายแล้วจัดคิวโพสต์ตามนี้ · ดูช่วงเวลาที่ได้ผลจริงที่เมนู <b>วิเคราะห์ & แนะนำ Boost</b></p>
</div>

<div class="table-wrap" style="margin-top:18px">
    <table>
        <thead><tr><th>วัน</th><th>เวลา</th><th>แพลตฟอร์ม</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($days[$r['day_of_week']] ?? '-') ?></td>
                <td><?= e(substr($r['post_time'], 0, 5)) ?></td>
                <td><?= e($r['platform'] ?: 'ทุกแพลตฟอร์ม') ?></td>
                <td><?= $r['is_active'] ? '<span class="badge badge-green">เปิด</span>' : '<span class="badge badge-gray">ปิด</span>' ?></td>
                <td><div class="btn-row">
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm"><?= $r['is_active'] ? 'ปิด' : 'เปิด' ?></button>
                    </form>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-danger" data-confirm="ลบช่วงเวลานี้?">ลบ</button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">ยังไม่มีช่วงเวลาโพสต์</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
