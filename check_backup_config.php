<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/function.php';

echo "=== بررسی تنظیمات بکاپ ===\n\n";

// بررسی تنظیمات کلی
$setting = select("setting", "*");
echo "تنظیمات کلی:\n";
echo "- auto_backup_enabled: " . (!empty($setting['auto_backup_enabled']) ? 'فعال' : 'غیرفعال') . "\n";
echo "- auto_backup_minutes: " . ($setting['auto_backup_minutes'] ?? 0) . "\n";
echo "- backup_sql_last_ts: " . ($setting['backup_sql_last_ts'] ?? 0) . " (" . date('Y-m-d H:i:s', $setting['backup_sql_last_ts'] ?? 0) . ")\n\n";

// بررسی تنظیمات بات‌ها
$bots = select("botsaz", "*", null, null, "fetchAll");
$activeBots = 0;
$totalBots = 0;

foreach ($bots as $bot) {
    $totalBots++;
    $botSetting = json_decode($bot['setting'] ?? '{}', true);
    $enabled = !empty($botSetting['auto_backup_enabled']);
    $minutes = isset($botSetting['auto_backup_minutes']) ? (int)$botSetting['auto_backup_minutes'] : 0;
    $lastTs = isset($botSetting['auto_backup_last_ts']) ? (int)$botSetting['auto_backup_last_ts'] : 0;
    
    echo "بات @{$bot['username']} (ID: {$bot['id_user']}):\n";
    echo "  - فعال: " . ($enabled ? 'بله' : 'خیر') . "\n";
    echo "  - دقیقه: " . $minutes . "\n";
    echo "  - آخرین بکاپ: " . ($lastTs ? date('Y-m-d H:i:s', $lastTs) : 'هرگز') . "\n";
    
    if ($enabled && $minutes > 0) {
        $activeBots++;
        $now = time();
        $timeDiff = $now - $lastTs;
        $requiredDiff = $minutes * 60;
        $isDue = $timeDiff >= $requiredDiff;
        
        echo "  - زمان گذشته: " . round($timeDiff / 60, 1) . " دقیقه\n";
        echo "  - زمان مورد نیاز: " . $minutes . " دقیقه\n";
        echo "  - وضعیت: " . ($isDue ? '✅ زمان بکاپ رسیده' : '⏰ هنوز زمانش نرسیده') . "\n";
    }
    echo "\n";
}

echo "=== خلاصه ===\n";
echo "- کل بات‌ها: $totalBots\n";
echo "- بات‌های فعال: $activeBots\n";
echo "- زمان فعلی: " . date('Y-m-d H:i:s') . "\n";

// بررسی اینکه آیا بکاپ باید اجرا شود
$anyEnabled = false;
$minEnabledMinutes = null;
$globalMinutes = isset($setting['auto_backup_minutes']) ? (int)$setting['auto_backup_minutes'] : 0;
$globalEnabled = !empty($setting['auto_backup_enabled']);

if ($globalEnabled && $globalMinutes > 0) {
    $anyEnabled = true;
    $minEnabledMinutes = $globalMinutes;
}

foreach ($bots as $bot) {
    $botSetting = json_decode($bot['setting'] ?? '{}', true);
    $enabled = !empty($botSetting['auto_backup_enabled']);
    $minutes = isset($botSetting['auto_backup_minutes']) ? (int)$botSetting['auto_backup_minutes'] : 0;
    
    if ($enabled && $minutes > 0) {
        $anyEnabled = true;
        if ($minEnabledMinutes === null || $minutes < $minEnabledMinutes) {
            $minEnabledMinutes = $minutes;
        }
    }
}

$globalLastSqlTs = isset($setting['backup_sql_last_ts']) ? (int)$setting['backup_sql_last_ts'] : 0;
$nowBase = time();
$globalDueAggregate = ($anyEnabled && $minEnabledMinutes !== null) ? (($nowBase - $globalLastSqlTs) >= ($minEnabledMinutes * 60)) : false;

echo "\n=== وضعیت بکاپ SQL ===\n";
echo "- anyEnabled: " . ($anyEnabled ? 'بله' : 'خیر') . "\n";
echo "- minEnabledMinutes: " . ($minEnabledMinutes ?? 'null') . "\n";
echo "- lastSqlTs: " . ($globalLastSqlTs ? date('Y-m-d H:i:s', $globalLastSqlTs) : 'null') . "\n";
echo "- زمان گذشته از آخرین بکاپ SQL: " . round(($nowBase - $globalLastSqlTs) / 60, 1) . " دقیقه\n";
echo "- آیا بکاپ SQL باید اجرا شود: " . ($globalDueAggregate ? 'بله' : 'خیر') . "\n";

echo "\n=== نتیجه نهایی ===\n";
if ($anyEnabled && ($activeBots > 0 || $globalEnabled)) {
    echo "✅ بکاپ باید اجرا شود (اگر با --force اجرا کنید حتماً اجرا می‌شود)\n";
} else {
    echo "❌ بکاپ غیرفعال است یا هیچ باتی فعال نیست\n";
}