-- =====================================================================
--  AffiliateHub — Database Schema
--  Charset: utf8mb4 / utf8mb4_unicode_ci (รองรับภาษาไทย + อิโมจิ)
--  หลักการ: ทุกตารางที่เก็บข้อมูลผู้ใช้ต้องมี user_id เพื่อแยกข้อมูล 100%
--
--  วิธีติดตั้ง:
--    1) เปิด http://localhost/phpmyadmin
--    2) สร้างฐานข้อมูลชื่อ  affiliatehub  (collation: utf8mb4_unicode_ci)
--    3) เลือกฐานข้อมูลนั้น แล้ว Import ไฟล์นี้
--    4) Import ต่อด้วย sql/seed.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `affiliatehub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `affiliatehub`;

-- ---------------------------------------------------------------------
-- ผู้ใช้ระบบ
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120)  NOT NULL,
  `email`         VARCHAR(190)  NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `role`          ENUM('admin','user') NOT NULL DEFAULT 'user',
  `label`         VARCHAR(80)   DEFAULT NULL,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `reset_token`   VARCHAR(64)   DEFAULT NULL,
  `reset_expires` DATETIME      DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- รหัสเข้าใช้งาน (Access Code) — ออกโดยแอดมิน
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_codes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40)  NOT NULL,
  `label`       VARCHAR(80)  DEFAULT NULL,
  `max_uses`    INT UNSIGNED NOT NULL DEFAULT 1,
  `used_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`  DATE         DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_access_code` (`code`),
  KEY `idx_access_active` (`is_active`),
  CONSTRAINT `fk_access_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- แพลตฟอร์ม (lookup ส่วนกลาง) + บัญชีที่ผู้ใช้เชื่อม
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `platforms` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`   VARCHAR(20)  NOT NULL,
  `name`   VARCHAR(50)  NOT NULL,
  `color`  VARCHAR(20)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platform_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_accounts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `platform_id`   INT UNSIGNED NOT NULL,
  `account_name`  VARCHAR(120) NOT NULL,
  `external_id`   VARCHAR(120) DEFAULT NULL,
  `access_token`  VARCHAR(255) DEFAULT NULL,
  `is_connected`  TINYINT(1)   NOT NULL DEFAULT 1,
  `connected_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pa_user` (`user_id`),
  KEY `idx_pa_platform` (`platform_id`),
  CONSTRAINT `fk_pa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- หมวดหมู่ + สินค้า
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   INT UNSIGNED NOT NULL,
  `name`      VARCHAR(100) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_user` (`user_id`),
  CONSTRAINT `fk_cat_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(160) NOT NULL,
  `sku`         VARCHAR(60)  DEFAULT NULL,
  `price`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock`       INT          NOT NULL DEFAULT 0,
  `image_url`   VARCHAR(255) DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_user` (`user_id`),
  KEY `idx_prod_cat` (`category_id`),
  CONSTRAINT `fk_prod_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ไลฟ์สด + สถิติ
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `live_sessions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `platform_id` INT UNSIGNED DEFAULT NULL,
  `title`       VARCHAR(160) NOT NULL,
  `started_at`  DATETIME     DEFAULT NULL,
  `ended_at`    DATETIME     DEFAULT NULL,
  `peak_viewers` INT         NOT NULL DEFAULT 0,
  `total_sales` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes`       TEXT         DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_live_user` (`user_id`),
  CONSTRAINT `fk_live_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `live_stats` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `live_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `minute_mark` INT         NOT NULL DEFAULT 0,
  `viewers`    INT          NOT NULL DEFAULT 0,
  `orders`     INT          NOT NULL DEFAULT 0,
  `product_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ls_live` (`live_id`),
  KEY `idx_ls_user` (`user_id`),
  CONSTRAINT `fk_ls_live` FOREIGN KEY (`live_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ls_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- โพสต์ + ตารางเวลา + AI คอนเทนต์
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `platform_id` INT UNSIGNED DEFAULT NULL,
  `product_id`  INT UNSIGNED DEFAULT NULL,
  `formula_id`  INT UNSIGNED DEFAULT NULL,
  `title`       VARCHAR(200) DEFAULT NULL,
  `caption`     TEXT         DEFAULT NULL,
  `media_type`  ENUM('image','video','carousel','text') NOT NULL DEFAULT 'image',
  `status`      ENUM('draft','queued','published','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at` DATETIME    DEFAULT NULL,
  `published_at` DATETIME    DEFAULT NULL,
  `reach`       INT          NOT NULL DEFAULT 0,
  `clicks`      INT          NOT NULL DEFAULT 0,
  `engagement`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_user` (`user_id`),
  KEY `idx_post_status` (`user_id`,`status`),
  KEY `idx_post_formula` (`formula_id`),
  CONSTRAINT `fk_post_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_post_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_schedules` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `platform_id` INT UNSIGNED DEFAULT NULL,
  `day_of_week` TINYINT      NOT NULL DEFAULT 1,  -- 0=อาทิตย์ ... 6=เสาร์
  `post_time`   TIME         NOT NULL DEFAULT '18:00:00',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sched_user` (`user_id`),
  CONSTRAINT `fk_sched_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sched_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_contents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED DEFAULT NULL,
  `formula_id`  INT UNSIGNED DEFAULT NULL,
  `prompt`      TEXT         DEFAULT NULL,
  `caption`     TEXT         DEFAULT NULL,
  `storyboard`  TEXT         DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_user` (`user_id`),
  CONSTRAINT `fk_ai_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- คลังสูตรของฉัน (v1.2.0)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `content_formulas` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `name`          VARCHAR(160) NOT NULL,
  `category`      VARCHAR(60)  DEFAULT NULL,
  `platforms`     VARCHAR(120) DEFAULT NULL,   -- คั่นด้วยจุลภาค เช่น "facebook,tiktok"
  `total_seconds` INT          NOT NULL DEFAULT 10,
  `scene_count`   INT          NOT NULL DEFAULT 6,
  `overlay_style` VARCHAR(120) DEFAULT NULL,
  `audio_style`   VARCHAR(120) DEFAULT NULL,
  `tone`          VARCHAR(120) DEFAULT NULL,
  `notes`         TEXT         DEFAULT NULL,
  `variables_json` TEXT        DEFAULT NULL,
  `is_default`    TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `version`       INT          NOT NULL DEFAULT 1,
  `parent_id`     INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_formula_user_cat` (`user_id`,`category`),
  KEY `idx_formula_parent` (`parent_id`),
  CONSTRAINT `fk_formula_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_formula_scenes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formula_id`  INT UNSIGNED NOT NULL,
  `seq`         INT          NOT NULL DEFAULT 1,
  `time_from`   DECIMAL(5,1) NOT NULL DEFAULT 0.0,
  `time_to`     DECIMAL(5,1) NOT NULL DEFAULT 0.0,
  `description` TEXT         DEFAULT NULL,
  `camera_angle` VARCHAR(120) DEFAULT NULL,
  `lighting`    VARCHAR(120) DEFAULT NULL,
  `overlay_text` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scene_formula_seq` (`formula_id`,`seq`),
  CONSTRAINT `fk_scene_formula` FOREIGN KEY (`formula_id`) REFERENCES `content_formulas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_formula_usages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formula_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `post_id`     INT UNSIGNED DEFAULT NULL,
  `product_id`  INT UNSIGNED DEFAULT NULL,
  `reach`       INT          NOT NULL DEFAULT 0,
  `engagement`  INT          NOT NULL DEFAULT 0,
  `ctr`         DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `linked_sales` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `used_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usage_formula_used` (`formula_id`,`used_at`),
  KEY `idx_usage_user` (`user_id`),
  CONSTRAINT `fk_usage_formula` FOREIGN KEY (`formula_id`) REFERENCES `content_formulas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- คำสั่งซื้อ + รายการสินค้า
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `order_code`    VARCHAR(40)  NOT NULL,
  `customer_name` VARCHAR(160) DEFAULT NULL,
  `customer_phone` VARCHAR(40) DEFAULT NULL,
  `address`       TEXT         DEFAULT NULL,
  `source_type`   ENUM('post','live','manual') NOT NULL DEFAULT 'manual',
  `source_post_id` INT UNSIGNED DEFAULT NULL,
  `source_live_id` INT UNSIGNED DEFAULT NULL,
  `subtotal`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_total`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_fee`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `profit`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_type`  ENUM('prepaid','cod') NOT NULL DEFAULT 'cod',
  `status`        ENUM('new','packed','shipped','delivered','returned','cancelled') NOT NULL DEFAULT 'new',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_code` (`user_id`,`order_code`),
  KEY `idx_order_user` (`user_id`),
  KEY `idx_order_status` (`user_id`,`status`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `name`       VARCHAR(160) NOT NULL,
  `qty`        INT          NOT NULL DEFAULT 1,
  `price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_user` (`user_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- จัดส่ง + COD + รอบรถ + คนขับ + ตีกลับ
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `drivers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `phone`      VARCHAR(40)  DEFAULT NULL,
  `vehicle`    VARCHAR(80)  DEFAULT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_driver_user` (`user_id`),
  CONSTRAINT `fk_driver_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pickup_rounds` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `driver_id`  INT UNSIGNED DEFAULT NULL,
  `round_date` DATE         NOT NULL,
  `manifest_code` VARCHAR(40) DEFAULT NULL,
  `parcel_count` INT        NOT NULL DEFAULT 0,
  `cod_total`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`     ENUM('open','handed','settled') NOT NULL DEFAULT 'open',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_round_user` (`user_id`),
  CONSTRAINT `fk_round_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_round_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `order_id`      INT UNSIGNED NOT NULL,
  `pickup_round_id` INT UNSIGNED DEFAULT NULL,
  `tracking_no`   VARCHAR(60)  DEFAULT NULL,
  `carrier`       VARCHAR(60)  DEFAULT NULL,
  `status`        ENUM('preparing','picked_up','in_transit','delivered','failed') NOT NULL DEFAULT 'preparing',
  `shipped_at`    DATETIME     DEFAULT NULL,
  `delivered_at`  DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ship_user` (`user_id`),
  KEY `idx_ship_order` (`order_id`),
  CONSTRAINT `fk_ship_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ship_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ship_round` FOREIGN KEY (`pickup_round_id`) REFERENCES `pickup_rounds`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cod_records` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `order_id`    INT UNSIGNED NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `collected`   TINYINT(1)   NOT NULL DEFAULT 0,
  `remitted`    TINYINT(1)   NOT NULL DEFAULT 0,
  `remitted_at` DATETIME     DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cod_user` (`user_id`),
  KEY `idx_cod_order` (`order_id`),
  CONSTRAINT `fk_cod_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cod_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `returns` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `order_id`    INT UNSIGNED DEFAULT NULL,
  `product_id`  INT UNSIGNED DEFAULT NULL,
  `reason`      VARCHAR(200) DEFAULT NULL,
  `qty`         INT          NOT NULL DEFAULT 1,
  `restocked`   TINYINT(1)   NOT NULL DEFAULT 0,
  `resell_status` ENUM('none','listed','sold') NOT NULL DEFAULT 'none',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_return_user` (`user_id`),
  CONSTRAINT `fk_return_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_return_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_return_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ตั้งค่า (คีย์-ค่า ต่อผู้ใช้) — เก็บ API Key ฯลฯ
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `skey`       VARCHAR(80)  NOT NULL,
  `svalue`     TEXT         DEFAULT NULL,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting` (`user_id`,`skey`),
  CONSTRAINT `fk_setting_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
