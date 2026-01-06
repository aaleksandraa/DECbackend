#!/bin/bash

# Email Notifications Toggle Fix
# Fixes: Salon owner cannot enable/disable email notifications from profile settings

echo "=========================================="
echo "Email Notifications Toggle Fix"
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

echo -e "${YELLOW}Step 2: Clearing caches...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

echo -e "${YELLOW}Step 3: Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
echo -e "${GREEN}✓ Application optimized${NC}"
echo ""

echo "=========================================="
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo "=========================================="
echo ""
echo "What was fixed:"
echo "  - Added 'email_notifications_enabled' to UpdateSalonRequest validation rules"
echo "  - Salon owners can now enable/disable email notifications from profile settings"
echo ""
echo "Testing:"
echo "  1. Login as salon owner"
echo "  2. Go to Profile/Settings"
echo "  3. Toggle 'Email notifikacije o novim terminima' checkbox"
echo "  4. Save changes"
echo "  5. Verify in database: SELECT id, name, email_notifications_enabled FROM salons;"
echo ""
