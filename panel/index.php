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
    die("Fatal Error: Database connection variable (\$pdo) is not defined or is not a PDO object. Please check 'config.php'.");
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
    die("Database Error during authentication check. Please check logs. Message: " . $e->getMessage());
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

    // New Users Today (Fix applied here in previous turn: using execute array instead of bindParam)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :time_register AND register != 'none'");
    $stmt->execute([':time_register' => $datefirstday]); 
    $resultcountday = $stmt->fetchColumn();

    // Sales Count (Filtered)
    $query = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'"); // Exclude unpaid from order count
    $query->execute($invoiceParams);
    $resultcontsell = $query->fetchColumn();
} catch (PDOException $e) {
    die("Database Error during data retrieval. Message: " . $e->getMessage());
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
        die("Database Error while fetching Sales Trend. Message: " . $e->getMessage());
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
    'disabledn' => 'غیرفعال', // Changed from 'ناموجود' to 'غیرفعال' for better context
    'end_of_time' => 'پایان زمان',
    'end_of_volume' => 'پایان حجم',
    'sendedwarn' => 'هشدار',
    'send_on_hold' => 'در انتظار اتصال',
    'removebyuser' => 'حذف شده'
];
$colorMap = [
    'unpaid' => '#fbbf24', // Amber
    'active' => '#10b981', // Emerald (Slightly darker for better contrast)
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
    die("Database Error while fetching Status Distribution. Message: " . $e->getMessage());
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
    die("Database Error while fetching New Users Trend. Message: " . $e->getMessage());
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
        .time-presets-group { background: rgba(255,255,255,0.05); padding: 5px; border-radius: 15px; display: flex; border: 1px solid rgba(255,255,255,0.05); }
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
        }
        .stat-icon-wrapper::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            opacity: 0.15; z-index: 1;
        }

        .stat-content h3 { font-size: 30px; font-weight: 900; margin: 0 0 4px 0; color: #fff; letter-spacing: -1px; }
        .stat-content span { font-size: 15px; color: var(--text-muted); font-weight: 500; }
        
        /* Specific Icon Gradients (Background color defines the glow/accent) */
        .icon-grad-1 { color: #60a5fa; } /* Blue */
        .icon-grad-1::before { background-color: #60a5fa; }
        
        .icon-grad-2 { color: #c084fc; } /* Violet */
        .icon-grad-2::before { background-color: #c084fc; }

        .icon-grad-3 { color: #fb923c; } /* Orange */
        .icon-grad-3::before { background-color: #fb923c; }

        .icon-grad-4 { color: #34d399; } /* Green */
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
        }
        @media (max-width: 768px) { 
            .charts-grid { grid-template-columns: 1fr; }
            .wrapper { padding: 20px; gap: 20px; }
            .site-header { padding: 0 15px; }
            .header-nav a { padding: 6px 10px; font-size: 14px; }
            .hero-title h1 { font-size: 24px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
        
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .chart-title { font-size: 18px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }
        .chart-title i { color: var(--accent); }
        
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
        }
        .custom-check input[type="checkbox"]:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .custom-check input[type="checkbox"]:checked::after {
            content: '\f00c';
            font-family: 'FontAwesome';
            font-size: 12px;
        }

        /* --- Footer --- */
        #footer { 
            margin-top: 50px; padding: 25px; 
            color: var(--text-muted); text-align: center; 
            font-size: 13px; 
            border-top: 1px solid rgba(255, 255, 255, 0.05); 
            background: rgba(0,0,0,0.2);
        }
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
                        <i class="<?php echo $greetIcon; ?>"></i>
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
                        <i class="icon-calendar" style="position: absolute; right: 15px; top: 14px; color: var(--text-muted); pointer-events: none;"></i>
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
                            <i class="icon-filter"></i> 
                            <span>اعمال فیلتر</span>
                        </button>
                        
                        <?php if($fromDate || $toDate || !empty($selectedStatuses)): ?>
                        <!-- Reset Filter Button -->
                        <a href="index.php" class="btn-glass" title="حذف فیلترها" style="display: flex; align-items: center; justify-content: center; padding: 12px 18px;">
                            <i class="icon-refresh"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid animate-enter delay-2">
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-1"><i class="icon-bar-chart"></i></div>
                    <div class="stat-content">
                        <h3><?php echo $formatted_total_sales; ?></h3>
                        <span>مجموع فروش (تومان)</span>
                    </div>
                </div>
                
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-2"><i class="icon-shopping-bag"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcontsell); ?></h3>
                        <span>تعداد سفارشات</span>
                    </div>
                </div>

                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-3"><i class="icon-users"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcount); ?></h3>
                        <span>کل کاربران سیستم</span>
                    </div>
                </div>

                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-4"><i class="icon-user-plus"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcountday); ?></h3>
                        <span>کاربران جدید امروز</span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Preferences -->
            <div class="modern-card animate-enter delay-2" id="dashPrefs" style="padding: 15px 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <span class="text-muted" style="font-size: 15px; font-weight: 500; color: #cbd5e1;"><i class="icon-cogs"></i> نمایش نمودارها:</span>
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <!-- Checkboxes bound to Vue 'show' state -->
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.sales"> 
                        روند فروش
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.status"> 
                        توزیع وضعیت‌ها
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.users"> 
                        جذب کاربر
                    </label>
                </div>
            </div>

            <!-- Charts Section (Dynamically controlled by Vue) -->
            <div class="charts-grid animate-enter delay-3" id="chartsArea">
                <!-- Sales Chart (Bar) -->
                <div class="chart-card modern-card" data-chart="sales" id="salesChartContainer" style="grid-column: 1 / -1; display: none;">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-bar-chart"></i> تحلیل فروش روزانه</span>
                    </div>
                    <div style="height: 350px; width: 100%;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Status Doughnut Chart -->
                <div class="chart-card modern-card" data-chart="status" id="statusChartContainer" style="grid-column: span 1; display: none;">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-pie-chart"></i> توزیع وضعیت سفارشات</span>
                    </div>
                    <div style="height: 300px; display: flex; justify-content: center; position: relative; min-width: 0;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Users Line Chart -->
                <div class="chart-card modern-card" data-chart="users" id="usersChartContainer" style="grid-column: span 2; display: none;">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-line-chart"></i> روند ثبت نام کاربران جدید</span>
                    </div>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="animate-enter delay-4">
                <div class="section-header">
                    <i class="icon-bolt" style="color: var(--accent);"></i> عملیات سریع
                </div>
                <div class="actions-grid">
                    <a href="invoice.php" class="action-btn">
                        <i class="icon-list-alt"></i>
                        <span>مشاهده سفارشات</span>
                    </a>
                    <a href="user.php" class="action-btn">
                        <i class="icon-users"></i>
                        <span>مدیریت کاربران</span>
                    </a>
                    <a href="product.php" class="action-btn">
                        <i class="icon-archive"></i>
                        <span>تعریف محصولات</span>
                    </a>
                    <a href="inbound.php" class="action-btn">
                        <i class="icon-exchange"></i>
                        <span>تعیین ورودی‌ها</span>
                    </a>
                    <a href="payment.php" class="action-btn">
                        <i class="icon-credit-card"></i>
                        <span>لیست پرداخت‌ها</span>
                    </a>
                    <a href="cancelService.php" class="action-btn danger">
                        <i class="icon-trash"></i>
                        <span>حذف سرویس</span>
                    </a>
                    <a href="keyboard.php" class="action-btn">
                        <i class="icon-th"></i>
                        <span>تنظیمات کیبورد</span>
                    </a>
                    <a href="productedit.php" class="action-btn">
                        <i class="icon-edit"></i>
                        <span>ویرایش سریع</span>
                    </a>
                </div>
            </div>

        </section>
        <footer id="footer">
            2024 &copy; پنل مدیریت حرفه‌ای. تمامی حقوق محفوظ است.
        </footer>
    </section>
</section>

<!-- Scripts -->
<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.scrollTo.min.js"></script>
<script src="js/jquery.nicescroll.js"></script>
<!-- Essential Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/vue@3"></script> 
<!-- Daterange picker dependencies -->
<script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
<script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>
<!-- The original common-scripts.js is expected to be present -->
<script src="js/common-scripts.js"></script>

<script>
$(function(){
    // Date Picker Logic
    var from = $('#rangeFrom').val();
    var to = $('#rangeTo').val();
    var $input = $('#rangePicker');
    
    // Set initial dates based on current filter or defaults (last 13 days + today)
    var start = from ? moment(from) : moment().subtract(13, 'days');
    var end = to ? moment(to) : moment();

    function cb(start, end) {
        // Update input field display (Gregorian format for submission clarity, but user sees Persian via jdf)
        // Since jdf is PHP-based, we keep moment formats for internal use
        $input.val(start.format('YYYY/MM/DD') + '  تا  ' + end.format('YYYY/MM/DD'));
        // Update hidden fields for submission
        $('#rangeFrom').val(start.format('YYYY-MM-DD'));
        $('#rangeTo').val(end.format('YYYY-MM-DD'));
    }

    $input.daterangepicker({
        startDate: start,
        endDate: end,
        opens: 'right', // Changed to right for better RTL compatibility
        locale: { format: 'YYYY/MM/DD', separator: ' - ', applyLabel: 'تایید', cancelLabel: 'لغو' }
    }, cb);

    // Initial display of dates if they were set
    if(from && to) { cb(start, end); } else { $input.val(''); }

    // Preset buttons functionality to automatically submit the form
    $('#preset7d').click(function(e){ 
        e.preventDefault(); 
        $('#rangeFrom').val(moment().subtract(6, 'days').format('YYYY-MM-DD')); 
        $('#rangeTo').val(moment().format('YYYY-MM-DD')); 
        $('#dashboardFilterForm').submit(); 
    });
    $('#presetMonth').click(function(e){ 
        e.preventDefault(); 
        $('#rangeFrom').val(moment().subtract(30, 'days').format('YYYY-MM-DD')); 
        $('#rangeTo').val(moment().format('YYYY-MM-DD')); 
        $('#dashboardFilterForm').submit(); 
    });
    $('#presetYear').click(function(e){ 
        e.preventDefault(); 
        $('#rangeFrom').val(moment().subtract(365, 'days').format('YYYY-MM-DD')); 
        $('#rangeTo').val(moment().format('YYYY-MM-DD')); 
        $('#dashboardFilterForm').submit(); 
    });
});
</script>

<script>
(function(){
    // Chart.js Global Config for Vazirmatn and Dark Theme
    Chart.defaults.font.family = 'Vazirmatn';
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.08)';

    // Data from PHP, safely encoded
    var salesLabels = <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>;
    var salesAmount = <?php echo json_encode($salesAmount); ?>;
    var statusLabels = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>;
    var statusData = <?php echo json_encode($statusData); ?>;
    var statusColors = <?php echo json_encode($statusColors); ?>;
    var userLabels = <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>;
    var userCounts = <?php echo json_encode($userCounts); ?>;

    const initializedCharts = new Set();
    const chartRenderers = {};
    const chartContainers = document.querySelectorAll('.chart-card');


    // 1. Render Sales Chart
    chartRenderers['sales'] = function() {
        if(initializedCharts.has('sales')) return;
        var ctx = document.getElementById('salesChart').getContext('2d');
        var grad = ctx.createLinearGradient(0, 0, 0, 300);
        grad.addColorStop(0, 'rgba(79, 70, 229, 0.5)'); // Primary Indigo
        grad.addColorStop(1, 'rgba(79, 70, 229, 0.05)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'فروش (تومان)',
                    data: salesAmount,
                    backgroundColor: grad,
                    borderColor: '#4f46e5',
                    borderWidth: 1,
                    borderRadius: 8,
                    hoverBackgroundColor: '#818cf8'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        backgroundColor: 'rgba(30, 41, 59, 0.9)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 14 },
                        callbacks: {
                            label: function(c) { 
                                return ' ' + Number(c.raw).toLocaleString('fa-IR') + ' تومان'; 
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        border: { display: false }, 
                        grid: { color: 'rgba(255,255,255,0.08)' },
                        ticks: {
                            color: '#cbd5e1',
                            callback: function(value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'م';
                                if (value >= 1000) return (value / 1000).toFixed(0) + 'هز';
                                return value;
                            }
                        }
                    },
                    x: { grid: { display: false }, ticks: { color: '#cbd5e1' } }
                }
            }
        });
        initializedCharts.add('sales');
    };

    // 2. Render Status Chart
    chartRenderers['status'] = function() {
        if(initializedCharts.has('status')) return;
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: statusColors,
                    borderWidth: 4,
                    borderColor: 'var(--bg-body)' // Border matches body background for floating effect
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { 
                        position: 'right', 
                        labels: { 
                            boxWidth: 12, 
                            padding: 15,
                            font: { size: 14 }
                        } 
                    },
                    tooltip: { rtl: true, backgroundColor: 'rgba(30, 41, 59, 0.9)' }
                }
            }
        });
        initializedCharts.add('status');
    };

    // 3. Render Users Chart
    chartRenderers['users'] = function() {
        if(initializedCharts.has('users')) return;
        var ctxU = document.getElementById('usersChart').getContext('2d');
        var gradU = ctxU.createLinearGradient(0, 0, 0, 300);
        gradU.addColorStop(0, 'rgba(6, 182, 212, 0.3)'); // Cyan
        gradU.addColorStop(1, 'rgba(6, 182, 212, 0)');

        new Chart(ctxU, {
            type: 'line',
            data: {
                labels: userLabels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userCounts,
                    borderColor: '#06b6d4',
                    backgroundColor: gradU,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#06b6d4',
                    pointBorderColor: '#1e293b',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: { rtl: true, backgroundColor: 'rgba(30, 41, 59, 0.9)' }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        border: { display: false }, 
                        padding: { top: 10, bottom: 0 }, 
                        grid: { color: 'rgba(255,255,255,0.08)' },
                        ticks: { precision: 0, color: '#cbd5e1' }
                    },
                    x: { 
                        grid: { display: true, color: 'rgba(255,255,255,0.08)' }, 
                        ticks: { maxRotation: 0, autoSkipPadding: 20, color: '#cbd5e1' } 
                    }
                }
            }
        });
        initializedCharts.add('users');
    };

    // --- Chart Visibility and Layout Logic (Vue integration) ---

    // Array of chart keys
    const chartKeys = ['sales', 'status', 'users'];

    /**
     * Toggles chart visibility and updates grid layout based on Vue state
     * @param {object} s - The 'show' state object from Vue: {sales: bool, status: bool, users: bool}
     */
    function toggleCharts(s){
        const visibleKeys = chartKeys.filter(key => s[key]);
        const activeCount = visibleKeys.length;
        const chartsArea = document.getElementById('chartsArea');
        
        // Hide/Show elements based on preference and render if needed
        chartKeys.forEach(key => {
            const el = document.getElementById(key + 'ChartContainer');
            if (el) {
                if (s[key]) {
                    el.style.display = 'flex';
                    if (chartRenderers[key]) chartRenderers[key](); // Render on first show
                } else {
                    el.style.display = 'none';
                }
                el.style.gridColumn = 'unset'; // Reset column span
            }
        });
        
        if (activeCount === 0) {
            chartsArea.style.display = 'none';
            return;
        } else {
            chartsArea.style.display = 'grid';
        }

        // --- Layout Adjustment for Desktop (1200px+) ---
        if (window.innerWidth > 1200) {
            const salesEl = document.getElementById('salesChartContainer');
            const statusEl = document.getElementById('statusChartContainer');
            const usersEl = document.getElementById('usersChartContainer');
            
            if (s.sales) {
                // Sales (Row 1) takes full width (span 3)
                salesEl.style.gridColumn = '1 / -1'; 
                
                // Row 2: Status and Users
                if (s.status && s.users) {
                    // Status 1/3, Users 2/3 (3 columns total)
                    statusEl.style.gridColumn = 'span 1';
                    usersEl.style.gridColumn = 'span 2';
                } else if (s.status) {
                    // Status takes full width (span 3)
                    statusEl.style.gridColumn = '1 / -1'; 
                } else if (s.users) {
                    // Users takes full width (span 3)
                    usersEl.style.gridColumn = '1 / -1'; 
                }
            } else if (activeCount > 0) {
                // Sales is hidden. Status and Users share the 3 columns.
                if (s.status && s.users) {
                    // Status 1/3, Users 2/3
                    statusEl.style.gridColumn = 'span 1';
                    usersEl.style.gridColumn = 'span 2';
                } else if (s.status) {
                    statusEl.style.gridColumn = '1 / -1';
                } else if (s.users) {
                    usersEl.style.gridColumn = '1 / -1';
                }
            }
        }
        // --- Layout Adjustment for Tablet (768px-1200px) ---
        else if (window.innerWidth > 768 && window.innerWidth <= 1200) {
            // Charts share 2 columns equally.
            visibleKeys.forEach(key => {
                const el = document.getElementById(key + 'ChartContainer');
                if (el) el.style.gridColumn = 'span 1';
            });
            // If only one chart is visible, make it full width
            if (activeCount === 1) {
                document.getElementById(visibleKeys[0] + 'ChartContainer').style.gridColumn = '1 / -1';
            }
        }
    }


    // Vue App for Preferences
    if(window.Vue) {
        var app = Vue.createApp({
            data(){ 
                const defaultPrefs = {'sales':true, 'status':true, 'users':true};
                let storedPrefs;
                try {
                    storedPrefs = JSON.parse(localStorage.getItem('dash_prefs'));
                } catch (e) {
                    storedPrefs = null;
                }
                return { 
                    // Load from localStorage or use default. Ensure all keys exist.
                    show: {...defaultPrefs, ...storedPrefs} 
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