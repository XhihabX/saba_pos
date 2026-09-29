#!/bin/bash
# Saba POS - Enterprise Automated Production Deployment Script
# Usage: bash deploy.sh

echo "=========================================================="
echo "🚀 Starting Saba POS Automated Production Deployment"
echo "=========================================================="

# Exit on error
set -e

# 1. Maintenance Mode
echo "📌 Putting application into maintenance mode..."
php artisan down || true

# 2. Update Composer Dependencies & Optimize Autoloader
echo "📦 Optimizing Composer Dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Database Migrations
echo "🗄️ Running Database Migrations..."
php artisan migrate --force

# 4. Storage Symlink Creation
echo "🔗 Verifying Storage Symlink..."
php artisan storage:link || true

# 5. Clear Old Caches & Re-cache Framework Configuration
echo "⚡ Caching Framework Configuration & Routes..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Bring Application Back Live
echo "🟢 Bringing Application Back Online..."
php artisan up

echo "=========================================================="
echo "✅ Saba POS Enterprise Deployment Successfully Completed!"
echo "=========================================================="
