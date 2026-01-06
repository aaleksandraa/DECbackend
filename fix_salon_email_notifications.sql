-- Fix Salon Email Notifications
-- This ensures all salons have email_notifications_enabled set to TRUE

-- Check current status
SELECT
    id,
    name,
    email_notifications_enabled,
    CASE
        WHEN email_notifications_enabled IS NULL THEN 'NULL'
        WHEN email_notifications_enabled = true THEN 'TRUE'
        WHEN email_notifications_enabled = false THEN 'FALSE'
        ELSE 'UNKNOWN'
    END as status
FROM salons
ORDER BY id;

-- Update all salons to have email notifications enabled
UPDATE salons
SET email_notifications_enabled = true
WHERE email_notifications_enabled IS NULL
   OR email_notifications_enabled = false;

-- Verify the fix
SELECT
    COUNT(*) as total_salons,
    SUM(CASE WHEN email_notifications_enabled = true THEN 1 ELSE 0 END) as enabled,
    SUM(CASE WHEN email_notifications_enabled = false THEN 1 ELSE 0 END) as disabled,
    SUM(CASE WHEN email_notifications_enabled IS NULL THEN 1 ELSE 0 END) as null_values
FROM salons;

-- Show salon owners who will receive emails
SELECT
    s.id as salon_id,
    s.name as salon_name,
    s.email_notifications_enabled,
    u.id as owner_id,
    u.name as owner_name,
    u.email as owner_email,
    CASE
        WHEN u.email IS NOT NULL AND s.email_notifications_enabled = true THEN 'WILL RECEIVE'
        WHEN u.email IS NULL THEN 'NO EMAIL'
        WHEN s.email_notifications_enabled = false THEN 'DISABLED'
        ELSE 'WILL NOT RECEIVE'
    END as email_status
FROM salons s
JOIN users u ON s.owner_id = u.id
ORDER BY s.id;
