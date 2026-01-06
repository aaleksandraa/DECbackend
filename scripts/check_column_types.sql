-- Quick Diagnostic: Check which columns are SMALLINT vs BOOLEAN
-- Run this to see current state before migration

\echo '=========================================='
\echo 'Boolean Column Type Check'
\echo '=========================================='
\echo ''

SELECT
    table_name,
    column_name,
    data_type,
    CASE
        WHEN data_type = 'smallint' THEN '❌ NEEDS MIGRATION'
        WHEN data_type = 'boolean' THEN '✅ ALREADY BOOLEAN'
        ELSE '⚠️  UNEXPECTED TYPE'
    END as status
FROM information_schema.columns
WHERE table_name IN (
    'users', 'appointments', 'widget_settings', 'salon_settings',
    'staff', 'services', 'locations', 'job_ads',
    'homepage_categories', 'notifications', 'reviews',
    'staff_portfolio', 'user_consents', 'service_images',
    'salon_images', 'staff_breaks', 'staff_vacations',
    'salon_breaks', 'salon_vacations'
)
AND (
    column_name LIKE '%is_%'
    OR column_name LIKE '%accepted%'
    OR column_name LIKE '%enabled%'
    OR column_name = 'auto_confirm'
)
ORDER BY
    CASE
        WHEN data_type = 'smallint' THEN 1
        WHEN data_type = 'boolean' THEN 2
        ELSE 3
    END,
    table_name,
    column_name;

\echo ''
\echo '=========================================='
\echo 'Summary'
\echo '=========================================='

SELECT
    data_type,
    COUNT(*) as count,
    CASE
        WHEN data_type = 'smallint' THEN 'Need to migrate'
        WHEN data_type = 'boolean' THEN 'Already migrated'
        ELSE 'Check manually'
    END as action
FROM information_schema.columns
WHERE table_name IN (
    'users', 'appointments', 'widget_settings', 'salon_settings',
    'staff', 'services', 'locations', 'job_ads',
    'homepage_categories', 'notifications', 'reviews',
    'staff_portfolio', 'user_consents', 'service_images',
    'salon_images', 'staff_breaks', 'staff_vacations',
    'salon_breaks', 'salon_vacations'
)
AND (
    column_name LIKE '%is_%'
    OR column_name LIKE '%accepted%'
    OR column_name LIKE '%enabled%'
    OR column_name = 'auto_confirm'
)
GROUP BY data_type
ORDER BY data_type;

\echo ''
