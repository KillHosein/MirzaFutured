<?php
// --- خطایابی و گزارش‌دهی PHP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// فراخوانی فایل‌های مورد نیاز
if (file_exists('../config.php')) require_once '../config.php';
if (file_exists('../jdf.php')) require_once '../jdf.php';

// --- بررسی اتصال دیتابیس ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // مدیریت خطا در محیط واقعی
}

// --- بخش منطق (Logic) ---
$datefirstday = time() - 86400;
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];

if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// 1. بررسی احراز هویت
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

// 2. منطق فیلترها
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

// 3. دریافت آمار و ارقام
$total_sales = 0;
$resultcount = 0;
$resultcountday = 0;
$resultcontsell = 0;
$grouped_data = [];
$statusRows = [];
$regRows = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // مجموع فروش
        $query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
        $query->execute($invoiceParams);
        $subinvoice = $query->fetch(PDO::FETCH_ASSOC);
        $total_sales = $subinvoice['SUM(price_product)'] ?? 0;

        // تعداد کل کاربران
        $query = $pdo->prepare("SELECT COUNT(*) FROM user");
        $query->execute();
        $resultcount = $query->fetchColumn();

        // کاربران جدید امروز
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :time_register AND register != 'none'");
        $stmt->execute([':time_register' => $datefirstday]); 
        $resultcountday = $stmt->fetchColumn();

        // تعداد فروش
        $query = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
        $query->execute($invoiceParams);
        $resultcontsell = $query->fetchColumn();

        // داده‌های نمودار فروش
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

        // داده‌های نمودار وضعیت
        $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
        $stmt->execute($invoiceParams);
        $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // داده‌های نمودار کاربران جدید
        $userStart = ($fromDate && strtotime($fromDate)) ? strtotime(date('Y/m/d', strtotime($fromDate))) : (strtotime(date('Y/m/d')) - (13 * 86400));
        $userEnd = ($toDate && strtotime($toDate)) ? strtotime(date('Y/m/d', strtotime($toDate))) : strtotime(date('Y/m/d'));
        
        $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :ustart AND register <= :uend");
        $stmt->execute([':ustart' => $userStart, ':uend' => $userEnd + 86400 - 1]);
        $regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // error_log("DB Error");
    }
}

$formatted_total_sales = number_format($total_sales);

// فرمت‌دهی داده‌ها برای نمودار
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
// پالت رنگی حرفه‌ای (Modern Aurora)
$colorMap = [
    'unpaid' => '#F59E0B',      
    'active' => '#10B981',      
    'disabledn' => '#64748B',   
    'end_of_time' => '#EF4444', 
    'end_of_volume' => '#3B82F6', 
    'sendedwarn' => '#8B5CF6',  
    'send_on_hold' => '#F97316', 
    'removebyuser' => '#334155' 
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

// پیام خوش‌آمدگویی
$hour = date('H');
if ($hour < 12) { $greeting = "صبح بخیر"; $greetIcon = "fa-sun"; $greetColor = "#fbbf24"; }
elseif ($hour < 17) { $greeting = "ظهر بخیر"; $greetIcon = "fa-cloud-sun"; $greetColor = "#f59e0b"; }
else { $greeting = "عصر بخیر"; $greetIcon = "fa-moon"; $greetColor = "#818cf8"; }

$currentDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>داشبورد مدیریت | نسخه حرفه‌ای</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        :root {
            /* Palette: Deep Ocean & Neon Accents */
            --bg-body: #020617;        /* Slate 950 - Extremely dark blue */
            --bg-sidebar: rgba(15, 23, 42, 0.7);
            
            --glass-bg: rgba(30, 41, 59, 0.4); 
            --glass-border: 1px solid rgba(255, 255, 255, 0.06);
            --glass-shine: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, transparent 100%);
            
            --primary: #6366f1;        /* Indigo 500 */
            --primary-glow: rgba(99, 102, 241, 0.4);
            
            --secondary: #06b6d4;      /* Cyan 500 */
            --accent: #ec4899;         /* Pink 500 */
            --success: #10b981;        /* Emerald 500 */
            --warning: #f59e0b;        /* Amber 500 */
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            
            --radius-xl: 24px;
            --radius-lg: 18px;
            --radius-md: 12px;
            
            --sidebar-width: 80px;
            --sidebar-expanded: 240px;
        }

        /* --- Reset & Base --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            overflow-x: hidden;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        a { text-decoration: none; color: inherit; transition: 0.3s; }
        
        /* --- Animations --- */
        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .anim { animation: slideIn 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; } .d-4 { animation-delay: 0.4s; }

        /* --- Layout Grid --- */
        .app-container {
            display: flex; min-height: 100vh;
        }

        /* --- Sidebar (Glassmorphism) --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-left: var(--glass-border);
            display: flex; flex-direction: column; align-items: center;
            padding: 30px 0;
            position: fixed; right: 0; top: 0; bottom: 0; z-index: 1000;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .sidebar:hover { width: var(--sidebar-expanded); align-items: flex-start; }
        
        .brand {
            height: 50px; display: flex; align-items: center; justify-content: center; width: 100%;
            margin-bottom: 40px; color: #fff; font-size: 1.5rem; flex-shrink: 0;
        }
        .sidebar:hover .brand { justify-content: flex-start; padding-right: 25px; gap: 15px; }
        .brand span { display: none; font-size: 1.1rem; font-weight: 800; white-space: nowrap; }
        .sidebar:hover .brand span { display: block; animation: slideIn 0.3s; }

        .nav-list {
            display: flex; flex-direction: column; gap: 10px; width: 100%; padding: 0 15px;
        }
        .nav-item {
            height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); font-size: 1.2rem;
            position: relative; transition: 0.3s;
        }
        .sidebar:hover .nav-item { justify-content: flex-start; padding-right: 15px; gap: 15px; }
        
        .nav-item span { display: none; font-size: 0.95rem; white-space: nowrap; font-weight: 500; }
        .sidebar:hover .nav-item span { display: block; }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.08); color: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .nav-item.active::before {
            content: ''; position: absolute; right: -15px; top: 10px; bottom: 10px; width: 4px;
            background: var(--primary); border-radius: 4px 0 0 4px;
        }
        .nav-item.logout { margin-top: auto; color: var(--end_of_time); }
        .nav-item.logout:hover { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        /* --- Main Content --- */
        .main-content {
            flex: 1; margin-right: var(--sidebar-width);
            padding: 40px 50px;
            transition: margin-right 0.3s;
        }
        
        /* --- Header --- */
        .top-header {
            display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 50px;
        }
        .welcome-section h1 {
            font-size: 2.2rem; font-weight: 900; margin: 0;
            background: linear-gradient(to left, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .welcome-section p { color: var(--text-muted); margin: 8px 0 0; display: flex; align-items: center; gap: 8px; }
        
        .date-card {
            background: var(--glass-bg); border: var(--glass-border);
            padding: 10px 20px; border-radius: 50px;
            display: flex; align-items: center; gap: 12px; color: var(--text-main);
            font-size: 0.95rem; backdrop-filter: blur(10px);
        }

        /* --- Quick Actions (Interactive Tiles) --- */
        .section-header {
            display: flex; align-items: center; gap: 12px; margin-bottom: 25px;
            color: var(--text-main); font-weight: 700; font-size: 1.1rem;
        }
        .section-header i { color: var(--secondary); }

        .actions-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 20px;
            margin-bottom: 50px;
        }
        .action-card {
            background: var(--glass-bg); border: var(--glass-border);
            border-radius: var(--radius-lg); padding: 25px 15px;
            display: flex; flex-direction: column; align-items: center; gap: 15px;
            position: relative; overflow: hidden; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .action-card::after {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 50% 100%, rgba(255,255,255,0.1), transparent 60%);
            opacity: 0; transition: 0.3s;
        }
        .action-card:hover { transform: translateY(-8px); border-color: rgba(255,255,255,0.2); background: rgba(30, 41, 59, 0.6); }
        .action-card:hover::after { opacity: 1; }
        
        .action-icon {
            font-size: 2rem; color: var(--text-muted); transition: 0.3s;
            filter: drop-shadow(0 0 0 rgba(0,0,0,0));
        }
        .action-card:hover .action-icon { transform: scale(1.1); color: #fff; filter: drop-shadow(0 0 15px var(--glow-color)); }
        .action-label { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); transition: 0.3s; }
        .action-card:hover .action-label { color: #fff; }

        /* Specific Glow Colors */
        .ac-primary { --glow-color: rgba(99, 102, 241, 0.6); }
        .ac-cyan { --glow-color: rgba(6, 182, 212, 0.6); }
        .ac-pink { --glow-color: rgba(236, 72, 153, 0.6); }
        .ac-amber { --glow-color: rgba(245, 158, 11, 0.6); }
        .ac-red { --glow-color: rgba(239, 68, 68, 0.6); }

        /* --- Filter Bar --- */
        .filter-wrapper {
            background: rgba(15, 23, 42, 0.4); border: 1px dashed rgba(255,255,255,0.1);
            border-radius: var(--radius-lg); padding: 15px;
            display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 40px;
        }
        .glass-input {
            flex: 1; min-width: 200px;
            background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 12px 18px; border-radius: var(--radius-md);
            font-family: inherit; transition: 0.3s;
        }
        .glass-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        
        .btn-glass {
            background: var(--primary); color: #fff; border: none;
            padding: 0 25px; border-radius: var(--radius-md); font-weight: 600;
            cursor: pointer; transition: 0.3s;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            display: flex; align-items: center; gap: 8px;
        }
        .btn-glass:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5); }

        /* --- Stats Overview --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px; margin-bottom: 40px;
        }
        .stat-card {
            background: var(--glass-bg); border: var(--glass-border);
            backdrop-filter: blur(12px); border-radius: var(--radius-xl);
            padding: 25px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 150px; transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.15); }
        
        /* Background Icon for Stat Card */
        .stat-bg-icon {
            position: absolute; left: -20px; bottom: -20px;
            font-size: 8rem; opacity: 0.05; transform: rotate(15deg);
            pointer-events: none; transition: 0.3s;
        }
        .stat-card:hover .stat-bg-icon { transform: rotate(0deg) scale(1.1); opacity: 0.1; }

        .stat-head { display: flex; justify-content: space-between; align-items: flex-start; z-index: 1; }
        .icon-circle {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .stat-badge { font-size: 0.8rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; }
        
        .stat-body { z-index: 1; margin-top: 15px; }
        .stat-val { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 4px; }
        .stat-lbl { color: var(--text-muted); font-size: 0.9rem; }

        /* --- Charts Layout --- */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .chart-panel {
            background: var(--glass-bg); border: var(--glass-border);
            border-radius: var(--radius-xl); padding: 25px;
            position: relative;
        }
        .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .panel-title { font-size: 1.1rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { width: 0; padding: 0; }
            .sidebar.mobile-active { width: var(--sidebar-expanded); padding: 30px 0; }
            .main-content { margin-right: 0; padding: 30px 20px; }
            .charts-row { grid-template-columns: 1fr; }
            
            /* Mobile Nav Toggle */
            .nav-toggle { display: block; position: fixed; bottom: 20px; left: 20px; z-index: 1100; background: var(--primary); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.4); cursor: pointer; }
        }
        @media (min-width: 1025px) { .nav-toggle { display: none; } }
        @media (max-width: 480px) {
            .top-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .stat-val { font-size: 1.6rem; }
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <i class="fa-brands fa-hive" style="color: var(--primary);"></i>
            <span>AdminPanel</span>
        </div>
        <div class="nav-list">
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-grid-2"></i>
                <span>داشبورد</span>
            </a>
            <a href="invoice.php" class="nav-item">
                <i class="fa-solid fa-receipt"></i>
                <span>سفارشات</span>
            </a>
            <a href="user.php" class="nav-item">
                <i class="fa-solid fa-users"></i>
                <span>کاربران</span>
            </a>
            <a href="product.php" class="nav-item">
                <i class="fa-solid fa-box"></i>
                <span>محصولات</span>
            </a>
            <a href="server_status.php" class="nav-item">
                <i class="fa-solid fa-server"></i>
                <span>سرور</span>
            </a>
            <a href="logout.php" class="nav-item logout">
                <i class="fa-solid fa-power-off"></i>
                <span>خروج</span>
            </a>
        </div>
    </aside>

    <!-- Mobile Toggle -->
    <div class="nav-toggle" onclick="document.getElementById('sidebar').classList.toggle('mobile-active')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Workspace -->
    <main class="main-content">
        
        <!-- Header -->
        <header class="top-header anim">
            <div class="welcome-section">
                <h1><?php echo $greeting; ?>، مدیر</h1>
                <p>
                    <i class="<?php echo $greetIcon; ?>" style="color: <?php echo $greetColor; ?>;"></i>
                    وضعیت سیستم در یک نگاه
                </p>
            </div>
            <div class="date-card">
                <span><?php echo $currentDate; ?></span>
                <i class="fa-regular fa-calendar-check" style="color: var(--secondary);"></i>
            </div>
        </header>

        <!-- Quick Access -->
        <section class="anim d-1">
            <div class="section-header">
                <i class="fa-solid fa-bolt"></i> دسترسی سریع
            </div>
            <div class="actions-grid">
                <a href="invoice.php" class="action-card ac-primary">
                    <i class="fa-solid fa-cart-shopping action-icon"></i>
                    <span class="action-label">سفارشات</span>
                </a>
                <a href="user.php" class="action-card ac-pink">
                    <i class="fa-solid fa-user-group action-icon"></i>
                    <span class="action-label">کاربران</span>
                </a>
                <a href="inbound.php" class="action-card ac-cyan">
                    <i class="fa-solid fa-network-wired action-icon"></i>
                    <span class="action-label">کانفیگ</span>
                </a>
                <a href="payment.php" class="action-card ac-amber">
                    <i class="fa-solid fa-wallet action-icon"></i>
                    <span class="action-label">مالی</span>
                </a>
                <a href="server_status.php" class="action-card ac-cyan">
                    <i class="fa-solid fa-microchip action-icon"></i>
                    <span class="action-label">سرور</span>
                </a>
                <a href="cancelService.php" class="action-card ac-red">
                    <i class="fa-solid fa-ban action-icon"></i>
                    <span class="action-label">مسدودسازی</span>
                </a>
            </div>
        </section>

        <!-- Stats Overview -->
        <section class="stats-grid anim d-2">
            <!-- Sales -->
            <div class="stat-card">
                <i class="fa-solid fa-sack-dollar stat-bg-icon"></i>
                <div class="stat-head">
                    <div class="icon-circle" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                    <span class="stat-badge" style="background: rgba(99, 102, 241, 0.15); color: #a5b4fc;">درآمد</span>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?php echo $formatted_total_sales; ?></div>
                    <div class="stat-lbl">کل فروش (تومان)</div>
                </div>
            </div>

            <!-- Orders -->
            <div class="stat-card">
                <i class="fa-solid fa-file-invoice stat-bg-icon"></i>
                <div class="stat-head">
                    <div class="icon-circle" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <span class="stat-badge" style="background: rgba(16, 185, 129, 0.15); color: #6ee7b7;">سفارشات</span>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?php echo number_format($resultcontsell); ?></div>
                    <div class="stat-lbl">تعداد سفارش موفق</div>
                </div>
            </div>

            <!-- Users -->
            <div class="stat-card">
                <i class="fa-solid fa-users-viewfinder stat-bg-icon"></i>
                <div class="stat-head">
                    <div class="icon-circle" style="background: rgba(236, 72, 153, 0.1); color: var(--accent);">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <span class="stat-badge" style="background: rgba(236, 72, 153, 0.15); color: #f9a8d4;">کاربران</span>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?php echo number_format($resultcount); ?></div>
                    <div class="stat-lbl">کل کاربران</div>
                </div>
            </div>

            <!-- New Users -->
            <div class="stat-card">
                <i class="fa-solid fa-user-plus stat-bg-icon"></i>
                <div class="stat-head">
                    <div class="icon-circle" style="background: rgba(6, 182, 212, 0.1); color: var(--secondary);">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <span class="stat-badge" style="background: rgba(6, 182, 212, 0.15); color: #67e8f9;">امروز</span>
                </div>
                <div class="stat-body">
                    <div class="stat-val"><?php echo number_format($resultcountday); ?></div>
                    <div class="stat-lbl">ثبت نام جدید</div>
                </div>
            </div>
        </section>

        <!-- Filter Bar -->
        <form method="get" class="filter-wrapper anim d-3">
            <input type="text" id="dateRange" class="glass-input" placeholder="انتخاب بازه زمانی..." readonly>
            <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
            <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
            
            <select name="status[]" multiple class="glass-input">
                <?php foreach($statusMapFa as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo in_array($k, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-glass">
                <i class="fa-solid fa-filter"></i> اعمال
            </button>
            <?php if($fromDate || !empty($selectedStatuses)): ?>
                <a href="index.php" class="btn-glass" style="background: rgba(255,255,255,0.1); width: auto;">
                    <i class="fa-solid fa-rotate-right"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Charts -->
        <div class="charts-row anim d-4">
            <!-- Main Chart -->
            <div class="chart-panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fa-solid fa-chart-area" style="color: var(--primary);"></i> روند فروش</div>
                </div>
                <div style="height: 320px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Side Charts -->
            <div style="display: flex; flex-direction: column; gap: 25px;">
                <div class="chart-panel" style="flex: 1;">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fa-solid fa-chart-pie" style="color: var(--accent);"></i> وضعیت‌ها</div>
                    </div>
                    <div style="height: 200px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-panel" style="flex: 1;">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fa-solid fa-arrow-trend-up" style="color: var(--secondary);"></i> جذب کاربر</div>
                    </div>
                    <div style="height: 150px;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>

    <script>
        // --- DatePicker ---
        $(function(){
            var start = moment().subtract(13, 'days');
            var end = moment();
            var phpFrom = '<?php echo $fromDate; ?>';
            var phpTo = '<?php echo $toDate; ?>';
            if(phpFrom && phpTo){ start = moment(phpFrom); end = moment(phpTo); }

            $('#dateRange').daterangepicker({
                startDate: start, endDate: end, opens: 'left',
                locale: { format: 'YYYY/MM/DD', applyLabel: 'تایید', cancelLabel: 'لغو' }
            }, function(s, e) {
                $('#dateRange').val(s.format('YYYY/MM/DD') + '  -  ' + e.format('YYYY/MM/DD'));
                $('#inputFrom').val(s.format('YYYY-MM-DD'));
                $('#inputTo').val(e.format('YYYY-MM-DD'));
            });
            if(phpFrom) $('#dateRange').val(start.format('YYYY/MM/DD') + '  -  ' + end.format('YYYY/MM/DD'));
        });

        // --- Charts Config (Ultra Modern) ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#64748B';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.03)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';
        Chart.defaults.plugins.tooltip.padding = 15;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.titleColor = '#fff';
        Chart.defaults.plugins.tooltip.displayColors = false;

        const salesData = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesAmount); ?> };
        const statusData = { labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($statusData); ?>, colors: <?php echo json_encode($statusColors); ?> };
        const userData = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userCounts); ?> };

        // 1. Sales Chart (Purple Gradient)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gSales.addColorStop(0, '#6366f1');
        gSales.addColorStop(1, 'rgba(99, 102, 241, 0.01)');

        new Chart(ctxSales, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'فروش',
                    data: salesData.values,
                    backgroundColor: gSales,
                    borderRadius: 6,
                    hoverBackgroundColor: '#818cf8',
                    barThickness: 20
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.02)' }, ticks: { callback: v => v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Status Chart (Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.values,
                    backgroundColor: statusData.colors,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, padding: 15, font: { size: 11 }, color: '#94a3b8' } }
                }
            }
        });

        // 3. User Chart (Cyan Line)
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        const gUser = ctxUser.createLinearGradient(0, 0, 0, 200);
        gUser.addColorStop(0, 'rgba(6, 182, 212, 0.3)');
        gUser.addColorStop(1, 'rgba(6, 182, 212, 0)');

        new Chart(ctxUser, {
            type: 'line',
            data: {
                labels: userData.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userData.values,
                    borderColor: '#06b6d4',
                    backgroundColor: gUser,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { display: false } }
            }
        });
    </script>
</body>
</html>