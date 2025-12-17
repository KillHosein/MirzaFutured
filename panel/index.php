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
// پالت رنگی جدید و مدرن (Vibrant & Neon)
$colorMap = [
    'unpaid' => '#F59E0B',      // Amber Glow
    'active' => '#10B981',      // Emerald Neon
    'disabledn' => '#64748B',   // Cool Slate
    'end_of_time' => '#EF4444', // Red Alert
    'end_of_volume' => '#3B82F6', // Electric Blue
    'sendedwarn' => '#8B5CF6',  // Violet Beam
    'send_on_hold' => '#F97316', // Orange Flame
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
if ($hour < 12) { $greeting = "صبح بخیر"; $greetIcon = "fa-sun"; $greetColor = "#FCD34D"; }
elseif ($hour < 17) { $greeting = "ظهر بخیر"; $greetIcon = "fa-cloud-sun"; $greetColor = "#F97316"; }
else { $greeting = "عصر بخیر"; $greetIcon = "fa-moon"; $greetColor = "#818CF8"; }

$currentDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل مدیریت | داشبورد</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- CSS Dependencies -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        :root {
            --bg-body: #0B0F19;       /* Deepest Slate */
            --bg-nav: rgba(15, 23, 42, 0.7);
            --bg-card: rgba(30, 41, 59, 0.4);
            --bg-card-hover: rgba(51, 65, 85, 0.5);
            
            --primary: #6366F1;       /* Indigo */
            --primary-light: #818CF8;
            --secondary: #EC4899;     /* Pink */
            --accent: #06B6D4;        /* Cyan */
            
            --text-main: #F1F5F9;
            --text-muted: #94A3B8;
            
            --glass-border: 1px solid rgba(255, 255, 255, 0.08);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 24px;
        }

        /* --- Global Reset & Body --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            overflow-x: hidden;
            /* Dynamic Aurora Background */
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 10%, rgba(236, 72, 153, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(6, 182, 212, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 90%, rgba(139, 92, 246, 0.12) 0%, transparent 45%);
            background-attachment: fixed;
        }

        /* --- Animations --- */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.96) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .animate-in { animation: fadeInScale 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        /* --- Navigation (Floating Island) --- */
        .nav-container {
            position: fixed; top: 20px; left: 0; right: 0;
            z-index: 1000; padding: 0 20px;
            display: flex; justify-content: center;
        }
        .navbar-island {
            background: var(--bg-nav);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: var(--glass-border);
            border-radius: 50px;
            padding: 10px 30px;
            display: flex; align-items: center; justify-content: space-between;
            width: 100%; max-width: 1200px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .nav-brand {
            font-size: 1.2rem; font-weight: 800; color: #fff;
            display: flex; align-items: center; gap: 12px;
        }
        .nav-brand i { color: var(--accent); filter: drop-shadow(0 0 8px var(--accent)); }
        
        .nav-menu { display: flex; gap: 8px; }
        .nav-link {
            color: var(--text-muted); text-decoration: none;
            padding: 10px 18px; border-radius: 30px;
            font-size: 0.9rem; font-weight: 500;
            transition: all 0.3s ease;
            display: flex; align-items: center; gap: 8px;
        }
        .nav-link:hover { color: #fff; background: rgba(255,255,255,0.06); }
        .nav-link.active {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary-light);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.1);
        }
        .nav-link.logout { margin-right: 15px; }
        .nav-link.logout:hover { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        /* --- Main Layout --- */
        .wrapper {
            max-width: 1200px; margin: 0 auto;
            padding: 120px 20px 50px 20px;
        }

        /* --- Hero Header --- */
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 40px; padding: 0 10px;
        }
        .welcome h1 { 
            margin: 0; font-size: 2.2rem; font-weight: 900; 
            background: linear-gradient(135deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .welcome p { margin: 8px 0 0; color: var(--text-muted); font-size: 1rem; }
        .date-display {
            background: rgba(255,255,255,0.03); border: var(--glass-border);
            padding: 10px 20px; border-radius: var(--radius-md);
            display: flex; align-items: center; gap: 10px;
            color: var(--text-main); font-weight: 500;
        }

        /* --- Quick Actions (App Icons) --- */
        .quick-actions-title {
            font-size: 1.1rem; font-weight: 700; color: var(--text-main);
            margin-bottom: 20px; padding-right: 10px; display: flex; align-items: center; gap: 10px;
        }
        .quick-actions-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 20px; margin-bottom: 40px;
        }
        .app-icon {
            display: flex; flex-direction: column; align-items: center; gap: 12px;
            text-decoration: none; color: var(--text-muted);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
        }
        .icon-box {
            width: 65px; height: 65px; border-radius: 20px;
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
            border: var(--glass-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
        }
        .app-icon span { font-size: 0.85rem; font-weight: 500; transition: color 0.3s; text-align: center; }
        
        .app-icon:hover .icon-box {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.4), 0 0 10px rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.2);
        }
        .app-icon:hover span { color: #fff; }
        
        /* Specific Icon Colors */
        .i-orders { color: #60A5FA; }  /* Blue */
        .i-users { color: #F472B6; }   /* Pink */
        .i-products { color: #A78BFA; } /* Purple */
        .i-server { color: #34D399; }  /* Green */
        .i-danger { color: #F87171; }  /* Red */

        /* --- Stats Cards (Glass Panels) --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px; margin-bottom: 40px;
        }
        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 25px;
            position: relative; overflow: hidden;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); background: var(--bg-card-hover); }
        .stat-card::after {
            content: ''; position: absolute; top: 0; right: 0; width: 6px; height: 100%;
            background: currentColor; opacity: 0.8;
        }
        
        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .stat-icon { font-size: 2rem; opacity: 0.8; }
        .stat-info h3 { margin: 0; font-size: 2rem; font-weight: 800; color: #fff; }
        .stat-info span { font-size: 0.9rem; color: var(--text-muted); display: block; margin-top: 5px; }

        /* --- Filter Bar --- */
        .filter-section {
            background: rgba(15, 23, 42, 0.6);
            border: 1px dashed rgba(255,255,255,0.1);
            border-radius: var(--radius-md);
            padding: 15px 20px;
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center;
            margin-bottom: 30px;
        }
        .modern-input {
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 12px 16px; border-radius: 12px;
            flex: 1; min-width: 200px;
            transition: 0.3s;
        }
        .modern-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); }
        
        .btn-apply {
            background: var(--primary); color: #fff; border: none;
            padding: 12px 24px; border-radius: 12px; font-weight: 600;
            cursor: pointer; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            transition: 0.3s;
        }
        .btn-apply:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.5); }

        /* --- Charts --- */
        .chart-container {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 25px;
        }
        .chart-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .chart-head h4 { margin: 0; font-size: 1.1rem; color: var(--text-main); display: flex; align-items: center; gap: 10px; }
        
        .grid-charts { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        @media (max-width: 1000px) { .grid-charts { grid-template-columns: 1fr; } }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .nav-container { top: auto; bottom: 20px; padding: 0 15px; }
            .navbar-island { border-radius: 20px; padding: 10px 15px; }
            .nav-text { display: none; }
            .nav-brand { display: none; }
            .nav-menu { width: 100%; justify-content: space-around; }
            .nav-link { padding: 10px; border-radius: 14px; font-size: 1.2rem; }
            
            .wrapper { padding-top: 30px; padding-bottom: 100px; }
            .dashboard-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
            .filter-section { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 400px) {
            .quick-actions-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .icon-box { width: 55px; height: 55px; font-size: 1.4rem; }
        }
    </style>
</head>
<body>

    <!-- Floating Navigation -->
    <div class="nav-container animate-in">
        <nav class="navbar-island">
            <div class="nav-brand">
                <i class="fa-solid fa-cube"></i>
                <span>Admin Panel</span>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-link active"><i class="fa-solid fa-house"></i> <span class="nav-text">خانه</span></a>
                <a href="invoice.php" class="nav-link"><i class="fa-solid fa-layer-group"></i> <span class="nav-text">سفارشات</span></a>
                <a href="user.php" class="nav-link"><i class="fa-solid fa-users"></i> <span class="nav-text">کاربران</span></a>
                <a href="logout.php" class="nav-link logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </nav>
    </div>

    <div class="wrapper">
        
        <!-- Welcome Header -->
        <header class="dashboard-header animate-in delay-1">
            <div class="welcome">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                    <i class="<?php echo $greetIcon; ?>" style="color: <?php echo $greetColor; ?>; font-size: 1.4rem;"></i>
                    <span style="color: var(--text-muted); font-size: 0.95rem;"><?php echo $greeting; ?></span>
                </div>
                <h1>داشبورد مدیریت</h1>
            </div>
            <div class="date-display">
                <i class="fa-regular fa-calendar-check" style="color: var(--accent);"></i>
                <span><?php echo $currentDate; ?></span>
            </div>
        </header>

        <!-- Quick Apps Grid -->
        <div class="animate-in delay-2">
            <div class="quick-actions-title">
                <i class="fa-solid fa-grip" style="color: var(--text-muted);"></i>
                دسترسی سریع
            </div>
            <div class="quick-actions-grid">
                <a href="invoice.php" class="app-icon">
                    <div class="icon-box i-orders"><i class="fa-solid fa-basket-shopping"></i></div>
                    <span>سفارشات</span>
                </a>
                <a href="user.php" class="app-icon">
                    <div class="icon-box i-users"><i class="fa-solid fa-user-group"></i></div>
                    <span>کاربران</span>
                </a>
                <a href="product.php" class="app-icon">
                    <div class="icon-box i-products"><i class="fa-solid fa-box-open"></i></div>
                    <span>محصولات</span>
                </a>
                <a href="inbound.php" class="app-icon">
                    <div class="icon-box i-server"><i class="fa-solid fa-server"></i></div>
                    <span>کانفیگ</span>
                </a>
                <a href="payment.php" class="app-icon">
                    <div class="icon-box" style="color: #FCD34D;"><i class="fa-solid fa-credit-card"></i></div>
                    <span>مالی</span>
                </a>
                <a href="server_status.php" class="app-icon">
                    <div class="icon-box" style="color: #fff;"><i class="fa-solid fa-microchip"></i></div>
                    <span>سرور</span>
                </a>
                <a href="cancelService.php" class="app-icon">
                    <div class="icon-box i-danger"><i class="fa-solid fa-ban"></i></div>
                    <span>مسدودی</span>
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="get" class="filter-section animate-in delay-2">
            <div style="flex: 2; position: relative;">
                <input type="text" id="dateRange" class="modern-input" placeholder="انتخاب تاریخ..." readonly style="padding-right: 40px; cursor: pointer;">
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
                <i class="fa-solid fa-calendar-day" style="position: absolute; right: 15px; top: 14px; color: var(--text-muted); pointer-events: none;"></i>
            </div>
            
            <div style="flex: 2;">
                <select name="status[]" multiple class="modern-input" style="height: 48px; padding-top: 8px;">
                    <?php foreach($statusMapFa as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-apply">
                <i class="fa-solid fa-filter"></i> فیلتر
            </button>
            <?php if($fromDate || !empty($selectedStatuses)): ?>
                <a href="index.php" class="btn-apply" style="background: rgba(255,255,255,0.1); width: auto; text-align: center;">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Stats Overview -->
        <div class="stats-grid animate-in delay-3">
            <!-- Sales -->
            <div class="stat-card" style="color: #6366F1;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
                    <div style="background: rgba(99, 102, 241, 0.2); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">درآمد</div>
                </div>
                <div class="stat-info">
                    <h3><?php echo $formatted_total_sales; ?></h3>
                    <span>مجموع فروش (تومان)</span>
                </div>
            </div>

            <!-- Count -->
            <div class="stat-card" style="color: #10B981;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
                    <div style="background: rgba(16, 185, 129, 0.2); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">سفارشات</div>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($resultcontsell); ?></h3>
                    <span>تعداد فاکتور موفق</span>
                </div>
            </div>

            <!-- Users -->
            <div class="stat-card" style="color: #EC4899;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div style="background: rgba(236, 72, 153, 0.2); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">کاربران</div>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($resultcount); ?></h3>
                    <span>کل کاربران</span>
                </div>
            </div>

            <!-- New Users -->
            <div class="stat-card" style="color: #06B6D4;">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div>
                    <div style="background: rgba(6, 182, 212, 0.2); padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">جذب</div>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($resultcountday); ?></h3>
                    <span>ثبت‌نام امروز</span>
                </div>
            </div>
        </div>

        <!-- Charts Area -->
        <div class="grid-charts animate-in delay-3">
            <div class="chart-container">
                <div class="chart-head">
                    <h4><i class="fa-solid fa-chart-area" style="color: var(--primary);"></i> نمودار فروش</h4>
                </div>
                <div style="height: 300px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-head">
                    <h4><i class="fa-solid fa-chart-pie" style="color: var(--secondary);"></i> وضعیت‌ها</h4>
                </div>
                <div style="height: 300px; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container" style="grid-column: 1 / -1;">
                <div class="chart-head">
                    <h4><i class="fa-solid fa-arrow-trend-up" style="color: var(--accent);"></i> روند رشد کاربران</h4>
                </div>
                <div style="height: 250px;">
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
        // --- DatePicker & Logic ---
        $(function(){
            var start = moment().subtract(13, 'days');
            var end = moment();
            var phpFrom = '<?php echo $fromDate; ?>';
            var phpTo = '<?php echo $toDate; ?>';
            
            if(phpFrom && phpTo){ start = moment(phpFrom); end = moment(phpTo); }

            function cb(start, end) {
                $('#dateRange').val(start.format('YYYY/MM/DD') + '  تا  ' + end.format('YYYY/MM/DD'));
                $('#inputFrom').val(start.format('YYYY-MM-DD'));
                $('#inputTo').val(end.format('YYYY-MM-DD'));
            }

            $('#dateRange').daterangepicker({
                startDate: start, endDate: end, opens: 'left',
                locale: { format: 'YYYY/MM/DD', applyLabel: 'تایید', cancelLabel: 'لغو' }
            }, cb);

            if(phpFrom) cb(start, end);
        });

        // --- Aesthetic Charts Config ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#64748B';
        Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.03)';
        Chart.defaults.scale.grid.borderColor = 'transparent';
        
        // Tooltip Styling
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.titleFont = { size: 13, family: 'Vazirmatn' };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 12, family: 'Vazirmatn' };
        Chart.defaults.plugins.tooltip.cornerRadius = 12;
        Chart.defaults.plugins.tooltip.displayColors = false;

        const salesData = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesAmount); ?> };
        const statusData = { labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($statusData); ?>, colors: <?php echo json_encode($statusColors); ?> };
        const userData = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userCounts); ?> };

        // 1. Sales Chart
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gradSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gradSales.addColorStop(0, '#6366F1');
        gradSales.addColorStop(1, 'rgba(99, 102, 241, 0.01)');

        new Chart(ctxSales, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'فروش',
                    data: salesData.values,
                    backgroundColor: gradSales,
                    borderRadius: 8,
                    barThickness: 25
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.values,
                    backgroundColor: statusData.colors,
                    borderWidth: 0, hoverOffset: 15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '80%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, padding: 15, font: { size: 11 } } }
                }
            }
        });

        // 3. User Chart
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        const gradUser = ctxUser.createLinearGradient(0, 0, 0, 250);
        gradUser.addColorStop(0, 'rgba(6, 182, 212, 0.4)');
        gradUser.addColorStop(1, 'rgba(6, 182, 212, 0)');

        new Chart(ctxUser, {
            type: 'line',
            data: {
                labels: userData.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userData.values,
                    borderColor: '#06B6D4',
                    backgroundColor: gradUser,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { display: false } }
            }
        });
    </script>
</body>
</html>