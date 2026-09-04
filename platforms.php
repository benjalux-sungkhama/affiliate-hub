<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $backPf = (int)($_POST['pf'] ?? 0);
    if ($action === 'connect') {
        $st = $pdo->prepare(
            'INSERT INTO platform_accounts (user_id,platform_id,account_name,external_id,access_token) VALUES (?,?,?,?,?)'
        );
        $st->execute([
            $u, (int)$_POST['platform_id'],
            trim($_POST['account_name'] ?? ''), trim($_POST['external_id'] ?? '') ?: null,
            trim($_POST['access_token'] ?? '') ?: null,
        ]);
        flash('เชื่อมบัญชีแล้ว');
    } elseif ($action === 'disconnect') {
        $st = $pdo->prepare('DELETE FROM platform_accounts WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['id'], $u]);
        flash('ยกเลิกการเชื่อมต่อแล้ว');
    }
    redirect('platforms.php?pf=' . $backPf . '&tab=' . ($_POST['tab'] ?? 'accounts'));
}

$platforms = $pdo->query('SELECT * FROM platforms ORDER BY id')->fetchAll();
if (!$platforms) {
    $__title = 'แพลตฟอร์ม & บัญชี';
    $__active = 'platforms';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty">ยังไม่มีแพลตฟอร์มในระบบ (import sql/seed.sql ก่อน)</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// แพลตฟอร์มที่เลือก + แท็บที่เลือก
$pfId = (int)($_GET['pf'] ?? $platforms[0]['id']);
$current = null;
foreach ($platforms as $p) {
    if ((int)$p['id'] === $pfId) {
        $current = $p;
    }
}
if (!$current) {
    $current = $platforms[0];
    $pfId = (int)$current['id'];
}
$tab = in_array($_GET['tab'] ?? 'overview', ['overview', 'posts', 'accounts', 'analytics'], true)
    ? $_GET['tab'] : 'overview';

$__title = 'แพลตฟอร์ม & บัญชี';
$__active = 'platforms';
include __DIR__ . '/includes/header.php';

// ปุ่มเลือกแพลตฟอร์ม
echo '<div class="tabs">';
foreach ($platforms as $p) {
    $cls = (int)$p['id'] === $pfId ? 'tab active' : 'tab';
    echo '<a class="' . $cls . '" href="' . e(url('platforms.php?pf=' . (int)$p['id'] . '&tab=' . $tab)) . '">'
        . e($p['name']) . '</a>';
}
echo '</div>';

// แท็บย่อย 4 แท็บของแพลตฟอร์มที่เลือก
$subtabs = ['overview' => 'ภาพรวม', 'posts' => 'โพสต์ & คิว', 'accounts' => 'บัญชีที่เชื่อม', 'analytics' => 'วิเคราะห์'];
echo '<div class="tabs" style="margin-bottom:18px">';
foreach ($subtabs as $key => $label) {
    $cls = $key === $tab ? 'tab active' : 'tab';
    echo '<a class="' . $cls . '" href="' . e(url('platforms.php?pf=' . $pfId . '&tab=' . $key)) . '">'
        . e($label) . '</a>';
}
echo '</div>';
?>

<div class="section-head">
    <span class="badge" style="background:<?= e($current['color']) ?>22;color:<?= e($current['color']) ?>"><?= e($current['name']) ?></span>
    <h2 style="flex:1"><?= e($current['name']) ?> — <?= e($subtabs[$tab]) ?></h2>
</div>

<?php
// ----- แท็บ 1: ภาพรวม -----
if ($tab === 'overview'):
    $st = $pdo->prepare(
        'SELECT COUNT(*) posts, COALESCE(SUM(reach),0) reach, COALESCE(SUM(clicks),0) clicks,
                COALESCE(SUM(engagement),0) eng,
                SUM(status=\'published\') published, SUM(status=\'queued\') queued
         FROM posts WHERE user_id=? AND platform_id=?'
    );
    $st->execute([$u, $pfId]);
    $s = $st->fetch();
    $acc = $pdo->prepare('SELECT COUNT(*) FROM platform_accounts WHERE user_id=? AND platform_id=?');
    $acc->execute([$u, $pfId]);
    $accCount = (int)$acc->fetchColumn();
?>
    <div class="grid grid-4">
        <div class="card stat"><div class="label">บัญชีที่เชื่อม</div><div class="value"><?= $accCount ?></div></div>
        <div class="card stat"><div class="label">โพสต์ทั้งหมด</div><div class="value"><?= number_format($s['posts']) ?></div><div class="sub"><?= (int)$s['published'] ?> เผยแพร่ · <?= (int)$s['queued'] ?> เข้าคิว</div></div>
        <div class="card stat"><div class="label">การเข้าถึงรวม</div><div class="value"><?= number_format($s['reach']) ?></div></div>
        <div class="card stat"><div class="label">คลิกรวม</div><div class="value"><?= number_format($s['clicks']) ?></div></div>
    </div>
    <div class="card" style="margin-top:16px">
        <p class="muted">ภาพรวมของ <b><?= e($current['name']) ?></b> เฉพาะบัญชีของคุณ — ไปที่แท็บ
            <b>บัญชีที่เชื่อม</b> เพื่อเพิ่มเพจ/บัญชี หรือแท็บ <b>โพสต์ & คิว</b> เพื่อดูสถานะการเผยแพร่</p>
    </div>

<?php
// ----- แท็บ 2: โพสต์ & คิว -----
elseif ($tab === 'posts'):
    $st = $pdo->prepare(
        'SELECT * FROM posts WHERE user_id=? AND platform_id=? ORDER BY
         FIELD(status,\'queued\',\'draft\',\'failed\',\'published\'), scheduled_at ASC, created_at DESC'
    );
    $st->execute([$u, $pfId]);
    $posts = $st->fetchAll();
?>
    <div class="section-head">
        <span class="pill"><?= count($posts) ?> โพสต์</span>
        <a class="btn btn-primary" style="margin-left:auto" href="<?= url('posts.php') ?>">+ สร้างโพสต์</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>หัวข้อ / แคปชั่น</th><th>ชนิด</th><th>ตั้งเวลา</th><th>สถานะ</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($posts as $p): ?>
                <tr>
                    <td class="wrap"><b><?= e($p['title'] ?: '(ไม่มีหัวข้อ)') ?></b><div class="muted" style="font-size:12px"><?= e(mb_substr((string)$p['caption'], 0, 60)) ?></div></td>
                    <td><?= e($p['media_type']) ?></td>
                    <td><?= e($p['scheduled_at'] ? substr($p['scheduled_at'], 0, 16) : '—') ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td><a class="btn btn-sm" href="<?= url('posts.php?edit=' . (int)$p['id']) ?>">แก้</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$posts): ?><tr><td colspan="5" class="empty">ยังไม่มีโพสต์ของแพลตฟอร์มนี้</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

<?php
// ----- แท็บ 3: บัญชีที่เชื่อม -----
elseif ($tab === 'accounts'):
    $st = $pdo->prepare('SELECT * FROM platform_accounts WHERE user_id=? AND platform_id=? ORDER BY connected_at DESC');
    $st->execute([$u, $pfId]);
    $accounts = $st->fetchAll();
?>
    <div class="table-wrap" style="margin-bottom:18px">
        <table>
            <thead><tr><th>ชื่อเพจ/บัญชี</th><th>Page/Account ID</th><th>Token</th><th>สถานะ</th><th>เชื่อมเมื่อ</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($accounts as $a): ?>
                <tr>
                    <td class="wrap">🔗 <?= e($a['account_name']) ?></td>
                    <td><?= e($a['external_id'] ?: '—') ?></td>
                    <td><?= $a['access_token'] ? '<span class="badge badge-green">มี</span>' : '<span class="badge badge-gray">ไม่มี</span>' ?></td>
                    <td><?= $a['is_connected'] ? '<span class="badge badge-green">เชื่อมแล้ว</span>' : '<span class="badge badge-gray">หลุด</span>' ?></td>
                    <td><?= e(substr($a['connected_at'], 0, 16)) ?></td>
                    <td>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="disconnect">
                            <input type="hidden" name="pf" value="<?= $pfId ?>">
                            <input type="hidden" name="tab" value="accounts">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-danger" data-confirm="ยกเลิกการเชื่อมต่อ?">ยกเลิก</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$accounts): ?><tr><td colspan="6" class="empty">ยังไม่ได้เชื่อมบัญชี</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3>+ เชื่อมบัญชี <?= e($current['name']) ?></h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="connect">
            <input type="hidden" name="platform_id" value="<?= $pfId ?>">
            <input type="hidden" name="pf" value="<?= $pfId ?>">
            <input type="hidden" name="tab" value="accounts">
            <label>ชื่อเพจ/บัญชี</label>
            <input name="account_name" required placeholder="เช่น ร้านของฉัน">
            <div class="form-row">
                <div><label>Page/Account ID</label><input name="external_id" placeholder="สำหรับโพสต์อัตโนมัติ"></div>
                <div><label>Access Token</label><input name="access_token" placeholder="ใส่เมื่อใช้ automation โหมด live"></div>
            </div>
            <p class="hint">Page ID + Access Token ใส่เมื่อจะเปิด automation โหมด <b>live</b> (Facebook ต้องมีสิทธิ์ <code>pages_manage_posts</code>)</p>
            <button class="btn btn-primary" style="margin-top:12px">เชื่อมบัญชี</button>
        </form>
    </div>

<?php
// ----- แท็บ 4: วิเคราะห์ -----
elseif ($tab === 'analytics'):
    $st = $pdo->prepare(
        "SELECT *, CASE WHEN reach>0 THEN ROUND(clicks/reach*100,2) ELSE 0 END ctr
         FROM posts WHERE user_id=? AND platform_id=? AND status='published'
         ORDER BY reach DESC"
    );
    $st->execute([$u, $pfId]);
    $rows = $st->fetchAll();
    $sumReach = array_sum(array_column($rows, 'reach'));
    $sumClicks = array_sum(array_column($rows, 'clicks'));
    $avgCtr = $sumReach > 0 ? round($sumClicks / $sumReach * 100, 2) : 0;
?>
    <div class="grid grid-3" style="margin-bottom:16px">
        <div class="card stat"><div class="label">โพสต์ที่เผยแพร่</div><div class="value"><?= count($rows) ?></div></div>
        <div class="card stat"><div class="label">CTR เฉลี่ย</div><div class="value"><?= $avgCtr ?>%</div></div>
        <div class="card stat"><div class="label">การเข้าถึงรวม</div><div class="value"><?= number_format($sumReach) ?></div></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>โพสต์</th><th>Reach</th><th>คลิก</th><th>Engagement</th><th>CTR</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="wrap"><?= e($r['title'] ?: mb_substr((string)$r['caption'], 0, 40)) ?></td>
                    <td><?= number_format($r['reach']) ?></td>
                    <td><?= number_format($r['clicks']) ?></td>
                    <td><?= number_format($r['engagement']) ?></td>
                    <td><b style="color:<?= $r['ctr'] >= 2 ? 'var(--green)' : 'var(--ink)' ?>"><?= $r['ctr'] ?>%</b></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="empty">ยังไม่มีโพสต์ที่เผยแพร่ — กรอกตัวเลขผลลัพธ์ได้ที่เมนู "วิเคราะห์ & แนะนำ Boost"</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
