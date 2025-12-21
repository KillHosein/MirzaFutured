<?php
/**
 * سیستم همگام‌سازی هوشمند اینباندها (Enterprise Sync)
 * طراحی شده برای محیط گرافیکی و CLI
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../x-ui_single.php';
require_once __DIR__ . '/../alireza.php';
require_once __DIR__ . '/../alireza_single.php';
require_once __DIR__ . '/../marzneshin.php';
require_once __DIR__ . '/../hiddify.php';
require_once __DIR__ . '/../s_ui.php';

// جلوگیری از اجرای همزمان
$lockFile = __DIR__ . '/sync_inbounds.lock';
$fp = fopen($lockFile, 'c+');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "خطا: یک عملیات همگام‌سازی دیگر در حال اجرا است.";
    return;
}

$is_cli = (php_sapi_name() === 'cli' || defined('CRON_RUNNING'));

if (!$is_cli) {
    session_start();
    if (!isset($_SESSION["user"])) {
        echo "دسترسی غیرمجاز";
        return;
    }
    
    // فعال‌سازی خروجی لحظه‌ای برای مرورگر
    ob_implicit_flush(true);
    while (ob_get_level()) ob_end_clean();
}

/**
 * تابع کمکی برای چاپ پیام‌ها با استایل مناسب
 */
function logMessage($msg, $type = 'info') {
    global $is_cli;
    $time = date('H:i:s');
    $colors = [
        'info'    => '#00f2ff',
        'success' => '#10b981',
        'warning' => '#fbbf24',
        'error'   => '#ff2a6d'
    ];
    $color = $colors[$type] ?? '#fff';

    if ($is_cli) {
        echo "[$time] $msg\n";
    } else {
        echo "<script>
            const logBox = document.getElementById('log-container');
            const item = document.createElement('div');
            item.className = 'log-item';
            item.innerHTML = `<span style='color: #64748b; font-size: 0.8rem;'>[$time]</span> <span style='color: $color'>$msg</span>`;
            logBox.appendChild(item);
            logBox.scrollTop = logBox.scrollHeight;
        </script>";
        usleep(50000); // وقفه کوتاه برای تجربه بصری بهتر
    }
}

// شروع قالب HTML برای مرورگر
if (!$is_cli): ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>همگام‌سازی اینباندها</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        body {
            background-color: #050509;
            color: #fff;
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 40px 20px;
            display: flex; justify-content: center;
        }
        .sync-card {
            width: 100%; max-width: 800px;
            background: rgba(23, 23, 30, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px; padding: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
        }
        .header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; }
        .header i { font-size: 2rem; color: #00f2ff; text-shadow: 0 0 15px rgba(0,242,255,0.5); }
        .header h1 { font-size: 1.5rem; margin: 0; font-weight: 800; }
        
        #log-container {
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            padding: 20px;
            height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .log-item { margin-bottom: 8px; font-size: 0.95rem; line-height: 1.6; border-bottom: 1px solid rgba(255,255,255,0.02); padding-bottom: 4px; }
        
        .footer-actions { margin-top: 25px; display: flex; justify-content: space-between; align-items: center; }
        .btn-back {
            text-decoration: none; color: #94a3b8; font-size: 0.9rem;
            display: flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .btn-back:hover { color: #fff; }
        
        .loader {
            width: 20px; height: 20px; border: 2px solid #00f2ff;
            border-bottom-color: transparent; border-radius: 50%;
            display: inline-block; animation: rotation 1s linear infinite;
        }
        @keyframes rotation { 0% { transform: rotate(0deg) } 100% { transform: rotate(360deg) } }

        /* Scrollbar */
        #log-container::-webkit-scrollbar { width: 6px; }
        #log-container::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    </style>
</head>
<body>
    <div class="sync-card">
        <div class="header">
            <i class="fa-solid fa-rotate"></i>
            <div>
                <h1>همگام‌سازی اینباندها</h1>
                <div style="font-size: 0.85rem; color: #94a3b8;">در حال دریافت و به‌روزرسانی داده‌ها از پنل‌ها...</div>
            </div>
        </div>

        <div id="log-container">
            <!-- Logs will appear here -->
        </div>

        <div class="footer-actions">
            <a href="../panel/inbound.php" class="btn-back">
                <i class="fa-solid fa-arrow-right"></i> بازگشت به اینباندها
            </a>
            <div id="status-tag" style="font-size: 0.9rem; color: #00f2ff;">
                <span class="loader"></span> در حال اجرا...
            </div>
        </div>
    </div>
<?php endif;

logMessage("شروع فرآیند همگام‌سازی اینباندها...", "info");

try {
    // مرحله ۰: اطمینان از وجود جداول
    $result = $pdo->query("SHOW TABLES LIKE 'Inbound'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE Inbound (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location VARCHAR(200) NULL,
            protocol VARCHAR(50) NULL,
            nameinbound VARCHAR(200) NULL,
            setting TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        logMessage("جدول Inbound ایجاد شد.", "success");
    }

    $result = $pdo->query("SHOW TABLES LIKE 'x_ui'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE x_ui (
            codepanel VARCHAR(200) PRIMARY KEY,
            setting TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        logMessage("جدول x_ui ایجاد شد.", "success");
    }

    // مرحله ۱: پاکسازی داده‌های قدیمی
    $pdo->exec("TRUNCATE TABLE Inbound");
    logMessage("جدول اینباندها برای داده‌های جدید پاکسازی شد.", "warning");

    // مرحله ۲: دریافت تمام پنل‌ها
    $panels = select("marzban_panel", "*", null, null, "fetchAll");
    
    if (empty($panels)) {
        logMessage("هیچ پنلی برای همگام‌سازی یافت نشد.", "error");
    }

    foreach ($panels as $panel) {
        $panelName = $panel['name_panel'];
        $panelType = $panel['type'];
        
        logMessage("در حال پردازش پنل: <b>$panelName</b> ($panelType)", "info");

        $inbounds = [];
        try {
            if ($panelType == 'marzban') {
                $inbounds = getinbounds($panelName);
            } elseif ($panelType == 'x-ui_single') {
                $inbounds = getinbounds_xui($panelName);
            } elseif ($panelType == 'alireza') {
                $inbounds = getinbounds_alireza($panelName);
            } elseif ($panelType == 'alireza_single') {
                $inbounds = getinbounds_alireza_single($panelName);
            } elseif ($panelType == 'marzneshin') {
                $inbounds = getinbounds_marzneshin($panelName);
            } elseif ($panelType == 'hiddify') {
                $inbounds = getinbounds_hiddify($panelName);
            } elseif ($panelType == 's_ui') {
                $inbounds = getinbounds_sui($panelName);
            }

            if (is_array($inbounds) && count($inbounds) > 0) {
                foreach ($inbounds as $key => $inbound) {
                    $name = $inbound['tag'] ?? $inbound['remark'] ?? $inbound['name'] ?? "Inbound $key";
                    $protocol = $inbound['protocol'] ?? 'unknown';
                    $settings = json_encode($inbound);

                    $stmt = $pdo->prepare("INSERT INTO Inbound (location, protocol, nameinbound, setting) VALUES (:loc, :prot, :name, :set)");
                    $stmt->execute([
                        ':loc' => $panelName,
                        ':prot' => $protocol,
                        ':name' => $name,
                        ':set' => $settings
                    ]);
                }
                logMessage("تعداد " . count($inbounds) . " اینباند با موفقیت همگام شد.", "success");
            } else {
                logMessage("اینباندی یافت نشد یا خطا در اتصال به پنل رخ داد.", "error");
            }
        } catch (Exception $innerEx) {
            logMessage("خطا در پردازش پنل $panelName: " . $innerEx->getMessage(), "error");
        }
    }
    
    logMessage("عملیات همگام‌سازی با موفقیت به پایان رسید.", "success");

    if (!$is_cli) {
        echo "<script>
            document.getElementById('status-tag').innerHTML = '<i class=\"fa-solid fa-check-circle\"></i> تکمیل شده';
            document.getElementById('status-tag').style.color = '#10b981';
        </script>";
    }

} catch (Exception $e) {
    logMessage("خطای بحرانی: " . $e->getMessage(), "error");
}

if (!$is_cli) {
    echo "</body></html>";
}