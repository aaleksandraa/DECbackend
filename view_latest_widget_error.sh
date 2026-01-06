#!/bin/bash

echo "=== Najnovija greška iz widget booking-a ==="
echo ""

ssh root@mail.frizerino.com "tail -100 /var/www/vhosts/frizerino.com/api.frizerino.com/storage/logs/laravel.log | grep -A 20 'Widget booking error' | tail -30"

echo ""
echo "=== Kraj ==="
