# راهنمای فنی مینی‌اپ تلگرام (WebApp)

این مینی‌اپ در مسیر `app/` پیاده‌سازی شده و از APIهای موجود پروژه استفاده می‌کند:

- احراز هویت: `api/verify.php`
- خرید/سرویس‌ها: `api/miniapp.php`
- شارژ کیف پول (افزوده‌شده): `api/miniapp_wallet.php`

## 1) پیش‌نیازهای تلگرام

1. در BotFather برای ربات، Web App را تنظیم کنید.
2. دامنه‌ی سایت را در تنظیمات ربات (Domain / Web App) ثبت کنید.
3. آدرس Web App را روی مسیر زیر قرار دهید:
   - `https://YOUR_DOMAIN/app/`

## 2) روند احراز هویت و امنیت

1. مینی‌اپ هنگام اجرا از `Telegram.WebApp.initData` استفاده می‌کند.
2. `initData` به `api/verify.php` ارسال می‌شود.
3. در پاسخ، توکن جدید دریافت می‌شود.
4. تمام درخواست‌های بعدی به `api/miniapp.php` و `api/miniapp_wallet.php` با هدر زیر ارسال می‌شود:
   - `Authorization: Bearer <token>`

توکن در جدول `user` و فیلد `token` ذخیره می‌شود و برای هر بار ورود، تغییر می‌کند.

## 3) مسیرهای رابط کاربری

مسیریابی داخل مینی‌اپ بر پایه `hash` انجام می‌شود:

- `#/home` داشبورد
- `#/wallet` کیف پول (شارژ و تاریخچه)
- `#/shop` خرید سرویس (انتخاب موقعیت، فیلتر، سرویس دلخواه در صورت فعال بودن)
- `#/services` سرویس‌های فعال و جزئیات/کانفیگ

فایل‌های اصلی:

- `app/index.html`
- `app/assets/app.css`
- `app/assets/app.js`

## 4) API شارژ کیف پول (miniapp_wallet)

فایل: `api/miniapp_wallet.php`

### 4.1) دریافت متادیتا
`GET /api/miniapp_wallet.php?actions=meta&user_id=...`

### 4.2) دریافت روش‌های پرداخت فعال
`GET /api/miniapp_wallet.php?actions=methods&user_id=...`

### 4.3) ایجاد لینک پرداخت
`POST /api/miniapp_wallet.php`

Body:
```json
{
  "actions": "create",
  "user_id": 123456,
  "amount": 100000,
  "method": "zarinpal"
}
```

پاسخ موفق:
```json
{
  "status": true,
  "msg": "Successful",
  "obj": {
    "order_id": "...",
    "payment_url": "https://...",
    "amount": 100000,
    "method": "zarinpal"
  }
}
```

### 4.4) وضعیت یک تراکنش
`GET /api/miniapp_wallet.php?actions=status&user_id=...&order_id=...`

### 4.5) تاریخچه تراکنش‌ها
`GET /api/miniapp_wallet.php?actions=history&user_id=...`

## 5) نکات استقرار

- مینی‌اپ یک SPA بدون نیاز به build است و روی هر هاست PHP قابل سرو است.
- مسیر `app/` باید از طریق وب‌سرور قابل دسترسی باشد.
- APIها (`api/*`) باید در همان دامنه (یا با CORS مناسب) در دسترس باشند.

