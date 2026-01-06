#!/bin/bash

echo "=== Provjera is_guest tipa na produkciji ==="
echo ""

echo "1. Provjera WidgetController.php:"
ssh root@mail.frizerino.com "grep -n \"'is_guest'\" /var/www/vhosts/frizerino.com/api.frizerino.com/app/Http/Controllers/Api/WidgetController.php | head -5"

echo ""
echo "2. Provjera AppointmentController.php:"
ssh root@mail.frizerino.com "grep -n \"'is_guest'\" /var/www/vhosts/frizerino.com/api.frizerino.com/app/Http/Controllers/Api/AppointmentController.php | head -5"

echo ""
echo "3. Provjera PublicController.php:"
ssh root@mail.frizerino.com "grep -n \"'is_guest'\" /var/www/vhosts/frizerino.com/api.frizerino.com/app/Http/Controllers/Api/PublicController.php | head -5"

echo ""
echo "4. Provjera ImportService.php:"
ssh root@mail.frizerino.com "grep -n \"'is_guest'\" /var/www/vhosts/frizerino.com/api.frizerino.com/app/Services/ImportService.php | head -5"

echo ""
echo "5. Provjera baze - tip kolone is_guest:"
ssh root@mail.frizerino.com "cd /var/www/vhosts/frizerino.com/api.frizerino.com && php artisan tinker --execute=\"echo DB::select('SELECT data_type FROM information_schema.columns WHERE table_name = \\\"appointments\\\" AND column_name = \\\"is_guest\\\"')[0]->data_type;\""

echo ""
echo "=== Kraj provjere ==="
