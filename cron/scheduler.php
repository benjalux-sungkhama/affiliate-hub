<?php
/**
 * AffiliateHub — ตัวรันอัตโนมัติเผยแพร่โพสต์ตามคิว (Scheduler / Worker)
 *
 * วิธีรัน (CLI):
 *   php cron/scheduler.php
 *
 * ตั้งให้รันเองอัตโนมัติ (Windows): ใช้ Task Scheduler เรียก cron/run-scheduler.bat
 * ทุก ๆ 1–5 นาที — ดูขั้นตอนใน docs/AUTOMATION.md
 *
 * ตรรกะ:
 *   - หยิบโพสต์สถานะ "queued" ที่ scheduled_at <= เวลาปัจจุบัน (หรือไม่ได้ตั้งเวลา)
 *   - ตรวจโหมด automation ของเจ้าของโพสต์ (off = ข้าม)
 *   - เผยแพร่ตามโหมด (simulate / live) แล้วอัปเดตสถานะ + published_at
 *   - บันทึกผลลง cron/scheduler.log
 */

// รันได้เฉพาะทาง CLI เท่านั้น (กันเรียกผ่านเว็บ)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('สคริปต์นี้รันผ่าน command line เท่านั้น');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/publishers.php';

$logFile = __DIR__ . '/scheduler.log';
function slog(string $msg): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

$pdo = db();

// โหมด automation ต่อผู้ใช้ (เก็บใน settings key = "automation")
$modes = [];
foreach ($pdo->query("SELECT user_id, svalue FROM settings WHERE skey='automation'") as $r) {
    $modes[(int)$r['user_id']] = $r['svalue'];
}

// โพสต์ที่ถึงกำหนดเผยแพร่
$st = $pdo->query(
    "SELECT * FROM posts
     WHERE status='queued'
       AND (scheduled_at IS NULL OR scheduled_at <= NOW())
     ORDER BY scheduled_at ASC
     LIMIT 50"
);
$posts = $st->fetchAll();

if (!$posts) {
    slog('ไม่มีโพสต์ที่ถึงกำหนด');
    exit(0);
}

$updDone = $pdo->prepare("UPDATE posts SET status='published', published_at=NOW() WHERE id=? AND user_id=?");
$updFail = $pdo->prepare("UPDATE posts SET status='failed' WHERE id=? AND user_id=?");

$ok = 0;
$fail = 0;
$skip = 0;
foreach ($posts as $post) {
    $uid  = (int)$post['user_id'];
    $mode = $modes[$uid] ?? 'off';

    if ($mode === 'off') {
        $skip++;
        continue;   // ผู้ใช้ยังไม่เปิด automation — ปล่อยไว้ในคิว
    }

    [$success, $message] = publish_post($pdo, $post, $mode);

    if ($success) {
        $updDone->execute([(int)$post['id'], $uid]);
        $ok++;
        slog(sprintf('OK   #%d (user %d, %s): %s', $post['id'], $uid, $mode, $message));
    } else {
        $updFail->execute([(int)$post['id'], $uid]);
        $fail++;
        slog(sprintf('FAIL #%d (user %d, %s): %s', $post['id'], $uid, $mode, $message));
    }
}

slog(sprintf('สรุป: สำเร็จ %d · ล้มเหลว %d · ข้าม(ปิด automation) %d', $ok, $fail, $skip));
exit(0);
