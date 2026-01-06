#!/bin/bash

# Frizerino - Start All Services
# This script starts all required Laravel services for development

echo "🚀 Starting Frizerino Services..."
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from the backend directory."
    exit 1
fi

echo "${YELLOW}📋 Starting services...${NC}"
echo ""

# Check Redis
echo "${GREEN}0. Checking Redis...${NC}"
if redis-cli ping > /dev/null 2>&1; then
    echo "   ✅ Redis is running"
else
    echo "   ⚠️  WARNING: Redis is not running!"
    echo "   Please start Redis: redis-server"
fi
echo ""

# Start Laravel Scheduler
echo "${GREEN}1. Starting Laravel Scheduler (for daily reports and reminders)...${NC}"
gnome-terminal --tab --title="Scheduler" -- bash -c "php artisan schedule:work; exec bash" 2>/dev/null || \
xterm -e "php artisan schedule:work" 2>/dev/null || \
start cmd /k "php artisan schedule:work" 2>/dev/null || \
php artisan schedule:work &

sleep 1

# Start Queue Worker
echo "${GREEN}2. Starting Queue Worker (for emails and notifications)...${NC}"
gnome-terminal --tab --title="Queue" -- bash -c "php artisan queue:work; exec bash" 2>/dev/null || \
xterm -e "php artisan queue:work" 2>/dev/null || \
start cmd /k "php artisan queue:work" 2>/dev/null || \
php artisan queue:work &

sleep 1

echo ""
echo "${GREEN}✅ All services started!${NC}"
echo ""
echo "Services running:"
echo "  📅 Scheduler - Runs daily reports (20:00) and reminders (18:00)"
echo "  📧 Queue Worker - Processes email and notification jobs"
echo "  🔴 Redis - Cache and queue backend"
echo ""
echo "To stop services:"
echo "  - Press Ctrl+C in each terminal window"
echo "  - Or run: pkill -f 'artisan schedule:work' && pkill -f 'artisan queue:work'"
echo ""
echo "Manual testing commands:"
echo "  php artisan reports:send-daily"
echo "  php artisan appointments:send-reminders"
echo ""
