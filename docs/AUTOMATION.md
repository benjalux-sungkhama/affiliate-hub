# ระบบเผยแพร่โพสต์อัตโนมัติ (Automation)

AffiliateHub มีตัวรันเบื้องหลังที่หยิบโพสต์สถานะ **"เข้าคิว"** ซึ่งถึงเวลาที่ตั้งไว้
(`scheduled_at`) มาเผยแพร่ให้เองโดยไม่ต้องกดมือ

## ภาพรวม 2 ชั้น

1. **ตัวรันอัตโนมัติ (Scheduler)** — `cron/scheduler.php`
   ให้ **Windows Task Scheduler** เรียกทุก ๆ 1–5 นาที
2. **ตัวเชื่อม API แพลตฟอร์ม (Publisher)** — `includes/publishers.php`
   เผยแพร่จริงไปยัง Facebook/TikTok/Shopee/Lazada (ต้องมี Access Token ของคุณเอง)

## 3 โหมด (ตั้งที่เมนู ตั้งค่าการโพสต์ & ช่วงเวลา)

| โหมด | ทำอะไร | ต้องมีอะไร |
|---|---|---|
| **off** | ปิด automation — กดเผยแพร่เองในหน้าโพสต์ | — |
| **simulate** | เดินคิวอัตโนมัติจริง แต่ **ไม่ยิง API** (แค่เปลี่ยนสถานะเป็น "เผยแพร่แล้ว") | — เหมาะทดสอบ flow |
| **live** | ยิง API แพลตฟอร์มจริง | บัญชีที่เชื่อม + **Page ID** + **Access Token** |

## ขั้นตอนเปิดใช้งาน (Windows)

### 1) ทดสอบสคริปต์ด้วยมือก่อน
เปิด Command Prompt แล้วรัน:
```
C:\xampp\php\php.exe C:\xampp\htdocs\affiliatehub\cron\scheduler.php
```
ควรเห็นข้อความสรุป และมีไฟล์ `cron\scheduler.log` ถูกสร้าง

### 2) ตั้งให้รันเองด้วย Task Scheduler
1. เปิด **Task Scheduler** (พิมพ์ในเมนู Start)
2. **Create Task…**
   - แท็บ *General*: ตั้งชื่อ เช่น `AffiliateHub Auto Post` → เลือก *Run whether user is logged on or not*
   - แท็บ *Triggers* → **New…** → *Begin the task: On a schedule* → *Daily* →
     ติ๊ก **Repeat task every 5 minutes** for a duration of **Indefinitely**
   - แท็บ *Actions* → **New…** → *Start a program* →
     **Program/script:** `C:\xampp\htdocs\affiliatehub\cron\run-scheduler.bat`
   - กด OK
3. คลิกขวาที่ task → **Run** เพื่อทดสอบ แล้วเปิดดู `cron\scheduler.log`

> ถ้า path XAMPP ของคุณไม่ใช่ `C:\xampp` ให้แก้ในไฟล์ `cron\run-scheduler.bat`

### 3) เปิดโหมดในระบบ
เข้าเว็บ → **ตั้งค่าการโพสต์ & ช่วงเวลา** → เลือกโหมด **simulate** (ทดสอบ) หรือ **live**

### 4) ทดลอง
1. ไป **สร้าง & จัดคิวโพสต์** → สร้างโพสต์ → ตั้ง **เวลาโพสต์** เป็นอดีต/ตอนนี้ → กด **เข้าคิว**
2. รอ Task รอบถัดไป (หรือรันสคริปต์เอง) → สถานะจะเปลี่ยนเป็น **เผยแพร่แล้ว** อัตโนมัติ

## การเชื่อม API จริง (โหมด live)

### Facebook Page (พร้อมใช้แล้วในโค้ด)
1. สร้าง App ที่ https://developers.facebook.com → ขอสิทธิ์ `pages_manage_posts`, `pages_read_engagement`
2. ออก **Page Access Token** ของเพจ
3. หน้า **แพลตฟอร์ม & บัญชี** → เชื่อมบัญชี Facebook → กรอก **Page ID** (external_id) และ **Access Token**
4. ระบบจะโพสต์ผ่าน Graph API: `POST /{page-id}/feed`

### TikTok / Shopee / Lazada
โครงโค้ดเตรียมไว้ใน `publish_to_platform()` แล้ว แต่ละเจ้าต้อง:
- มี Developer App + ผ่านการรีวิวสิทธิ์โพสต์
- ใส่โค้ดเรียก API ในฟังก์ชัน publisher ของแพลตฟอร์มนั้น (จุด TODO ในไฟล์)

> ⚠️ Access Token เป็นความลับ — เก็บในบัญชีที่เชื่อมของแต่ละผู้ใช้ ระบบไม่แชร์ข้ามผู้ใช้

## สรุปไฟล์ที่เกี่ยวข้อง
| ไฟล์ | หน้าที่ |
|---|---|
| `cron/scheduler.php` | ตัวรัน หยิบคิว → เผยแพร่ → อัปเดตสถานะ + log |
| `cron/run-scheduler.bat` | ให้ Task Scheduler เรียก (Windows) |
| `includes/publishers.php` | ตรรกะเผยแพร่ต่อแพลตฟอร์ม (Facebook พร้อมใช้) |
| `cron/scheduler.log` | บันทึกผลการทำงานแต่ละรอบ |
