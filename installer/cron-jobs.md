# مستندات کورن‌جاب‌ها (Cron Jobs)

این پروژه چند مسیر برای اجرای کارهای زمان‌بندی‌شده دارد:

- روی VPS (نصب خودکار): `install.sh` کرون‌ها را در `crontab` ثبت می‌کند.
- روی هاست اشتراکی: از طریق پنل Cron Jobs هاست، کرون‌ها را بر اساس مسیر فایل یا `curl` تنظیم می‌کنید.
- داخل پروژه: تابع `activecron()` در `function.php` کرون‌های مبتنی بر `curl` را در `crontab` اضافه می‌کند.

## مانیتورینگ و لاگ‌ها

سه منبع اصلی برای بررسی اجرای موفق کرون‌ها وجود دارد:

- دیتابیس: جدول `cron_runs` (به صورت خودکار در اولین اجرا ساخته می‌شود)
- لاگ خطاهای PHP هر کرون: `cronbot/logs/<job>.error.log` (اگر قابل‌نوشتن نباشد، در `sys_get_temp_dir()` ساخته می‌شود)
- لاگ خروجی/خطای کرون در سیستم (برای نصب VPS): `/var/log/mirza_pro/cron/<job>.log`

برای مشاهده وضعیت آخرین اجراها:

- مرورگر (فقط با سشن پنل): `https://<domain>/cronbot/cron_status.php`
- CLI: `php cronbot/cron_status.php`

## لیست کرون‌جاب‌های اصلی

در حالت پیش‌فرض (منطبق با `function.php:1716` و `install.sh`)، زمان‌بندی پیشنهادی به شکل زیر است:

| نام | زمان‌بندی | فایل | توضیح |
|---|---:|---|---|
| `statusday` | `*/15 * * * *` | `cronbot/statusday.php` | گزارش روزانه (ارسال نزدیک 23:45) |
| `NoticationsService` | `* * * * *` | `cronbot/NoticationsService.php` | اعلان‌های نزدیک به پایان/حجم/حذف سرویس |
| `croncard` | `* * * * *` | `cronbot/croncard.php` | تایید خودکار کارت‌به‌کارت/آفلاین (در صورت فعال بودن) |
| `payment_expire` | `*/5 * * * *` | `cronbot/payment_expire.php` | انقضای پرداخت‌های معلق (قدیمی‌تر از 24 ساعت) |
| `sendmessage` | `* * * * *` | `cronbot/sendmessage.php` | ارسال پیام‌های صف‌شده (۲۰ نفر در هر اجرا) |
| `plisio` | `*/3 * * * *` | `cronbot/plisio.php` | بررسی وضعیت تراکنش‌های Plisio |
| `iranpay1` | `* * * * *` | `cronbot/iranpay1.php` | بررسی وضعیت تراکنش‌های ایران‌پی (Currency Rial 3) |
| `activeconfig` | `* * * * *` | `cronbot/activeconfig.php` | فعال‌سازی سرویس‌های `disablebyadmin` برای کاربران دارای `checkstatus=1` |
| `disableconfig` | `* * * * *` | `cronbot/disableconfig.php` | غیرفعال‌سازی سرویس‌ها برای کاربران دارای `checkstatus=2` |
| `backupbot` | `* * * * *` | `cronbot/backupbot.php` | اجرای بکاپ زمان‌بندی‌شده بر اساس تنظیمات (گیتینگ داخلی) |
| `gift` | `*/2 * * * *` | `cronbot/gift.php` | پردازش هدایا از صف فایل (`cronbot/gift` و `cronbot/username.json`) |
| `expireagent` | `*/30 * * * *` | `cronbot/expireagent.php` | پایان نمایندگی و تغییر نقش به `f` |
| `on_hold` | `*/15 * * * *` | `cronbot/on_hold.php` | پیام به کاربران on_hold که مدت مشخصی وصل نشده‌اند |
| `configtest` | `*/2 * * * *` | `cronbot/configtest.php` | پاکسازی سرویس‌های تست در صورت فعال بودن کرون تست |
| `uptime_node` | `*/15 * * * *` | `cronbot/uptime_node.php` | آلارم نودهای غیرمتصل (در صورت فعال بودن) |
| `uptime_panel` | `*/15 * * * *` | `cronbot/uptime_panel.php` | آلارم پنل‌های غیرقابل اتصال (در صورت فعال بودن) |
| `lottery` | `0 0 * * *` | `cronbot/lottery.php` | قرعه‌کشی شبانه (در صورت فعال بودن) |

## وابستگی‌ها و پیش‌نیازهای مهم

پیش‌نیازهای مشترک:

- PHP 8.2 (یا حداقل هم‌سطح وابستگی‌های Composer)
- اکستنشن‌ها: `pdo_mysql`, `mysqli`, `curl`, `openssl`, `json`
- دسترسی شبکه خروجی برای APIها (تلگرام، Plisio، ایران‌پی، پنل‌ها)
- دسترسی نوشتن برای لاگ‌ها (`cronbot/logs` یا مسیر موقت سیستم)

پیش‌نیازهای دیتابیس:

- اکثر کرون‌ها به جدول‌های `setting`, `invoice`, `user`, `Payment_report`, `marzban_panel` نیاز دارند.
- مانیتورینگ اجراها به جدول `cron_runs` نیاز دارد (به صورت خودکار ساخته می‌شود).

پیش‌نیازهای تنظیمات:

- `setting.cron_status` تعیین می‌کند کدام بخش‌ها فعال باشند.
  - `uptime_node`, `uptime_panel`، و `configtest` در صورت `false` بودن، اجرا را با وضعیت `skipped` ثبت می‌کنند.
  - `NoticationsService` چند پرچم داخلی (مثل `day`, `volume`, `remove`, `remove_volume`) را استفاده می‌کند.

## نحوه تست در محیط‌های مختلف (dev / staging / prod)

### 1) تست سریع اجرای اسکریپت‌ها

- اجرای دستی هر کرون در CLI:
  - `php cronbot/NoticationsService.php`
  - `php cronbot/payment_expire.php`
  - ...
- بعد از اجرا، خروجی جدول `cron_runs` را از `cronbot/cron_status.php` بررسی کنید.

### 2) تست لاگ‌ها

- لاگ خطاهای PHP هر کرون:
  - `cronbot/logs/<job>.error.log`
- لاگ خروجی کرون سیستم (VPS):
  - `/var/log/mirza_pro/cron/<job>.log`

### 3) تست زمان‌بندی واقعی

- روی VPS:
  - با `crontab -l` وجود خطوط کرون را بررسی کنید.
  - چند دقیقه بعد، باید رکوردهای جدید در `cron_runs` دیده شود.
- روی هاست اشتراکی:
  - اگر کرون را با `curl` می‌زنید، `curl -fsS https://<domain>/cronbot/<job>.php` را دستی تست کنید.
  - اگر با PHP اجرا می‌کنید، مطمئن شوید مسیر PHP و مسیر فایل صحیح است.

## نکات امنیتی و پایداری

- برای جلوگیری از اجرای همزمان، هر کرون قفل فایل (lock) دارد و در صورت همزمانی، با وضعیت `skipped` ثبت می‌شود.
- در صورت خطای fatal یا exception، وضعیت `failed` در `cron_runs` ثبت می‌شود و جزئیات در `message` و فایل `.error.log` می‌آید.

