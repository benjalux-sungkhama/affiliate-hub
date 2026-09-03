<?php
/**
 * AffiliateHub — ค่าคอนฟิกหลัก
 * แก้ค่าตรงนี้ให้ตรงกับ XAMPP ของคุณ (ปกติค่า default ใช้ได้เลย)
 */

// ----- ฐานข้อมูล -----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'affiliatehub');   // ต้องตรงกับชื่อโฟลเดอร์โปรเจกต์เป๊ะ
define('DB_USER', 'root');
define('DB_PASS', '');                // ค่า default ของ XAMPP คือว่าง
define('DB_CHARSET', 'utf8mb4');

// ----- ระบบ -----
define('APP_NAME', 'AffiliateHub');
define('APP_TZ', 'Asia/Bangkok');

// Base URL path — ถ้าติดตั้งใน htdocs/affiliatehub ให้ใช้ '/affiliatehub'
define('BASE_URL', '/affiliatehub');

date_default_timezone_set(APP_TZ);

// แสดง error ระหว่างพัฒนา (ปิดเมื่อขึ้น production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
