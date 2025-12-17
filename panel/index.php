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
    // در محیط واقعی اگر دیتابیس وصل نباشد، اینجا مدیریت خطا صورت می‌گیرد
}

// --- بخش منطق (Logic) ---
// تنظیم مقادیر پیش‌فرض
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
        // die("Database Error");
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
// پالت رنگی نئونی (Cyberpunk Palette)
$colorMap = [
    'unpaid' => '#F59E0B',      // Amber
    'active' => '#00E676',      // Neon Green
    'disabledn' => '#94A3B8',   // Slate
    'end_of_time' => '#FF1744', // Neon Red
    'end_of_volume' => '#2979FF', // Electric Blue
    'sendedwarn' => '#D500F9',  // Neon Purple
    'send_on_hold' => '#FF9100', // Deep Orange
    'removebyuser' => '#37474F'  // Dark Grey
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
if ($hour < 12) { $greeting = "صبح‌بخیر"; $greetIcon = "fa-sun"; $greetColor = "#FFD600"; }
elseif ($hour < 17) { $greeting = "ظهر‌بخیر"; $greetIcon = "fa-cloud-sun"; $greetColor = "#FF6D00"; }
else { $greeting = "عصر‌بخیر"; $greetIcon = "fa-moon"; $greetColor = "#651FFF"; }

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
            /* Theme Colors: Deep Space & Neon */
            --bg-body: #050509;       /* Blackish Blue */
            --bg-glass: rgba(20, 20, 30, 0.6); /* Translucent dark */
            --bg-card: rgba(255, 255, 255, 0.03); /* Ultra subtle white tint */
            --bg-card-hover: rgba(255, 255, 255, 0.07);
            
            /* Accents */
            --primary: #6366F1;       /* Indigo */
            --primary-glow: rgba(99, 102, 241, 0.5);
            --accent-cyan: #00E5FF;
            --accent-pink: #F50057;
            --accent-gold: #FFD600;
            
            --text-main: #FFFFFF;
            --text-muted: #94A3B8;
            
            /* Borders & Shadows */
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --border-active: 1px solid rgba(99, 102, 241, 0.5);
            --shadow-card: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            --shadow-glow: 0 0 20px rgba(99, 102, 241, 0.15);
            
            --radius-lg: 24px;
            --radius-md: 16px;
        }

        /* --- Base Setup --- */
        * { box-sizing: border-box; outline: none; }
        
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 85% 15%, rgba(245, 0, 87, 0.06) 0%, transparent 45%),
                radial-gradient(circle at 50% 90%, rgba(0, 229, 255, 0.06) 0%, transparent 50%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* --- Scrollbar --- */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }

        /* --- Navigation (Floating Dock) --- */
        .nav-dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            z-index: 1000; display: flex; justify-content: center;
            pointer-events: none; /* Let clicks pass through around the dock */
        }
        
        .nav-dock {
            background: rgba(15, 20, 30, 0.85);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: var(--border-glass);
            padding: 10px 20px;
            border-radius: 24px;
            display: flex; gap: 10px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            pointer-events: auto;
            transform: translateY(0); transition: 0.3s;
        }
        
        .dock-item {
            position: relative;
            color: var(--text-muted);
            width: 50px; height: 50px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 16px;
            text-decoration: none;
            font-size: 1.3rem;
            transition: all 0.25s cubic-bezier(0.2, 0.8, 0.2, 1.2);
        }
        
        .dock-item:hover, .dock-item.active {
            color: #fff;
            background: rgba(255,255,255,0.1);
            transform: translateY(-8px) scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .dock-item.active { background: var(--primary); box-shadow: 0 5px 20px var(--primary-glow); }

        .dock-tooltip {
            position: absolute; top: -40px; left: 50%; transform: translateX(-50%) scale(0.8);
            background: rgba(0,0,0,0.8); padding: 5px 12px; border-radius: 8px;
            font-size: 0.75rem; color: #fff; opacity: 0; transition: 0.2s; pointer-events: none;
            white-space: nowrap;
        }
        .dock-item:hover .dock-tooltip { opacity: 1; transform: translateX(-50%) scale(1); top: -45px; }

        /* --- Layout --- */
        .main-wrapper {
            max-width: 1400px; margin: 0 auto;
            padding: 40px 25px 120px 25px; /* Bottom padding for dock */
        }

        /* --- Header Section --- */
        .header-hero {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px; flex-wrap: wrap; gap: 20px;
        }
        .greeting-box h1 {
            font-size: 2.5rem; font-weight: 900; margin: 0;
            background: linear-gradient(135deg, #FFFFFF 0%, #94A3B8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }
        .greeting-sub { color: var(--text-muted); font-size: 1rem; margin-top: 5px; display: flex; align-items: center; gap: 8px; }
        
        .date-pill {
            background: rgba(255,255,255,0.05); border: var(--border-glass);
            padding: 8px 16px; border-radius: 50px;
            display: flex; align-items: center; gap: 10px;
            font-size: 0.9rem; color: var(--text-main);
        }

        /* --- Quick Actions (Tiles) --- */
        .section-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; opacity: 0.9; }
        
        .tiles-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 20px; margin-bottom: 50px;
        }
        
        .action-tile {
            background: var(--bg-card);
            border: var(--border-glass);
            border-radius: var(--radius-md);
            padding: 20px 15px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px;
            text-decoration: none; color: var(--text-muted);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative; overflow: hidden;
        }
        
        .action-tile::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at center, rgba(255,255,255,0.05) 0%, transparent 70%);
            opacity: 0; transition: 0.3s;
        }
        
        .tile-icon {
            font-size: 2rem; transition: 0.3s;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
        }
        .tile-label { font-size: 0.9rem; font-weight: 500; transition: 0.3s; }
        
        .action-tile:hover {
            transform: translateY(-5px);
            background: var(--bg-card-hover);
            border-color: rgba(255,255,255,0.2);
            color: #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .action-tile:hover::before { opacity: 1; }
        .action-tile:hover .tile-icon { transform: scale(1.15); }
        
        /* Custom Colors for Tiles */
        .t-blue:hover .tile-icon { color: #2979FF; filter: drop-shadow(0 0 10px rgba(41, 121, 255, 0.6)); }
        .t-pink:hover .tile-icon { color: #F50057; filter: drop-shadow(0 0 10px rgba(245, 0, 87, 0.6)); }
        .t-cyan:hover .tile-icon { color: #00E5FF; filter: drop-shadow(0 0 10px rgba(0, 229, 255, 0.6)); }
        .t-gold:hover .tile-icon { color: #FFD600; filter: drop-shadow(0 0 10px rgba(255, 214, 0, 0.6)); }
        .t-red:hover .tile-icon { color: #FF1744; filter: drop-shadow(0 0 10px rgba(255, 23, 68, 0.6)); }

        /* --- Stats Cards --- */
        .stats-container {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px; margin-bottom: 50px;
        }
        
        .glass-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px;
            position: relative; overflow: hidden;
            transition: 0.3s;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 160px;
        }
        
        .glass-card:hover {
            border-color: rgba(255,255,255,0.15);
            background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%);
            transform: translateY(-5px);
            box-shadow: var(--shadow-card);
        }
        
        .stat-top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .stat-icon-wrapper {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .stat-badge {
            font-size: 0.75rem; padding: 4px 10px; border-radius: 20px;
            font-weight: 700; letter-spacing: 0.5px;
        }
        
        .stat-value-big { font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 5px; }
        .stat-label-sub { color: var(--text-muted); font-size: 0.95rem; }

        /* Specific Card Styles */
        .card-indigo .stat-icon-wrapper { background: rgba(99, 102, 241, 0.15); color: #818CF8; }
        .card-indigo:hover { box-shadow: 0 10px 30px rgba(99, 102, 241, 0.15); border-top: 1px solid rgba(99, 102, 241, 0.3); }
        
        .card-green .stat-icon-wrapper { background: rgba(0, 230, 118, 0.15); color: #69F0AE; }
        .card-green:hover { box-shadow: 0 10px 30px rgba(0, 230, 118, 0.15); border-top: 1px solid rgba(0, 230, 118, 0.3); }
        
        .card-pink .stat-icon-wrapper { background: rgba(245, 0, 87, 0.15); color: #FF4081; }
        .card-pink:hover { box-shadow: 0 10px 30px rgba(245, 0, 87, 0.15); border-top: 1px solid rgba(245, 0, 87, 0.3); }
        
        .card-cyan .stat-icon-wrapper { background: rgba(0, 229, 255, 0.15); color: #18FFFF; }
        .card-cyan:hover { box-shadow: 0 10px 30px rgba(0, 229, 255, 0.15); border-top: 1px solid rgba(0, 229, 255, 0.3); }

        /* --- Filter Section --- */
        .filter-panel {
            background: rgba(255,255,255,0.02);
            border: 1px dashed rgba(255,255,255,0.1);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex; flex-wrap: wrap; align-items: center; gap: 20px;
            margin-bottom: 40px;
        }
        
        .input-group-glass {
            position: relative; flex: 1; min-width: 220px;
        }
        .input-glass {
            width: 100%; background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 14px 20px; border-radius: 12px;
            font-family: inherit; transition: 0.3s;
        }
        .input-glass:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        
        .btn-filter-submit {
            background: var(--primary); color: white; border: none;
            padding: 14px 30px; border-radius: 12px; font-weight: 700;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4); cursor: pointer;
            transition: 0.3s;
        }
        .btn-filter-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99, 102, 241, 0.6); }

        /* --- Charts Grid --- */
        .charts-wrapper {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px;
        }
        
        .chart-box {
            background: var(--bg-card);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 25px;
        }
        .chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .chart-head h3 { margin: 0; font-size: 1.1rem; color: #fff; font-weight: 600; }

        /* --- Animations --- */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim { opacity: 0; animation: fadeUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
        .d-1 { animation-delay: 0.1s; }
        .d-2 { animation-delay: 0.2s; }
        .d-3 { animation-delay: 0.3s; }
        .d-4 { animation-delay: 0.4s; }

        /* --- Responsive --- */
        @media (max-width: 1024px) {
            .charts-wrapper { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .nav-dock-container { top: auto; bottom: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(20px); border-top: var(--border-glass); padding: 10px; }
            .nav-dock { background: transparent; border: none; box-shadow: none; width: 100%; justify-content: space-around; padding: 0; border-radius: 0; }
            .dock-tooltip { display: none; }
            .main-wrapper { padding-bottom: 90px; }
            .header-hero { flex-direction: column; align-items: flex-start; }
            .tiles-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .filter-panel { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 480px) {
            .tiles-grid { grid-template-columns: repeat(3, 1fr); }
            .stat-value-big { font-size: 1.8rem; }
            .dock-item { font-size: 1.1rem; width: 40px; height: 40px; }
        }
    </style>
</head>
<body>

    <!-- Main Content -->
    <div class="main-wrapper">
        
        <!-- Header -->
        <header class="header-hero anim">
            <div class="greeting-box">
                <div class="greeting-sub">
                    <i class="<?php echo $greetIcon; ?>" style="color: <?php echo $greetColor; ?>;"></i>
                    <?php echo $greeting; ?>
                </div>
                <h1>داشبورد مدیریت</h1>
            </div>
            <div class="date-pill">
                <i class="fa-regular fa-calendar" style="color: var(--accent-cyan);"></i>
                <span><?php echo $currentDate; ?></span>
            </div>
        </header>

        <!-- Quick Actions Grid -->
        <div class="anim d-1">
            <div class="section-title"><i class="fa-solid fa-layer-group" style="color: var(--accent-cyan);"></i> دسترسی سریع</div>
            <div class="tiles-grid">
                <a href="invoice.php" class="action-tile t-blue">
                    <i class="fa-solid fa-cart-shopping tile-icon"></i>
                    <span class="tile-label">سفارشات</span>
                </a>
                <a href="user.php" class="action-tile t-pink">
                    <i class="fa-solid fa-users tile-icon"></i>
                    <span class="tile-label">کاربران</span>
                </a>
                <a href="product.php" class="action-tile t-cyan">
                    <i class="fa-solid fa-box-open tile-icon"></i>
                    <span class="tile-label">محصولات</span>
                </a>
                <a href="inbound.php" class="action-tile t-gold">
                    <i class="fa-solid fa-server tile-icon"></i>
                    <span class="tile-label">کانفیگ</span>
                </a>
                <a href="payment.php" class="action-tile t-blue">
                    <i class="fa-solid fa-credit-card tile-icon"></i>
                    <span class="tile-label">مالی</span>
                </a>
                <a href="server_status.php" class="action-tile t-pink">
                    <i class="fa-solid fa-microchip tile-icon"></i>
                    <span class="tile-label">سرور</span>
                </a>
                <a href="cancelService.php" class="action-tile t-red">
                    <i class="fa-solid fa-ban tile-icon"></i>
                    <span class="tile-label">مسدودی</span>
                </a>
            </div>
        </div>

        <!-- Filter -->
        <form method="get" class="filter-panel anim d-2">
            <div class="input-group-glass">
                <i class="fa-regular fa-calendar-alt" style="position: absolute; right: 15px; top: 16px; color: var(--text-muted);"></i>
                <input type="text" id="dateRange" class="input-glass" placeholder="انتخاب تاریخ..." readonly style="padding-right: 45px;">
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
            </div>
            <div class="input-group-glass">
                <select name="status[]" multiple class="input-glass" style="height: 50px;">
                    <?php foreach($statusMapFa as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter-submit">اعمال فیلتر</button>
            <?php if($fromDate || !empty($selectedStatuses)): ?>
                <a href="index.php" class="dock-item" style="width: 50px; background: rgba(255,255,255,0.05); color: #fff;"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </form>

        <!-- Stats Cards -->
        <div class="stats-container anim d-3">
            <!-- Sales -->
            <div class="glass-card card-indigo">
                <div class="stat-top-row">
                    <div class="stat-icon-wrapper"><i class="fa-solid fa-coins"></i></div>
                    <span class="stat-badge" style="background: rgba(99, 102, 241, 0.2); color: #818CF8;">درآمد</span>
                </div>
                <div>
                    <div class="stat-value-big"><?php echo $formatted_total_sales; ?></div>
                    <span class="stat-label-sub">مجموع فروش (تومان)</span>
                </div>
            </div>

            <!-- Orders -->
            <div class="glass-card card-green">
                <div class="stat-top-row">
                    <div class="stat-icon-wrapper"><i class="fa-solid fa-receipt"></i></div>
                    <span class="stat-badge" style="background: rgba(0, 230, 118, 0.2); color: #69F0AE;">فاکتور</span>
                </div>
                <div>
                    <div class="stat-value-big"><?php echo number_format($resultcontsell); ?></div>
                    <span class="stat-label-sub">تعداد فروش موفق</span>
                </div>
            </div>

            <!-- Users -->
            <div class="glass-card card-pink">
                <div class="stat-top-row">
                    <div class="stat-icon-wrapper"><i class="fa-solid fa-users"></i></div>
                    <span class="stat-badge" style="background: rgba(245, 0, 87, 0.2); color: #FF4081;">کاربران</span>
                </div>
                <div>
                    <div class="stat-value-big"><?php echo number_format($resultcount); ?></div>
                    <span class="stat-label-sub">کل کاربران ثبت شده</span>
                </div>
            </div>

            <!-- New Users -->
            <div class="glass-card card-cyan">
                <div class="stat-top-row">
                    <div class="stat-icon-wrapper"><i class="fa-solid fa-user-plus"></i></div>
                    <span class="stat-badge" style="background: rgba(0, 229, 255, 0.2); color: #18FFFF;">امروز</span>
                </div>
                <div>
                    <div class="stat-value-big"><?php echo number_format($resultcountday); ?></div>
                    <span class="stat-label-sub">جذب کاربر جدید</span>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-wrapper anim d-4">
            <div class="chart-box">
                <div class="chart-head">
                    <h3><i class="fa-solid fa-chart-area" style="color: var(--primary);"></i> نمودار فروش</h3>
                </div>
                <div style="height: 320px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="chart-box">
                <div class="chart-head">
                    <h3><i class="fa-solid fa-chart-pie" style="color: var(--accent-pink);"></i> وضعیت‌ها</h3>
                </div>
                <div style="height: 300px; width: 100%; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="chart-box" style="grid-column: 1 / -1;">
                <div class="chart-head">
                    <h3><i class="fa-solid fa-arrow-trend-up" style="color: var(--accent-cyan);"></i> روند جذب کاربر</h3>
                </div>
                <div style="height: 280px; width: 100%;">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Floating Dock Nav -->
    <div class="nav-dock-container anim">
        <nav class="nav-dock">
            <a href="index.php" class="dock-item active">
                <i class="fa-solid fa-house"></i>
                <span class="dock-tooltip">خانه</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <i class="fa-solid fa-file-invoice"></i>
                <span class="dock-tooltip">سفارشات</span>
            </a>
            <a href="user.php" class="dock-item">
                <i class="fa-solid fa-users"></i>
                <span class="dock-tooltip">کاربران</span>
            </a>
            <a href="server_status.php" class="dock-item">
                <i class="fa-solid fa-server"></i>
                <span class="dock-tooltip">سرور</span>
            </a>
            <a href="logout.php" class="dock-item" style="color: #FF1744;">
                <i class="fa-solid fa-power-off"></i>
                <span class="dock-tooltip">خروج</span>
            </a>
        </nav>
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

        // --- Chart Configurations (Modern & Glow) ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#64748B';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(5, 5, 9, 0.95)';
        Chart.defaults.plugins.tooltip.padding = 15;
        Chart.defaults.plugins.tooltip.cornerRadius = 10;
        Chart.defaults.plugins.tooltip.displayColors = false;

        const salesData = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesAmount); ?> };
        const statusData = { labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($statusData); ?>, colors: <?php echo json_encode($statusColors); ?> };
        const userData = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userCounts); ?> };

        // 1. Sales Chart (Gradient Glow)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gSales.addColorStop(0, '#6366F1');
        gSales.addColorStop(1, 'rgba(99, 102, 241, 0.05)');

        new Chart(ctxSales, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'فروش',
                    data: salesData.values,
                    backgroundColor: gSales,
                    borderRadius: 8,
                    hoverBackgroundColor: '#818CF8',
                    barThickness: 25
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { callback: v => v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Status Chart (Neon Doughnut)
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
                cutout: '85%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, padding: 15, font: { size: 12 }, color: '#94A3B8' } }
                }
            }
        });

        // 3. User Chart (Smooth Line)
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        const gUser = ctxUser.createLinearGradient(0, 0, 0, 300);
        gUser.addColorStop(0, 'rgba(0, 229, 255, 0.3)');
        gUser.addColorStop(1, 'rgba(0, 229, 255, 0)');

        new Chart(ctxUser, {
            type: 'line',
            data: {
                labels: userData.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userData.values,
                    borderColor: '#00E5FF',
                    backgroundColor: gUser,
                    fill: true,
                    tension: 0.45,
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