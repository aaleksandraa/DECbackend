-- Boolean Migration - Smart Data Verification Script
-- Works with both SMALLINT and BOOLEAN columns
-- Purpose: Document current data counts regardless of column type

\echo '=========================================='
\echo 'Boolean Migration - Smart Data Verification'
\echo '=========================================='
\echo ''

-- Check column types first
\echo 'Column Types:'
SELECT
    table_name,
    column_name,
    data_type
FROM information_schema.columns
WHERE table_name IN ('users', 'appointments', 'widget_settings', 'salon_settings', 'staff', 'services')
    AND column_name IN ('is_guest', 'is_active', 'is_public', 'daily_report_enabled')
ORDER BY table_name, column_name;

\echo ''
\echo '=========================================='
\echo 'Data Counts (works with both SMALLINT and BOOLEAN)'
\echo '=========================================='
\echo ''

-- Users table (is_guest)
\echo 'Users table (is_guest):'
SELECT
    'users_false_or_0' as check_name,
    COUNT(*) as count
FROM users
WHERE (CASE
    WHEN pg_typeof(is_guest)::text = 'boolean' THEN is_guest = false
    ELSE is_guest::int = 0
END)
UNION ALL
SELECT
    'users_true_or_1',
    COUNT(*)
FROM users
WHERE (CASE
    WHEN pg_typeof(is_guest)::text = 'boolean' THEN is_guest = true
    ELSE is_guest::int = 1
END)
UNION ALL
SELECT
    'users_total',
    COUNT(*)
FROM users;

\echo ''

-- Appointments table (is_guest)
\echo 'Appointments table (is_guest):'
SELECT
    'appointments_false_or_0' as check_name,
    COUNT(*) as count
FROM appointments
WHERE (CASE
    WHEN pg_typeof(is_guest)::text = 'boolean' THEN is_guest = false
    ELSE is_guest::int = 0
END)
UNION ALL
SELECT
    'appointments_true_or_1',
    COUNT(*)
FROM appointments
WHERE (CASE
    WHEN pg_typeof(is_guest)::text = 'boolean' THEN is_guest = true
    ELSE is_guest::int = 1
END)
UNION ALL
SELECT
    'appointments_total',
    COUNT(*)
FROM appointments;

\echo ''

-- Widget Settings table (is_active)
\echo 'Widget Settings table (is_active):'
SELECT
    'widget_settings_false_or_0' as check_name,
    COUNT(*) as count
FROM widget_settings
WHERE (CASE
    WHEN pg_typeof(is_active)::text = 'boolean' THEN is_active = false
    ELSE is_active::int = 0
END)
UNION ALL
SELECT
    'widget_settings_true_or_1',
    COUNT(*)
FROM widget_settings
WHERE (CASE
    WHEN pg_typeof(is_active)::text = 'boolean' THEN is_active = true
    ELSE is_active::int = 1
END)
UNION ALL
SELECT
    'widget_settings_total',
    COUNT(*)
FROM widget_settings;

\echo ''

-- Staff table (is_active)
\echo 'Staff table (is_active):'
SELECT
    'staff_is_active_false_or_0' as check_name,
    COUNT(*) as count
FROM staff
WHERE (CASE
    WHEN pg_typeof(is_active)::text = 'boolean' THEN is_active = false
    ELSE is_active::int = 0
END)
UNION ALL
SELECT
    'staff_is_active_true_or_1',
    COUNT(*)
FROM staff
WHERE (CASE
    WHEN pg_typeof(is_active)::text = 'boolean' THEN is_active = true
    ELSE is_active::int = 1
END)
UNION ALL
SELECT
    'staff_total',
    COUNT(*)
FROM staff;

\echo ''

-- Services table (is_active)
\echo 'Services table (is_active):'
SELECT
    'services_is_active_false_or_0' as check_name,
    COUNT(*) as count
FROM services
WHERE (CASE
    WHEN pg_typeof(is_active)::text = 'boolean' THEN is_active = false
    ELSE is_active::int = 0
END)
UNION ALL
SELECT
    'services_is_active_true_or_1',
    COUNT(*)
FROM services
WHERE (CASE
    WHEN pg_typeof(is_active)::text = 'boolean' THEN is_active = true
    ELSE is_active::int = 1
END)
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
