#!/bin/bash

echo "=========================================="
echo "Deploying Appointment Reminder Fix"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: Must be run from backend directory${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Testing reminder logic...${NC}"
php test_reminder_logic.php
echo ""

echo -e "${YELLOW}Step 2: Clearing failed reminder jobs...${NC}"
php clear_failed_reminder_jobs.php
echo ""

echo -e "${YELLOW}Step 3: Restarting queue worker...${NC}"
php artisan queue:restart
echo ""

echo -e "${YELLOW}Step 4: Testing reminder command (dry run)...${NC}"
php artisan appointments:send-reminders
echo ""

echo -e "${GREEN}=========================================="
echo "Deployment Complete!"
echo "==========================================${NC}"
echo ""
echo "What was fixed:"
echo "  ✓ In-app notifications only for registered users"
echo "  ✓ Email reminders for both registered users AND guest bookings"
echo "  ✓ Guest bookings with client_email now get reminders"
echo "  ✓ Failed jobs cleared from queue"
echo "  ✓ Queue worker restarted"
echo ""
echo "Reminder Logic:"
echo "  • Registered users → In-app notification + Email"
echo "  • Guest bookings with email → Email only"
echo "  • Guest bookings without email → Skipped (contact by phone)"
echo ""
echo "Next steps:"
echo "  1. Monitor logs: tail -f storage/logs/laravel.log"
echo "  2. Check queue: php artisan queue:work --once"
echo "  3. Test with real data: php test_reminder_logic.php"
echo ""
