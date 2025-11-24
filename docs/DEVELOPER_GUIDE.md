# راهنمای توسعه‌دهندگان

## ساختار کلی
- پنل مبتنی بر PHP/jQuery/Bootstrap با افزوده‌های Chart.js و Vue برای تعاملات سبک.
- اسکریپت‌های عمومی در `panel/js/common-scripts.js` و جداول در `panel/js/dynamic-table.js`.

## اعلان‌ها
- Endpoint سبک: `panel/metrics.php` خروجی JSON شامل شمارنده‌ها و آخرین اقلام.
- Poll هر 10 ثانیه در `common-scripts.js` و رندر منوی اعلان‌ها.

## داشبورد
- نمودارها با Chart.js در `panel/index.php`.
- ترجیحات نمایش نمودارها با Vue و ذخیره در localStorage.

## فیلترها و ستون‌ها
- توابع سراسری: `setupSavedFilter`, `attachColumnToggles`, `attachSelectionCounter`.

## تست و کیفیت
- لطفاً قبل از استقرار، lint/n功能 را اجرا نمایید. اگر دستورات lint پروژه موجود نیست، اضافه کنید.
- برای صفحات سنگین، از DataTables pagination استفاده کنید و از DOM سنگین پرهیز کنید.
