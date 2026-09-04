<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_driver') {
        $st = $pdo->prepare('INSERT INTO drivers (user_id,name,phone,vehicle) VALUES (?,?,?,?)');
        $st->execute([$u, trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? '') ?: null, trim($_POST['vehicle'] ?? '') ?: null]);
        flash('เพิ่มคนขับแล้ว');
    } elseif ($action === 'del_driver') {
        $pdo->prepare('DELETE FROM drivers WHERE id=? AND user_id=?')->execute([(int)$_POST['id'], $u]);
        flash('ลบคนขับแล้ว');
    } elseif ($action === 'create_round') {
        $driverId = ($_POST['driver_id'] ?? '') !== '' ? (int)$_POST['driver_id'] : null;
        $date = $_POST['round_date'] ?: date('Y-m-d');
        $pdo->beginTransaction();
        try {
            $manifest = 'MF' . date('ymd', strtotime($date)) . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
            $ins = $pdo->prepare(
                'INSERT INTO pickup_rounds (user_id,driver_id,round_date,manifest_code,status) VALUES (?,?,?,?,?)'
            );
            $ins->execute([$u, $driverId, $date, $manifest, 'open']);
            $roundId = (int)$pdo->lastInsertId();
            // ผูกพัสดุที่ยังไม่ถูกจัดรอบ + สถานะเตรียมของ → รับพัสดุแล้ว
            $pdo->prepare(
                "UPDATE shipments SET pickup_round_id=?, status='picked_up', shipped_at=COALESCE(shipped_at,NOW())
                 WHERE user_id=? AND pickup_round_id IS NULL AND status='preparing'"
            )->execute([$roundId, $u]);
            // สรุปจำนวน + ยอด COD
            $sum = $pdo->prepare(
                'SELECT COUNT(*) n, COALESCE(SUM(c.amount),0) cod
                 FROM shipments s
                 LEFT JOIN cod_records c ON c.order_id=s.order_id
                 WHERE s.pickup_round_id=? AND s.user_id=?'
            );
            $sum->execute([$roundId, $u]);
            $r = $sum->fetch();
            $pdo->prepare('UPDATE pickup_rounds SET parcel_count=?, cod_total=? WHERE id=?')
                ->execute([(int)$r['n'], (float)$r['cod'], $roundId]);
            $pdo->commit();
            flash('สร้างรอบเข้ารับ + ใบส่งมอบ ' . $manifest . ' (' . (int)$r['n'] . ' พัสดุ)');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('สร้างไม่สำเร็จ: ' . $ex->getMessage(), 'err');
        }
    } elseif ($action === 'round_status') {
        $pdo->prepare('UPDATE pickup_rounds SET status=? WHERE id=? AND user_id=?')
            ->execute([$_POST['status'], (int)$_POST['id'], $u]);
        flash('อัปเดตสถานะรอบแล้ว');
    }
    redirect('pickup.php');
}

$dr = $pdo->prepare('SELECT * FROM drivers WHERE user_id=? ORDER BY name');
$dr->execute([$u]);
$drivers = $dr->fetchAll();

$rd = $pdo->prepare(
    'SELECT r.*, d.name driver FROM pickup_rounds r LEFT JOIN drivers d ON d.id=r.driver_id
     WHERE r.user_id=? ORDER BY r.round_date DESC, r.id DESC'
);
$rd->execute([$u]);
$rounds = $rd->fetchAll();

// สรุปยอดต่อคนขับขาประจำ
$ds = $pdo->prepare(
    'SELECT d.name, COUNT(r.id) rounds, COALESCE(SUM(r.parcel_count),0) parcels, COALESCE(SUM(r.cod_total),0) cod
     FROM drivers d LEFT JOIN pickup_rounds r ON r.driver_id=d.id AND r.user_id=d.user_id
     WHERE d.user_id=? GROUP BY d.id ORDER BY parcels DESC'
);
$ds->execute([$u]);
$driverSummary = $ds->fetchAll();

$roundStatuses = ['open' => 'เปิดรอบ', 'handed' => 'ส่งมอบแล้ว', 'settled' => 'เคลียร์เงินแล้ว'];

// จำนวนพัสดุที่รอจัดรอบ
$pending = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE user_id=? AND pickup_round_id IS NULL AND status='preparing'");
$pending->execute([$u]);
$pendingCount = (int)$pending->fetchColumn();

$__title = 'รอบเข้ารับ & คนขับ';
$__active = 'pickup';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3>สร้างรอบเข้ารับ (ใบส่งมอบอัตโนมัติ)</h3>
        <p class="muted">พัสดุที่ "เตรียมของ" และยังไม่ถูกจัดรอบ ตอนนี้มี <b><?= $pendingCount ?></b> ชิ้น</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_round">
            <div class="form-row">
                <div><label>คนขับ</label><select name="driver_id"><option value="">— ไม่ระบุ —</option>
                    <?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                </select></div>
                <div><label>วันที่รอบ</label><input type="date" name="round_date" value="<?= date('Y-m-d') ?>"></div>
            </div>
            <button class="btn btn-primary" style="margin-top:14px" <?= $pendingCount === 0 ? 'disabled' : '' ?>>สร้างรอบ + ใบส่งมอบ</button>
        </form>
    </div>
    <div class="card">
        <h3>+ เพิ่มคนขับ</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_driver">
            <label>ชื่อคนขับ</label><input name="name" required>
            <div class="form-row">
                <div><label>เบอร์โทร</label><input name="phone"></div>
                <div><label>ยานพาหนะ</label><input name="vehicle" placeholder="มอเตอร์ไซค์ / กระบะ"></div>
            </div>
            <button class="btn btn-primary" style="margin-top:14px">เพิ่มคนขับ</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <h3>สรุปยอดคนขับขาประจำ</h3>
    <div class="table-wrap">
        <table><thead><tr><th>คนขับ</th><th>จำนวนรอบ</th><th>พัสดุรวม</th><th>ยอด COD รวม</th></tr></thead><tbody>
        <?php foreach ($driverSummary as $d): ?>
            <tr><td><?= e($d['name']) ?></td><td><?= (int)$d['rounds'] ?></td><td><?= (int)$d['parcels'] ?></td><td>฿<?= money($d['cod']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$driverSummary): ?><tr><td colspan="4" class="empty">ยังไม่มีคนขับ</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="table-wrap" style="margin-top:18px">
    <table>
        <thead><tr><th>ใบส่งมอบ</th><th>วันที่</th><th>คนขับ</th><th>พัสดุ</th><th>ยอด COD</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rounds as $r): ?>
            <tr>
                <td><code><?= e($r['manifest_code']) ?></code></td>
                <td><?= e($r['round_date']) ?></td>
                <td><?= e($r['driver'] ?: '—') ?></td>
                <td><?= (int)$r['parcel_count'] ?></td>
                <td>฿<?= money($r['cod_total']) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td>
                    <form method="post" style="display:flex;gap:4px"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="round_status">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach ($roundStatuses as $k => $v): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rounds): ?><tr><td colspan="7" class="empty">ยังไม่มีรอบเข้ารับ</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
