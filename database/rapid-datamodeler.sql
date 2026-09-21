-- ============================================================
-- RAPID Repair System — Logical schema for Data Modeler
-- Source of truth: database/rapid.sql (tables and foreign keys)
--
-- Import:
--   Oracle SQL Developer Data Modeler
--     File > Import > DDL File > Database Type: MySQL > this file
--   MySQL Workbench
--     File > Import > Reverse Engineer MySQL Create Script > this file
-- ============================================================

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the user',
  `role` ENUM('admin', 'technician', 'customer') NOT NULL COMMENT 'Role of the user in the RAPID system',
  `first_name` VARCHAR(100) NOT NULL COMMENT 'First name of the user',
  `last_name` VARCHAR(100) NOT NULL COMMENT 'Last name of the user',
  `email` VARCHAR(190) NOT NULL COMMENT 'Email address used to log in',
  `phone` VARCHAR(30) DEFAULT NULL COMMENT 'Contact number of the user',
  `password` VARCHAR(255) NOT NULL COMMENT 'Hashed password of the user',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'Account status of the user',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the user was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the user was last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login accounts for administrators, technicians, and customers';

CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the customer',
  `user_id` INT UNSIGNED NOT NULL COMMENT 'Linked user account of the customer',
  `address` TEXT DEFAULT NULL COMMENT 'Address of the customer',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the customer was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the customer was last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_user` (`user_id`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer profile linked to a user account';

CREATE TABLE `technicians` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the technician',
  `user_id` INT UNSIGNED NOT NULL COMMENT 'Linked user account of the technician',
  `specialization` VARCHAR(150) DEFAULT NULL COMMENT 'Repair specialization of the technician',
  `availability_status` ENUM('available', 'busy', 'unavailable') NOT NULL DEFAULT 'available' COMMENT 'Current availability of the technician',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the technician was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the technician was last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technicians_user` (`user_id`),
  KEY `idx_technicians_availability` (`availability_status`),
  CONSTRAINT `fk_technicians_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Technician profile linked to a user account';

CREATE TABLE `devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the device',
  `customer_id` INT UNSIGNED NOT NULL COMMENT 'Owner of the device',
  `device_type` VARCHAR(100) NOT NULL COMMENT 'Type of the device',
  `brand` VARCHAR(100) NOT NULL COMMENT 'Brand of the device',
  `model` VARCHAR(150) NOT NULL COMMENT 'Model of the device',
  `serial_number` VARCHAR(150) DEFAULT NULL COMMENT 'Serial number of the device',
  `imei` VARCHAR(50) DEFAULT NULL COMMENT 'IMEI of the device',
  `color` VARCHAR(50) DEFAULT NULL COMMENT 'Color of the device',
  `accessories` TEXT DEFAULT NULL COMMENT 'Accessories received with the device',
  `physical_condition` TEXT DEFAULT NULL COMMENT 'Physical condition of the device',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the device was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the device was last updated',
  PRIMARY KEY (`id`),
  KEY `idx_devices_customer` (`customer_id`),
  KEY `idx_devices_brand_model` (`brand`, `model`),
  CONSTRAINT `fk_devices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer devices submitted for repair';

CREATE TABLE `repair_tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the repair ticket',
  `ticket_number` VARCHAR(30) NOT NULL COMMENT 'Public tracking number of the ticket',
  `customer_id` INT UNSIGNED NOT NULL COMMENT 'Customer who requested the repair',
  `device_id` INT UNSIGNED NOT NULL COMMENT 'Device attached to the ticket',
  `assigned_technician_id` INT UNSIGNED DEFAULT NULL COMMENT 'Technician assigned to the ticket',
  `problem_description` TEXT NOT NULL COMMENT 'Reported problem of the device',
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
  ) NOT NULL DEFAULT 'booking_submitted' COMMENT 'Current repair status of the ticket',
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal' COMMENT 'Priority level of the ticket',
  `appointment_date` DATETIME DEFAULT NULL COMMENT 'Scheduled appointment date of the ticket',
  `received_at` DATETIME DEFAULT NULL COMMENT 'Date and time the device was received',
  `completed_at` DATETIME DEFAULT NULL COMMENT 'Date and time the repair was completed',
  `estimated_completion` DATETIME DEFAULT NULL COMMENT 'Estimated completion date of the repair',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the ticket was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the ticket was last updated',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Central repair job record for a customer device';

CREATE TABLE `device_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the media file',
  `ticket_id` INT UNSIGNED NOT NULL COMMENT 'Repair ticket of the media file',
  `uploaded_by` INT UNSIGNED NOT NULL COMMENT 'User who uploaded the media file',
  `file_name` VARCHAR(255) NOT NULL COMMENT 'Original file name of the media',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Stored path of the media file',
  `file_type` VARCHAR(100) NOT NULL COMMENT 'MIME type of the media file',
  `media_category` ENUM('before_repair', 'diagnosis', 'during_repair', 'after_repair') NOT NULL DEFAULT 'before_repair' COMMENT 'Stage of the repair when the media was taken',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the media was uploaded',
  PRIMARY KEY (`id`),
  KEY `idx_media_ticket` (`ticket_id`),
  KEY `idx_media_uploader` (`uploaded_by`),
  KEY `idx_media_category` (`media_category`),
  CONSTRAINT `fk_media_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Photos and files attached to a repair ticket';

CREATE TABLE `diagnoses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the diagnosis',
  `ticket_id` INT UNSIGNED NOT NULL COMMENT 'Repair ticket of the diagnosis',
  `technician_id` INT UNSIGNED NOT NULL COMMENT 'Technician who recorded the diagnosis',
  `diagnosis` TEXT NOT NULL COMMENT 'Diagnosis findings of the device',
  `recommended_action` TEXT DEFAULT NULL COMMENT 'Recommended repair action',
  `estimated_completion` DATETIME DEFAULT NULL COMMENT 'Estimated completion after diagnosis',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the diagnosis was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the diagnosis was last updated',
  PRIMARY KEY (`id`),
  KEY `idx_diagnoses_ticket` (`ticket_id`),
  KEY `idx_diagnoses_technician` (`technician_id`),
  CONSTRAINT `fk_diagnoses_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_diagnoses_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Technician diagnosis recorded for a repair ticket';

CREATE TABLE `ai_suggestions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the AI suggestion',
  `ticket_id` INT UNSIGNED NOT NULL COMMENT 'Repair ticket of the AI suggestion',
  `kind` VARCHAR(40) NOT NULL DEFAULT 'technician_repair' COMMENT 'Type of AI suggestion stored',
  `context_hash` CHAR(64) NOT NULL COMMENT 'Hash of the ticket context used for cache',
  `payload_json` MEDIUMTEXT NOT NULL COMMENT 'JSON payload of the AI suggestion',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the suggestion was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the suggestion was last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_ticket_kind` (`ticket_id`, `kind`),
  KEY `idx_ai_kind` (`kind`),
  CONSTRAINT `fk_ai_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached AI helper output for a repair ticket';

CREATE TABLE `quotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the quotation',
  `ticket_id` INT UNSIGNED NOT NULL COMMENT 'Repair ticket of the quotation',
  `technician_id` INT UNSIGNED NOT NULL COMMENT 'Technician who prepared the quotation',
  `labor_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Labor cost of the repair',
  `parts_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Parts cost of the repair',
  `other_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Other cost of the repair',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount of the quotation',
  `notes` TEXT DEFAULT NULL COMMENT 'Additional notes of the quotation',
  `status` ENUM('pending', 'approved', 'declined', 'expired') NOT NULL DEFAULT 'pending' COMMENT 'Approval status of the quotation',
  `valid_until` DATE DEFAULT NULL COMMENT 'Expiry date of the quotation',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the quotation was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the quotation was last updated',
  PRIMARY KEY (`id`),
  KEY `idx_quotations_ticket` (`ticket_id`),
  KEY `idx_quotations_technician` (`technician_id`),
  KEY `idx_quotations_status` (`status`),
  CONSTRAINT `fk_quotations_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quotations_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Repair cost quotation for a ticket';

CREATE TABLE `repair_status_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the status history record',
  `ticket_id` INT UNSIGNED NOT NULL COMMENT 'Repair ticket of the status change',
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
  ) NOT NULL COMMENT 'Status value recorded in the timeline',
  `remarks` TEXT DEFAULT NULL COMMENT 'Remarks of the status change',
  `updated_by` INT UNSIGNED DEFAULT NULL COMMENT 'User who recorded the status change',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the status was recorded',
  PRIMARY KEY (`id`),
  KEY `idx_status_history_ticket` (`ticket_id`),
  KEY `idx_status_history_status` (`status`),
  KEY `idx_status_history_created` (`created_at`),
  CONSTRAINT `fk_status_history_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_status_history_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Timeline of status changes for a repair ticket';

CREATE TABLE `warranties` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the warranty',
  `ticket_id` INT UNSIGNED NOT NULL COMMENT 'Completed repair ticket of the warranty',
  `warranty_start` DATE NOT NULL COMMENT 'Start date of the warranty',
  `warranty_end` DATE NOT NULL COMMENT 'End date of the warranty',
  `warranty_days` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Coverage length of the warranty in days',
  `warranty_status` ENUM('active', 'expiring_soon', 'expired') NOT NULL DEFAULT 'active' COMMENT 'Current status of the warranty',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the warranty was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the warranty was last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_warranties_ticket` (`ticket_id`),
  KEY `idx_warranties_status` (`warranty_status`),
  KEY `idx_warranties_end` (`warranty_end`),
  CONSTRAINT `fk_warranties_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warranty coverage issued after a completed repair';

CREATE TABLE `warranty_claims` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the warranty claim',
  `original_ticket_id` INT UNSIGNED NOT NULL COMMENT 'Original repair ticket of the claim',
  `customer_id` INT UNSIGNED NOT NULL COMMENT 'Customer who filed the claim',
  `device_id` INT UNSIGNED NOT NULL COMMENT 'Device covered by the claim',
  `issue_description` TEXT NOT NULL COMMENT 'Reported issue of the warranty claim',
  `claim_status` ENUM('submitted', 'reviewing', 'approved', 'rejected', 'repairing', 'resolved') NOT NULL DEFAULT 'submitted' COMMENT 'Current status of the warranty claim',
  `technician_id` INT UNSIGNED DEFAULT NULL COMMENT 'Technician assigned to the claim',
  `resolution` TEXT DEFAULT NULL COMMENT 'Resolution notes of the claim',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the claim was created',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the claim was last updated',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warranty claim filed against a previous repair';

CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the notification',
  `user_id` INT UNSIGNED NOT NULL COMMENT 'User who receives the notification',
  `ticket_id` INT UNSIGNED DEFAULT NULL COMMENT 'Related repair ticket of the notification',
  `title` VARCHAR(200) NOT NULL COMMENT 'Title of the notification',
  `message` TEXT NOT NULL COMMENT 'Message of the notification',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Read flag of the notification',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the notification was created',
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_ticket` (`ticket_id`),
  KEY `idx_notifications_read` (`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='In-app notifications sent to users';

CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the activity log',
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'User who performed the action',
  `action` VARCHAR(100) NOT NULL COMMENT 'Action name of the log',
  `description` TEXT DEFAULT NULL COMMENT 'Details of the activity',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the activity',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time the activity was recorded',
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail of user and system actions';

CREATE TABLE `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID of the setting',
  `setting_key` VARCHAR(100) NOT NULL COMMENT 'Key name of the setting',
  `setting_value` TEXT DEFAULT NULL COMMENT 'Value of the setting',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time the setting was last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Key-value configuration of the RAPID system';

