<?php
// --- خطایابی و گزارش‌دهی PHP (برای پیدا کردن علت خطای 500) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// فرض می‌کنیم این فایل‌ها در دسترس هستند و حاوی توابع مورد نیازند
// مطمئن شوید که مسیرهای زیر ('../config.php' و '../jdf.php') درست هستند.
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
    // از آنجایی که $_SESSION["user"] در اینجا استفاده شده، فرض می‌کنیم قبل از این خط باید احراز هویت شده باشد.
    if( !isset($_SESSION["user"]) ){
        header('Location: login.php');
        exit;
    }
    
    $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    // $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR); // این درست است زیرا $_SESSION["user"] یک متغیر است.
    // اما برای سادگی و اجتناب از خطاهای BindParam/BindValue، از اجرای مستقیم با آرایه‌ی پارامتر استفاده می‌کنیم (روش امن‌تر و توصیه‌شده‌تر):
    $query->execute(['username' => $_SESSION["user"]]); 
    $result = $query->fetch(PDO::FETCH_ASSOC);
    
    if(!$result ){
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    // در صورت وجود مشکل در کوئری احراز هویت (نه اتصال)
    error_log("Auth failed: " . $e->getMessage());
    // نمایش یک پیغام خطای دوستانه
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
    $ph = [];
    foreach($selectedStatuses as $i => $st){
        // Ensure that the status value is safe for query execution (PDO binding handles this)
        $k = ":st$i";
        $ph[] = $k;
        $invoiceParams[$k] = $st;
    }
    // Using IN clause for status filtering (needs special handling for PDO execute)
    // NOTE: This approach requires named parameters. For IN clause, the best practice is dynamic generation:
    $inQuery = implode(',', array_keys($invoiceParams));
    // Since we are using execute($invoiceParams) later, we must ensure $invoiceWhere does not contain array keys unless all parameters are bound dynamically.
    
    // For simplicity with IN clause, we stick to the dynamic placeholder method:
    $placeholders = [];
    foreach ($selectedStatuses as $i => $status) {
        $placeholder = ":status_$i";
        $placeholders[] = $placeholder;
        $invoiceParams[$placeholder] = $status;
    }
    $invoiceWhere[] = "status IN (" . implode(', ', $placeholders) . ")";
}else{
    // Default statuses to include most relevant orders if no filter is applied
    // This default list should usually be handled outside the prepared statement or bound if dynamic. 
    // Here we use simple SQL injection for the constants, which is generally acceptable for fixed internal values.
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
    // --- تغییر در خط مورد نظر: استفاده از execute با آرایه پارامتر بجای bindParam/bindValue ---
    // این روش امن‌تر و ساده‌تر است و از خطای bindParam جلوگیری می‌کند
    $stmt->execute([':time_register' => $datefirstday]); 
    // خطای 204 در اینجا رخ داده بود اگر از bindParam استفاده می‌کردید:
    // $stmt->bindParam(':time_register', $datefirstday); // $datefirstday متغیر است، اما شاید در کپی قبلی شما مشکل دیگری وجود داشته
    // $stmt->bindValue(':time_register', $datefirstday); // اگر از bindValue استفاده می‌شد، مشکل حل می‌شد.
    // اما بهترین روش: execute با آرایه
    $resultcountday = $stmt->fetchColumn();

    // Sales Count (Filtered)
    $query = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'"); // Exclude unpaid from order count
    $query->execute($invoiceParams);
    $resultcontsell = $query->fetchColumn();
} catch (PDOException $e) {
    // در صورت وجود مشکل در کوئری‌های آماری
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
            // Ensure time_sell is numeric (timestamp) before using it in date()
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
    // از آنجایی که $d به فرمت Y/m/d (میلادی) است، باید به timestamp تبدیل شود.
    // در صورتی که دیتابیس شما تاریخ شمسی ذخیره می‌کند، این قسمت باید تغییر کند. 
    return jdate('Y/m/d', strtotime($d)); 
}, array_keys($grouped_data)));
$salesAmount = array_values(array_map(function($i){ return $i['total_amount']; }, $grouped_data));
$salesCount = array_values(array_map(function($i){ return $i['order_count']; }, $grouped_data));


// 5. Chart Data: Status Distribution (All statuses in filtered period)
$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت',
    'active' => 'فعال',
    'disabledn' => 'ناموجود',
    'end_of_time' => 'پایان زمان',
    'end_of_volume' => 'پایان حجم',
    'sendedwarn' => 'هشدار',
    'send_on_hold' => 'در انتظار اتصال',
    'removebyuser' => 'حذف توسط کاربر'
];
$colorMap = [
    'unpaid' => '#fbbf24', // Amber
    'active' => '#34d399', // Emerald
    'disabledn' => '#9ca3af', // Gray
    'end_of_time' => '#f87171', // Red
    'end_of_volume' => '#60a5fa', // Blue
    'sendedwarn' => '#a78bfa', // Violet
    'send_on_hold' => '#fb923c', // Orange
    'removebyuser' => '#cbd5e1' // Light Gray
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
    $statusColors[] = isset($colorMap[$k]) ? $colorMap[$k] : '#999999';
}

// 6. Chart Data: New Users Trend (Last 14 days or filtered period)
// Determine time range for user registration chart
$userStart = ($fromDate && strtotime($fromDate)) ? strtotime(date('Y/m/d', strtotime($fromDate))) : (strtotime(date('Y/m/d')) - (13 * 86400)); // 14 days back including today
$userEnd = ($toDate && strtotime($toDate)) ? strtotime(date('Y/m/d', strtotime($toDate))) : strtotime(date('Y/m/d'));
$daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);

try {
    $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :ustart AND register <= :uend");
    // --- اجرای کوئری با آرایه پارامترها برای جلوگیری از خطای bindParam ---
    $stmt->execute([
        ':ustart' => $userStart,
        ':uend' => $userEnd + 86400 - 1 // End of the 'to' day
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
        /* CSS variables for a dark, glassmorphism theme */
        :root {
            --bg-body: #0f172a; /* Slate 900 */
            --glass-bg: rgba(30, 41, 59, 0.65);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-highlight: rgba(255, 255, 255, 0.03);
            
            --primary: #6366f1; /* Indigo */
            --primary-light: #818cf8;
            --secondary: #ec4899; /* Pink */
            --accent: #06b6d4; /* Cyan */
            
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            
            --font-main: 'Vazirmatn', sans-serif;
            --header-height: 60px;
        }

        body {
            background-color: var(--bg-body);
            /* Background gradients for visual depth */
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(236, 72, 153, 0.15) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: var(--font-main);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Global Layout --- */
        #container { width: 100%; height: 100%; }
        #main-content { margin-right: 0px; padding-top: var(--header-height); transition: all 0.3s; }
        .wrapper { padding: 25px; display: flex; flex-direction: column; gap: 24px; max-width: 1600px; margin: 0 auto; }
        .site-header {
            position: fixed; top: 0; right: 0; left: 0; height: var(--header-height); z-index: 100;
            background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; padding: 0 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        .header-title { color: #fff; font-size: 18px; font-weight: 700; padding-right: 15px; border-right: 2px solid var(--primary); }
        .header-nav { margin-right: auto; display: flex; gap: 15px; }
        .header-nav a { color: var(--text-muted); text-decoration: none; padding: 5px 10px; border-radius: 8px; transition: 0.2s; }
        .header-nav a:hover, .header-nav a.active { color: var(--text-main); background: rgba(255, 255, 255, 0.05); }
        
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
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .modern-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.35); border-color: rgba(255,255,255,0.2); }

        /* --- Hero Section --- */
        .hero-banner { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; }
        .hero-title h1 { font-size: 28px; font-weight: 800; background: linear-gradient(to right, #fff, #cbd5e1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 8px; }
        .hero-subtitle { font-size: 15px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }

        /* --- Filter Bar & Inputs --- */
        .filter-bar {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 12px 20px;
            display: flex; flex-wrap: wrap; align-items: center; gap: 15px;
            justify-content: space-between;
        }
        .filter-inputs { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; flex: 1; }
        
        .input-glass {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid #334155;
            color: #fff;
            border-radius: 10px;
            padding: 10px 14px;
            font-family: var(--font-main);
            outline: none; transition: 0.2s;
            min-width: 180px;
            appearance: none;
            cursor: pointer;
        }
        .input-glass:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25); }

        .btn-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border: none;
            padding: 10px 24px; border-radius: 10px;
            font-weight: 700; cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none !important;
        }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6); filter: brightness(1.1); }
        
        .btn-glass {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--text-muted);
            padding: 8px 16px; border-radius: 10px;
            transition: 0.2s; cursor: pointer;
        }
        .btn-glass:hover { background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.2); }

        /* --- Stats --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
        .stat-card { display: flex; align-items: center; gap: 20px; }
        .stat-icon-wrapper { width: 64px; height: 64px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 26px; box-shadow: inset 0 0 12px rgba(255,255,255,0.1); }
        .stat-content h3 { font-size: 26px; font-weight: 800; margin: 0 0 4px 0; color: #fff; letter-spacing: -0.5px; }
        .stat-content span { font-size: 14px; color: var(--text-muted); font-weight: 500; }
        
        .icon-grad-1 { background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05)); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2); }
        .icon-grad-2 { background: linear-gradient(135deg, rgba(168,85,247,0.2), rgba(168,85,247,0.05)); color: #c084fc; border: 1px solid rgba(168,85,247,0.2); }
        .icon-grad-3 { background: linear-gradient(135deg, rgba(249,115,22,0.2), rgba(249,115,22,0.05)); color: #fb923c; border: 1px solid rgba(249,115,22,0.2); }
        .icon-grad-4 { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05)); color: #34d399; border: 1px solid rgba(16,185,129,0.2); }

        /* --- Charts --- */
        .charts-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 24px; 
            /* Layout is adjusted dynamically by JS/Vue based on visibility */
        }
        .chart-card {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Responsive Layout Adjustments */
        @media (max-width: 1024px) { 
            .charts-grid { grid-template-columns: 1fr; } 
            #salesChartContainer, #statusChartContainer, #usersChartContainer { grid-column: 1 / -1 !important; }
        }
        
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .chart-title { font-size: 16px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
        
        /* --- Quick Actions --- */
        .section-header { margin-top: 10px; font-size: 18px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .section-header::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to left, rgba(255,255,255,0.08), transparent); }
        
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 18px; }
        .action-btn {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 30px 20px; gap: 15px;
            background: linear-gradient(145deg, rgba(30,41,59,0.6), rgba(15,23,42,0.6));
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            text-decoration: none !important; color: var(--text-muted);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
        }
        .action-btn i { font-size: 32px; transition: 0.3s; color: var(--text-muted); opacity: 0.8; }
        .action-btn:hover { transform: translateY(-5px); }
        .action-btn:hover i { transform: scale(1.1); color: var(--accent); opacity: 1; }
        .action-btn.danger:hover i { color: var(--secondary); }
        .action-btn.danger i { color: #f87171; }

        /* Custom Checkbox Style for Preferences (Vue component) */
        .custom-check {
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 14px;
        }
        .custom-check input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--primary);
            border-radius: 4px;
            margin-left: 8px;
            position: relative;
            transition: all 0.2s;
            cursor: pointer;
            background: rgba(255,255,255,0.05);
        }
        .custom-check input[type="checkbox"]:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .custom-check input[type="checkbox"]:checked::after {
            content: '\f00c'; /* Font Awesome check icon */
            font-family: 'FontAwesome';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 10px;
        }

        /* --- Footer --- */
        #footer { margin-top: 50px; padding: 20px 25px; color: var(--text-muted); text-align: center; font-size: 12px; border-top: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>

<body>

<section id="container">
    <!-- Manual Header Inclusion -->
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
                    <h1><?php echo $greeting; ?>، مدیر عزیز 👋</h1>
                    <div class="hero-subtitle">
                        <i class="<?php echo $greetIcon; ?>"></i>
                        <span>امروز: <?php echo jdate('l، j F Y'); ?></span>
                        <span style="margin: 0 8px; opacity: 0.3;">|</span>
                        <span>وضعیت سیستم پایدار است</span>
                    </div>
                </div>
                <!-- Time Range Presets -->
                <div class="btn-group" style="background: rgba(0,0,0,0.2); padding: 4px; border-radius: 12px; display: flex;">
                    <button class="btn-glass" id="preset7d">۷ روز</button>
                    <button class="btn-glass" id="presetMonth">ماه</button>
                    <button class="btn-glass" id="presetYear">سال</button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar animate-enter delay-1">
                <form class="filter-inputs" method="get" id="dashboardFilterForm">
                    <!-- Date Picker Input -->
                    <div style="position: relative;">
                        <input type="text" id="rangePicker" class="input-glass" placeholder="انتخاب تاریخ..." style="padding-right: 35px; text-align: right;">
                        <i class="icon-calendar" style="position: absolute; right: 12px; top: 12px; color: var(--text-muted); pointer-events: none;"></i>
                    </div>
                    <!-- Hidden fields to store date range values for submission -->
                    <input type="hidden" name="from" id="rangeFrom" value="<?php echo htmlspecialchars($fromDate ?? '', ENT_QUOTES); ?>">
                    <input type="hidden" name="to" id="rangeTo" value="<?php echo htmlspecialchars($toDate ?? '', ENT_QUOTES); ?>">

                    <!-- Status Multi-Select -->
                    <select name="status[]" multiple class="input-glass" style="height: auto; min-height: 42px;">
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
                        <a href="index.php" class="btn-glass" title="حذف فیلترها" style="display: flex; align-items: center; justify-content: center; padding: 10px 14px;">
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
                    <div class="stat-icon-wrapper icon-grad-2"><i class="icon-shopping-cart"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcontsell); ?></h3>
                        <span>تعداد سفارشات</span>
                    </div>
                </div>

                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-3"><i class="icon-group"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcount); ?></h3>
                        <span>کل کاربران</span>
                    </div>
                </div>

                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-4"><i class="icon-user"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcountday); ?></h3>
                        <span>کاربران جدید امروز</span>
                    </div>
                </div>
            </div>

            <!-- Charts Section (Lazy-loaded and controlled by Vue) -->
            <div class="charts-grid animate-enter delay-3" id="chartsArea">
                <!-- Sales Chart (Bar) -->
                <div class="chart-card modern-card" data-chart="sales" id="salesChartContainer">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-line-chart"></i> روند فروش روزانه</span>
                    </div>
                    <div style="height: 320px; width: 100%;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Status Doughnut Chart -->
                <div class="chart-card modern-card" data-chart="status" id="statusChartContainer">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-pie-chart"></i> وضعیت سفارشات</span>
                    </div>
                    <!-- min-width: 0 is important for Chart.js in flex containers -->
                    <div style="height: 260px; display: flex; justify-content: center; position: relative; min-width: 0;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Users Line Chart -->
                <div class="chart-card modern-card" data-chart="users" id="usersChartContainer">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-user-md"></i> نرخ جذب کاربر</span>
                    </div>
                    <div style="height: 260px; width: 100%;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="animate-enter delay-4">
                <div class="section-header">
                    <i class="icon-bolt" style="color: var(--accent);"></i> دسترسی سریع
                </div>
                <div class="actions-grid">
                    <a href="invoice.php" class="action-btn">
                        <i class="icon-list-alt"></i>
                        <span>سفارشات</span>
                    </a>
                    <a href="user.php" class="action-btn">
                        <i class="icon-user"></i>
                        <span>مدیریت کاربران</span>
                    </a>
                    <a href="product.php" class="action-btn">
                        <i class="icon-archive"></i>
                        <span>محصولات</span>
                    </a>
                    <a href="inbound.php" class="action-btn">
                        <i class="icon-exchange"></i>
                        <span>ورودی‌ها</span>
                    </a>
                    <a href="payment.php" class="action-btn">
                        <i class="icon-credit-card"></i>
                        <span>پرداخت‌ها</span>
                    </a>
                    <a href="cancelService.php" class="action-btn danger">
                        <i class="icon-trash"></i>
                        <span>حذف سرویس</span>
                    </a>
                    <a href="keyboard.php" class="action-btn">
                        <i class="icon-th"></i>
                        <span>کیبورد</span>
                    </a>
                    <a href="productedit.php" class="action-btn">
                        <i class="icon-edit"></i>
                        <span>ویرایش سریع</span>
                    </a>
                </div>
            </div>

            <!-- Dashboard Preferences (Vue component container) -->
            <div class="modern-card animate-enter delay-4" id="dashPrefs" style="margin-top: 10px; padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <span class="text-muted" style="font-size: 13px;"><i class="icon-cogs"></i> شخصی‌سازی داشبورد:</span>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Checkboxes bound to Vue 'show' state -->
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.sales"> 
                        نمودار فروش
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.status"> 
                        وضعیت‌ها
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.users"> 
                        کاربران جدید
                    </label>
                </div>
            </div>

        </section>
        <footer id="footer">
            2024 &copy; پنل مدیریت
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
    
    // Set initial dates based on current filter or defaults (last 14 days)
    var start = from ? moment(from) : moment().subtract(13, 'days');
    var end = to ? moment(to) : moment();

    function cb(start, end) {
        // Update input field display (Persian format)
        $input.val(start.format('YYYY-MM-DD') + '  تا  ' + end.format('YYYY-MM-DD'));
        // Update hidden fields for submission
        $('#rangeFrom').val(start.format('YYYY-MM-DD'));
        $('#rangeTo').val(end.format('YYYY-MM-DD'));
    }

    $input.daterangepicker({
        startDate: start,
        endDate: end,
        opens: 'left',
        // Note: The moment.js date formats used here are Gregorian, which is required
        // by the PHP processing logic (strtotime). The labels are what the user sees.
        locale: { format: 'YYYY-MM-DD', separator: ' - ', applyLabel: 'تایید', cancelLabel: 'لغو' }
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
        $('#rangeFrom').val(moment().startOf('month').format('YYYY-MM-DD')); 
        $('#rangeTo').val(moment().format('YYYY-MM-DD')); 
        $('#dashboardFilterForm').submit(); 
    });
    $('#presetYear').click(function(e){ 
        e.preventDefault(); 
        $('#rangeFrom').val(moment().startOf('year').format('YYYY-MM-DD')); 
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
        grad.addColorStop(0, 'rgba(99, 102, 241, 0.5)'); // Indigo
        grad.addColorStop(1, 'rgba(99, 102, 241, 0.05)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'فروش (تومان)',
                    data: salesAmount,
                    backgroundColor: grad,
                    borderColor: '#818cf8',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#a5b4fc'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true, // Enable RTL for Persian text
                        callbacks: {
                            label: function(c) { 
                                // Format number with commas
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
                            callback: function(value) {
                                // Simple formatting for large numbers on the axis
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'م';
                                if (value >= 1000) return (value / 1000).toFixed(0) + 'هز';
                                return value;
                            }
                        }
                    },
                    x: { grid: { display: false } }
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
                    borderWidth: 2,
                    borderColor: 'rgba(30, 41, 59, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, padding: 15 } }
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
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        border: { display: false }, 
                        padding: { top: 10, bottom: 0 }, 
                        grid: { color: 'rgba(255,255,255,0.08)' },
                        ticks: { precision: 0 } // Ensure whole numbers for counts
                    },
                    x: { 
                        grid: { display: true, color: 'rgba(255,255,255,0.08)' }, 
                        ticks: { maxRotation: 0, autoSkipPadding: 20 } 
                    }
                }
            }
        });
        initializedCharts.add('users');
    };

    // Lazy Loading Logic with IntersectionObserver
    function lazyInitCharts(){
        if(!('IntersectionObserver' in window)) {
            // Fallback: If not supported, render all charts immediately
            chartRenderers['sales']();
            chartRenderers['status']();
            chartRenderers['users']();
            return;
        }

        var io = new IntersectionObserver(function(entries, observer){ 
            entries.forEach(function(entry){ 
                if(entry.isIntersecting){
                    const chartKey = entry.target.getAttribute('data-chart');
                    if (chartRenderers[chartKey] && !initializedCharts.has(chartKey)) {
                        chartRenderers[chartKey]();
                        observer.unobserve(entry.target);
                    }
                } 
            }); 
        }, { threshold: 0.1 });

        // Only observe visible containers initially
        chartContainers.forEach(function(el){ 
            if(el.style.display !== 'none'){
                io.observe(el); 
            }
        });
    }

    /**
     * Toggles chart visibility and updates grid layout based on Vue state
     * @param {object} s - The 'show' state object from Vue: {sales: bool, status: bool, users: bool}
     */
    function toggleCharts(s){
        const salesEl = document.getElementById('salesChartContainer');
        const statusEl = document.getElementById('statusChartContainer');
        const usersEl = document.getElementById('usersChartContainer');

        // Render charts that are now visible if they haven't been rendered yet
        if(s.sales && !initializedCharts.has('sales')) chartRenderers['sales']();
        if(s.status && !initializedCharts.has('status')) chartRenderers['status']();
        if(s.users && !initializedCharts.has('users')) chartRenderers['users']();
        
        // Hide/Show elements based on preference
        if(salesEl) salesEl.style.display = s.sales ? 'flex' : 'none';
        if(statusEl) statusEl.style.display = s.status ? 'flex' : 'none';
        if(usersEl) usersEl.style.display = s.users ? 'flex' : 'none';

        // --- Grid Layout Adjustment for Desktop (1024px+) ---
        if (window.innerWidth > 1024) {
            
            // Default reset
            salesEl.style.gridColumn = 'unset';
            statusEl.style.gridColumn = 'unset';
            usersEl.style.gridColumn = 'unset';

            if (s.sales) {
                salesEl.style.gridColumn = '1 / -1'; // Sales takes full width (row 1)
                
                // Row 2: Status and Users
                if (s.status && s.users) {
                     statusEl.style.gridColumn = 'span 1';
                     usersEl.style.gridColumn = 'span 2'; // Users takes 2/3 for visual balance
                } else if (!s.status && s.users) {
                    usersEl.style.gridColumn = '1 / -1'; 
                } else if (s.status && !s.users) {
                    statusEl.style.gridColumn = '1 / -1';
                }
                
            } else {
                // Sales is hidden. All remaining charts share 3 columns in one row.
                const activeCount = (s.status ? 1 : 0) + (s.users ? 1 : 0);
                if (activeCount === 2) {
                    // Status 1fr, Users 2fr
                    statusEl.style.gridColumn = 'span 1';
                    usersEl.style.gridColumn = 'span 2';
                } else if (activeCount === 1) {
                    // One chart remaining, make it full width
                    if (s.status) statusEl.style.gridColumn = '1 / -1';
                    if (s.users) usersEl.style.gridColumn = '1 / -1';
                }
            }
        }
    }


    // Vue App for Preferences
    if(window.Vue) {
        var app = Vue.createApp({
            data(){ 
                // Load from localStorage or use default
                const defaultPrefs = {'sales':true, 'status':true, 'users':true};
                let storedPrefs;
                try {
                    // NOTE: Changed key from 'dash_show' to 'dash_prefs' for clarity
                    storedPrefs = JSON.parse(localStorage.getItem('dash_prefs'));
                } catch (e) {
                    storedPrefs = null;
                }
                return { 
                    show: storedPrefs || defaultPrefs
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
                toggleCharts(this.show); // Apply initial visibility
                lazyInitCharts(); // Start chart rendering process
            }
        });
        app.mount('#dashPrefs');
    } else {
        // Fallback if Vue.js is not loaded, still attempt to initialize charts
        lazyInitCharts();
    }
})();
</script>

</body>
</html>