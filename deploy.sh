#!/bin/bash

# Deployment Configuration
REMOTE_USER="champkris"
REMOTE_HOST="110.77.196.218"
REMOTE_PATH="/home/champkris/logistic_auto"
GIT_REPO="https://github.com/champkris/logistic_auto.git"
BRANCH="master"

echo "🚀 Starting deployment to $REMOTE_HOST..."

# Step 1: Commit any changes locally
echo "📝 Checking for local changes..."
if [[ -n $(git status -s) ]]; then
    echo "⚠️  You have uncommitted changes. Please commit or stash them first."
    git status -s
    exit 1
fi

# Step 2: Push to GitHub
echo "📤 Pushing to GitHub..."
git push origin $BRANCH

if [ $? -ne 0 ]; then
    echo "❌ Failed to push to GitHub"
    exit 1
fi

echo "✅ Pushed to GitHub successfully"

# Step 3: Deploy to remote server
echo "🔧 Deploying to remote server..."
echo "Please enter the SSH password when prompted: Kri23655#"

ssh ${REMOTE_USER}@${REMOTE_HOST} << 'ENDSSH'
    # Navigate to project directory or clone if doesn't exist
    if [ ! -d "$REMOTE_PATH" ]; then
        echo "📦 Cloning repository for the first time..."
        cd /home/champkris
        git clone $GIT_REPO
        cd logistic_auto
    else
        echo "🔄 Updating existing repository..."
        cd $REMOTE_PATH
        git fetch origin
        git reset --hard origin/$BRANCH
    fi
    
    # Install/Update dependencies
    echo "📚 Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader
    
    # Install/Update NPM dependencies and build assets
    echo "🎨 Building assets..."
    npm install
    npm run build
    
    # Set permissions
    echo "🔐 Setting permissions..."
    chmod -R 755 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
    
    # Run migrations
    echo "🗄️  Running migrations..."
    php artisan migrate --force
    
    # Clear and cache config
    echo "🧹 Clearing and caching..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Restart services (adjust based on your setup)
    echo "🔄 Restarting services..."
    # sudo systemctl restart nginx
    # sudo systemctl restart php8.2-fpm
    
    echo "✅ Deployment completed successfully!"
ENDSSH

echo "🎉 Deployment process finished!"
