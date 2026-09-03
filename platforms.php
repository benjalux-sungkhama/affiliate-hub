<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'connect') {
        $st = db()->prepare(
            'INSERT INTO platform_accounts (user_id,platform_id,account_name,external_id) VALUES (?,?,?,?)'
        );
        $st->execute([
            $u, (int)$_POST['platform_id'],
            trim($_POST['account_name'] ?? ''), trim($_POST['external_id'] ?? '') ?: null,
        ]);
        flash('เชื่อมบัญชีแล้ว');
    } elseif ($action === 'disconnect') {
        $st = db()->prepare('DELETE FROM platform_accounts WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['id'], $u]);
        flash('ยกเลิกการเชื่อมต่อแล้ว');
    }
    redirect('platforms.php');
}

$platforms = db()->query('SELECT * FROM platforms ORDER BY id')->fetchAll();

// สถิติต่อแพลตฟอร์ม
$st = db()->prepare(
    'SELECT platform_id, COUNT(*) posts, COALESCE(SUM(reach),0) reach, COALESCE(SUM(clicks),0) clicks
     FROM posts WHERE user_id=? GROUP BY platform_id'
);
$st->execute([$u]);
$stats = [];
foreach ($st->fetchAll() as $r) {
    $stats[$r['platform_id']] = $r;
}

$st = db()->prepare('SELECT * FROM platform_accounts WHERE user_id=? ORDER BY connected_at DESC');
$st->execute([$u]);
$accounts = $st->fetchAll();
$byPlatform = [];
foreach ($accounts as $a) {
    $byPlatform[$a['platform_id']][] = $a;
}

$__title = 'แพลตฟอร์ม & บัญชี';
$__active = 'platforms';
include __DIR__ . '/includes/header.php';
?>
<p class="muted">เชื่อมบัญชี Facebook / TikTok / Shopee / Lazada แต่ละแพลตฟอร์มมีภาพรวม โพสต์ & คิว บัญชีที่เชื่อม และวิเคราะห์</p>

<div class="grid grid-2" style="align-items:start">
<?php foreach ($platforms as $pf):
    $s = $stats[$pf['id']] ?? ['posts' => 0, 'reach' => 0, 'clicks' => 0];
    $accs = $byPlatform[$pf['id']] ?? [];
?>
    <div class="card">
        <div class="section-head">
            <span class="badge" style="background:<?= e($pf['color']) ?>22;color:<?= e($pf['color']) ?>"><?= e($pf['name']) ?></span>
            <h3 style="flex:1"><?= e($pf['name']) ?></h3>
            <span class="pill"><?= count($accs) ?> บัญชี</span>
        </div>
        <div class="grid grid-3" style="margin-bottom:12px">
            <div class="stat"><div class="label">โพสต์</div><div class="value" style="font-size:20px"><?= number_format($s['posts']) ?></div></div>
            <div class="stat"><div class="label">Reach</div><div class="value" style="font-size:20px"><?= number_format($s['reach']) ?></div></div>
            <div class="stat"><div class="label">คลิก</div><div class="value" style="font-size:20px"><?= number_format($s['clicks']) ?></div></div>
        </div>

        <?php if ($accs): ?>
            <table><tbody>
            <?php foreach ($accs as $a): ?>
                <tr>
                    <td class="wrap">🔗 <?= e($a['account_name']) ?></td>
                    <td><?= $a['is_connected'] ? '<span class="badge badge-green">เชื่อมแล้ว</span>' : '<span class="badge badge-gray">หลุด</span>' ?></td>
                    <td>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="disconnect">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-danger" data-confirm="ยกเลิกการเชื่อมต่อ?">ยกเลิก</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?>
            <div class="empty" style="padding:14px">ยังไม่ได้เชื่อมบัญชี</div>
        <?php endif; ?>

        <form method="post" style="margin-top:12px;display:flex;gap:8px;align-items:end;flex-wrap:wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="connect">
            <input type="hidden" name="platform_id" value="<?= (int)$pf['id'] ?>">
            <div style="flex:1;min-width:160px">
                <label style="margin-top:0">ชื่อเพจ/บัญชี</label>
                <input name="account_name" required placeholder="เช่น ร้านของฉัน">
            </div>
            <button class="btn btn-primary">+ เชื่อมบัญชี</button>
        </form>
    </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
