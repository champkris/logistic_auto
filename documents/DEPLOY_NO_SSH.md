# 🚀 Deploy Without SSH/FTP - CloudPanel Method

## Your Situation
- ✅ CloudPanel web access works
- ❌ SSH blocked by firewall  
- ❌ FTP blocked by firewall

## Solution: Deploy Using CloudPanel Tools

### Method 1: Automated GitHub Webhook (Recommended)

#### Step 1: Upload Deploy Script
1. Open CloudPanel → Your site → **File Manager**
2. Navigate to `/home/champkris/htdocs/vessel.easternair.co.th/public`
3. Upload the file: `deploy.php` (I created this for you)
4. Edit `deploy.php` and change:
   - `SECRET` to a random secure string (e.g., "my_secret_key_12345")

#### Step 2: Initial Git Clone (One Time Only)
You need to clone the repo once. Options:
- Ask hosting support to run: `git clone https://github.com/champkris/logistic_auto.git /home/champkris/htdocs/vessel.easternair.co.th`
- Or use CloudPanel's Git feature if available
- Or upload files manually via File Manager

#### Step 3: Setup GitHub Webhook
1. Go to: https://github.com/champkris/logistic_auto/settings/hooks
2. Click **Add webhook**
3. Configure:
   - **Payload URL**: `https://vessel.easternair.co.th/deploy.php`
   - **Content type**: `application/json`
   - **Secret**: (same secret you put in deploy.php)
   - **Events**: Select "Just the push event"
4. Click **Add webhook**

#### Step 4: Deploy!
Now every time you push to GitHub, it auto-deploys! 🎉

```bash
git add .
git commit -m "Update"
git push origin master
# Auto-deploys to server! ✨
```

---

### Method 2: Manual Deploy via File Manager

#### Step 1: Build Locally
```bash
cd /Users/apichakriskalambasuta/Sites/localhost/logistic_auto
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

#### Step 2: Create Deployment Package
```bash
# Create zip excluding unnecessary files
zip -r deploy.zip \
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
  artisan \
  composer.json \
  composer.lock \
  package.json \
  vite.config.js \
  tailwind.config.js \
  -x "*.git*" -x "node_modules/*" -x "tests/*"
```

#### Step 3: Upload & Extract
1. Go to CloudPanel → File Manager
2. Upload `deploy.zip`
3. Right-click → Extract
4. Delete the zip file

#### Step 4: Run Commands via CloudPanel
If CloudPanel has a terminal/console feature:
```bash
cd /home/champkris/htdocs/vessel.easternair.co.th
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### Method 3: Contact Hosting Support

Since both SSH and FTP are blocked, you might need to:

**Ask hosting support to:**
1. Enable SSH access for IP: `103.3.67.251` (your current IP)
2. Or provide alternative access method
3. Or help with initial Git setup

**Email template:**
```
Subject: Enable SSH/FTP Access for 110.77.196.218

Hello,

I need SSH or FTP access to my server:
- IP: 110.77.196.218
- Domain: vessel.easternair.co.th
- Username: champkris
- My IP: 103.3.67.251

Please enable SSH access or provide alternative deployment method.

Thank you!
```

---

### Method 4: Ask Support to Run Git Command (Easiest)

Contact support and ask them to run this ONE command:
```bash
cd /home/champkris && git clone https://github.com/champkris/logistic_auto.git htdocs/vessel.easternair.co.th
```

Then you can use the GitHub webhook method for all future deployments!

---

## Quick Start (What to Do Now)

1. ✅ **Upload deploy.php** via CloudPanel File Manager
2. ✅ **Contact support** to enable SSH or clone Git repo
3. ✅ **Setup GitHub webhook** once repo is cloned
4. ✅ **Push to GitHub** and it auto-deploys!

## Need Help?

Check these files I created:
- `deploy.php` - Webhook auto-deploy script
- `CLOUDPANEL_DEPLOYMENT.md` - Full CloudPanel guide
- `DEPLOYMENT_SETUP.md` - Complete deployment documentation
