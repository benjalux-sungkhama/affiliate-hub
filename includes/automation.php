<?php
/**
 * AffiliateHub — เอนจินระบบโพสต์อัตโนมัติ (Automation Rules)
 * อ้างอิงสเปก docs/automation-rules.md §1–§12
 *
 * สายพาน: ทริกเกอร์ → เงื่อนไข → AI สร้างจากสูตร → เข้าคิว/รออนุมัติ → โพสต์
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/** ทริกเกอร์ที่รองรับ (§3) */
function automation_triggers(): array
{
    return [
        'schedule.daily'        => 'ทุกวันเวลาที่กำหนด',
        'schedule.weekly'       => 'รายสัปดาห์ (เลือกวัน)',
        'schedule.interval'     => 'ทุก N ชั่วโมง',
        'schedule.before_live'  => 'ก่อนไลฟ์ N ชั่วโมง',
        'product.created'       => 'เพิ่มสินค้าใหม่',
        'product.price_changed' => 'ราคาสินค้าเปลี่ยน',
        'product.low_stock'     => 'สต๊อกต่ำกว่าเกณฑ์',
        'product.restocked'     => 'เติมสต๊อกกลับมา',
        'post.high_performing'  => 'โพสต์เก่าทะลุเกณฑ์',
        'order.milestone'       => 'ยอดขายถึงหมุดหมาย',
        'live.ended'            => 'ไลฟ์จบ',
        'formula.updated'       => 'สูตรถูกแก้',
    ];
}

/** Guardrails ค่าเริ่มต้น (§7) */
function automation_default_guardrails(): array
{
    return [
        'max_auto_posts_per_day' => 8,
        'min_gap_minutes'        => 90,
        'dup_similarity'         => 85,
        'product_cooldown_days'  => 7,
        'max_ai_calls_per_day'   => 30,
        'fail_streak_disable'    => 3,
        'quiet_from'             => '23:00',
        'quiet_to'               => '07:00',
    ];
}

/** โหลด settings + guardrails ของผู้ใช้ (สร้างค่าเริ่มต้นถ้ายังไม่มี) */
function automation_settings(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT * FROM automation_settings WHERE user_id=?');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        $g = json_encode(automation_default_guardrails(), JSON_UNESCAPED_UNICODE);
        $ins = $pdo->prepare('INSERT INTO automation_settings (user_id,guardrails,kill_switch) VALUES (?,?,0)');
        $ins->execute([$userId, $g]);
        return ['user_id' => $userId, 'guardrails' => automation_default_guardrails(), 'kill_switch' => 0];
    }
    $g = json_decode($row['guardrails'] ?? '', true);
    $row['guardrails'] = array_merge(automation_default_guardrails(), is_array($g) ? $g : []);
    return $row;
}

/** ประเมินเงื่อนไขทั้งหมด (AND) — §4 */
function automation_eval_conditions(array $conditions, array $ctx): array
{
    foreach ($conditions as $c) {
        $field = $c['field'] ?? '';
        $op    = $c['op'] ?? '=';
        $val   = $c['value'] ?? null;
        $have  = $ctx[$field] ?? null;
        if (!automation_cmp($have, $op, $val)) {
            return [false, "เงื่อนไขไม่ผ่าน: {$field} {$op} " . (is_array($val) ? implode(',', $val) : $val)];
        }
    }
    return [true, ''];
}

function automation_cmp($have, string $op, $val): bool
{
    switch ($op) {
        case '=':  return $have == $val;
        case '!=': return $have != $val;
        case '>':  return (float)$have >  (float)$val;
        case '>=': return (float)$have >= (float)$val;
        case '<':  return (float)$have <  (float)$val;
        case '<=': return (float)$have <= (float)$val;
        case 'between':
            return is_array($val) && count($val) === 2
                && (float)$have >= (float)$val[0] && (float)$have <= (float)$val[1];
        case 'in':      return is_array($val) && in_array($have, $val);
        case 'not_in':  return is_array($val) && !in_array($have, $val);
        case 'contains': return $have !== null && stripos((string)$have, (string)$val) !== false;
        default: return false;
    }
}

/** สร้างคอนเทนต์จากสูตร + ตัวแปร (คืน [ok, caption, storyboard, missing[]]) */
function automation_generate(PDO $pdo, array $formula, array $vars): array
{
    $sc = $pdo->prepare('SELECT * FROM content_formula_scenes WHERE formula_id=? ORDER BY seq');
    $sc->execute([$formula['id']]);
    $scenes = $sc->fetchAll();

    $allText = $formula['name'] . ' ' . $formula['notes'];
    foreach ($scenes as $s) {
        $allText .= ' ' . $s['description'] . ' ' . $s['overlay_text'];
    }
    $missing = missing_variables($allText, $vars);
    if ($missing) {
        return [false, '', '', $missing];
    }

    $lines = ['🎬 ' . fill_variables($formula['name'], $vars)
        . ' (' . (int)$formula['total_seconds'] . ' วินาที · ' . count($scenes) . ' ซีน)', ''];
    foreach ($scenes as $s) {
        $lines[] = sprintf('ซีน %d [%.1f–%.1f วิ] %s', $s['seq'], $s['time_from'], $s['time_to'],
            fill_variables($s['description'] ?? '', $vars));
        $ov = fill_variables($s['overlay_text'] ?? '', $vars);
        if ($ov) { $lines[] = '   🅰️ ' . $ov; }
    }
    $storyboard = implode("\n", $lines);
    $caption = sprintf("%s ✨\n%s\n📌 %s | ราคา %s บาท\n👉 %s",
        $vars['product_name'] ?? 'สินค้าใหม่', $vars['usp'] ?? '',
        $vars['target'] ?? 'ทุกคน', $vars['price'] ?? '-', $vars['cta'] ?? 'ทักแชทสั่งเลย!');

    return [true, $caption, $storyboard, []];
}

/** บันทึก run 1 แถว + อัปเดตตัวนับของกฎ */
function automation_log_run(PDO $pdo, array $rule, string $status, string $reason, array $detail = []): int
{
    $ins = $pdo->prepare(
        'INSERT INTO automation_runs (user_id,rule_id,status,reason,detail) VALUES (?,?,?,?,?)'
    );
    $ins->execute([(int)$rule['user_id'], (int)$rule['id'], $status, $reason,
        json_encode($detail, JSON_UNESCAPED_UNICODE)]);
    $runId = (int)$pdo->lastInsertId();

    if ($status === 'dry') {
        return $runId;   // dry run ไม่กระทบตัวนับ
    }
    $col = $status === 'success' ? 'success_count' : ($status === 'skip' ? 'skip_count' : 'fail_count');
    if ($status === 'failed') {
        $pdo->prepare("UPDATE automation_rules SET $col=$col+1, fail_streak=fail_streak+1, last_run_at=NOW() WHERE id=?")
            ->execute([(int)$rule['id']]);
        // ล้มเหลวติดกันถึงเพดาน → ปิดกฎ (§7)
        $g = automation_settings($pdo, (int)$rule['user_id'])['guardrails'];
        $st = $pdo->prepare('SELECT fail_streak FROM automation_rules WHERE id=?');
        $st->execute([(int)$rule['id']]);
        if ((int)$st->fetchColumn() >= (int)$g['fail_streak_disable']) {
            $pdo->prepare('UPDATE automation_rules SET is_active=0 WHERE id=?')->execute([(int)$rule['id']]);
        }
    } else {
        $reset = $status === 'success' ? ', fail_streak=0' : '';
        $pdo->prepare("UPDATE automation_rules SET $col=$col+1$reset, last_run_at=NOW() WHERE id=?")
            ->execute([(int)$rule['id']]);
    }
    return $runId;
}

/** ตรวจ Guardrails ก่อนโพสต์อัตโนมัติ (auto) — คืน [ok, reason] */
function automation_check_guardrails(PDO $pdo, int $userId, array $g): array
{
    // ช่วงเวลาห้ามโพสต์ (§7)
    $now = date('H:i');
    $from = $g['quiet_from']; $to = $g['quiet_to'];
    $inQuiet = $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
    if ($inQuiet) {
        return [false, "อยู่ในช่วงเวลาห้ามโพสต์ ($from–$to)"];
    }
    // เพดานโพสต์อัตโนมัติต่อวัน (นับ post ที่มาจาก automation วันนี้)
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM automation_runs
         WHERE user_id=? AND status='success' AND DATE(created_at)=CURDATE()
           AND detail LIKE '%\"posted\":true%'"
    );
    $st->execute([$userId]);
    if ((int)$st->fetchColumn() >= (int)$g['max_auto_posts_per_day']) {
        return [false, 'ถึงเพดานโพสต์อัตโนมัติต่อวันแล้ว (' . (int)$g['max_auto_posts_per_day'] . ')'];
    }
    return [true, ''];
}

/**
 * รันกฎ 1 กฎกับ context ที่ให้มา
 * @return array{status:string,reason:string,preview:array}
 */
function automation_run_rule(PDO $pdo, array $rule, array $ctx, bool $dry = false): array
{
    $userId = (int)$rule['user_id'];
    $set = automation_settings($pdo, $userId);

    // Kill Switch (§7) — ยกเว้น dry run ให้ทดสอบได้
    if (!$dry && (int)$set['kill_switch'] === 1) {
        automation_log_run($pdo, $rule, 'skip', 'Kill Switch เปิดอยู่ หยุดทุกกฎ');
        return ['status' => 'skip', 'reason' => 'kill switch', 'preview' => []];
    }

    // เงื่อนไข (§4)
    $conds = json_decode($rule['conditions'] ?? '[]', true) ?: [];
    [$pass, $why] = automation_eval_conditions($conds, $ctx);
    if (!$pass) {
        if (!$dry) { automation_log_run($pdo, $rule, 'skip', $why); }
        return ['status' => 'skip', 'reason' => $why, 'preview' => []];
    }

    // per-rule guardrails: max_runs_per_day + cooldown ต่อสินค้า
    $rg = json_decode($rule['guardrails'] ?? '{}', true) ?: [];
    if (!$dry && !empty($rg['max_runs_per_day'])) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM automation_runs WHERE rule_id=? AND status='success' AND DATE(created_at)=CURDATE()");
        $st->execute([(int)$rule['id']]);
        if ((int)$st->fetchColumn() >= (int)$rg['max_runs_per_day']) {
            automation_log_run($pdo, $rule, 'skip', 'ถึงเพดานการยิงต่อวันของกฎนี้');
            return ['status' => 'skip', 'reason' => 'max_runs_per_day', 'preview' => []];
        }
    }
    $pid = (int)($ctx['product.id'] ?? 0);
    if (!$dry && $pid && !empty($rg['cooldown_days_per_product'])) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM automation_runs
             WHERE rule_id=? AND status='success'
               AND detail LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $st->execute([(int)$rule['id'], '%\"product_id\":' . $pid . '%', (int)$rg['cooldown_days_per_product']]);
        if ((int)$st->fetchColumn() > 0) {
            automation_log_run($pdo, $rule, 'skip', 'สินค้านี้ยังอยู่ในช่วง cooldown');
            return ['status' => 'skip', 'reason' => 'product cooldown', 'preview' => []];
        }
    }

    // โหลดสูตร
    $fs = $pdo->prepare('SELECT * FROM content_formulas WHERE id=? AND user_id=?');
    $fs->execute([(int)$rule['formula_id'], $userId]);
    $formula = $fs->fetch();
    if (!$formula) {
        if (!$dry) { automation_log_run($pdo, $rule, 'failed', 'ไม่พบสูตรที่ผูกไว้'); }
        return ['status' => 'failed', 'reason' => 'ไม่พบสูตร', 'preview' => []];
    }

    // ตัวแปรจาก context
    $vars = [
        'product_name' => $ctx['product.name'] ?? null,
        'price'        => isset($ctx['product.price']) ? (string)money($ctx['product.price']) : null,
        'usp'          => $ctx['product.usp'] ?? ($formula['tone'] ?? null),
        'target'       => $ctx['product.target'] ?? null,
        'cta'          => $ctx['cta'] ?? 'ทักแชทสั่งเลย!',
        'platform'     => $ctx['platform'] ?? null,
    ];
    $vars = array_filter($vars, fn($v) => $v !== null && $v !== '');

    [$ok, $caption, $storyboard, $missing] = automation_generate($pdo, $formula, $vars);
    if (!$ok) {
        // ตัวแปรไม่ครบ → หยุดและแจ้งเตือน ไม่โพสต์ทิ้งค่าว่าง (§5)
        $reason = 'ตัวแปรไม่ครบ: ' . implode(', ', array_map(fn($m) => '{{' . $m . '}}', $missing));
        if (!$dry) { automation_log_run($pdo, $rule, 'failed', $reason); }
        return ['status' => 'failed', 'reason' => $reason, 'preview' => []];
    }

    // ปลายทาง
    $actions = json_decode($rule['actions'] ?? '[]', true) ?: [];
    $platforms = $formula['platforms'] ?: 'tiktok';
    $delay = 0;
    foreach ($actions as $a) {
        if (($a['type'] ?? '') === 'enqueue_post') {
            if (!empty($a['config']['platforms'])) {
                $platforms = implode(',', (array)$a['config']['platforms']);
            }
            $delay = (int)($a['config']['delay_minutes'] ?? 0);
        }
    }

    $preview = ['caption' => $caption, 'storyboard' => $storyboard, 'platforms' => $platforms];
    if ($dry) {
        automation_log_run($pdo, $rule, 'dry', 'ทดลองรัน (ไม่โพสต์)', ['product_id' => $pid]);
        return ['status' => 'dry', 'reason' => '', 'preview' => $preview];
    }

    $mode = $rule['approval_mode'];

    // โหมด Draft — เก็บเป็นร่างเฉย ๆ
    if ($mode === 'draft') {
        automation_create_post($pdo, $rule, $formula, $platforms, $caption, 'draft', 0, $pid);
        automation_log_run($pdo, $rule, 'success', 'สร้างร่างแล้ว', ['product_id' => $pid, 'posted' => false]);
        return ['status' => 'success', 'reason' => 'draft', 'preview' => $preview];
    }

    // โหมด Review — เข้าคิวรออนุมัติ
    if ($mode === 'review') {
        $ins = $pdo->prepare(
            'INSERT INTO automation_approvals (user_id,rule_id,product_id,formula_id,platforms,caption,storyboard)
             VALUES (?,?,?,?,?,?,?)'
        );
        $ins->execute([$userId, (int)$rule['id'], $pid ?: null, (int)$formula['id'], $platforms, $caption, $storyboard]);
        automation_log_run($pdo, $rule, 'success', 'เข้าคิวรออนุมัติ', ['product_id' => $pid, 'posted' => false]);
        return ['status' => 'success', 'reason' => 'review', 'preview' => $preview];
    }

    // โหมด Auto — เข้าคิวโพสต์เอง (ต้องผ่าน guardrails)
    [$gok, $greason] = automation_check_guardrails($pdo, $userId, $set['guardrails']);
    if (!$gok) {
        automation_log_run($pdo, $rule, 'skip', 'Guardrail: ' . $greason);
        return ['status' => 'skip', 'reason' => $greason, 'preview' => $preview];
    }
    automation_create_post($pdo, $rule, $formula, $platforms, $caption, 'queued', $delay, $pid);
    automation_log_run($pdo, $rule, 'success', 'เข้าคิวโพสต์อัตโนมัติ', ['product_id' => $pid, 'posted' => true]);
    return ['status' => 'success', 'reason' => 'auto', 'preview' => $preview];
}

/** สร้าง posts row (1 แถวต่อแพลตฟอร์มแรก — ใช้ platform แรกในรายการ) */
function automation_create_post(PDO $pdo, array $rule, array $formula, string $platforms,
                                string $caption, string $status, int $delayMin, int $productId): void
{
    $codes = array_filter(array_map('trim', explode(',', $platforms)));
    $scheduled = $status === 'queued'
        ? date('Y-m-d H:i:s', time() + max(0, $delayMin) * 60) : null;
    foreach ($codes as $code) {
        $pf = $pdo->prepare('SELECT id FROM platforms WHERE code=?');
        $pf->execute([$code]);
        $platformId = $pf->fetchColumn() ?: null;
        $ins = $pdo->prepare(
            'INSERT INTO posts (user_id,platform_id,product_id,formula_id,title,caption,media_type,status,scheduled_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            (int)$rule['user_id'], $platformId ?: null, $productId ?: null, (int)$formula['id'],
            '[Auto] ' . $rule['name'], $caption, 'video', $status, $scheduled,
        ]);
    }
}

/**
 * ยิงทริกเกอร์แบบเหตุการณ์ — เรียกจาก products.php / orders.php / live.php
 * ประมวลผลกฎที่ตรงทริกเกอร์ทันที (ภายใน request เดียวกัน)
 */
function automation_dispatch_event(PDO $pdo, int $userId, string $eventType, array $ctx): void
{
    try {
        $st = $pdo->prepare(
            'SELECT * FROM automation_rules WHERE user_id=? AND trigger_type=? AND is_active=1'
        );
        $st->execute([$userId, $eventType]);
        foreach ($st->fetchAll() as $rule) {
            automation_run_rule($pdo, $rule, $ctx, false);
        }
    } catch (Throwable $e) {
        // ห้ามให้ automation ทำให้งานหลัก (เพิ่มสินค้า/ออเดอร์) พัง
        error_log('automation_dispatch_event: ' . $e->getMessage());
    }
}

/** ดึงสินค้า 1 ชิ้น (พร้อมชื่อหมวด) เพื่อสร้าง context */
function automation_fetch_product(PDO $pdo, int $productId, int $userId): array
{
    $st = $pdo->prepare(
        'SELECT p.*, c.name AS category FROM products p
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE p.id=? AND p.user_id=?'
    );
    $st->execute([$productId, $userId]);
    return $st->fetch() ?: [];
}

/** สร้าง context จากสินค้า (ช่วยฝั่ง event) */
function automation_product_context(array $p): array
{
    return [
        'product.id'          => (int)$p['id'],
        'product.name'        => $p['name'],
        'product.category'    => $p['category'] ?? null,
        'product.category_id' => $p['category_id'] ?? null,
        'product.price'       => (float)$p['price'],
        'product.cost'        => (float)$p['cost'],
        'product.profit'      => (float)$p['price'] - (float)$p['cost'],
        'product.stock'       => (int)$p['stock'],
        'product.image_count' => !empty($p['image_url']) ? 1 : 0,
        'time.hour'           => (int)date('G'),
        'time.dow'            => (int)date('w'),
    ];
}
