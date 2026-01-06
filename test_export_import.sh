#!/bin/bash

# Test Export/Import Appointments
# Brzi test za eksport i import termina

echo "🧪 Test Eksport/Import Termina"
echo "=============================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Step 1: Export appointments
echo -e "${YELLOW}📤 Korak 1: Eksport termina...${NC}"
php export_appointments_by_staff.php --format=json

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Eksport uspješan${NC}"
else
    echo -e "${RED}❌ Eksport neuspješan${NC}"
    exit 1
fi

echo ""

# Step 2: List exported files
echo -e "${YELLOW}📁 Korak 2: Eksportovani fajlovi:${NC}"
ls -lh exports/appointments/*.json 2>/dev/null | tail -5

echo ""

# Step 3: Test dry-run import
echo -e "${YELLOW}🔍 Korak 3: Test import (dry-run)...${NC}"
LATEST_JSON=$(ls -t exports/appointments/*.json 2>/dev/null | head -1)

if [ -z "$LATEST_JSON" ]; then
    echo -e "${RED}❌ Nema JSON fajlova za import${NC}"
    exit 1
fi

echo "Testiram fajl: $LATEST_JSON"
php import_appointments_from_json.php "$LATEST_JSON" --dry-run

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Dry-run import uspješan${NC}"
else
    echo -e "${RED}❌ Dry-run import neuspješan${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✅ Svi testovi prošli!${NC}"
echo ""
echo "💡 Za stvarni import pokreni:"
echo "   php import_appointments_from_json.php $LATEST_JSON --skip-duplicates"
