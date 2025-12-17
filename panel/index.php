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
    'unpaid' => 'منتظر پرداخت',
    'active' => 'فعال شده',
    'disabledn' => 'غیرفعال',
    'end_of_time' => 'انقضای زمانی',
    'end_of_volume' => 'اتمام حجم',
    'sendedwarn' => 'هشدار ارسال شده',
    'send_on_hold' => 'در صف اتصال',
    'removebyuser' => 'حذف توسط کاربر'
];

// پالت رنگی مدیریتی (Executive Palette)
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
    <title>پنل نظارت و راهبری</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        :root {
            /* Palette: Midnight Executive */
            --bg-body: #0f172a;        /* Slate 900 */
            --bg-sidebar: rgba(15, 23, 42, 0.85);
            
            --glass-bg: rgba(30, 41, 59, 0.4); 
            --glass-border: 1px solid rgba(255, 255, 255, 0.08);
            --glass-hover: rgba(255, 255, 255, 0.05);
            
            --primary: #6366f1;        /* Indigo */
            --secondary: #0ea5e9;      /* Sky */
            --accent: #f43f5e;         /* Rose */
            --gold: #f59e0b;           /* Amber */
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            
            --radius-xl: 20px;
            --radius-lg: 16px;
            --radius-md: 12px;
            
            --sidebar-width: 86px;
            --sidebar-expanded: 260px;
        }

        /* --- Reset & Typography --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        h1, h2, h3, h4, h5, h6 { margin: 0; font-weight: 700; letter-spacing: -0.02em; }
        
        /* --- Animations --- */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .anim { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; } .d-4 { animation-delay: 0.4s; }

        /* --- Layout --- */
        .app-shell { display: flex; min-height: 100vh; }
        .main-view {
            flex: 1; margin-right: var(--sidebar-width);
            padding: 40px 60px;
            transition: margin-right 0.3s ease;
        }

        /* --- Sidebar (Premium Glass) --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-left: var(--glass-border);
            display: flex; flex-direction: column; align-items: center;
            padding: 35px 0;
            position: fixed; right: 0; top: 0; bottom: 0; z-index: 1000;
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden; box-shadow: -10px 0 40px rgba(0,0,0,0.5);
        }
        .sidebar:hover { width: var(--sidebar-expanded); align-items: stretch; padding-right: 20px; padding-left: 20px; }
        
        /* Logo Area */
        .brand-box {
            height: 60px; display: flex; align-items: center; justify-content: center;
            margin-bottom: 40px; color: #fff; flex-shrink: 0;
            transition: 0.3s;
        }
        .sidebar:hover .brand-box { justify-content: flex-start; gap: 16px; padding-right: 10px; }
        .brand-icon { font-size: 1.8rem; color: var(--primary); filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.4)); }
        .brand-text { display: none; font-size: 1.15rem; font-weight: 800; white-space: nowrap; opacity: 0; transform: translateX(10px); transition: 0.3s; }
        .sidebar:hover .brand-text { display: block; opacity: 1; transform: translateX(0); }

        /* Navigation */
        .nav-menu { display: flex; flex-direction: column; gap: 8px; width: 100%; padding: 0 12px; }
        .nav-link {
            height: 52px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); position: relative;
            transition: all 0.25s ease;
        }
        .sidebar:hover .nav-link { justify-content: flex-start; padding-right: 16px; gap: 16px; }
        
        .nav-link i { font-size: 1.3rem; min-width: 24px; text-align: center; transition: 0.3s; }
        .nav-text { display: none; font-size: 0.95rem; font-weight: 500; white-space: nowrap; opacity: 0; }
        .sidebar:hover .nav-text { display: block; opacity: 1; transition-delay: 0.1s; }
        
        .nav-link:hover { background: var(--glass-hover); color: #fff; }
        .nav-link.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.15), transparent);
            color: #fff; border-right: 3px solid var(--primary);
        }
        .nav-link.active i { color: var(--primary); transform: scale(1.1); }
        
        .nav-link.logout { margin-top: auto; color: var(--accent); }
        .nav-link.logout:hover { background: rgba(244, 63, 94, 0.1); }

        /* --- Header Section --- */
        .header-bar {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px; border-bottom: 1px solid rgba(255,255,255,0.03);
            padding-bottom: 25px;
        }
        .page-title h1 {
            font-size: 2rem; color: #fff; margin-bottom: 8px;
            text-shadow: 0 0 30px rgba(255,255,255,0.1);
        }
        .page-title p { color: var(--text-muted); font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
        
        .date-pill {
            background: rgba(15, 23, 42, 0.6); border: var(--glass-border);
            padding: 10px 24px; border-radius: 50px;
            display: flex; align-items: center; gap: 12px;
            font-size: 0.9rem; font-weight: 500; color: var(--text-main);
        }

        /* --- Quick Actions (Tiles) --- */
        .section-label {
            font-size: 1rem; color: var(--text-main); font-weight: 700;
            margin-bottom: 25px; padding-right: 5px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-label i { color: var(--primary); font-size: 0.9rem; }

        .tiles-wrapper {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 20px; margin-bottom: 50px;
        }
        .tile {
            background: var(--glass-bg); border: var(--glass-border);
            border-radius: var(--radius-lg); padding: 24px 15px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative; overflow: hidden;
        }
        .tile::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.05), transparent);
            opacity: 0; transition: 0.4s;
        }
        .tile:hover {
            transform: translateY(-6px);
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        .tile:hover::before { opacity: 1; }
        
        .tile-icon {
            font-size: 1.8rem; color: var(--text-muted); transition: 0.4s;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        .tile span { font-size: 0.85rem; font-weight: 500; color: var(--text-muted); transition: 0.3s; }
        
        .tile:hover .tile-icon { transform: scale(1.15); color: #fff; }
        .tile:hover span { color: #fff; }
        
        /* Tile Specifics */
        .t-indigo:hover .tile-icon { color: var(--primary); filter: drop-shadow(0 0 15px rgba(99, 102, 241, 0.5)); }
        .t-sky:hover .tile-icon { color: var(--secondary); filter: drop-shadow(0 0 15px rgba(14, 165, 233, 0.5)); }
        .t-rose:hover .tile-icon { color: var(--accent); filter: drop-shadow(0 0 15px rgba(244, 63, 94, 0.5)); }
        .t-amber:hover .tile-icon { color: var(--gold); filter: drop-shadow(0 0 15px rgba(245, 158, 11, 0.5)); }

        /* --- Stats Grid --- */
        .stats-deck {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px; margin-bottom: 50px;
        }
        .stat-box {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.4) 0%, rgba(15, 23, 42, 0.4) 100%);
            border: var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 28px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 170px; transition: 0.3s;
        }
        .stat-box:hover { transform: translateY(-4px); border-color: rgba(255,255,255,0.1); }
        
        /* Subtle Glow Background */
        .glow-point {
            position: absolute; width: 150px; height: 150px; border-radius: 50%;
            background: radial-gradient(circle, var(--glow-color) 0%, transparent 70%);
            opacity: 0.08; top: -50px; right: -50px; transition: 0.5s;
        }
        .stat-box:hover .glow-point { opacity: 0.15; transform: scale(1.2); }

        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; z-index: 1; margin-bottom: 20px; }
        .icon-sq {
            width: 54px; height: 54px; border-radius: 18px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
            color: var(--text-main);
        }
        
        .stat-content { z-index: 1; }
        .stat-num { font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.1; letter-spacing: -0.03em; margin-bottom: 6px; }
        .stat-desc { font-size: 0.9rem; color: var(--text-muted); font-weight: 400; }

        /* Stat Colors */
        .s-primary { --glow-color: var(--primary); }
        .s-success { --glow-color: #10b981; }
        .s-rose { --glow-color: var(--accent); }
        .s-sky { --glow-color: var(--secondary); }

        /* --- Filters --- */
        .filter-strip {
            background: rgba(15, 23, 42, 0.3); border: 1px dashed rgba(255,255,255,0.1);
            border-radius: var(--radius-lg); padding: 16px;
            display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 40px;
            align-items: center;
        }
        .input-elegant {
            flex: 1; min-width: 220px;
            background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.08);
            color: #fff; padding: 12px 20px; border-radius: 14px;
            font-family: inherit; font-size: 0.9rem; transition: 0.3s;
        }
        .input-elegant:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); background: rgba(0,0,0,0.3); }
        
        .btn-elegant {
            background: var(--primary); color: #fff; border: none;
            padding: 0 28px; height: 48px; border-radius: 14px; font-weight: 600;
            cursor: pointer; transition: 0.3s;
            box-shadow: 0 8px 20px -5px rgba(99, 102, 241, 0.4);
            display: flex; align-items: center; gap: 10px;
        }
        .btn-elegant:hover { transform: translateY(-2px); box-shadow: 0 12px 25px -5px rgba(99, 102, 241, 0.5); filter: brightness(110%); }

        /* --- Charts Area --- */
        .charts-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .chart-card {
            background: var(--glass-bg); border: var(--glass-border);
            border-radius: var(--radius-xl); padding: 30px;
            display: flex; flex-direction: column;
        }
        .chart-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .chart-label { font-size: 1.05rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 12px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { width: 0; padding: 0; }
            .sidebar.show { width: var(--sidebar-expanded); padding: 35px 0; }
            .main-view { margin-right: 0; padding: 30px 20px; }
            .charts-layout { grid-template-columns: 1fr; }
            .mobile-trigger { display: flex; position: fixed; bottom: 25px; left: 25px; z-index: 1100; width: 56px; height: 56px; background: var(--primary); border-radius: 50%; align-items: center; justify-content: center; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.4); font-size: 1.5rem; cursor: pointer; }
        }
        @media (min-width: 1025px) { .mobile-trigger { display: none; } }
        @media (max-width: 480px) {
            .header-bar { flex-direction: column; align-items: flex-start; gap: 20px; }
            .tiles-wrapper { grid-template-columns: repeat(2, 1fr); }
            .stat-num { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="appSidebar">
        <div class="brand-box">
            <i class="fa-solid fa-cube brand-icon"></i>
            <span class="brand-text">Admin Panel</span>
        </div>
        <nav class="nav-menu">
            <a href="index.php" class="nav-link active">
                <i class="fa-solid fa-chart-pie"></i>
                <span class="nav-text">پنل نظارت</span>
            </a>
            <a href="invoice.php" class="nav-link">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span class="nav-text">مدیریت سفارشات</span>
            </a>
            <a href="user.php" class="nav-link">
                <i class="fa-solid fa-users"></i>
                <span class="nav-text">مشترکین</span>
            </a>
            <a href="product.php" class="nav-link">
                <i class="fa-solid fa-box-open"></i>
                <span class="nav-text">محصولات</span>
            </a>
            <a href="server_status.php" class="nav-link">
                <i class="fa-solid fa-server"></i>
                <span class="nav-text">وضعیت سرور</span>
            </a>
            <a href="logout.php" class="nav-link logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="nav-text">خروج امن</span>
            </a>
        </nav>
    </aside>

    <!-- Mobile Trigger -->
    <div class="mobile-trigger" onclick="document.getElementById('appSidebar').classList.toggle('show')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Content -->
    <main class="main-view">
        
        <!-- Header -->
        <header class="header-bar anim">
            <div class="page-title">
                <h1><?php echo $greeting; ?>، مدیر ارشد</h1>
                <p>
                    <i class="<?php echo $greetIcon; ?>" style="color: <?php echo $greetColor; ?>;"></i>
                    خلاصه عملکرد و وضعیت سیستم
                </p>
            </div>
            <div class="date-pill">
                <i class="fa-regular fa-calendar" style="color: var(--secondary);"></i>
                <span><?php echo $currentDate; ?></span>
            </div>
        </header>

        <!-- Quick Actions -->
        <section class="anim d-1">
            <div class="section-label">
                <i class="fa-solid fa-rocket"></i> میانبرهای اجرایی
            </div>
            <div class="tiles-wrapper">
                <a href="invoice.php" class="tile t-indigo">
                    <i class="fa-solid fa-cart-shopping tile-icon"></i>
                    <span>سفارشات</span>
                </a>
                <a href="user.php" class="tile t-rose">
                    <i class="fa-solid fa-user-group tile-icon"></i>
                    <span>کاربران</span>
                </a>
                <a href="inbound.php" class="tile t-sky">
                    <i class="fa-solid fa-sliders tile-icon"></i>
                    <span>کانفیگ</span>
                </a>
                <a href="payment.php" class="tile t-amber">
                    <i class="fa-solid fa-credit-card tile-icon"></i>
                    <span>امور مالی</span>
                </a>
                <a href="server_status.php" class="tile t-sky">
                    <i class="fa-solid fa-memory tile-icon"></i>
                    <span>منابع سرور</span>
                </a>
                <a href="cancelService.php" class="tile t-rose">
                    <i class="fa-solid fa-ban tile-icon"></i>
                    <span>مسدودسازی</span>
                </a>
            </div>
        </section>

        <!-- Stats Overview -->
        <section class="stats-deck anim d-2">
            <!-- Sales -->
            <div class="stat-box s-primary">
                <div class="glow-point"></div>
                <div class="stat-header">
                    <div class="icon-sq"><i class="fa-solid fa-wallet" style="color: #818CF8;"></i></div>
                    <i class="fa-solid fa-arrow-trend-up" style="color: #4ade80;"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-num"><?php echo $formatted_total_sales; ?></div>
                    <div class="stat-desc">مجموع درآمد فروش (تومان)</div>
                </div>
            </div>

            <!-- Orders -->
            <div class="stat-box s-success">
                <div class="glow-point"></div>
                <div class="stat-header">
                    <div class="icon-sq"><i class="fa-solid fa-clipboard-check" style="color: #34d399;"></i></div>
                </div>
                <div class="stat-content">
                    <div class="stat-num"><?php echo number_format($resultcontsell); ?></div>
                    <div class="stat-desc">تراکنش‌های موفق</div>
                </div>
            </div>

            <!-- Users -->
            <div class="stat-box s-rose">
                <div class="glow-point"></div>
                <div class="stat-header">
                    <div class="icon-sq"><i class="fa-solid fa-users" style="color: #f472b6;"></i></div>
                </div>
                <div class="stat-content">
                    <div class="stat-num"><?php echo number_format($resultcount); ?></div>
                    <div class="stat-desc">مشترکین فعال</div>
                </div>
            </div>

            <!-- New Users -->
            <div class="stat-box s-sky">
                <div class="glow-point"></div>
                <div class="stat-header">
                    <div class="icon-sq"><i class="fa-solid fa-user-plus" style="color: #38bdf8;"></i></div>
                    <span style="font-size: 0.8rem; background: rgba(14, 165, 233, 0.2); color: #7dd3fc; padding: 2px 8px; border-radius: 10px;">امروز</span>
                </div>
                <div class="stat-content">
                    <div class="stat-num"><?php echo number_format($resultcountday); ?></div>
                    <div class="stat-desc">ورودی‌های جدید</div>
                </div>
            </div>
        </section>

        <!-- Filter -->
        <form method="get" class="filter-strip anim d-3">
            <input type="text" id="dateRange" class="input-elegant" placeholder="بازه زمانی را انتخاب کنید..." readonly>
            <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
            <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
            
            <select name="status[]" multiple class="input-elegant">
                <?php foreach($statusMapFa as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo in_array($k, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-elegant">
                <i class="fa-solid fa-filter"></i> اعمال فیلتر
            </button>
            <?php if($fromDate || !empty($selectedStatuses)): ?>
                <a href="index.php" class="btn-elegant" style="background: rgba(255,255,255,0.08); width: auto; padding: 0 15px;">
                    <i class="fa-solid fa-refresh"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Charts -->
        <div class="charts-layout anim d-4">
            <!-- Main Chart -->
            <div class="chart-card">
                <div class="chart-top">
                    <div class="chart-label"><i class="fa-solid fa-chart-column" style="color: var(--primary);"></i> تحلیل درآمد</div>
                </div>
                <div style="height: 300px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Side Charts -->
            <div style="display: flex; flex-direction: column; gap: 25px;">
                <div class="chart-card" style="flex: 1;">
                    <div class="chart-top">
                        <div class="chart-label"><i class="fa-solid fa-chart-pie" style="color: var(--accent);"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="height: 180px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card" style="flex: 1;">
                    <div class="chart-top">
                        <div class="chart-label"><i class="fa-solid fa-arrow-trend-up" style="color: var(--secondary);"></i> روند رشد</div>
                    </div>
                    <div style="height: 140px;">
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

        // --- Charts Config (Minimalist & Smooth) ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#64748B';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.03)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';
        Chart.defaults.plugins.tooltip.padding = 14;
        Chart.defaults.plugins.tooltip.cornerRadius = 12;
        Chart.defaults.plugins.tooltip.titleFont = { size: 13 };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 13 };
        Chart.defaults.plugins.tooltip.displayColors = false;

        const salesData = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesAmount); ?> };
        const statusData = { labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($statusData); ?>, colors: <?php echo json_encode($statusColors); ?> };
        const userData = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userCounts); ?> };

        // 1. Sales Chart (Smooth Gradient Bar)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gSales = ctxSales.createLinearGradient(0, 0, 0, 300);
        gSales.addColorStop(0, '#6366f1');
        gSales.addColorStop(1, 'rgba(99, 102, 241, 0.1)');

        new Chart(ctxSales, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'فروش',
                    data: salesData.values,
                    backgroundColor: gSales,
                    borderRadius: 8,
                    hoverBackgroundColor: '#818cf8',
                    barThickness: 24
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.02)' }, ticks: { font: { size: 11 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
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
                responsive: true, maintainAspectRatio: false,
                cutout: '78%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 8, padding: 15, font: { size: 11 }, color: '#94a3b8', usePointStyle: true } }
                }
            }
        });

        // 3. User Chart (Curved Line)
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        const gUser = ctxUser.createLinearGradient(0, 0, 0, 150);
        gUser.addColorStop(0, 'rgba(14, 165, 233, 0.2)');
        gUser.addColorStop(1, 'rgba(14, 165, 233, 0)');

        new Chart(ctxUser, {
            type: 'line',
            data: {
                labels: userData.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userData.values,
                    borderColor: '#0ea5e9',
                    backgroundColor: gUser,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 0,
                    pointHoverRadius: 6
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