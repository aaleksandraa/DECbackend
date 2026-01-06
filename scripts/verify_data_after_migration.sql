-- Boolean Migration - Post-Migration Data Verification Script
-- Purpose: Verify data integrity after SMALLINT to BOOLEAN migration
-- Run this AFTER migration to confirm data preservation

\echo '=========================================='
\echo 'Boolean Migration - Post-Migration Data Verification'
\echo '=========================================='
\echo ''

-- Users table
\echo 'Users table (is_guest - now BOOLEAN):'
SELECT
    'users_is_guest_false' as check_name,
    COUNT(*) as count
FROM users
WHERE is_guest = false
UNION ALL
SELECT
    'users_is_guest_true',
    COUNT(*)
FROM users
WHERE is_guest = true
UNION ALL
SELECT
    'users_is_guest_NULL',
    COUNT(*)
FROM users
WHERE is_guest IS NULL
UNION ALL
SELECT
    'users_total',
    COUNT(*)
FROM users;

\echo ''

-- Appointments table
\echo 'Appointments table (is_guest - now BOOLEAN):'
SELECT
    'appointments_is_guest_false' as check_name,
    COUNT(*) as count
FROM appointments
WHERE is_guest = false
UNION ALL
SELECT
    'appointments_is_guest_true',
    COUNT(*)
FROM appointments
WHERE is_guest = true
UNION ALL
SELECT
    'appointments_is_guest_NULL',
    COUNT(*)
FROM appointments
WHERE is_guest IS NULL
UNION ALL
SELECT
    'appointments_total',
    COUNT(*)
FROM appointments;

\echo ''

-- Widget Settings table
\echo 'Widget Settings table (is_active - now BOOLEAN):'
SELECT
    'widget_settings_is_active_false' as check_name,
    COUNT(*) as count
FROM widget_settings
WHERE is_active = false
UNION ALL
SELECT
    'widget_settings_is_active_true',
    COUNT(*)
FROM widget_settings
WHERE is_active = true
UNION ALL
SELECT
    'widget_settings_total',
    COUNT(*)
FROM widget_settings;

\echo ''

-- Salon Settings table
\echo 'Salon Settings table (daily_report_enabled - now BOOLEAN):'
SELECT
    'salon_settings_daily_report_enabled_false' as check_name,
    COUNT(*) as count
FROM salon_settings
WHERE daily_report_enabled = false
UNION ALL
SELECT
    'salon_settings_daily_report_enabled_true',
    COUNT(*)
FROM salon_settings
WHERE daily_report_enabled = true
UNION ALL
SELECT
    'salon_settings_total',
    COUNT(*)
FROM salon_settings;

\echo ''

-- Staff table
\echo 'Staff table (is_active, is_public - now BOOLEAN):'
SELECT
    'staff_is_active_false' as check_name,
    COUNT(*) as count
FROM staff
WHERE is_active = false
UNION ALL
SELECT
    'staff_is_active_true',
    COUNT(*)
FROM staff
WHERE is_active = true
UNION ALL
SELECT
    'staff_is_public_false',
    COUNT(*)
FROM staff
WHERE is_public = false
UNION ALL
SELECT
    'staff_is_public_true',
    COUNT(*)
FROM staff
WHERE is_public = true
UNION ALL
SELECT
    'staff_total',
    COUNT(*)
FROM staff;

\echo ''

-- Services table
\echo 'Services table (is_active - now BOOLEAN):'
SELECT
    'services_is_active_false' as check_name,
    COUNT(*) as count
FROM services
WHERE is_active = false
UNION ALL
SELECT
    'services_is_active_true',
    COUNT(*)
FROM services
WHERE is_active = true
UNION ALL
SELECT
    'services_total',
    COUNT(*)
FROM services;

\echo ''

-- Verify column types
\echo 'Column types verification:'
SELECT
    table_name,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_name IN ('users', 'appointments', 'widget_settings', 'salon_settings', 'staff', 'services')
    AND column_name IN ('is_guest', 'is_active', 'is_public', 'daily_report_enabled', 'accepts_bookings', 'auto_confirm')
ORDER BY table_name, column_name;

\echo ''
\echo '=========================================='
\echo 'Verification Complete'
\echo '=========================================='
\echo ''
\echo 'Compare these counts with pre-migration counts!'
\echo 'Expected: false count = old 0 count, true count = old 1 count'
\echo ''
