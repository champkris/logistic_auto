#!/bin/bash
# Quick Deploy Script - Run from local machine

echo "🚀 Deploying Logistic Auto to Production"
echo "========================================"

# Step 1: Push to GitHub
echo "📤 Step 1: Pushing to GitHub..."
cd /Users/apichakriskalambasuta/Sites/localhost/logistic_auto
git add .
git commit -m "Deploy: $(date +%Y-%m-%d-%H:%M:%S)"
git push origin master

if [ $? -eq 0 ]; then
    echo "✅ Code pushed to GitHub successfully!"
    echo ""
    echo "📋 Step 2: Deploy on Server"
    echo "========================================"
    echo ""
    echo "Since SSH is blocked, use CloudPanel:"
    echo ""
    echo "1. Go to: https://panel.web.easternair.co.th:8443"
    echo "2. Navigate to: Sites → vessel.easternair.co.th → File Manager"
    echo "3. Open Terminal (button in File Manager)"
    echo "4. Run this command:"
    echo ""
    echo "cd /home/champkris/logistic_auto && git pull origin master && composer install --no-dev --optimize-autoloader && npm install && npm run build && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"
    echo ""
    echo "5. Check site: http://vessel.easternair.co.th"
    echo ""
else
    echo "❌ Failed to push to GitHub"
    exit 1
fi
