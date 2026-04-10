-- ============================================================
-- Migration: Add site_settings table
-- Run this in phpMyAdmin or via MySQL CLI on your cig_system DB
-- ============================================================

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key`   varchar(100)  NOT NULL,
  `setting_value` text          NOT NULL,
  `updated_at`    timestamp     NOT NULL DEFAULT current_timestamp()
                                ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default values (skipped if already exist)
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('mission',          'To strengthen the capability of organization through collaboration and active participation in school governance.'),
('vision',           'A highly trusted organization committed to capacitating progressive communities.'),
('values',           'SERVICE - Dedicated to serving our communities\nVOLUNTEERISM - Active participation and commitment'),
('president_name',   'Name of Interim University President'),
('president_title',  'Interim University President'),
('dean_name',        'Name of Dean'),
('dean_title',       'Dean, Office of Student Affairs and Services');
