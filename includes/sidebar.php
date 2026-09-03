<?php
/** เมนูด้านข้าง — ใช้ $__active เพื่อไฮไลต์เมนูปัจจุบัน */
$nav = [
    'ภาพรวม' => [
        ['dashboard', 'dashboard.php', '📊 แดชบอร์ด'],
    ],
    'แพลตฟอร์ม' => [
        ['platforms', 'platforms.php', '🔗 แพลตฟอร์ม & บัญชี'],
        ['posting', 'settings-posting.php', '🕒 ตั้งค่าการโพสต์ & ช่วงเวลา'],
        ['ai', 'ai-content.php', '🤖 ให้ AI คิดคอนเทนต์'],
        ['formulas', 'formulas.php', '📚 คลังสูตรของฉัน'],
        ['posts', 'posts.php', '📝 สร้าง & จัดคิวโพสต์'],
        ['analytics', 'analytics.php', '📈 วิเคราะห์ & แนะนำ Boost'],
    ],
    'การจัดการ' => [
        ['live', 'live.php', '📡 ไลฟ์สด'],
        ['products', 'products.php', '📦 สินค้า'],
    ],
    'การขาย & จัดส่ง' => [
        ['orders', 'orders.php', '🧾 คำสั่งซื้อ'],
        ['sales', 'sales-analytics.php', '💰 ยอดขาย & กำไร'],
        ['shipping', 'shipping.php', '🚚 จัดส่ง & COD'],
        ['pickup', 'pickup.php', '🛻 รอบเข้ารับ & คนขับ'],
        ['returns', 'returns.php', '↩️ ตีกลับ & เซล'],
    ],
    'ตั้งค่า' => [
        ['settings', 'settings.php', '⚙️ โปรไฟล์ & AI'],
    ],
];
if (is_admin()) {
    $nav['ตั้งค่า'][] = ['codes', 'access-codes.php', '🔑 รหัสเข้าใช้งาน'];
}
?>
<aside class="sidebar">
    <div class="brand">
        <span class="brand-mark">A</span>
        <span class="brand-name"><?= APP_NAME ?></span>
    </div>
    <nav class="nav">
        <?php foreach ($nav as $group => $items): ?>
            <div class="nav-group"><?= e($group) ?></div>
            <?php foreach ($items as [$key, $file, $label]): ?>
                <a class="nav-item <?= ($__active ?? '') === $key ? 'active' : '' ?>"
                   href="<?= url($file) ?>"><?= $label ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
