<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

$agg = function (string $sql) use ($pdo, $u) {
    $st = $pdo->prepare($sql);
    $st->execute([$u]);
    return $st->fetch();
};

$tot = $agg(
    "SELECT COUNT(*) orders, COALESCE(SUM(subtotal),0) revenue, COALESCE(SUM(profit),0) profit,
            COALESCE(SUM(cost_total),0) cost, COALESCE(SUM(shipping_fee),0) shipping
     FROM orders WHERE user_id=? AND status!='cancelled'"
);
$margin = $tot['revenue'] > 0 ? round($tot['profit'] / $tot['revenue'] * 100, 1) : 0;

// ที่มาของยอด
$src = $pdo->prepare(
    "SELECT source_type, COUNT(*) n, COALESCE(SUM(subtotal),0) rev, COALESCE(SUM(profit),0) profit
     FROM orders WHERE user_id=? AND status!='cancelled' GROUP BY source_type"
);
$src->execute([$u]);
$sources = $src->fetchAll();

// สินค้าขายดี
$top = $pdo->prepare(
    'SELECT name, SUM(qty) qty, SUM((price-cost)*qty) profit
     FROM order_items WHERE user_id=? GROUP BY name ORDER BY qty DESC LIMIT 10'
);
$top->execute([$u]);
$topProducts = $top->fetchAll();

// ยอดขายรายวัน 14 วันล่าสุด
$daily = $pdo->prepare(
    "SELECT DATE(created_at) d, SUM(subtotal) rev, SUM(profit) profit
     FROM orders WHERE user_id=? AND status!='cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at) ORDER BY d"
);
$daily->execute([$u]);
$dailyRows = $daily->fetchAll();
$maxRev = max(1, ...array_map(fn($r) => (float)$r['rev'], $dailyRows ?: [['rev' => 1]]));

$srcLabel = ['post' => 'จากโพสต์', 'live' => 'จากไลฟ์', 'manual' => 'กรอกเอง'];

$__title = 'ยอดขาย & กำไร';
$__active = 'sales';
include __DIR__ . '/includes/header.php';
?>
<div class="grid grid-4">
    <div class="card stat"><div class="label">รายได้รวม</div><div class="value">฿<?= money($tot['revenue']) ?></div><div class="sub"><?= (int)$tot['orders'] ?> คำสั่งซื้อ</div></div>
    <div class="card stat"><div class="label">กำไรรวม</div><div class="value">฿<?= money($tot['profit']) ?></div><div class="sub">มาร์จิน <?= $margin ?>%</div></div>
    <div class="card stat"><div class="label">ต้นทุนรวม</div><div class="value">฿<?= money($tot['cost']) ?></div></div>
    <div class="card stat"><div class="label">ค่าส่งรวม</div><div class="value">฿<?= money($tot['shipping']) ?></div></div>
</div>

<div class="grid grid-2" style="margin-top:16px;align-items:start">
    <div class="card">
        <h3>ที่มาของยอดขาย</h3>
        <table><thead><tr><th>ที่มา</th><th>ออเดอร์</th><th>รายได้</th><th>กำไร</th></tr></thead><tbody>
        <?php foreach ($sources as $s): ?>
            <tr><td><?= e($srcLabel[$s['source_type']] ?? $s['source_type']) ?></td><td><?= (int)$s['n'] ?></td><td>฿<?= money($s['rev']) ?></td><td>฿<?= money($s['profit']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$sources): ?><tr><td colspan="4" class="empty">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
        </tbody></table>
    </div>
    <div class="card">
        <h3>สินค้าขายดี</h3>
        <table><thead><tr><th>สินค้า</th><th>ขายได้ (ชิ้น)</th><th>กำไร</th></tr></thead><tbody>
        <?php foreach ($topProducts as $t): ?>
            <tr><td class="wrap"><?= e($t['name']) ?></td><td><?= (int)$t['qty'] ?></td><td>฿<?= money($t['profit']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$topProducts): ?><tr><td colspan="3" class="empty">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h3>ยอดขายรายวัน (14 วันล่าสุด)</h3>
    <?php if ($dailyRows): ?>
        <div style="display:flex;align-items:flex-end;gap:6px;height:180px;padding-top:10px">
            <?php foreach ($dailyRows as $r): $h = round($r['rev'] / $maxRev * 160); ?>
                <div style="flex:1;text-align:center" title="฿<?= money($r['rev']) ?>">
                    <div style="background:linear-gradient(180deg,var(--brand),var(--brand-2));border-radius:5px 5px 0 0;height:<?= $h ?>px"></div>
                    <div class="muted" style="font-size:10px;margin-top:4px"><?= e(substr($r['d'], 5)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?><div class="empty">ยังไม่มียอดขายใน 14 วันที่ผ่านมา</div><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
