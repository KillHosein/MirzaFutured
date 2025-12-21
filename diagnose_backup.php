<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/function.php';

echo "=== دلایل عدم اجرای بکاپ در حالت عادی ===\n\n";

// بررسی شرایط اصلی
$setting = select("setting", "*");
$bots = select("botsaz", "*", null, null, "fetchAll");

$reasons = [];

// دلیل 1: تنظیمات کلی بکاپ
$globalEnabled = !empty($setting['auto_backup_enabled']);
$globalMinutes = isset($setting['auto_backup_minutes']) ? (int)$setting['auto_backup_minutes'] : 0;

if (!$globalEnabled) {
    $reasons[] = "❌ بکاپ خودکار در تنظیمات کلی غیرفعال است (auto_backup_enabled = false)";
} elseif ($globalMinutes <= 0) {
    $reasons[] = "❌ بازه زمانی بکاپ در تنظیمات کلی تنظیم نشده (auto_backup_minutes = $globalMinutes)";
} else {
    echo "✅ تنظیمات کلی: فعال، هر $globalMinutes دقیقه\n";
}

// دلیل 2: بات‌های فعال
$activeBotCount = 0;
$totalBotCount = 0;
$botsWithSettings = [];

foreach ($bots as $bot) {
    $totalBotCount++;
    $botSetting = json_decode($bot['setting'] ?? '{}', true);
    $enabled = !empty($botSetting['auto_backup_enabled']);
    $minutes = isset($botSetting['auto_backup_minutes']) ? (int)$botSetting['auto_backup_minutes'] : 0;
    
    if ($enabled && $minutes > 0) {
        $activeBotCount++;
        $botsWithSettings[] = $bot;
    }
}

if ($activeBotCount == 0 && !$globalEnabled) {
    $reasons[] = "❌ هیچ باتی فعال برای بکاپ وجود ندارد";
} elseif ($activeBotCount > 0) {
    echo "✅ تعداد بات‌های فعال: $activeBotCount از $totalBotCount\n";
} else {
    echo "ℹ️ هیچ باتی فعال نیست، اما تنظیمات کلی ممکن است بکاپ را انجام دهد\n";
}

// دلیل 3: زمان‌بندی
echo "\n=== بررسی زمان‌بندی ===\n";

$now = time();
$hasDueBackup = false;

// بررسی بکاپ SQL جهانی
$globalLastSqlTs = isset($setting['backup_sql_last_ts']) ? (int)$setting['backup_sql_last_ts'] : 0;
if ($globalEnabled && $globalMinutes > 0) {
    $sqlTimeDiff = $now - $globalLastSqlTs;
    $sqlRequiredDiff = $globalMinutes * 60;
    $sqlIsDue = $sqlTimeDiff >= $sqlRequiredDiff;
    
    echo "بکاپ SQL جهانی:\n";
    echo "- آخرین بکاپ: " . ($globalLastSqlTs ? date('Y-m-d H:i:s', $globalLastSqlTs) : 'هرگز') . "\n";
    echo "- زمان گذشته: " . round($sqlTimeDiff / 60, 1) . " دقیقه\n";
    echo "- زمان مورد نیاز: $globalMinutes دقیقه\n";
    echo "- وضعیت: " . ($sqlIsDue ? '✅ زمانش رسیده' : '⏰ هنوز نرسیده') . "\n";
    
    if ($sqlIsDue) {
        $hasDueBackup = true;
    }
}

// بررسی بات‌های فردی
foreach ($botsWithSettings as $bot) {
    $botSetting = json_decode($bot['setting'] ?? '{}', true);
    $minutes = isset($botSetting['auto_backup_minutes']) ? (int)$botSetting['auto_backup_minutes'] : 0;
    $lastTs = isset($botSetting['auto_backup_last_ts']) ? (int)$botSetting['auto_backup_last_ts'] : 0;
    
    $timeDiff = $now - $lastTs;
    $requiredDiff = $minutes * 60;
    $isDue = $timeDiff >= $requiredDiff;
    
    echo "\nبات @{$bot['username']}:\n";
    echo "- آخرین بکاپ: " . ($lastTs ? date('Y-m-d H:i:s', $lastTs) : 'هرگز') . "\n";
    echo "- زمان گذشته: " . round($timeDiff / 60, 1) . " دقیقه\n";
    echo "- زمان مورد نیاز: $minutes دقیقه\n";
    echo "- وضعیت: " . ($isDue ? '✅ زمانش رسیده' : '⏰ هنوز نرسیده') . "\n";
    
    if ($isDue) {
        $hasDueBackup = true;
    }
}

// جمع‌بندی
echo "\n=== جمع‌بندی ===\n";

if (count($reasons) > 0) {
    echo "دلایل عدم اجرای بکاپ:\n";
    foreach ($reasons as $reason) {
        echo $reason . "\n";
    }
} elseif ($hasDueBackup) {
    echo "✅ بکاپ باید اجرا شود - مشکل از جای دیگری است\n";
    echo "💡 احتمالاً مشکل در اجرای کرون جاب یا مسائل فنی دیگر است\n";
} else {
    echo "ℹ️ هیچ بکاپی زمانش نرسیده - همه چیز طبیعی است\n";
    echo "💡 بکاپ در زمان مقرر خودش اجرا خواهد شد\n";
}

echo "\n=== راه‌حل‌ها ===\n";
if (count($reasons) > 0) {
    echo "1. برای تست فوری: از --force استفاده کنید\n";
    echo "2. برای فعال‌سازی دائمی:\n";
    echo "   - وارد پنل ادمین شوید\n";
    echo "   - گزینه '🗂 دریافت بکاپ' را انتخاب کنید\n";
    echo "   - زمان‌بندی را تنظیم کنید (مثلاً 60 دقیقه)\n";
    echo "   - منابع بکاپ را انتخاب کنید (db, files, configs)\n";
} else {
    echo "✅ همه چیز طبیعی است - منتظر زمان‌بندی باشید\n";
}