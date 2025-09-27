#!/bin/bash
# CloudPanel Deployment Script
# Upload this file to /home/champkris/logistic_auto/deploy_via_cloudpanel.sh
# Run it from CloudPanel File Manager terminal

echo "🚀 Starting Deployment..."

# Navigate to project directory
cd /home/champkris/logistic_auto || exit 1

# Check if it's a git repository
if [ ! -d ".git" ]; then
    echo "📦 Cloning repository for first time..."
    cd /home/champkris
    git clone https://github.com/champkris/logistic_auto.git
    cd logistic_auto
else
    echo "🔄 Updating existing repository..."
    git fetch origin
    git reset --hard origin/master
    git pull origin master
fi

echo "📚 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

echo "🎨 Installing NPM dependencies..."
npm install

echo "🏗️ Building assets..."
npm run build

echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R clp:clp storage bootstrap/cache

echo "🗄️ Running migrations..."
php artisan migrate --force

echo "🧹 Clearing and caching..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Deployment completed successfully!"
echo ""
echo "📋 Next: Check your site at http://vessel.easternair.co.th"
