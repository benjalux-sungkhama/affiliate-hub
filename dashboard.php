<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();

$one = function (string $sql, array $p = []) use ($u) {
    $st = db()->prepare($sql);
    $st->execute(array_merge([$u], $p));
    return $st->fetchColumn();
};

$totalClicks    = (int)$one('SELECT COALESCE(SUM(clicks),0) FROM posts WHERE user_id=?');
$totalReach     = (int)$one('SELECT COALESCE(SUM(reach),0) FROM posts WHERE user_id=?');
$publishedPosts = (int)$one("SELECT COUNT(*) FROM posts WHERE user_id=? AND status='published'");
$liveSales      = (float)$one('SELECT COALESCE(SUM(total_sales),0) FROM live_sessions WHERE user_id=?');

$orders    = (int)$one('SELECT COUNT(*) FROM orders WHERE user_id=?');
$profit    = (float)$one("SELECT COALESCE(SUM(profit),0) FROM orders WHERE user_id=? AND status!='cancelled'");
$products  = (int)$one('SELECT COUNT(*) FROM products WHERE user_id=?');
$queued    = (int)$one("SELECT COUNT(*) FROM posts WHERE user_id=? AND status='queued'");

// โพสต์ล่าสุด
$st = db()->prepare(
    'SELECT p.*, pl.name AS platform FROM posts p
     LEFT JOIN platforms pl ON pl.id=p.platform_id
     WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 6'
);
$st->execute([$u]);
$recentPosts = $st->fetchAll();

// ออเดอร์ล่าสุด
$st = db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 6');
$st->execute([$u]);
$recentOrders = $st->fetchAll();

$__title = 'แดชบอร์ด';
$__active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-4">
    <div class="card stat"><div class="label">ยอดคลิกรวม</div>
        <div class="value"><?= number_format($totalClicks) ?></div>
        <div class="sub">ทุกโพสต์ทุกแพลตฟอร์ม</div></div>
    <div class="card stat"><div class="label">การเข้าถึงรวม</div>
        <div class="value"><?= number_format($totalReach) ?></div>
        <div class="sub">Reach สะสม</div></div>
    <div class="card stat"><div class="label">โพสต์ที่เผยแพร่</div>
        <div class="value"><?= number_format($publishedPosts) ?></div>
        <div class="sub"><?= $queued ?> รายการรอคิว</div></div>
    <div class="card stat"><div class="label">ยอดขายไลฟ์</div>
        <div class="value">฿<?= money($liveSales) ?></div>
        <div class="sub">รวมทุกรอบไลฟ์</div></div>
</div>

<div class="grid grid-4" style="margin-top:16px">
    <div class="card stat"><div class="label">คำสั่งซื้อ</div><div class="value"><?= number_format($orders) ?></div></div>
    <div class="card stat"><div class="label">กำไรสะสม</div><div class="value">฿<?= money($profit) ?></div></div>
    <div class="card stat"><div class="label">สินค้าในระบบ</div><div class="value"><?= number_format($products) ?></div></div>
    <div class="card stat"><div class="label">โพสต์รอคิว</div><div class="value"><?= number_format($queued) ?></div></div>
</div>

<div class="grid grid-2" style="margin-top:16px;align-items:start">
    <div class="card">
        <div class="section-head"><h3>โพสต์ล่าสุด</h3><a class="pill" href="<?= url('posts.php') ?>">ดูทั้งหมด</a></div>
        <?php if ($recentPosts): ?>
            <table><tbody>
            <?php foreach ($recentPosts as $p): ?>
                <tr>
                    <td class="wrap"><?= e($p['title'] ?: mb_substr((string)$p['caption'], 0, 40)) ?></td>
                    <td><?= e($p['platform'] ?: '—') ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?><div class="empty">ยังไม่มีโพสต์ — เริ่มที่เมนู "สร้าง & จัดคิวโพสต์"</div><?php endif; ?>
    </div>

    <div class="card">
        <div class="section-head"><h3>คำสั่งซื้อล่าสุด</h3><a class="pill" href="<?= url('orders.php') ?>">ดูทั้งหมด</a></div>
        <?php if ($recentOrders): ?>
            <table><tbody>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><code><?= e($o['order_code']) ?></code></td>
                    <td class="wrap"><?= e($o['customer_name'] ?: '—') ?></td>
                    <td>฿<?= money($o['subtotal']) ?></td>
                    <td><?= status_badge($o['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?><div class="empty">ยังไม่มีคำสั่งซื้อ</div><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
