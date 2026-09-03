<?php
/**
 * การเชื่อมต่อฐานข้อมูลด้วย PDO (prepared statements ทุกที่)
 */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(
            '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;'
            . 'border:1px solid #f3c;border-radius:12px;background:#fff5fb">'
            . '<h2>เชื่อมต่อฐานข้อมูลไม่ได้</h2>'
            . '<p>ตรวจว่า MySQL ใน XAMPP กำลังทำงาน และสร้างฐานข้อมูล <b>' . htmlspecialchars(DB_NAME)
            . '</b> พร้อม import <code>sql/schema.sql</code> และ <code>sql/seed.sql</code> แล้ว</p>'
            . '<p style="color:#a00">รายละเอียด: ' . htmlspecialchars($e->getMessage()) . '</p>'
            . '</div>'
        );
    }
    return $pdo;
}
