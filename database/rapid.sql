-- ============================================================
-- RAPID Repair System — Database Schema & Seed Data
-- Import via phpMyAdmin or: mysql -u root < rapid.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `rapid`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `rapid`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `warranty_claims`;
DROP TABLE IF EXISTS `warranties`;
DROP TABLE IF EXISTS `repair_status_history`;
DROP TABLE IF EXISTS `quotations`;
DROP TABLE IF EXISTS `diagnoses`;
DROP TABLE IF EXISTS `ai_suggestions`;
DROP TABLE IF EXISTS `device_media`;
DROP TABLE IF EXISTS `repair_tickets`;
DROP TABLE IF EXISTS `devices`;
DROP TABLE IF EXISTS `technicians`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` ENUM('admin', 'technician', 'customer') NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- customers
-- ------------------------------------------------------------
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_user` (`user_id`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- technicians
-- ------------------------------------------------------------
CREATE TABLE `technicians` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `specialization` VARCHAR(150) DEFAULT NULL,
  `availability_status` ENUM('available', 'busy', 'unavailable') NOT NULL DEFAULT 'available',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technicians_user` (`user_id`),
  KEY `idx_technicians_availability` (`availability_status`),
  CONSTRAINT `fk_technicians_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- devices
-- ------------------------------------------------------------
CREATE TABLE `devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `device_type` VARCHAR(100) NOT NULL,
  `brand` VARCHAR(100) NOT NULL,
  `model` VARCHAR(150) NOT NULL,
  `serial_number` VARCHAR(150) DEFAULT NULL,
  `imei` VARCHAR(50) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `accessories` TEXT DEFAULT NULL,
  `physical_condition` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_devices_customer` (`customer_id`),
  KEY `idx_devices_brand_model` (`brand`, `model`),
  CONSTRAINT `fk_devices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- repair_tickets
-- ------------------------------------------------------------
CREATE TABLE `repair_tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(30) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `assigned_technician_id` INT UNSIGNED DEFAULT NULL,
  `problem_description` TEXT NOT NULL,
  `current_status` ENUM(
    'booking_submitted',
    'received',
    'diagnosing',
    'quotation_pending',
    'awaiting_approval',
    'approved',
    'repairing',
    'ready_for_pickup',
    'completed',
    'declined',
    'cancelled'
  ) NOT NULL DEFAULT 'booking_submitted',
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  `appointment_date` DATETIME DEFAULT NULL,
  `received_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `estimated_completion` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_number` (`ticket_number`),
  KEY `idx_tickets_customer` (`customer_id`),
  KEY `idx_tickets_device` (`device_id`),
  KEY `idx_tickets_technician` (`assigned_technician_id`),
  KEY `idx_tickets_status` (`current_status`),
  KEY `idx_tickets_priority` (`priority`),
  KEY `idx_tickets_created` (`created_at`),
  CONSTRAINT `fk_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tickets_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tickets_technician` FOREIGN KEY (`assigned_technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- device_media
-- ------------------------------------------------------------
CREATE TABLE `device_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_type` VARCHAR(100) NOT NULL,
  `media_category` ENUM('before_repair', 'diagnosis', 'during_repair', 'after_repair') NOT NULL DEFAULT 'before_repair',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_ticket` (`ticket_id`),
  KEY `idx_media_uploader` (`uploaded_by`),
  KEY `idx_media_category` (`media_category`),
  CONSTRAINT `fk_media_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- diagnoses
-- ------------------------------------------------------------
CREATE TABLE `diagnoses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `technician_id` INT UNSIGNED NOT NULL,
  `diagnosis` TEXT NOT NULL,
  `recommended_action` TEXT DEFAULT NULL,
  `estimated_completion` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_diagnoses_ticket` (`ticket_id`),
  KEY `idx_diagnoses_technician` (`technician_id`),
  CONSTRAINT `fk_diagnoses_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_diagnoses_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ai_suggestions (cached Gemini repair / helper output per ticket)
-- ------------------------------------------------------------
CREATE TABLE `ai_suggestions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(40) NOT NULL DEFAULT 'technician_repair',
  `context_hash` CHAR(64) NOT NULL,
  `payload_json` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_ticket_kind` (`ticket_id`, `kind`),
  KEY `idx_ai_kind` (`kind`),
  CONSTRAINT `fk_ai_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- quotations
-- ------------------------------------------------------------
CREATE TABLE `quotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `technician_id` INT UNSIGNED NOT NULL,
  `labor_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `parts_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `other_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'declined', 'expired') NOT NULL DEFAULT 'pending',
  `valid_until` DATE DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quotations_ticket` (`ticket_id`),
  KEY `idx_quotations_technician` (`technician_id`),
  KEY `idx_quotations_status` (`status`),
  CONSTRAINT `fk_quotations_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quotations_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- repair_status_history
-- ------------------------------------------------------------
CREATE TABLE `repair_status_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `status` ENUM(
    'booking_submitted',
    'received',
    'diagnosing',
    'quotation_pending',
    'awaiting_approval',
    'approved',
    'repairing',
    'ready_for_pickup',
    'completed',
    'declined',
    'cancelled'
  ) NOT NULL,
  `remarks` TEXT DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_history_ticket` (`ticket_id`),
  KEY `idx_status_history_status` (`status`),
  KEY `idx_status_history_created` (`created_at`),
  CONSTRAINT `fk_status_history_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_status_history_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- warranties
-- ------------------------------------------------------------
CREATE TABLE `warranties` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `warranty_start` DATE NOT NULL,
  `warranty_end` DATE NOT NULL,
  `warranty_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `warranty_status` ENUM('active', 'expiring_soon', 'expired') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_warranties_ticket` (`ticket_id`),
  KEY `idx_warranties_status` (`warranty_status`),
  KEY `idx_warranties_end` (`warranty_end`),
  CONSTRAINT `fk_warranties_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- warranty_claims
-- ------------------------------------------------------------
CREATE TABLE `warranty_claims` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_ticket_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `issue_description` TEXT NOT NULL,
  `claim_status` ENUM('submitted', 'reviewing', 'approved', 'rejected', 'repairing', 'resolved') NOT NULL DEFAULT 'submitted',
  `technician_id` INT UNSIGNED DEFAULT NULL,
  `resolution` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_claims_ticket` (`original_ticket_id`),
  KEY `idx_claims_customer` (`customer_id`),
  KEY `idx_claims_device` (`device_id`),
  KEY `idx_claims_technician` (`technician_id`),
  KEY `idx_claims_status` (`claim_status`),
  CONSTRAINT `fk_claims_ticket` FOREIGN KEY (`original_ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_claims_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_claims_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_claims_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `ticket_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_ticket` (`ticket_id`),
  KEY `idx_notifications_read` (`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- activity_logs
-- ------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- system_settings
-- ------------------------------------------------------------
CREATE TABLE `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Minimal seed (enough to log in)
-- For the full demo dataset (all statuses, quotes, warranties, claims),
-- import database/seed.sql after this file — it replaces these rows.
-- Passwords (bcrypt):
--   Admin@123     -> admin@rapid.local
--   Tech@123      -> tech@rapid.local
--   Customer@123  -> customer@rapid.local
-- ============================================================

INSERT INTO `users` (`id`, `role`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`) VALUES
(1, 'admin', 'System', 'Administrator', 'admin@rapid.local', '09170000001', '$2y$10$UrsDoXI218mj6wixKcX0x.xcnwRjWlweTllWAT5DwbeHpotSllkAy', 'active'),
(2, 'technician', 'Marco', 'Reyes', 'tech@rapid.local', '09170000002', '$2y$10$mnF3EpQt6ocPl/IohHLbZOOndrnlpgcnB1dkVmZHrJF8c/1wyLRAO', 'active'),
(3, 'customer', 'Ana', 'Santos', 'customer@rapid.local', '09170000003', '$2y$10$VvY5jJGeM/0eLHECz/4RZ.LvCCeg4d/2XJmWZowCQerzURMn51Klu', 'active');

INSERT INTO `technicians` (`id`, `user_id`, `specialization`, `availability_status`) VALUES
(1, 2, 'Smartphones & Tablets', 'available');

INSERT INTO `customers` (`id`, `user_id`, `address`) VALUES
(1, 3, '123 Mabini Street, Quezon City');

INSERT INTO `devices` (`id`, `customer_id`, `device_type`, `brand`, `model`, `serial_number`, `imei`, `color`, `accessories`, `physical_condition`) VALUES
(1, 1, 'Smartphone', 'Samsung', 'Galaxy A54', 'SN-A54-DEMO-001', '356938035643809', 'Black', 'Charger, SIM tray pin', 'Light scratches on back cover; screen intact');

INSERT INTO `repair_tickets` (
  `id`, `ticket_number`, `customer_id`, `device_id`, `assigned_technician_id`,
  `problem_description`, `current_status`, `priority`, `appointment_date`,
  `received_at`, `estimated_completion`
) VALUES (
  1,
  'RPR-2026-000001',
  1,
  1,
  1,
  'Device does not charge when plugged in. Battery drains quickly even when idle.',
  'received',
  'normal',
  '2026-09-04 10:00:00',
  '2026-09-04 10:15:00',
  '2026-09-06 17:00:00'
);

INSERT INTO `repair_status_history` (`ticket_id`, `status`, `remarks`, `updated_by`, `created_at`) VALUES
(1, 'booking_submitted', 'Online booking submitted by customer.', 3, '2026-09-04 09:30:00'),
(1, 'received', 'Device received at service counter.', 1, '2026-09-04 10:15:00');

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'RAPID Device Care'),
('shop_phone', '09101495174'),
('shop_email', 'support@rapid.local'),
('shop_hours', 'Mon–Sat, 9:00 AM – 6:00 PM'),
('shop_address', 'Esposado, Cannery Site, Polomolok, South Cotabato 9505'),
('warranty_days', '30'),
('ticket_prefix', 'RPR'),
('ai_enabled', '1'),
('ai_gemini_model', 'gemini-flash-lite-latest'),
('ai_gemini_api_key', '');
