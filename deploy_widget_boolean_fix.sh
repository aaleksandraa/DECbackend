#!/bin/bash

echo "=========================================="
echo "Widget Boolean Fix - Deployment"
echo "=========================================="
echo ""

# Pull latest changes
echo "Step 1: Pull latest changes"
echo "----------------------------"
git pull origin main
if [ $? -ne 0 ]; then
    echo "❌ Git pull failed"
    exit 1
fi
echo "✅ Git pull successful"
echo ""

# Clear cache
echo "Step 2: Clear cache"
echo "----------------------------"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo "✅ Cache cleared"
echo ""

# Test the fix
echo "Step 3: Test widget endpoint"
echo "----------------------------"
curl -s "https://api.frizerino.com/api/v1/widget/frizerski-salon-mr-barber?key=frzn_live_UgXYsmR4p43IPMkJmDHiBLRafVOaGaHz" | head -c 200
echo ""
echo ""

# Check logs for errors
echo "Step 4: Check recent logs"
echo "----------------------------"
tail -20 storage/logs/laravel.log | grep -i "boolean\|error" || echo "✅ No boolean errors found"
echo ""

echo "=========================================="
echo "Deployment Complete"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Test widget GET: Should return JSON"
echo "2. Test widget booking: Try to book an appointment"
echo "3. Monitor logs: tail -f storage/logs/laravel.log"
echo ""
