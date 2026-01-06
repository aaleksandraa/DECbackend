-- Add service_ids column to appointments table
-- This is a direct SQL alternative to the migration

-- Check if column already exists
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'appointments'
        AND column_name = 'service_ids'
    ) THEN
        -- Add service_ids column
        ALTER TABLE appointments ADD COLUMN service_ids JSON NULL;
        RAISE NOTICE 'Added service_ids column';
    ELSE
        RAISE NOTICE 'service_ids column already exists';
    END IF;
END $$;

-- Make service_id nullable (if not already)
DO $$
BEGIN
    ALTER TABLE appointments ALTER COLUMN service_id DROP NOT NULL;
    RAISE NOTICE 'Made service_id nullable';
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'service_id is already nullable or error occurred: %', SQLERRM;
END $$;

-- Verify the changes
SELECT
    column_name,
    data_type,
    is_nullable
FROM information_schema.columns
WHERE table_name = 'appointments'
AND column_name IN ('service_id', 'service_ids')
ORDER BY column_name;
