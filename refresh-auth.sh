#!/bin/bash

# Script to refresh authentication and clear caches after deployment
# Run this on the server after deploying new code

echo "🔄 Refreshing authentication and caches..."

# Clear all caches
echo "📦 Clearing application cache..."
php artisan cache:clear

echo "🔧 Clearing config cache..."
php artisan config:clear
php artisan config:cache

echo "🛣️  Clearing route cache..."
php artisan route:clear
php artisan route:cache

echo "👁️  Clearing view cache..."
php artisan view:clear
php artisan view:cache

echo "🔐 Clearing auth sessions (optional - will log out all users)..."
# Uncomment the next line if you want to clear all sessions
# php artisan session:clear

echo "🧹 Optimizing application..."
php artisan optimize

echo "✅ Done! Authentication and caches refreshed."
echo ""
echo "📝 Note: Users may need to log out and log back in to get fresh sessions."
