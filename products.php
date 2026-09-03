<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        // หมวดหมู่ (สร้างใหม่ถ้ากรอกชื่อมา)
        $catId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
        $newCat = trim($_POST['new_category'] ?? '');
        if ($newCat !== '') {
            $c = db()->prepare('INSERT INTO categories (user_id,name) VALUES (?,?)');
            $c->execute([$u, $newCat]);
            $catId = (int)db()->lastInsertId();
        }
        $data = [
            trim($_POST['name'] ?? ''), trim($_POST['sku'] ?? '') ?: null, $catId,
            (float)($_POST['price'] ?? 0), (float)($_POST['cost'] ?? 0), (int)($_POST['stock'] ?? 0),
        ];
        if ($id) {
            $st = db()->prepare(
                'UPDATE products SET name=?,sku=?,category_id=?,price=?,cost=?,stock=? WHERE id=? AND user_id=?'
            );
            $st->execute(array_merge($data, [$id, $u]));
            flash('บันทึกสินค้าแล้ว');
        } else {
            $st = db()->prepare(
                'INSERT INTO products (name,sku,category_id,price,cost,stock,user_id) VALUES (?,?,?,?,?,?,?)'
            );
            $st->execute(array_merge($data, [$u]));
            flash('เพิ่มสินค้าแล้ว');
        }
    } elseif ($action === 'delete') {
        $st = db()->prepare('DELETE FROM products WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['id'], $u]);
        flash('ลบสินค้าแล้ว');
    }
    redirect('products.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare('SELECT * FROM products WHERE id=? AND user_id=?');
    $st->execute([(int)$_GET['edit'], $u]);
    $edit = $st->fetch() ?: null;
}

$st = db()->prepare('SELECT * FROM categories WHERE user_id=? ORDER BY name');
$st->execute([$u]);
$cats = $st->fetchAll();

$st = db()->prepare(
    'SELECT p.*, c.name category FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.user_id=? ORDER BY p.created_at DESC'
);
$st->execute([$u]);
$products = $st->fetchAll();

$__title = 'สินค้า';
$__active = 'products';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3><?= $edit ? 'แก้ไขสินค้า' : '+ เพิ่มสินค้า' ?></h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <label>ชื่อสินค้า</label>
            <input name="name" required value="<?= e($edit['name'] ?? '') ?>">
            <div class="form-row">
                <div><label>SKU</label><input name="sku" value="<?= e($edit['sku'] ?? '') ?>"></div>
                <div><label>สต๊อก</label><input type="number" name="stock" value="<?= (int)($edit['stock'] ?? 0) ?>"></div>
            </div>
            <div class="form-row">
                <div><label>ราคาขาย (บาท)</label><input type="number" step="0.01" name="price" value="<?= e($edit['price'] ?? '0') ?>"></div>
                <div><label>ต้นทุน (บาท)</label><input type="number" step="0.01" name="cost" value="<?= e($edit['cost'] ?? '0') ?>"></div>
            </div>
            <p class="hint">⚠️ ต้องกรอก <b>ต้นทุน</b> ระบบถึงคำนวณกำไรในออเดอร์ได้</p>
            <label>หมวดหมู่</label>
            <select name="category_id">
                <option value="">— ไม่ระบุ —</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($edit['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>หรือสร้างหมวดใหม่</label>
            <input name="new_category" placeholder="เว้นว่างถ้าไม่ต้องการ">
            <div class="btn-row" style="margin-top:16px">
                <button class="btn btn-primary"><?= $edit ? 'บันทึก' : 'เพิ่มสินค้า' ?></button>
                <?php if ($edit): ?><a class="btn" href="<?= url('products.php') ?>">ยกเลิก</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <h3>สรุปสินค้า</h3>
        <p class="muted">ทั้งหมด <b><?= count($products) ?></b> รายการ ·
            มูลค่าสต๊อก (ต้นทุน) ฿<?php
                $val = 0; foreach ($products as $p) { $val += $p['cost'] * $p['stock']; }
                echo money($val); ?></p>
        <p class="muted">กำไรต่อชิ้นเฉลี่ย = ราคาขาย − ต้นทุน</p>
    </div>
</div>

<div class="table-wrap" style="margin-top:18px">
    <table>
        <thead><tr>
            <th>สินค้า</th><th>SKU</th><th>หมวด</th><th>ราคาขาย</th><th>ต้นทุน</th>
            <th>กำไร/ชิ้น</th><th>สต๊อก</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($products as $p): $margin = $p['price'] - $p['cost']; ?>
            <tr>
                <td class="wrap"><?= e($p['name']) ?></td>
                <td><?= e($p['sku'] ?: '—') ?></td>
                <td><?= e($p['category'] ?: '—') ?></td>
                <td>฿<?= money($p['price']) ?></td>
                <td>฿<?= money($p['cost']) ?></td>
                <td style="color:<?= $margin >= 0 ? 'var(--green)' : 'var(--red)' ?>">฿<?= money($margin) ?></td>
                <td><?= (int)$p['stock'] ?></td>
                <td><div class="btn-row">
                    <a class="btn btn-sm" href="<?= url('products.php?edit=' . (int)$p['id']) ?>">แก้</a>
                    <form method="post"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-sm btn-danger" data-confirm="ลบสินค้านี้?">ลบ</button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="8" class="empty">ยังไม่มีสินค้า</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
