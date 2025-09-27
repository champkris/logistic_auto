# 🚀 Deployment Setup Complete!

## Files Created

1. **quick_deploy.sh** - Interactive script to commit, push, and show deployment commands
2. **deploy_simple.sh** - Semi-automated deployment with manual SSH step
3. **deploy.sh** - Full deployment script (requires SSH setup)
4. **deploy_auto.exp** - Expect script for fully automated deployment
5. **DEPLOYMENT_GUIDE.md** - Comprehensive deployment documentation
6. **.env.production** - Production environment template

## Quick Start - First Time Deployment

### Step 1: Prepare Local Changes
```bash
cd /Users/apichakriskalambasuta/Sites/localhost/logistic_auto
./quick_deploy.sh
```

### Step 2: SSH to Server
```bash
ssh champkris@110.77.196.218
# Password: Kri23655#
```

### Step 3: Initial Setup on Server (First Time Only)
```bash
# Clone repository
cd ~
git clone https://github.com/champkris/logistic_auto.git
cd logistic_auto

# Configure environment
cp .env.example .env
nano .env  # Edit with production settings

# Setup application
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan key:generate
php artisan migrate --force

# Set permissions
chmod -R 755 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Step 4: Configure Web Server
Make sure Nginx/Apache is configured to serve from:
`/home/champkris/logistic_auto/public`

## Subsequent Deployments

### Option A: Quick Deploy (Recommended)
```bash
# Local: Push changes
./quick_deploy.sh

# Server: Update and deploy
ssh champkris@110.77.196.218
cd /home/champkris/logistic_auto
git pull origin master
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Option B: One-Liner Deploy on Server
After pushing to GitHub, SSH to server and run:
```bash
cd /home/champkris/logistic_auto && git pull origin master && composer install --no-dev --optimize-autoloader && npm install && npm run build && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Important Configuration

### Database Setup
1. Create MySQL database on server:
```sql
CREATE DATABASE logistic_auto CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'logistic_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON logistic_auto.* TO 'logistic_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Update .env on server with database credentials

### LINE Configuration
Update these in server's .env:
- LINE_BOT_CHANNEL_TOKEN
- LINE_BOT_CHANNEL_SECRET
- LINE_LOGIN_CLIENT_ID
- LINE_LOGIN_CLIENT_SECRET
- LINE_CLIENT_LOGIN_CLIENT_ID
- LINE_CLIENT_LOGIN_CLIENT_SECRET

### Web Server Configuration (Nginx Example)
```nginx
server {
    listen 80;
    server_name 110.77.196.218;  # or your domain
    root /home/champkris/logistic_auto/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Troubleshooting

### Permission Errors
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
php artisan optimize:clear
```

### View Logs
```bash
tail -f /home/champkris/logistic_auto/storage/logs/laravel.log
```

## Server Requirements Checklist

- [ ] PHP 8.1+ installed
- [ ] Composer installed
- [ ] Node.js & NPM installed
- [ ] MySQL/MariaDB running
- [ ] Nginx/Apache configured
- [ ] Git installed
- [ ] SSL certificate (optional, for HTTPS)

## Next Steps

1. Run `./quick_deploy.sh` to deploy
2. SSH to server and complete setup
3. Test the application
4. Monitor logs for any issues

## Support Files

- See `DEPLOYMENT_GUIDE.md` for detailed instructions
- See `.env.production` for environment template
- See `PROJECT_SUMMARY.md` for project overview
