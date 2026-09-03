<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

// สินค้าไว้เลือกเติมตัวแปร
$ps = $pdo->prepare('SELECT * FROM products WHERE user_id=? ORDER BY name');
$ps->execute([$u]);
$products = $ps->fetchAll();

// สูตรที่ใช้งานได้
$fs = $pdo->prepare('SELECT * FROM content_formulas WHERE user_id=? AND is_active=1 AND parent_id IS NULL ORDER BY is_default DESC, name');
$fs->execute([$u]);
$formulas = $fs->fetchAll();

$selFormulaId = (int)($_GET['formula'] ?? $_POST['formula_id'] ?? 0);
if (!$selFormulaId && $formulas) {
    // ถ้าไม่ได้เลือก ใช้สูตรค่าเริ่มต้นตัวแรก
    $selFormulaId = (int)$formulas[0]['id'];
}

$formula = null;
$scenes = [];
if ($selFormulaId) {
    $st = $pdo->prepare('SELECT * FROM content_formulas WHERE id=? AND user_id=?');
    $st->execute([$selFormulaId, $u]);
    $formula = $st->fetch() ?: null;
    if ($formula) {
        $sc = $pdo->prepare('SELECT * FROM content_formula_scenes WHERE formula_id=? ORDER BY seq');
        $sc->execute([$selFormulaId]);
        $scenes = $sc->fetchAll();
    }
}

$result = null;
$missing = [];
$vars = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate' && $formula) {
    csrf_check();
    // เติมค่าจากสินค้าที่เลือก
    $pid = (int)($_POST['product_id'] ?? 0);
    if ($pid) {
        foreach ($products as $p) {
            if ((int)$p['id'] === $pid) {
                $vars['product_name'] = $vars['product_name'] ?? $p['name'];
                $vars['price'] = $vars['price'] ?? (string)money($p['price']);
                break;
            }
        }
    }
    // ค่าที่กรอกมาเอง
    foreach (['product_name', 'price', 'usp', 'target', 'cta', 'platform'] as $k) {
        if (trim($_POST[$k] ?? '') !== '') {
            $vars[$k] = trim($_POST[$k]);
        }
    }

    // รวมข้อความสูตรทั้งหมดเพื่อตรวจตัวแปรที่ขาด
    $allText = $formula['name'] . ' ' . $formula['notes'];
    foreach ($scenes as $s) {
        $allText .= ' ' . $s['description'] . ' ' . $s['overlay_text'];
    }
    $missing = missing_variables($allText, $vars);

    if (!$missing) {
        // สร้าง storyboard + caption
        $lines = [];
        $lines[] = '🎬 ' . fill_variables($formula['name'], $vars)
            . ' (' . (int)$formula['total_seconds'] . ' วินาที · ' . count($scenes) . ' ซีน)';
        $lines[] = '';
        foreach ($scenes as $s) {
            $lines[] = sprintf(
                'ซีน %d [%.1f–%.1f วิ] %s',
                $s['seq'], $s['time_from'], $s['time_to'], fill_variables($s['description'] ?? '', $vars)
            );
            $extra = [];
            if ($s['camera_angle']) {
                $extra[] = '📷 ' . $s['camera_angle'];
            }
            if ($s['lighting']) {
                $extra[] = '💡 ' . $s['lighting'];
            }
            if ($s['overlay_text']) {
                $extra[] = '🅰️ ' . fill_variables($s['overlay_text'], $vars);
            }
            if ($extra) {
                $lines[] = '   ' . implode('  ·  ', $extra);
            }
        }
        $storyboard = implode("\n", $lines);
        $caption = sprintf(
            "%s ✨\n%s\n📌 %s | ราคา %s บาท\n👉 %s",
            $vars['product_name'] ?? 'สินค้าใหม่',
            $vars['usp'] ?? '',
            $vars['target'] ?? 'ทุกคน',
            $vars['price'] ?? '-',
            $vars['cta'] ?? 'ทักแชทสั่งเลย!'
        );

        // บันทึกลง ai_contents + usage
        $ins = $pdo->prepare(
            'INSERT INTO ai_contents (user_id,product_id,formula_id,prompt,caption,storyboard) VALUES (?,?,?,?,?,?)'
        );
        $ins->execute([$u, $pid ?: null, $formula['id'], json_encode($vars, JSON_UNESCAPED_UNICODE), $caption, $storyboard]);
        $usg = $pdo->prepare(
            'INSERT INTO content_formula_usages (formula_id,user_id,product_id) VALUES (?,?,?)'
        );
        $usg->execute([$formula['id'], $u, $pid ?: null]);

        $result = ['storyboard' => $storyboard, 'caption' => $caption, 'vars' => $vars];
    }
}

$__title = 'ให้ AI คิดคอนเทนต์';
$__active = 'ai';
include __DIR__ . '/includes/header.php';
?>
<div class="tabs">
    <a class="tab" href="<?= url('formulas.php') ?>">📚 คลังสูตรของฉัน</a>
    <a class="tab active" href="<?= url('ai-content.php') ?>">🤖 ให้ AI คิดคอนเทนต์</a>
</div>

<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <h3>Storyboard มินิมอล 10 วินาที</h3>
        <p class="muted">เลือกสูตรจากคลัง เลือกสินค้า แล้วเติมข้อมูล ระบบจะเติมตัวแปร <code>{{ }}</code> ให้อัตโนมัติ</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate">
            <label>เลือกสูตร</label>
            <select name="formula_id" onchange="this.form.action='';this.form.submit()">
                <?php foreach ($formulas as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= $selFormulaId === (int)$f['id'] ? 'selected' : '' ?>>
                        <?= e($f['name']) ?><?= $f['is_default'] ? ' (ค่าเริ่มต้น)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$formulas): ?>
                <p class="hint">ยังไม่มีสูตร — ไป <a href="<?= url('formulas.php?new=1') ?>">สร้างสูตร</a> ก่อน</p>
            <?php endif; ?>

            <label>สินค้า (เติมชื่อ/ราคาให้อัตโนมัติ)</label>
            <select name="product_id">
                <option value="">— ไม่ผูกสินค้า —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)($_POST['product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($missing): ?>
                <div class="alert alert-info" style="margin-top:14px">
                    ต้องเติมตัวแปรเพิ่ม: <b><?= e(implode(', ', array_map(fn($m) => '{{' . $m . '}}', $missing))) ?></b>
                </div>
            <?php endif; ?>

            <div class="form-row">
                <div><label>ชื่อสินค้า {{product_name}}</label><input name="product_name" value="<?= e($_POST['product_name'] ?? '') ?>"></div>
                <div><label>ราคา {{price}}</label><input name="price" value="<?= e($_POST['price'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div><label>จุดขาย {{usp}}</label><input name="usp" value="<?= e($_POST['usp'] ?? '') ?>"></div>
                <div><label>กลุ่มเป้าหมาย {{target}}</label><input name="target" value="<?= e($_POST['target'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div><label>CTA {{cta}}</label><input name="cta" value="<?= e($_POST['cta'] ?? '') ?>" placeholder="เช่น ทักแชทรับส่วนลด"></div>
                <div><label>แพลตฟอร์ม {{platform}}</label><input name="platform" value="<?= e($_POST['platform'] ?? '') ?>"></div>
            </div>
            <button class="btn btn-primary" style="margin-top:16px" <?= !$formula ? 'disabled' : '' ?>>สร้างคอนเทนต์</button>
        </form>
    </div>

    <div class="card">
        <h3>ผลลัพธ์</h3>
        <?php if ($result): ?>
            <label>Storyboard</label>
            <pre><?= e($result['storyboard']) ?></pre>
            <label>แคปชั่น</label>
            <pre><?= e($result['caption']) ?></pre>

            <form method="post" action="<?= url('formulas.php') ?>" style="margin-top:14px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_from_result">
                <input type="hidden" name="result_text" value="<?= e($result['storyboard']) ?>">
                <input type="hidden" name="category" value="<?= e($formula['category'] ?? 'วิดีโอสั้น') ?>">
                <input type="hidden" name="platforms" value="<?= e($formula['platforms'] ?? '') ?>">
                <input type="hidden" name="total_seconds" value="<?= (int)($formula['total_seconds'] ?? 10) ?>">
                <label>บันทึกผลลัพธ์นี้เป็นสูตรใหม่</label>
                <div style="display:flex;gap:8px">
                    <input name="name" placeholder="ตั้งชื่อสูตร">
                    <button class="btn btn-primary">บันทึกเป็นสูตร</button>
                </div>
            </form>

            <form method="post" action="<?= url('posts.php') ?>" style="margin-top:10px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="caption" value="<?= e($result['caption']) ?>">
                <input type="hidden" name="formula_id" value="<?= (int)$formula['id'] ?>">
                <input type="hidden" name="status" value="draft">
                <input type="hidden" name="media_type" value="video">
                <button class="btn">→ ส่งเข้าหน้าโพสต์ (ฉบับร่าง)</button>
            </form>
        <?php else: ?>
            <div class="empty">กรอกข้อมูลด้านซ้ายแล้วกด "สร้างคอนเทนต์"</div>
            <?php if ($formula): ?>
                <p class="muted">สูตรที่เลือก: <b><?= e($formula['name']) ?></b> · <?= count($scenes) ?> ซีน · <?= (int)$formula['total_seconds'] ?> วินาที</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
