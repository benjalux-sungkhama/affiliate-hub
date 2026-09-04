# ติดตั้ง AffiliateHub บนเครื่องของคุณ (XAMPP)

ระบบนี้เป็นเว็บแอป **PHP + MySQL** รันบน XAMPP ได้ทันที ทำตาม 5 ขั้นตอน

## Phase 1 — เตรียมเครื่องมือ
1. ติดตั้ง **XAMPP** (เลือกอย่างน้อย Apache + MySQL + PHP 8.x)
2. เปิด XAMPP Control Panel แล้วกด **Start** ทั้ง Apache และ MySQL (ต้องขึ้นเขียวทั้งคู่)

## Phase 2 — วางไฟล์โปรเจกต์
วางโค้ดทั้งหมดไว้ใน `htdocs/affiliatehub` เช่น

```bash
# Windows
cd C:\xampp\htdocs
git clone <repo-url> affiliatehub

# macOS
cd /Applications/XAMPP/htdocs
git clone <repo-url> affiliatehub
```

> ชื่อโฟลเดอร์ต้องเป็น **affiliatehub** (ตรงกับชื่อฐานข้อมูลและ `BASE_URL`)

## Phase 3 — สร้างฐานข้อมูล
1. เปิด `http://localhost/phpmyadmin`
2. เมนู **Import** → เลือกไฟล์ `sql/schema.sql` → Go
   (ไฟล์นี้สร้างฐานข้อมูล `affiliatehub` + ทุกตารางให้เอง ด้วย `utf8mb4_unicode_ci`)
3. Import ต่อด้วย `sql/seed.sql` (แพลตฟอร์ม + แอดมิน + Access Code แรก + สูตร seed)

> ฐานข้อมูลใหม่มีตารางระบบอัตโนมัติอยู่ใน `schema.sql` แล้ว
> ถ้าเป็นฐานข้อมูลเดิมที่ลงก่อนมีฟีเจอร์นี้ ให้ Import เพิ่ม `sql/automation.sql`
> และตั้ง cron `cron/automation_worker.php` (ดู `docs/automation-rules.md`)

## Phase 4 — ตั้งค่า
เปิด `config/config.php` แล้วตรวจให้ตรงกับ XAMPP (ปกติค่า default ใช้ได้เลย):

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'affiliatehub');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP ค่าเริ่มต้นคือว่าง
define('BASE_URL', '/affiliatehub');
```

## Phase 5 — เข้าใช้งาน + Smoke Test
เปิด `http://localhost/affiliatehub`

| # | ทดสอบ | ผลที่ต้องได้ |
|---|---|---|
| 1 | เปิดหน้าแรก | หน้าล็อกอินขึ้น ไม่มี error |
| 2 | ล็อกอินแอดมิน | `admin@affiliatehub.local` / `admin1234` |
| 3 | เมนู "รหัสเข้าใช้งาน" → ออก Access Code | บันทึกสำเร็จ |
| 4 | สมัครผู้ใช้ใหม่ด้วยรหัส `AFH-START-2026` | เข้าแดชบอร์ดได้ |
| 5 | เพิ่มสินค้า 1 ตัว (กรอกต้นทุน) | บันทึกสำเร็จ |
| 6 | สร้างคำสั่งซื้อทดสอบ | กำไรคำนวณถูกต้อง (ราคาขาย − ต้นทุน) |
| 7 | สร้างโพสต์ฉบับร่าง แล้วเปลี่ยนสถานะ | เปลี่ยนเป็นเข้าคิว/เผยแพร่ได้ |
| 8 | เปิดแท็บ "คลังสูตรของฉัน" | เห็นสูตร seed (เฉพาะบัญชีแอดมิน) |
| 9 | สร้างสูตรใหม่ 6 ซีน | บันทึกสำเร็จ ซีนครบ |
| 10 | ใช้สูตรสร้างคอนเทนต์ | ตัวแปร `{{ }}` ถูกเติมครบ |
| 11 | กดบันทึกผลลัพธ์เป็นสูตร | ขึ้นในคลังทันที |
| 12 | ส่งออก JSON แล้วนำเข้าใหม่ | ได้สูตรเหมือนเดิม |

> ⚠️ **เปลี่ยนรหัสผ่านแอดมินทันที** หลังล็อกอินครั้งแรก (เมนู ตั้งค่า → โปรไฟล์)

## บัญชี & รหัสเริ่มต้น
- แอดมิน: `admin@affiliatehub.local` / `admin1234`
- Access Code แรก: `AFH-START-2026` (ใช้ได้ 50 ครั้ง)

## หมายเหตุเรื่องการแยกข้อมูล
ทุกตารางที่เก็บข้อมูลผู้ใช้มี `user_id` และทุก query กรองด้วย `user_id` ของผู้ล็อกอิน
— ข้อมูลของผู้ใช้แต่ละคนแยกกัน 100%
