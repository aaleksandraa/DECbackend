-- Boolean Migration - Data Verification Script
-- Purpose: Document current data counts before migration
-- Run this BEFORE migration to establish baseline

\echo '=========================================='
\echo 'Boolean Migration - Pre-Migration Data Verification'
\echo '=========================================='
\echo ''

-- Users table
\echo 'Users table (is_guest):'
SELECT
    'users_is_guest_0' as check_name,
    COUNT(*) as count
FROM users
WHERE is_guest = 0
UNION ALL
SELECT
    'users_is_guest_1',
    COUNT(*)
FROM users
WHERE is_guest = 1
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
\echo 'Appointments table (is_guest):'
SELECT
    'appointments_is_guest_0' as check_name,
    COUNT(*) as count
FROM appointments
WHERE is_guest = 0
UNION ALL
SELECT
    'appointments_is_guest_1',
    COUNT(*)
FROM appointments
WHERE is_guest = 1
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
\echo 'Widget Settings table (is_active):'
SELECT
    'widget_settings_is_active_0' as check_name,
    COUNT(*) as count
FROM widget_settings
WHERE is_active = 0
UNION ALL
SELECT
    'widget_settings_is_active_1',
    COUNT(*)
FROM widget_settings
WHERE is_active = 1
UNION ALL
SELECT
    'widget_settings_total',
    COUNT(*)
FROM widget_settings;

\echo ''

-- Salon Settings table
\echo 'Salon Settings table (daily_report_enabled):'
SELECT
    'salon_settings_daily_report_enabled_0' as check_name,
    COUNT(*) as count
FROM salon_settings
WHERE daily_report_enabled = 0
UNION ALL
SELECT
    'salon_settings_daily_report_enabled_1',
    COUNT(*)
FROM salon_settings
WHERE daily_report_enabled = 1
UNION ALL
SELECT
    'salon_settings_total',
    COUNT(*)
FROM salon_settings;

\echo ''

-- Staff table
\echo 'Staff table (is_active, is_public):'
SELECT
    'staff_is_active_0' as check_name,
    COUNT(*) as count
FROM staff
WHERE is_active = 0
UNION ALL
SELECT
    'staff_is_active_1',
    COUNT(*)
FROM staff
WHERE is_active = 1
UNION ALL
SELECT
    'staff_is_public_0',
    COUNT(*)
FROM staff
WHERE is_public = 0
UNION ALL
SELECT
    'staff_is_public_1',
    COUNT(*)
FROM staff
WHERE is_public = 1
UNION ALL
SELECT
    'staff_total',
    COUNT(*)
FROM staff;

\echo ''

-- Services table
\echo 'Services table (is_active):'
SELECT
    'services_is_active_0' as check_name,
    COUNT(*) as count
FROM services
WHERE is_active = 0
UNION ALL
SELECT
    'services_is_active_1',
    COUNT(*)
FROM services
WHERE is_active = 1
UNION ALL
SELECT
    'services_total',
    COUNT(*)
FROM services;

\echo ''
\echo '=========================================='
\echo 'Verification Complete'
\echo '=========================================='
\echo ''
\echo 'Save these counts for post-migration verification!'
\echo ''
