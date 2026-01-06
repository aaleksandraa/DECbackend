#!/bin/bash

# Email Notifications Fix Deployment Script
# Fixes: Salon owners and staff not receiving appointment notification emails

echo "=========================================="
echo "Email Notifications Fix Deployment"
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

echo -e "${YELLOW}Step 1: Pulling latest changes...${NC}"
git pull origin main
if [ $? -ne 0 ]; then
    echo -e "${RED}Git pull failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Git pull successful${NC}"
echo ""

echo -e "${YELLOW}Step 2: Installing/updating dependencies...${NC}"
composer install --no-dev --optimize-autoloader
if [ $? -ne 0 ]; then
    echo -e "${RED}Composer install failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Dependencies updated${NC}"
echo ""

echo -e "${YELLOW}Step 3: Running migration...${NC}"
php artisan migrate --force
if [ $? -ne 0 ]; then
    echo -e "${RED}Migration failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Migration completed${NC}"
echo ""

echo -e "${YELLOW}Step 4: Checking email notifications status...${NC}"
php check_email_notifications_enabled.php
echo ""

echo -e "${YELLOW}Step 5: Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

echo -e "${YELLOW}Step 6: Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo -e "${GREEN}✓ Application optimized${NC}"
echo ""

echo "=========================================="
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo "=========================================="
echo ""
echo "What was fixed:"
echo "  - Added 'email_notifications_enabled' column to salons table"
echo "  - Set default value to true for all salons"
echo "  - Salon owners will now receive emails for new appointments"
echo "  - Staff members will now receive emails for new appointments"
echo ""
echo "Email notifications are sent for:"
echo "  ✓ New appointments (from app, widget, guest bookings)"
echo "  ✓ Appointment cancellations"
echo "  ✓ Appointment confirmations"
echo ""
echo "Next steps:"
echo "  1. Test by creating a new appointment"
echo "  2. Check salon owner's email inbox"
echo "  3. Check staff member's email inbox"
echo "  4. Check logs: tail -f storage/logs/laravel.log"
echo ""
