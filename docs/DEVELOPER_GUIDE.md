# راهنمای توسعه‌دهندگان

## ساختار کلی
- پنل مبتنی بر PHP/jQuery/Bootstrap با افزوده‌های Chart.js و Vue برای تعاملات سبک.
- اسکریپت‌های عمومی در `panel/js/common-scripts.js` و جداول در `panel/js/dynamic-table.js`.
- مینی‌اپ تلگرام در مسیر `app/` با فرانت‌اند React (خروجی Vite/Tailwind) و ورودی PHP در `app/index.php`.

## اعلان‌ها
- Endpoint سبک: `panel/metrics.php` خروجی JSON شامل شمارنده‌ها و آخرین اقلام.
- Poll هر 10 ثانیه در `common-scripts.js` و رندر منوی اعلان‌ها.

## داشبورد
- نمودارها با Chart.js در `panel/index.php`.
- ترجیحات نمایش نمودارها با Vue و ذخیره در localStorage.

## فیلترها و ستون‌ها
- توابع سراسری: `setupSavedFilter`, `attachColumnToggles`, `attachSelectionCounter`.

## تست و کیفیت
- برای بررسی سریع PHP، از `php -l` روی فایل‌های تغییر یافته استفاده کنید.
- تست‌های ساده بدون وابستگی در `tests/run.php` قرار دارند و با `php tests/run.php` اجرا می‌شوند.
- برای صفحات سنگین، از DataTables pagination استفاده کنید و از DOM سنگین پرهیز کنید.

## UI/UX مینی‌اپ تلگرام
- بارگذاری و پیکربندی اولیه در `app/index.php` انجام می‌شود (ارسال `window.__APP_CONFIG__` و هدرهای امنیتی).
- بهبودهای UI در زمان اجرا (تم، دسترس‌پذیری و ابزارهای سریع) از طریق این دو فایل اعمال می‌شوند:
  - `app/assets/runtime-ui.js`
  - `app/assets/runtime-ui.css`
- تنظیمات کاربر در localStorage ذخیره می‌شود (کلیدهای `tg_ui_*`) و با تم تلگرام از طریق `Telegram.WebApp.themeParams` همگام می‌گردد.
