#!/bin/bash

# Boolean Migration - Rollback Script
# Created: 2024-12-28
# Purpose: Safely rollback BOOLEAN migration if needed
# Author: Senior Developer

set -e  # Exit on error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo ""
echo "=========================================="
echo "Boolean Migration - ROLLBACK"
echo "=========================================="
echo ""
echo -e "${RED}⚠️  WARNING: This will rollback the boolean migration${NC}"
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

confirm "Are you sure you want to rollback the migration?"

# Step 1: Enable maintenance mode
echo ""
echo -e "${BLUE}Step 1: Enabling maintenance mode${NC}"
cd "$PROJECT_DIR"
php artisan down --message="Rolling back database changes" --retry=60
echo -e "${YELLOW}⚠️  Application is now in maintenance mode${NC}"
echo ""

# Step 2: Rollback migration
echo ""
echo -e "${BLUE}Step 2: Rolling back migration${NC}"
START_TIME=$(date +%s)

if php artisan migrate:rollback --force; then
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    echo -e "${GREEN}✅ Rollback completed successfully in ${DURATION} seconds${NC}"
else
    echo -e "${RED}❌ Rollback failed!${NC}"
    echo "You may need to restore from backup manually."
    exit 1
fi
echo ""

# Step 3: Clear cache
echo ""
echo -e "${BLUE}Step 3: Clearing cache${NC}"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo -e "${GREEN}✅ Cache cleared${NC}"
echo ""

# Step 4: Disable maintenance mode
echo ""
echo -e "${BLUE}Step 4: Disabling maintenance mode${NC}"
php artisan up
echo -e "${GREEN}✅ Application is now live${NC}"
echo ""

# Step 5: Summary
echo ""
echo "=========================================="
echo -e "${GREEN}Rollback Completed Successfully!${NC}"
echo "=========================================="
echo ""
echo "Summary:"
echo "  - Rollback duration: ${DURATION} seconds"
echo "  - Database columns reverted to SMALLINT"
echo "  - Application is using 1/0 values again"
echo ""
echo "Note:"
echo "  - Current code uses 1/0 in WHERE clauses (temporary workaround)"
echo "  - This is working but not the senior-level solution"
echo "  - Consider re-running migration after fixing any issues"
echo ""
