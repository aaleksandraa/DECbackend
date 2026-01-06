#!/bin/bash

# Widget Fix - Quick Deployment Script
# Run on production server from backend folder: cd backend && bash deploy_widget_fix.sh

echo "=========================================="
echo "Widget Fix - Quick Deployment"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: artisan file not found. Please run from backend directory:${NC}"
    echo "cd /var/www/vhosts/frizerino.com/api.frizerino.com/backend"
    echo "bash deploy_widget_fix.sh"
    exit 1
fi

echo "Step 1: Pull latest changes"
echo "----------------------------"
git pull origin main
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Git pull failed${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Git pull successful${NC}"
echo ""

echo "Step 2: Clear cache"
echo "----------------------------"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo -e "${GREEN}✅ Cache cleared${NC}"
echo ""

echo "Step 3: Test Widget API"
echo "----------------------------"
echo "Testing: https://api.frizerino.com/api/v1/widget/frizerski-salon-mr-barber?api_key=frzn_live_UgXYsmR4p43IPMkJmDHiBLRafVOaGaHz"
echo ""

RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "https://api.frizerino.com/api/v1/widget/frizerski-salon-mr-barber?api_key=frzn_live_UgXYsmR4p43IPMkJmDHiBLRafVOaGaHz")

if [ "$RESPONSE" = "200" ]; then
    echo -e "${GREEN}✅ Widget API returned 200 OK${NC}"
elif [ "$RESPONSE" = "401" ]; then
    echo -e "${YELLOW}⚠️  Widget API returned 401 (check API key)${NC}"
elif [ "$RESPONSE" = "500" ]; then
    echo -e "${RED}❌ Widget API still returning 500 error${NC}"
    echo "Check logs: tail -50 storage/logs/laravel.log"
    exit 1
else
    echo -e "${YELLOW}⚠️  Widget API returned: $RESPONSE${NC}"
fi
echo ""

echo "Step 4: Check for errors in logs"
echo "----------------------------"
ERRORS=$(grep -i "error\|exception" storage/logs/laravel.log | tail -5)
if [ -z "$ERRORS" ]; then
    echo -e "${GREEN}✅ No recent errors in logs${NC}"
else
    echo -e "${YELLOW}⚠️  Recent errors found:${NC}"
    echo "$ERRORS"
fi
echo ""

echo "=========================================="
echo "Deployment Complete"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Test widget on your website"
echo "2. Monitor logs: tail -f storage/logs/laravel.log"
echo "3. If everything works, proceed with boolean migration"
echo ""
echo "Documentation:"
echo "- WIDGET_FIX_SMALLINT_COMPLETE.md"
echo "- BOOLEAN_FINAL_TRUTH.md"
echo ""
