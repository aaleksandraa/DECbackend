#!/bin/bash

echo "========================================="
echo "Boolean Status Check"
echo "========================================="
echo ""

# Run the verification script
php verify_all_booleans.php

echo ""
echo "========================================="
echo "Widget Test"
echo "========================================="
echo ""

# Test the widget endpoint
echo "Testing widget endpoint..."
curl -s "https://api.frizerino.com/api/v1/widget/frizerski-salon-mr-barber?key=frzn_live_UgXYsmR4p43IPMkJmDHiBLRafVOaGaHz" | head -c 200

echo ""
echo ""
echo "========================================="
echo "Check Complete"
echo "========================================="
