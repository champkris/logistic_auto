#!/bin/bash

# Simple Deployment Script for Logistic Auto
# Usage: ./deploy_simple.sh

REMOTE_USER="champkris"
REMOTE_HOST="110.77.196.218"
REMOTE_PATH="/home/champkris/logistic_auto"
GIT_REPO="https://github.com/champkris/logistic_auto.git"
BRANCH="master"

echo "🚀 Logistic Auto Deployment Script"
echo "===================================="
echo ""

# Check for uncommitted changes
if [[ -n $(git status -s) ]]; then
    echo "⚠️  Warning: You have uncommitted changes:"
    git status -s
    echo ""
    read -p "Do you want to continue? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Push to GitHub
echo "📤 Pushing to GitHub..."
git push origin $BRANCH

if [ $? -ne 0 ]; then
    echo "❌ Failed to push to GitHub"
    exit 1
fi

echo "✅ Pushed to GitHub successfully"
echo ""
echo "📋 Remote deployment commands:"
echo "================================"
echo "SSH into server with: ssh ${REMOTE_USER}@${REMOTE_HOST}"
echo "Password: Kri23655#"
echo ""
echo "Then run these commands:"
echo ""
echo "# If first time deployment:"
echo "cd ~"
echo "git clone ${GIT_REPO}"
echo ""
echo "# If updating existing deployment:"
echo "cd ${REMOTE_PATH}"
echo "git pull origin ${BRANCH}"
echo ""
echo "# After git pull, run:"
echo "composer install --no-dev --optimize-autoloader"
echo "npm install && npm run build"
echo "cp .env.example .env  # (configure .env file)"
echo "php artisan key:generate"
echo "php artisan migrate --force"
echo "php artisan config:cache"
echo "php artisan route:cache"
echo "php artisan view:cache"
echo "chmod -R 755 storage bootstrap/cache"
echo ""
