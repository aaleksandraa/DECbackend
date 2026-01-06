# 🔧 Production Database Fix Instructions

## Problem Summary
1. ❌ Missing `is_guest` column in `users` table
2. ❌ Missing `is_guest` column in `appointments` table  
3. ❌ Permission denied for `homepage_categories` table

## Solution Steps

### Step 1: Check Database User
```bash
cd /var/www/vhosts/frizerino.com/api.frizerino.com
cat .env | grep DB_
```

Note down:
- `DB_USERNAME` (e.g., frizerino_user)
- `DB_DATABASE` (e.g., frizerino_db)

### Step 2: Fix Missing Columns
```bash
# Connect to PostgreSQL as application user
psql -U frizerino_user -d frizerino_db -f fix_production_database.sql
```

This will:
- ✅ Add `is_guest` column to `users` table
- ✅ Add `created_via` column to `users` table
- ✅ Add `is_guest` column to `appointments` table
- ✅ Grant basic permissions

### Step 3: Fix Permissions (as superuser)
```bash
# Connect as postgres superuser
sudo -u postgres psql frizerino_db

# Then run:
\i /var/www/vhosts/frizerino.com/api.frizerino.com/fix_production_permissions.sql
```

**OR** manually edit `fix_production_permissions.sql` to replace `frizerino_user` with your actual username, then:
```bash
sudo -u postgres psql frizerino_db -f fix_production_permissions.sql
```

### Step 4: Verify Fix
```bash
cd /var/www/vhosts/frizerino.com/api.frizerino.com
php artisan tinker
```

Then in tinker:
```php
// Test 1: Check users table
DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users' AND column_name IN ('is_guest', 'created_via')");

// Test 2: Check appointments table  
DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'appointments' AND column_name = 'is_guest'");

// Test 3: Check homepage_categories permissions
DB::table('homepage_categories')->count();

// Test 4: Try creating a guest user (like widget does)
$user = \App\Models\User::create([
    'name' => 'Test Guest',
    'email' => 'test@example.com',
    'phone' => '123456789',
    'password' => bcrypt('test'),
    'role' => 'klijent',
    'is_guest' => true,
    'created_via' => 'test'
]);
echo "✅ User created with ID: " . $user->id;
$user->delete(); // Clean up
```

### Step 5: Test Widget Booking
Try booking through widget at:
```
https://frizerino.com/widget/[salon-slug]
```

## Quick One-Liner Fix (if you have superuser access)
```bash
cd /var/www/vhosts/frizerino.com/api.frizerino.com
sudo -u postgres psql frizerino_db << 'EOF'
-- Add missing columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_guest BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_via VARCHAR(50) DEFAULT 'manual';
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS is_guest BOOLEAN NOT NULL DEFAULT false;

-- Fix permissions (replace frizerino_user with your DB_USERNAME)
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO frizerino_user;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO frizerino_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO frizerino_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO frizerino_user;

-- Verify
SELECT 'users.is_guest' as check_name, COUNT(*) as exists FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'is_guest'
UNION ALL
SELECT 'appointments.is_guest', COUNT(*) FROM information_schema.columns WHERE table_name = 'appointments' AND column_name = 'is_guest'
UNION ALL
SELECT 'homepage_categories perms', COUNT(*) FROM information_schema.table_privileges WHERE table_name = 'homepage_categories' AND grantee = 'frizerino_user';
EOF
```

## Alternative: Run Migrations
If you prefer to use Laravel migrations:
```bash
cd /var/www/vhosts/frizerino.com/api.frizerino.com
php artisan migrate --force
```

**Note:** This will run ALL pending migrations, which might include other changes.

## Troubleshooting

### Error: "permission denied for schema public"
```sql
-- As postgres superuser:
GRANT ALL ON SCHEMA public TO frizerino_user;
```

### Error: "must be owner of table"
```sql
-- As postgres superuser:
ALTER TABLE homepage_categories OWNER TO frizerino_user;
ALTER TABLE users OWNER TO frizerino_user;
ALTER TABLE appointments OWNER TO frizerino_user;
```

### Check Current User Permissions
```sql
SELECT 
    grantee,
    table_schema,
    table_name,
    privilege_type
FROM information_schema.table_privileges
WHERE grantee = 'frizerino_user'
ORDER BY table_name, privilege_type;
```

## After Fix
1. ✅ Widget booking should work
2. ✅ Guest users can be created
3. ✅ Homepage categories should load
4. ✅ No more "column does not exist" errors
5. ✅ No more "permission denied" errors

## Need Help?
If errors persist, check Laravel logs:
```bash
tail -f /var/www/vhosts/frizerino.com/api.frizerino.com/storage/logs/laravel.log
```
