-- ============================================================
-- RAPID Repair System — Demo seed data
-- Import after the schema exists (phpMyAdmin or mysql CLI).
-- Re-runnable: deletes existing RAPID rows, then inserts demo data.
--
-- phpMyAdmin:
--   1. Click the `rapid` database on the left (do not import into mysql/sys).
--   2. Import → choose this file.
--   3. Uncheck "Enable foreign key checks" → Go.
--
--   mysql -u root rapid < database/seed.sql
--
-- Demo logins (bcrypt hashes below):
--   Admin        admin@rapid.local      Admin@123
--   Technician   tech@rapid.local       Tech@123
--   Technician   tech2@rapid.local      Tech@123
--   Technician   tech3@rapid.local      Tech@123
--   Customer     customer@rapid.local   Customer@123
--   Customer     customer2@rapid.local  Customer@123
--   Customer     customer3@rapid.local  Customer@123
--   Customer     customer4@rapid.local  Customer@123
--   Customer     customer5@rapid.local  Customer@123
--
-- Public track demo: RPR-2026-000001 (Received)
-- ============================================================

USE `rapid`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- DELETE (not TRUNCATE): InnoDB refuses TRUNCATE on parent tables when FKs exist,
-- and phpMyAdmin often ignores SET FOREIGN_KEY_CHECKS during import.
DELETE FROM `activity_logs`;
DELETE FROM `notifications`;
DELETE FROM `warranty_claims`;
DELETE FROM `warranties`;
DELETE FROM `repair_status_history`;
DELETE FROM `quotations`;
DELETE FROM `diagnoses`;
DELETE FROM `ai_suggestions`;
DELETE FROM `device_media`;
DELETE FROM `repair_tickets`;
DELETE FROM `devices`;
DELETE FROM `technicians`;
DELETE FROM `customers`;
DELETE FROM `system_settings`;
DELETE FROM `users`;

ALTER TABLE `activity_logs` AUTO_INCREMENT = 1;
ALTER TABLE `notifications` AUTO_INCREMENT = 1;
ALTER TABLE `warranty_claims` AUTO_INCREMENT = 1;
ALTER TABLE `warranties` AUTO_INCREMENT = 1;
ALTER TABLE `repair_status_history` AUTO_INCREMENT = 1;
ALTER TABLE `quotations` AUTO_INCREMENT = 1;
ALTER TABLE `diagnoses` AUTO_INCREMENT = 1;
ALTER TABLE `ai_suggestions` AUTO_INCREMENT = 1;
ALTER TABLE `device_media` AUTO_INCREMENT = 1;
ALTER TABLE `repair_tickets` AUTO_INCREMENT = 1;
ALTER TABLE `devices` AUTO_INCREMENT = 1;
ALTER TABLE `technicians` AUTO_INCREMENT = 1;
ALTER TABLE `customers` AUTO_INCREMENT = 1;
ALTER TABLE `system_settings` AUTO_INCREMENT = 1;
ALTER TABLE `users` AUTO_INCREMENT = 1;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
INSERT INTO `users` (`id`, `role`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `created_at`) VALUES
(1,  'admin',       'System', 'Administrator', 'admin@rapid.local',      '09170000001', '$2y$10$UrsDoXI218mj6wixKcX0x.xcnwRjWlweTllWAT5DwbeHpotSllkAy', 'active',   '2025-10-01 08:00:00'),
(2,  'technician',  'Marco',  'Reyes',         'tech@rapid.local',       '09170000002', '$2y$10$mnF3EpQt6ocPl/IohHLbZOOndrnlpgcnB1dkVmZHrJF8c/1wyLRAO', 'active',   '2025-10-01 08:05:00'),
(3,  'customer',    'Ana',    'Santos',        'customer@rapid.local',   '09170000003', '$2y$10$VvY5jJGeM/0eLHECz/4RZ.LvCCeg4d/2XJmWZowCQerzURMn51Klu', 'active',   '2025-10-02 09:00:00'),
(4,  'technician',  'Luis',   'Dela Cruz',     'tech2@rapid.local',      '09170000004', '$2y$10$mnF3EpQt6ocPl/IohHLbZOOndrnlpgcnB1dkVmZHrJF8c/1wyLRAO', 'active',   '2025-10-03 09:00:00'),
(5,  'technician',  'Sofia',  'Mendoza',       'tech3@rapid.local',      '09170000005', '$2y$10$mnF3EpQt6ocPl/IohHLbZOOndrnlpgcnB1dkVmZHrJF8c/1wyLRAO', 'active',   '2025-10-03 09:10:00'),
(6,  'customer',    'Ben',    'Cruz',          'customer2@rapid.local',  '09170000006', '$2y$10$VvY5jJGeM/0eLHECz/4RZ.LvCCeg4d/2XJmWZowCQerzURMn51Klu', 'active',   '2025-11-08 10:00:00'),
(7,  'customer',    'Carla',  'Reyes',         'customer3@rapid.local',  '09170000007', '$2y$10$VvY5jJGeM/0eLHECz/4RZ.LvCCeg4d/2XJmWZowCQerzURMn51Klu', 'active',   '2025-11-15 11:20:00'),
(8,  'customer',    'Diego',  'Navarro',       'customer4@rapid.local',  '09170000008', '$2y$10$VvY5jJGeM/0eLHECz/4RZ.LvCCeg4d/2XJmWZowCQerzURMn51Klu', 'active',   '2025-12-02 14:00:00'),
(9,  'customer',    'Elena',  'Garcia',        'customer5@rapid.local',  '09170000009', '$2y$10$VvY5jJGeM/0eLHECz/4RZ.LvCCeg4d/2XJmWZowCQerzURMn51Klu', 'active',   '2026-01-12 16:30:00'),
(10, 'customer',    'Paolo',  'Lim',           'customer6@rapid.local',  '09170000010', '$2y$10$VvY5jJGeM/0eLHECz/4RZ.LvCCeg4d/2XJmWZowCQerzURMn51Klu', 'inactive', '2026-02-01 09:00:00');

-- ------------------------------------------------------------
-- technicians / customers
-- ------------------------------------------------------------
INSERT INTO `technicians` (`id`, `user_id`, `specialization`, `availability_status`, `created_at`) VALUES
(1, 2, 'Smartphones & Tablets', 'available', '2025-10-01 08:05:00'),
(2, 4, 'Laptops & Desktops',    'busy',      '2025-10-03 09:00:00'),
(3, 5, 'Tablets & Wearables',   'available', '2025-10-03 09:10:00');

INSERT INTO `customers` (`id`, `user_id`, `address`, `created_at`) VALUES
(1, 3,  '123 Mabini Street, Quezon City',           '2025-10-02 09:00:00'),
(2, 6,  '88 Espana Boulevard, Manila',              '2025-11-08 10:00:00'),
(3, 7,  '15 Katipunan Avenue, Quezon City',         '2025-11-15 11:20:00'),
(4, 8,  '42 Ayala Avenue, Makati City',             '2025-12-02 14:00:00'),
(5, 9,  '7 Bonifacio High Street, Taguig',          '2026-01-12 16:30:00'),
(6, 10, '9 Session Road, Baguio City',              '2026-02-01 09:00:00');

-- ------------------------------------------------------------
-- devices
-- ------------------------------------------------------------
INSERT INTO `devices` (`id`, `customer_id`, `device_type`, `brand`, `model`, `serial_number`, `imei`, `color`, `accessories`, `physical_condition`, `created_at`) VALUES
(1,  1, 'Smartphone', 'Samsung',  'Galaxy A54',       'SN-A54-DEMO-001',  '356938035643809', 'Black',        'Charger, SIM tray pin', 'Light scratches on back cover; screen intact', '2026-09-04 09:28:00'),
(2,  1, 'Smartphone', 'Apple',    'iPhone 13',        'SN-IP13-ANA-002',  '353325110000001', 'Blue',         'Lightning cable',       'Cracked front glass; Face ID still works',     '2026-09-07 08:10:00'),
(3,  1, 'Tablet',     'Apple',    'iPad Air 5',       'SN-IPAD-ANA-003',  NULL,               'Space Gray',   'Smart Folio',           'Minor dent on lower-left corner',              '2026-09-02 09:50:00'),
(4,  2, 'Laptop',     'ASUS',     'VivoBook 15',      'SN-ASUS-BEN-004',  NULL,               'Silver',       '65W charger',           'Sticky keyboard; scuffed palm rest',           '2026-08-28 12:40:00'),
(5,  2, 'Smartphone', 'Xiaomi',   'Redmi Note 12',    'SN-MI-BEN-005',    '860123000000005', 'Green',        'Charger',               'Screen intact; charging port dusty',           '2026-09-05 10:50:00'),
(6,  3, 'Smartphone', 'Apple',    'iPhone 14 Pro',    'SN-IP14-CAR-006',  '353325110000006', 'Deep Purple',  'MagSafe case',          'Camera glass cracked',                         '2026-09-03 08:50:00'),
(7,  3, 'Tablet',     'Samsung',  'Galaxy Tab S9',    'SN-TAB-CAR-007',   '356938000000007', 'Beige',        'S Pen',                 'Good condition',                               '2026-08-20 09:00:00'),
(8,  4, 'Laptop',     'Apple',    'MacBook Air M2',   'SN-MBA-DIE-008',   NULL,               'Midnight',     'USB-C charger',         'Liquid marks on keyboard',                     '2026-08-30 08:40:00'),
(9,  4, 'Wearable',   'Apple',    'Watch SE',         'SN-AW-DIE-009',    NULL,               'Starlight',    'Sport band',            'Hairline scratch on display',                  '2026-08-22 11:00:00'),
(10, 5, 'Smartphone', 'Oppo',     'Reno 10',          'SN-OPPO-ELE-010',  '860555000000010', 'Gold',         'Charger',               'Back glass cracked',                           '2026-08-25 09:40:00'),
(11, 5, 'Laptop',     'HP',       'Pavilion 15',      'SN-HP-ELE-011',    NULL,               'Silver',       'Charger',               'Fans loud; chassis warm',                      '2026-08-18 13:00:00'),
(12, 3, 'Headphone',  'Sony',     'WH-1000XM5',       'SN-SONY-CAR-012',  NULL,               'Black',        'Carry case',            'Left cup silent',                              '2026-08-10 10:00:00'),
(13, 4, 'Smartphone', 'Google',   'Pixel 7',          'SN-PIX-DIE-013',   '353888000000013', 'Snow',         'Charger',               'Reboots during setup',                         '2026-06-28 09:00:00'),
(14, 2, 'Tablet',     'Lenovo',   'Tab M10',          'SN-LEN-BEN-014',   NULL,               'Gray',         'Folio case',            'Will not power on',                            '2026-06-05 10:00:00'),
(15, 5, 'Smartphone', 'Vivo',     'V27',              'SN-VIVO-ELE-015',  '860777000000015', 'Blue',         'Charger',               'Speaker distorted',                            '2026-05-02 11:00:00'),
(16, 4, 'Laptop',     'Dell',     'XPS 13',           'SN-DELL-DIE-016',  NULL,               'Platinum',     'Charger',               'Trackpad click failed',                        '2026-04-08 09:00:00'),
(17, 5, 'Smartphone', 'Samsung',  'Galaxy S21',       'SN-S21-ELE-017',   '356900000000017', 'Phantom Gray', 'Charger',               'Green line on display',                        '2026-02-16 14:00:00'),
(18, 2, 'Tablet',     'Huawei',   'MatePad 11',       'SN-HUA-BEN-018',   NULL,               'Matte Gray',   'Stylus',                'Charging slowly',                              '2025-12-10 10:00:00'),
(19, 3, 'Console',    'Nintendo', 'Switch OLED',      'SN-NSW-CAR-019',   NULL,               'White',        'Dock, Joy-Cons',        'Dock not outputting HDMI',                     '2025-11-18 09:00:00'),
(20, 4, 'Smartphone', 'Apple',    'iPhone 11',        'SN-IP11-DIE-020',  '353325110000020', 'White',        'Lightning cable',       'Battery swollen slightly',                     '2025-10-14 11:00:00');

-- ------------------------------------------------------------
-- repair_tickets
-- IDs 1–11 = live workflow stages (Sep 2026)
-- IDs 12–20 = completed history for reports / warranties
-- ------------------------------------------------------------
INSERT INTO `repair_tickets` (
  `id`, `ticket_number`, `customer_id`, `device_id`, `assigned_technician_id`,
  `problem_description`, `current_status`, `priority`,
  `appointment_date`, `received_at`, `completed_at`, `estimated_completion`,
  `created_at`, `updated_at`
) VALUES
(1,  'RPR-2026-000001', 1, 1,  1,
  'Device does not charge when plugged in. Battery drains quickly even when idle.',
  'received', 'normal', '2026-09-04 10:00:00', '2026-09-04 10:15:00', NULL, '2026-09-06 17:00:00',
  '2026-09-04 09:30:00', '2026-09-04 10:15:00'),
(2,  'RPR-2026-000002', 1, 2,  NULL,
  'Front glass shattered after a drop. Touch still works but cracks catch on fingers.',
  'booking_submitted', 'high', '2026-09-08 14:00:00', NULL, NULL, NULL,
  '2026-09-07 08:15:00', '2026-09-07 08:15:00'),
(3,  'RPR-2026-000003', 2, 5,  1,
  'Phone only charges when the cable is held at a certain angle. Port feels loose.',
  'diagnosing', 'normal', '2026-09-05 14:00:00', '2026-09-05 14:10:00', NULL, '2026-09-08 17:00:00',
  '2026-09-05 11:00:00', '2026-09-06 09:20:00'),
(4,  'RPR-2026-000004', 3, 6,  1,
  'Rear camera glass is cracked. Photos are cloudy and autofocus hunts.',
  'quotation_pending', 'urgent', '2026-09-03 10:00:00', '2026-09-03 10:20:00', NULL, '2026-09-07 17:00:00',
  '2026-09-03 09:00:00', '2026-09-04 16:40:00'),
(5,  'RPR-2026-000005', 1, 3,  3,
  'iPad restarts during drawing apps. Battery percentage jumps from 40% to 10%.',
  'awaiting_approval', 'normal', '2026-09-02 11:00:00', '2026-09-02 11:15:00', NULL, '2026-09-09 17:00:00',
  '2026-09-02 10:00:00', '2026-09-05 15:00:00'),
(6,  'RPR-2026-000006', 4, 8,  2,
  'Spilled coffee on the keyboard. Several keys stick and the trackpad skips.',
  'approved', 'high', '2026-08-30 10:00:00', '2026-08-30 10:25:00', NULL, '2026-09-10 17:00:00',
  '2026-08-30 09:00:00', '2026-09-04 11:00:00'),
(7,  'RPR-2026-000007', 2, 4,  2,
  'Laptop overheats within 10 minutes and shuts down. Fans spin loudly.',
  'repairing', 'normal', '2026-08-28 14:00:00', '2026-08-28 14:20:00', NULL, '2026-09-08 17:00:00',
  '2026-08-28 13:00:00', '2026-09-05 10:00:00'),
(8,  'RPR-2026-000008', 5, 10, 1,
  'Back glass shattered. Fingerprint sensor still works.',
  'ready_for_pickup', 'normal', '2026-08-25 11:00:00', '2026-08-25 11:10:00', NULL, '2026-09-04 17:00:00',
  '2026-08-25 10:00:00', '2026-09-06 16:00:00'),
(9,  'RPR-2026-000009', 3, 7,  3,
  'S Pen pairing drops and the screen has a dead zone on the right edge.',
  'completed', 'normal', '2026-08-20 10:00:00', '2026-08-20 10:20:00', '2026-09-01 16:30:00', '2026-09-01 17:00:00',
  '2026-08-20 09:10:00', '2026-09-01 16:30:00'),
(10, 'RPR-2026-000010', 4, 9,  3,
  'Watch battery lasts less than a day. Customer asked for battery replacement only.',
  'declined', 'low', '2026-08-22 13:00:00', '2026-08-22 13:15:00', NULL, NULL,
  '2026-08-22 11:30:00', '2026-08-27 09:40:00'),
(11, 'RPR-2026-000011', 5, 11, NULL,
  'Customer booked a cleaning and thermal paste refresh, then cancelled before drop-off.',
  'cancelled', 'normal', '2026-08-19 10:00:00', NULL, NULL, NULL,
  '2026-08-18 13:20:00', '2026-08-18 18:05:00'),
(12, 'RPR-2026-000012', 3, 12, 3,
  'Left ear cup has no audio. ANC still engages.',
  'completed', 'normal', '2026-08-10 11:00:00', '2026-08-10 11:20:00', '2026-08-10 16:00:00', '2026-08-12 17:00:00',
  '2026-08-10 10:05:00', '2026-08-10 16:00:00'),
(13, 'RPR-2026-000013', 4, 13, 1,
  'Pixel 7 stuck in a boot loop after a system update.',
  'completed', 'high', '2026-06-29 10:00:00', '2026-06-29 10:15:00', '2026-07-01 15:40:00', '2026-07-02 17:00:00',
  '2026-06-28 09:30:00', '2026-07-01 15:40:00'),
(14, 'RPR-2026-000014', 2, 14, 2,
  'Tablet will not power on. No charging LED.',
  'completed', 'normal', '2026-06-06 11:00:00', '2026-06-06 11:10:00', '2026-06-10 14:00:00', '2026-06-11 17:00:00',
  '2026-06-05 10:20:00', '2026-06-10 14:00:00'),
(15, 'RPR-2026-000015', 5, 15, 2,
  'Earpiece and loudspeaker sound crackly at any volume.',
  'completed', 'normal', '2026-05-03 10:00:00', '2026-05-03 10:20:00', '2026-05-05 16:10:00', '2026-05-06 17:00:00',
  '2026-05-02 11:15:00', '2026-05-05 16:10:00'),
(16, 'RPR-2026-000016', 4, 16, 2,
  'Trackpad physical click stopped working. Cursor still moves.',
  'completed', 'normal', '2026-04-09 10:00:00', '2026-04-09 10:25:00', '2026-04-12 15:00:00', '2026-04-14 17:00:00',
  '2026-04-08 09:40:00', '2026-04-12 15:00:00'),
(17, 'RPR-2026-000017', 5, 17, 1,
  'Green vertical line appeared on the display after the phone was left in a hot car.',
  'completed', 'urgent', '2026-02-17 11:00:00', '2026-02-17 11:20:00', '2026-02-20 17:00:00', '2026-02-21 17:00:00',
  '2026-02-16 14:10:00', '2026-02-20 17:00:00'),
(18, 'RPR-2025-000001', 2, 18, 1,
  'Tablet charges very slowly and disconnects if bumped.',
  'completed', 'normal', '2025-12-11 10:00:00', '2025-12-11 10:15:00', '2025-12-14 16:00:00', '2025-12-15 17:00:00',
  '2025-12-10 10:30:00', '2025-12-14 16:00:00'),
(19, 'RPR-2025-000002', 3, 19, 3,
  'Switch dock shows no HDMI signal on two different TVs.',
  'completed', 'normal', '2025-11-19 10:00:00', '2025-11-19 10:20:00', '2025-11-22 13:30:00', '2025-11-24 17:00:00',
  '2025-11-18 09:45:00', '2025-11-22 13:30:00'),
(20, 'RPR-2025-000003', 4, 20, 1,
  'iPhone 11 battery health at 74% and shuts down at 20%.',
  'completed', 'normal', '2025-10-15 10:00:00', '2025-10-15 10:10:00', '2025-10-18 15:20:00', '2025-10-18 17:00:00',
  '2025-10-14 11:20:00', '2025-10-18 15:20:00');

-- ------------------------------------------------------------
-- diagnoses (tickets that reached diagnosis or later)
-- ------------------------------------------------------------
INSERT INTO `diagnoses` (`ticket_id`, `technician_id`, `diagnosis`, `recommended_action`, `estimated_completion`, `created_at`) VALUES
(4,  1, 'Cracked camera lens and debris on the sensor. Focus motor still responds.', 'Replace rear camera glass and clean the module. Recalibrate focus.', '2026-09-07 17:00:00', '2026-09-04 16:30:00'),
(5,  3, 'Battery reports unstable voltage. Logic board is otherwise healthy.', 'Replace battery and run a full charge-cycle test.', '2026-09-09 17:00:00', '2026-09-04 14:00:00'),
(6,  2, 'Liquid under the top case. Trackpad ribbon is corroded; board is dry.', 'Ultrasonic clean, replace top case/keyboard and trackpad.', '2026-09-10 17:00:00', '2026-09-01 11:00:00'),
(7,  2, 'Dust-packed heatsink and dried thermal paste. GPU throttle at 95°C.', 'Deep clean, repaste, and replace the CPU fan if bearings rattle.', '2026-09-08 17:00:00', '2026-08-30 15:00:00'),
(8,  1, 'Back glass shattered; fingerprint flex intact.', 'Replace back glass and gasket. Test in-display fingerprint.', '2026-09-04 17:00:00', '2026-08-27 10:00:00'),
(9,  3, 'Digitizer dead zone on the right 2 cm. S Pen coil is weak.', 'Replace display assembly and S Pen. Recalibrate AES.', '2026-09-01 17:00:00', '2026-08-22 16:00:00'),
(10, 3, 'Battery at 78% health. Watch otherwise functions.', 'Replace battery. 30-day parts warranty.', '2026-08-26 17:00:00', '2026-08-24 12:00:00'),
(12, 3, 'Left driver voice coil open circuit.', 'Replace left speaker driver and re-foam the ear pad.', '2026-08-12 17:00:00', '2026-08-10 13:00:00'),
(13, 1, 'Corrupted system partition after OTA. Hardware self-test passed.', 'Reflash firmware, wipe cache, verify sensors.', '2026-07-02 17:00:00', '2026-06-29 15:00:00'),
(14, 2, 'Main battery swollen; charging IC still good.', 'Replace battery and inspect the charging board.', '2026-06-11 17:00:00', '2026-06-07 11:00:00'),
(15, 2, 'Water mark on the speaker mesh. Driver cone torn.', 'Replace earpiece and loudspeaker modules.', '2026-05-06 17:00:00', '2026-05-03 16:00:00'),
(16, 2, 'Trackpad haptic motor disconnected; cable nicked.', 'Replace trackpad assembly and cable.', '2026-04-14 17:00:00', '2026-04-09 16:00:00'),
(17, 1, 'OLED burn from heat; not a software artifact.', 'Replace AMOLED panel. Advise against heat exposure.', '2026-02-21 17:00:00', '2026-02-18 10:00:00'),
(18, 1, 'Worn USB-C port; data pins intermittent.', 'Replace charging board and port.', '2025-12-15 17:00:00', '2025-12-11 16:00:00'),
(19, 3, 'Dock HDMI IC failed. Console HDMI works with a third-party dock.', 'Replace official dock HDMI board.', '2025-11-24 17:00:00', '2025-11-19 16:00:00'),
(20, 1, 'Battery swollen against the display. No other damage.', 'Replace battery, pressure-test the chassis.', '2025-10-18 17:00:00', '2025-10-15 15:00:00');

-- ------------------------------------------------------------
-- quotations
-- ------------------------------------------------------------
INSERT INTO `quotations` (
  `ticket_id`, `technician_id`, `labor_cost`, `parts_cost`, `other_cost`, `total_amount`,
  `notes`, `status`, `valid_until`, `created_at`
) VALUES
(5,  3,  800.00,  2200.00,  0.00,  3000.00, 'iPad Air 5 battery replacement including cycle test.', 'pending',  '2026-09-12', '2026-09-05 15:00:00'),
(6,  2, 3500.00, 12800.00, 500.00, 16800.00, 'Top case, keyboard, and trackpad. Liquid-damage handling fee included.', 'approved', '2026-09-11', '2026-09-02 09:30:00'),
(7,  2, 1200.00,  1800.00,  0.00,  3000.00, 'Cleaning, repaste, and replacement CPU fan.', 'approved', '2026-09-06', '2026-08-31 10:00:00'),
(8,  1,  900.00,  2500.00,  0.00,  3400.00, 'Back glass and adhesive. Fingerprint sensor reused.', 'approved', '2026-09-03', '2026-08-28 11:00:00'),
(9,  3, 1500.00,  8900.00,  0.00, 10400.00, 'Display assembly and genuine S Pen.', 'approved', '2026-08-29', '2026-08-23 10:00:00'),
(10, 3,  700.00,  2800.00,  0.00,  3500.00, 'Watch SE battery. Customer declined — price higher than expected.', 'declined', '2026-09-03', '2026-08-25 09:00:00'),
(12, 3,  600.00,  1900.00,  0.00,  2500.00, 'Left driver and pad replacement.', 'approved', '2026-08-17', '2026-08-10 14:00:00'),
(13, 1, 1500.00,     0.00,  0.00,  1500.00, 'Firmware reflash. No parts required.', 'approved', '2026-07-06', '2026-06-30 09:00:00'),
(14, 2,  800.00,  1600.00,  0.00,  2400.00, 'Lenovo Tab M10 battery.', 'approved', '2026-06-14', '2026-06-07 14:00:00'),
(15, 2,  700.00,  1100.00,  0.00,  1800.00, 'Earpiece and loudspeaker pair.', 'approved', '2026-05-10', '2026-05-04 09:00:00'),
(16, 2, 1800.00,  4200.00,  0.00,  6000.00, 'XPS 13 trackpad and cable.', 'approved', '2026-04-16', '2026-04-10 11:00:00'),
(17, 1, 1200.00,  6500.00,  0.00,  7700.00, 'Galaxy S21 OLED panel.', 'approved', '2026-02-24', '2026-02-18 14:00:00'),
(18, 1,  700.00,   950.00,  0.00,  1650.00, 'MatePad charging board.', 'approved', '2025-12-18', '2025-12-12 10:00:00'),
(19, 3,  900.00,  2100.00,  0.00,  3000.00, 'Switch dock HDMI board.', 'approved', '2025-11-26', '2025-11-20 11:00:00'),
(20, 1,  800.00,  1800.00,  0.00,  2600.00, 'iPhone 11 battery (genuine-grade).', 'approved', '2025-10-22', '2025-10-16 09:00:00');

-- ------------------------------------------------------------
-- repair_status_history
-- ------------------------------------------------------------
INSERT INTO `repair_status_history` (`ticket_id`, `status`, `remarks`, `updated_by`, `created_at`) VALUES
(1,  'booking_submitted', 'Online booking submitted by customer.', 3, '2026-09-04 09:30:00'),
(1,  'received',          'Device received at service counter.', 1, '2026-09-04 10:15:00'),

(2,  'booking_submitted', 'Walk-in style online booking for cracked iPhone 13.', 3, '2026-09-07 08:15:00'),

(3,  'booking_submitted', 'Online booking submitted.', 6, '2026-09-05 11:00:00'),
(3,  'received',          'Device received and tagged.', 1, '2026-09-05 14:10:00'),
(3,  'diagnosing',        'Assigned to Marco for charging-port inspection.', 1, '2026-09-06 09:20:00'),

(4,  'booking_submitted', 'Urgent camera repair booking.', 7, '2026-09-03 09:00:00'),
(4,  'received',          'Device received.', 1, '2026-09-03 10:20:00'),
(4,  'diagnosing',        'Camera module inspection started.', 2, '2026-09-03 15:00:00'),
(4,  'quotation_pending', 'Diagnosis complete. Preparing quotation.', 2, '2026-09-04 16:40:00'),

(5,  'booking_submitted', 'iPad battery issue booked.', 3, '2026-09-02 10:00:00'),
(5,  'received',          'Device received.', 1, '2026-09-02 11:15:00'),
(5,  'diagnosing',        'Sofia running battery diagnostics.', 5, '2026-09-03 10:00:00'),
(5,  'quotation_pending', 'Battery replacement recommended.', 5, '2026-09-04 14:10:00'),
(5,  'awaiting_approval', 'Quotation sent to customer.', 5, '2026-09-05 15:00:00'),

(6,  'booking_submitted', 'Liquid damage booking.', 8, '2026-08-30 09:00:00'),
(6,  'received',          'MacBook received. Powered off for drying.', 1, '2026-08-30 10:25:00'),
(6,  'diagnosing',        'Luis opened the unit for liquid inspection.', 4, '2026-08-31 09:00:00'),
(6,  'quotation_pending', 'Top case replacement required.', 4, '2026-09-01 11:10:00'),
(6,  'awaiting_approval', 'Quotation sent.', 4, '2026-09-02 09:30:00'),
(6,  'approved',          'Customer approved the quotation.', 8, '2026-09-04 11:00:00'),

(7,  'booking_submitted', 'Overheating laptop booked.', 6, '2026-08-28 13:00:00'),
(7,  'received',          'Device received.', 1, '2026-08-28 14:20:00'),
(7,  'diagnosing',        'Thermal diagnostics started.', 4, '2026-08-29 10:00:00'),
(7,  'quotation_pending', 'Cleaning and fan replacement quoted.', 4, '2026-08-30 15:10:00'),
(7,  'awaiting_approval', 'Quotation sent.', 4, '2026-08-31 10:00:00'),
(7,  'approved',          'Customer approved.', 6, '2026-09-01 09:00:00'),
(7,  'repairing',         'Heatsink removed; paste and fan in progress.', 4, '2026-09-05 10:00:00'),

(8,  'booking_submitted', 'Back glass replacement booked.', 9, '2026-08-25 10:00:00'),
(8,  'received',          'Device received.', 1, '2026-08-25 11:10:00'),
(8,  'diagnosing',        'Glass and fingerprint flex checked.', 2, '2026-08-26 09:00:00'),
(8,  'quotation_pending', 'Back glass quote prepared.', 2, '2026-08-27 10:10:00'),
(8,  'awaiting_approval', 'Quotation sent.', 2, '2026-08-28 11:00:00'),
(8,  'approved',          'Customer approved.', 9, '2026-08-29 08:40:00'),
(8,  'repairing',         'Glass replacement underway.', 2, '2026-09-02 09:00:00'),
(8,  'ready_for_pickup',  'Repair complete. QC passed. Ready for pickup.', 2, '2026-09-06 16:00:00'),

(9,  'booking_submitted', 'Tab S9 digitizer issue booked.', 7, '2026-08-20 09:10:00'),
(9,  'received',          'Device received.', 1, '2026-08-20 10:20:00'),
(9,  'diagnosing',        'Digitizer and S Pen coil tested.', 5, '2026-08-21 09:00:00'),
(9,  'quotation_pending', 'Display + S Pen required.', 5, '2026-08-22 16:10:00'),
(9,  'awaiting_approval', 'Quotation sent.', 5, '2026-08-23 10:00:00'),
(9,  'approved',          'Customer approved.', 7, '2026-08-24 09:00:00'),
(9,  'repairing',         'Display assembly replacement.', 5, '2026-08-27 10:00:00'),
(9,  'ready_for_pickup',  'QC passed.', 5, '2026-09-01 11:00:00'),
(9,  'completed',         'Released to customer. 30-day warranty started.', 1, '2026-09-01 16:30:00'),

(10, 'booking_submitted', 'Watch battery booking.', 8, '2026-08-22 11:30:00'),
(10, 'received',          'Watch received.', 1, '2026-08-22 13:15:00'),
(10, 'diagnosing',        'Battery health 78%.', 5, '2026-08-24 12:00:00'),
(10, 'quotation_pending', 'Battery quote prepared.', 5, '2026-08-24 12:20:00'),
(10, 'awaiting_approval', 'Quotation sent.', 5, '2026-08-25 09:00:00'),
(10, 'declined',          'Customer declined the quotation.', 8, '2026-08-27 09:40:00'),

(11, 'booking_submitted', 'Laptop cleaning booking.', 9, '2026-08-18 13:20:00'),
(11, 'cancelled',         'Customer cancelled before drop-off.', 9, '2026-08-18 18:05:00'),

(12, 'booking_submitted', 'Headphone driver issue booked.', 7, '2026-08-10 10:05:00'),
(12, 'received',          'Device received.', 1, '2026-08-10 11:20:00'),
(12, 'diagnosing',        'Left driver open circuit.', 5, '2026-08-10 13:00:00'),
(12, 'quotation_pending', 'Driver replacement quoted.', 5, '2026-08-10 13:20:00'),
(12, 'awaiting_approval', 'Quotation sent.', 5, '2026-08-10 14:00:00'),
(12, 'approved',          'Customer approved on-site.', 7, '2026-08-10 14:20:00'),
(12, 'repairing',         'Driver replaced.', 5, '2026-08-10 15:00:00'),
(12, 'ready_for_pickup',  'Same-day QC passed.', 5, '2026-08-10 15:40:00'),
(12, 'completed',         'Released to customer.', 1, '2026-08-10 16:00:00'),

(13, 'booking_submitted', 'Boot-loop Pixel booked.', 8, '2026-06-28 09:30:00'),
(13, 'received',          'Device received.', 1, '2026-06-29 10:15:00'),
(13, 'diagnosing',        'Firmware partition corrupted.', 2, '2026-06-29 15:00:00'),
(13, 'quotation_pending', 'Reflash quoted.', 2, '2026-06-29 15:20:00'),
(13, 'awaiting_approval', 'Quotation sent.', 2, '2026-06-30 09:00:00'),
(13, 'approved',          'Customer approved.', 8, '2026-06-30 12:00:00'),
(13, 'repairing',         'Firmware reflash in progress.', 2, '2026-07-01 09:00:00'),
(13, 'ready_for_pickup',  'Device boots and passes tests.', 2, '2026-07-01 14:00:00'),
(13, 'completed',         'Released to customer.', 1, '2026-07-01 15:40:00'),

(14, 'booking_submitted', 'Dead tablet booked.', 6, '2026-06-05 10:20:00'),
(14, 'received',          'Device received.', 1, '2026-06-06 11:10:00'),
(14, 'diagnosing',        'Swollen battery found.', 4, '2026-06-07 11:00:00'),
(14, 'quotation_pending', 'Battery replacement quoted.', 4, '2026-06-07 11:20:00'),
(14, 'awaiting_approval', 'Quotation sent.', 4, '2026-06-07 14:00:00'),
(14, 'approved',          'Customer approved.', 6, '2026-06-08 09:00:00'),
(14, 'repairing',         'Battery replaced.', 4, '2026-06-09 10:00:00'),
(14, 'ready_for_pickup',  'Powers on. QC passed.', 4, '2026-06-10 11:00:00'),
(14, 'completed',         'Released to customer.', 1, '2026-06-10 14:00:00'),

(15, 'booking_submitted', 'Speaker distortion booked.', 9, '2026-05-02 11:15:00'),
(15, 'received',          'Device received.', 1, '2026-05-03 10:20:00'),
(15, 'diagnosing',        'Torn speaker cone.', 4, '2026-05-03 16:00:00'),
(15, 'quotation_pending', 'Speaker pair quoted.', 4, '2026-05-03 16:20:00'),
(15, 'awaiting_approval', 'Quotation sent.', 4, '2026-05-04 09:00:00'),
(15, 'approved',          'Customer approved.', 9, '2026-05-04 14:00:00'),
(15, 'repairing',         'Speakers replaced.', 4, '2026-05-05 10:00:00'),
(15, 'ready_for_pickup',  'Audio QC passed.', 4, '2026-05-05 15:00:00'),
(15, 'completed',         'Released to customer.', 1, '2026-05-05 16:10:00'),

(16, 'booking_submitted', 'XPS trackpad booked.', 8, '2026-04-08 09:40:00'),
(16, 'received',          'Device received.', 1, '2026-04-09 10:25:00'),
(16, 'diagnosing',        'Trackpad cable nicked.', 4, '2026-04-09 16:00:00'),
(16, 'quotation_pending', 'Trackpad assembly quoted.', 4, '2026-04-09 16:20:00'),
(16, 'awaiting_approval', 'Quotation sent.', 4, '2026-04-10 11:00:00'),
(16, 'approved',          'Customer approved.', 8, '2026-04-10 16:00:00'),
(16, 'repairing',         'Trackpad replaced.', 4, '2026-04-11 09:00:00'),
(16, 'ready_for_pickup',  'Click restored.', 4, '2026-04-12 11:00:00'),
(16, 'completed',         'Released to customer.', 1, '2026-04-12 15:00:00'),

(17, 'booking_submitted', 'Green line display booked.', 9, '2026-02-16 14:10:00'),
(17, 'received',          'Device received.', 1, '2026-02-17 11:20:00'),
(17, 'diagnosing',        'OLED panel heat damage.', 2, '2026-02-18 10:00:00'),
(17, 'quotation_pending', 'Panel replacement quoted.', 2, '2026-02-18 10:20:00'),
(17, 'awaiting_approval', 'Quotation sent.', 2, '2026-02-18 14:00:00'),
(17, 'approved',          'Customer approved.', 9, '2026-02-19 09:00:00'),
(17, 'repairing',         'Panel replacement.', 2, '2026-02-19 13:00:00'),
(17, 'ready_for_pickup',  'Display QC passed.', 2, '2026-02-20 15:00:00'),
(17, 'completed',         'Released to customer.', 1, '2026-02-20 17:00:00'),

(18, 'booking_submitted', 'Slow charging tablet booked.', 6, '2025-12-10 10:30:00'),
(18, 'received',          'Device received.', 1, '2025-12-11 10:15:00'),
(18, 'diagnosing',        'Worn USB-C port.', 2, '2025-12-11 16:00:00'),
(18, 'quotation_pending', 'Charging board quoted.', 2, '2025-12-11 16:20:00'),
(18, 'awaiting_approval', 'Quotation sent.', 2, '2025-12-12 10:00:00'),
(18, 'approved',          'Customer approved.', 6, '2025-12-12 15:00:00'),
(18, 'repairing',         'Port replaced.', 2, '2025-12-13 10:00:00'),
(18, 'ready_for_pickup',  'Charges at full rate.', 2, '2025-12-14 11:00:00'),
(18, 'completed',         'Released to customer.', 1, '2025-12-14 16:00:00'),

(19, 'booking_submitted', 'Switch dock HDMI booked.', 7, '2025-11-18 09:45:00'),
(19, 'received',          'Dock received.', 1, '2025-11-19 10:20:00'),
(19, 'diagnosing',        'HDMI IC failed.', 5, '2025-11-19 16:00:00'),
(19, 'quotation_pending', 'Dock board quoted.', 5, '2025-11-19 16:20:00'),
(19, 'awaiting_approval', 'Quotation sent.', 5, '2025-11-20 11:00:00'),
(19, 'approved',          'Customer approved.', 7, '2025-11-20 16:00:00'),
(19, 'repairing',         'HDMI board replaced.', 5, '2025-11-21 10:00:00'),
(19, 'ready_for_pickup',  'HDMI output verified.', 5, '2025-11-22 11:00:00'),
(19, 'completed',         'Released to customer.', 1, '2025-11-22 13:30:00'),

(20, 'booking_submitted', 'iPhone 11 battery booked.', 8, '2025-10-14 11:20:00'),
(20, 'received',          'Device received.', 1, '2025-10-15 10:10:00'),
(20, 'diagnosing',        'Swollen battery.', 2, '2025-10-15 15:00:00'),
(20, 'quotation_pending', 'Battery quoted.', 2, '2025-10-15 15:20:00'),
(20, 'awaiting_approval', 'Quotation sent.', 2, '2025-10-16 09:00:00'),
(20, 'approved',          'Customer approved.', 8, '2025-10-16 14:00:00'),
(20, 'repairing',         'Battery replaced.', 2, '2025-10-17 09:00:00'),
(20, 'ready_for_pickup',  'Battery health 100%.', 2, '2025-10-18 11:00:00'),
(20, 'completed',         'Released to customer.', 1, '2025-10-18 15:20:00');

-- ------------------------------------------------------------
-- warranties (completed tickets only)
-- As of 2026-09-07: ticket 9 active, ticket 12 expiring soon, rest expired
-- ------------------------------------------------------------
INSERT INTO `warranties` (`ticket_id`, `warranty_start`, `warranty_end`, `warranty_days`, `warranty_status`, `created_at`) VALUES
(9,  '2026-09-01', '2026-10-01', 30, 'active',         '2026-09-01 16:30:00'),
(12, '2026-08-10', '2026-09-09', 30, 'expiring_soon',  '2026-08-10 16:00:00'),
(13, '2026-07-01', '2026-07-31', 30, 'expired',        '2026-07-01 15:40:00'),
(14, '2026-06-10', '2026-07-10', 30, 'expired',        '2026-06-10 14:00:00'),
(15, '2026-05-05', '2026-06-04', 30, 'expired',        '2026-05-05 16:10:00'),
(16, '2026-04-12', '2026-05-12', 30, 'expired',        '2026-04-12 15:00:00'),
(17, '2026-02-20', '2026-03-22', 30, 'expired',        '2026-02-20 17:00:00'),
(18, '2025-12-14', '2026-01-13', 30, 'expired',        '2025-12-14 16:00:00'),
(19, '2025-11-22', '2025-12-22', 30, 'expired',        '2025-11-22 13:30:00'),
(20, '2025-10-18', '2025-11-17', 30, 'expired',        '2025-10-18 15:20:00');

-- ------------------------------------------------------------
-- warranty_claims
-- ------------------------------------------------------------
INSERT INTO `warranty_claims` (
  `original_ticket_id`, `customer_id`, `device_id`, `issue_description`,
  `claim_status`, `technician_id`, `resolution`, `created_at`, `updated_at`
) VALUES
(9,  3, 7,  'Dead zone on the right edge came back after one week of use.',
  'submitted', NULL, NULL, '2026-09-06 09:10:00', '2026-09-06 09:10:00'),
(12, 3, 12, 'Left cup audio dropped out again during a flight.',
  'reviewing', 3, 'Inspecting solder joints on the replacement driver.', '2026-09-05 18:00:00', '2026-09-06 10:00:00'),
(13, 4, 13, 'Phone restarted twice after the firmware reflash.',
  'resolved', 1, 'Cache partition wiped and update reapplied. Stable for 48 hours.', '2026-07-08 11:00:00', '2026-07-10 16:00:00'),
(14, 2, 14, 'Tablet still refuses to charge overnight.',
  'rejected', 2, 'Customer used a third-party 9V charger. Not covered. Advised official charger.', '2026-06-18 09:00:00', '2026-06-19 14:00:00'),
(15, 5, 15, 'Loudspeaker crackle returned after a week in the rain.',
  'repairing', 2, 'Replacing speaker gasket and applying extra mesh. In progress.', '2026-05-12 10:00:00', '2026-05-13 09:00:00'),
(16, 4, 16, 'Trackpad click feels mushy compared with the first day back.',
  'approved', 2, 'Covered. Replacement trackpad ordered.', '2026-04-20 13:00:00', '2026-04-21 10:00:00');

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
INSERT INTO `notifications` (`user_id`, `ticket_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(3, 5,  'Quotation ready',           'A quotation is waiting for your approval on ticket RPR-2026-000005.', 0, '2026-09-05 15:01:00'),
(3, 2,  'Booking received',          'We received your booking RPR-2026-000002. Bring the iPhone 13 on 8 Sep, 2:00 PM.', 0, '2026-09-07 08:16:00'),
(3, 1,  'Device received',           'Ticket RPR-2026-000001 is now marked Received.', 1, '2026-09-04 10:16:00'),
(3, 9,  'Warranty claim submitted',  'Your claim for RPR-2026-000009 was submitted.', 0, '2026-09-06 09:11:00'),
(2, 3,  'Ticket assigned',           'You were assigned ticket RPR-2026-000003 (Redmi Note 12).', 0, '2026-09-06 09:21:00'),
(2, 4,  'Urgent ticket',             'RPR-2026-000004 (iPhone 14 Pro camera) is quotation pending.', 0, '2026-09-04 16:41:00'),
(2, 8,  'Ready for pickup',          'RPR-2026-000008 passed QC and is ready for pickup.', 1, '2026-09-06 16:01:00'),
(4, 6,  'Quotation approved',        'Diego Navarro approved the MacBook Air quotation (RPR-2026-000006).', 0, '2026-09-04 11:01:00'),
(4, 7,  'Repair in progress',        'RPR-2026-000007 is in Repairing. Finish thermal service.', 0, '2026-09-05 10:01:00'),
(5, 5,  'Awaiting customer',         'Quotation for RPR-2026-000005 is waiting on Ana Santos.', 1, '2026-09-05 15:01:00'),
(5, 12, 'Warranty claim to review',  'Claim on RPR-2026-000012 needs your review.', 0, '2026-09-05 18:01:00'),
(1, 2,  'Unassigned booking',        'New booking RPR-2026-000002 has no technician yet.', 0, '2026-09-07 08:16:00'),
(1, 9,  'New warranty claim',        'Carla Reyes filed a claim on RPR-2026-000009.', 0, '2026-09-06 09:11:00'),
(6, 7,  'Repair update',             'Your VivoBook (RPR-2026-000007) is currently being repaired.', 1, '2026-09-05 10:02:00'),
(7, 9,  'Repair completed',          'Ticket RPR-2026-000009 was completed. Warranty is active until 1 Oct 2026.', 1, '2026-09-01 16:31:00'),
(8, 6,  'Quotation approved',        'You approved the quotation for RPR-2026-000006.', 1, '2026-09-04 11:01:00'),
(8, 10, 'Quotation declined',        'You declined the quotation for RPR-2026-000010.', 1, '2026-08-27 09:41:00'),
(9, 8,  'Ready for pickup',          'RPR-2026-000008 is ready. Please collect at RAPID Device Care.', 0, '2026-09-06 16:01:00');

-- ------------------------------------------------------------
-- activity_logs
-- ------------------------------------------------------------
INSERT INTO `activity_logs` (`user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1,  'login',              'User signed in.',                                          '127.0.0.1', '2026-09-07 08:00:00'),
(3,  'login',              'User signed in.',                                          '127.0.0.1', '2026-09-07 08:10:00'),
(3,  'booking_created',    'Created ticket RPR-2026-000002',                           '127.0.0.1', '2026-09-07 08:15:00'),
(1,  'assign_technician',  'Assigned technician #1 to RPR-2026-000003',                '127.0.0.1', '2026-09-06 09:20:00'),
(2,  'diagnosis_saved',    'Diagnosis saved for RPR-2026-000004',                      '127.0.0.1', '2026-09-04 16:30:00'),
(5,  'quotation_created',  'Quotation #1 for RPR-2026-000005',                         '127.0.0.1', '2026-09-05 15:00:00'),
(8,  'status_update',      'Ticket RPR-2026-000006 → approved',                        '127.0.0.1', '2026-09-04 11:00:00'),
(4,  'status_update',      'Ticket RPR-2026-000007 → repairing',                       '127.0.0.1', '2026-09-05 10:00:00'),
(2,  'status_update',      'Ticket RPR-2026-000008 → ready_for_pickup',                '127.0.0.1', '2026-09-06 16:00:00'),
(1,  'status_update',      'Ticket RPR-2026-000009 → completed',                       '127.0.0.1', '2026-09-01 16:30:00'),
(7,  'warranty_claim',     'Filed claim #1 for RPR-2026-000009',                       '127.0.0.1', '2026-09-06 09:10:00'),
(5,  'claim_update',       'Claim #2 → reviewing',                                     '127.0.0.1', '2026-09-06 10:00:00'),
(1,  'customer_create',    'Created customer #5',                                      '127.0.0.1', '2026-01-12 16:30:00'),
(1,  'technician_create',  'Created technician #3',                                    '127.0.0.1', '2025-10-03 09:10:00'),
(9,  'status_update',      'Ticket RPR-2026-000011 → cancelled',                       '127.0.0.1', '2026-08-18 18:05:00');

-- ------------------------------------------------------------
-- system_settings
-- ------------------------------------------------------------
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

SET UNIQUE_CHECKS = 1;
SET FOREIGN_KEY_CHECKS = 1;
