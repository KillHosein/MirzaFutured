<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';

// --- Logic Section (Unchanged) ---
$datefirstday = time() - 86400;
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];
if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// Check Admin
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);
if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    return;
}

// Filter Logic
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
    $ph = [];
    foreach($selectedStatuses as $i => $st){
        $k = ":st$i";
        $ph[] = $k;
        $invoiceParams[$k] = $st;
    }
    $invoiceWhere[] = "status IN (".implode(',', $ph).")";
}else{
    $invoiceWhere[] = "(status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold')";
}

$invoiceWhereSql = implode(' AND ', $invoiceWhere);

// Total Sales Amount
$query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql");
$query->execute($invoiceParams);
$subinvoice = $query->fetch(PDO::FETCH_ASSOC);

// User Counts
$query = $pdo->prepare("SELECT * FROM user");
$query->execute();
$resultcount = $query->rowCount();

$time = strtotime(date('Y/m/d'));
$stmt = $pdo->prepare("SELECT * FROM user WHERE register > :time_register AND register != 'none'");
$stmt->bindParam(':time_register', $datefirstday);
$stmt->execute();
$resultcountday = $stmt->rowCount();

// Sales Count
$query = $pdo->prepare("SELECT  * FROM invoice WHERE $invoiceWhereSql");
$query->execute($invoiceParams);
$resultcontsell = $query->rowCount();

$subinvoice['SUM(price_product)'] = number_format($subinvoice['SUM(price_product)']);

// Chart Data: Sales
$salesData = [];
$grouped_data = [];
$max_amount = 1;

if($resultcontsell != 0){
    $query = $pdo->prepare("SELECT time_sell,price_product FROM invoice WHERE $invoiceWhereSql ORDER BY time_sell DESC;");
    $query->execute($invoiceParams);
    $salesData = $query->fetchAll();

    foreach ($salesData as $sell){
        if(count($grouped_data) > 15) break;
        if(!is_numeric($sell['time_sell'])) continue;
        
        $time = date('Y/m/d',$sell['time_sell']);
        $price = (int)$sell['price_product'];
        
        if (!isset($grouped_data[$time])) {
            $grouped_data[$time] = ['total_amount' => 0, 'order_count' => 0];
        }
        $grouped_data[$time]['total_amount'] += $price;
        $grouped_data[$time]['order_count'] += 1;
    }
    $max_amount = max(array_map(function($info) { return $info['total_amount']; }, $grouped_data)) ?: 1;
}

// Chart Data: Status
$statusLabels = [];
$statusData = [];
$statusColors = [];

$stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
$stmt->execute($invoiceParams);
$statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    'unpaid' => '#fbbf24',     // Amber
    'active' => '#34d399',     // Emerald
    'disabledn' => '#9ca3af',  // Gray
    'end_of_time' => '#f87171',// Red
    'end_of_volume' => '#60a5fa', // Blue
    'sendedwarn' => '#a78bfa', // Violet
    'send_on_hold' => '#fb923c', // Orange
    'removebyuser' => '#cbd5e1' // Light Gray
];

foreach($statusRows as $r){
    $k = $r['status'];
    $statusLabels[] = isset($statusMapFa[$k]) ? $statusMapFa[$k] : $k;
    $statusData[] = (int)$r['cnt'];
    $statusColors[] = isset($colorMap[$k]) ? $colorMap[$k] : '#999999';
}

// Chart Data: New Users Trend
$daysBack = 14;
$userStart = ($fromDate && strtotime($fromDate)) ? strtotime($fromDate) : (strtotime(date('Y/m/d')) - ($daysBack-1)*86400);
$userEnd = ($toDate && strtotime($toDate)) ? strtotime($toDate.' 23:59:59') : strtotime(date('Y/m/d'));
$daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);

$stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register BETWEEN :ustart AND :uend");
$stmt->bindParam(':ustart',$userStart,PDO::PARAM_INT);
$stmt->bindParam(':uend',$userEnd,PDO::PARAM_INT);
$stmt->execute();
$regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userLabels = [];
$userCounts = [];
$indexByDate = [];

for($i=0;$i<$daysBack;$i++){
    $d = strtotime(date('Y/m/d', $userStart + $i*86400));
    $key = date('Y/m/d',$d);
    $indexByDate[$key] = count($userLabels);
    $userLabels[] = jdate('Y/m/d',$d);
    $userCounts[] = 0;
}

foreach($regRows as $row){
    if(!is_numeric($row['register'])) continue;
    $key = date('Y/m/d', (int)$row['register']);
    if(isset($indexByDate[$key])){
        $userCounts[$indexByDate[$key]]++;
    }
}

// Time Greeting Logic
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
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
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
        }

        body {
            background-color: var(--bg-body);
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

        /* --- Layout --- */
        #container { width: 100%; height: 100%; }
        #main-content { margin-right: 0px; padding-top: 60px; transition: all 0.3s; }
        .wrapper { padding: 25px; display: flex; flex-direction: column; gap: 30px; max-width: 1600px; margin: 0 auto; }

        /* --- Animations --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-enter { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        /* --- Glass Cards --- */
        .modern-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-top: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .modern-card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.03) 0%, transparent 100%);
            pointer-events: none;
        }
        .modern-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.35);
            border-color: rgba(255,255,255,0.2);
        }

        /* --- Hero Section --- */
        .hero-banner {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 10px;
        }
        .hero-title h1 {
            font-size: 28px; font-weight: 800;
            background: linear-gradient(to right, #fff, #cbd5e1);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .hero-subtitle { font-size: 15px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }

        /* --- Filter Bar --- */
        .filter-bar {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 12px 20px;
            display: flex; flex-wrap: wrap; align-items: center; gap: 15px;
            justify-content: space-between;
        }
        .filter-inputs { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        
        .input-glass {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid #334155;
            color: #fff;
            border-radius: 10px;
            padding: 10px 14px;
            font-family: var(--font-main);
            outline: none; transition: 0.2s;
            min-width: 180px;
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
        .btn-glass:hover, .btn-glass.active { background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.2); }

        /* --- Stats --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
        .stat-card { display: flex; align-items: center; gap: 20px; }
        .stat-icon-wrapper {
            width: 64px; height: 64px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            box-shadow: inset 0 0 12px rgba(255,255,255,0.1);
        }
        .stat-content h3 { font-size: 26px; font-weight: 800; margin: 0 0 4px 0; color: #fff; letter-spacing: -0.5px; }
        .stat-content span { font-size: 14px; color: var(--text-muted); font-weight: 500; }
        
        .icon-grad-1 { background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(59,130,246,0.05)); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2); }
        .icon-grad-2 { background: linear-gradient(135deg, rgba(168,85,247,0.2), rgba(168,85,247,0.05)); color: #c084fc; border: 1px solid rgba(168,85,247,0.2); }
        .icon-grad-3 { background: linear-gradient(135deg, rgba(249,115,22,0.2), rgba(249,115,22,0.05)); color: #fb923c; border: 1px solid rgba(249,115,22,0.2); }
        .icon-grad-4 { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.05)); color: #34d399; border: 1px solid rgba(16,185,129,0.2); }

        /* --- Charts --- */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        @media (max-width: 1024px) { .charts-grid { grid-template-columns: 1fr; } }
        
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .chart-title { font-size: 16px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
        .chart-title i { color: var(--accent); }

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
        .action-btn span { font-weight: 600; font-size: 15px; transition: 0.3s; }
        
        .action-btn:hover { transform: translateY(-5px); background: linear-gradient(145deg, rgba(40,55,80,0.7), rgba(20,30,50,0.7)); border-color: rgba(99,102,241,0.3); }
        .action-btn:hover i { transform: scale(1.1); color: var(--accent); opacity: 1; }
        .action-btn:hover span { color: #fff; }
        .action-btn.danger:hover i { color: var(--secondary); }
        .action-btn.danger:hover { border-color: rgba(236, 72, 153, 0.3); }

        /* --- Checkbox --- */
        .custom-check { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted); font-size: 14px; padding: 5px 10px; border-radius: 8px; transition: 0.2s; }
        .custom-check:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .custom-check input { accent-color: var(--primary); }

    </style>
</head>

<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <section class="wrapper">
            
            <!-- Hero Section -->
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
                <!-- Time Range Presets for Desktop -->
                <div class="btn-group hidden-xs" style="background: rgba(0,0,0,0.2); padding: 4px; border-radius: 12px;">
                    <button class="btn-glass" id="preset7d">هفته اخیر</button>
                    <button class="btn-glass" id="presetMonth">این ماه</button>
                    <button class="btn-glass" id="presetYear">امسال</button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar animate-enter delay-1">
                <form class="filter-inputs" method="get" id="dashboardFilterForm" style="flex: 1;">
                    <!-- Date -->
                    <div style="position: relative;">
                        <input type="text" id="rangePicker" class="input-glass" placeholder="انتخاب تاریخ..." style="padding-right: 35px; text-align: right;">
                        <i class="icon-calendar" style="position: absolute; right: 12px; top: 12px; color: var(--text-muted); pointer-events: none;"></i>
                    </div>
                    <input type="hidden" name="from" id="rangeFrom" value="<?php echo htmlspecialchars($fromDate ?? '', ENT_QUOTES); ?>">
                    <input type="hidden" name="to" id="rangeTo" value="<?php echo htmlspecialchars($toDate ?? '', ENT_QUOTES); ?>">

                    <!-- Status -->
                    <select name="status[]" multiple class="input-glass" style="height: 42px;">
                        <?php foreach($statusMapFa as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo in_array($sk, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-gradient">
                        <i class="icon-filter"></i> 
                        <span>فیلتر کن</span>
                    </button>
                    
                    <?php if($fromDate || !empty($selectedStatuses)): ?>
                    <a href="index.php" class="btn-glass" title="حذف فیلترها" style="display: flex; align-items: center; justify-content: center;">
                        <i class="icon-refresh"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid animate-enter delay-2">
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-1"><i class="icon-bar-chart"></i></div>
                    <div class="stat-content">
                        <h3><?php echo $subinvoice['SUM(price_product)']; ?></h3>
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

            <!-- Charts Section -->
            <div class="charts-grid animate-enter delay-3" id="chartsArea">
                <!-- Sales Chart -->
                <?php if($resultcontsell != 0): ?>
                <div class="modern-card" style="grid-column: span 2;" v-show="show.sales" id="cardSales">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-graph"></i> روند فروش روزانه</span>
                    </div>
                    <div style="height: 320px; width: 100%;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Donut Chart -->
                <div class="modern-card" v-show="show.status" id="cardStatus">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-pie-chart"></i> وضعیت سفارشات</span>
                    </div>
                    <div style="height: 260px; display: flex; justify-content: center; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Line Chart -->
                <div class="modern-card" v-show="show.users" id="cardUsers">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-user-md"></i> نرخ جذب کاربر</span>
                    </div>
                    <div style="height: 260px; width: 100%;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
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

            <!-- Dashboard Preferences -->
            <div class="modern-card animate-enter delay-4" id="dashPrefs" style="margin-top: 10px; padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <span class="text-muted" style="font-size: 13px;"><i class="icon-cogs"></i> شخصی‌سازی داشبورد:</span>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <label class="custom-check"><input type="checkbox" v-model="show.sales"> نمودار فروش</label>
                    <label class="custom-check"><input type="checkbox" v-model="show.status"> وضعیت‌ها</label>
                    <label class="custom-check"><input type="checkbox" v-model="show.users"> کاربران جدید</label>
                </div>
            </div>

        </section>
    </section>
</section>

<!-- Scripts -->
<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.scrollTo.min.js"></script>
<script src="js/jquery.nicescroll.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
<script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
<script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>
<script src="js/common-scripts.js"></script>

<script>
$(function(){
    // Date Picker Logic
    var from = $('#rangeFrom').val();
    var to = $('#rangeTo').val();
    var $input = $('#rangePicker');
    
    var start = from ? moment(from) : moment();
    var end = to ? moment(to) : moment();

    function cb(start, end) {
        $input.val(start.format('YYYY-MM-DD') + '  تا  ' + end.format('YYYY-MM-DD'));
        $('#rangeFrom').val(start.format('YYYY-MM-DD'));
        $('#rangeTo').val(end.format('YYYY-MM-DD'));
    }

    $input.daterangepicker({
        startDate: start,
        endDate: end,
        opens: 'left',
        locale: { format: 'YYYY-MM-DD', separator: ' - ', applyLabel: 'تایید', cancelLabel: 'لغو' }
    }, cb);

    if(from && to) { cb(start, end); }

    $('#preset7d').click(function(e){ e.preventDefault(); $('#rangeFrom').val(moment().subtract(6, 'days').format('YYYY-MM-DD')); $('#rangeTo').val(moment().format('YYYY-MM-DD')); $('#dashboardFilterForm').submit(); });
    $('#presetMonth').click(function(e){ e.preventDefault(); $('#rangeFrom').val(moment().startOf('month').format('YYYY-MM-DD')); $('#rangeTo').val(moment().endOf('month').format('YYYY-MM-DD')); $('#dashboardFilterForm').submit(); });
    $('#presetYear').click(function(e){ e.preventDefault(); $('#rangeFrom').val(moment().startOf('year').format('YYYY-MM-DD')); $('#rangeTo').val(moment().endOf('year').format('YYYY-MM-DD')); $('#dashboardFilterForm').submit(); });
});
</script>

<script>
(function(){
    // Chart Config
    Chart.defaults.font.family = 'Vazirmatn';
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.04)';

    // Data from PHP
    <?php if($resultcontsell != 0): ?>
    var salesLabels = <?php echo json_encode(array_values(array_map(function($d){ return jdate('Y/m/d', strtotime($d)); }, array_keys($grouped_data))), JSON_UNESCAPED_UNICODE); ?>;
    var salesAmount = <?php echo json_encode(array_values(array_map(function($i){ return $i['total_amount']; }, $grouped_data))); ?>;
    var salesCount = <?php echo json_encode(array_values(array_map(function($i){ return $i['order_count']; }, $grouped_data))); ?>;
    <?php else: ?>
    var salesLabels = [], salesAmount = [], salesCount = [];
    <?php endif; ?>

    var statusLabels = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>;
    var statusData = <?php echo json_encode($statusData); ?>;
    var statusColors = <?php echo json_encode($statusColors); ?>;

    var userLabels = <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>;
    var userCounts = <?php echo json_encode($userCounts); ?>;

    // Vue App
    const app = Vue.createApp({
        data() {
            return {
                show: JSON.parse(localStorage.getItem('dash_prefs') || '{"status":true,"users":true,"sales":true}')
            }
        },
        watch: {
            show: {
                deep: true,
                handler(v) { localStorage.setItem('dash_prefs', JSON.stringify(v)); this.updateVisibility(v); }
            }
        },
        mounted() {
            this.initCharts();
            this.updateVisibility(this.show);
        },
        methods: {
            updateVisibility(v){
                const toggle = (id, s) => { const el = document.getElementById(id); if(el) el.style.display = s ? 'block' : 'none'; };
                toggle('cardSales', v.sales);
                toggle('cardStatus', v.status);
                toggle('cardUsers', v.users);
            },
            initCharts() {
                // Sales Bar
                if(document.getElementById('salesChart')) {
                    var ctx = document.getElementById('salesChart').getContext('2d');
                    var grad = ctx.createLinearGradient(0, 0, 0, 300);
                    grad.addColorStop(0, 'rgba(99, 102, 241, 0.5)');
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
                                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                                    padding: 12,
                                    titleFont: { family: 'Vazirmatn', size: 14 },
                                    bodyFont: { family: 'Vazirmatn', size: 13 },
                                    callbacks: {
                                        label: function(c) { return ' ' + Number(c.raw).toLocaleString() + ' تومان'; }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, border: { display: false } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }

                // Status Doughnut
                if(document.getElementById('statusChart')) {
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
                                legend: { position: 'right', labels: { boxWidth: 10, padding: 15, font: { family: 'Vazirmatn', size: 12 } } }
                            }
                        }
                    });
                }

                // Users Line
                if(document.getElementById('usersChart')) {
                    var ctxU = document.getElementById('usersChart').getContext('2d');
                    var gradU = ctxU.createLinearGradient(0, 0, 0, 300);
                    gradU.addColorStop(0, 'rgba(6, 182, 212, 0.3)');
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
                                y: { beginAtZero: true, border: { display: false } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            }
        }
    });
    app.mount('#dashPrefs');
})();
</script>

</body>
</html>