-- ============================================
-- FIX DATABASE PERMISSIONS
-- ============================================
-- Run this as PostgreSQL superuser (postgres)
-- Replace 'frizerino_user' with your actual DB_USERNAME from .env
-- ============================================

-- 1. Grant all necessary permissions to application user
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO frizerino_user;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO frizerino_user;

-- 2. Set default privileges for future tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO frizerino_user;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
GRANT USAGE, SELECT ON SEQUENCES TO frizerino_user;

-- 3. Specifically grant on homepage_categories (the problematic table)
GRANT ALL PRIVILEGES ON TABLE homepage_categories TO frizerino_user;
GRANT USAGE, SELECT ON SEQUENCE homepage_categories_id_seq TO frizerino_user;

-- 4. Verify permissions
SELECT
    schemaname,
    tablename,
    tableowner
FROM pg_tables
WHERE schemaname = 'public'
AND tablename = 'homepage_categories';

SELECT
    grantee,
    table_name,
    privilege_type
FROM information_schema.table_privileges
WHERE table_name = 'homepage_categories'
ORDER BY grantee, privilege_type;
