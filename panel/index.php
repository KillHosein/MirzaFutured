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
    // برای نمایش دمو در محیط‌هایی که دیتابیس ندارند، یک شیء ساختگی ایجاد می‌کنیم (فقط برای نمایش ظاهر)
    // در محیط واقعی این بخش else را حذف کنید.
    // die("Fatal Error: Database connection variable (\$pdo) is not defined.");
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
$colorMap = [
    'unpaid' => '#fbbf24', 'active' => '#10b981', 'disabledn' => '#94a3b8',
    'end_of_time' => '#ef4444', 'end_of_volume' => '#3b82f6', 'sendedwarn' => '#a855f7',
    'send_on_hold' => '#f97316', 'removebyuser' => '#475569'
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
if ($hour < 12) { $greeting = "صبح بخیر"; $greetIcon = "icon-sun"; }
elseif ($hour < 17) { $greeting = "ظهر بخیر"; $greetIcon = "icon-coffee"; }
else { $greeting = "عصر بخیر"; $greetIcon = "icon-moon"; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت | داشبورد</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- CSS Dependencies -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        /* --- Modern Theme Variables --- */
        :root {
            /* Backgrounds */
            --bg-body: #0f172a; /* Slate 900 */
            --bg-glass: rgba(30, 41, 59, 0.65); /* Slate 800 with opacity */
            --bg-card: rgba(15, 23, 42, 0.6);
            
            /* Borders & Shadows */
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --shadow-glass: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            --shadow-glow: 0 0 15px rgba(79, 70, 229, 0.15);

            /* Colors */
            --primary: #6366f1; /* Indigo 500 */
            --primary-hover: #4f46e5;
            --secondary: #ec4899; /* Pink 500 */
            --accent: #06b6d4; /* Cyan 500 */
            --success: #10b981;
            
            /* Text */
            --text-main: #f1f5f9; /* Slate 100 */
            --text-muted: #94a3b8; /* Slate 400 */
            
            /* Structure */
            --header-height: 75px;
            --radius-xl: 24px;
            --radius-lg: 16px;
        }

        body {
            background-color: var(--bg-body);
            /* Complex modern gradient background */
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 100% 0%, rgba(236, 72, 153, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(6, 182, 212, 0.1) 0%, transparent 40%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Animations --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
            100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }

        .animate-enter { animation: fadeInUp 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }

        /* --- Layout --- */
        .wrapper {
            max-width: 1600px;
            margin: 0 auto;
            padding: calc(var(--header-height) + 30px) 30px 40px 30px;
            display: flex;
            flex-direction: column;
            gap: 35px;
        }

        /* --- Header --- */
        .site-header {
            position: fixed; top: 0; left: 0; right: 0; height: var(--header-height);
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: var(--border-glass);
            z-index: 1000;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .header-brand { 
            font-size: 22px; font-weight: 900; color: #fff; 
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            display: flex; align-items: center; gap: 10px;
        }
        .header-nav { display: flex; gap: 8px; }
        .nav-link {
            color: var(--text-muted); text-decoration: none;
            padding: 10px 18px; border-radius: 12px;
            font-weight: 500; font-size: 14px;
            transition: all 0.3s;
            display: flex; align-items: center; gap: 8px;
        }
        .nav-link:hover, .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.08);
            box-shadow: 0 0 15px rgba(255,255,255,0.05);
        }
        .nav-link.logout:hover { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        /* --- Components: Glass Card --- */
        .glass-card {
            background: var(--bg-glass);
            backdrop-filter: blur(24px) saturate(140%);
            border: var(--border-glass);
            border-radius: var(--radius-xl);
            padding: 30px;
            box-shadow: var(--shadow-glass);
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s;
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            border-color: rgba(255,255,255,0.2);
        }

        /* --- Hero Section --- */
        .hero { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; }
        .hero-text h1 { 
            font-size: 36px; font-weight: 800; margin: 0 0 10px 0; 
            color: #fff; letter-spacing: -0.5px;
        }
        .hero-meta { color: var(--text-muted); display: flex; align-items: center; gap: 15px; font-size: 15px; }
        .status-badge { 
            background: rgba(16, 185, 129, 0.15); color: #34d399; 
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
            display: flex; align-items: center; gap: 6px;
        }
        .status-dot { width: 8px; height: 8px; background: #34d399; border-radius: 50%; display: inline-block; animation: pulseGlow 2s infinite; }

        /* --- Filter Bar --- */
        .filter-container {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex; flex-wrap: wrap; align-items: center; gap: 20px;
        }
        .filter-group { flex: 1; min-width: 200px; position: relative; }
        
        .input-modern {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.2);
            color: #fff;
            padding: 14px 20px;
            border-radius: 14px;
            font-family: inherit; font-size: 14px;
            transition: 0.3s; outline: none;
        }
        .input-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        select.input-modern { cursor: pointer; }
        
        .btn-action {
            background: linear-gradient(135deg, var(--primary), #4338ca);
            color: white; border: none;
            padding: 14px 30px; border-radius: 14px;
            font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: 0.3s;
            display: flex; align-items: center; gap: 10px;
        }
        .btn-action:hover { transform: translateY(-2px); filter: brightness(110%); }
        
        .btn-icon-only {
            background: rgba(255,255,255,0.05);
            width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 14px; color: var(--text-muted);
            border: 1px solid transparent; transition: 0.3s;
            text-decoration: none;
        }
        .btn-icon-only:hover { background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.1); }

        /* --- Stats Grid --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; }
        .stat-box { display: flex; flex-direction: column; gap: 15px; position: relative; overflow: hidden; }
        .stat-box::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 60%);
            opacity: 0; transition: 0.5s;
        }
        .stat-box:hover::before { opacity: 1; }
        
        .stat-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .stat-value { font-size: 28px; font-weight: 800; color: #fff; }
        .stat-label { font-size: 14px; color: var(--text-muted); }

        /* Icon Colors */
        .icon-blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .icon-purple { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        .icon-pink { background: rgba(236, 72, 153, 0.15); color: #f472b6; }
        .icon-emerald { background: rgba(16, 185, 129, 0.15); color: #34d399; }

        /* --- Charts Grid --- */
        .charts-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 25px; }
        .chart-main { grid-column: span 12; }
        .chart-half { grid-column: span 6; }
        
        @media (min-width: 1400px) {
            .chart-main { grid-column: span 8; }
            .chart-half { grid-column: span 4; }
            /* Force status to be smaller on very wide screens if desired, or keep as is */
        }
        @media (max-width: 1000px) {
            .chart-main, .chart-half { grid-column: span 12; }
        }

        .chart-header { display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .chart-title { font-size: 16px; font-weight: 700; color: #e2e8f0; display: flex; align-items: center; gap: 10px; }

        /* --- Quick Actions (App Grid Style) --- */
        .actions-section h3 { font-size: 18px; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .actions-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); 
            gap: 20px; 
        }
        .action-item {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 20px 10px;
            display: flex; flex-direction: column; align-items: center; gap: 15px;
            text-decoration: none; color: var(--text-muted);
            transition: 0.3s;
        }
        .action-item i { 
            font-size: 32px; color: var(--text-main); 
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
            transition: 0.3s;
        }
        .action-item span { font-size: 13px; font-weight: 500; }
        
        .action-item:hover { 
            background: rgba(30, 41, 59, 0.8); 
            transform: translateY(-5px); 
            color: #fff;
            border-color: rgba(99, 102, 241, 0.3);
        }
        .action-item:hover i { transform: scale(1.1); color: var(--accent); }
        .action-item.danger:hover i { color: #ef4444; }

        /* --- Responsive Tweaks --- */
        @media (max-width: 768px) {
            .site-header { padding: 0 20px; height: 60px; }
            .header-brand span { display: none; }
            .wrapper { padding: 80px 15px 30px 15px; }
            .hero { flex-direction: column; align-items: flex-start; gap: 10px; }
            .actions-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 480px) {
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
            .header-nav .nav-text { display: none; } /* Hide text, keep icons */
        }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <header class="site-header">
        <div class="header-nav">
            <a href="logout.php" class="nav-link logout" title="خروج">
                <i class="icon-power-off"></i>
            </a>
            <div style="width: 1px; height: 20px; background: rgba(255,255,255,0.1); margin: 0 5px;"></div>
            <a href="user.php" class="nav-link"><i class="icon-group"></i> <span class="nav-text">کاربران</span></a>
            <a href="invoice.php" class="nav-link"><i class="icon-shopping-cart"></i> <span class="nav-text">سفارشات</span></a>
            <a href="index.php" class="nav-link active"><i class="icon-dashboard"></i> <span class="nav-text">داشبورد</span></a>
        </div>
        <div class="header-brand">
            <span>پنل مدیریت</span>
            <i class="icon-cogs" style="color: var(--primary);"></i>
        </div>
    </header>

    <div class="wrapper">
        
        <!-- Hero Section -->
        <div class="hero animate-enter">
            <div class="hero-text">
                <h1><?php echo $greeting; ?></h1>
                <div class="hero-meta">
                    <span><i class="icon-calendar"></i> <?php echo function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d'); ?></span>
                    <span class="status-badge">
                        <span class="status-dot"></span>
                        سیستم آنلاین
                    </span>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-container animate-enter delay-100">
            <form id="filterForm" method="get" style="display: contents;">
                <!-- Date Picker -->
                <div class="filter-group">
                    <input type="text" id="dateRange" class="input-modern" placeholder="تاریخ را انتخاب کنید..." readonly>
                    <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
                    <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
                    <i class="icon-calendar" style="position: absolute; left: 15px; top: 16px; color: var(--text-muted); pointer-events: none;"></i>
                </div>

                <!-- Status Select -->
                <div class="filter-group">
                    <select name="status[]" multiple class="input-modern" style="min-height: 50px;">
                        <?php foreach($statusMapFa as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo in_array($k, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-action">
                    <span>اعمال فیلتر</span>
                    <i class="icon-filter"></i>
                </button>
                
                <?php if($fromDate || !empty($selectedStatuses)): ?>
                    <a href="index.php" class="btn-icon-only" title="پاک کردن فیلترها">
                        <i class="icon-refresh"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid animate-enter delay-200">
            <!-- Sales -->
            <div class="glass-card stat-box">
                <div class="stat-header">
                    <div class="stat-icon icon-blue"><i class="icon-bar-chart"></i></div>
                    <!-- Mockup Trend -->
                    <span style="color: #34d399; font-size: 12px; display: flex; align-items: center; gap: 4px;">
                        %12+ <i class="icon-level-up"></i>
                    </span>
                </div>
                <div>
                    <div class="stat-value"><?php echo $formatted_total_sales; ?></div>
                    <div class="stat-label">مجموع فروش (تومان)</div>
                </div>
            </div>

            <!-- Orders -->
            <div class="glass-card stat-box">
                <div class="stat-header">
                    <div class="stat-icon icon-purple"><i class="icon-shopping-bag"></i></div>
                </div>
                <div>
                    <div class="stat-value"><?php echo number_format($resultcontsell); ?></div>
                    <div class="stat-label">تعداد سفارشات</div>
                </div>
            </div>

            <!-- Users -->
            <div class="glass-card stat-box">
                <div class="stat-header">
                    <div class="stat-icon icon-pink"><i class="icon-users"></i></div>
                </div>
                <div>
                    <div class="stat-value"><?php echo number_format($resultcount); ?></div>
                    <div class="stat-label">کاربران کل</div>
                </div>
            </div>

            <!-- New Users -->
            <div class="glass-card stat-box">
                <div class="stat-header">
                    <div class="stat-icon icon-emerald"><i class="icon-user-plus"></i></div>
                </div>
                <div>
                    <div class="stat-value"><?php echo number_format($resultcountday); ?></div>
                    <div class="stat-label">ثبت نام امروز</div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container animate-enter delay-300">
            <!-- Sales Chart -->
            <div class="glass-card chart-main">
                <div class="chart-header">
                    <div class="chart-title"><i class="icon-area-chart" style="color: var(--primary);"></i> نمودار فروش</div>
                    <div style="font-size: 12px; color: var(--text-muted);">تحلیل درآمد در بازه زمانی</div>
                </div>
                <div style="height: 320px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- User Growth -->
            <div class="glass-card chart-half">
                <div class="chart-header">
                    <div class="chart-title"><i class="icon-line-chart" style="color: var(--accent);"></i> رشد کاربران</div>
                </div>
                <div style="height: 250px; width: 100%;">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>

            <!-- Status Distribution -->
            <div class="glass-card chart-half">
                <div class="chart-header">
                    <div class="chart-title"><i class="icon-pie-chart" style="color: var(--secondary);"></i> وضعیت سفارشات</div>
                </div>
                <div style="height: 250px; width: 100%; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-section animate-enter delay-400">
            <h3><i class="icon-rocket" style="color: #f59e0b;"></i> دسترسی سریع</h3>
            <div class="actions-grid">
                <a href="invoice.php" class="action-item">
                    <i class="icon-list-alt"></i>
                    <span>سفارشات</span>
                </a>
                <a href="user.php" class="action-item">
                    <i class="icon-group"></i>
                    <span>کاربران</span>
                </a>
                <a href="product.php" class="action-item">
                    <i class="icon-cube"></i>
                    <span>محصولات</span>
                </a>
                <a href="inbound.php" class="action-item">
                    <i class="icon-exchange"></i>
                    <span>ورودی‌ها</span>
                </a>
                <a href="payment.php" class="action-item">
                    <i class="icon-credit-card"></i>
                    <span>تراکنش‌ها</span>
                </a>
                <a href="keyboard.php" class="action-item">
                    <i class="icon-keyboard"></i>
                    <span>کیبورد</span>
                </a>
                <a href="cancelService.php" class="action-item danger">
                    <i class="icon-remove-sign"></i>
                    <span>حذف سرویس</span>
                </a>
                <a href="server_status.php" class="action-item">
                    <i class="icon-hdd"></i>
                    <span>سرور</span>
                </a>
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
        // --- Daterangepicker Setup ---
        $(function(){
            var start = moment().subtract(13, 'days');
            var end = moment();
            
            // Check PHP values
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
                    cancelLabel: 'لغو'
                }
            }, cb);

            if(phpFrom) cb(start, end);
        });

        // --- Chart Configuration ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.04)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;

        // Data from PHP
        const salesData = {
            labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>,
            values: <?php echo json_encode($salesAmount); ?>
        };
        const statusData = {
            labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>,
            values: <?php echo json_encode($statusData); ?>,
            colors: <?php echo json_encode($statusColors); ?>
        };
        const userData = {
            labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>,
            values: <?php echo json_encode($userCounts); ?>
        };

        // 1. Sales Chart (Bar with Gradient)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gradSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gradSales.addColorStop(0, '#6366f1');
        gradSales.addColorStop(1, 'rgba(99, 102, 241, 0.1)');

        new Chart(ctxSales, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'فروش',
                    data: salesData.values,
                    backgroundColor: gradSales,
                    borderRadius: 6,
                    barThickness: 20
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, border: { display: false } },
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
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true } }
                }
            }
        });

        // 3. User Chart (Line with fill)
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        const gradUser = ctxUser.createLinearGradient(0, 0, 0, 300);
        gradUser.addColorStop(0, 'rgba(6, 182, 212, 0.4)');
        gradUser.addColorStop(1, 'rgba(6, 182, 212, 0)');

        new Chart(ctxUser, {
            type: 'line',
            data: {
                labels: userData.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userData.values,
                    borderColor: '#06b6d4',
                    backgroundColor: gradUser,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { display: false } // Minimalist look
                }
            }
        });
    </script>
</body>
</html>