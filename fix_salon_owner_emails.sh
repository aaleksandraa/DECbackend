#!/bin/bash

# Fix Salon Owner Emails
# This script fixes the issue where salon owners don't receive appointment emails

echo "=========================================="
echo "Fix Salon Owner Emails"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: artisan file not found. Please run this script from the backend directory.${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Diagnosing the problem...${NC}"
php diagnose_salon_owner_email.php
echo ""

echo -e "${YELLOW}Step 2: Fixing email_notifications_enabled...${NC}"
php artisan tinker --execute="
\$updated = DB::table('salons')
    ->whereNull('email_notifications_enabled')
    ->orWhere('email_notifications_enabled', false)
    ->update(['email_notifications_enabled' => true]);

echo \"Updated \$updated salon(s)\n\";

\$total = DB::table('salons')->count();
\$enabled = DB::table('salons')->where('email_notifications_enabled', true)->count();

echo \"Total salons: \$total\n\";
echo \"Enabled: \$enabled\n\";
"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Fixed email_notifications_enabled${NC}"
else
    echo -e "${RED}✗ Failed to fix email_notifications_enabled${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}Step 3: Verifying the fix...${NC}"
php diagnose_salon_owner_email.php | grep -A 5 "Result:"
echo ""

echo -e "${YELLOW}Step 4: Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

echo "=========================================="
echo -e "${GREEN}Fix completed!${NC}"
echo "=========================================="
echo ""
echo "What was fixed:"
echo "  - Set email_notifications_enabled = true for all salons"
echo "  - Salon owners will now receive appointment emails"
echo ""
echo "Next steps:"
echo "  1. Create a test appointment"
echo "  2. Check salon owner's email inbox"
echo "  3. Check logs: tail -f storage/logs/laravel.log | grep -i 'mail'"
echo ""
