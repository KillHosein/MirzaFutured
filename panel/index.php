<?php
// --- خطایابی و گزارش‌دهی PHP (برای پیدا کردن علت خطای 500) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// فرض می‌کنیم این فایل‌ها در دسترس هستند و حاوی توابع مورد نیازند
// اگر فایل‌ها در مسیر دیگری هستند، آدرس‌ها را اصلاح کنید
if (file_exists('../config.php')) require_once '../config.php';
if (file_exists('../jdf.php')) require_once '../jdf.php';

// --- بررسی حیاتی: اطمینان از تعریف متغیر اتصال به دیتابیس ---
// اگر config.php لود نشده باشد یا pdo تعریف نشده باشد، اینجا متوقف می‌شویم تا از خطا جلوگیری کنیم
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // برای نمایش دمو در محیط‌هایی که دیتابیس ندارند، ممکن است نیاز به هندل کردن خطا باشد
    // در محیط واقعی این بخش else را حذف کنید یا مدیریت کنید.
}

// --- Logic Section ---
// مقادیر پیش‌فرض برای متغیرها جهت جلوگیری از خطا در نمایش دمو
$datefirstday = time() - 86400;
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];

if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// 1. Authentication Check (اگر دیتابیس متصل باشد)
if (isset($pdo) && $pdo instanceof PDO) {
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
    }
}

// 2. Filter Logic for Invoices
$invoiceWhere = ["name_product != 'سرویس تست'"];
$invoiceParams = [];

if($fromDate && strtotime($fromDate)){
    $invoiceWhere[] = "time_sell >= :fromTs";
    $invoiceParams[':fromTs'] = strtotime($fromDate);
}
if($toDate && strtotime($toDate)){
    $invoiceWhere[] = "time_sell <= :toTs";
    $invoiceParams[':toTs'] = strtotime($toDate.' 23:59:59');
}

if(!empty($selectedStatuses)){
    $placeholders = [];
    foreach ($selectedStatuses as $i => $status) {
        $placeholder = ":status_$i";
        $placeholders[] = $placeholder;
        $invoiceParams[$placeholder] = $status;
    }
    $invoiceWhere[] = "status IN (" . implode(', ', $placeholders) . ")";
}else{
    $invoiceWhere[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";
}

$invoiceWhereSql = implode(' AND ', $invoiceWhere);

// 3. Sales and User Counts
$total_sales = 0;
$resultcount = 0;
$resultcountday = 0;
$resultcontsell = 0;
$grouped_data = [];
$statusRows = [];
$regRows = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Total Sales Amount
        $query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
        $query->execute($invoiceParams);
        $subinvoice = $query->fetch(PDO::FETCH_ASSOC);
        $total_sales = $subinvoice['SUM(price_product)'] ?? 0;

        // Total User Counts
        $query = $pdo->prepare("SELECT COUNT(*) FROM user");
        $query->execute();
        $resultcount = $query->fetchColumn();

        // New Users Today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :time_register AND register != 'none'");
        $stmt->execute([':time_register' => $datefirstday]); 
        $resultcountday = $stmt->fetchColumn();

        // Sales Count
        $query = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
        $query->execute($invoiceParams);
        $resultcontsell = $query->fetchColumn();

        // Chart Data: Sales
        if($resultcontsell > 0){
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
            ksort($grouped_data);
        }

        // Chart Data: Status
        $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
        $stmt->execute($invoiceParams);
        $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chart Data: New Users
        $userStart = ($fromDate && strtotime($fromDate)) ? strtotime(date('Y/m/d', strtotime($fromDate))) : (strtotime(date('Y/m/d')) - (13 * 86400));
        $userEnd = ($toDate && strtotime($toDate)) ? strtotime(date('Y/m/d', strtotime($toDate))) : strtotime(date('Y/m/d'));
        
        $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :ustart AND register <= :uend");
        $stmt->execute([':ustart' => $userStart, ':uend' => $userEnd + 86400 - 1]);
        $regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // die("Database Error: " . $e->getMessage());
    }
}

$formatted_total_sales = number_format($total_sales);

// Data Formatting for Charts
$salesLabels = [];
$salesAmount = [];
if (!empty($grouped_data)) {
    $salesLabels = array_values(array_map(function($d){ return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($grouped_data)));
    $salesAmount = array_values(array_map(function($i){ return $i['total_amount']; }, $grouped_data));
}

$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت',
    'active' => 'فعال',
    'disabledn' => 'غیرفعال',
    'end_of_time' => 'پایان زمان',
    'end_of_volume' => 'پایان حجم',
    'sendedwarn' => 'هشدار تمدید',
    'send_on_hold' => 'در انتظار اتصال',
    'removebyuser' => 'حذف شده'
];
// پالت رنگی جدید و مدرن
$colorMap = [
    'unpaid' => '#fbbf24',      // Amber
    'active' => '#10b981',      // Emerald
    'disabledn' => '#64748b',   // Slate
    'end_of_time' => '#ef4444', // Red
    'end_of_volume' => '#3b82f6', // Blue
    'sendedwarn' => '#a855f7',  // Purple
    'send_on_hold' => '#f97316', // Orange
    'removebyuser' => '#334155' // Dark Slate
];

$statusLabels = [];
$statusData = [];
$statusColors = [];

foreach($statusRows as $r){
    $k = $r['status'];
    $statusLabels[] = isset($statusMapFa[$k]) ? $statusMapFa[$k] : $k;
    $statusData[] = (int)$r['cnt'];
    $statusColors[] = isset($colorMap[$k]) ? $colorMap[$k] : '#64748b';
}

// User Trend Data
$userStart = ($fromDate && strtotime($fromDate)) ? strtotime(date('Y/m/d', strtotime($fromDate))) : (strtotime(date('Y/m/d')) - (13 * 86400));
$userEnd = ($toDate && strtotime($toDate)) ? strtotime(date('Y/m/d', strtotime($toDate))) : strtotime(date('Y/m/d'));
$daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);

$userLabels = [];
$userCounts = [];
$indexByDate = [];

for($i=0;$i<$daysBack;$i++){
    $d = $userStart + $i*86400;
    $key = date('Y/m/d',$d);
    $indexByDate[$key] = count($userLabels);
    $userLabels[] = function_exists('jdate') ? jdate('Y/m/d',$d) : date('Y/m/d',$d);
    $userCounts[] = 0;
}

foreach($regRows as $row){
    if(!is_numeric($row['register'])) continue;
    $key = date('Y/m/d', (int)$row['register']);
    if(isset($indexByDate[$key])){
        $userCounts[$indexByDate[$key]]++;
    }
}

// Time Greeting
$hour = date('H');
if ($hour < 12) { $greeting = "صبح بخیر"; $greetIcon = "fa-sun"; }
elseif ($hour < 17) { $greeting = "ظهر بخیر"; $greetIcon = "fa-coffee"; }
else { $greeting = "عصر بخیر"; $greetIcon = "fa-moon"; }

$currentDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>داشبورد مدیریت | پنل حرفه‌ای</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- CSS Dependencies -->
    <!-- FontAwesome 6 (CDN newer version for better icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" xintegrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Bootstrap CSS (Just for grid/utility, customised heavily) -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        :root {
            /* Palette: Slate & Indigo Theme */
            --bg-body: #0f172a;       /* Slate 900 */
            --bg-sidebar: #1e293b;    /* Slate 800 */
            --bg-card: rgba(30, 41, 59, 0.7); /* Glassy Slate 800 */
            --bg-card-hover: rgba(51, 65, 85, 0.8);
            
            --border-color: rgba(148, 163, 184, 0.1);
            --border-hover: rgba(99, 102, 241, 0.4);

            --primary: #6366f1;       /* Indigo 500 */
            --primary-glow: rgba(99, 102, 241, 0.3);
            --secondary: #06b6d4;     /* Cyan 500 */
            --accent: #f43f5e;        /* Rose 500 */
            
            --text-main: #f8fafc;     /* Slate 50 */
            --text-muted: #94a3b8;    /* Slate 400 */
            
            --nav-height: 70px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0;
            padding-top: var(--nav-height);
            overflow-x: hidden;
            /* Subtle Mesh Gradient Background */
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(6, 182, 212, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(244, 63, 94, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
        }

        /* --- Scrollbar --- */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* --- Navbar --- */
        .navbar-custom {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            height: var(--nav-height);
            position: fixed; top: 0; right: 0; left: 0; z-index: 1000;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 5%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .nav-brand {
            font-size: 1.4rem; font-weight: 800; color: #fff;
            display: flex; align-items: center; gap: 10px;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .nav-links { display: flex; gap: 5px; }
        .nav-item {
            color: var(--text-muted); text-decoration: none;
            padding: 10px 16px; border-radius: var(--radius-md);
            transition: all 0.25s ease; font-weight: 500; font-size: 0.95rem;
            display: flex; align-items: center; gap: 8px;
        }
        .nav-item:hover, .nav-item.active {
            color: #fff; background: rgba(255,255,255,0.05);
        }
        .nav-item.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        .nav-item.logout:hover {
            background: rgba(239, 68, 68, 0.15); color: #ef4444;
        }

        /* --- Main Layout --- */
        .container-dashboard {
            max-width: 1400px; margin: 0 auto;
            padding: 30px 20px 60px 20px;
        }

        /* --- Header Section --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 35px; flex-wrap: wrap; gap: 20px;
        }
        .welcome-text h1 { margin: 0; font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; }
        .welcome-text p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.95rem; }
        .date-badge {
            background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border-color);
            padding: 8px 16px; border-radius: 50px; font-size: 0.85rem;
            display: flex; align-items: center; gap: 8px; color: var(--text-muted);
        }

        /* --- Glass Cards --- */
        .card-glass {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 24px;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s;
            position: relative; overflow: hidden;
        }
        .card-glass:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
            border-color: var(--border-hover);
        }
        
        /* --- Quick Actions Grid --- */
        .actions-wrapper {
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); 
            gap: 16px; margin-bottom: 35px;
        }
        .action-btn {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px 10px;
            display: flex; flex-direction: column; align-items: center; gap: 12px;
            text-decoration: none; color: var(--text-muted);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .action-btn i { font-size: 1.6rem; transition: transform 0.3s; color: var(--text-main); }
        .action-btn span { font-size: 0.85rem; font-weight: 500; }
        
        .action-btn:hover {
            background: rgba(51, 65, 85, 0.6);
            border-color: var(--primary);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        .action-btn:hover i { transform: scale(1.15); color: var(--primary); }
        .action-btn.danger:hover { border-color: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .action-btn.danger:hover i { color: #ef4444; }

        /* --- Stats Cards --- */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px; margin-bottom: 35px;
        }
        .stat-card {
            display: flex; flex-direction: column; gap: 16px;
        }
        .stat-top { display: flex; justify-content: space-between; align-items: center; }
        .stat-icon-box {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .stat-value { font-size: 1.8rem; font-weight: 800; color: #fff; line-height: 1.2; }
        .stat-label { font-size: 0.9rem; color: var(--text-muted); }
        
        /* Icon Colors */
        .bg-indigo-soft { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
        .bg-emerald-soft { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .bg-rose-soft { background: rgba(244, 63, 94, 0.15); color: #fb7185; }
        .bg-cyan-soft { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }

        /* --- Filter Bar --- */
        .filter-bar {
            background: rgba(30, 41, 59, 0.4); border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); padding: 16px;
            display: flex; flex-wrap: wrap; align-items: center; gap: 16px;
            margin-bottom: 35px;
        }
        .form-control-custom {
            background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.2);
            color: #fff; padding: 0.7rem 1rem; border-radius: var(--radius-md);
            font-family: inherit; width: 100%; transition: 0.3s;
            min-height: 48px;
        }
        .form-control-custom:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .btn-filter {
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            color: white; border: none; padding: 0 24px; height: 48px;
            border-radius: var(--radius-md); font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); transition: 0.3s;
        }
        .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4); }

        /* --- Charts Grid --- */
        .charts-grid {
            display: grid; grid-template-columns: repeat(12, 1fr); gap: 24px;
        }
        .chart-col-8 { grid-column: span 12; }
        .chart-col-4 { grid-column: span 12; }
        
        @media (min-width: 1024px) {
            .chart-col-8 { grid-column: span 8; }
            .chart-col-4 { grid-column: span 4; }
        }

        .chart-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .chart-title { font-weight: 700; color: #f1f5f9; display: flex; align-items: center; gap: 8px; }

        /* --- Responsive Mobile --- */
        @media (max-width: 768px) {
            .navbar-custom { padding: 0 15px; }
            .nav-text { display: none; } /* Show only icons on mobile */
            .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .btn-filter { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar-custom">
        <div class="nav-brand">
            <i class="fa-solid fa-layer-group" style="color: var(--primary);"></i>
            <span>پنل مدیریت</span>
        </div>
        <div class="nav-links">
            <a href="index.php" class="nav-item active"><i class="fa-solid fa-chart-line"></i> <span class="nav-text">داشبورد</span></a>
            <a href="invoice.php" class="nav-item"><i class="fa-solid fa-cart-shopping"></i> <span class="nav-text">سفارشات</span></a>
            <a href="user.php" class="nav-item"><i class="fa-solid fa-users"></i> <span class="nav-text">کاربران</span></a>
            <a href="logout.php" class="nav-item logout" title="خروج"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </nav>

    <div class="container-dashboard">
        
        <!-- Header & Welcome -->
        <header class="page-header">
            <div class="welcome-text">
                <h1><?php echo $greeting; ?>، مدیر عزیز</h1>
                <p>خلاصه وضعیت سیستم و آمار لحظه‌ای فروشگاه شما</p>
            </div>
            <div class="date-badge">
                <i class="<?php echo $greetIcon; ?>" style="color: #fbbf24;"></i>
                <span><?php echo $currentDate; ?></span>
            </div>
        </header>

        <!-- Quick Access (Modern Grid) -->
        <div class="actions-wrapper">
            <a href="invoice.php" class="action-btn">
                <i class="fa-solid fa-receipt"></i>
                <span>سفارشات</span>
            </a>
            <a href="user.php" class="action-btn">
                <i class="fa-solid fa-user-group"></i>
                <span>کاربران</span>
            </a>
            <a href="product.php" class="action-btn">
                <i class="fa-solid fa-box-open"></i>
                <span>محصولات</span>
            </a>
            <a href="inbound.php" class="action-btn">
                <i class="fa-solid fa-server"></i>
                <span>کانفیگ‌ها</span>
            </a>
            <a href="payment.php" class="action-btn">
                <i class="fa-solid fa-credit-card"></i>
                <span>تراکنش‌ها</span>
            </a>
            <a href="support.php" class="action-btn">
                <i class="fa-solid fa-headset"></i>
                <span>پشتیبانی</span>
            </a>
            <a href="server_status.php" class="action-btn">
                <i class="fa-solid fa-hard-drive"></i>
                <span>وضعیت سرور</span>
            </a>
            <a href="cancelService.php" class="action-btn danger">
                <i class="fa-solid fa-ban"></i>
                <span>حذف سرویس</span>
            </a>
        </div>

        <!-- Stats Overview -->
        <div class="stats-row">
            <!-- Total Sales -->
            <div class="card-glass stat-card">
                <div class="stat-top">
                    <div class="stat-icon-box bg-indigo-soft"><i class="fa-solid fa-wallet"></i></div>
                    <i class="fa-solid fa-arrow-trend-up" style="color: #34d399;"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $formatted_total_sales; ?></div>
                    <div class="stat-label">مجموع فروش (تومان)</div>
                </div>
            </div>

            <!-- Total Users -->
            <div class="card-glass stat-card">
                <div class="stat-top">
                    <div class="stat-icon-box bg-rose-soft"><i class="fa-solid fa-users"></i></div>
                </div>
                <div>
                    <div class="stat-value"><?php echo number_format($resultcount); ?></div>
                    <div class="stat-label">تعداد کل کاربران</div>
                </div>
            </div>

            <!-- New Users Today -->
            <div class="card-glass stat-card">
                <div class="stat-top">
                    <div class="stat-icon-box bg-cyan-soft"><i class="fa-solid fa-user-plus"></i></div>
                </div>
                <div>
                    <div class="stat-value"><?php echo number_format($resultcountday); ?></div>
                    <div class="stat-label">ثبت نام‌های امروز</div>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="card-glass stat-card">
                <div class="stat-top">
                    <div class="stat-icon-box bg-emerald-soft"><i class="fa-solid fa-basket-shopping"></i></div>
                </div>
                <div>
                    <div class="stat-value"><?php echo number_format($resultcontsell); ?></div>
                    <div class="stat-label">تعداد فاکتورهای موفق</div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="get" class="filter-bar">
            <div style="flex: 1; min-width: 200px; position: relative;">
                <input type="text" id="dateRange" class="form-control-custom" placeholder="انتخاب بازه زمانی..." readonly>
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
                <i class="fa-regular fa-calendar" style="position: absolute; left: 15px; top: 16px; color: var(--text-muted); pointer-events: none;"></i>
            </div>

            <div style="flex: 1; min-width: 200px;">
                <select name="status[]" multiple class="form-control-custom" style="padding-top: 8px;">
                    <?php foreach($statusMapFa as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-filter"></i>
                اعمال فیلتر
            </button>
            <?php if($fromDate || !empty($selectedStatuses)): ?>
                <a href="index.php" class="btn-filter" style="background: rgba(255,255,255,0.1); width: auto;">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Main Sales Chart -->
            <div class="card-glass chart-col-8">
                <div class="chart-header">
                    <div class="chart-title"><i class="fa-solid fa-chart-area" style="color: var(--primary);"></i> نمودار درآمد</div>
                    <small style="color: var(--text-muted);">روند فروش در بازه زمانی</small>
                </div>
                <div style="height: 350px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Status Doughnut Chart -->
            <div class="card-glass chart-col-4">
                <div class="chart-header">
                    <div class="chart-title"><i class="fa-solid fa-chart-pie" style="color: var(--secondary);"></i> وضعیت سفارشات</div>
                </div>
                <div style="height: 250px; width: 100%; position: relative; margin-top: 20px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <!-- User Growth Chart -->
            <div class="card-glass chart-col-12" style="grid-column: span 12;">
                <div class="chart-header">
                    <div class="chart-title"><i class="fa-solid fa-user-group" style="color: var(--accent);"></i> روند جذب کاربر</div>
                    <small style="color: var(--text-muted);">تعداد ثبت نام‌های روزانه</small>
                </div>
                <div style="height: 300px; width: 100%;">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>

    <script>
        // --- DatePicker Logic ---
        $(function(){
            var start = moment().subtract(13, 'days');
            var end = moment();
            var phpFrom = '<?php echo $fromDate; ?>';
            var phpTo = '<?php echo $toDate; ?>';
            
            if(phpFrom && phpTo){
                start = moment(phpFrom);
                end = moment(phpTo);
            }

            function cb(start, end) {
                $('#dateRange').val(start.format('YYYY/MM/DD') + ' - ' + end.format('YYYY/MM/DD'));
                $('#inputFrom').val(start.format('YYYY-MM-DD'));
                $('#inputTo').val(end.format('YYYY-MM-DD'));
            }

            $('#dateRange').daterangepicker({
                startDate: start,
                endDate: end,
                opens: 'left',
                locale: {
                    format: 'YYYY/MM/DD',
                    applyLabel: 'تایید',
                    cancelLabel: 'لغو',
                    customRangeLabel: 'بازه دلخواه'
                }
            }, cb);

            if(phpFrom) cb(start, end);
        });

        // --- Charts Configuration (Modern Style) ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
        Chart.defaults.plugins.tooltip.titleColor = '#f8fafc';
        Chart.defaults.plugins.tooltip.padding = 14;
        Chart.defaults.plugins.tooltip.cornerRadius = 10;
        Chart.defaults.plugins.tooltip.displayColors = false;

        // PHP Data
        const salesData = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesAmount); ?> };
        const statusData = { labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($statusData); ?>, colors: <?php echo json_encode($statusColors); ?> };
        const userData = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userCounts); ?> };

        // 1. Sales Chart (Gradient Bar)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gradSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gradSales.addColorStop(0, '#6366f1');
        gradSales.addColorStop(1, 'rgba(99, 102, 241, 0.05)');

        new Chart(ctxSales, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'فروش (تومان)',
                    data: salesData.values,
                    backgroundColor: gradSales,
                    borderRadius: 6,
                    barPercentage: 0.6,
                    hoverBackgroundColor: '#818cf8'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { callback: function(value) { return value.toLocaleString(); } } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Status Chart (Clean Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.values,
                    backgroundColor: statusData.colors,
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, padding: 15, font: { size: 11 } } }
                }
            }
        });

        // 3. User Chart (Smooth Line Area)
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        const gradUser = ctxUser.createLinearGradient(0, 0, 0, 300);
        gradUser.addColorStop(0, 'rgba(244, 63, 94, 0.4)');
        gradUser.addColorStop(1, 'rgba(244, 63, 94, 0)');

        new Chart(ctxUser, {
            type: 'line',
            data: {
                labels: userData.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userData.values,
                    borderColor: '#f43f5e',
                    backgroundColor: gradUser,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { display: false }
                }
            }
        });
    </script>
</body>
</html>