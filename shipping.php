<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $st = $pdo->prepare(
            'INSERT INTO shipments (user_id,order_id,tracking_no,carrier,status) VALUES (?,?,?,?,?)'
        );
        $st->execute([
            $u, (int)$_POST['order_id'], trim($_POST['tracking_no'] ?? '') ?: null,
            trim($_POST['carrier'] ?? '') ?: null, 'preparing',
        ]);
        flash('สร้างรายการจัดส่งแล้ว');
    } elseif ($action === 'ship_status') {
        $s = $_POST['status'];
        $shipped = $s === 'picked_up' || $s === 'in_transit' ? date('Y-m-d H:i:s') : null;
        $delivered = $s === 'delivered' ? date('Y-m-d H:i:s') : null;
        $sql = 'UPDATE shipments SET status=?';
        $params = [$s];
        if ($shipped) {
            $sql .= ', shipped_at=COALESCE(shipped_at,?)';
            $params[] = $shipped;
        }
        if ($delivered) {
            $sql .= ', delivered_at=?';
            $params[] = $delivered;
        }
        $sql .= ' WHERE id=? AND user_id=?';
        $params[] = (int)$_POST['id'];
        $params[] = $u;
        $pdo->prepare($sql)->execute($params);
        // ส่งสำเร็จ → อัปเดตออเดอร์ + mark COD collected
        if ($s === 'delivered') {
            $oid = (int)$_POST['order_id'];
            $pdo->prepare("UPDATE orders SET status='delivered' WHERE id=? AND user_id=?")->execute([$oid, $u]);
            $pdo->prepare('UPDATE cod_records SET collected=1 WHERE order_id=? AND user_id=?')->execute([$oid, $u]);
        }
        flash('อัปเดตสถานะการจัดส่งแล้ว');
    } elseif ($action === 'remit') {
        $pdo->prepare('UPDATE cod_records SET remitted=1, remitted_at=NOW() WHERE id=? AND user_id=?')
            ->execute([(int)$_POST['id'], $u]);
        flash('ยืนยันโอนเงิน COD คืนร้านแล้ว');
    }
    redirect('shipping.php');
}

// ออเดอร์ที่ยังไม่มี shipment
$noShip = $pdo->prepare(
    'SELECT o.* FROM orders o
     LEFT JOIN shipments s ON s.order_id=o.id
     WHERE o.user_id=? AND s.id IS NULL AND o.status NOT IN ("cancelled","returned")
     ORDER BY o.created_at DESC'
);
$noShip->execute([$u]);
$pendingOrders = $noShip->fetchAll();

$st = $pdo->prepare(
    'SELECT s.*, o.order_code, o.customer_name FROM shipments s
     JOIN orders o ON o.id=s.order_id WHERE s.user_id=? ORDER BY s.created_at DESC'
);
$st->execute([$u]);
$shipments = $st->fetchAll();

$cod = $pdo->prepare(
    'SELECT c.*, o.order_code, o.customer_name FROM cod_records c
     JOIN orders o ON o.id=c.order_id WHERE c.user_id=? ORDER BY c.created_at DESC'
);
$cod->execute([$u]);
$codRecords = $cod->fetchAll();
$codPending = array_filter($codRecords, fn($c) => $c['collected'] && !$c['remitted']);

$shipStatuses = ['preparing' => 'เตรียมของ', 'picked_up' => 'รับพัสดุแล้ว', 'in_transit' => 'กำลังส่ง', 'delivered' => 'ส่งสำเร็จ', 'failed' => 'ส่งไม่สำเร็จ'];

$__title = 'จัดส่ง & COD';
$__active = 'shipping';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card stat"><div class="label">รอสร้างการจัดส่ง</div><div class="value"><?= count($pendingOrders) ?></div></div>
    <div class="card stat"><div class="label">กำลังส่ง</div><div class="value"><?= count(array_filter($shipments, fn($s) => in_array($s['status'], ['picked_up', 'in_transit']))) ?></div></div>
    <div class="card stat"><div class="label">COD รอโอนคืน</div><div class="value"><?= count($codPending) ?></div></div>
    <div class="card stat"><div class="label">ยอด COD รอโอน</div><div class="value">฿<?= money(array_sum(array_column($codPending, 'amount'))) ?></div></div>
</div>

<?php if ($pendingOrders): ?>
<div class="card" style="margin-bottom:18px">
    <h3>สร้างรายการจัดส่ง</h3>
    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div style="flex:1;min-width:200px"><label>ออเดอร์</label>
            <select name="order_id" required>
                <?php foreach ($pendingOrders as $o): ?>
                    <option value="<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?> — <?= e($o['customer_name'] ?: 'ลูกค้า') ?> (฿<?= money($o['subtotal'] + $o['shipping_fee']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>ขนส่ง</label><input name="carrier" placeholder="Flash / J&T / ไปรษณีย์"></div>
        <div><label>เลขพัสดุ</label><input name="tracking_no"></div>
        <button class="btn btn-primary">สร้าง</button>
    </form>
</div>
<?php endif; ?>

<div class="table-wrap" style="margin-bottom:18px">
    <table>
        <thead><tr><th>ออเดอร์</th><th>ลูกค้า</th><th>ขนส่ง</th><th>เลขพัสดุ</th><th>สถานะ</th><th>อัปเดต</th></tr></thead>
        <tbody>
        <?php foreach ($shipments as $s): ?>
            <tr>
                <td><code><?= e($s['order_code']) ?></code></td>
                <td class="wrap"><?= e($s['customer_name'] ?: '—') ?></td>
                <td><?= e($s['carrier'] ?: '—') ?></td>
                <td><?= e($s['tracking_no'] ?: '—') ?></td>
                <td><?= status_badge($s['status']) ?></td>
                <td>
                    <form method="post" style="display:flex;gap:4px"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="ship_status">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <input type="hidden" name="order_id" value="<?= (int)$s['order_id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach ($shipStatuses as $k => $v): ?><option value="<?= $k ?>" <?= $s['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$shipments): ?><tr><td colspan="6" class="empty">ยังไม่มีรายการจัดส่ง</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>เงินเก็บปลายทาง (COD)</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ออเดอร์</th><th>ยอด COD</th><th>เก็บเงินแล้ว</th><th>โอนคืนร้าน</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($codRecords as $c): ?>
                <tr>
                    <td><code><?= e($c['order_code']) ?></code></td>
                    <td>฿<?= money($c['amount']) ?></td>
                    <td><?= $c['collected'] ? '<span class="badge badge-green">เก็บแล้ว</span>' : '<span class="badge badge-gray">ยังไม่เก็บ</span>' ?></td>
                    <td><?= $c['remitted'] ? '<span class="badge badge-green">โอนแล้ว</span>' : '<span class="badge badge-orange">รอโอน</span>' ?></td>
                    <td>
                        <?php if ($c['collected'] && !$c['remitted']): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remit"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-primary">ยืนยันโอนคืนร้าน</button></form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$codRecords): ?><tr><td colspan="5" class="empty">ยังไม่มีรายการ COD</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
