-- =====================================================================
--  AffiliateHub — Seed Data
--  Import ไฟล์นี้ "หลัง" schema.sql
--
--  บัญชีแอดมินเริ่มต้น:
--    email    : admin@affiliatehub.local
--    password : admin1234        (เปลี่ยนทันทีหลังล็อกอินครั้งแรก)
--
--  Access Code แรก (ให้ผู้ใช้คนแรกสมัคร): AFH-START-2026
-- =====================================================================

USE `affiliatehub`;

-- แพลตฟอร์มส่วนกลาง
INSERT INTO `platforms` (`code`,`name`,`color`) VALUES
  ('facebook','Facebook','#1877F2'),
  ('tiktok','TikTok','#000000'),
  ('shopee','Shopee','#EE4D2D'),
  ('lazada','Lazada','#0F146D')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- แอดมินเริ่มต้น (password = admin1234)
INSERT INTO `users` (`name`,`email`,`password_hash`,`role`,`label`,`is_active`)
VALUES ('ผู้ดูแลระบบ','admin@affiliatehub.local',
        '$2y$12$73S66Doe/gwGcb.9VxLT7uwAhU5oMnRkXmCMiAElU9r6df/6UmOvi',
        'admin','แอดมิน',1)
ON DUPLICATE KEY UPDATE `email`=VALUES(`email`);

-- Access Code แรก
INSERT INTO `access_codes` (`code`,`label`,`max_uses`,`used_count`,`expires_at`,`is_active`,`created_by`)
VALUES ('AFH-START-2026','ชุดเริ่มต้น',50,0,NULL,1,
        (SELECT id FROM users WHERE email='admin@affiliatehub.local' LIMIT 1))
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`);

-- สูตร seed เริ่มต้นของแอดมิน: "วิดีโอสั้น 10 วิ — 6 ซีนมาตรฐาน"
INSERT INTO `content_formulas`
  (`user_id`,`name`,`category`,`platforms`,`total_seconds`,`scene_count`,
   `overlay_style`,`audio_style`,`tone`,`notes`,`variables_json`,`is_default`,`is_active`,`version`)
SELECT u.id,'วิดีโอสั้น 10 วิ — 6 ซีนมาตรฐาน','วิดีโอสั้น','tiktok,facebook',10,6,
       'ตัวหนากลางจอ อ่านง่าย','เพลงจังหวะสนุก + Click ASMR','สนุก กระชับ ขายของ',
       'โครง 6 ซีน ซีนละ 1.5–2 วินาที ตามคู่มือ marketing.md §3.1',
       '{"product_name":"","price":"","usp":"","target":"","cta":"","platform":""}',
       1,1,1
FROM users u WHERE u.email='admin@affiliatehub.local' LIMIT 1;

-- 6 ซีนของสูตร seed
INSERT INTO `content_formula_scenes`
  (`formula_id`,`seq`,`time_from`,`time_to`,`description`,`camera_angle`,`lighting`,`overlay_text`)
SELECT f.id, s.seq, s.time_from, s.time_to, s.description, s.camera_angle, s.lighting, s.overlay_text
FROM content_formulas f
JOIN (
  SELECT 1 seq, 0.0 time_from, 1.5 time_to, 'เปิดตัวสินค้า วางกลางเฟรมให้เด่น' description, 'มุมตรง (eye-level)' camera_angle, 'สว่างนุ่ม' lighting, '{{product_name}}' overlay_text
  UNION ALL SELECT 2, 1.5, 3.5, 'มือหยิบจับ/เข้าใกล้ เห็นรายละเอียด', 'โคลสอัพ (close-up)', 'ไฟข้าง', 'จุดเด่น: {{usp}}'
  UNION ALL SELECT 3, 3.5, 5.5, 'สาธิตการใช้งาน (Click ASMR)', 'มุมสูง (top-down)', 'สปอตไลต์', 'ใช้ง่ายแค่กดเดียว'
  UNION ALL SELECT 4, 5.5, 7.0, 'ผลลัพธ์ before/after', 'แบ่งจอ (split)', 'สว่างเท่ากันสองฝั่ง', 'ต่างกันชัด!'
  UNION ALL SELECT 5, 7.0, 8.5, 'มุมไลฟ์สไตล์ ใช้จริงในชีวิตประจำวัน', 'มุมกว้าง (wide)', 'แสงธรรมชาติ', 'เหมาะกับ {{target}}'
  UNION ALL SELECT 6, 8.5, 10.0, 'CTA ปิดท้าย เร่งการตัดสินใจ', 'มุมตรง ซูมเข้า', 'สว่างเด่น', '{{cta}} • {{price}} บาท'
) s
WHERE f.user_id=(SELECT id FROM users WHERE email='admin@affiliatehub.local' LIMIT 1)
  AND f.is_default=1
LIMIT 6;
