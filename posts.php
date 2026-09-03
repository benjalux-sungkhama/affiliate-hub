<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            ($_POST['platform_id'] ?? '') !== '' ? (int)$_POST['platform_id'] : null,
            ($_POST['product_id'] ?? '') !== '' ? (int)$_POST['product_id'] : null,
            ($_POST['formula_id'] ?? '') !== '' ? (int)$_POST['formula_id'] : null,
            trim($_POST['title'] ?? '') ?: null,
            trim($_POST['caption'] ?? ''),
            $_POST['media_type'] ?? 'image',
            in_array($_POST['status'] ?? 'draft', ['draft', 'queued', 'published', 'failed'], true) ? $_POST['status'] : 'draft',
            ($_POST['scheduled_at'] ?? '') !== '' ? $_POST['scheduled_at'] : null,
        ];
        if ($id) {
            $st = $pdo->prepare(
                'UPDATE posts SET platform_id=?,product_id=?,formula_id=?,title=?,caption=?,media_type=?,status=?,scheduled_at=?
                 WHERE id=? AND user_id=?'
            );
            $st->execute(array_merge($data, [$id, $u]));
            flash('บันทึกโพสต์แล้ว');
        } else {
            $st = $pdo->prepare(
                'INSERT INTO posts (platform_id,product_id,formula_id,title,caption,media_type,status,scheduled_at,user_id)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $st->execute(array_merge($data, [$u]));
            flash('สร้างโพสต์แล้ว');
        }
    } elseif ($action === 'set_status') {
        $s = $_POST['status'] ?? 'draft';
        $pub = $s === 'published' ? date('Y-m-d H:i:s') : null;
        $st = $pdo->prepare('UPDATE posts SET status=?, published_at=? WHERE id=? AND user_id=?');
        $st->execute([$s, $pub, (int)$_POST['id'], $u]);
        flash('เปลี่ยนสถานะเป็น "' . $s . '" แล้ว');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM posts WHERE id=? AND user_id=?')->execute([(int)$_POST['id'], $u]);
        flash('ลบโพสต์แล้ว');
    }
    redirect('posts.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM posts WHERE id=? AND user_id=?');
    $st->execute([(int)$_GET['edit'], $u]);
    $edit = $st->fetch() ?: null;
}

$platforms = $pdo->query('SELECT * FROM platforms ORDER BY id')->fetchAll();
$prodStmt = $pdo->prepare('SELECT id,name FROM products WHERE user_id=? ORDER BY name');
$prodStmt->execute([$u]);
$products = $prodStmt->fetchAll();
$fStmt = $pdo->prepare('SELECT id,name FROM content_formulas WHERE user_id=? AND parent_id IS NULL ORDER BY name');
$fStmt->execute([$u]);
$formulas = $fStmt->fetchAll();

$filter = $_GET['status'] ?? '';
$sql = 'SELECT p.*, pl.name platform FROM posts p LEFT JOIN platforms pl ON pl.id=p.platform_id WHERE p.user_id=?';
$params = [$u];
if (in_array($filter, ['draft', 'queued', 'published', 'failed'], true)) {
    $sql .= ' AND p.status=?';
    $params[] = $filter;
}
$sql .= ' ORDER BY p.created_at DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$posts = $st->fetchAll();

$__title = 'สร้าง & จัดคิวโพสต์';
$__active = 'posts';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3><?= $edit ? 'แก้ไขโพสต์' : '+ สร้างโพสต์' ?></h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <label>หัวข้อ</label>
            <input name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="ชื่อโพสต์ (ภายใน)">
            <label>แคปชั่น</label>
            <textarea name="caption" placeholder="ข้อความโพสต์"><?= e($edit['caption'] ?? '') ?></textarea>
            <div class="form-row">
                <div>
                    <label>แพลตฟอร์ม</label>
                    <select name="platform_id">
                        <option value="">— เลือก —</option>
                        <?php foreach ($platforms as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ($edit['platform_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>ชนิดสื่อ</label>
                    <select name="media_type">
                        <?php foreach (['image' => 'รูปภาพ', 'video' => 'วิดีโอ', 'carousel' => 'อัลบั้ม', 'text' => 'ข้อความ'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($edit['media_type'] ?? 'image') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label>สินค้า</label>
                    <select name="product_id"><option value="">—</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ($edit['product_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>สูตรที่ใช้</label>
                    <select name="formula_id"><option value="">—</option>
                        <?php foreach ($formulas as $f): ?>
                            <option value="<?= (int)$f['id'] ?>" <?= ($edit['formula_id'] ?? '') == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label>สถานะ</label>
                    <select name="status">
                        <?php foreach (['draft' => 'ฉบับร่าง', 'queued' => 'เข้าคิว', 'published' => 'เผยแพร่แล้ว', 'failed' => 'ล้มเหลว'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($edit['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>ตั้งเวลาโพสต์</label><input type="datetime-local" name="scheduled_at" value="<?= e($edit['scheduled_at'] ? str_replace(' ', 'T', substr($edit['scheduled_at'], 0, 16)) : '') ?>"></div>
            </div>
            <div class="btn-row" style="margin-top:16px">
                <button class="btn btn-primary"><?= $edit ? 'บันทึก' : 'สร้างโพสต์' ?></button>
                <?php if ($edit): ?><a class="btn" href="<?= url('posts.php') ?>">ยกเลิก</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="card">
        <h3>คิวโพสต์</h3>
        <p class="muted">กรองตามสถานะ:</p>
        <div class="btn-row">
            <a class="btn btn-sm <?= $filter === '' ? 'btn-primary' : '' ?>" href="<?= url('posts.php') ?>">ทั้งหมด</a>
            <a class="btn btn-sm <?= $filter === 'draft' ? 'btn-primary' : '' ?>" href="<?= url('posts.php?status=draft') ?>">ฉบับร่าง</a>
            <a class="btn btn-sm <?= $filter === 'queued' ? 'btn-primary' : '' ?>" href="<?= url('posts.php?status=queued') ?>">เข้าคิว</a>
            <a class="btn btn-sm <?= $filter === 'published' ? 'btn-primary' : '' ?>" href="<?= url('posts.php?status=published') ?>">เผยแพร่แล้ว</a>
            <a class="btn btn-sm <?= $filter === 'failed' ? 'btn-primary' : '' ?>" href="<?= url('posts.php?status=failed') ?>">ล้มเหลว</a>
        </div>
    </div>
</div>

<div class="table-wrap" style="margin-top:18px">
    <table>
        <thead><tr><th>หัวข้อ / แคปชั่น</th><th>แพลตฟอร์ม</th><th>ชนิด</th><th>ตั้งเวลา</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td class="wrap"><b><?= e($p['title'] ?: '(ไม่มีหัวข้อ)') ?></b><div class="muted" style="font-size:12px"><?= e(mb_substr((string)$p['caption'], 0, 60)) ?></div></td>
                <td><?= e($p['platform'] ?: '—') ?></td>
                <td><?= e($p['media_type']) ?></td>
                <td><?= e($p['scheduled_at'] ? substr($p['scheduled_at'], 0, 16) : '—') ?></td>
                <td><?= status_badge($p['status']) ?></td>
                <td><div class="btn-row">
                    <a class="btn btn-sm" href="<?= url('posts.php?edit=' . (int)$p['id']) ?>">แก้</a>
                    <?php if ($p['status'] !== 'queued'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="queued"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm">เข้าคิว</button></form>
                    <?php endif; ?>
                    <?php if ($p['status'] !== 'published'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="published"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm">เผยแพร่</button></form>
                    <?php endif; ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="ลบโพสต์นี้?">ลบ</button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$posts): ?><tr><td colspan="6" class="empty">ยังไม่มีโพสต์</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
