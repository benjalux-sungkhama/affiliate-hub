<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $orderId = ($_POST['order_id'] ?? '') !== '' ? (int)$_POST['order_id'] : null;
        $productId = ($_POST['product_id'] ?? '') !== '' ? (int)$_POST['product_id'] : null;
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $restock = isset($_POST['restock']) ? 1 : 0;
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO returns (user_id,order_id,product_id,reason,qty,restocked,resell_status)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $ins->execute([$u, $orderId, $productId, trim($_POST['reason'] ?? '') ?: null, $qty, $restock, $restock ? 'listed' : 'none']);
            // คืนสต๊อกอัตโนมัติ
            if ($restock && $productId) {
                $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id=? AND user_id=?')->execute([$qty, $productId, $u]);
            }
            // อัปเดตออเดอร์เป็นตีกลับ
            if ($orderId) {
                $pdo->prepare("UPDATE orders SET status='returned' WHERE id=? AND user_id=?")->execute([$orderId, $u]);
            }
            $pdo->commit();
            flash('บันทึกของตีกลับแล้ว' . ($restock ? ' + คืนสต๊อก' . $qty . ' ชิ้น' : ''));
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('บันทึกไม่สำเร็จ: ' . $ex->getMessage(), 'err');
        }
    } elseif ($action === 'resell') {
        $pdo->prepare('UPDATE returns SET resell_status=? WHERE id=? AND user_id=?')
            ->execute([$_POST['status'], (int)$_POST['id'], $u]);
        flash('อัปเดตสถานะเซลรอบสองแล้ว');
    }
    redirect('returns.php');
}

$ord = $pdo->prepare("SELECT id,order_code,customer_name FROM orders WHERE user_id=? AND status NOT IN ('cancelled') ORDER BY created_at DESC");
$ord->execute([$u]);
$orders = $ord->fetchAll();
$prod = $pdo->prepare('SELECT id,name FROM products WHERE user_id=? ORDER BY name');
$prod->execute([$u]);
$products = $prod->fetchAll();

$st = $pdo->prepare(
    'SELECT r.*, o.order_code, p.name product FROM returns r
     LEFT JOIN orders o ON o.id=r.order_id
     LEFT JOIN products p ON p.id=r.product_id
     WHERE r.user_id=? ORDER BY r.created_at DESC'
);
$st->execute([$u]);
$returns = $st->fetchAll();
$resellLabel = ['none' => 'ยังไม่เซล', 'listed' => 'ลงขายรอบสอง', 'sold' => 'ขายได้แล้ว'];

$__title = 'ตีกลับ & เซล';
$__active = 'returns';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-4" style="margin-bottom:16px">
    <div class="card stat"><div class="label">ของตีกลับทั้งหมด</div><div class="value"><?= count($returns) ?></div></div>
    <div class="card stat"><div class="label">คืนสต๊อกแล้ว</div><div class="value"><?= count(array_filter($returns, fn($r) => $r['restocked'])) ?></div></div>
    <div class="card stat"><div class="label">รอเซลรอบสอง</div><div class="value"><?= count(array_filter($returns, fn($r) => $r['resell_status'] === 'listed')) ?></div></div>
    <div class="card stat"><div class="label">เซลรอบสองสำเร็จ</div><div class="value"><?= count(array_filter($returns, fn($r) => $r['resell_status'] === 'sold')) ?></div></div>
</div>

<div class="card" style="margin-bottom:18px">
    <h3>+ บันทึกของตีกลับ</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <div><label>ออเดอร์ (ถ้ามี)</label><select name="order_id"><option value="">—</option>
                <?php foreach ($orders as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?> — <?= e($o['customer_name'] ?: 'ลูกค้า') ?></option><?php endforeach; ?>
            </select></div>
            <div><label>สินค้า</label><select name="product_id"><option value="">—</option>
                <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-row">
            <div><label>จำนวน</label><input type="number" name="qty" value="1" min="1"></div>
            <div><label>สาเหตุ</label><input name="reason" placeholder="เช่น ลูกค้าปฏิเสธรับ / สินค้าชำรุด"></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:12px">
            <input type="checkbox" name="restock" value="1" checked style="width:auto"> คืนสต๊อกอัตโนมัติ (พร้อมลงเซลรอบสอง)
        </label>
        <button class="btn btn-primary" style="margin-top:14px">บันทึก</button>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>วันที่</th><th>ออเดอร์</th><th>สินค้า</th><th>จำนวน</th><th>สาเหตุ</th><th>คืนสต๊อก</th><th>เซลรอบสอง</th></tr></thead>
        <tbody>
        <?php foreach ($returns as $r): ?>
            <tr>
                <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
                <td><?= e($r['order_code'] ?: '—') ?></td>
                <td class="wrap"><?= e($r['product'] ?: '—') ?></td>
                <td><?= (int)$r['qty'] ?></td>
                <td class="wrap"><?= e($r['reason'] ?: '—') ?></td>
                <td><?= $r['restocked'] ? '<span class="badge badge-green">คืนแล้ว</span>' : '<span class="badge badge-gray">ไม่คืน</span>' ?></td>
                <td>
                    <form method="post" style="display:flex;gap:4px"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="resell">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach ($resellLabel as $k => $v): ?><option value="<?= $k ?>" <?= $r['resell_status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$returns): ?><tr><td colspan="7" class="empty">ยังไม่มีของตีกลับ</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
