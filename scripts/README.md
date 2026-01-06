# Boolean Migration - Deployment Scripts

## 📋 Overview

Professional deployment scripts for migrating PostgreSQL SMALLINT boolean columns to proper BOOLEAN type.

**Created**: 2024-12-28  
**Author**: Senior Developer  
**Risk Level**: Low (with proper backup)  
**Estimated Downtime**: 2-5 minutes

---

## 🚀 Quick Start (Production)

### Option 1: Automated Deployment (Recommended)

```bash
cd /var/www/vhosts/frizerino.com/api.frizerino.com
bash scripts/deploy_boolean_migration.sh
```

This script will:
1. ✅ Check database connection
2. ✅ Create full backup
3. ✅ Document pre-migration data counts
4. ✅ Enable maintenance mode
5. ✅ Run migration
6. ✅ Verify data integrity
7. ✅ Clear cache
8. ✅ Disable maintenance mode

### Option 2: Manual Step-by-Step

```bash
# 1. Create backup
bash scripts/backup_before_boolean_migration.sh

# 2. Document current data
psql -h localhost -U a0hcym59d1rhk -d frizerinodb -f scripts/verify_data_before_migration.sql > pre_migration_counts.txt

# 3. Enable maintenance mode
php artisan down

# 4. Run migration
php artisan migrate --force

# 5. Verify data integrity
psql -h localhost -U a0hcym59d1rhk -d frizerinodb -f scripts/verify_data_after_migration.sql > post_migration_counts.txt

# 6. Clear cache
php artisan config:clear
php artisan cache:clear

# 7. Disable maintenance mode
php artisan up
```

---

## 🔄 Rollback (If Needed)

### Option 1: Automated Rollback

```bash
bash scripts/rollback_boolean_migration.sh
```

### Option 2: Manual Rollback

```bash
# Enable maintenance mode
php artisan down

# Rollback migration
php artisan migrate:rollback --force

# Clear cache
php artisan config:clear
php artisan cache:clear

# Disable maintenance mode
php artisan up
```

### Option 3: Restore from Backup

```bash
# Enable maintenance mode
php artisan down

# Restore backup (replace TIMESTAMP with actual timestamp)
gunzip /var/www/vhosts/frizerino.com/backups/backup_before_boolean_TIMESTAMP.sql.gz
psql -h localhost -U a0hcym59d1rhk -d frizerinodb < /var/www/vhosts/frizerino.com/backups/backup_before_boolean_TIMESTAMP.sql

# Disable maintenance mode
php artisan up
```

---

## 📁 Script Files

### 1. `deploy_boolean_migration.sh`
**Purpose**: Orchestrate complete migration process  
**Usage**: `bash scripts/deploy_boolean_migration.sh`  
**Features**:
- Interactive confirmation prompts
- Automatic backup creation
- Data integrity verification
- Rollback on failure
- Colored output for clarity

### 2. `backup_before_boolean_migration.sh`
**Purpose**: Create full database backup  
**Usage**: `bash scripts/backup_before_boolean_migration.sh`  
**Output**: `/var/www/vhosts/frizerino.com/backups/backup_before_boolean_TIMESTAMP.sql.gz`

### 3. `rollback_boolean_migration.sh`
**Purpose**: Safely rollback migration  
**Usage**: `bash scripts/rollback_boolean_migration.sh`  
**Features**:
- Automatic maintenance mode
- Safe rollback to SMALLINT
- Cache clearing

### 4. `verify_data_before_migration.sql`
**Purpose**: Document pre-migration data counts  
**Usage**: `psql -h localhost -U a0hcym59d1rhk -d frizerinodb -f scripts/verify_data_before_migration.sql`  
**Output**: Counts for all boolean columns (0/1 values)

### 5. `verify_data_after_migration.sql`
**Purpose**: Verify post-migration data integrity  
**Usage**: `psql -h localhost -U a0hcym59d1rhk -d frizerinodb -f scripts/verify_data_after_migration.sql`  
**Output**: Counts for all boolean columns (false/true values)

---

## ✅ Pre-Migration Checklist

- [ ] Read `BOOLEAN_MIGRATION_GUIDE.md`
- [ ] Review migration file: `database/migrations/2024_12_27_200000_convert_smallint_to_boolean.php`
- [ ] Test on staging environment (recommended)
- [ ] Notify team of maintenance window
- [ ] Ensure backup directory exists: `/var/www/vhosts/frizerino.com/backups/`
- [ ] Verify database credentials
- [ ] Schedule maintenance window (5-10 minutes)

---

## 📊 What Gets Migrated

### Tables and Columns (19 tables, 30+ columns)

| Table | Columns |
|-------|---------|
| users | is_guest |
| appointments | is_guest |
| widget_settings | is_active |
| salon_settings | daily_report_enabled, daily_report_include_staff, daily_report_include_services, daily_report_include_capacity, daily_report_include_cancellations |
| staff | is_active, is_public, accepts_bookings, auto_confirm |
| services | is_active |
| locations | is_active |
| job_ads | is_active |
| homepage_categories | is_enabled |
| notifications | is_read |
| reviews | is_verified |
| staff_portfolio | is_featured |
| user_consents | accepted |
| service_images | is_featured |
| salon_images | is_primary |
| staff_breaks | is_active |
| staff_vacations | is_active |
| salon_breaks | is_active |
| salon_vacations | is_active |

### Data Conversion

```sql
-- BEFORE (SMALLINT)
0 → false
1 → true

-- AFTER (BOOLEAN)
false → false
true → true
```

**No data loss!** PostgreSQL `USING column::boolean` safely converts values.

---

## 🔍 Verification

### Compare Pre/Post Migration Counts

```bash
# Pre-migration
cat scripts/pre_migration_counts.txt | grep "users_is_guest"
# Expected: users_is_guest_0 | 50
#           users_is_guest_1 | 10

# Post-migration
cat scripts/post_migration_counts.txt | grep "users_is_guest"
# Expected: users_is_guest_false | 50  (same as 0)
#           users_is_guest_true  | 10  (same as 1)
```

### Test Critical Paths

```bash
# 1. Test widget booking
curl -X POST https://api.frizerino.com/api/v1/widget/book \
  -H "Content-Type: application/json" \
  -d '{"api_key":"test_key",...}'

# 2. Test daily reports
php artisan reports:send-daily --date=$(date +%Y-%m-%d)

# 3. Check logs for errors
tail -f storage/logs/laravel.log | grep -i "error\|exception"
```

---

## ⏱️ Timeline

| Phase | Duration | Description |
|-------|----------|-------------|
| Backup | 30-60s | Create compressed database backup |
| Data verification | 5-10s | Document current counts |
| Maintenance mode | 1s | Enable maintenance mode |
| Migration | 2-5s | ALTER TABLE operations |
| Data verification | 5-10s | Verify data integrity |
| Cache clear | 2-3s | Clear application cache |
| Maintenance mode | 1s | Disable maintenance mode |
| **Total** | **2-5 min** | **Complete process** |

---

## 🚨 Troubleshooting

### Migration Fails

```bash
# Check error message
tail -100 storage/logs/laravel.log

# Rollback
bash scripts/rollback_boolean_migration.sh

# Or restore backup
psql -h localhost -U a0hcym59d1rhk -d frizerinodb < backup_file.sql
```

### Data Counts Don't Match

```bash
# Check for NULL values
psql -h localhost -U a0hcym59d1rhk -d frizerinodb -c "
SELECT COUNT(*) FROM users WHERE is_guest IS NULL;
"

# If NULLs exist, fix them before migration
psql -h localhost -U a0hcym59d1rhk -d frizerinodb -c "
UPDATE users SET is_guest = 0 WHERE is_guest IS NULL;
"
```

### Application Errors After Migration

```bash
# Check for type mismatch errors
grep "operator does not exist" storage/logs/laravel.log

# If found, code still uses 1/0 instead of true/false
# Deploy code changes (Task 4 in tasks.md)
```

---

## 📝 Post-Migration Tasks

After successful migration:

1. **Deploy Code Changes** (Task 4-9 in `tasks.md`)
   - Revert WidgetController to use `true/false`
   - Revert SendDailyReportsCommand to use `true/false`
   - Fix all whereRaw() calls

2. **Monitor Application**
   - Watch logs for 30 minutes
   - Test widget booking
   - Test daily reports
   - Verify no 500 errors

3. **Update Documentation**
   - Mark migration as complete
   - Document any issues encountered
   - Update team on changes

---

## 🎯 Success Criteria

Migration is successful when:

- ✅ All SMALLINT columns converted to BOOLEAN
- ✅ Data counts match pre-migration (0→false, 1→true)
- ✅ No errors in migration process
- ✅ Application starts successfully
- ✅ Widget booking works
- ✅ Daily reports work
- ✅ No type mismatch errors in logs
- ✅ Downtime < 5 minutes

---

## 📞 Support

If you encounter issues:

1. Check `storage/logs/laravel.log`
2. Check PostgreSQL logs: `/var/log/postgresql/*.log`
3. Review `BOOLEAN_MIGRATION_GUIDE.md`
4. Check `BOOLEAN_FIX_PRIORITY.md` for code fixes
5. Rollback if necessary

---

**Last Updated**: 2024-12-28  
**Status**: Ready for Production  
**Tested**: Staging ✅ | Production ⏳
