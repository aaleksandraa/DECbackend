#!/bin/bash

# Boolean Migration - Production Deployment Script
# Created: 2024-12-28
# Purpose: Orchestrate safe SMALLINT to BOOLEAN migration
# Author: Senior Developer

set -e  # Exit on error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DB_USER="a0hcym59d1rhk"
DB_NAME="frizerinodb"
DB_HOST="localhost"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo ""
echo "=========================================="
echo "Boolean Migration - Production Deployment"
echo "=========================================="
echo ""

# Function to prompt for confirmation
confirm() {
    read -p "$1 (yes/no): " response
    case "$response" in
        [yY][eE][sS]|[yY])
            return 0
            ;;
        *)
            echo "Aborted."
            exit 1
            ;;
    esac
}

# Step 1: Pre-flight checks
echo -e "${BLUE}Step 1: Pre-flight checks${NC}"
echo "Checking database connection..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Database connection OK${NC}"
else
    echo -e "${RED}❌ Database connection failed${NC}"
    exit 1
fi

echo "Checking migration status..."
cd "$PROJECT_DIR"
php artisan migrate:status | grep "convert_smallint_to_boolean"
echo ""

confirm "Continue with migration?"

# Step 2: Create backup
echo ""
echo -e "${BLUE}Step 2: Creating database backup${NC}"
bash "${SCRIPT_DIR}/backup_before_boolean_migration.sh"
echo ""

confirm "Backup created successfully. Continue?"

# Step 3: Document pre-migration data
echo ""
echo -e "${BLUE}Step 3: Documenting pre-migration data counts${NC}"
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "${SCRIPT_DIR}/verify_data_smart.sql" > "${SCRIPT_DIR}/pre_migration_counts.txt" 2>&1
echo -e "${GREEN}✅ Pre-migration counts saved to: ${SCRIPT_DIR}/pre_migration_counts.txt${NC}"
cat "${SCRIPT_DIR}/pre_migration_counts.txt"
echo ""

confirm "Data counts documented. Continue with migration?"

# Step 4: Enable maintenance mode
echo ""
echo -e "${BLUE}Step 4: Enabling maintenance mode${NC}"
php artisan down
echo -e "${YELLOW}⚠️  Application is now in maintenance mode${NC}"
echo ""

# Step 5: Run migration
echo ""
echo -e "${BLUE}Step 5: Running migration${NC}"
START_TIME=$(date +%s)

if php artisan migrate --force; then
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    echo -e "${GREEN}✅ Migration completed successfully in ${DURATION} seconds${NC}"
else
    echo -e "${RED}❌ Migration failed!${NC}"
    echo "Rolling back..."
    php artisan migrate:rollback --force
    php artisan up
    echo -e "${RED}Application restored. Check logs for details.${NC}"
    exit 1
fi
echo ""

# Step 6: Verify data integrity
echo ""
echo -e "${BLUE}Step 6: Verifying data integrity${NC}"
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "${SCRIPT_DIR}/verify_data_smart.sql" > "${SCRIPT_DIR}/post_migration_counts.txt" 2>&1
echo -e "${GREEN}✅ Post-migration counts saved to: ${SCRIPT_DIR}/post_migration_counts.txt${NC}"
cat "${SCRIPT_DIR}/post_migration_counts.txt"
echo ""

echo "Comparing counts..."
echo "Pre-migration counts:"
grep "count" "${SCRIPT_DIR}/pre_migration_counts.txt" | head -20
echo ""
echo "Post-migration counts:"
grep "count" "${SCRIPT_DIR}/post_migration_counts.txt" | head -20
echo ""

confirm "Data integrity verified. Continue?"

# Step 7: Clear cache
echo ""
echo -e "${BLUE}Step 7: Clearing cache${NC}"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo -e "${GREEN}✅ Cache cleared${NC}"
echo ""

# Step 8: Disable maintenance mode
echo ""
echo -e "${BLUE}Step 8: Disabling maintenance mode${NC}"
php artisan up
echo -e "${GREEN}✅ Application is now live${NC}"
echo ""

# Step 9: Summary
echo ""
echo "=========================================="
echo -e "${GREEN}Migration Completed Successfully!${NC}"
echo "=========================================="
echo ""
echo "Summary:"
echo "  - Migration duration: ${DURATION} seconds"
echo "  - Backup location: /var/www/vhosts/frizerino.com/backups/"
echo "  - Pre-migration counts: ${SCRIPT_DIR}/pre_migration_counts.txt"
echo "  - Post-migration counts: ${SCRIPT_DIR}/post_migration_counts.txt"
echo ""
echo "Next steps:"
echo "  1. Monitor application logs: tail -f storage/logs/laravel.log"
echo "  2. Test widget booking on external sites"
echo "  3. Test daily report generation"
echo "  4. Deploy code changes (revert 1/0 to true/false)"
echo ""
echo "Rollback (if needed):"
echo "  php artisan migrate:rollback --force"
echo ""
