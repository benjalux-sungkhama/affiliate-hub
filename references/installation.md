# ติดตั้งและสร้างระบบ AffiliateHub

> ไฟล์นี้เป็นสคริปต์ปฏิบัติการ อ่านจบทั้งไฟล์ก่อนลงมือ

## ตัวแปรมาตรฐาน

| ตัวแปร | ค่าเริ่มต้น | หมายเหตุ |
|---|---|---|
| `PROJECT` | `affiliatehub` | ชื่อโฟลเดอร์ = ชื่อ DB |
| `HTDOCS` (Win) | `C:\xampp\htdocs` | |
| `HTDOCS` (macOS) | `/Applications/XAMPP/htdocs` | |
| `URL` | `http://localhost/affiliatehub` | |
| `DB_USER` | `root` | |
| `DB_PASS` | *(ว่าง)* | ค่า default ของ XAMPP |
| `WORKER` | `cron/automation_worker.php` | ตัวรันระบบอัตโนมัติ |
| `CRON_EVERY` | 5 นาที | ความถี่ที่แนะนำ |

## Phase 0 — Preflight Check

ตรวจและรายงานเป็นตารางก่อนเริ่มเสมอ:

| รายการ | คำสั่งตรวจ | เกณฑ์ผ่าน |
|---|---|---|
| ระบบปฏิบัติการ | — | Windows 10+ / macOS 12+ |
| Git | `git --version` | มีเลขเวอร์ชันขึ้น |
| XAMPP | เปิด Control Panel | ติดตั้งแล้ว |
| Apache | สถานะใน XAMPP | Start ได้ (เขียว) |
| MySQL | สถานะใน XAMPP | Start ได้ (เขียว) |
| PHP CLI | `php -v` | มีเวอร์ชันขึ้น (จำเป็นสำหรับระบบอัตโนมัติ) |
| Port 80 | `netstat -ano \| findstr :80` | ว่าง หรือเป็นของ Apache |
| Port 3306 | `netstat -ano \| findstr :3306` | ว่าง หรือเป็นของ MySQL |
| htdocs | เข้าถึงโฟลเดอร์ | เขียนไฟล์ได้ |
| ชื่อซ้ำ | ดู `htdocs/affiliatehub` | **ต้องยังไม่มี** |

**ถ้าไม่ผ่านข้อไหน** → ข้ามไป "ภาคผนวก A" เพื่อติดตั้งส่วนที่ขาด แล้วตรวจใหม่

---

## Phase 1 — เตรียมเครื่องมือ

1. **Claude App** — ติดตั้งและล็อกอินให้เรียบร้อย
2. **XAMPP** — ติดตั้ง ต้องเลือกอย่างน้อย Apache + MySQL + PHP + phpMyAdmin
3. **Git** — ติดตั้งแล้ว **ปิด-เปิด Terminal ใหม่** เพื่อให้ PATH อัปเดต

✅ **จบ Phase 1 เมื่อ:** `git --version` และ `php -v` ขึ้นเวอร์ชัน และ XAMPP Start ได้ทั้ง Apache/MySQL

---

## Phase 2 — เตรียมโฟลเดอร์โปรเจกต์

```bash
# Windows
cd C:\xampp\htdocs
mkdir affiliatehub
cd affiliatehub
git init

# macOS
cd /Applications/XAMPP/htdocs
mkdir affiliatehub
cd affiliatehub
git init
```

> **macOS:** ถ้าสร้างโฟลเดอร์ไม่ได้เพราะสิทธิ์ระบบ — เป็นเรื่องปกติ ไม่ใช่บั๊ก
> ให้สั่ง Claude สร้างให้แทน

✅ **จบ Phase 2 เมื่อ:** มีโฟลเดอร์ `affiliatehub` ใน htdocs และ `git init` สำเร็จ

---

## Phase 3 — สร้างฐานข้อมูล

1. เปิด `http://localhost/phpmyadmin`
2. สร้างฐานข้อมูลชื่อ **`affiliatehub`** (ตรงกับชื่อโฟลเดอร์เป๊ะ)
3. Collation: `utf8mb4_unicode_ci` — จำเป็นสำหรับภาษาไทยและอิโมจิ

> **macOS:** ถ้ากดสร้างแล้วไม่ขึ้น ให้สั่ง Claude สร้างผ่าน SQL แทน

✅ **จบ Phase 3 เมื่อ:** เห็นฐานข้อมูล `affiliatehub` ในรายการฝั่งซ้ายของ phpMyAdmin

---

## Phase 4 — รันชุด Prompt สร้างระบบ

เปิด Claude App ชี้ไปที่โฟลเดอร์โปรเจกต์ แล้วรัน **ตามลำดับ** — รอแต่ละชุดเสร็จก่อนขึ้นชุดถัดไป

**ลำดับบังคับ:** 4.1 → 4.2 → 4.3 → 4.4 → **4.4b** → 4.5 → 4.6 → 4.7 → **4.8**

### 4.1 สร้างฐานข้อมูลและตาราง
```
สร้างโครงสร้างฐานข้อมูล affiliatehub สำหรับระบบ AffiliateHub
ประกอบด้วยตาราง: users, access_codes, platforms, platform_accounts,
posts, post_schedules, ai_contents, content_formulas,
content_formula_scenes, content_formula_usages,
automation_rules, automation_runs, automation_approvals, automation_settings,
live_sessions, live_stats, products, categories, orders, order_items,
shipments, cod_records, pickup_rounds, drivers, returns, settings
พร้อม foreign key และ index ที่จำเป็น ใช้ utf8mb4_unicode_ci
ทุกตารางที่เก็บข้อมูลผู้ใช้ต้องมี user_id เพื่อแยกข้อมูล 100%
```

### 4.2 ระบบสมัคร/ล็อกอิน + Access Code
```
สร้างระบบสมัครสมาชิกด้วย Access Code, ล็อกอิน, ลืมรหัสผ่าน
และหน้าจัดการ Access Code สำหรับแอดมิน
(ป้ายกำกับ, จำนวนครั้งที่ใช้ได้, วันหมดอายุ, เปิด/ปิด/ลบ)
```

### 4.3 แดชบอร์ด + โมดูลแพลตฟอร์ม
```
สร้างแดชบอร์ด (ยอดคลิกรวม, การเข้าถึงรวม, โพสต์ที่เผยแพร่, ยอดขายไลฟ์)
และโมดูลแพลตฟอร์ม Facebook/TikTok/Shopee/Lazada
แต่ละแพลตฟอร์มมี 4 แท็บ: ภาพรวม, โพสต์ & คิว, บัญชีที่เชื่อม, วิเคราะห์
```

### 4.4 AI คอนเทนต์ + คิวโพสต์
```
สร้างหน้าตั้งค่าการโพสต์ & ช่วงเวลา, หน้าให้ AI คิดคอนเทนต์ (เชื่อม Qwen),
หน้าสร้าง/จัดคิว/จัดการโพสต์ (สถานะ: ฉบับร่าง, เข้าคิว, เผยแพร่แล้ว, ล้มเหลว)
และหน้าวิเคราะห์ & AI แนะนำ Boost
ในหน้า AI คิดคอนเทนต์ ให้มีเทมเพลต "Storyboard มินิมอล 10 วินาที"
แบบ 6 ซีน ซีนละ 1.5–2 วินาที พร้อมช่องกรอก: ข้อมูลสินค้า, แนวคิดหลัก,
ตัวละคร, Text Overlay, เสียง
```

### 4.4b คลังสูตรของฉัน (ต้องรันหลัง 4.4)
```
เพิ่มฟีเจอร์ "คลังสูตรของฉัน" ในหน้าให้ AI คิดคอนเทนต์ ของระบบ AffiliateHub

ฐานข้อมูล: สร้างตาราง content_formulas, content_formula_scenes,
content_formula_usages ตาม schema นี้ ทุกตารางมี user_id
และ filter ด้วย user_id ทุก query

content_formulas (
  id, user_id, name, category, platforms, total_seconds,
  scene_count, overlay_style, audio_style, tone, notes,
  variables_json, is_default, is_active, version,
  parent_id, created_at, updated_at
)
content_formula_scenes (
  id, formula_id, seq, time_from, time_to, description,
  camera_angle, lighting, overlay_text
)
content_formula_usages (
  id, formula_id, user_id, post_id, product_id,
  reach, engagement, ctr, linked_sales, used_at
)
Index: (user_id, category), (formula_id, seq), (formula_id, used_at)

หน้าจอ:
1. แท็บ "คลังสูตรของฉัน" — ตารางรายการสูตร แสดงชื่อ หมวด แพลตฟอร์ม
   จำนวนครั้งที่ใช้ CTR เฉลี่ย ยอดขายที่ผูก ใช้ล่าสุด พร้อมค้นหาและกรองตามหมวด
2. ปุ่ม "+ สูตรใหม่" → ฟอร์มสร้างสูตร มีตารางซีนแบบเพิ่ม/ลบ/ลากสลับลำดับได้
   ฟิลด์ต่อซีน: ลำดับ, เวลาเริ่ม, เวลาจบ, เนื้อหา, มุมกล้อง, แสง, ข้อความบนจอ
3. ปุ่ม "บันทึกเป็นสูตร" บนผลลัพธ์ที่ AI สร้าง → เปิด modal ตั้งชื่อแล้วบันทึกทันที
   โดยแปลงผลลัพธ์เป็นโครงซีนอัตโนมัติ
4. ปุ่มต่อแถว: ใช้สูตรนี้, ทำสำเนา, แก้ไข, ตั้งเป็นค่าเริ่มต้น,
   เปิด/ปิดใช้งาน, ส่งออก JSON, ลบ (ยืนยัน 2 ชั้น)
5. ปุ่ม "นำเข้า JSON" ด้านบนตาราง

ตรรกะ:
- รองรับตัวแปร {{product_name}} {{price}} {{usp}} {{target}} {{cta}} {{platform}}
  เติมค่าอัตโนมัติจากเมนูสินค้าและช่องกรอก ถ้าหาค่าไม่เจอให้แสดง modal ถามก่อนสร้าง
- หมวดละ 1 สูตรที่เป็นค่าเริ่มต้นเท่านั้น
- แก้แล้วบันทึกให้เพิ่ม version และเก็บเวอร์ชันเดิมไว้ (parent_id) ย้อนกลับได้
- ลบไม่ได้ถ้ามีโพสต์สถานะ "เข้าคิว" อ้างอิงอยู่
- ทุกครั้งที่สร้างคอนเทนต์จากสูตร ให้บันทึกลง content_formula_usages
  และอัปเดตสถิติเมื่อโพสต์นั้นมีข้อมูล reach/ctr/ยอดขายเข้ามา
- Seed สูตรเริ่มต้น 1 รายการ: "วิดีโอสั้น 10 วิ — 6 ซีนมาตรฐาน"
  ตามโครงในคู่มือ marketing.md §3.1 และตั้งเป็นค่าเริ่มต้น
```

### 4.5 ไลฟ์สด + สินค้า
```
สร้างหน้าไลฟ์สด (บันทึกรอบไลฟ์, สถิติ, กราฟผู้ชม, ช่วงเวลาขายดี, สินค้าที่ปัง)
และหน้าจัดการสินค้า (ชื่อ, ราคาขาย, ต้นทุน, สต๊อก, หมวดหมู่)
```

### 4.6 ขาย & จัดส่ง
```
สร้างหน้าคำสั่งซื้อ (ผูกที่มาจากไลฟ์/โพสต์ + คำนวณกำไรจากต้นทุน),
หน้าวิเคราะห์ยอดขาย & กำไร,
หน้าการจัดส่ง & COD (5 สถานะ + ยืนยันโอนเงินคืนร้าน),
หน้ารอบเข้ารับ & คนขับ (สร้างใบส่งมอบ Manifest อัตโนมัติ),
หน้าตีกลับ & เซลรอบสอง (บันทึกสาเหตุ + คืนสต๊อกอัตโนมัติ)
```

### 4.7 ตั้งค่า
```
สร้างหน้าโปรไฟล์ & การเชื่อมต่อ AI (API Key)
และตรวจสอบว่าทุกหน้า filter ข้อมูลด้วย user_id ครบถ้วน
รวมถึงตาราง content_formulas, content_formula_scenes, content_formula_usages
```

### 4.8 ระบบโพสต์อัตโนมัติ (ต้องรันหลัง 4.4b)

> กฎอัตโนมัติต้องผูกกับสูตรในคลัง ถ้ายังไม่ได้รัน 4.4b ให้ย้อนกลับไปทำก่อน

```
สร้างระบบโพสต์อัตโนมัติ (Automation Post) ใน AffiliateHub

ฐานข้อมูล — สร้าง 4 ตารางด้วย CREATE TABLE IF NOT EXISTS ทุกตารางมี user_id:
1. automation_rules: id, user_id, name, description, trigger_type, trigger_config JSON,
   conditions JSON, formula_id, actions JSON, approval_mode ENUM('draft','review','auto'),
   guardrails JSON, is_active TINYINT DEFAULT 0, fail_streak INT DEFAULT 0,
   success_review_count INT DEFAULT 0, last_run_at, created_at, updated_at
   FOREIGN KEY formula_id REFERENCES content_formulas(id) ON DELETE RESTRICT
2. automation_runs: id, user_id, rule_id, started_at, finished_at,
   status ENUM('success','skipped','failed'), skip_reason, output_ref, error_message
3. automation_approvals: id, user_id, rule_id, run_id, content LONGTEXT, product_id,
   platforms JSON, scheduled_at, status ENUM('pending','approved','edited','rejected','expired'),
   decided_at, created_at
4. automation_settings: user_id PRIMARY KEY, max_posts_per_day INT DEFAULT 8,
   min_gap_minutes INT DEFAULT 90, quiet_hours JSON, max_ai_calls_per_day INT DEFAULT 30,
   dedup_threshold INT DEFAULT 85, product_cooldown_days INT DEFAULT 7,
   kill_switch TINYINT DEFAULT 0
Index: (user_id, is_active), (rule_id, started_at), (user_id, status)

หน้าเว็บ — เมนูใหม่ "ระบบอัตโนมัติ" มี 5 แท็บ:
- กฎทั้งหมด: ตาราง (ชื่อกฎ, ทริกเกอร์, สูตรที่ใช้, โหมด, ยิงล่าสุด, สำเร็จ/ล้มเหลว)
  + สวิตช์เปิดปิด + ปุ่มทดลองรัน (Dry Run) + ปุ่มรันเดี๋ยวนี้ + ส่งออก/นำเข้า JSON
- รออนุมัติ: การ์ดคอนเทนต์ พร้อมปุ่ม อนุมัติ / แก้แล้วอนุมัติ / ทิ้ง และอนุมัติหลายรายการพร้อมกัน
- ประวัติการทำงาน: ทุก Run พร้อมเหตุผลที่ข้าม กรองตามกฎและวันที่ได้
- สูตรสำเร็จ: Blueprint 6 แบบตาม automation.md §8 กดติดตั้งเป็นกฎได้ทันที
- ตั้งค่า: Guardrails ทั้งหมด + ปุ่ม Kill Switch สีแดงชัดเจน

ตัวสร้างกฎ (Rule Builder) แบบ 4 ขั้น:
เลือกทริกเกอร์ > ใส่เงื่อนไข > เลือกสูตรและปลายทาง > เลือกโหมดอนุมัติ
รองรับทริกเกอร์: schedule.daily, schedule.weekly, schedule.interval, schedule.before_live,
product.created, product.price_changed, product.low_stock, product.restocked,
post.high_performing, order.milestone, live.ended, formula.updated
รองรับเงื่อนไขแบบ field/op/value โดย op รับค่า:
=, !=, >, >=, <, <=, between, in, not_in, contains
รองรับแอ็กชัน: generate_content, enqueue_post, publish_now, create_draft, notify, log_only

กฎเหล็กที่ต้องบังคับในโค้ด:
- กฎที่สร้างใหม่ต้องเริ่มที่โหมด draft และ is_active = 0 เสมอ
- เปลี่ยนเป็นโหมด auto ได้ต่อเมื่อ success_review_count >= 10
- ก่อนโพสต์ทุกครั้งต้องเช็ก guardrails ระดับบัญชี: เพดานต่อวัน, เว้นระยะขั้นต่ำ,
  ช่วงเวลาห้ามโพสต์, กันคอนเทนต์ซ้ำ (ความเหมือน > dedup_threshold), กันสินค้าซ้ำ
- ถ้าตัวแปร {{ }} ตัวใดหาค่าไม่เจอ ห้ามโพสต์ ให้บันทึก run เป็น failed พร้อมเหตุผล
- กฎที่ล้มเหลว 3 ครั้งติดกัน (fail_streak >= 3) ให้ปิดอัตโนมัติและแจ้งเตือน
- kill_switch = 1 ต้องหยุดทุกกฎทันที และบันทึก run เป็น skipped พร้อมเหตุผล
- รายการรออนุมัติที่เลย scheduled_at เกิน 24 ชั่วโมง ให้ตั้งเป็น expired อัตโนมัติ
- ทุก query ต้อง filter ด้วย user_id

Worker — สร้างไฟล์ cron/automation_worker.php:
- ทำงานได้จาก command line เท่านั้น (ปฏิเสธการเรียกผ่าน HTTP)
- ใช้ lock file กันรันซ้อน รอบก่อนยังไม่จบให้รอบใหม่ข้ามทันที
- ประมวลผลสูงสุด 10 งานต่อรอบ
- บันทึกทุก run ลง automation_runs เสมอ แม้ผลจะเป็น skipped
- เจอ error ให้บันทึกแล้วไปงานถัดไป ห้าม die กลางทาง

ภาษาไทยทั้งหมด ใช้ utf8mb4 ไม่ต้องแก้ไฟล์อื่นที่ไม่เกี่ยวข้อง
```

**หลังรันเสร็จ ตั้ง Cron ทันที**

Windows (Task Scheduler):
```
โปรแกรม: C:\xampp\php\php.exe
อาร์กิวเมนต์: C:\xampp\htdocs\affiliatehub\cron\automation_worker.php
ทำซ้ำ: ทุก 5 นาที
ติ๊ก: Run whether user is logged on or not
```

macOS / Linux (crontab):
```bash
crontab -e
# เพิ่มบรรทัดนี้
*/5 * * * * /Applications/XAMPP/bin/php /Applications/XAMPP/htdocs/affiliatehub/cron/automation_worker.php >> /tmp/ahub_cron.log 2>&1
```

ทดสอบด้วยมือก่อนเสมอ — ต้องไม่มี error:
```bash
php C:\xampp\htdocs\affiliatehub\cron\automation_worker.php                          # Windows
/Applications/XAMPP/bin/php /Applications/XAMPP/htdocs/affiliatehub/cron/automation_worker.php   # macOS
```

✅ **จบ Phase 4 เมื่อ:** ทุกชุด Prompt (4.1–4.8 รวม 4.4b) รันผ่าน และ Worker รันด้วยมือได้ไม่มี error

---

## Phase 5 — Smoke Test

| # | ทดสอบ | ทำอย่างไร | ผลที่ต้องได้ |
|---|---|---|---|
| 1 | เปิดหน้าแรก | เข้า `http://localhost/affiliatehub` | หน้าล็อกอินขึ้น ไม่มี error |
| 2 | สร้าง Access Code | ตั้งค่า → รหัสเข้าใช้งาน | บันทึกสำเร็จ |
| 3 | สมัครสมาชิก | ใช้รหัสที่เพิ่งสร้าง | เข้าแดชบอร์ดได้ |
| 4 | เพิ่มสินค้า | กรอกราคาขาย + ต้นทุน | บันทึกสำเร็จ |
| 5 | สร้างออเดอร์ | ผูกสินค้าที่เพิ่งเพิ่ม | กำไรคำนวณถูกต้อง |
| 6 | สร้างโพสต์ | บันทึกเป็นฉบับร่าง | เปลี่ยนสถานะได้ |
| 7 | เปิดคลังสูตร | ให้ AI คิดคอนเทนต์ → แท็บคลังสูตร | เห็นสูตร seed 1 รายการ |
| 8 | สร้างสูตรใหม่ | + สูตรใหม่ → ใส่ 6 ซีน | บันทึกสำเร็จ ซีนครบ |
| 9 | ใช้สูตร | เลือกสินค้า → สร้างคอนเทนต์ | ตัวแปรถูกเติมครบ ไม่มีช่องว่าง |
| 10 | บันทึกผลลัพธ์เป็นสูตร | กด 💾 บันทึกเป็นสูตร | ขึ้นในคลังทันที |
| 11 | นำเข้า/ส่งออก | ส่งออก JSON แล้วนำเข้าใหม่ | ได้สูตรเหมือนเดิม |
| 12 | กันลบผิด | ลบสูตรที่มีโพสต์เข้าคิว | ระบบปฏิเสธพร้อมข้อความเตือน |
| 13 | สร้างกฎอัตโนมัติ | ระบบอัตโนมัติ → + สร้างกฎ ครบ 4 ขั้น | บันทึกได้ และเริ่มที่โหมด Draft อัตโนมัติ |
| 14 | ทดลองรัน | กด ▶️ Dry Run | เห็นคอนเทนต์ตัวอย่าง แต่ไม่มีโพสต์ถูกสร้าง |
| 15 | ติดตั้ง Blueprint | แท็บสูตรสำเร็จ → ติดตั้ง "สินค้าใหม่เข้า" | มีกฎใหม่โผล่ในตาราง |
| 16 | ทดสอบทริกเกอร์ | เพิ่มสินค้าใหม่ → รัน worker ด้วยมือ | มีรายการโผล่ในแท็บรออนุมัติ |
| 17 | อนุมัติ | กด ✅ ในแท็บรออนุมัติ | โพสต์เข้าคิวจริง |
| 18 | Kill Switch | กดปุ่มแดง → รัน worker | ทุกกฎถูกข้าม บันทึกเหตุผล "kill switch" |

**เมื่อผ่านครบ ให้รายงานผู้ใช้:**
- URL เข้าระบบ
- Access Code แรกที่สร้างไว้
- สถานะ Cron (ตั้งแล้วหรือยัง)
- **ย้ำว่ากฎอัตโนมัติทุกกฎยังอยู่โหมด Draft/Review**
- ขั้นตอนถัดไป → `references/onboarding.md`

---

## ภาคผนวก A — แก้ Preflight ไม่ผ่าน

| ไม่ผ่านข้อ | วิธีแก้ |
|---|---|
| Git ไม่เจอ | ติดตั้ง Git → ปิด-เปิด Terminal ใหม่ → รีสตาร์ท Claude App |
| `php -v` ไม่เจอ | เพิ่ม `C:\xampp\php` ลง PATH หรือเรียกด้วย path เต็ม |
| Apache Start ไม่ขึ้น | Port 80 ชน (Skype/IIS/VPN) → ปิดโปรแกรมนั้น หรือย้าย Port |
| MySQL Start ไม่ขึ้น | Port 3306 ชน (MySQL ตัวอื่น) → ปิด service เดิม |
| เขียน htdocs ไม่ได้ | Windows: รัน XAMPP as Administrator / macOS: ให้ Claude สร้างให้ |
| โฟลเดอร์ชื่อซ้ำ | **หยุด** แล้วถามผู้ใช้ว่าจะเปลี่ยนชื่อหรือใช้ของเดิม |

รายละเอียดเพิ่มเติม → `references/troubleshooting.md`

## ภาคผนวก B — Rollback

ถ้าต้องเริ่มใหม่:
1. **ปิด Task Scheduler / crontab ของ worker ก่อนเสมอ** ไม่งั้นมันจะรันใส่ DB ที่กำลังลบ
2. ลบฐานข้อมูล `affiliatehub` ใน phpMyAdmin
3. ลบโฟลเดอร์ใน htdocs
4. เริ่มที่ Phase 2

> ⚠️ ทำเฉพาะตอนที่ยังไม่มีข้อมูลจริงเท่านั้น

## ภาคผนวก C — ติดตั้งคลังสูตรย้อนหลัง

ถ้าติดตั้งระบบไปก่อนที่จะมีฟีเจอร์นี้ ไม่ต้องรื้อใหม่:

1. สำรองฐานข้อมูลก่อน (phpMyAdmin → Export → SQL)
2. เช็กว่ามีตารางหรือยัง:
```sql
SHOW TABLES LIKE 'content_formula%';
```
3. รัน **Prompt 4.4b** ชุดเดียวจบ
4. รัน Smoke Test ข้อ 7–12

## ภาคผนวก D — ติดตั้งระบบอัตโนมัติย้อนหลัง

สำหรับระบบที่ลงไปแล้วและมีคลังสูตรครบ:

1. สำรองฐานข้อมูล (phpMyAdmin → Export → SQL)
2. ตรวจว่ามีคลังสูตรแล้ว — ถ้ายังไม่มี ให้ทำภาคผนวก C ก่อน
```sql
SHOW TABLES LIKE 'content_formula%';
SHOW TABLES LIKE 'automation_%';
```
3. รัน **Prompt 4.8** ชุดเดียวจบ
4. ตั้ง Cron ตามขั้นตอนท้าย Phase 4.8
5. รัน Smoke Test ข้อ 13–18
6. เปิดใช้ทีละกฎ **โดยคงโหมด Review ไว้อย่างน้อย 14 วัน** ก่อนพิจารณาเปลี่ยนเป็น Auto