#!/bin/bash

echo "=== PROVJERA WidgetController.php NA PRODUKCIJI ==="
echo ""

cd /var/www/vhosts/frizerino.com/api.frizerino.com

echo "1. Datum modifikacije fajla:"
ls -lh app/Http/Controllers/Api/WidgetController.php
echo ""

echo "2. Sve instance 'is_guest' u fajlu:"
grep -n "'is_guest'" app/Http/Controllers/Api/WidgetController.php
echo ""

echo "3. Provjera da li ima integer (1 ili 0):"
if grep -q "'is_guest'.*[01]" app/Http/Controllers/Api/WidgetController.php; then
    echo "❌ PRONAĐEN INTEGER!"
    grep -n "'is_guest'.*[01]" app/Http/Controllers/Api/WidgetController.php
else
    echo "✅ Nema integera, sve je boolean (true/false)"
fi
echo ""

echo "4. Provjera cache-a:"
php artisan config:cache --help > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "Artisan radi"
else
    echo "⚠️  Problem sa artisan"
fi
echo ""

echo "5. PHP-FPM status:"
systemctl status php8.2-fpm | grep Active
echo ""

echo "=== KRAJ PROVJERE ==="
