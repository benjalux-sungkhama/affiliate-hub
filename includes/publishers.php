<?php
/**
 * ตัวเผยแพร่โพสต์ไปยังแพลตฟอร์ม (Publisher layer)
 *
 * โหมดการทำงานต่อผู้ใช้ (setting key = "automation"):
 *   off      — ไม่เผยแพร่อัตโนมัติ (ปล่อยให้กดเองในหน้าโพสต์)
 *   simulate — ทำเสมือนเผยแพร่สำเร็จ (ไว้ทดสอบ flow โดยไม่ต้องมี API จริง)
 *   live     — ยิง API จริงของแพลตฟอร์ม (ต้องมี Access Token ในบัญชีที่เชื่อม)
 *
 * เพิ่มแพลตฟอร์มใหม่ = เพิ่ม case ใน publish_to_platform()
 */
require_once __DIR__ . '/../config/db.php';

/**
 * เผยแพร่โพสต์ 1 รายการ
 * @return array{ok:bool,message:string}
 */
function publish_post(PDO $pdo, array $post, string $mode): array
{
    if ($mode === 'simulate') {
        return [true, 'จำลองการเผยแพร่สำเร็จ (โหมด simulate)'];
    }

    // โหมด live — ต้องมีบัญชีที่เชื่อม + token ของแพลตฟอร์มนั้น
    $st = $pdo->prepare(
        'SELECT pa.*, p.code AS platform_code
         FROM platform_accounts pa
         JOIN platforms p ON p.id = pa.platform_id
         WHERE pa.user_id=? AND pa.platform_id=? AND pa.is_connected=1
         ORDER BY pa.connected_at DESC LIMIT 1'
    );
    $st->execute([(int)$post['user_id'], (int)$post['platform_id']]);
    $account = $st->fetch();

    if (!$account) {
        return [false, 'ยังไม่ได้เชื่อมบัญชีของแพลตฟอร์มนี้'];
    }
    if (empty($account['access_token'])) {
        return [false, 'บัญชีที่เชื่อมยังไม่มี Access Token (ไปใส่ที่หน้าแพลตฟอร์ม)'];
    }

    return publish_to_platform($account['platform_code'], $account, $post);
}

/** เลือก publisher ตาม code ของแพลตฟอร์ม */
function publish_to_platform(string $code, array $account, array $post): array
{
    switch ($code) {
        case 'facebook':
            return publish_facebook($account, $post);
        case 'tiktok':
        case 'shopee':
        case 'lazada':
            // โครงพร้อมต่อยอด — แต่ละเจ้าต้องมี Developer App + สิทธิ์ที่อนุมัติแล้ว
            return [false, 'ยังไม่ได้ตั้งค่า API ของ ' . $code . ' (ต้องมี Developer App + token ที่อนุมัติสิทธิ์โพสต์)'];
        default:
            return [false, 'ไม่รู้จักแพลตฟอร์ม: ' . $code];
    }
}

/**
 * เผยแพร่ขึ้น Facebook Page ผ่าน Graph API
 * ต้องการ: external_id = Page ID, access_token = Page Access Token (สิทธิ์ pages_manage_posts)
 */
function publish_facebook(array $account, array $post): array
{
    $pageId = $account['external_id'] ?? '';
    $token  = $account['access_token'] ?? '';
    if ($pageId === '') {
        return [false, 'บัญชี Facebook ยังไม่ได้ระบุ Page ID (external_id)'];
    }

    $graphVersion = 'v19.0';
    $url = "https://graph.facebook.com/{$graphVersion}/{$pageId}/feed";
    $fields = [
        'message'      => (string)($post['caption'] ?? ''),
        'access_token' => $token,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [false, 'เรียก Facebook API ไม่สำเร็จ: ' . $err];
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['id'])) {
        return [true, 'เผยแพร่ขึ้น Facebook แล้ว (post id: ' . $data['id'] . ')'];
    }
    $reason = $data['error']['message'] ?? ('HTTP ' . $httpCode);
    return [false, 'Facebook ปฏิเสธ: ' . $reason];
}
