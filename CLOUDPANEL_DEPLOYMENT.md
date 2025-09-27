# Deployment via CloudPanel (Without SSH)

Since SSH is blocked, deploy using CloudPanel's web interface.

## 📋 Step-by-Step Deployment

### Step 1: Push Code to GitHub (Local Machine)
```bash
cd /Users/apichakriskalambasuta/Sites/localhost/logistic_auto
git add .
git commit -m "Deploy to production"
git push origin master
```

### Step 2: Access CloudPanel File Manager
1. Go to: https://panel.web.easternair.co.th:8443
2. Login with your credentials
3. Navigate to: **Sites** → **vessel.easternair.co.th** → **File Manager**

### Step 3: Open Terminal in File Manager
Look for one of these options:
- **Terminal** button (usually top right)
- **Console** icon
- **Shell** access
- Or right-click → **Open Terminal**

### Step 4: Run Deployment Commands

#### First Time Setup:
```bash
cd /home/champkris
git clone https://github.com/champkris/logistic_auto.git
cd logistic_auto
cp .env.example .env
nano .env  # Configure production settings
php artisan key:generate
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
chmod -R 755 storage bootstrap/cache
```

#### Subsequent Deployments:
```bash
cd /home/champkris/logistic_auto
git pull origin master
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 5: Alternative - Upload Deployment Script
If terminal is available in File Manager:
1. Upload `deploy_via_cloudpanel.sh` to `/home/champkris/`
2. Make it executable: `chmod +x deploy_via_cloudpanel.sh`
3. Run: `./deploy_via_cloudpanel.sh`

## 🔧 Alternative: SFTP Deployment

If no terminal available, use SFTP:
1. Connect via SFTP: `sftp://champkris@110.77.196.218`
2. Password: `Kri23655#`
3. Upload files manually
4. Use CloudPanel PHP console to run artisan commands

## ✅ Verify Deployment
