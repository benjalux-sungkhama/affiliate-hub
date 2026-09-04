<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/automation.php';
require_login();
$u = uid();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $st = $pdo->prepare(
            'INSERT INTO live_sessions (user_id,platform_id,title,started_at,ended_at,peak_viewers,total_sales,notes)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $u, ($_POST['platform_id'] ?? '') !== '' ? (int)$_POST['platform_id'] : null,
            trim($_POST['title'] ?? ''), $_POST['started_at'] ?: null, $_POST['ended_at'] ?: null,
            (int)($_POST['peak_viewers'] ?? 0), (float)($_POST['total_sales'] ?? 0),
            trim($_POST['notes'] ?? '') ?: null,
        ]);
        flash('บันทึกรอบไลฟ์แล้ว');
        // ทริกเกอร์ live.ended — เมื่อบันทึกรอบไลฟ์ที่มีเวลาจบแล้ว
        if (($_POST['ended_at'] ?? '') !== '') {
            automation_dispatch_event($pdo, $u, 'live.ended', [
                'live.title'   => trim($_POST['title'] ?? ''),
                'time.hour'    => (int)date('G'),
                'product.name' => 'สรุปไลฟ์: ' . trim($_POST['title'] ?? ''),
                'product.usp'  => 'ยอดขายไลฟ์ ฿' . money((float)($_POST['total_sales'] ?? 0)),
                'product.target' => 'ผู้ชมไลฟ์',
                'cta'          => 'รอไลฟ์รอบหน้า กดติดตามเลย',
            ]);
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM live_sessions WHERE id=? AND user_id=?')->execute([(int)$_POST['id'], $u]);
        flash('ลบรอบไลฟ์แล้ว');
    }
    redirect('live.php');
}

$platforms = $pdo->query('SELECT * FROM platforms ORDER BY id')->fetchAll();
$st = $pdo->prepare('SELECT l.*, p.name platform FROM live_sessions l LEFT JOIN platforms p ON p.id=l.platform_id WHERE l.user_id=? ORDER BY l.started_at DESC, l.created_at DESC');
$st->execute([$u]);
$lives = $st->fetchAll();

// ช่วงเวลาขายดี (จากชั่วโมงเริ่มไลฟ์ ถ่วงด้วยยอดขาย)
$byHour = [];
foreach ($lives as $l) {
    if ($l['started_at']) {
        $h = (int)substr($l['started_at'], 11, 2);
        $byHour[$h] = ($byHour[$h] ?? 0) + $l['total_sales'];
    }
}
arsort($byHour);
$bestHour = array_key_first($byHour);

$__title = 'ไลฟ์สด';
$__active = 'live';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card stat"><div class="label">จำนวนรอบไลฟ์</div><div class="value"><?= count($lives) ?></div></div>
    <div class="card stat"><div class="label">ยอดขายไลฟ์รวม</div><div class="value">฿<?= money(array_sum(array_column($lives, 'total_sales'))) ?></div></div>
    <div class="card stat"><div class="label">ผู้ชมสูงสุด</div><div class="value"><?= number_format($lives ? max(array_column($lives, 'peak_viewers')) : 0) ?></div></div>
    <div class="card stat"><div class="label">ช่วงเวลาขายดี</div><div class="value"><?= $bestHour !== null ? sprintf('%02d:00', $bestHour) : '—' ?></div></div>
</div>

<div class="card" style="margin-bottom:18px">
    <h3>+ บันทึกรอบไลฟ์</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="form-row">
            <div><label>หัวข้อไลฟ์</label><input name="title" required></div>
            <div><label>แพลตฟอร์ม</label><select name="platform_id"><option value="">—</option>
                <?php foreach ($platforms as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-row">
            <div><label>เริ่ม</label><input type="datetime-local" name="started_at"></div>
            <div><label>จบ</label><input type="datetime-local" name="ended_at"></div>
        </div>
        <div class="form-row">
            <div><label>ผู้ชมสูงสุด</label><input type="number" name="peak_viewers" value="0"></div>
            <div><label>ยอดขายรวม (บาท)</label><input type="number" step="0.01" name="total_sales" value="0"></div>
        </div>
        <label>บันทึกช่วยจำ (สินค้าที่ปัง ฯลฯ)</label>
        <textarea name="notes"></textarea>
        <button class="btn btn-primary" style="margin-top:14px">บันทึก</button>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>หัวข้อ</th><th>แพลตฟอร์ม</th><th>เริ่ม</th><th>ผู้ชมสูงสุด</th><th>ยอดขาย</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lives as $l): ?>
            <tr>
                <td class="wrap"><?= e($l['title']) ?><?php if ($l['notes']): ?><div class="muted" style="font-size:12px"><?= e($l['notes']) ?></div><?php endif; ?></td>
                <td><?= e($l['platform'] ?: '—') ?></td>
                <td><?= e($l['started_at'] ? substr($l['started_at'], 0, 16) : '—') ?></td>
                <td><?= number_format($l['peak_viewers']) ?></td>
                <td>฿<?= money($l['total_sales']) ?></td>
                <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="ลบรอบไลฟ์นี้?">ลบ</button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lives): ?><tr><td colspan="6" class="empty">ยังไม่มีรอบไลฟ์</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
