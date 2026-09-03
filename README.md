# AffiliateHub Skill

Skill สำหรับ Claude — คู่มือติดตั้งและใช้งานระบบ **AffiliateHub**
(การตลาดหลายแพลตฟอร์ม + ไลฟ์ขายของ + จัดส่ง/COD ในระบบเดียว)

**เวอร์ชันปัจจุบัน:** `v1.2.0` · อัปเดตล่าสุด **3 ก.ย. 2026**

> ไฟล์นี้มีไว้ให้ **คนอ่าน** ไม่ใช่ให้ Claude โหลด
> Claude อ่านจาก `SKILL.md` เป็นหลัก แล้วค่อยเปิดไฟล์ใน `references/` ตามที่ระบุ

---

## โครงสร้างโฟลเดอร์

```
affiliatehub/
├── README.md                        ← ไฟล์นี้ (สำหรับคน)
├── SKILL.md                         ← ประตูหน้า Claude อ่านก่อนเสมอ
└── references/
    ├── installation.md
    ├── onboarding.md
    ├── marketing.md
    ├── sales-fulfillment.md
    └── troubleshooting.md
```

---

## ไฟล์ไหนทำอะไร

| ไฟล์ | หน้าที่ | เปิดเมื่อ |
|---|---|---|
| `SKILL.md` | Quick Reference + แผนผังโมดูล + กติกาการตอบ + Auto-Install Mode | Claude อ่านทุกครั้ง |
| `references/installation.md` | สคริปต์ติดตั้ง Phase 0–5 + ชุด Prompt 4.1–4.7 + Smoke Test 12 ข้อ | ผู้ใช้สั่ง "ติดตั้ง" |
| `references/onboarding.md` | สมัคร / ล็อกอิน / Access Code / สิทธิ์ผู้ใช้ | ถามเรื่องเข้าระบบ |
| `references/marketing.md` | เชื่อมบัญชี, ตารางโพสต์, AI คิดคอนเทนต์, **คลังสูตร**, Boost, ไลฟ์ | ถามเรื่องการตลาด/คอนเทนต์ |
| `references/sales-fulfillment.md` | สินค้า, ออเดอร์, กำไร, จัดส่ง, COD, รอบรถ, ตีกลับ | ถามเรื่องขาย/ส่งของ |
| `references/troubleshooting.md` | แก้ปัญหา §1–§12 ตั้งแต่ติดตั้งถึงใช้งานจริง | เจอ error |

---

## หัวข้อที่ถูกเปิดบ่อยสุด

| อยากได้ | ไปที่ |
|---|---|
| Prompt วิดีโอสั้น 10 วิ | `marketing.md` §3.1 |
| ตัวอย่าง Prompt เต็ม (Omni Flash) | `marketing.md` §3.2 |
| บันทึก/แก้สูตรคอนเทนต์เอง | `marketing.md` §3.3 |
| AI ควรเลือกสูตรไหน | `marketing.md` §3.4 |
| ชุด Prompt สร้างระบบ | `installation.md` Phase 4 |
| ติดตั้งคลังสูตรย้อนหลัง | `installation.md` ภาคผนวก C |
| ตัวแปร `{{ }}` ไม่เติมค่า | `troubleshooting.md` §6.1 |

---

## ประวัติเวอร์ชัน

| เวอร์ชัน | วันที่ | เปลี่ยนอะไร | ไฟล์ที่แตะ |
|---|---|---|---|
| **v1.2.0** | 3 ก.ย. 2026 | เพิ่มฟีเจอร์ **คลังสูตรของฉัน** (§3.3–3.4), Prompt 4.4b, 3 ตารางใหม่, Smoke Test 7–12, แก้ปัญหาคลังสูตร §6 (13 อาการ) | `SKILL.md`, `installation.md`, `marketing.md`, `troubleshooting.md` |
| v1.1.0 | — | เพิ่มสูตร Storyboard 10 วิ 6 ซีน + ตัวอย่าง Omni Flash | `SKILL.md`, `marketing.md` |
| v1.0.0 | — | เวอร์ชันแรก: ติดตั้ง, onboarding, การตลาด, ขาย/จัดส่ง, แก้ปัญหา | ทั้งหมด |

---

## ฐานข้อมูลที่เพิ่มใน v1.2.0

```
content_formulas          ← สูตรหลัก (มี version + parent_id)
content_formula_scenes    ← ซีนในสูตร
content_formula_usages    ← สถิติการใช้งาน
```
ทุกตารางมี `user_id` และต้อง filter ด้วย `user_id` ทุก query

---

## กติกาแก้ไข Skill นี้

1. แก้เนื้อหาใน `references/` → **ต้องอัปเดต Quick Reference ใน `SKILL.md` ด้วยเสมอ**
2. เพิ่มฟีเจอร์ที่มีตารางใหม่ → เพิ่มใน `installation.md` **3 จุด**: Prompt 4.1, Prompt เฉพาะฟีเจอร์, Smoke Test
3. เพิ่มฟีเจอร์แล้ว → เพิ่มหัวข้อแก้ปัญหาใน `troubleshooting.md` ด้วย
4. ส่งไฟล์ให้ผู้ใช้ = **ส่งเต็มทั้งไฟล์เสมอ** ห้ามส่งเฉพาะส่วนที่แก้ ห้ามใช้ `...`
5. อัปเดตตารางประวัติเวอร์ชันในไฟล์นี้ทุกครั้ง

---

## เริ่มใช้งาน

พิมพ์คุยกับ Claude ได้เลย เช่น
- `ติดตั้ง AffiliateHub` → เข้าโหมดติดตั้งอัตโนมัติ
- `ขอ Prompt วิดีโอสั้น 10 วิ ให้ไฟฉาย Omni Flash`
- `บันทึกสูตรนี้ไว้ในคลัง`
- `Apache start ไม่ขึ้น แก้ยังไง`

**หลังติดตั้งเสร็จ:** `http://localhost/affiliatehub` → สมัครด้วย Access Code แรก