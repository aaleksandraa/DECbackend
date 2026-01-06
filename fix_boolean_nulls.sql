-- Fix Boolean NULL Values and Set Defaults (SMALLINT version)
-- Run this script to fix existing NULL values in boolean columns stored as SMALLINT
-- Date: 2025-12-27
-- SMALLINT: 0 = false, 1 = true

-- Users table
UPDATE users SET is_guest = 0 WHERE is_guest IS NULL;

-- Appointments table
UPDATE appointments SET is_guest = 0 WHERE is_guest IS NULL;

-- Salon Settings table
UPDATE salon_settings
SET daily_report_enabled = 0
WHERE daily_report_enabled IS NULL;

UPDATE salon_settings
SET daily_report_include_staff = 1
WHERE daily_report_include_staff IS NULL;

UPDATE salon_settings
SET daily_report_include_services = 1
WHERE daily_report_include_services IS NULL;

UPDATE salon_settings
SET daily_report_include_capacity = 1
WHERE daily_report_include_capacity IS NULL;

UPDATE salon_settings
SET daily_report_include_cancellations = 1
WHERE daily_report_include_cancellations IS NULL;

-- Widget Settings table
UPDATE widget_settings
SET is_active = 1
WHERE is_active IS NULL;

-- Verify changes
SELECT 'Users with NULL is_guest:' as check_name, COUNT(*) as count FROM users WHERE is_guest IS NULL
UNION ALL
SELECT 'Appointments with NULL is_guest:', COUNT(*) FROM appointments WHERE is_guest IS NULL
UNION ALL
SELECT 'Salon settings with NULL daily_report_enabled:', COUNT(*) FROM salon_settings WHERE daily_report_enabled IS NULL
UNION ALL
SELECT 'Widget settings with NULL is_active:', COUNT(*) FROM widget_settings WHERE is_active IS NULL;
