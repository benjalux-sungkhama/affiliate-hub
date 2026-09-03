# AffiliateHub

ระบบจัดการการตลาดหลายแพลตฟอร์ม · ไลฟ์ขายของ · จัดส่ง/COD **ในระบบเดียว**
ข้อมูลของผู้ใช้แต่ละคนแยกกัน 100% (ทุกตารางมี `user_id`)

เว็บแอป **PHP 8 + MySQL** รันบน XAMPP ได้ทันที — ติดตั้งตาม [`INSTALL.md`](INSTALL.md)

> รีโปนี้มีทั้ง **โค้ดแอปพลิเคชัน** (โฟลเดอร์รากนี้) และ **Skill คู่มือ** สำหรับ Claude
> (`SKILL.md` + `references/`) การเปิด `SKILL.md` ยังใช้ค้นคู่มือการใช้งานได้เหมือนเดิม

---

## เริ่มใช้งานเร็ว

1. วางโค้ดไว้ที่ `htdocs/affiliatehub`
2. phpMyAdmin → Import `sql/schema.sql` แล้วตามด้วย `sql/seed.sql`
3. เปิด `http://localhost/affiliatehub`
4. ล็อกอินแอดมิน `admin@affiliatehub.local` / `admin1234` (เปลี่ยนรหัสทันที)
5. ออก Access Code แล้วให้ผู้ใช้ใหม่สมัคร (รหัสแรก `AFH-START-2026`)

รายละเอียดครบ + Smoke Test 12 ข้อ → [`INSTALL.md`](INSTALL.md)

---

## โครงสร้างไฟล์

```
affiliatehub/
├── index.php               ← เข้าเว็บ → เด้งไปล็อกอิน/แดชบอร์ด
├── login.php · register.php · logout.php · forgot-password.php
├── dashboard.php           ← ภาพรวม
├── platforms.php           ← เชื่อมบัญชี Facebook/TikTok/Shopee/Lazada
├── settings-posting.php    ← ตั้งค่าการโพสต์ & ช่วงเวลา
├── ai-content.php          ← ให้ AI คิดคอนเทนต์ (Storyboard 10 วิ)
├── formulas.php            ← คลังสูตรของฉัน (สร้าง/แก้/เวอร์ชัน/นำเข้า-ส่งออก JSON)
├── posts.php               ← สร้าง & จัดคิวโพสต์
├── analytics.php           ← วิเคราะห์ & แนะนำ Boost
├── live.php                ← ไลฟ์สด
├── products.php            ← สินค้า (ราคา/ต้นทุน/สต๊อก)
├── orders.php              ← คำสั่งซื้อ (ผูกที่มา + คำนวณกำไร)
├── sales-analytics.php     ← ยอดขาย & กำไร
├── shipping.php            ← จัดส่ง & COD
├── pickup.php              ← รอบเข้ารับ & คนขับ (ใบส่งมอบ Manifest)
├── returns.php             ← ตีกลับ & เซลรอบสอง
├── settings.php            ← โปรไฟล์ & การเชื่อมต่อ AI
├── access-codes.php        ← จัดการ Access Code (เฉพาะแอดมิน)
├── config/                 ← config.php, db.php (PDO)
├── includes/               ← auth.php, functions.php, header/sidebar/footer
├── assets/                 ← css/js
├── sql/                    ← schema.sql, seed.sql
├── SKILL.md · references/  ← Skill คู่มือสำหรับ Claude (คงไว้)
└── INSTALL.md
```

## โมดูลหลัก
- **การตลาด** — เชื่อมบัญชี · ตั้งตารางโพสต์ · AI คิดคอนเทนต์ · คลังสูตร · วิเคราะห์/Boost
- **การจัดการ** — ไลฟ์สด · สินค้า
- **ขาย & จัดส่ง** — คำสั่งซื้อ+กำไร · ยอดขาย · จัดส่ง/COD · รอบรถ/คนขับ · ตีกลับ

## คลังสูตรของฉัน
บันทึกสูตรคอนเทนต์ได้ 3 ทาง (บันทึกจากผลลัพธ์ / สร้างจากศูนย์ / ทำสำเนาแล้วแก้)
รองรับตัวแปร `{{product_name}}` `{{price}}` `{{usp}}` `{{target}}` `{{cta}}` `{{platform}}`
มีเวอร์ชันย้อนกลับได้ · นำเข้า/ส่งออก JSON · สถิติ CTR/ยอดขายต่อสูตร

## เทคโนโลยี
PHP 8 · MySQL (utf8mb4) · PDO + prepared statements · CSRF protection · ไม่พึ่ง framework
