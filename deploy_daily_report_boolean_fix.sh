#!/bin/bash

# Daily Report Boolean Fix Deployment Script
# Fixes: operator does not exist: boolean = integer error

echo "=========================================="
echo "Daily Report Boolean Fix Deployment"
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

echo -e "${YELLOW}Step 3: Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

echo -e "${YELLOW}Step 4: Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo -e "${GREEN}✓ Application optimized${NC}"
echo ""

echo -e "${YELLOW}Step 5: Testing daily report command...${NC}"
php artisan reports:send-daily --help
if [ $? -ne 0 ]; then
    echo -e "${RED}Daily report command test failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Daily report command is working${NC}"
echo ""

echo "=========================================="
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo "=========================================="
echo ""
echo "What was fixed:"
echo "  - Changed 'daily_report_enabled' comparison from integer (1) to boolean (true)"
echo "  - Fixed PostgreSQL operator error: boolean = integer"
echo ""
echo "Next steps:"
echo "  1. Test manually: php artisan reports:send-daily --salon=1"
echo "  2. Check logs: tail -f storage/logs/laravel.log"
echo "  3. Verify cron job is running at 19:00 daily"
echo ""
