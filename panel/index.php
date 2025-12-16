<?php
// --- خطایابی و گزارش‌دهی PHP (برای پیدا کردن علت خطای 500) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// فرض می‌کنیم این فایل‌ها در دسترس هستند و حاوی توابع مورد نیازند
require_once '../config.php';
require_once '../jdf.php';

// --- بررسی حیاتی: اطمینان از تعریف متغیر اتصال به دیتابیس ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // This message is translated to be more friendly and helpful.
    die("خطای مهلک: متغیر اتصال به دیتابیس (\$pdo) تعریف نشده یا یک شیء PDO نیست. لطفاً فایل 'config.php' را بررسی کنید.");
}

// --- Logic Section ---
$datefirstday = time() - 86400; // Time yesterday (for new users calculation)
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];

if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// 1. Authentication Check
try {
    if( !isset($_SESSION["user"]) ){
        header('Location: login.php');
        exit;
    }
    
    $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->execute(['username' => $_SESSION["user"]]); 
    $result = $query->fetch(PDO::FETCH_ASSOC);
    
    if(!$result ){
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Auth failed: " . $e->getMessage());
    die("خطای دیتابیس هنگام بررسی احراز هویت. لطفاً لاگ‌ها را بررسی کنید. پیام: " . $e->getMessage());
}


// 2. Filter Logic for Invoices
$invoiceWhere = ["name_product != 'سرویس تست'"];
$invoiceParams = [];

// Date Filtering
if($fromDate && strtotime($fromDate)){
    $invoiceWhere[] = "time_sell >= :fromTs";
    $invoiceParams[':fromTs'] = strtotime($fromDate);
}
if($toDate && strtotime($toDate)){
    $invoiceWhere[] = "time_sell <= :toTs";
    // Adding 23:59:59 to include the entire 'to' day
    $invoiceParams[':toTs'] = strtotime($toDate.' 23:59:59');
}

// Status Filtering
if(!empty($selectedStatuses)){
    $placeholders = [];
    foreach ($selectedStatuses as $i => $status) {
        $placeholder = ":status_$i";
        $placeholders[] = $placeholder;
        $invoiceParams[$placeholder] = $status;
    }
    $invoiceWhere[] = "status IN (" . implode(', ', $placeholders) . ")";
}else{
    // Default statuses to include most relevant orders if no filter is applied
    $invoiceWhere[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";
}

$invoiceWhereSql = implode(' AND ', $invoiceWhere);

// 3. Sales and User Counts

try {
    // Total Sales Amount
    $query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'"); // Exclude unpaid from total sales
    $query->execute($invoiceParams);
    $subinvoice = $query->fetch(PDO::FETCH_ASSOC);
    $total_sales = $subinvoice['SUM(price_product)'] ?? 0;

    // Total User Counts (Overall)
    $query = $pdo->prepare("SELECT COUNT(*) FROM user");
    $query->execute();
    $resultcount = $query->fetchColumn();

    // New Users Today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :time_register AND register != 'none'");
    $stmt->execute([':time_register' => $datefirstday]); 
    $resultcountday = $stmt->fetchColumn();

    // Sales Count (Filtered)
    $query = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'"); // Exclude unpaid from order count
    $query->execute($invoiceParams);
    $resultcontsell = $query->fetchColumn();
} catch (PDOException $e) {
    die("خطای دیتابیس هنگام بازیابی داده‌ها. پیام: " . $e->getMessage());
}

$formatted_total_sales = number_format($total_sales);

// 4. Chart Data: Sales Trend
$grouped_data = [];
if($resultcontsell > 0){
    try {
        // Fetch only paid sales data for the chart
        $query = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid' ORDER BY time_sell DESC;");
        $query->execute($invoiceParams);
        $salesData = $query->fetchAll(PDO::FETCH_ASSOC);

        foreach ($salesData as $sell){
            if(!is_numeric($sell['time_sell'])) continue; 
            
            $time_sell_day = date('Y/m/d', (int)$sell['time_sell']);
            $price = (int)$sell['price_product'];
            
            if (!isset($grouped_data[$time_sell_day])) {
                $grouped_data[$time_sell_day] = ['total_amount' => 0, 'order_count' => 0];
            }
            $grouped_data[$time_sell_day]['total_amount'] += $price;
            $grouped_data[$time_sell_day]['order_count'] += 1;
        }
        ksort($grouped_data); // Sort by date ascending for chart
    } catch (PDOException $e) {
        die("خطای دیتابیس هنگام دریافت روند فروش. پیام: " . $e->getMessage());
    }
}

// Convert Gregorian dates to Persian for chart labels
$salesLabels = array_values(array_map(function($d){ 
    return jdate('Y/m/d', strtotime($d)); 
}, array_keys($grouped_data)));
$salesAmount = array_values(array_map(function($i){ return $i['total_amount']; }, $grouped_data));

// 5. Chart Data: Status Distribution
$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت',
    'active' => 'فعال',
    'disabledn' => 'غیرفعال', 
    'end_of_time' => 'پایان زمان',
    'end_of_volume' => 'پایان حجم',
    'sendedwarn' => 'هشدار',
    'send_on_hold' => 'در انتظار اتصال',
    'removebyuser' => 'حذف شده'
];
$colorMap = [
    'unpaid' => '#fbbf24', // Amber
    'active' => '#10b981', // Emerald
    'disabledn' => '#94a3b8', // Slate
    'end_of_time' => '#ef4444', // Red
    'end_of_volume' => '#3b82f6', // Blue
    'sendedwarn' => '#a855f7', // Violet
    'send_on_hold' => '#f97316', // Orange
    'removebyuser' => '#475569' // Dark Slate
];

try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
    $stmt->execute($invoiceParams);
    $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطای دیتابیس هنگام دریافت توزیع وضعیت‌ها. پیام: " . $e->getMessage());
}

$statusLabels = [];
$statusData = [];
$statusColors = [];

foreach($statusRows as $r){
    $k = $r['status'];
    $statusLabels[] = isset($statusMapFa[$k]) ? $statusMapFa[$k] : $k;
    $statusData[] = (int)$r['cnt'];
    $statusColors[] = isset($colorMap[$k]) ? $colorMap[$k] : '#64748b';
}

// 6. Chart Data: New Users Trend
$userStart = ($fromDate && strtotime($fromDate)) ? strtotime(date('Y/m/d', strtotime($fromDate))) : (strtotime(date('Y/m/d')) - (13 * 86400));
$userEnd = ($toDate && strtotime($toDate)) ? strtotime(date('Y/m/d', strtotime($toDate))) : strtotime(date('Y/m/d'));
$daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);

try {
    $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :ustart AND register <= :uend");
    $stmt->execute([
        ':ustart' => $userStart,
        ':uend' => $userEnd + 86400 - 1
    ]);
    $regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطای دیتابیس هنگام دریافت روند کاربران جدید. پیام: " . $e->getMessage());
}

$userLabels = [];
$userCounts = [];
$indexByDate = [];

// Prepare labels for the time range
for($i=0;$i<$daysBack;$i++){
    $d = $userStart + $i*86400;
    $key = date('Y/m/d',$d);
    $indexByDate[$key] = count($userLabels);
    $userLabels[] = jdate('Y/m/d',$d); // Persian date label
    $userCounts[] = 0;
}

// Count registrations
foreach($regRows as $row){
    if(!is_numeric($row['register'])) continue;
    $key = date('Y/m/d', (int)$row['register']);
    if(isset($indexByDate[$key])){
        $userCounts[$indexByDate[$key]]++;
    }
}

// 7. Time Greeting Logic
$hour = date('H');
if ($hour < 12) { $greeting = "صبح بخیر"; $greetIcon = "icon-sun"; }
elseif ($hour < 17) { $greeting = "ظهر بخیر"; $greetIcon = "icon-coffee"; }
else { $greeting = "عصر بخیر"; $greetIcon = "icon-moon"; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت حرفه‌ای</title>
    
    <!-- Fonts: Vazirmatn is standard for Persian typography -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- CSS Dependencies -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <!-- Required for Daterange Picker -->
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        /* --- CSS Variables (Enhanced Dark/Glass Theme) --- */
        :root {
            --bg-body: #0b1121; /* Darker Slate */
            --glass-bg: rgba(18, 25, 40, 0.7); /* Deep glass effect */
            --glass-border: rgba(255, 255, 255, 0.15); /* Stronger border for visibility */
            --glass-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            
            --primary: #4f46e5; /* Indigo 600 */
            --primary-glow: rgba(79, 70, 229, 0.3);
            --secondary: #db2777; /* Pink 600 */
            --accent: #06b6d4; /* Cyan */
            
            --text-main: #f8fafc; /* White */
            --text-muted: #94a3b8; /* Slate 400 */
            
            --font-main: 'Vazirmatn', sans-serif;
            --header-height: 70px;
        }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(219, 39, 119, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: var(--font-main);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Global Layout --- */
        #main-content { margin-right: 0px; padding-top: var(--header-height); transition: all 0.3s; }
        .wrapper { padding: 30px; display: flex; flex-direction: column; gap: 30px; max-width: 1700px; margin: 0 auto; }
        .site-header {
            position: fixed; top: 0; right: 0; left: 0; height: var(--header-height); z-index: 100;
            background: rgba(11, 17, 33, 0.9); backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px); /* Safari support */
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex; align-items: center; padding: 0 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        }
        .header-title { color: #fff; font-size: 20px; font-weight: 800; padding-right: 20px; border-right: 3px solid var(--accent); }
        .header-nav { margin-right: auto; display: flex; gap: 20px; }
        .header-nav a { 
            color: var(--text-muted); text-decoration: none; padding: 8px 15px; border-radius: 10px; transition: 0.2s; 
            font-weight: 500; display: flex; align-items: center; gap: 8px;
        }
        .header-nav a:hover, .header-nav a.active { color: var(--text-main); background: rgba(255, 255, 255, 0.1); }
        
        /* Fade In Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-enter { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        /* Glassmorphism Card Style */
        .modern-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 28px;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .modern-card:hover { transform: translateY(-4px); box-shadow: 0 15px 45px rgba(0, 0, 0, 0.5); border-color: rgba(255,255,255,0.25); }

        /* --- Hero Section --- */
        .hero-banner { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .hero-title h1 { 
            font-size: 32px; font-weight: 900; 
            background: linear-gradient(to right, #e2e8f0, #fff); 
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin: 0 0 8px 0; 
            line-height: 1.2;
        }
        .hero-subtitle { font-size: 16px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; font-weight: 400; }
        .hero-subtitle i { color: var(--accent); }

        /* --- Filter Bar & Inputs --- */
        .filter-bar {
            background: rgba(18, 25, 40, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 15px 25px;
            display: flex; flex-wrap: wrap; align-items: center; gap: 15px;
            justify-content: space-between;
        }
        .filter-inputs { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; flex: 1; }
        
        .input-glass {
            background: rgba(45, 55, 72, 0.6);
            border: 1px solid #334155;
            color: var(--text-main);
            border-radius: 12px;
            padding: 12px 16px;
            font-family: var(--font-main);
            outline: none; transition: 0.3s;
            min-width: 190px;
            appearance: none;
            cursor: pointer;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);
        }
        .input-glass:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-glow), inset 0 1px 3px rgba(0,0,0,0.5); }
        .input-glass option { background-color: var(--bg-body); }

        .btn-gradient {
            background: linear-gradient(135deg, var(--primary), #5a51e8);
            color: white; border: none;
            padding: 12px 28px; border-radius: 12px;
            font-weight: 700; cursor: pointer;
            transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 5px 20px var(--primary-glow);
            display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none !important;
            line-height: 1;
        }
        .btn-gradient:hover { transform: translateY(-3px); box-shadow: 0 8px 30px var(--primary-glow); filter: brightness(1.1); }
        
        .btn-glass {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            padding: 10px 18px; border-radius: 12px;
            transition: 0.2s; cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            font-weight: 500;
        }
        .btn-glass:hover { background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.3); }

        /* Time Range Presets */
        .time-presets-group { 
            background: rgba(255,255,255,0.05); 
            padding: 5px; 
            border-radius: 15px; 
            display: flex; 
            border: 1px solid rgba(255,255,255,0.05);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        .time-presets-group .btn-glass { padding: 8px 14px; border-radius: 10px; border: none; background: transparent; }
        .time-presets-group .btn-glass:hover { background: rgba(255,255,255,0.1); }

        /* --- Stats --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .stat-card { display: flex; align-items: center; gap: 20px; }
        
        .stat-icon-wrapper { 
            width: 70px; height: 70px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 28px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.5); /* Icon Shadow */
            position: relative; overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(0,0,0,0.3);
        }
        .stat-icon-wrapper::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            opacity: 0.15; z-index: 1;
        }

        .stat-content h3 { 
            font-size: 30px; 
            font-weight: 900; 
            margin: 0 0 4px 0; 
            color: #fff; 
            letter-spacing: -1px; 
            /* Light effect for numbers */
            text-shadow: 0 0 5px rgba(255,255,255,0.2); 
        }
        .stat-content span { font-size: 15px; color: var(--text-muted); font-weight: 500; }
        
        /* Specific Icon Gradients (Background color defines the glow/accent) */
        .icon-grad-1 { color: #60a5fa; } /* Blue - Sales */
        .icon-grad-1::before { background-color: #60a5fa; }
        
        .icon-grad-2 { color: #c084fc; } /* Violet - Orders */
        .icon-grad-2::before { background-color: #c084fc; }

        .icon-grad-3 { color: #fb923c; } /* Orange - Total Users */
        .icon-grad-3::before { background-color: #fb923c; }

        .icon-grad-4 { color: #34d399; } /* Green - New Users */
        .icon-grad-4::before { background-color: #34d399; }

        /* --- Charts --- */
        .charts-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 30px; 
        }
        .chart-card {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Responsive Chart Layout */
        @media (max-width: 1200px) { 
            .charts-grid { grid-template-columns: 1fr 1fr; } 
            /* On tablet/small desktop, status and users can share space */
            .chart-card-full { grid-column: span 2; } /* Sales Chart takes full width */
            .charts-grid-col-2 > div { grid-column: span 1; }
        }
        @media (max-width: 768px) { 
            .charts-grid { grid-template-columns: 1fr; }
            .chart-card-full { grid-column: span 1 !important; }
            .wrapper { padding: 20px; gap: 20px; }
            .site-header { padding: 0 15px; }
            .header-nav a { padding: 6px 10px; font-size: 14px; }
            .hero-title h1 { font-size: 24px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { justify-content: center; }
            .filter-inputs { flex-direction: column; width: 100%; }
            /* Fixing input width on mobile */
            .input-glass, .filter-inputs > div:first-child, .filter-inputs > select { max-width: 100% !important; min-width: 100% !important; }
            .filter-inputs > div:last-child { width: 100%; justify-content: space-between; display: flex; }
        }
        
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .chart-title { font-size: 18px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }
        .chart-title i { color: var(--accent); }
        
        /* Chart specific styling */
        .chart-canvas-wrapper { position: relative; height: 350px; }

        /* --- Quick Actions --- */
        .section-header { 
            margin-top: 20px; font-size: 20px; font-weight: 800; 
            color: var(--text-main); 
            display: flex; align-items: center; gap: 15px; 
            margin-bottom: 25px; 
            padding-bottom: 5px;
            border-bottom: 2px solid var(--primary);
            width: fit-content;
        }
        .section-header i { font-size: 24px; }
        
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px; }
        .action-btn {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 25px 15px; gap: 15px;
            background: rgba(30, 41, 59, 0.5); /* Slightly darker card */
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            text-decoration: none !important; color: var(--text-main);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            font-weight: 600;
        }
        .action-btn i { font-size: 36px; transition: 0.3s; color: var(--accent); opacity: 0.9; }
        .action-btn:hover { 
            transform: translateY(-5px); 
            background: rgba(30, 41, 59, 0.8);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5), 0 0 15px var(--primary-glow);
        }
        .action-btn:hover i { transform: scale(1.1); color: var(--primary); opacity: 1; }
        .action-btn.danger i { color: var(--secondary); }

        /* --- Preferences Bar --- */
        #dashPrefs {
            border: 1px solid rgba(255,255,255,0.15);
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.1), rgba(18, 25, 40, 0.8));
            padding: 18px 30px;
            border-radius: 20px;
        }
        .custom-check {
            color: var(--text-main);
            font-weight: 500;
            transition: color 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .custom-check:hover { color: #fff; }
        .custom-check input[type="checkbox"] {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--primary);
            border-radius: 6px;
            margin-left: 10px;
            background: transparent;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        .custom-check input[type="checkbox"]:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .custom-check input[type="checkbox"]:checked::after {
            content: '\f00c';
            font-family: 'FontAwesome';
            font-size: 12px;
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .prefs-grid { display: flex; flex-wrap: wrap; gap: 25px; align-items: center; }

        /* --- Footer --- */
        #footer { 
            margin-top: 50px; padding: 25px; 
            color: var(--text-muted); text-align: center; 
            font-size: 13px; 
            border-top: 1px solid rgba(255, 255, 255, 0.05); 
            background: rgba(0,0,0,0.2);
        }

        /* Daterangepicker Custom Styling for Dark Mode */
        .daterangepicker { border-radius: 12px; border: 1px solid var(--glass-border) !important; font-family: var(--font-main) !important; font-size: 14px !important; }
        .daterangepicker .calendar-table, .daterangepicker .ranges { background: var(--bg-body) !important; color: var(--text-main) !important; }
        .daterangepicker td.active, .daterangepicker td.active:hover { background-color: var(--primary) !important; border-color: var(--primary) !important; color: white !important; }
        .daterangepicker td.in-range { background-color: rgba(79, 70, 229, 0.1) !important; color: white !important; }
        .daterangepicker .drp-buttons .btn { border-radius: 8px !important; font-weight: 500 !important; margin-left: 8px; }
        .daterangepicker .drp-buttons .btn-primary { background-color: var(--primary) !important; border-color: var(--primary) !important; }
        .daterangepicker .drp-buttons .btn-default { background-color: rgba(255,255,255,0.1) !important; border-color: rgba(255,255,255,0.2) !important; color: var(--text-main) !important; }
        .daterangepicker th, .daterangepicker td { color: var(--text-main) !important; }
        .daterangepicker .calendar-table td.available:hover { background: rgba(255,255,255,0.1) !important; }

    </style>
</head>

<body>

<section id="container">
    <!-- Header -->
    <header class="site-header">
        <h1 class="header-title">پنل مدیریت</h1>
        <nav class="header-nav">
            <a href="index.php" class="active"><i class="icon-dashboard"></i> داشبورد</a>
            <a href="invoice.php"><i class="icon-list-alt"></i> سفارشات</a>
            <a href="user.php"><i class="icon-group"></i> کاربران</a>
            <a href="logout.php"><i class="icon-off"></i> خروج</a>
        </nav>
    </header>

    <section id="main-content">
        <section class="wrapper">
            
            <!-- Hero Section (Greeting and Date) -->
            <div class="hero-banner animate-enter">
                <div class="hero-title">
                    <h1><?php echo $greeting; ?>، مدیر عزیز</h1>
                    <div class="hero-subtitle">
                        <i class="fa <?php echo $greetIcon; ?>"></i>
                        <span>امروز: <?php echo jdate('l، j F Y'); ?></span>
                        <span style="margin: 0 10px; opacity: 0.3;">|</span>
                        <span>وضعیت: <span style="color: #34d399;">سیستم پایدار است</span></span>
                    </div>
                </div>
                <!-- Time Range Presets -->
                <div class="time-presets-group">
                    <button class="btn-glass" id="preset7d">۷ روز</button>
                    <button class="btn-glass" id="presetMonth">ماه اخیر</button>
                    <button class="btn-glass" id="presetYear">سال اخیر</button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar animate-enter delay-1">
                <form class="filter-inputs" method="get" id="dashboardFilterForm">
                    <!-- Date Picker Input -->
                    <div style="position: relative; flex-grow: 1; max-width: 300px;">
                        <input type="text" id="rangePicker" class="input-glass" placeholder="انتخاب محدوده تاریخ..." style="padding-right: 40px; text-align: right; width: 100%;">
                        <i class="fa icon-calendar" style="position: absolute; right: 15px; top: 14px; color: var(--text-muted); pointer-events: none;"></i>
                    </div>
                    <!-- Hidden fields to store date range values for submission -->
                    <input type="hidden" name="from" id="rangeFrom" value="<?php echo htmlspecialchars($fromDate ?? '', ENT_QUOTES); ?>">
                    <input type="hidden" name="to" id="rangeTo" value="<?php echo htmlspecialchars($toDate ?? '', ENT_QUOTES); ?>">

                    <!-- Status Multi-Select -->
                    <select name="status[]" multiple class="input-glass" style="height: auto; min-height: 46px; flex-grow: 1; max-width: 300px;">
                        <!-- Populate status options from PHP data -->
                        <?php foreach($statusMapFa as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo in_array($sk, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-gradient">
                            <i class="fa icon-filter"></i> 
                            <span>اعمال فیلتر</span>
                        </button>
                        
                        <?php if($fromDate || $toDate || !empty($selectedStatuses)): ?>
                        <!-- Reset Filter Button -->
                        <a href="index.php" class="btn-glass" title="حذف فیلترها" style="display: flex; align-items: center; justify-content: center; padding: 12px 18px;">
                            <i class="fa icon-refresh"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid animate-enter delay-2">
                <!-- Card 1: Total Sales Amount -->
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-1"><i class="fa icon-line-chart"></i></div>
                    <div class="stat-content">
                        <!-- PHP Logic remains here -->
                        <h3><?php echo $formatted_total_sales; ?></h3>
                        <span>مجموع فروش (ریال)</span>
                    </div>
                </div>

                <!-- Card 2: Total Orders Count -->
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-2"><i class="fa icon-shopping-cart"></i></div>
                    <div class="stat-content">
                        <!-- PHP Logic remains here -->
                        <h3><?php echo number_format($resultcontsell); ?></h3>
                        <span>تعداد کل سفارشات (فیلتر شده)</span>
                    </div>
                </div>

                <!-- Card 3: Total Users Count -->
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-3"><i class="fa icon-users"></i></div>
                    <div class="stat-content">
                        <!-- PHP Logic remains here -->
                        <h3><?php echo number_format($resultcount); ?></h3>
                        <span>تعداد کل کاربران</span>
                    </div>
                </div>

                <!-- Card 4: New Users Today -->
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-4"><i class="fa icon-user-plus"></i></div>
                    <div class="stat-content">
                        <!-- PHP Logic remains here -->
                        <h3><?php echo number_format($resultcountday); ?></h3>
                        <span>کاربر جدید (امروز)</span>
                    </div>
                </div>
            </div>

            <!-- Chart Visibility Preferences (Using Vue.js for state management) -->
            <div class="modern-card animate-enter delay-3" id="dashPrefs">
                <div class="chart-header">
                    <div class="chart-title"><i class="fa icon-sliders"></i> تنظیمات نمایش نمودارها</div>
                </div>
                <div class="prefs-grid">
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.sales">
                        نمودار روند فروش
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.status">
                        نمودار توزیع وضعیت‌ها
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.users">
                        نمودار روند کاربران جدید
                    </label>
                </div>
            </div>

            <!-- Charts Grid (Managed by Vue.js/CSS) -->
            <div class="charts-grid charts-grid-col-2" id="chartsGrid">
                
                <!-- Chart 1: Sales Trend -->
                <div class="modern-card chart-card chart-card-full animate-enter delay-4" id="salesChartContainer" style="display: none;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa icon-area-chart"></i> روند فروش در بازه زمانی (مبالغ به ریال)</div>
                    </div>
                    <div class="chart-canvas-wrapper">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Status Distribution -->
                <div class="modern-card chart-card animate-enter delay-4" id="statusChartContainer" style="display: none;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa icon-pie-chart"></i> توزیع وضعیت سفارشات</div>
                    </div>
                    <div class="chart-canvas-wrapper" style="height: 300px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Chart 3: New Users Trend -->
                <div class="modern-card chart-card animate-enter delay-4" id="usersChartContainer" style="display: none;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa icon-user-plus"></i> روند ثبت‌نام کاربران جدید</div>
                    </div>
                    <div class="chart-canvas-wrapper">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
                
            </div>

            <!-- Quick Actions -->
            <h2 class="section-header animate-enter delay-4"><i class="fa icon-cogs"></i> اقدامات سریع</h2>
            <div class="actions-grid animate-enter delay-5">
                <a href="invoice.php?status[]=unpaid" class="action-btn">
                    <i class="fa icon-money"></i>
                    بررسی پرداخت‌های معلق
                </a>
                <a href="user.php?new=1" class="action-btn">
                    <i class="fa icon-user-plus"></i>
                    مشاهده کاربران جدید
                </a>
                <a href="invoice.php?status[]=end_of_time&status[]=end_of_volume" class="action-btn danger">
                    <i class="fa icon-warning"></i>
                    سرویس‌های در حال اتمام
                </a>
                <a href="invoice.php" class="action-btn">
                    <i class="fa icon-list"></i>
                    مشاهده کل سفارشات
                </a>
                <a href="setting.php" class="action-btn">
                    <i class="fa icon-gear"></i>
                    تنظیمات سیستم
                </a>
            </div>

        </section>
        <!-- /wrapper -->
    </section>
    <!-- /main-content -->

    <!-- Footer -->
    <footer id="footer">
        © <?php echo date('Y'); ?> پنل مدیریت حرفه‌ای. تمامی حقوق محفوظ است. | طراحی و توسعه با عشق 🚀
    </footer>
    
</section>

<!-- Required JavaScript Dependencies -->
<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/chart.min.js"></script> <!-- Chart.js for data visualization -->
<script src="https://cdn.jsdelivr.net/npm/vue@3.4.27/dist/vue.global.prod.js"></script> <!-- Vue.js for component and reactivity -->

<!-- Date Range Picker Dependencies -->
<script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
<script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>

<!-- Custom Script for Dashboard Logic and Chart Rendering -->
<script>
// PHP data injection for JavaScript
const salesChartData = {
    labels: <?php echo json_encode($salesLabels); ?>,
    data: <?php echo json_encode($salesAmount); ?>
};
const statusChartData = {
    labels: <?php echo json_encode($statusLabels); ?>,
    data: <?php echo json_encode($statusData); ?>,
    colors: <?php echo json_encode($statusColors); ?>
};
const usersChartData = {
    labels: <?php echo json_encode($userLabels); ?>,
    data: <?php echo json_encode($userCounts); ?>
};

// --- Chart Rendering Logic ---
const chartInstances = {};
const chartRenderers = {
    // 1. Sales Chart (Line/Area)
    sales: function(){
        const ctx = document.getElementById('salesChart');
        if(!ctx) return;
        
        // Destroy existing instance if any
        if(chartInstances['sales']) chartInstances['sales'].destroy();

        chartInstances['sales'] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesChartData.labels,
                datasets: [{
                    label: 'مجموع فروش (ریال)',
                    data: salesChartData.data,
                    backgroundColor: 'rgba(96, 165, 250, 0.2)', // icon-grad-1 color
                    borderColor: '#60a5fa',
                    borderWidth: 3,
                    fill: 'start',
                    tension: 0.4, // Smooth curve
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { rtl: true, bodyFont: { family: 'Vazirmatn' }, titleFont: { family: 'Vazirmatn' } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { 
                            color: '#94a3b8',
                            font: { family: 'Vazirmatn' },
                            callback: function(value, index, values) {
                                // Format numbers for better readability (e.g., 1,000,000)
                                return value.toLocaleString('fa-IR');
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { family: 'Vazirmatn' } }
                    }
                }
            }
        });
    },

    // 2. Status Chart (Doughnut)
    status: function(){
        const ctx = document.getElementById('statusChart');
        if(!ctx) return;
        
        if(chartInstances['status']) chartInstances['status'].destroy();

        chartInstances['status'] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statusChartData.labels,
                datasets: [{
                    data: statusChartData.data,
                    backgroundColor: statusChartData.colors,
                    borderColor: 'var(--bg-body)',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'right', 
                        labels: { 
                            color: '#fff',
                            font: { family: 'Vazirmatn', size: 13 },
                            boxWidth: 15
                        },
                        rtl: true
                    },
                    tooltip: { rtl: true, bodyFont: { family: 'Vazirmatn' }, titleFont: { family: 'Vazirmatn' } }
                }
            }
        });
    },

    // 3. New Users Chart (Bar)
    users: function(){
        const ctx = document.getElementById('usersChart');
        if(!ctx) return;
        
        if(chartInstances['users']) chartInstances['users'].destroy();

        chartInstances['users'] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: usersChartData.labels,
                datasets: [{
                    label: 'تعداد کاربران جدید',
                    data: usersChartData.data,
                    backgroundColor: '#34d399', // icon-grad-4 color
                    borderColor: '#34d399',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { rtl: true, bodyFont: { family: 'Vazirmatn' }, titleFont: { family: 'Vazirmatn' } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { 
                            color: '#94a3b8',
                            font: { family: 'Vazirmatn' },
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { family: 'Vazirmatn' } }
                    }
                }
            }
        });
    }
};

// --- Main Initialization Function ---
(function() {
    // Helper function for Persian date range formatting
    const formatDate = (date) => moment(date).format('YYYY/MM/DD');
    const today = moment().format('YYYY/MM/DD');
    
    // 1. Daterange Picker Initialization
    const rangePicker = $('#rangePicker');
    const rangeFrom = $('#rangeFrom');
    const rangeTo = $('#rangeTo');
    
    // Check if initial dates are set from PHP/GET parameters
    const initialStart = rangeFrom.val() ? moment(rangeFrom.val()) : null;
    const initialEnd = rangeTo.val() ? moment(rangeTo.val()) : null;

    if (initialStart && initialEnd) {
        rangePicker.val(initialStart.format('YYYY/MM/DD') + ' - ' + initialEnd.format('YYYY/MM/DD'));
    }

    rangePicker.daterangepicker({
        startDate: initialStart || moment().subtract(29, 'days'),
        endDate: initialEnd || moment(),
        opens: 'left',
        // Define Custom Ranges in Persian
        ranges: {
            'امروز': [moment(), moment()],
            'دیروز': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            '۷ روز اخیر': [moment().subtract(6, 'days'), moment()],
            '۳۰ روز اخیر': [moment().subtract(29, 'days'), moment()],
            'این ماه': [moment().startOf('month'), moment().endOf('month')],
            'ماه گذشته': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        },
        locale: {
            format: 'YYYY/MM/DD',
            separator: ' - ',
            applyLabel: 'اعمال',
            cancelLabel: 'لغو',
            fromLabel: 'از',
            toLabel: 'تا',
            customRangeLabel: 'بازه دلخواه',
            weekLabel: 'ه',
            daysOfWeek: ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'],
            monthNames: ['ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن', 'جولای', 'آگوست', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر'],
            firstDay: 6 // Saturday (or 0 for Sunday)
        }
    }, function(start, end, label) {
        rangeFrom.val(start.format('YYYY/MM/DD'));
        rangeTo.val(end.format('YYYY/MM/DD'));
    });

    // 2. Preset Buttons Logic (7 Days, Month, Year)
    $('#preset7d').on('click', function(e) {
        e.preventDefault();
        rangeFrom.val(formatDate(moment().subtract(6, 'days')));
        rangeTo.val(today);
        $('#dashboardFilterForm').submit();
    });

    $('#presetMonth').on('click', function(e) {
        e.preventDefault();
        rangeFrom.val(formatDate(moment().subtract(1, 'month')));
        rangeTo.val(today);
        $('#dashboardFilterForm').submit();
    });

    $('#presetYear').on('click', function(e) {
        e.preventDefault();
        rangeFrom.val(formatDate(moment().subtract(1, 'year')));
        rangeTo.val(today);
        $('#dashboardFilterForm').submit();
    });


    // 3. Chart Visibility/Layout Toggle (Vue.js Component)
    const toggleCharts = (showState) => {
        const grid = document.getElementById('chartsGrid');
        const containers = {
            sales: document.getElementById('salesChartContainer'),
            status: document.getElementById('statusChartContainer'),
            users: document.getElementById('usersChartContainer')
        };
        
        let visibleCount = 0;
        
        for (const key in containers) {
            if (containers[key]) {
                containers[key].style.display = showState[key] ? 'flex' : 'none';
                if (showState[key]) {
                    visibleCount++;
                    // Lazy render chart when it becomes visible
                    if (!chartInstances[key]) {
                        chartRenderers[key]();
                    } else {
                        // Ensure chart is redrawn on resize/visibility change
                        chartInstances[key].resize();
                    }
                }
            }
        }
        
        // Adjust grid layout based on visible charts
        if (grid) {
            if (visibleCount >= 3) {
                grid.style.gridTemplateColumns = 'repeat(3, 1fr)';
                if (containers.sales) containers.sales.classList.remove('chart-card-full');
            } else if (visibleCount === 2) {
                 grid.style.gridTemplateColumns = '1fr 1fr';
                 // If sales chart is visible among 2, give it priority for 2 columns on larger screens
                 if (showState.sales) {
                     grid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                     containers.sales.classList.add('chart-card-full');
                 } else {
                     grid.style.gridTemplateColumns = '1fr 1fr';
                     if (containers.sales) containers.sales.classList.remove('chart-card-full');
                 }
            } else if (visibleCount === 1) {
                grid.style.gridTemplateColumns = '1fr';
                if (containers.sales) containers.sales.classList.remove('chart-card-full');
            } else {
                grid.style.gridTemplateColumns = '1fr';
            }
        }
    };

    // Load preferences from localStorage or use defaults
    let initialPrefs = { sales: true, status: true, users: true };
    try {
        const storedPrefs = localStorage.getItem('dash_prefs');
        if (storedPrefs) {
            initialPrefs = JSON.parse(storedPrefs);
            // Ensure all keys exist in case of schema change
            if (typeof initialPrefs.sales === 'undefined') initialPrefs.sales = true;
            if (typeof initialPrefs.status === 'undefined') initialPrefs.status = true;
            if (typeof initialPrefs.users === 'undefined') initialPrefs.users = true;
        }
    } catch (e) {
        console.error("Error loading dashboard preferences:", e);
    }

    if (typeof Vue !== 'undefined') {
        const app = Vue.createApp({
            data() {
                return {
                    show: initialPrefs
                }
            },
            watch:{ 
                show:{ 
                    deep:true, 
                    handler:function(v){ 
                        localStorage.setItem('dash_prefs', JSON.stringify(v)); 
                        toggleCharts(v); 
                    } 
                } 
            },
            mounted(){ 
                // Initial application of visibility and layout
                toggleCharts(this.show); 
                // Since we render charts on first show, no separate lazyInit is strictly needed,
                // but we keep the concept for future use or heavy charts.
                window.addEventListener('resize', () => toggleCharts(this.show));
            }
        });
        app.mount('#dashPrefs');
    } else {
        // Fallback: If Vue.js is not loaded, just render all charts initially
        chartRenderers['sales']();
        chartRenderers['status']();
        chartRenderers['users']();
        // Since Vue is not running, we set initial display inline (already done in HTML for safety)
        const salesEl = document.getElementById('salesChartContainer');
        const statusEl = document.getElementById('statusChartContainer');
        const usersEl = document.getElementById('usersChartContainer');
        if (salesEl) salesEl.style.display = 'flex';
        if (statusEl) statusEl.style.display = 'flex';
        if (usersEl) usersEl.style.display = 'flex';
    }
})();
</script>

</body>
</html>