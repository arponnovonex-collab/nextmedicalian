# NEXT MEDICALIAN – Hostinger & Production Deployment Guide

---

### 1. Prerequisites & Environment

- **Server Environment**: Hostinger Web Hosting, Cloud Hosting, or VPS.
- **PHP Version**: PHP 8.2 or 8.3 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `gd` (for image/PDF QR rendering), `zip`.
- **Database**: MySQL 8.0 or MariaDB 10.4+.
- **Node.js**: Node 18+ (for local Vite asset compilation).

---

### 2. Deployment Architecture on Hostinger

```
Hostinger Root Directory Structure:
/home/u123456789/
│
├── next_backend/               <-- Laravel backend files (outside public_html for security)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── artisan
│
└── public_html/                <-- Public document root
    ├── index.php               <-- Points to ../next_backend/public/index.php
    ├── .htaccess               <-- URL rewriting rules
    ├── build/                  <-- Compiled React Vite bundle (JS/CSS/Assets)
    └── storage/                <-- Symlink to ../next_backend/storage/app/public
```

---

### 3. Step-by-Step Deployment Instructions

#### Step 1: Prepare Environment File (`.env`)
```ini
APP_NAME="NEXT MEDICALIAN"
APP_ENV=production
APP_KEY=base64:... (generated via php artisan key:generate)
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE="Asia/Dhaka"

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_next
DB_USERNAME=u123456789_user
DB_PASSWORD=SecurePasswordHere

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=public
```

#### Step 2: Build Frontend Assets
In your development environment:
```bash
npm.cmd run build
```
The compiled output is located in `public/build/`.

#### Step 3: Install PHP Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

#### Step 4: Database Migration & Seeding
Access Hostinger Terminal (SSH) or phpMyAdmin:
```bash
php artisan migrate --force
php artisan db:seed --force
```

#### Step 5: Storage Link & Cache Optimization
```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Step 6: Configure Apache `.htaccess` in `public_html`
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

#### Step 7: Configure Scheduled Tasks (Cron Job)
In Hostinger Control Panel -> Cron Jobs:
```cron
* * * * * cd /home/u123456789/next_backend && php artisan schedule:run >> /dev/null 2>&1
```
This handles scheduled admit card and result releases automatically.
