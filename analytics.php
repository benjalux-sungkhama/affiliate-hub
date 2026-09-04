<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

// อัปเดตตัวเลขจริง (จำลอง) ให้โพสต์ — ช่องกรอก reach/clicks/engagement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'update_metrics') {
        $st = $pdo->prepare('UPDATE posts SET reach=?, clicks=?, engagement=? WHERE id=? AND user_id=?');
        $st->execute([
            (int)($_POST['reach'] ?? 0), (int)($_POST['clicks'] ?? 0),
            (int)($_POST['engagement'] ?? 0), (int)$_POST['id'], $u,
        ]);
        flash('อัปเดตตัวเลขแล้ว');
    }
    redirect('analytics.php');
}

$st = $pdo->prepare(
    "SELECT p.*, pl.name platform,
      CASE WHEN p.reach>0 THEN ROUND(p.clicks/p.reach*100,2) ELSE 0 END AS ctr
     FROM posts p LEFT JOIN platforms pl ON pl.id=p.platform_id
     WHERE p.user_id=? AND p.status='published' ORDER BY p.reach DESC"
);
$st->execute([$u]);
$posts = $st->fetchAll();

// คำแนะนำ Boost: CTR สูง (>2%) แต่ reach ต่ำกว่าค่าเฉลี่ย → ควรยิงแอด
$avgReach = 0;
if ($posts) {
    $avgReach = array_sum(array_column($posts, 'reach')) / count($posts);
}
$boost = array_filter($posts, fn($p) => $p['ctr'] >= 2 && $p['reach'] < $avgReach && $p['reach'] > 0);

$__title = 'วิเคราะห์ & แนะนำ Boost';
$__active = 'analytics';
include __DIR__ . '/includes/header.php';
?>
<div class="card" style="margin-bottom:18px">
    <h3>🤖 AI แนะนำ Boost</h3>
    <?php if ($boost): ?>
        <p class="muted">โพสต์เหล่านี้มี <b>CTR สูง</b> แต่การเข้าถึงยังน้อยกว่าค่าเฉลี่ย (<?= number_format($avgReach) ?>) — คุ้มที่จะยิงโฆษณาเพื่อขยายผล</p>
        <ul>
            <?php foreach ($boost as $b): ?>
                <li><b><?= e($b['title'] ?: mb_substr((string)$b['caption'], 0, 30)) ?></b>
                    — CTR <?= $b['ctr'] ?>% · Reach <?= number_format($b['reach']) ?>
                    <span class="badge badge-orange">แนะนำ Boost</span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">ยังไม่มีโพสต์ที่เข้าเงื่อนไขแนะนำ Boost — กรอกตัวเลข Reach/คลิกในตารางด้านล่างเพื่อให้ระบบวิเคราะห์</p>
    <?php endif; ?>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>โพสต์</th><th>แพลตฟอร์ม</th><th>Reach</th><th>คลิก</th><th>Engagement</th><th>CTR</th><th>อัปเดตตัวเลข</th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td class="wrap"><?= e($p['title'] ?: mb_substr((string)$p['caption'], 0, 40)) ?></td>
                <td><?= e($p['platform'] ?: '—') ?></td>
                <td><?= number_format($p['reach']) ?></td>
                <td><?= number_format($p['clicks']) ?></td>
                <td><?= number_format($p['engagement']) ?></td>
                <td><b style="color:<?= $p['ctr'] >= 2 ? 'var(--green)' : 'var(--ink)' ?>"><?= $p['ctr'] ?>%</b></td>
                <td>
                    <form method="post" style="display:flex;gap:6px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_metrics">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="number" name="reach" value="<?= (int)$p['reach'] ?>" style="width:80px" title="Reach">
                        <input type="number" name="clicks" value="<?= (int)$p['clicks'] ?>" style="width:70px" title="คลิก">
                        <input type="number" name="engagement" value="<?= (int)$p['engagement'] ?>" style="width:70px" title="Engagement">
                        <button class="btn btn-sm">บันทึก</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$posts): ?><tr><td colspan="7" class="empty">ยังไม่มีโพสต์ที่เผยแพร่</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
