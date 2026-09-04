<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/automation.php';
require_login();
$u = uid();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $pids = $_POST['product_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $subtotal = 0;
        $costTotal = 0;
        $items = [];
        foreach ($pids as $i => $pid) {
            $pid = (int)$pid;
            $qty = max(1, (int)($qtys[$i] ?? 1));
            if (!$pid) {
                continue;
            }
            $ps = $pdo->prepare('SELECT * FROM products WHERE id=? AND user_id=?');
            $ps->execute([$pid, $u]);
            $p = $ps->fetch();
            if (!$p) {
                continue;
            }
            $subtotal += $p['price'] * $qty;
            $costTotal += $p['cost'] * $qty;
            $items[] = ['id' => $pid, 'name' => $p['name'], 'qty' => $qty, 'price' => $p['price'], 'cost' => $p['cost']];
        }
        $shipping = (float)($_POST['shipping_fee'] ?? 0);
        $profit = $subtotal - $costTotal;   // กำไร = ยอดขาย − ต้นทุน
        $source = $_POST['source_type'] ?? 'manual';

        $pdo->beginTransaction();
        try {
            $code = 'AH' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $ins = $pdo->prepare(
                'INSERT INTO orders
                 (user_id,order_code,customer_name,customer_phone,address,source_type,source_post_id,source_live_id,
                  subtotal,cost_total,shipping_fee,profit,payment_type,status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $u, $code, trim($_POST['customer_name'] ?? '') ?: null, trim($_POST['customer_phone'] ?? '') ?: null,
                trim($_POST['address'] ?? '') ?: null, $source,
                $source === 'post' && ($_POST['source_post_id'] ?? '') !== '' ? (int)$_POST['source_post_id'] : null,
                $source === 'live' && ($_POST['source_live_id'] ?? '') !== '' ? (int)$_POST['source_live_id'] : null,
                $subtotal, $costTotal, $shipping, $profit,
                in_array($_POST['payment_type'] ?? 'cod', ['prepaid', 'cod'], true) ? $_POST['payment_type'] : 'cod',
                'new',
            ]);
            $orderId = (int)$pdo->lastInsertId();
            $insItem = $pdo->prepare(
                'INSERT INTO order_items (order_id,user_id,product_id,name,qty,price,cost) VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $insItem->execute([$orderId, $u, $it['id'], $it['name'], $it['qty'], $it['price'], $it['cost']]);
                // ตัดสต๊อก
                $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id=? AND user_id=?')
                    ->execute([$it['qty'], $it['id'], $u]);
            }
            // สร้าง COD record ถ้าเป็นเก็บเงินปลายทาง
            if (($_POST['payment_type'] ?? 'cod') === 'cod') {
                $pdo->prepare('INSERT INTO cod_records (user_id,order_id,amount) VALUES (?,?,?)')
                    ->execute([$u, $orderId, $subtotal + $shipping]);
            }
            $pdo->commit();
            flash('สร้างคำสั่งซื้อ ' . $code . ' แล้ว (กำไร ฿' . money($profit) . ')');
            // ทริกเกอร์ order.milestone — ยอดสั่งซื้อครบทุก 10 ออเดอร์
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');
            $cnt->execute([$u]);
            $orderCount = (int)$cnt->fetchColumn();
            if ($orderCount > 0 && $orderCount % 10 === 0) {
                automation_dispatch_event($pdo, $u, 'order.milestone', [
                    'order.count'  => $orderCount,
                    'time.hour'    => (int)date('G'),
                    'product.name' => 'ร้านของเรา',
                    'product.usp'  => 'ยอดสั่งซื้อทะลุ ' . $orderCount . ' ออเดอร์แล้ว 🎉',
                    'product.target' => 'ลูกค้าทุกคน',
                    'cta'          => 'สั่งเลยวันนี้',
                ]);
            }
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('สร้างไม่สำเร็จ: ' . $ex->getMessage(), 'err');
        }
    } elseif ($action === 'set_status') {
        $pdo->prepare('UPDATE orders SET status=? WHERE id=? AND user_id=?')
            ->execute([$_POST['status'], (int)$_POST['id'], $u]);
        flash('อัปเดตสถานะแล้ว');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM orders WHERE id=? AND user_id=?')->execute([(int)$_POST['id'], $u]);
        flash('ลบคำสั่งซื้อแล้ว');
    }
    redirect('orders.php');
}

$prodStmt = $pdo->prepare('SELECT id,name,price,cost,stock FROM products WHERE user_id=? AND is_active=1 ORDER BY name');
$prodStmt->execute([$u]);
$products = $prodStmt->fetchAll();
$postStmt = $pdo->prepare("SELECT id,title,caption FROM posts WHERE user_id=? AND status='published' ORDER BY published_at DESC");
$postStmt->execute([$u]);
$posts = $postStmt->fetchAll();
$liveStmt = $pdo->prepare('SELECT id,title FROM live_sessions WHERE user_id=? ORDER BY started_at DESC');
$liveStmt->execute([$u]);
$lives = $liveStmt->fetchAll();

$st = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 200');
$st->execute([$u]);
$orders = $st->fetchAll();

$statuses = ['new' => 'ใหม่', 'packed' => 'แพ็คแล้ว', 'shipped' => 'ส่งแล้ว', 'delivered' => 'ส่งสำเร็จ', 'returned' => 'ตีกลับ', 'cancelled' => 'ยกเลิก'];

$__title = 'คำสั่งซื้อ';
$__active = 'orders';
include __DIR__ . '/includes/header.php';
?>
<div class="card" style="margin-bottom:18px">
    <h3>+ สร้างคำสั่งซื้อ</h3>
    <?php if (!$products): ?>
        <p class="hint">ยังไม่มีสินค้า — <a href="<?= url('products.php') ?>">เพิ่มสินค้า</a> (พร้อมต้นทุน) ก่อน ระบบถึงคำนวณกำไรได้</p>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <div><label>ชื่อลูกค้า</label><input name="customer_name"></div>
            <div><label>เบอร์โทร</label><input name="customer_phone"></div>
        </div>
        <label>ที่อยู่จัดส่ง</label>
        <textarea name="address"></textarea>

        <label style="margin-top:14px">รายการสินค้า</label>
        <div id="items">
            <div class="form-row" style="margin-bottom:8px">
                <select name="product_id[]">
                    <option value="">— เลือกสินค้า —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (฿<?= money($p['price']) ?>, สต๊อก <?= (int)$p['stock'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="qty[]" value="1" min="1" placeholder="จำนวน">
            </div>
        </div>
        <button type="button" class="btn btn-sm" onclick="addItem()">+ เพิ่มรายการ</button>

        <div class="form-row" style="margin-top:14px">
            <div><label>ค่าส่ง (บาท)</label><input type="number" step="0.01" name="shipping_fee" value="0"></div>
            <div><label>การชำระเงิน</label><select name="payment_type"><option value="cod">เก็บเงินปลายทาง (COD)</option><option value="prepaid">โอนก่อน</option></select></div>
        </div>
        <div class="form-row">
            <div><label>ที่มาของยอดขาย</label>
                <select name="source_type" onchange="document.getElementById('srcPost').hidden=this.value!=='post';document.getElementById('srcLive').hidden=this.value!=='live'">
                    <option value="manual">กรอกเอง</option>
                    <option value="post">จากโพสต์</option>
                    <option value="live">จากไลฟ์</option>
                </select>
            </div>
            <div id="srcPost" hidden><label>โพสต์</label>
                <select name="source_post_id"><option value="">—</option>
                    <?php foreach ($posts as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['title'] ?: mb_substr((string)$p['caption'], 0, 30)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div id="srcLive" hidden><label>ไลฟ์</label>
                <select name="source_live_id"><option value="">—</option>
                    <?php foreach ($lives as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['title']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn btn-primary" style="margin-top:16px">สร้างคำสั่งซื้อ</button>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>รหัส</th><th>ลูกค้า</th><th>ที่มา</th><th>ยอด</th><th>กำไร</th><th>ชำระ</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><code><?= e($o['order_code']) ?></code></td>
                <td class="wrap"><?= e($o['customer_name'] ?: '—') ?></td>
                <td><?= e(['post' => 'โพสต์', 'live' => 'ไลฟ์', 'manual' => 'กรอกเอง'][$o['source_type']] ?? '-') ?></td>
                <td>฿<?= money($o['subtotal'] + $o['shipping_fee']) ?></td>
                <td style="color:<?= $o['profit'] >= 0 ? 'var(--green)' : 'var(--red)' ?>">฿<?= money($o['profit']) ?></td>
                <td><?= $o['payment_type'] === 'cod' ? 'COD' : 'โอนก่อน' ?></td>
                <td><?= status_badge($o['status']) ?></td>
                <td><div class="btn-row">
                    <form method="post" style="display:flex;gap:4px"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="ลบคำสั่งซื้อนี้?">ลบ</button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="8" class="empty">ยังไม่มีคำสั่งซื้อ</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function addItem(){
  var box=document.getElementById('items');
  var opts=box.querySelector('select').innerHTML;
  var row=document.createElement('div');
  row.className='form-row';row.style.marginBottom='8px';
  row.innerHTML='<select name="product_id[]">'+opts+'</select><input type="number" name="qty[]" value="1" min="1">';
  box.appendChild(row);
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
