#!/bin/bash

# Create Deployment Package for Manual Upload
# Use this when SSH/FTP is not available

echo "📦 Creating Deployment Package..."
echo "=================================="

# Build assets locally
echo "🎨 Building assets..."
npm install
npm run build

# Install production dependencies
echo "📚 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Create deployment package
echo "📦 Creating zip package..."
zip -r logistic_auto_deploy_$(date +%Y%m%d_%H%M%S).zip \
  app/ \
  bootstrap/ \
  config/ \
  database/ \
  public/ \
  resources/ \
  routes/ \
  storage/ \
  vendor/ \
  .env.example \
  .env.production \
  artisan \
  composer.json \
  composer.lock \
  package.json \
  package-lock.json \
  vite.config.js \
  tailwind.config.js \
  postcss.config.js \
  -x "*.git*" \
  -x "node_modules/*" \
  -x "tests/*" \
  -x "*.log" \
  -x ".DS_Store"

echo ""
echo "✅ Deployment package created!"
echo ""
echo "📋 Next Steps:"
echo "1. Upload the zip file to your server via CloudPanel File Manager"
echo "2. Extract it to: /home/champkris/htdocs/vessel.easternair.co.th"
echo "3. Configure .env file with production settings"
echo "4. Run: php artisan migrate --force"
echo "5. Run: php artisan config:cache"
echo ""
echo "Package name: logistic_auto_deploy_$(date +%Y%m%d_%H%M%S).zip"
