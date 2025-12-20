# تلگرام وب حرفه‌ای - راهنمای نصب و راه‌اندازی

## معرفی پروژه

این پروژه یک وب اپلیکیشن تلگرام حرفه‌ای است که امکانات جامعی در زمینه‌های زیر ارائه می‌دهد:

- ✅ سیستم دریافت اطلاعات کاربر از طریق ربات تلگرام
- ✅ سیستم مالی با قابلیت کارت به کارت و مدیریت موجودی
- ✅ سیستم خرید سرویس با سبد خرید و پرداخت
- ✅ پنل کاربری جامع با اطلاعات حساب و تاریخچه تراکنش‌ها
- ✅ داشبورد مدیریتی با گزارش‌گیری و آمار
- ✅ سیستم اطلاع‌رسانی و نوتیفیکیشن پیشرفته

## پیش‌نیازهای نصب

### نیازمندی‌های سرور
- PHP 7.4 یا بالاتر
- MySQL 5.7 یا بالاتر / MariaDB 10.2 یا بالاتر
- Apache/Nginx
- SSL Certificate (برای پرداخت‌های آنلاین)
- حداقل 1GB RAM
- حداقل 10GB فضای دیسک

### نیازمندی‌های نرم‌افزاری
- Composer (برای مدیریت پکیج‌های PHP)
- Git (اختیاری)
- Cron Job (برای اجرای وظایف خودکار)

### نیازمندی‌های تلگرام
- توکن ربات تلگرام از [@BotFather](https://t.me/BotFather)
- Webhook برای دریافت آپدیت‌ها
- دسترسی به API تلگرام

## مراحل نصب

### 1. دانلود و آماده‌سازی فایل‌ها

```bash
# دانلود پروژه
git clone https://github.com/your-repo/telegram-web-app.git
cd telegram-web-app

# نصب وابستگی‌ها
composer install
```

### 2. تنظیمات پایگاه داده

#### ایجاد پایگاه داده
```sql
CREATE DATABASE telegram_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'telegram_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON telegram_web.* TO 'telegram_user'@'localhost';
FLUSH PRIVILEGES;
```

#### اجرای اسکریپت‌های پایگاه داده
```bash
# وارد کردن اسکیمای پایگاه داده
mysql -u telegram_user -p telegram_web < app/database_schema.sql
```

### 3. تنظیمات فایل پیکربندی

#### فایل `config.php` را باز کرده و مقادیر را تنظیم کنید:

```php
<?php
// اطلاعات پایگاه داده
$dbname = 'telegram_web';
$usernamedb = 'telegram_user';
$passworddb = 'your_strong_password';

// اطلاعات ربات تلگرام
$APIKEY = 'YOUR_BOT_TOKEN_FROM_BOTFATHER';
$adminnumber = 'YOUR_ADMIN_TELEGRAM_ID';
$domainhosts = 'your-domain.com';
$usernamebot = 'your_bot_username';

// تنظیمات امنیتی
$encryption_key = 'your_32_character_encryption_key_here';
$jwt_secret = 'your_jwt_secret_key_here';

// تنظیمات مالی
$min_deposit_amount = 10000; // حداقل مبلغ واریز (ریال)
$max_deposit_amount = 100000000; // حداکثر مبلغ واریز (ریال)
$transaction_fee_percentage = 0; // کارمزد تراکنش (درصد)
$transaction_fee_fixed = 0; // کارمزد ثابت تراکنش (ریال)

// تنظیمات سرویس‌ها
$auto_service_activation = true; // فعال‌سازی خودکار سرویس‌ها
$service_expiry_reminder_days = 3; // روزهای اطلاع‌رسانی انقضا

// تنظیمات اعلان‌ها
$notification_telegram_enabled = true;
$notification_email_enabled = false;
$notification_sms_enabled = false;

// اطلاعات بانکی برای کارت به کارت
$bank_cards = [
    [
        'bank_name' => 'بانک ملی ایران',
        'card_number' => '6037991234567890',
        'account_holder' => 'شرکت تلگرام وب',
        'status' => 'active'
    ]
];

// تنظیمات درگاه پرداخت
$payment_gateways = [
    'zarinpal' => [
        'merchant_id' => 'your_zarinpal_merchant_id',
        'enabled' => false
    ],
    'aqayepardakht' => [
        'api_key' => 'your_api_key',
        'enabled' => false
    ]
];
?>
```

### 4. تنظیمات وب‌سرور

#### Apache (.htaccess)
فایل `.htaccess` موجود در روت پروژه را بررسی کنید:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# امنیت
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set X-XSS-Protection "1; mode=block"

# فشرده‌سازی
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

#### Nginx
در فایل پیکربندی Nginx:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /path/to/telegram-web-app;
    index index.php index.html;
    
    # SSL configuration
    ssl_certificate /path/to/ssl/certificate.crt;
    ssl_certificate_key /path/to/ssl/private.key;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~* \.(sql|log|sh|git)$ {
        deny all;
    }
}
```

### 5. تنظیم Webhook تلگرام

#### ایجاد Webhook
فایل `webhook.php` در روت پروژه ایجاد کنید:

```php
<?php
require_once 'config.php';
require_once 'botapi.php';
require_once 'app/TelegramWebhook.php';

$webhook = new TelegramWebhook($pdo);
$webhook->processUpdate();
?>
```

#### تنظیم Webhook در تلگرام
```bash
curl -F "url=https://your-domain.com/webhook.php" \
     -F "max_connections=40" \
     https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook
```

### 6. تنظیم Cron Jobs

Cron jobs را برای اجرای وظایف خودکار تنظیم کنید:

```bash
# اجرای هر 5 دقیقه - پردازش نوتیفیکیشن‌های زمان‌بندی شده
*/5 * * * * /usr/bin/php /path/to/telegram-web-app/cron/process_notifications.php

# اجرای هر ساعت - بررسی انقضای سرویس‌ها
0 * * * * /usr/bin/php /path/to/telegram-web-app/cron/check_service_expiry.php

# اجرای روزانه در ساعت 2 بامداد - ارسال خلاصه روزانه
0 2 * * * /usr/bin/php /path/to/telegram-web-app/cron/send_daily_summary.php

# اجرای هفتگی - تمیز کردن داده‌های قدیمی
0 3 * * 0 /usr/bin/php /path/to/telegram-web-app/cron/cleanup_old_data.php

# اجرای ماهانه - بک‌آپ پایگاه داده
0 4 1 * * /usr/bin/mysqldump -u telegram_user -p'password' telegram_web > /backup/telegram_web_$(date +\%Y\%m\%d).sql
```

### 7. تنظیمات امنیتی

#### فایل‌های حساس
مطمئن شوید که فایل‌های زیر قابل دسترسی عمومی نیستند:
- `config.php`
- `.env` (اگر استفاده می‌کنید)
- فایل‌های لاگ
- فایل‌های بک‌آپ

#### مجوزهای فایل
```bash
# تنظیم مجوزهای مناسب
chmod 600 config.php
chmod 755 app/
chmod 644 app/*.php
chmod 777 uploads/ # برای آپلود فایل‌ها
chmod 777 logs/ # برای فایل‌های لاگ
```

#### تنظیمات PHP
در فایل `php.ini`:
```ini
; Security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; File uploads
file_uploads = On
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 5

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1

; Error reporting (for production)
display_errors = Off
log_errors = On
error_log = /path/to/logs/php_errors.log
```

## تست و راه‌اندازی

### 1. تست اتصال به پایگاه داده
```php
<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8mb4", $usernamedb, $passworddb);
    echo "✅ اتصال به پایگاه داده موفق بود.";
} catch (PDOException $e) {
    echo "❌ خطا در اتصال به پایگاه داده: " . $e->getMessage();
}
?>
```

### 2. تست Webhook تلگرام
```bash
curl -X POST https://your-domain.com/webhook.php \
  -H "Content-Type: application/json" \
  -d '{"update_id":123456789,"message":{"message_id":1,"from":{"id":123456789,"is_bot":false,"first_name":"Test","username":"testuser"},"chat":{"id":123456789,"first_name":"Test","username":"testuser","type":"private"},"date":1234567890,"text":"/start"}}'
```

### 3. تست Cron Jobs
```bash
# تست دستی اسکریپت‌های کرون
php /path/to/telegram-web-app/cron/process_notifications.php
php /path/to/telegram-web-app/cron/check_service_expiry.php
```

## نگهداری و پشتیبانی

### لاگ‌گیری
سیستم لاگ‌گیری جامعی در فایل `logs/system.log` ایجاد شده است:

```php
// مثال استفاده از لاگ‌گیری
error_log("User registration: User ID {$userId}", 3, "logs/system.log");
```

### بک‌آپ‌گیری
#### بک‌آپ پایگاه داده
```bash
#!/bin/bash
# فایل backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u telegram_user -p'password' telegram_web > /backup/telegram_web_$DATE.sql
gzip /backup/telegram_web_$DATE.sql
find /backup -name "telegram_web_*.sql.gz" -mtime +30 -delete
```

#### بک‌آپ فایل‌ها
```bash
#!/bin/bash
# فایل backup_files.sh
DATE=$(date +%Y%m%d_%H%M%S)
tar -czf /backup/telegram_web_files_$DATE.tar.gz /path/to/telegram-web-app
find /backup -name "telegram_web_files_*.tar.gz" -mtime +30 -delete
```

### نظارت بر عملکرد
#### بررسی لاگ‌های خطا
```bash
tail -f /path/to/logs/system.log
tail -f /var/log/nginx/error.log
tail -f /var/log/php_errors.log
```

#### بررسی وضعیت Cron Jobs
```bash
grep CRON /var/log/syslog
# یا
systemctl status cron
```

## عیب‌یابی رایج

### مشکلات اتصال به تلگرام
1. بررسی توکن ربات
2. بررسی Webhook
3. بررسی SSL Certificate
4. بررسی محدودیت‌های تلگرام

### مشکلات پایگاه داده
1. بررسی اطلاعات اتصال
2. بررسی کاراکترست پایگاه داده
3. بررسی دسترسی‌های کاربر پایگاه داده

### مشکلات مالی
1. بررسی تنظیمات درگاه پرداخت
2. بررسی لاگ‌های تراکنش
3. بررسی تنظیمات کارت به کارت

## امنیت

### توصیه‌های امنیتی
1. **همیشه از HTTPS استفاده کنید**
2. **رمزهای عبور قوی انتخاب کنید**
3. **فایل‌های حساس را محدود کنید**
4. **بک‌آپ منظم داشته باشید**
5. **سیستم را به‌روز نگه دارید**
6. **لاگ‌ها را بررسی کنید**

### فایل‌های امنیتی
- `.htaccess` برای محدودسازی دسترسی
- `robots.txt` برای جلوگیری از ایندکس شدن توسط موتورهای جستجو
- `security.txt` برای اطلاعات امنیتی

## پشتیبانی و به‌روزرسانی

### گزارش مشکلات
برای گزارش مشکلات از ایمیل زیر استفاده کنید:
support@telegram-web.com

### به‌روزرسانی سیستم
1. بک‌آپ کامل بگیرید
2. فایل‌های جدید را دانلود کنید
3. تغییرات را اعمال کنید
4. تست کنید
5. در صورت وجود مشکل، ری‌استور کنید

## مجوز و حق کپی‌رایت

این پروژه تحت مجوز MIT منتشر شده است. برای اطلاعات بیشتر فایل LICENSE را ببینید.

## حمایت و کمک

اگر این پروژه برای شما مفید بوده، لطفاً آن را ستاره دهید و با دیگران به اشتراک بگذارید.

---

**تاریخ آخرین به‌روزرسانی:** 2024-12-21
**نسخه:** 1.0.0
**پشتیبانی:** support@telegram-web.com