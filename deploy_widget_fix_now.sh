#!/bin/bash

echo "========================================="
echo "Widget Zero Duration Fix - Deployment"
echo "========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Frontend path
FRONTEND_PATH="/var/www/vhosts/frizerino.com/frizerino.com"

echo "Step 1: Check current build status"
echo "-----------------------------------"
cd $FRONTEND_PATH

if [ -d "dist" ]; then
    echo "Current dist/ files:"
    ls -lh dist/assets/*.js 2>/dev/null | head -5
    echo ""
    echo "Current time:"
    date
    echo ""
else
    echo -e "${RED}❌ dist/ folder does not exist!${NC}"
    echo ""
fi

echo "Step 2: Pull latest code"
echo "------------------------"
git pull origin main
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Git pull failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Git pull successful${NC}"
echo ""

echo "Step 3: Build frontend"
echo "----------------------"
echo "Running npm run build..."
npm run build
if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Build failed!${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Build successful${NC}"
echo ""

echo "Step 4: Verify build"
echo "--------------------"
if [ -d "dist" ]; then
    echo "New dist/ files:"
    ls -lh dist/assets/*.js 2>/dev/null | head -5
    echo ""
    
    # Check if files are recent (less than 5 minutes old)
    NEWEST_FILE=$(find dist/assets/*.js -type f -printf '%T@ %p\n' 2>/dev/null | sort -n | tail -1 | cut -d' ' -f2)
    if [ -n "$NEWEST_FILE" ]; then
        FILE_TIME=$(stat -c %Y "$NEWEST_FILE" 2>/dev/null)
        CURRENT_TIME=$(date +%s)
        TIME_DIFF=$((CURRENT_TIME - FILE_TIME))
        
        if [ $TIME_DIFF -lt 300 ]; then
            echo -e "${GREEN}✅ Build is fresh (${TIME_DIFF} seconds old)${NC}"
        else
            echo -e "${YELLOW}⚠️  Build is old (${TIME_DIFF} seconds old)${NC}"
        fi
    fi
else
    echo -e "${RED}❌ dist/ folder still does not exist!${NC}"
    exit 1
fi
echo ""

echo "Step 5: Check GuestBookingModal.tsx"
echo "------------------------------------"
echo "Checking if zero duration validation exists..."
if grep -q "totalDuration === 0" src/components/Public/GuestBookingModal.tsx; then
    echo -e "${GREEN}✅ Zero duration validation found in source${NC}"
else
    echo -e "${RED}❌ Zero duration validation NOT found in source!${NC}"
    echo "This means the code was not committed or pulled correctly."
fi
echo ""

echo "========================================="
echo "DEPLOYMENT COMPLETE"
echo "========================================="
echo ""
echo -e "${GREEN}✅ Frontend deployed successfully!${NC}"
echo ""
echo "IMPORTANT: Test widget now!"
echo ""
echo "Test steps:"
echo "1. Open widget in INCOGNITO mode (Ctrl+Shift+N)"
echo "2. Select only 'Pranje kose' (0 min service)"
echo "3. Button 'Dalje' should be DISABLED (grey)"
echo "4. Message should show: 'Ne možete rezervisati ovu uslugu samostalno. Molimo dodajte glavnu uslugu.'"
echo ""
echo "If it still doesn't work:"
echo "1. Check browser console (F12) for errors"
echo "2. Check Network tab to see if files are loaded"
echo "3. Try different browser"
echo ""
echo "Widget URL should be something like:"
echo "https://frizerino.com/widget/[salon-slug]?key=[api-key]"
echo ""

