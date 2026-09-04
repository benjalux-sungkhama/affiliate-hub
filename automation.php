<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/automation.php';
require_login();
$u = uid();
$pdo = db();

/** Blueprint สำเร็จรูป 6 แบบ (§8) */
function automation_blueprints(): array
{
    return [
        'new_product' => [
            'name' => 'สินค้าใหม่เข้า โพสต์เปิดตัวทันที', 'trigger' => 'product.created',
            'desc' => 'ยิงเมื่อเพิ่มสินค้าใหม่ที่มีรูป/ราคา/สต๊อกครบ',
            'conditions' => [['field' => 'product.image_count', 'op' => '>=', 'value' => 1],
                             ['field' => 'product.price', 'op' => '>', 'value' => 0],
                             ['field' => 'product.stock', 'op' => '>=', 'value' => 5]],
            'platforms' => 'tiktok,facebook', 'delay' => 30,
            'guardrails' => ['max_runs_per_day' => 3, 'cooldown_days_per_product' => 7],
        ],
        'low_stock' => [
            'name' => 'สต๊อกใกล้หมด เร่งระบาย', 'trigger' => 'product.low_stock',
            'desc' => 'ยิงเมื่อสต๊อกต่ำกว่าเกณฑ์',
            'conditions' => [['field' => 'product.stock', 'op' => '<', 'value' => 10]],
            'platforms' => 'facebook', 'delay' => 0,
            'guardrails' => ['cooldown_days_per_product' => 3],
        ],
        'high_perf' => [
            'name' => 'โพสต์ที่ปัง ทำซ้ำอีกเวอร์ชัน', 'trigger' => 'post.high_performing',
            'desc' => 'ยิงเมื่อโพสต์เก่า Reach ทะลุเกณฑ์และอายุ ≥ 14 วัน',
            'conditions' => [['field' => 'post.reach', 'op' => '>', 'value' => 10000],
                             ['field' => 'post.age_days', 'op' => '>=', 'value' => 14]],
            'platforms' => 'tiktok', 'delay' => 0, 'guardrails' => ['max_runs_per_day' => 2],
        ],
        'before_live' => [
            'name' => 'ปั๊มโปรโมตก่อนไลฟ์', 'trigger' => 'schedule.before_live',
            'desc' => 'โปรโมตล่วงหน้าก่อนไลฟ์',
            'conditions' => [], 'platforms' => 'facebook,tiktok', 'delay' => 0,
            'trigger_config' => ['hours_before' => 3], 'guardrails' => [],
        ],
        'daily' => [
            'name' => 'คอนเทนต์ประจำวัน', 'trigger' => 'schedule.daily',
            'desc' => 'สุ่มสินค้ากำไร ≥ 80 ที่ไม่ซ้ำ 7 วัน โพสต์วันละครั้ง',
            'conditions' => [['field' => 'product.profit', 'op' => '>=', 'value' => 80]],
            'platforms' => 'tiktok,facebook', 'delay' => 0,
            'trigger_config' => ['time' => '10:00'],
            'guardrails' => ['max_runs_per_day' => 1, 'cooldown_days_per_product' => 7],
        ],
        'after_live' => [
            'name' => 'สรุปหลังไลฟ์', 'trigger' => 'live.ended',
            'desc' => 'ไลฟ์จบ → โพสต์สรุป/ขอบคุณ',
            'conditions' => [], 'platforms' => 'facebook', 'delay' => 0, 'guardrails' => [],
        ],
    ];
}

// ----- ส่งออก JSON (§ผ.ก.A) -----
if (isset($_GET['export'])) {
    $st = $pdo->prepare('SELECT * FROM automation_rules WHERE id=? AND user_id=?');
    $st->execute([(int)$_GET['export'], $u]);
    if ($r = $st->fetch()) {
        $out = [
            'name' => $r['name'], 'description' => $r['description'],
            'trigger' => ['type' => $r['trigger_type'], 'config' => json_decode($r['trigger_config'] ?? '{}', true) ?: new stdClass()],
            'conditions' => json_decode($r['conditions'] ?? '[]', true) ?: [],
            'formula_id' => (int)$r['formula_id'],
            'actions' => json_decode($r['actions'] ?? '[]', true) ?: [],
            'approval_mode' => $r['approval_mode'],
            'guardrails' => json_decode($r['guardrails'] ?? '{}', true) ?: new stdClass(),
            'is_active' => false,
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="rule-' . $r['id'] . '.json"');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

// ----- POST actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_rule') {
        $id = (int)($_POST['id'] ?? 0);
        $formulaId = (int)($_POST['formula_id'] ?? 0);
        // สร้าง actions จากช่องปลายทาง + delay
        $platforms = array_filter(array_map('trim', explode(',', $_POST['platforms'] ?? '')));
        $actions = [
            ['type' => 'generate_content', 'config' => ['variant_count' => 1]],
            ['type' => 'enqueue_post', 'config' => ['platforms' => array_values($platforms),
                'delay_minutes' => (int)($_POST['delay_minutes'] ?? 0)]],
            ['type' => 'notify', 'config' => ['channel' => 'in_app']],
        ];
        $conds = json_decode($_POST['conditions'] ?? '[]', true);
        if (!is_array($conds)) { $conds = []; }
        $tcfg = json_decode($_POST['trigger_config'] ?? '{}', true);
        if (!is_array($tcfg)) { $tcfg = []; }
        $guard = [];
        if (($_POST['max_runs_per_day'] ?? '') !== '') { $guard['max_runs_per_day'] = (int)$_POST['max_runs_per_day']; }
        if (($_POST['cooldown_days_per_product'] ?? '') !== '') { $guard['cooldown_days_per_product'] = (int)$_POST['cooldown_days_per_product']; }

        // ตรวจว่าสูตรเป็นของผู้ใช้จริง
        $chk = $pdo->prepare('SELECT COUNT(*) FROM content_formulas WHERE id=? AND user_id=?');
        $chk->execute([$formulaId, $u]);
        if (!$chk->fetchColumn()) {
            flash('เลือกสูตรที่ถูกต้องก่อน (ต้องเป็นสูตรของคุณ)', 'err');
            redirect('automation.php?tab=rules');
        }

        if ($id) {
            // แก้ไข — เปลี่ยนโหมดได้ (escalate)
            $mode = in_array($_POST['approval_mode'] ?? 'draft', ['draft', 'review', 'auto'], true) ? $_POST['approval_mode'] : 'draft';
            $st = $pdo->prepare(
                'UPDATE automation_rules SET name=?,description=?,trigger_type=?,trigger_config=?,conditions=?,
                 formula_id=?,actions=?,approval_mode=?,guardrails=? WHERE id=? AND user_id=?'
            );
            $st->execute([
                trim($_POST['name'] ?? ''), trim($_POST['description'] ?? '') ?: null,
                $_POST['trigger_type'] ?? 'schedule.daily', json_encode($tcfg, JSON_UNESCAPED_UNICODE),
                json_encode($conds, JSON_UNESCAPED_UNICODE), $formulaId,
                json_encode($actions, JSON_UNESCAPED_UNICODE), $mode,
                json_encode($guard, JSON_UNESCAPED_UNICODE), $id, $u,
            ]);
            flash('บันทึกกฎแล้ว');
        } else {
            // สร้างใหม่ — บังคับเริ่มที่ Draft + ปิดใช้งาน (§6)
            $st = $pdo->prepare(
                'INSERT INTO automation_rules (user_id,name,description,trigger_type,trigger_config,conditions,
                 formula_id,actions,approval_mode,guardrails,is_active)
                 VALUES (?,?,?,?,?,?,?,?,\'draft\',?,0)'
            );
            $st->execute([
                $u, trim($_POST['name'] ?? ''), trim($_POST['description'] ?? '') ?: null,
                $_POST['trigger_type'] ?? 'schedule.daily', json_encode($tcfg, JSON_UNESCAPED_UNICODE),
                json_encode($conds, JSON_UNESCAPED_UNICODE), $formulaId,
                json_encode($actions, JSON_UNESCAPED_UNICODE), json_encode($guard, JSON_UNESCAPED_UNICODE),
            ]);
            flash('สร้างกฎแล้ว (เริ่มที่โหมด Draft และปิดอยู่ — เปิดสวิตช์เมื่อพร้อม)');
        }
        redirect('automation.php?tab=rules');
    }

    if ($action === 'toggle_rule') {
        $pdo->prepare('UPDATE automation_rules SET is_active=1-is_active, fail_streak=0 WHERE id=? AND user_id=?')
            ->execute([(int)$_POST['id'], $u]);
        redirect('automation.php?tab=rules');
    }

    if ($action === 'delete_rule') {
        $pdo->prepare('DELETE FROM automation_rules WHERE id=? AND user_id=?')->execute([(int)$_POST['id'], $u]);
        flash('ลบกฎแล้ว');
        redirect('automation.php?tab=rules');
    }

    if ($action === 'dry_run') {
        $st = $pdo->prepare('SELECT * FROM automation_rules WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['id'], $u]);
        if ($rule = $st->fetch()) {
            $ctx = ['time.hour' => (int)date('G'), 'time.dow' => (int)date('w')];
            $prod = $pdo->prepare('SELECT p.*, c.name category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.user_id=? AND p.is_active=1 ORDER BY RAND() LIMIT 1');
            $prod->execute([$u]);
            if ($p = $prod->fetch()) { $ctx = array_merge($ctx, automation_product_context($p)); }
            $r = automation_run_rule($pdo, $rule, $ctx, true);
            $_SESSION['dry'] = $r;
        }
        redirect('automation.php?tab=rules');
    }

    if ($action === 'run_now') {
        $st = $pdo->prepare('SELECT * FROM automation_rules WHERE id=? AND user_id=?');
        $st->execute([(int)$_POST['id'], $u]);
        if ($rule = $st->fetch()) {
            $ctx = ['time.hour' => (int)date('G'), 'time.dow' => (int)date('w')];
            $prod = $pdo->prepare('SELECT p.*, c.name category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.user_id=? AND p.is_active=1 ORDER BY RAND() LIMIT 1');
            $prod->execute([$u]);
            if ($p = $prod->fetch()) { $ctx = array_merge($ctx, automation_product_context($p)); }
            $r = automation_run_rule($pdo, $rule, $ctx, false);
            flash('รันแล้ว: ' . $r['status'] . ($r['reason'] ? ' — ' . $r['reason'] : ''),
                $r['status'] === 'failed' ? 'err' : 'ok');
        }
        redirect('automation.php?tab=rules');
    }

    if ($action === 'approve') {
        $st = $pdo->prepare('SELECT * FROM automation_approvals WHERE id=? AND user_id=? AND status=\'pending\'');
        $st->execute([(int)$_POST['id'], $u]);
        if ($ap = $st->fetch()) {
            // สร้างโพสต์เข้าคิวจากคอนเทนต์ที่อนุมัติ
            $caption = trim($_POST['caption'] ?? $ap['caption']);
            foreach (array_filter(array_map('trim', explode(',', $ap['platforms'] ?? ''))) as $code) {
                $pf = $pdo->prepare('SELECT id FROM platforms WHERE code=?');
                $pf->execute([$code]);
                $ins = $pdo->prepare(
                    'INSERT INTO posts (user_id,platform_id,product_id,formula_id,title,caption,media_type,status,scheduled_at)
                     VALUES (?,?,?,?,?,?,?,\'queued\',NOW())'
                );
                $ins->execute([$u, $pf->fetchColumn() ?: null, $ap['product_id'] ?: null,
                    $ap['formula_id'] ?: null, '[Auto] อนุมัติแล้ว', $caption, 'video']);
            }
            $pdo->prepare('UPDATE automation_approvals SET status=\'approved\', decided_at=NOW() WHERE id=?')
                ->execute([(int)$ap['id']]);
            flash('อนุมัติและเข้าคิวโพสต์แล้ว');
        }
        redirect('automation.php?tab=approvals');
    }

    if ($action === 'reject') {
        $pdo->prepare('UPDATE automation_approvals SET status=\'rejected\', decided_at=NOW() WHERE id=? AND user_id=?')
            ->execute([(int)$_POST['id'], $u]);
        flash('ทิ้งคอนเทนต์นี้แล้ว');
        redirect('automation.php?tab=approvals');
    }

    if ($action === 'install_blueprint') {
        $bp = automation_blueprints()[$_POST['key'] ?? ''] ?? null;
        if ($bp) {
            // ใช้สูตรค่าเริ่มต้น/ตัวแรกของผู้ใช้
            $f = $pdo->prepare('SELECT id FROM content_formulas WHERE user_id=? AND is_active=1 AND parent_id IS NULL ORDER BY is_default DESC, id LIMIT 1');
            $f->execute([$u]);
            $formulaId = (int)$f->fetchColumn();
            if (!$formulaId) {
                flash('ติดตั้ง Blueprint ไม่ได้ — ยังไม่มีสูตรในคลัง ไปสร้างสูตรก่อน', 'err');
                redirect('automation.php?tab=blueprints');
            }
            $actions = [
                ['type' => 'generate_content', 'config' => ['variant_count' => 1]],
                ['type' => 'enqueue_post', 'config' => ['platforms' => explode(',', $bp['platforms']), 'delay_minutes' => $bp['delay']]],
                ['type' => 'notify', 'config' => ['channel' => 'in_app']],
            ];
            $ins = $pdo->prepare(
                'INSERT INTO automation_rules (user_id,name,description,trigger_type,trigger_config,conditions,
                 formula_id,actions,approval_mode,guardrails,is_active)
                 VALUES (?,?,?,?,?,?,?,?,\'draft\',?,0)'
            );
            $ins->execute([
                $u, $bp['name'], $bp['desc'], $bp['trigger'],
                json_encode($bp['trigger_config'] ?? new stdClass(), JSON_UNESCAPED_UNICODE),
                json_encode($bp['conditions'], JSON_UNESCAPED_UNICODE), $formulaId,
                json_encode($actions, JSON_UNESCAPED_UNICODE),
                json_encode($bp['guardrails'], JSON_UNESCAPED_UNICODE),
            ]);
            flash('ติดตั้ง Blueprint "' . $bp['name'] . '" แล้ว (โหมด Draft ปิดอยู่)');
        }
        redirect('automation.php?tab=rules');
    }

    if ($action === 'import_rule') {
        $data = json_decode($_POST['json'] ?? '', true);
        if (is_array($data) && !empty($data['name']) && !empty($data['formula_id'])) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM content_formulas WHERE id=? AND user_id=?');
            $chk->execute([(int)$data['formula_id'], $u]);
            if (!$chk->fetchColumn()) {
                flash('formula_id ใน JSON ไม่ใช่สูตรของคุณ', 'err');
                redirect('automation.php?tab=rules');
            }
            $ins = $pdo->prepare(
                'INSERT INTO automation_rules (user_id,name,description,trigger_type,trigger_config,conditions,
                 formula_id,actions,approval_mode,guardrails,is_active)
                 VALUES (?,?,?,?,?,?,?,?,\'draft\',?,0)'
            );
            $ins->execute([
                $u, $data['name'], $data['description'] ?? null,
                $data['trigger']['type'] ?? 'schedule.daily',
                json_encode($data['trigger']['config'] ?? new stdClass(), JSON_UNESCAPED_UNICODE),
                json_encode($data['conditions'] ?? [], JSON_UNESCAPED_UNICODE), (int)$data['formula_id'],
                json_encode($data['actions'] ?? [], JSON_UNESCAPED_UNICODE),
                json_encode($data['guardrails'] ?? new stdClass(), JSON_UNESCAPED_UNICODE),
            ]);
            flash('นำเข้ากฎจาก JSON แล้ว (โหมด Draft ปิดอยู่)');
        } else {
            flash('JSON ไม่ถูกต้อง (ต้องมี name และ formula_id)', 'err');
        }
        redirect('automation.php?tab=rules');
    }

    if ($action === 'kill_switch') {
        automation_settings($pdo, $u); // ensure row
        $pdo->prepare('UPDATE automation_settings SET kill_switch=1-kill_switch WHERE user_id=?')->execute([$u]);
        flash('สลับ Kill Switch แล้ว');
        redirect('automation.php?tab=settings');
    }

    if ($action === 'save_guardrails') {
        $g = automation_default_guardrails();
        foreach (array_keys($g) as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') { $g[$k] = is_numeric($_POST[$k]) ? (int)$_POST[$k] : $_POST[$k]; }
        }
        automation_settings($pdo, $u);
        $pdo->prepare('UPDATE automation_settings SET guardrails=? WHERE user_id=?')
            ->execute([json_encode($g, JSON_UNESCAPED_UNICODE), $u]);
        flash('บันทึก Guardrails แล้ว');
        redirect('automation.php?tab=settings');
    }
}

$tab = in_array($_GET['tab'] ?? 'rules', ['rules', 'approvals', 'runs', 'blueprints', 'settings'], true) ? $_GET['tab'] : 'rules';
$set = automation_settings($pdo, $u);
$formulasList = $pdo->prepare('SELECT id,name FROM content_formulas WHERE user_id=? AND parent_id IS NULL ORDER BY is_default DESC, name');
$formulasList->execute([$u]);
$formulas = $formulasList->fetchAll();
$pcStmt = $pdo->prepare("SELECT COUNT(*) FROM automation_approvals WHERE user_id=? AND status='pending'");
$pcStmt->execute([$u]);
$pendingCount = (int)$pcStmt->fetchColumn();

$__title = 'ระบบอัตโนมัติ';
$__active = 'automation';
include __DIR__ . '/includes/header.php';
?>
<?php if ((int)$set['kill_switch'] === 1): ?>
    <div class="alert alert-err">🔴 Kill Switch เปิดอยู่ — ทุกกฎถูกหยุดชั่วคราว (ปิดได้ที่แท็บ ตั้งค่า Guardrails)</div>
<?php endif; ?>

<div class="tabs">
    <a class="tab <?= $tab === 'rules' ? 'active' : '' ?>" href="<?= url('automation.php?tab=rules') ?>">กฎทั้งหมด</a>
    <a class="tab <?= $tab === 'approvals' ? 'active' : '' ?>" href="<?= url('automation.php?tab=approvals') ?>">รออนุมัติ<?= $pendingCount ? ' (' . $pendingCount . ')' : '' ?></a>
    <a class="tab <?= $tab === 'runs' ? 'active' : '' ?>" href="<?= url('automation.php?tab=runs') ?>">ประวัติการทำงาน</a>
    <a class="tab <?= $tab === 'blueprints' ? 'active' : '' ?>" href="<?= url('automation.php?tab=blueprints') ?>">สูตรสำเร็จ</a>
    <a class="tab <?= $tab === 'settings' ? 'active' : '' ?>" href="<?= url('automation.php?tab=settings') ?>">ตั้งค่า Guardrails</a>
</div>

<?php
$triggers = automation_triggers();

// ============ แท็บ: กฎทั้งหมด ============
if ($tab === 'rules'):
    $editing = null;
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM automation_rules WHERE id=? AND user_id=?');
        $st->execute([(int)$_GET['edit'], $u]);
        $editing = $st->fetch() ?: null;
    }
    $showForm = $editing !== null || isset($_GET['new']);

    // แสดงผล Dry Run ถ้ามี
    if (!empty($_SESSION['dry'])):
        $d = $_SESSION['dry']; unset($_SESSION['dry']); ?>
        <div class="card" style="margin-bottom:16px">
            <div class="section-head"><h3>▶️ ผลทดลองรัน (Dry Run)</h3><span class="pill"><?= e($d['status']) ?></span></div>
            <?php if (!empty($d['preview']['storyboard'])): ?>
                <label>Storyboard</label><pre><?= e($d['preview']['storyboard']) ?></pre>
                <label>แคปชั่น</label><pre><?= e($d['preview']['caption']) ?></pre>
                <p class="muted">ปลายทาง: <?= e($d['preview']['platforms']) ?> · ยังไม่โพสต์จริง</p>
            <?php else: ?>
                <p class="muted">ไม่ได้สร้างคอนเทนต์ — เหตุผล: <?= e($d['reason'] ?: '—') ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
        <div class="card">
            <div class="section-head">
                <h3><?= $editing ? 'แก้ไขกฎ' : '+ สร้างกฎใหม่' ?></h3>
                <a class="pill" href="<?= url('automation.php?tab=rules') ?>">← กลับ</a>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_rule">
                <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
                <label>ชื่อกฎ</label>
                <input name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="เช่น สินค้าใหม่เข้า โพสต์ TikTok ทันที">
                <label>คำอธิบาย</label>
                <input name="description" value="<?= e($editing['description'] ?? '') ?>">
                <div class="form-row">
                    <div><label>ทริกเกอร์</label>
                        <select name="trigger_type">
                            <?php foreach ($triggers as $k => $v): ?>
                                <option value="<?= e($k) ?>" <?= ($editing['trigger_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?> (<?= e($k) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>สูตรที่ใช้ (บังคับ)</label>
                        <select name="formula_id" required>
                            <option value="">— เลือกสูตร —</option>
                            <?php foreach ($formulas as $f): ?>
                                <option value="<?= (int)$f['id'] ?>" <?= (int)($editing['formula_id'] ?? 0) === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div><label>ปลายทาง (คั่นด้วยจุลภาค)</label>
                        <?php $curPlat = 'tiktok,facebook';
                        if ($editing) { $a = json_decode($editing['actions'] ?? '[]', true) ?: []; foreach ($a as $x) { if (($x['type'] ?? '') === 'enqueue_post') { $curPlat = implode(',', $x['config']['platforms'] ?? []); } } } ?>
                        <input name="platforms" value="<?= e($curPlat) ?>"></div>
                    <div><label>หน่วงก่อนโพสต์ (นาที)</label>
                        <?php $curDelay = 0; if ($editing) { $a = json_decode($editing['actions'] ?? '[]', true) ?: []; foreach ($a as $x) { if (($x['type'] ?? '') === 'enqueue_post') { $curDelay = (int)($x['config']['delay_minutes'] ?? 0); } } } ?>
                        <input type="number" name="delay_minutes" value="<?= $curDelay ?>"></div>
                </div>
                <?php $eg = json_decode($editing['guardrails'] ?? '{}', true) ?: []; ?>
                <div class="form-row">
                    <div><label>เพดานยิงต่อวัน (กฎนี้)</label><input type="number" name="max_runs_per_day" value="<?= e($eg['max_runs_per_day'] ?? '') ?>" placeholder="เว้นว่าง = ไม่จำกัด"></div>
                    <div><label>เว้นสินค้าซ้ำ (วัน)</label><input type="number" name="cooldown_days_per_product" value="<?= e($eg['cooldown_days_per_product'] ?? '') ?>" placeholder="เช่น 7"></div>
                </div>
                <?php if ($editing): ?>
                    <label>โหมดอนุมัติ</label>
                    <select name="approval_mode">
                        <?php foreach (['draft' => '🟢 Draft — เก็บร่าง', 'review' => '🟡 Review — รออนุมัติ (แนะนำ)', 'auto' => '🔴 Auto — โพสต์เอง'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($editing['approval_mode'] ?? 'draft') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">เปิด Auto ได้ต่อเมื่อผ่านเช็กลิสต์ (ดู docs/automation-rules.md ผ.ก.B)</p>
                <?php else: ?>
                    <p class="hint">กฎใหม่เริ่มที่โหมด <b>Draft</b> และปิดอยู่เสมอ (§6) — เปลี่ยนโหมดได้หลังบันทึก</p>
                <?php endif; ?>
                <label>เงื่อนไข (JSON — ไม่บังคับ)</label>
                <textarea name="conditions" placeholder='[{"field":"product.profit","op":">=","value":80}]'><?= e($editing['conditions'] ?? '') ?></textarea>
                <label>Trigger config (JSON — ไม่บังคับ เช่น เวลา/ชั่วโมง)</label>
                <textarea name="trigger_config" placeholder='{"time":"10:00"}'><?= e($editing['trigger_config'] ?? '') ?></textarea>
                <button class="btn btn-primary" style="margin-top:14px"><?= $editing ? 'บันทึก' : 'สร้างกฎ' ?></button>
            </form>
        </div>
    <?php else: ?>
        <div class="section-head">
            <span class="pill"><?= (int)$pdo->query("SELECT COUNT(*) FROM automation_rules WHERE user_id=" . (int)$u)->fetchColumn() ?> กฎ</span>
            <a class="btn btn-primary" style="margin-left:auto" href="<?= url('automation.php?tab=rules&new=1') ?>">+ สร้างกฎ</a>
            <button class="btn" onclick="document.getElementById('imp').hidden=!document.getElementById('imp').hidden">นำเข้า JSON</button>
        </div>
        <div class="card" id="imp" hidden style="margin-bottom:16px">
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="action" value="import_rule">
                <label>วาง JSON ของกฎ (ตาม ผ.ก.A)</label>
                <textarea name="json" placeholder='{"name":"...","trigger":{"type":"product.created"},"formula_id":1,"actions":[]}'></textarea>
                <button class="btn btn-primary" style="margin-top:10px">นำเข้า</button>
            </form>
        </div>
        <?php
        $st = $pdo->prepare('SELECT r.*, f.name formula FROM automation_rules r LEFT JOIN content_formulas f ON f.id=r.formula_id WHERE r.user_id=? ORDER BY r.created_at DESC');
        $st->execute([$u]);
        $rules = $st->fetchAll();
        $modeBadge = ['draft' => '<span class="badge badge-gray">Draft</span>', 'review' => '<span class="badge badge-orange">Review</span>', 'auto' => '<span class="badge badge-red">Auto</span>'];
        ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ชื่อกฎ</th><th>ทริกเกอร์</th><th>สูตร</th><th>โหมด</th><th>ยิงล่าสุด</th><th>สำเร็จ/ข้าม/ล้ม</th><th>สวิตช์</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rules as $r): ?>
                    <tr>
                        <td class="wrap"><b><?= e($r['name']) ?></b><?php if ($r['description']): ?><div class="muted" style="font-size:12px"><?= e($r['description']) ?></div><?php endif; ?></td>
                        <td><?= e($triggers[$r['trigger_type']] ?? $r['trigger_type']) ?></td>
                        <td class="wrap"><?= e($r['formula'] ?: '—') ?></td>
                        <td><?= $modeBadge[$r['approval_mode']] ?? e($r['approval_mode']) ?></td>
                        <td><?= e($r['last_run_at'] ? substr($r['last_run_at'], 0, 16) : '—') ?></td>
                        <td><?= (int)$r['success_count'] ?>/<?= (int)$r['skip_count'] ?>/<?= (int)$r['fail_count'] ?></td>
                        <td>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_rule"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm <?= $r['is_active'] ? 'btn-primary' : '' ?>"><?= $r['is_active'] ? 'เปิด' : 'ปิด' ?></button>
                            </form>
                        </td>
                        <td><div class="btn-row">
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="dry_run"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm">▶️ ทดลอง</button></form>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="run_now"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm">รันเดี๋ยวนี้</button></form>
                            <a class="btn btn-sm" href="<?= url('automation.php?tab=rules&edit=' . (int)$r['id']) ?>">แก้</a>
                            <a class="btn btn-sm" href="<?= url('automation.php?export=' . (int)$r['id']) ?>">JSON</a>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_rule"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="ลบกฎนี้?">ลบ</button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rules): ?><tr><td colspan="8" class="empty">ยังไม่มีกฎ — กด "+ สร้างกฎ" หรือติดตั้งจากแท็บ "สูตรสำเร็จ"</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php
// ============ แท็บ: รออนุมัติ ============
elseif ($tab === 'approvals'):
    $st = $pdo->prepare('SELECT a.*, r.name rule_name FROM automation_approvals a LEFT JOIN automation_rules r ON r.id=a.rule_id WHERE a.user_id=? AND a.status=\'pending\' ORDER BY a.created_at DESC');
    $st->execute([$u]);
    $aps = $st->fetchAll();
?>
    <?php if (!$aps): ?><div class="empty">ไม่มีคอนเทนต์รออนุมัติ</div><?php endif; ?>
    <?php foreach ($aps as $ap): ?>
        <div class="card" style="margin-bottom:14px">
            <div class="section-head">
                <h3><?= e($ap['rule_name'] ?: 'กฎ') ?></h3>
                <span class="pill"><?= e($ap['platforms']) ?></span>
                <span class="muted" style="margin-left:auto"><?= e(substr($ap['created_at'], 0, 16)) ?></span>
            </div>
            <details style="margin:0 0 10px"><summary class="muted">ดู Storyboard</summary><pre><?= e($ap['storyboard']) ?></pre></details>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="<?= (int)$ap['id'] ?>">
                <label>แคปชั่น (แก้ได้ก่อนอนุมัติ)</label>
                <textarea name="caption"><?= e($ap['caption']) ?></textarea>
                <div class="btn-row" style="margin-top:10px">
                    <button class="btn btn-primary">✅ อนุมัติ & เข้าคิว</button>
                </div>
            </form>
            <form method="post" style="margin-top:8px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?= (int)$ap['id'] ?>">
                <button class="btn btn-danger btn-sm" data-confirm="ทิ้งคอนเทนต์นี้?">❌ ทิ้ง</button>
            </form>
        </div>
    <?php endforeach; ?>

<?php
// ============ แท็บ: ประวัติการทำงาน ============
elseif ($tab === 'runs'):
    $st = $pdo->prepare('SELECT ru.*, r.name rule_name FROM automation_runs ru LEFT JOIN automation_rules r ON r.id=ru.rule_id WHERE ru.user_id=? ORDER BY ru.created_at DESC LIMIT 200');
    $st->execute([$u]);
    $runs = $st->fetchAll();
    $badge = ['success' => 'badge-green', 'skip' => 'badge-gray', 'failed' => 'badge-red', 'dry' => 'badge-blue'];
?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>เวลา</th><th>กฎ</th><th>ผล</th><th>เหตุผล</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
                    <td class="wrap"><?= e($r['rule_name'] ?: '—') ?></td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'badge-gray' ?>"><?= e($r['status']) ?></span></td>
                    <td class="wrap"><?= e($r['reason'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$runs): ?><tr><td colspan="4" class="empty">ยังไม่มีประวัติการทำงาน</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

<?php
// ============ แท็บ: สูตรสำเร็จ (Blueprints) ============
elseif ($tab === 'blueprints'): ?>
    <div class="grid grid-2" style="align-items:start">
        <?php foreach (automation_blueprints() as $key => $bp): ?>
            <div class="card">
                <div class="section-head"><h3 style="flex:1"><?= e($bp['name']) ?></h3><span class="pill"><?= e($bp['trigger']) ?></span></div>
                <p class="muted"><?= e($bp['desc']) ?></p>
                <p class="muted" style="font-size:12px">ปลายทาง: <?= e($bp['platforms']) ?><?= $bp['delay'] ? ' · หน่วง ' . (int)$bp['delay'] . ' นาที' : '' ?></p>
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="install_blueprint">
                    <input type="hidden" name="key" value="<?= e($key) ?>">
                    <button class="btn btn-primary btn-sm">ติดตั้ง Blueprint</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="hint" style="margin-top:12px">ทุก Blueprint ติดตั้งเป็นโหมด Draft และปิดอยู่ — ผูกกับสูตรค่าเริ่มต้นในคลังของคุณ</p>

<?php
// ============ แท็บ: ตั้งค่า Guardrails ============
elseif ($tab === 'settings'):
    $g = $set['guardrails'];
?>
    <div class="card" style="margin-bottom:16px">
        <div class="section-head">
            <h3 style="flex:1">🔴 Kill Switch</h3>
            <span class="badge <?= $set['kill_switch'] ? 'badge-red' : 'badge-green' ?>"><?= $set['kill_switch'] ? 'เปิด (หยุดทุกกฎ)' : 'ปิด (ทำงานปกติ)' ?></span>
        </div>
        <p class="muted">กดเพื่อหยุด/ปล่อยทุกกฎทันทีทั้งบัญชี</p>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="kill_switch">
            <button class="btn <?= $set['kill_switch'] ? 'btn-primary' : 'btn-danger' ?>"><?= $set['kill_switch'] ? 'ปลด Kill Switch' : 'เปิด Kill Switch (หยุดทุกกฎ)' ?></button>
        </form>
    </div>

    <div class="card">
        <h3>Guardrails (ระดับบัญชี §7)</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_guardrails">
            <div class="form-row">
                <div><label>เพดานโพสต์อัตโนมัติ/วัน</label><input type="number" name="max_auto_posts_per_day" value="<?= (int)$g['max_auto_posts_per_day'] ?>"></div>
                <div><label>เว้นระยะขั้นต่ำ (นาที)</label><input type="number" name="min_gap_minutes" value="<?= (int)$g['min_gap_minutes'] ?>"></div>
            </div>
            <div class="form-row">
                <div><label>กันสินค้าซ้ำ (วัน)</label><input type="number" name="product_cooldown_days" value="<?= (int)$g['product_cooldown_days'] ?>"></div>
                <div><label>เพดานเรียก AI/วัน</label><input type="number" name="max_ai_calls_per_day" value="<?= (int)$g['max_ai_calls_per_day'] ?>"></div>
            </div>
            <div class="form-row">
                <div><label>ห้ามโพสต์ตั้งแต่</label><input name="quiet_from" value="<?= e($g['quiet_from']) ?>" placeholder="23:00"></div>
                <div><label>ถึงเวลา</label><input name="quiet_to" value="<?= e($g['quiet_to']) ?>" placeholder="07:00"></div>
            </div>
            <p class="hint">ล้มเหลว <?= (int)$g['fail_streak_disable'] ?> ครั้งติด → ปิดกฎอัตโนมัติ (ค่านี้ล็อกไว้ตามสเปก)</p>
            <button class="btn btn-primary" style="margin-top:12px">บันทึก Guardrails</button>
        </form>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
