-- ============================================
-- FIX PRODUCTION DATABASE ISSUES
-- ============================================
-- Problem 1: Missing is_guest column in users table
-- Problem 2: Missing is_guest column in appointments table
-- Problem 3: Database permissions for homepage_categories
-- ============================================

-- 1. Add is_guest column to users table if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'is_guest'
    ) THEN
        ALTER TABLE users ADD COLUMN is_guest BOOLEAN NOT NULL DEFAULT false;
        RAISE NOTICE 'Added is_guest column to users table';
    ELSE
        RAISE NOTICE 'is_guest column already exists in users table';
    END IF;
END $$;

-- 2. Add created_via column to users table if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'created_via'
    ) THEN
        ALTER TABLE users ADD COLUMN created_via VARCHAR(50) DEFAULT 'manual';
        RAISE NOTICE 'Added created_via column to users table';
    ELSE
        RAISE NOTICE 'created_via column already exists in users table';
    END IF;
END $$;

-- 3. Add is_guest column to appointments table if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'appointments' AND column_name = 'is_guest'
    ) THEN
        ALTER TABLE appointments ADD COLUMN is_guest BOOLEAN NOT NULL DEFAULT false;
        RAISE NOTICE 'Added is_guest column to appointments table';
    ELSE
        RAISE NOTICE 'is_guest column already exists in appointments table';
    END IF;
END $$;

-- 4. Fix database permissions for homepage_categories table
-- Grant SELECT, INSERT, UPDATE, DELETE to the application user
DO $$
DECLARE
    app_user TEXT;
BEGIN
    -- Get the current database user (usually from .env DB_USERNAME)
    -- Replace 'your_db_user' with actual username from .env
    app_user := current_user;

    -- Grant permissions on homepage_categories
    EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE homepage_categories TO %I', app_user);
    EXECUTE format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %I', app_user);

    RAISE NOTICE 'Granted permissions to user: %', app_user;
END $$;

-- 5. Verify the changes
SELECT
    'users' as table_name,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_name = 'users'
AND column_name IN ('is_guest', 'created_via')
UNION ALL
SELECT
    'appointments' as table_name,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_name = 'appointments'
AND column_name = 'is_guest';

-- 6. Check current permissions
SELECT
    grantee,
    table_name,
    privilege_type
FROM information_schema.table_privileges
WHERE table_name = 'homepage_categories'
ORDER BY grantee, privilege_type;
