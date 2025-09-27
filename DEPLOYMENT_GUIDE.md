# Deployment Guide - Logistic Auto

## Server Information
- **Host**: 110.77.196.218
- **Username**: champkris
- **Password**: Kri23655#
- **Deployment Path**: /home/champkris/logistic_auto

## Quick Start Deployment

### Method 1: Using Deploy Script (Recommended)
```bash
./deploy_simple.sh
```
This will push to GitHub and show you the commands to run on the server.

### Method 2: Manual Deployment

#### Step 1: Push to GitHub
```bash
cd /Users/apichakriskalambasuta/Sites/localhost/logistic_auto
git add .
git commit -m "Deploy updates"
git push origin master
```

#### Step 2: SSH to Server
```bash
ssh champkris@110.77.196.218
# Enter password: Kri23655#
```

#### Step 3: First Time Setup (Only Once)
```bash
cd ~
git clone https://github.com/champkris/logistic_auto.git
cd logistic_auto
cp .env.example .env
# Edit .env file with production settings
php artisan key:generate
```

#### Step 4: Update Deployment (Every Time)
```bash
cd /home/champkris/logistic_auto
git pull origin master
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 755 storage bootstrap/cache
sudo systemctl restart nginx  # or your web server
sudo systemctl restart php8.2-fpm  # adjust PHP version if needed
```

## Server Requirements

Make sure your server has:
- PHP 8.1 or higher
- Composer
- Node.js & NPM
- MySQL/MariaDB
- Nginx or Apache
- Git

## Environment Configuration

### Important .env Variables to Configure
```
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=logistic_auto
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Add other service credentials
```

## Troubleshooting

### Permission Issues
```bash
sudo chown -R www-data:www-data /home/champkris/logistic_auto
chmod -R 755 /home/champkris/logistic_auto/storage
chmod -R 755 /home/champkris/logistic_auto/bootstrap/cache
```

### Clear All Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Check Logs
```bash
tail -f /home/champkris/logistic_auto/storage/logs/laravel.log
```

## Automated Deployment Options

### Option A: GitHub Actions (CI/CD)
Create `.github/workflows/deploy.yml` for automatic deployment on push.

### Option B: Deploy Script
Use the provided `deploy_simple.sh` script for semi-automated deployment.

### Option C: SSH Key Authentication (More Secure)
```bash
# Generate SSH key locally
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"

# Copy to server
ssh-copy-id champkris@110.77.196.218

# Now you can SSH without password
```

## Post-Deployment Checklist

- [ ] Check site is accessible
- [ ] Test database connection
- [ ] Verify browser automation features
- [ ] Check vessel tracking functionality
- [ ] Test LINE integration
- [ ] Verify shipment tracking
- [ ] Check logs for errors
- [ ] Monitor server resources

## Quick Deploy Command
```bash
./deploy_simple.sh
```

## Support
If you encounter issues, check:
1. Server logs: `/var/log/nginx/error.log`
2. Laravel logs: `storage/logs/laravel.log`
3. PHP-FPM logs: `/var/log/php8.2-fpm.log`
