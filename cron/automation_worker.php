<?php
/**
 * AffiliateHub — Automation Worker (§10)
 * รันทุก 5 นาทีผ่าน Task Scheduler (Windows) / crontab (mac/Linux)
 *   php cron/automation_worker.php
 *
 * รับผิดชอบ: ทริกเกอร์แบบเวลา (schedule.*) — ทริกเกอร์เหตุการณ์ (product.* ฯลฯ)
 * ยิงทันทีจากหน้าเว็บผ่าน automation_dispatch_event()
 *
 * กติกา: ล็อกกันรันซ้อน · สูงสุด 10 งาน/รอบ · ทุก run บันทึกเสมอ · error แล้วไปต่อ
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/automation.php';

$logFile = __DIR__ . '/automation_worker.log';
function wlog(string $m): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

// ---- ล็อกกันรันซ้อน ----
$lock = fopen(__DIR__ . '/automation.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    wlog('รอบก่อนยังทำงานอยู่ — ข้ามรอบนี้');
    exit(0);
}

$pdo = db();
$MAX_JOBS = 10;
$done = 0;

/** ตรวจว่ากฎแบบเวลาถึงกำหนดยิงหรือยัง */
function schedule_due(array $rule): bool
{
    $cfg = json_decode($rule['trigger_config'] ?? '{}', true) ?: [];
    $last = $rule['last_run_at'] ? strtotime($rule['last_run_at']) : 0;
    $now = time();

    switch ($rule['trigger_type']) {
        case 'schedule.daily':
            $t = $cfg['time'] ?? '10:00';
            $target = strtotime(date('Y-m-d ') . $t);
            return $now >= $target && $last < $target;

        case 'schedule.weekly':
            $dow = (int)($cfg['day_of_week'] ?? 1);
            $t = $cfg['time'] ?? '10:00';
            if ((int)date('w') !== $dow) { return false; }
            $target = strtotime(date('Y-m-d ') . $t);
            return $now >= $target && $last < $target;

        case 'schedule.interval':
            $hours = max(1, (int)($cfg['hours'] ?? 6));
            return $last === 0 || ($now - $last) >= $hours * 3600;

        default:
            return false;   // schedule.before_live จัดการแยกด้านล่าง
    }
}

/** เลือกสินค้าสุ่ม 1 ชิ้นเป็น context ให้กฎแบบเวลา (ถ้าสูตรต้องใช้ตัวแปรสินค้า) */
function pick_product(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare(
        'SELECT p.*, c.name AS category FROM products p
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE p.user_id=? AND p.is_active=1 AND p.stock>0
         ORDER BY RAND() LIMIT 1'
    );
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

try {
    // ---- ทริกเกอร์แบบเวลา ----
    $rules = $pdo->query(
        "SELECT * FROM automation_rules WHERE is_active=1 AND trigger_type LIKE 'schedule.%' ORDER BY id"
    )->fetchAll();

    foreach ($rules as $rule) {
        if ($done >= $MAX_JOBS) { break; }
        if ($rule['trigger_type'] === 'schedule.before_live') {
            continue;   // จัดการในบล็อกถัดไป
        }
        if (!schedule_due($rule)) { continue; }

        $ctx = ['time.hour' => (int)date('G'), 'time.dow' => (int)date('w')];
        $prod = pick_product($pdo, (int)$rule['user_id']);
        if ($prod) { $ctx = array_merge($ctx, automation_product_context($prod)); }

        try {
            $r = automation_run_rule($pdo, $rule, $ctx, false);
            wlog(sprintf('rule #%d (%s): %s — %s', $rule['id'], $rule['trigger_type'], $r['status'], $r['reason']));
        } catch (Throwable $e) {
            wlog(sprintf('rule #%d ERROR: %s', $rule['id'], $e->getMessage()));
        }
        $done++;
    }

    // ---- schedule.before_live: ไลฟ์ที่จะเริ่มในอีก N ชม. (หน้าต่าง 5 นาที) ----
    $blRules = $pdo->query(
        "SELECT * FROM automation_rules WHERE is_active=1 AND trigger_type='schedule.before_live' ORDER BY id"
    )->fetchAll();
    foreach ($blRules as $rule) {
        if ($done >= $MAX_JOBS) { break; }
        $cfg = json_decode($rule['trigger_config'] ?? '{}', true) ?: [];
        $hours = max(0, (int)($cfg['hours_before'] ?? 3));
        $lv = $pdo->prepare(
            "SELECT * FROM live_sessions
             WHERE user_id=? AND started_at IS NOT NULL
               AND started_at BETWEEN DATE_ADD(NOW(), INTERVAL ? HOUR)
                                  AND DATE_ADD(NOW(), INTERVAL ? HOUR) + INTERVAL 5 MINUTE"
        );
        $lv->execute([(int)$rule['user_id'], $hours, $hours]);
        foreach ($lv->fetchAll() as $live) {
            if ($done >= $MAX_JOBS) { break; }
            $ctx = ['time.hour' => (int)date('G'), 'live.id' => (int)$live['id'], 'live.title' => $live['title']];
            try {
                $r = automation_run_rule($pdo, $rule, $ctx, false);
                wlog(sprintf('rule #%d (before_live) live #%d: %s', $rule['id'], $live['id'], $r['status']));
            } catch (Throwable $e) {
                wlog(sprintf('rule #%d before_live ERROR: %s', $rule['id'], $e->getMessage()));
            }
            $done++;
        }
    }

    wlog("จบรอบ: ประมวลผล $done งาน");
} catch (Throwable $e) {
    wlog('WORKER ERROR: ' . $e->getMessage());
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
exit(0);
