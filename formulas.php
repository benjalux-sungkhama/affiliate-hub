<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = uid();
$pdo = db();

/** โหลดสูตร + ซีน (filter user_id) */
function load_formula(PDO $pdo, int $id, int $u): ?array
{
    $st = $pdo->prepare('SELECT * FROM content_formulas WHERE id=? AND user_id=?');
    $st->execute([$id, $u]);
    $f = $st->fetch();
    if (!$f) {
        return null;
    }
    $sc = $pdo->prepare('SELECT * FROM content_formula_scenes WHERE formula_id=? ORDER BY seq');
    $sc->execute([$id]);
    $f['scenes'] = $sc->fetchAll();
    return $f;
}

/** บันทึกซีนของสูตร */
function save_scenes(PDO $pdo, int $formulaId): void
{
    $pdo->prepare('DELETE FROM content_formula_scenes WHERE formula_id=?')->execute([$formulaId]);
    $seq = $_POST['seq'] ?? [];
    $ins = $pdo->prepare(
        'INSERT INTO content_formula_scenes
         (formula_id,seq,time_from,time_to,description,camera_angle,lighting,overlay_text)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($seq as $i => $s) {
        if (trim(($_POST['description'][$i] ?? '')) === '') {
            continue;
        }
        $ins->execute([
            $formulaId, (int)$s,
            (float)($_POST['time_from'][$i] ?? 0), (float)($_POST['time_to'][$i] ?? 0),
            trim($_POST['description'][$i] ?? ''), trim($_POST['camera_angle'][$i] ?? '') ?: null,
            trim($_POST['lighting'][$i] ?? '') ?: null, trim($_POST['overlay_text'][$i] ?? '') ?: null,
        ]);
    }
}

// ----- ส่งออก JSON -----
if (isset($_GET['export'])) {
    $f = load_formula($pdo, (int)$_GET['export'], $u);
    if ($f) {
        unset($f['id'], $f['user_id'], $f['parent_id']);
        foreach ($f['scenes'] as &$s) {
            unset($s['id'], $s['formula_id']);
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="formula-' . $f['id'] . '.json"');
        echo json_encode($f, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

// ----- POST actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $fields = [
            trim($_POST['name'] ?? ''), trim($_POST['category'] ?? '') ?: null,
            trim($_POST['platforms'] ?? '') ?: null, (int)($_POST['total_seconds'] ?? 10),
            (int)($_POST['scene_count'] ?? 6), trim($_POST['overlay_style'] ?? '') ?: null,
            trim($_POST['audio_style'] ?? '') ?: null, trim($_POST['tone'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
        ];
        if ($id) {
            // แก้ไข = เพิ่มเวอร์ชัน + เก็บเวอร์ชันเดิมไว้ (parent_id) ให้ย้อนกลับได้
            $old = load_formula($pdo, $id, $u);
            if ($old) {
                $pdo->beginTransaction();
                try {
                    // สำเนาเวอร์ชันเดิมเก็บเป็น archive (is_active=0, parent_id=id)
                    $arch = $pdo->prepare(
                        'INSERT INTO content_formulas
                         (user_id,name,category,platforms,total_seconds,scene_count,overlay_style,
                          audio_style,tone,notes,variables_json,is_default,is_active,version,parent_id)
                         SELECT user_id,name,category,platforms,total_seconds,scene_count,overlay_style,
                          audio_style,tone,notes,variables_json,0,0,version,?
                         FROM content_formulas WHERE id=? AND user_id=?'
                    );
                    $arch->execute([$id, $id, $u]);
                    $archId = (int)$pdo->lastInsertId();
                    // ก๊อปซีนเดิมไป archive
                    $cs = $pdo->prepare(
                        'INSERT INTO content_formula_scenes
                          (formula_id,seq,time_from,time_to,description,camera_angle,lighting,overlay_text)
                         SELECT ?,seq,time_from,time_to,description,camera_angle,lighting,overlay_text
                         FROM content_formula_scenes WHERE formula_id=?'
                    );
                    $cs->execute([$archId, $id]);
                    // อัปเดตตัวจริง + bump version
                    $up = $pdo->prepare(
                        'UPDATE content_formulas SET name=?,category=?,platforms=?,total_seconds=?,
                         scene_count=?,overlay_style=?,audio_style=?,tone=?,notes=?,version=version+1
                         WHERE id=? AND user_id=?'
                    );
                    $up->execute(array_merge($fields, [$id, $u]));
                    save_scenes($pdo, $id);
                    $pdo->commit();
                    flash('บันทึกสูตร (เก็บเวอร์ชันเดิมไว้ย้อนกลับได้)');
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    flash('บันทึกไม่สำเร็จ: ' . $ex->getMessage(), 'err');
                }
            }
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO content_formulas
                 (user_id,name,category,platforms,total_seconds,scene_count,overlay_style,
                  audio_style,tone,notes,variables_json,is_active,version)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,1,1)'
            );
            $ins->execute(array_merge([$u], $fields, ['{}']));
            $id = (int)$pdo->lastInsertId();
            save_scenes($pdo, $id);
            flash('สร้างสูตรใหม่แล้ว');
        }
        redirect('formulas.php');
    }

    if ($action === 'save_from_result') {
        // "บันทึกเป็นสูตร" จากผลลัพธ์ AI — แปลงเป็นโครงซีนอัตโนมัติ
        $name = trim($_POST['name'] ?? '') ?: 'สูตรจากผลลัพธ์ ' . date('d/m H:i');
        $ins = $pdo->prepare(
            'INSERT INTO content_formulas
             (user_id,name,category,platforms,total_seconds,scene_count,notes,variables_json,is_active,version)
             VALUES (?,?,?,?,?,?,?,?,1,1)'
        );
        $ins->execute([
            $u, $name, trim($_POST['category'] ?? '') ?: 'วิดีโอสั้น',
            trim($_POST['platforms'] ?? '') ?: null, (int)($_POST['total_seconds'] ?? 10),
            0, trim($_POST['notes'] ?? ''), '{}',
        ]);
        $id = (int)$pdo->lastInsertId();
        // แยกผลลัพธ์เป็นซีนจากบรรทัด
        $lines = preg_split('/\r?\n/', trim($_POST['result_text'] ?? ''));
        $seq = 1;
        $insS = $pdo->prepare(
            'INSERT INTO content_formula_scenes (formula_id,seq,time_from,time_to,description)
             VALUES (?,?,?,?,?)'
        );
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '') {
                continue;
            }
            $insS->execute([$id, $seq, ($seq - 1) * 1.5, $seq * 1.5, $ln]);
            $seq++;
        }
        $pdo->prepare('UPDATE content_formulas SET scene_count=? WHERE id=?')->execute([$seq - 1, $id]);
        flash('บันทึกผลลัพธ์เป็นสูตรแล้ว');
        redirect('formulas.php');
    }

    if ($action === 'duplicate') {
        $src = load_formula($pdo, (int)$_POST['id'], $u);
        if ($src) {
            $ins = $pdo->prepare(
                'INSERT INTO content_formulas
                 (user_id,name,category,platforms,total_seconds,scene_count,overlay_style,
                  audio_style,tone,notes,variables_json,is_active,version)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,1,1)'
            );
            $ins->execute([
                $u, $src['name'] . ' (สำเนา)', $src['category'], $src['platforms'],
                $src['total_seconds'], $src['scene_count'], $src['overlay_style'],
                $src['audio_style'], $src['tone'], $src['notes'], $src['variables_json'],
            ]);
            $newId = (int)$pdo->lastInsertId();
            $cs = $pdo->prepare(
                'INSERT INTO content_formula_scenes
                  (formula_id,seq,time_from,time_to,description,camera_angle,lighting,overlay_text)
                 SELECT ?,seq,time_from,time_to,description,camera_angle,lighting,overlay_text
                 FROM content_formula_scenes WHERE formula_id=?'
            );
            $cs->execute([$newId, $src['id']]);
            flash('ทำสำเนาสูตรแล้ว');
        }
        redirect('formulas.php');
    }

    if ($action === 'set_default') {
        // หมวดละ 1 สูตร default เท่านั้น
        $f = load_formula($pdo, (int)$_POST['id'], $u);
        if ($f) {
            $pdo->prepare('UPDATE content_formulas SET is_default=0 WHERE user_id=? AND category<=>?')
                ->execute([$u, $f['category']]);
            $pdo->prepare('UPDATE content_formulas SET is_default=1 WHERE id=? AND user_id=?')
                ->execute([$f['id'], $u]);
            flash('ตั้งเป็นค่าเริ่มต้นของหมวดแล้ว');
        }
        redirect('formulas.php');
    }

    if ($action === 'toggle') {
        $pdo->prepare('UPDATE content_formulas SET is_active=1-is_active WHERE id=? AND user_id=?')
            ->execute([(int)$_POST['id'], $u]);
        redirect('formulas.php');
    }

    if ($action === 'delete') {
        $fid = (int)$_POST['id'];
        // ลบไม่ได้ถ้ามีโพสต์สถานะ "เข้าคิว" อ้างอิงอยู่
        $chk = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE formula_id=? AND user_id=? AND status='queued'");
        $chk->execute([$fid, $u]);
        if ($chk->fetchColumn() > 0) {
            flash('ลบไม่ได้ — มีโพสต์สถานะ "เข้าคิว" อ้างอิงสูตรนี้อยู่', 'err');
        } else {
            $pdo->prepare('DELETE FROM content_formulas WHERE id=? AND user_id=?')->execute([$fid, $u]);
            flash('ลบสูตรแล้ว');
        }
        redirect('formulas.php');
    }

    if ($action === 'import') {
        $raw = $_POST['json'] ?? '';
        if (($_FILES['file']['tmp_name'] ?? '') && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $raw = file_get_contents($_FILES['file']['tmp_name']);
        }
        $data = json_decode($raw, true);
        if (is_array($data) && !empty($data['name'])) {
            $ins = $pdo->prepare(
                'INSERT INTO content_formulas
                 (user_id,name,category,platforms,total_seconds,scene_count,overlay_style,
                  audio_style,tone,notes,variables_json,is_active,version)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,1,1)'
            );
            $ins->execute([
                $u, $data['name'], $data['category'] ?? null, $data['platforms'] ?? null,
                (int)($data['total_seconds'] ?? 10), (int)($data['scene_count'] ?? 0),
                $data['overlay_style'] ?? null, $data['audio_style'] ?? null,
                $data['tone'] ?? null, $data['notes'] ?? null,
                json_encode($data['variables_json'] ?? new stdClass(), JSON_UNESCAPED_UNICODE),
            ]);
            $newId = (int)$pdo->lastInsertId();
            $insS = $pdo->prepare(
                'INSERT INTO content_formula_scenes
                  (formula_id,seq,time_from,time_to,description,camera_angle,lighting,overlay_text)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            foreach (($data['scenes'] ?? []) as $s) {
                $insS->execute([
                    $newId, (int)($s['seq'] ?? 1), (float)($s['time_from'] ?? 0),
                    (float)($s['time_to'] ?? 0), $s['description'] ?? '', $s['camera_angle'] ?? null,
                    $s['lighting'] ?? null, $s['overlay_text'] ?? null,
                ]);
            }
            flash('นำเข้าสูตรจาก JSON แล้ว');
        } else {
            flash('ไฟล์/ข้อความ JSON ไม่ถูกต้อง', 'err');
        }
        redirect('formulas.php');
    }
}

// ----- โหลดข้อมูลสำหรับแสดงผล -----
$edit = null;
if (isset($_GET['edit'])) {
    $edit = load_formula($pdo, (int)$_GET['edit'], $u);
}

$search = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['cat'] ?? '');
$sql = 'SELECT f.*,
          (SELECT COUNT(*) FROM content_formula_usages x WHERE x.formula_id=f.id) AS uses,
          (SELECT AVG(ctr) FROM content_formula_usages x WHERE x.formula_id=f.id) AS avg_ctr,
          (SELECT COALESCE(SUM(linked_sales),0) FROM content_formula_usages x WHERE x.formula_id=f.id) AS sales,
          (SELECT MAX(used_at) FROM content_formula_usages x WHERE x.formula_id=f.id) AS last_used
        FROM content_formulas f
        WHERE f.user_id=? AND f.parent_id IS NULL';
$params = [$u];
if ($search !== '') {
    $sql .= ' AND f.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($catFilter !== '') {
    $sql .= ' AND f.category=?';
    $params[] = $catFilter;
}
$sql .= ' ORDER BY f.is_default DESC, f.updated_at DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$formulas = $st->fetchAll();

$catsStmt = $pdo->prepare('SELECT DISTINCT category FROM content_formulas WHERE user_id=? AND category IS NOT NULL ORDER BY category');
$catsStmt->execute([$u]);
$allCats = $catsStmt->fetchAll(PDO::FETCH_COLUMN);

$__title = 'คลังสูตรของฉัน';
$__active = 'formulas';
include __DIR__ . '/includes/header.php';
?>
<div class="tabs">
    <a class="tab active" href="<?= url('formulas.php') ?>">📚 คลังสูตรของฉัน</a>
    <a class="tab" href="<?= url('ai-content.php') ?>">🤖 ให้ AI คิดคอนเทนต์</a>
</div>

<?php if ($edit !== null || isset($_GET['new'])): ?>
    <!-- ฟอร์มสร้าง/แก้ไขสูตร -->
    <div class="card">
        <div class="section-head">
            <h3><?= $edit ? 'แก้ไขสูตร (เวอร์ชัน ' . (int)$edit['version'] . ')' : '+ สูตรใหม่' ?></h3>
            <a class="pill" href="<?= url('formulas.php') ?>">← กลับคลังสูตร</a>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="form-row">
                <div><label>ชื่อสูตร</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
                <div><label>หมวด</label><input name="category" list="catlist" value="<?= e($edit['category'] ?? 'วิดีโอสั้น') ?>">
                    <datalist id="catlist"><?php foreach ($allCats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                </div>
            </div>
            <div class="form-row">
                <div><label>แพลตฟอร์ม (คั่นด้วยจุลภาค)</label><input name="platforms" value="<?= e($edit['platforms'] ?? 'tiktok,facebook') ?>"></div>
                <div><label>ความยาวรวม (วินาที)</label><input type="number" name="total_seconds" value="<?= (int)($edit['total_seconds'] ?? 10) ?>"></div>
            </div>
            <div class="form-row">
                <div><label>สไตล์ Text Overlay</label><input name="overlay_style" value="<?= e($edit['overlay_style'] ?? '') ?>"></div>
                <div><label>สไตล์เสียง</label><input name="audio_style" value="<?= e($edit['audio_style'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div><label>โทน</label><input name="tone" value="<?= e($edit['tone'] ?? '') ?>"></div>
                <div><label>จำนวนซีน</label><input type="number" name="scene_count" value="<?= (int)($edit['scene_count'] ?? 6) ?>"></div>
            </div>
            <label>บันทึกช่วยจำ</label>
            <textarea name="notes"><?= e($edit['notes'] ?? '') ?></textarea>

            <h3 style="margin-top:20px">ซีน (เพิ่ม/ลบได้)</h3>
            <div class="scene-row" style="font-weight:600;color:var(--muted)">
                <div>ลำดับ</div><div>เริ่ม</div><div>จบ</div><div>เนื้อหา</div><div>มุมกล้อง</div><div>ข้อความบนจอ</div>
            </div>
            <div id="scenes">
                <?php
                $scenes = $edit['scenes'] ?? [
                    ['seq' => 1, 'time_from' => 0, 'time_to' => 1.5, 'description' => '', 'camera_angle' => '', 'overlay_text' => ''],
                ];
                foreach ($scenes as $s): ?>
                    <div class="scene-row">
                        <input name="seq[]" value="<?= (int)$s['seq'] ?>">
                        <input name="time_from[]" value="<?= e($s['time_from']) ?>">
                        <input name="time_to[]" value="<?= e($s['time_to']) ?>">
                        <input name="description[]" value="<?= e($s['description']) ?>" placeholder="เนื้อหาซีน">
                        <input name="camera_angle[]" value="<?= e($s['camera_angle'] ?? '') ?>" placeholder="มุมกล้อง">
                        <input name="overlay_text[]" value="<?= e($s['overlay_text'] ?? '') ?>" placeholder="ข้อความบนจอ">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm" onclick="AFH.addScene('scenes')">+ เพิ่มซีน</button>
            <div style="margin-top:16px"><button class="btn btn-primary">บันทึกสูตร</button></div>
        </form>
    </div>
<?php else: ?>
    <!-- รายการสูตร -->
    <div class="section-head">
        <form style="display:flex;gap:8px;flex:1;flex-wrap:wrap" method="get">
            <input name="q" placeholder="ค้นหาชื่อสูตร" value="<?= e($search) ?>" style="max-width:220px">
            <select name="cat" style="max-width:180px" onchange="this.form.submit()">
                <option value="">ทุกหมวด</option>
                <?php foreach ($allCats as $c): ?>
                    <option value="<?= e($c) ?>" <?= $catFilter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn">ค้นหา</button>
        </form>
        <a class="btn btn-primary" href="<?= url('formulas.php?new=1') ?>">+ สูตรใหม่</a>
        <button class="btn" onclick="document.getElementById('importBox').hidden=!document.getElementById('importBox').hidden">นำเข้า JSON</button>
    </div>

    <div class="card" id="importBox" hidden style="margin-bottom:16px">
        <h3>นำเข้าสูตรจาก JSON</h3>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import">
            <label>อัปโหลดไฟล์ .json</label>
            <input type="file" name="file" accept="application/json">
            <label>หรือวางข้อความ JSON</label>
            <textarea name="json" placeholder='{"name":"...","scenes":[...]}'></textarea>
            <button class="btn btn-primary" style="margin-top:12px">นำเข้า</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>ชื่อสูตร</th><th>หมวด</th><th>แพลตฟอร์ม</th><th>ใช้ไป</th>
                <th>CTR เฉลี่ย</th><th>ยอดขายผูก</th><th>ใช้ล่าสุด</th><th>สถานะ</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($formulas as $f): ?>
                <tr>
                    <td class="wrap">
                        <?= e($f['name']) ?>
                        <?php if ($f['is_default']): ?> <span class="badge badge-purple">ค่าเริ่มต้น</span><?php endif; ?>
                        <div class="muted" style="font-size:12px">v<?= (int)$f['version'] ?> · <?= (int)$f['scene_count'] ?> ซีน · <?= (int)$f['total_seconds'] ?> วิ</div>
                    </td>
                    <td><?= e($f['category'] ?: '—') ?></td>
                    <td><?= e($f['platforms'] ?: '—') ?></td>
                    <td><?= (int)$f['uses'] ?></td>
                    <td><?= $f['avg_ctr'] !== null ? number_format($f['avg_ctr'], 2) . '%' : '—' ?></td>
                    <td>฿<?= money($f['sales']) ?></td>
                    <td><?= $f['last_used'] ? e(substr($f['last_used'], 0, 10)) : '—' ?></td>
                    <td><?= $f['is_active'] ? '<span class="badge badge-green">ใช้งาน</span>' : '<span class="badge badge-gray">ปิด</span>' ?></td>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-sm btn-primary" href="<?= url('ai-content.php?formula=' . (int)$f['id']) ?>">ใช้สูตรนี้</a>
                            <a class="btn btn-sm" href="<?= url('formulas.php?edit=' . (int)$f['id']) ?>">แก้ไข</a>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm">ทำสำเนา</button></form>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="set_default"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm">ตั้งค่าเริ่มต้น</button></form>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm"><?= $f['is_active'] ? 'ปิด' : 'เปิด' ?></button></form>
                            <a class="btn btn-sm" href="<?= url('formulas.php?export=' . (int)$f['id']) ?>">ส่งออก JSON</a>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="ยืนยันการลบสูตร '<?= e($f['name']) ?>' ? (ยืนยันอีกครั้งเพื่อความปลอดภัย)">ลบ</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$formulas): ?><tr><td colspan="9" class="empty">ยังไม่มีสูตร — กด "+ สูตรใหม่" หรือใช้สูตร seed เริ่มต้น</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
