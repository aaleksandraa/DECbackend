-- Fix appointments.is_guest column to BOOLEAN type
-- This script safely converts SMALLINT to BOOLEAN

-- Step 1: Drop any constraints that might interfere
ALTER TABLE appointments ALTER COLUMN is_guest DROP DEFAULT;

-- Step 2: Convert the column type
-- Using CASE to handle any edge cases
ALTER TABLE appointments
ALTER COLUMN is_guest TYPE BOOLEAN
USING CASE
    WHEN is_guest = 0 THEN false
    WHEN is_guest = 1 THEN true
    ELSE false
END;

-- Step 3: Set default value
ALTER TABLE appointments ALTER COLUMN is_guest SET DEFAULT false;

-- Step 4: Set NOT NULL constraint
ALTER TABLE appointments ALTER COLUMN is_guest SET NOT NULL;

-- Verify the change
SELECT
    column_name,
    data_type,
    column_default,
    is_nullable
FROM information_schema.columns
WHERE table_name = 'appointments'
AND column_name = 'is_guest';
