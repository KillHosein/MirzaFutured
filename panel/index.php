<?php
// --- سیستم گزارش‌دهی و مدیریت خطا (Enterprise Level) ---
ini_set('display_errors', 0); // در محیط واقعی خطاها به کاربر نمایش داده نشود
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

session_start();

// --- بارگذاری وابستگی‌ها ---
$configPath = '../config.php';
$jdfPath = '../jdf.php';

if (file_exists($configPath)) require_once $configPath;
if (file_exists($jdfPath)) require_once $jdfPath;

// --- لایه دسترسی به داده (Data Access Layer) ---
$isConnected = isset($pdo) && ($pdo instanceof PDO);

// مقادیر پیش‌فرض
$dateYesterday = time() - 86400;
$filterFrom = $_GET['from'] ?? null;
$filterTo = $_GET['to'] ?? null;
$filterStatus = $_GET['status'] ?? [];

if (!is_array($filterStatus)) $filterStatus = (!empty($filterStatus)) ? [$filterStatus] : [];

// 1. احراز هویت امنیتی
if ($isConnected) {
    if (!isset($_SESSION["user"])) {
        header('Location: login.php');
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, username FROM admin WHERE username=:username LIMIT 1");
        $stmt->execute(['username' => $_SESSION["user"]]);
        $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$adminUser) {
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Security Auth Error: " . $e->getMessage());
    }
}

// 2. ساختاردهی فیلترها (Query Builder Pattern)
$whereClauses = ["name_product != 'سرویس تست'"];
$queryParams = [];

if ($filterFrom && strtotime($filterFrom)) {
    $whereClauses[] = "time_sell >= :fromTs";
    $queryParams[':fromTs'] = strtotime($filterFrom);
}
if ($filterTo && strtotime($filterTo)) {
    $whereClauses[] = "time_sell <= :toTs";
    $queryParams[':toTs'] = strtotime($filterTo . ' 23:59:59');
}

if (!empty($filterStatus)) {
    $statusPlaceholders = [];
    foreach ($filterStatus as $index => $status) {
        $ph = ":status_$index";
        $statusPlaceholders[] = $ph;
        $queryParams[$ph] = $status;
    }
    $whereClauses[] = "status IN (" . implode(', ', $statusPlaceholders) . ")";
} else {
    $defaultStatuses = "'active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid'";
    $whereClauses[] = "status IN ($defaultStatuses)";
}

$whereSql = implode(' AND ', $whereClauses);

// 3. جمع‌آوری شاخص‌های کلیدی عملکرد (KPIs)
$stats = [
    'sales_volume' => 0,
    'orders_count' => 0,
    'total_users' => 0,
    'new_users_24h' => 0
];
$chartData = ['sales' => [], 'status' => [], 'growth' => []];

if ($isConnected) {
    try {
        // KPI 1: حجم فروش
        $stmt = $pdo->prepare("SELECT SUM(price_product) as total FROM invoice WHERE $whereSql AND status != 'unpaid'");
        $stmt->execute($queryParams);
        $stats['sales_volume'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // KPI 2: تعداد کل کاربران
        $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();

        // KPI 3: جذب کاربر جدید (24 ساعت اخیر)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :ts AND register != 'none'");
        $stmt->execute([':ts' => $dateYesterday]);
        $stats['new_users_24h'] = $stmt->fetchColumn();

        // KPI 4: تعداد سفارشات موفق فیلتر شده
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $whereSql AND status != 'unpaid'");
        $stmt->execute($queryParams);
        $stats['orders_count'] = $stmt->fetchColumn();

        // Chart 1: روند فروش
        if ($stats['orders_count'] > 0) {
            $stmt = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $whereSql AND status != 'unpaid' ORDER BY time_sell DESC");
            $stmt->execute($queryParams);
            $rawSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $groupedSales = [];
            foreach ($rawSales as $row) {
                if (!is_numeric($row['time_sell'])) continue;
                $dateKey = date('Y/m/d', (int)$row['time_sell']);
                if (!isset($groupedSales[$dateKey])) $groupedSales[$dateKey] = 0;
                $groupedSales[$dateKey] += (int)$row['price_product'];
            }
            ksort($groupedSales);
            $chartData['sales'] = $groupedSales;
        }

        // Chart 2: توزیع وضعیت‌ها
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM invoice WHERE $whereSql GROUP BY status");
        $stmt->execute($queryParams);
        $chartData['status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chart 3: روند جذب کاربر
        $uStart = ($filterFrom) ? strtotime($filterFrom) : (time() - (13 * 86400));
        $uEnd = ($filterTo) ? strtotime($filterTo . ' 23:59:59') : time();
        
        $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register BETWEEN :start AND :end");
        $stmt->execute([':start' => $uStart, ':end' => $uEnd]);
        $rawUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $period = new DatePeriod(new DateTime("@$uStart"), new DateInterval('P1D'), new DateTime("@$uEnd"));
        $userTrend = [];
        foreach ($period as $dt) {
            $userTrend[$dt->format('Y/m/d')] = 0;
        }
        
        foreach ($rawUsers as $u) {
            if (!is_numeric($u['register'])) continue;
            $d = date('Y/m/d', (int)$u['register']);
            if (isset($userTrend[$d])) $userTrend[$d]++;
        }
        $chartData['growth'] = $userTrend;

    } catch (PDOException $e) {
        error_log("KPI Fetch Error: " . $e->getMessage());
    }
}

// آماده‌سازی داده‌ها برای ویو
$salesLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['sales']));
$salesValues = array_values($chartData['sales']);
$userLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['growth']));
$userValues = array_values($chartData['growth']);

// دیکشنری وضعیت‌ها با رنگ‌های نئونی
$statusConfig = [
    'unpaid'       => ['label' => 'در انتظار پرداخت', 'color' => '#FBBF24'], // Amber
    'active'       => ['label' => 'فعال',             'color' => '#34D399'], // Emerald
    'disabledn'    => ['label' => 'غیرفعال',          'color' => '#94A3B8'], // Slate
    'end_of_time'  => ['label' => 'انقضای زمانی',     'color' => '#F87171'], // Red
    'end_of_volume'=> ['label' => 'اتمام حجم',        'color' => '#60A5FA'], // Blue
    'sendedwarn'   => ['label' => 'هشدار ارسال شده',  'color' => '#C084FC'], // Purple
    'send_on_hold' => ['label' => 'در صف اتصال',      'color' => '#FB923C'], // Orange
    'removebyuser' => ['label' => 'لغو توسط کاربر',   'color' => '#64748B']
];

$pieLabels = [];
$pieValues = [];
$pieColors = [];

foreach ($chartData['status'] as $row) {
    $st = $row['status'];
    $pieLabels[] = $statusConfig[$st]['label'] ?? $st;
    $pieValues[] = (int)$row['count'];
    $pieColors[] = $statusConfig[$st]['color'] ?? '#ffffff';
}

// خوش‌آمدگویی
$h = date('H');
if ($h < 12) { $greet = "صبح‌بخیر"; $greetIcon = "fa-sun"; }
elseif ($h < 18) { $greet = "روز‌خوش"; $greetIcon = "fa-cloud-sun"; }
else { $greet = "عصر‌بخیر"; $greetIcon = "fa-moon"; }

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل مدیریت | طرح آینده</title>
    
    <!-- Core Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* --- Future Tech Palette --- */
            --bg-deep: #030712;        /* Obsidian Black */
            --bg-card: rgba(17, 24, 39, 0.7); /* Deep Glass */
            
            --neon-primary: #0EA5E9;   /* Sky Blue */
            --neon-secondary: #8B5CF6; /* Violet */
            --neon-accent: #10B981;    /* Emerald */
            --neon-warn: #F59E0B;      /* Amber */
            --neon-danger: #EF4444;    /* Red */
            
            --text-primary: #F8FAFC;
            --text-secondary: #94A3B8;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --border-glow: 1px solid rgba(14, 165, 233, 0.3);
            
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            --glow-text: 0 0 20px rgba(14, 165, 233, 0.5);
            
            --radius-lg: 24px;
            --radius-md: 16px;
            
            --sidebar-width: 280px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-deep);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            font-size: 16px;
            min-height: 100vh;
            overflow-x: hidden;
            /* Subtle Mesh Background */
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(14, 165, 233, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(139, 92, 246, 0.06) 0%, transparent 40%);
            background-attachment: fixed;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        h1, h2, h3, h4 { margin: 0; font-weight: 800; letter-spacing: -0.02em; }

        /* --- Sidebar Navigation --- */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(10, 10, 15, 0.85);
            backdrop-filter: blur(20px);
            border-left: var(--border-glass);
            display: flex; flex-direction: column;
            position: fixed; top: 0; right: 0; bottom: 0;
            z-index: 1000; padding: 30px;
            box-shadow: -10px 0 40px rgba(0,0,0,0.6);
        }
        
        .brand {
            font-size: 1.8rem; font-weight: 900;
            color: #fff; margin-bottom: 50px;
            display: flex; align-items: center; gap: 15px;
            text-shadow: 0 0 20px rgba(14, 165, 233, 0.4);
        }
        .brand-icon { 
            width: 45px; height: 45px; 
            background: linear-gradient(135deg, var(--neon-primary), var(--neon-secondary));
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; box-shadow: 0 0 15px rgba(14, 165, 233, 0.4);
        }

        .nav-list { display: flex; flex-direction: column; gap: 10px; flex: 1; }
        .nav-item {
            display: flex; align-items: center; gap: 18px;
            padding: 16px 20px; border-radius: 16px;
            font-size: 1.1rem; color: var(--text-secondary);
            transition: all 0.3s; position: relative; overflow: hidden;
        }
        .nav-item:hover { color: #fff; background: rgba(255,255,255,0.03); }
        
        .nav-item.active {
            background: rgba(14, 165, 233, 0.1);
            color: var(--neon-primary);
            border: 1px solid rgba(14, 165, 233, 0.2);
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.1);
        }
        .nav-item.active::before {
            content: ''; position: absolute; right: 0; top: 15%; bottom: 15%; width: 4px;
            background: var(--neon-primary); border-radius: 4px;
            box-shadow: 0 0 10px var(--neon-primary);
        }
        
        .user-card {
            margin-top: auto; padding: 15px;
            background: rgba(255,255,255,0.03);
            border: var(--border-glass); border-radius: 16px;
            display: flex; align-items: center; gap: 15px;
        }
        .user-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            background: linear-gradient(to right, #6366f1, #a855f7);
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 1.2rem; color: #fff;
        }

        /* --- Main Layout --- */
        .main-wrapper {
            margin-right: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 40px 50px;
        }

        /* --- Header --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px;
        }
        .greeting-wrap h1 { 
            font-size: 2.8rem; color: #fff; margin-bottom: 10px;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .greeting-wrap p { 
            font-size: 1.25rem; color: var(--text-secondary); 
            display: flex; align-items: center; gap: 10px; 
        }
        
        .date-display {
            background: rgba(14, 165, 233, 0.1); border: 1px solid rgba(14, 165, 233, 0.3);
            padding: 12px 25px; border-radius: 50px;
            color: var(--neon-primary); font-weight: 600; font-size: 1.1rem;
            display: flex; align-items: center; gap: 10px;
            box-shadow: 0 0 15px rgba(14, 165, 233, 0.15);
        }

        /* --- KPI Grid --- */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; margin-bottom: 50px;
        }
        .kpi-box {
            background: var(--bg-card);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 200px; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .kpi-box::after {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.05), transparent 60%);
            opacity: 0; transition: 0.4s;
        }
        .kpi-box:hover {
            transform: translateY(-10px);
            border-color: var(--accent-color);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4), 0 0 20px var(--glow-color);
        }
        .kpi-box:hover::after { opacity: 1; }

        .kpi-icon-wrap {
            width: 60px; height: 60px; border-radius: 18px;
            background: rgba(255,255,255,0.03);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: var(--accent-color);
            margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);
            transition: 0.4s;
        }
        .kpi-box:hover .kpi-icon-wrap { 
            background: var(--accent-color); color: #000;
            box-shadow: 0 0 15px var(--accent-color);
        }

        .kpi-val { font-size: 2.8rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 8px; }
        .kpi-lbl { font-size: 1.1rem; color: var(--text-secondary); }

        /* KPI Variants */
        .k-blue { --accent-color: var(--neon-primary); --glow-color: rgba(14, 165, 233, 0.2); }
        .k-purple { --accent-color: var(--neon-secondary); --glow-color: rgba(139, 92, 246, 0.2); }
        .k-green { --accent-color: var(--neon-accent); --glow-color: rgba(16, 185, 129, 0.2); }
        .k-amber { --accent-color: var(--neon-warn); --glow-color: rgba(245, 158, 11, 0.2); }

        /* --- Quick Actions --- */
        .section-heading { 
            font-size: 1.6rem; color: #fff; margin-bottom: 30px; 
            display: flex; align-items: center; gap: 15px;
        }
        .section-heading::before {
            content: ''; width: 6px; height: 30px; background: var(--neon-primary);
            border-radius: 4px; box-shadow: 0 0 10px var(--neon-primary);
        }

        .actions-container {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 25px; margin-bottom: 50px;
        }
        .action-card {
            background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255,255,255,0.05);
            border-radius: var(--radius-md); padding: 25px;
            display: flex; align-items: center; gap: 20px;
            cursor: pointer; transition: 0.3s;
        }
        .action-card:hover {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--neon-primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .ac-icon {
            font-size: 2rem; color: var(--text-secondary); transition: 0.3s;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5));
        }
        .action-card:hover .ac-icon { color: #fff; transform: scale(1.1); filter: drop-shadow(0 0 10px var(--neon-primary)); }
        
        .ac-info h3 { font-size: 1.1rem; color: #fff; font-weight: 700; margin-bottom: 4px; }
        .ac-info span { font-size: 0.9rem; color: var(--text-secondary); }

        /* --- Filter Bar --- */
        .filter-console {
            background: #0B0F17; border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg); padding: 30px;
            display: grid; grid-template-columns: 1fr 1fr auto; gap: 25px;
            margin-bottom: 50px; align-items: center;
        }
        .input-tech {
            width: 100%; height: 60px; background: #020408; 
            border: 1px solid rgba(255,255,255,0.1); border-radius: 14px;
            color: #fff; padding: 0 25px; font-size: 1.1rem; transition: 0.3s;
        }
        .input-tech:focus {
            border-color: var(--neon-primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }
        .btn-tech {
            height: 60px; padding: 0 45px; background: var(--neon-primary);
            color: #000; font-weight: 800; font-size: 1.1rem; border: none;
            border-radius: 14px; cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; gap: 10px;
        }
        .btn-tech:hover {
            background: #38BDF8; box-shadow: 0 0 25px rgba(14, 165, 233, 0.4);
            transform: translateY(-2px);
        }

        /* --- Charts Area --- */
        .grid-charts {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px;
        }
        .chart-glass {
            background: var(--bg-card);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 35px;
        }
        .chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .chart-label { font-size: 1.4rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 12px; }
        .chart-label i { color: var(--neon-primary); }

        /* --- Responsive --- */
        @media (max-width: 1600px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-charts { grid-template-columns: 1fr; }
        }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(100%); width: 280px; }
            .sidebar.active { transform: translateX(0); }
            .main-wrapper { margin-right: 0; width: 100%; padding: 25px; }
            .filter-console { grid-template-columns: 1fr; }
            .mobile-btn { display: flex; position: fixed; bottom: 25px; left: 25px; width: 60px; height: 60px; background: var(--neon-primary); border-radius: 50%; z-index: 2000; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        }
        @media (min-width: 1025px) { .mobile-btn { display: none; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="fa-solid fa-layer-group"></i></div>
            <span>NEXUS PRO</span>
        </div>

        <nav class="nav-list">
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-grid-2"></i> <span>داشبورد اصلی</span>
            </a>
            <a href="invoice.php" class="nav-item">
                <i class="fa-solid fa-file-invoice"></i> <span>مدیریت فاکتورها</span>
            </a>
            <a href="user.php" class="nav-item">
                <i class="fa-solid fa-users-gear"></i> <span>مدیریت کاربران</span>
            </a>
            <a href="product.php" class="nav-item">
                <i class="fa-solid fa-box-open"></i> <span>محصولات و انبار</span>
            </a>
            <a href="server_status.php" class="nav-item">
                <i class="fa-solid fa-server"></i> <span>منابع سرور</span>
            </a>
            <a href="logout.php" class="nav-item" style="margin-top: auto; color: var(--neon-danger);">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>خروج امن</span>
            </a>
        </nav>

        <div class="user-card">
            <div class="user-avatar">AD</div>
            <div style="line-height: 1.3;">
                <div style="font-weight: 700; color: #fff;">مدیر سیستم</div>
                <div style="font-size: 0.85rem; color: var(--neon-primary);">دسترسی کامل</div>
            </div>
        </div>
    </aside>

    <div class="mobile-btn" onclick="document.getElementById('sidebar').classList.toggle('active')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        
        <!-- Header -->
        <header class="page-header">
            <div class="greeting-wrap">
                <h1><?php echo $greet; ?>، فرمانده</h1>
                <p>
                    <i class="<?php echo $greetIcon; ?>"></i>
                    وضعیت کلی سیستم: <span style="color: var(--neon-accent); font-weight: bold;">عملیاتی و پایدار</span>
                </p>
            </div>
            <div class="date-display">
                <i class="fa-regular fa-calendar-check"></i>
                <?php echo $todayDate; ?>
            </div>
        </header>

        <!-- KPI Grid -->
        <section class="kpi-grid">
            <div class="kpi-box k-blue">
                <div class="kpi-icon-wrap"><i class="fa-solid fa-coins"></i></div>
                <div>
                    <div class="kpi-val"><?php echo number_format($stats['sales_volume']); ?></div>
                    <div class="kpi-lbl">درآمد کل (تومان)</div>
                </div>
            </div>
            <div class="kpi-box k-green">
                <div class="kpi-icon-wrap"><i class="fa-solid fa-receipt"></i></div>
                <div>
                    <div class="kpi-val"><?php echo number_format($stats['orders_count']); ?></div>
                    <div class="kpi-lbl">تراکنش‌های موفق</div>
                </div>
            </div>
            <div class="kpi-box k-purple">
                <div class="kpi-icon-wrap"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="kpi-val"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="kpi-lbl">مشترکین فعال</div>
                </div>
            </div>
            <div class="kpi-box k-amber">
                <div class="kpi-icon-wrap"><i class="fa-solid fa-user-plus"></i></div>
                <div>
                    <div class="kpi-val"><?php echo number_format($stats['new_users_24h']); ?></div>
                    <div class="kpi-lbl">ورودی‌های امروز</div>
                </div>
            </div>
        </section>

        <!-- Actions -->
        <div class="section-heading">پنل عملیات سریع</div>
        <section class="actions-container">
            <a href="invoice.php" class="action-card">
                <i class="fa-solid fa-list-check ac-icon"></i>
                <div class="ac-info">
                    <h3>لیست سفارشات</h3>
                    <span>بررسی وضعیت</span>
                </div>
            </a>
            <a href="user.php" class="action-card">
                <i class="fa-solid fa-user-shield ac-icon"></i>
                <div class="ac-info">
                    <h3>کاربران</h3>
                    <span>مدیریت دسترسی</span>
                </div>
            </a>
            <a href="inbound.php" class="action-card">
                <i class="fa-solid fa-sliders ac-icon"></i>
                <div class="ac-info">
                    <h3>کانفیگ‌ها</h3>
                    <span>تنظیمات شبکه</span>
                </div>
            </a>
            <a href="payment.php" class="action-card">
                <i class="fa-solid fa-wallet ac-icon"></i>
                <div class="ac-info">
                    <h3>امور مالی</h3>
                    <span>تراکنش‌های بانکی</span>
                </div>
            </a>
            <a href="server_status.php" class="action-card">
                <i class="fa-solid fa-microchip ac-icon"></i>
                <div class="ac-info">
                    <h3>مانیتورینگ</h3>
                    <span>منابع سخت‌افزاری</span>
                </div>
            </a>
            <a href="cancelService.php" class="action-card" style="border-color: rgba(239, 68, 68, 0.3);">
                <i class="fa-solid fa-ban ac-icon" style="color: var(--neon-danger);"></i>
                <div class="ac-info">
                    <h3 style="color: var(--neon-danger);">مسدودسازی</h3>
                    <span>قطع دسترسی</span>
                </div>
            </a>
        </section>

        <!-- Filter -->
        <form method="get" class="filter-console">
            <div>
                <input type="text" id="dateRange" class="input-tech" placeholder="بازه زمانی را انتخاب کنید..." readonly>
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($filterFrom ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($filterTo ?? ''); ?>">
            </div>
            <div>
                <select name="status[]" multiple class="input-tech" style="padding-top: 15px;">
                    <?php foreach($statusConfig as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $filterStatus) ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-tech">
                <i class="fa-solid fa-filter"></i> اعمال فیلتر
            </button>
        </form>

        <!-- Charts -->
        <div class="grid-charts">
            <div class="chart-glass">
                <div class="chart-head">
                    <div class="chart-label"><i class="fa-solid fa-chart-area"></i> تحلیل جامع فروش</div>
                </div>
                <div style="height: 400px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="chart-glass" style="flex: 1;">
                    <div class="chart-head">
                        <div class="chart-label"><i class="fa-solid fa-chart-pie" style="color: var(--neon-secondary);"></i> وضعیت‌ها</div>
                    </div>
                    <div style="height: 250px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-glass" style="flex: 1;">
                    <div class="chart-head">
                        <div class="chart-label"><i class="fa-solid fa-arrow-trend-up" style="color: var(--neon-accent);"></i> رشد کاربر</div>
                    </div>
                    <div style="height: 180px;">
                        <canvas id="usersChart"></canvas>
                    </div>
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
        $(function(){
            // Datepicker Logic
            var start = moment().subtract(13, 'days');
            var end = moment();
            var phpFrom = '<?php echo $filterFrom; ?>';
            var phpTo = '<?php echo $filterTo; ?>';
            if(phpFrom && phpTo){ start = moment(phpFrom); end = moment(phpTo); }
            $('#dateRange').daterangepicker({ startDate: start, endDate: end, opens: 'left', locale: { format: 'YYYY/MM/DD', applyLabel: 'تایید', cancelLabel: 'لغو' } }, function(s, e) {
                $('#dateRange').val(s.format('YYYY/MM/DD') + '  -  ' + e.format('YYYY/MM/DD'));
                $('#inputFrom').val(s.format('YYYY-MM-DD'));
                $('#inputTo').val(e.format('YYYY-MM-DD'));
            });
            if(phpFrom) $('#dateRange').val(start.format('YYYY/MM/DD') + '  -  ' + end.format('YYYY/MM/DD'));
        });

        // --- Chart Config: High Tech ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.font.size = 14;
        Chart.defaults.color = '#94A3B8';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(3, 7, 18, 0.95)';
        Chart.defaults.plugins.tooltip.padding = 15;
        Chart.defaults.plugins.tooltip.cornerRadius = 12;
        
        const dataSales = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dataPie = { labels: <?php echo json_encode($pieLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($pieValues); ?>, colors: <?php echo json_encode($pieColors); ?> };
        const dataUsers = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Smooth Gradient Area)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gradSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gradSales.addColorStop(0, '#0EA5E9');
        gradSales.addColorStop(1, 'rgba(14, 165, 233, 0.02)');

        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: dataSales.labels,
                datasets: [{
                    label: 'فروش',
                    data: dataSales.values,
                    borderColor: '#0EA5E9',
                    backgroundColor: gradSales,
                    borderWidth: 3,
                    pointBackgroundColor: '#0EA5E9',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 8,
                    fill: true,
                    tension: 0.4
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

        // 2. Status Chart (Modern Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dataPie.labels,
                datasets: [{
                    data: dataPie.values,
                    backgroundColor: dataPie.colors,
                    borderColor: 'rgba(0,0,0,0)',
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, padding: 20, font: { size: 12 }, color: '#94A3B8', usePointStyle: true } }
                }
            }
        });

        // 3. User Chart (Bar)
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dataUsers.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: dataUsers.values,
                    backgroundColor: '#10B981',
                    borderRadius: 6,
                    barThickness: 18
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