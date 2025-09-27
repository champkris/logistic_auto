#!/bin/bash

# Quick Deploy Script
# This script pushes code to GitHub for deployment

echo "🚀 Logistic Auto - Quick Deploy to Production"
echo "=============================================="
echo ""

# Check git status
echo "📊 Current Git Status:"
git status -s
echo ""

# Ask for confirmation
read -p "Do you want to commit and push these changes? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Deployment cancelled"
    exit 1
fi

# Get commit message
read -p "Enter commit message: " commit_msg
if [ -z "$commit_msg" ]; then
    commit_msg="Update deployment"
fi

# Git operations
echo ""
echo "📝 Committing changes..."
git add .
git commit -m "$commit_msg"

echo "📤 Pushing to GitHub..."
git push origin master

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Successfully pushed to GitHub!"
    echo ""
    echo "📋 Next Steps - SSH to server and run:"
    echo "========================================="
    echo "ssh champkris@110.77.196.218"
    echo "Password: Kri23655#"
    echo ""
    echo "Then:"
    echo "cd /home/champkris/logistic_auto"
    echo "git pull origin master"
    echo "composer install --no-dev --optimize-autoloader"
    echo "npm install && npm run build"
    echo "php artisan migrate --force"
    echo "php artisan config:cache"
    echo "php artisan route:cache"
    echo "php artisan view:cache"
    echo ""
    echo "Or copy and paste this one-liner:"
    echo "cd /home/champkris/logistic_auto && git pull origin master && composer install --no-dev --optimize-autoloader && npm install && npm run build && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"
    echo ""
else
    echo "❌ Failed to push to GitHub"
    exit 1
fi
