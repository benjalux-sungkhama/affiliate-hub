<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $code = trim($_POST['code'] ?? '') ?: ('AFH-' . strtoupper(bin2hex(random_bytes(3))));
        $st = db()->prepare(
            'INSERT INTO access_codes (code,label,max_uses,expires_at,is_active,created_by)
             VALUES (?,?,?,?,1,?)'
        );
        $st->execute([
            $code,
            trim($_POST['label'] ?? '') ?: null,
            max(1, (int)($_POST['max_uses'] ?? 1)),
            ($_POST['expires_at'] ?? '') !== '' ? $_POST['expires_at'] : null,
            uid(),
        ]);
        flash('สร้าง Access Code ' . $code . ' แล้ว');
    } elseif ($action === 'toggle') {
        $st = db()->prepare('UPDATE access_codes SET is_active = 1 - is_active WHERE id=?');
        $st->execute([(int)$_POST['id']]);
        flash('เปลี่ยนสถานะแล้ว');
    } elseif ($action === 'delete') {
        $st = db()->prepare('DELETE FROM access_codes WHERE id=?');
        $st->execute([(int)$_POST['id']]);
        flash('ลบ Access Code แล้ว');
    }
    redirect('access-codes.php');
}

$codes = db()->query('SELECT * FROM access_codes ORDER BY created_at DESC')->fetchAll();

$__title = 'รหัสเข้าใช้งาน';
$__active = 'codes';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3>+ ออก Access Code ใหม่</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <label>รหัส (เว้นว่างให้ระบบสุ่มให้)</label>
            <input name="code" placeholder="เช่น AFH-TEAM-01">
            <label>ป้ายกำกับ</label>
            <input name="label" placeholder="เช่น ทีมขายรอบ 1">
            <div class="form-row">
                <div>
                    <label>จำนวนครั้งที่ใช้ได้</label>
                    <input type="number" name="max_uses" value="1" min="1">
                </div>
                <div>
                    <label>วันหมดอายุ (เว้นว่าง = ไม่หมด)</label>
                    <input type="date" name="expires_at">
                </div>
            </div>
            <button class="btn btn-primary" style="margin-top:14px">ออกรหัส</button>
        </form>
    </div>

    <div class="card">
        <h3>วิธีใช้งาน</h3>
        <p class="muted">ส่งรหัสให้ผู้ใช้ใหม่นำไปกรอกที่หน้า <b>สมัครสมาชิก</b>
            รหัสหนึ่งใช้ได้ตามจำนวนครั้งที่กำหนด เมื่อครบจะสมัครเพิ่มไม่ได้</p>
        <p class="muted">ปิดใช้งานชั่วคราวได้ด้วยปุ่ม "ปิด/เปิด" โดยไม่ต้องลบ</p>
    </div>
</div>

<div class="table-wrap" style="margin-top:18px">
    <table>
        <thead><tr>
            <th>รหัส</th><th>ป้ายกำกับ</th><th>ใช้ไป/สูงสุด</th><th>หมดอายุ</th>
            <th>สถานะ</th><th>สร้างเมื่อ</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($codes as $c): ?>
            <tr>
                <td><code><?= e($c['code']) ?></code></td>
                <td><?= e($c['label'] ?: '—') ?></td>
                <td><?= (int)$c['used_count'] ?> / <?= (int)$c['max_uses'] ?></td>
                <td><?= e($c['expires_at'] ?: 'ไม่หมด') ?></td>
                <td><?= $c['is_active'] ? '<span class="badge badge-green">เปิด</span>' : '<span class="badge badge-gray">ปิด</span>' ?></td>
                <td><?= e(substr($c['created_at'], 0, 10)) ?></td>
                <td>
                    <div class="btn-row">
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm"><?= $c['is_active'] ? 'ปิด' : 'เปิด' ?></button>
                        </form>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm btn-danger" data-confirm="ลบรหัสนี้?">ลบ</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$codes): ?>
            <tr><td colspan="7" class="empty">ยังไม่มี Access Code</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
