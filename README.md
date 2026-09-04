# AffiliateHub

ระบบจัดการการตลาดหลายแพลตฟอร์ม · ไลฟ์ขายของ · จัดส่ง/COD · **โพสต์อัตโนมัติ** ในระบบเดียว
ข้อมูลของผู้ใช้แต่ละคนแยกกัน 100% (ทุกตารางมี `user_id`)

เว็บแอป **PHP 8 + MySQL** รันบน XAMPP ได้ทันที — ติดตั้งตาม [`INSTALL.md`](INSTALL.md)

> รีโปนี้มีทั้ง **โค้ดแอปพลิเคชัน** (โฟลเดอร์รากนี้) และ **Skill คู่มือ** สำหรับ Claude
> (`SKILL.md` + `references/`, ปัจจุบัน **v1.3.0**) การเปิด `SKILL.md` ยังใช้ค้นคู่มือได้เหมือนเดิม

---

## เริ่มใช้งานเร็ว

1. วางโค้ดไว้ที่ `htdocs/affiliatehub`
2. phpMyAdmin → Import `sql/schema.sql` แล้วตามด้วย `sql/seed.sql`
3. เปิด `http://localhost/affiliatehub`
4. ล็อกอินแอดมิน `admin@affiliatehub.local` / `admin1234` (เปลี่ยนรหัสทันที)
5. ออก Access Code แล้วให้ผู้ใช้ใหม่สมัคร (รหัสแรก `AFH-START-2026`)

รายละเอียดครบ + Smoke Test → [`INSTALL.md`](INSTALL.md) · ระบบอัตโนมัติ → [`docs/automation-rules.md`](docs/automation-rules.md)

---

## โครงสร้างไฟล์ (แอปพลิเคชัน)

```
affiliatehub/
├── index.php               ← เข้าเว็บ → เด้งไปล็อกอิน/แดชบอร์ด
├── login.php · register.php · logout.php · forgot-password.php
├── dashboard.php           ← ภาพรวม
├── platforms.php           ← เชื่อมบัญชี Facebook/TikTok/Shopee/Lazada (4 แท็บ)
├── settings-posting.php    ← ตั้งค่าการโพสต์ & ช่วงเวลา + โหมด automation
├── ai-content.php          ← ให้ AI คิดคอนเทนต์ (Storyboard 10 วิ)
├── formulas.php            ← คลังสูตรของฉัน (สร้าง/แก้/เวอร์ชัน/นำเข้า-ส่งออก JSON)
├── posts.php               ← สร้าง & จัดคิวโพสต์
├── analytics.php           ← วิเคราะห์ & แนะนำ Boost
├── automation.php          ← ระบบโพสต์อัตโนมัติ (กฎ/อนุมัติ/ประวัติ/Blueprint/Guardrails)
├── live.php                ← ไลฟ์สด
├── products.php            ← สินค้า (ราคา/ต้นทุน/สต๊อก)
├── orders.php              ← คำสั่งซื้อ (ผูกที่มา + คำนวณกำไร)
├── sales-analytics.php     ← ยอดขาย & กำไร
├── shipping.php            ← จัดส่ง & COD
├── pickup.php              ← รอบเข้ารับ & คนขับ (ใบส่งมอบ Manifest)
├── returns.php             ← ตีกลับ & เซลรอบสอง
├── settings.php            ← โปรไฟล์ & การเชื่อมต่อ AI
├── access-codes.php        ← จัดการ Access Code (เฉพาะแอดมิน)
├── config/ · includes/ · assets/
├── cron/                   ← scheduler.php (เผยแพร่คิว) + automation_worker.php (ทริกเกอร์เวลา)
├── sql/                    ← schema.sql, seed.sql, automation.sql
├── docs/                   ← INSTALL หมายเหตุ, AUTOMATION.md, automation-rules.md
├── SKILL.md · references/  ← Skill คู่มือสำหรับ Claude (v1.3.0)
└── INSTALL.md
```

## โมดูลหลัก
- **การตลาด** — เชื่อมบัญชี · ตั้งตารางโพสต์ · AI คิดคอนเทนต์ · คลังสูตร · วิเคราะห์/Boost · **ระบบอัตโนมัติ (กฎ Trigger→Condition→Action)**
- **การจัดการ** — ไลฟ์สด · สินค้า
- **ขาย & จัดส่ง** — คำสั่งซื้อ+กำไร · ยอดขาย · จัดส่ง/COD · รอบรถ/คนขับ · ตีกลับ

## คลังสูตรของฉัน
บันทึกสูตรคอนเทนต์ได้ 3 ทาง (บันทึกจากผลลัพธ์ / สร้างจากศูนย์ / ทำสำเนาแล้วแก้)
รองรับตัวแปร `{{product_name}}` `{{price}}` `{{usp}}` `{{target}}` `{{cta}}` `{{platform}}`
มีเวอร์ชันย้อนกลับได้ · นำเข้า/ส่งออก JSON · สถิติ CTR/ยอดขายต่อสูตร

## ระบบโพสต์อัตโนมัติ
กฎ Trigger → Condition → Action ผูกกับสูตรในคลัง · โหมด Draft/Review/Auto · Guardrails + Kill Switch
· Worker (`cron/automation_worker.php`) สำหรับทริกเกอร์เวลา · ทริกเกอร์เหตุการณ์ยิงทันที
รายละเอียด → [`docs/automation-rules.md`](docs/automation-rules.md)

## เทคโนโลยี
PHP 8 · MySQL (utf8mb4) · PDO + prepared statements · CSRF protection · ไม่พึ่ง framework

---

# Skill (คู่มือสำหรับ Claude) — v1.3.0

> ส่วนนี้คือ Skill ที่ Claude อ่านเพื่อช่วยติดตั้ง/ใช้งาน — Claude อ่าน `SKILL.md` ก่อนเสมอ

| ไฟล์ | หน้าที่ | เปิดเมื่อ |
|---|---|---|
| `SKILL.md` | Quick Reference + แผนผังโมดูล + กติกาการตอบ + Auto-Install Mode | Claude อ่านทุกครั้ง |
| `references/installation.md` | สคริปต์ติดตั้ง Phase 0–5 + ชุด Prompt 4.1–4.8 + Smoke Test | ผู้ใช้สั่ง "ติดตั้ง" |
| `references/onboarding.md` | สมัคร / ล็อกอิน / Access Code / สิทธิ์ผู้ใช้ | ถามเรื่องเข้าระบบ |
| `references/marketing.md` | เชื่อมบัญชี, ตารางโพสต์, AI คิดคอนเทนต์, คลังสูตร, Boost, ไลฟ์ | ถามเรื่องการตลาด/คอนเทนต์ |
| `references/automation.md` | กฎอัตโนมัติ, ทริกเกอร์, โหมดอนุมัติ, Guardrails, Worker/Cron | ถามเรื่องโพสต์อัตโนมัติ |
| `references/sales-fulfillment.md` | สินค้า, ออเดอร์, กำไร, จัดส่ง, COD, รอบรถ, ตีกลับ | ถามเรื่องขาย/ส่งของ |
| `references/troubleshooting.md` | แก้ปัญหา ตั้งแต่ติดตั้งถึงใช้งานจริง | เจอ error |

## ประวัติเวอร์ชัน (Skill)

| เวอร์ชัน | วันที่ | เปลี่ยนอะไร |
|---|---|---|
| **v1.3.0** | 4 ก.ย. 2026 | เพิ่ม **ระบบโพสต์อัตโนมัติ** — `automation.md`, Prompt 4.8, 4 ตารางใหม่, Worker/Cron, Guardrails, Smoke Test 13–18, แก้ปัญหา §13 |
| v1.2.0 | 3 ก.ย. 2026 | เพิ่ม **คลังสูตรของฉัน** (§3.3–3.4), Prompt 4.4b, 3 ตารางใหม่, Smoke Test 7–12 |
| v1.1.0 | — | เพิ่มสูตร Storyboard 10 วิ 6 ซีน + ตัวอย่าง Omni Flash |
| v1.0.0 | — | เวอร์ชันแรก: ติดตั้ง, onboarding, การตลาด, ขาย/จัดส่ง, แก้ปัญหา |

## ตารางฐานข้อมูลที่เพิ่มมา

**v1.2.0 — คลังสูตร:** `content_formulas` · `content_formula_scenes` · `content_formula_usages`
**v1.3.0 — ระบบอัตโนมัติ:** `automation_rules` · `automation_runs` · `automation_approvals` · `automation_settings`
ทุกตารางมี `user_id` และต้อง filter ด้วย `user_id` ทุก query
