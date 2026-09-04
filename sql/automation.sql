-- =====================================================================
--  AffiliateHub — ระบบโพสต์อัตโนมัติ (Automation Rules) — §11
--  ติดตั้งย้อนหลังบนฐานข้อมูลเดิม: phpMyAdmin → เลือก DB affiliatehub → Import ไฟล์นี้
--  (ฐานข้อมูลใหม่มีตารางเหล่านี้อยู่ใน schema.sql แล้ว)
--
--  ทุกตารางมี user_id และต้อง filter ด้วย user_id ทุก query
-- =====================================================================

SET NAMES utf8mb4;
USE `affiliatehub`;

-- กฎอัตโนมัติ
CREATE TABLE IF NOT EXISTS `automation_rules` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `name`           VARCHAR(160) NOT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  `trigger_type`   VARCHAR(40)  NOT NULL,
  `trigger_config` TEXT         DEFAULT NULL,   -- JSON
  `conditions`     TEXT         DEFAULT NULL,   -- JSON array
  `formula_id`     INT UNSIGNED NOT NULL,       -- ทุกกฎต้องผูกสูตร (§1)
  `actions`        TEXT         DEFAULT NULL,   -- JSON array
  `approval_mode`  ENUM('draft','review','auto') NOT NULL DEFAULT 'draft',
  `guardrails`     TEXT         DEFAULT NULL,   -- JSON (max_runs_per_day, cooldown_days_per_product)
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 0,
  `last_run_at`    DATETIME     DEFAULT NULL,
  `success_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `skip_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `fail_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `fail_streak`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rule_user` (`user_id`),
  KEY `idx_rule_trigger` (`user_id`,`trigger_type`,`is_active`),
  KEY `idx_rule_formula` (`formula_id`),
  CONSTRAINT `fk_rule_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rule_formula` FOREIGN KEY (`formula_id`) REFERENCES `content_formulas`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ทุกรอบการทำงานของกฎ (แม้ผลเป็น "ข้าม")
CREATE TABLE IF NOT EXISTS `automation_runs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `rule_id`    INT UNSIGNED NOT NULL,
  `status`     ENUM('success','skip','failed','dry') NOT NULL,
  `reason`     VARCHAR(255) DEFAULT NULL,
  `detail`     TEXT         DEFAULT NULL,       -- JSON
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_run_user` (`user_id`),
  KEY `idx_run_rule` (`rule_id`,`created_at`),
  CONSTRAINT `fk_run_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_run_rule` FOREIGN KEY (`rule_id`) REFERENCES `automation_rules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- คอนเทนต์ที่รออนุมัติ (โหมด Review)
CREATE TABLE IF NOT EXISTS `automation_approvals` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `rule_id`    INT UNSIGNED NOT NULL,
  `run_id`     INT UNSIGNED DEFAULT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `formula_id` INT UNSIGNED DEFAULT NULL,
  `platforms`  VARCHAR(120) DEFAULT NULL,       -- คั่นด้วยจุลภาค
  `caption`    TEXT         DEFAULT NULL,
  `storyboard` TEXT         DEFAULT NULL,
  `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_appr_user` (`user_id`,`status`),
  KEY `idx_appr_rule` (`rule_id`),
  CONSTRAINT `fk_appr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appr_rule` FOREIGN KEY (`rule_id`) REFERENCES `automation_rules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guardrails รายบัญชี + Kill Switch (§7)
CREATE TABLE IF NOT EXISTS `automation_settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `guardrails`  TEXT         DEFAULT NULL,      -- JSON
  `kill_switch` TINYINT(1)   NOT NULL DEFAULT 0,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_autoset_user` (`user_id`),
  CONSTRAINT `fk_autoset_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
