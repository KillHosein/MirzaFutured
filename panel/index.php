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
    'unpaid'       => ['label' => 'در انتظار پرداخت', 'color' => '#FFC107'], // Neon Yellow
    'active'       => ['label' => 'فعال',             'color' => '#00E676'], // Neon Green
    'disabledn'    => ['label' => 'غیرفعال',          'color' => '#B0BEC5'], // Grey
    'end_of_time'  => ['label' => 'انقضای زمانی',     'color' => '#FF1744'], // Neon Red
    'end_of_volume'=> ['label' => 'اتمام حجم',        'color' => '#2979FF'], // Neon Blue
    'sendedwarn'   => ['label' => 'هشدار ارسال شده',  'color' => '#D500F9'], // Neon Purple
    'send_on_hold' => ['label' => 'در صف اتصال',      'color' => '#FF9100'], // Neon Orange
    'removebyuser' => ['label' => 'لغو توسط کاربر',   'color' => '#546E7A']
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
if ($h < 12) { $greet = "صبح بخیر"; $greetIcon = "fa-sun"; }
elseif ($h < 18) { $greet = "روز خوش"; $greetIcon = "fa-cloud-sun"; }
else { $greet = "عصر بخیر"; $greetIcon = "fa-moon"; }

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>داشبورد مدیریت | نئون</title>
    
    <!-- Core Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* --- Neon Cyberpunk Palette --- */
            --bg-core: #050505;        /* تقریباً سیاه */
            --bg-panel: #0a0a0a;       /* سیاه روشن‌تر */
            --bg-card: rgba(20, 20, 20, 0.95);
            
            --border-neon: rgba(255, 255, 255, 0.1);
            
            --neon-blue: #00F0FF;      /* Cyber Blue */
            --neon-pink: #FF00FF;      /* Cyber Pink */
            --neon-green: #00FF9D;     /* Cyber Green */
            --neon-purple: #BD00FF;    /* Cyber Purple */
            --neon-yellow: #FAFF00;    /* Cyber Yellow */
            
            --text-main: #FFFFFF;
            --text-muted: #A0A0A0;
            
            --glow-sm: 0 0 10px rgba(0, 240, 255, 0.3);
            --glow-md: 0 0 20px rgba(189, 0, 255, 0.2);
            --glow-strong: 0 0 25px rgba(0, 255, 157, 0.4);
            
            --font-size-base: 16px; /* پایه فونت بزرگتر */
            --sidebar-width: 280px; /* سایدبار عریض‌تر */
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-core);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            font-size: var(--font-size-base);
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                linear-gradient(rgba(0, 240, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 240, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px; /* Grid Pattern */
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }
        h1, h2, h3, h4, h5 { font-weight: 800; letter-spacing: -0.5px; margin: 0; }

        /* --- Layout --- */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-panel);
            border-left: 2px solid #222;
            display: flex; flex-direction: column;
            position: fixed; top: 0; right: 0; bottom: 0;
            z-index: 1000;
            padding: 20px;
            box-shadow: -5px 0 30px rgba(0,0,0,0.8);
        }
        
        .brand {
            font-size: 2rem;
            color: var(--text-main);
            text-shadow: 0 0 10px var(--neon-blue);
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
            display: flex; align-items: center; gap: 15px;
        }
        .brand i { color: var(--neon-blue); }

        .nav-list { display: flex; flex-direction: column; gap: 10px; flex: 1; }
        .nav-item {
            display: flex; align-items: center; gap: 15px;
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 1.1rem;
            color: var(--text-muted);
            transition: 0.3s;
            border: 1px solid transparent;
        }
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            transform: translateX(-5px);
        }
        .nav-item.active {
            background: rgba(0, 240, 255, 0.1);
            color: var(--neon-blue);
            border: 1px solid rgba(0, 240, 255, 0.3);
            box-shadow: 0 0 15px rgba(0, 240, 255, 0.1);
        }
        .nav-item i { font-size: 1.4rem; width: 30px; text-align: center; }

        .user-panel {
            margin-top: auto;
            background: #151515;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #333;
            display: flex; align-items: center; gap: 15px;
        }
        .avatar {
            width: 50px; height: 50px; background: var(--neon-purple);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: #fff; box-shadow: 0 0 10px var(--neon-purple);
        }
        .user-details div:first-child { font-weight: bold; font-size: 1.1rem; }
        .user-details div:last-child { color: var(--text-muted); font-size: 0.9rem; }

        /* --- Main Content --- */
        .main-content {
            margin-right: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 40px;
        }

        /* --- Header --- */
        .header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 40px;
        }
        .title-area h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .title-area p { font-size: 1.2rem; color: var(--text-muted); display: flex; align-items: center; gap: 10px; }
        
        .date-box {
            background: rgba(0, 255, 157, 0.1);
            border: 1px solid var(--neon-green);
            color: var(--neon-green);
            padding: 10px 25px; border-radius: 50px;
            font-size: 1.1rem; font-weight: bold;
            box-shadow: 0 0 10px rgba(0, 255, 157, 0.2);
            display: flex; align-items: center; gap: 10px;
        }

        /* --- KPI Grid --- */
        .kpi-container {
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 25px; margin-bottom: 40px;
        }
        .kpi-card {
            background: var(--bg-card);
            border: 1px solid #333;
            padding: 30px; border-radius: 20px;
            position: relative; overflow: hidden;
            transition: 0.3s;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 180px;
        }
        .kpi-card:hover {
            transform: translateY(-8px);
            border-color: var(--kpi-color);
            box-shadow: 0 0 25px var(--kpi-glow);
        }
        /* Top border line */
        .kpi-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: var(--kpi-color);
            box-shadow: 0 0 10px var(--kpi-color);
        }

        .kpi-icon {
            font-size: 2.5rem; color: var(--kpi-color);
            margin-bottom: 15px;
            filter: drop-shadow(0 0 5px var(--kpi-color));
        }
        .kpi-value { font-size: 3rem; font-weight: 900; color: #fff; line-height: 1; margin-bottom: 5px; }
        .kpi-label { font-size: 1.1rem; color: var(--text-muted); font-weight: 500; }

        /* KPI Specifics */
        .k-1 { --kpi-color: var(--neon-blue); --kpi-glow: rgba(0, 240, 255, 0.2); }
        .k-2 { --kpi-color: var(--neon-green); --kpi-glow: rgba(0, 255, 157, 0.2); }
        .k-3 { --kpi-color: var(--neon-pink); --kpi-glow: rgba(255, 0, 255, 0.2); }
        .k-4 { --kpi-color: var(--neon-yellow); --kpi-glow: rgba(250, 255, 0, 0.2); }

        /* --- Quick Actions (Big Tiles) --- */
        .section-header { 
            font-size: 1.5rem; color: #fff; margin-bottom: 25px; 
            border-right: 4px solid var(--neon-purple); padding-right: 15px; 
        }

        .actions-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 25px; margin-bottom: 50px;
        }
        .action-tile {
            background: #111;
            border: 1px solid #333;
            border-radius: 18px;
            padding: 30px 20px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px;
            transition: 0.4s; cursor: pointer;
            text-align: center;
        }
        .action-tile:hover {
            background: #1a1a1a;
            border-color: var(--neon-blue);
            box-shadow: inset 0 0 20px rgba(0, 240, 255, 0.1), 0 0 15px rgba(0, 240, 255, 0.2);
            transform: scale(1.03);
        }
        .action-tile i { font-size: 2.5rem; color: var(--text-muted); transition: 0.3s; }
        .action-tile span { font-size: 1.2rem; font-weight: 600; color: #fff; }
        
        .action-tile:hover i { color: var(--neon-blue); text-shadow: 0 0 10px var(--neon-blue); transform: scale(1.1); }
        .t-red:hover { border-color: var(--neon-pink); }
        .t-red:hover i { color: var(--neon-pink); text-shadow: 0 0 10px var(--neon-pink); }

        /* --- Charts Area --- */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr; /* 2/3 width vs 1/3 width */
            gap: 25px;
        }
        .chart-card {
            background: var(--bg-card);
            border: 1px solid #333;
            border-radius: 20px;
            padding: 30px;
        }
        .chart-title {
            font-size: 1.3rem; margin-bottom: 25px; color: #fff;
            display: flex; align-items: center; gap: 10px;
        }
        .chart-title i { color: var(--neon-purple); }

        /* --- Filter Bar (Integrated) --- */
        .filter-bar {
            display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px;
            background: #0f0f0f; padding: 25px; border-radius: 20px; border: 1px solid #333;
            margin-bottom: 40px; align-items: center;
        }
        .input-lg {
            width: 100%; height: 55px; background: #000; border: 1px solid #444;
            color: #fff; padding: 0 20px; font-size: 1.1rem; border-radius: 12px;
            transition: 0.3s;
        }
        .input-lg:focus { border-color: var(--neon-green); box-shadow: 0 0 15px rgba(0, 255, 157, 0.2); }
        
        .btn-neon {
            height: 55px; padding: 0 40px; background: transparent;
            border: 2px solid var(--neon-blue); color: var(--neon-blue);
            font-size: 1.1rem; font-weight: bold; border-radius: 12px;
            cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-neon:hover {
            background: var(--neon-blue); color: #000;
            box-shadow: 0 0 20px var(--neon-blue);
        }

        /* --- Responsive --- */
        @media (max-width: 1400px) {
            .kpi-container { grid-template-columns: repeat(2, 1fr); }
            .charts-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(100%); width: 280px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-right: 0; width: 100%; padding: 20px; }
            .mobile-toggle { display: block; position: fixed; bottom: 20px; left: 20px; z-index: 2000; width: 60px; height: 60px; background: var(--neon-purple); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; color: #fff; box-shadow: 0 0 20px var(--neon-purple); }
            .filter-bar { grid-template-columns: 1fr; }
        }
        @media (min-width: 993px) { .mobile-toggle { display: none; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <i class="fa-solid fa-bolt"></i>
            <span>NEXUS</span>
        </div>

        <nav class="nav-list">
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-grid-2"></i> <span>داشبورد</span>
            </a>
            <a href="invoice.php" class="nav-item">
                <i class="fa-solid fa-receipt"></i> <span>سفارشات</span>
            </a>
            <a href="user.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>کاربران</span>
            </a>
            <a href="product.php" class="nav-item">
                <i class="fa-solid fa-cube"></i> <span>محصولات</span>
            </a>
            <a href="server_status.php" class="nav-item">
                <i class="fa-solid fa-server"></i> <span>سرور</span>
            </a>
            <a href="logout.php" class="nav-item" style="color: var(--neon-pink);">
                <i class="fa-solid fa-power-off"></i> <span>خروج</span>
            </a>
        </nav>

        <div class="user-panel">
            <div class="avatar">A</div>
            <div class="user-details">
                <div>Admin</div>
                <div>Super User</div>
            </div>
        </div>
    </aside>

    <div class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Content -->
    <div class="main-content">
        
        <!-- Header -->
        <header class="header">
            <div class="title-area">
                <h1><?php echo $greet; ?></h1>
                <p>
                    <i class="<?php echo $greetIcon; ?>"></i>
                    وضعیت سیستم: <span style="color: var(--neon-green);">پایدار و آنلاین</span>
                </p>
            </div>
            <div class="date-box">
                <?php echo $todayDate; ?>
                <i class="fa-regular fa-calendar-days"></i>
            </div>
        </header>

        <!-- KPI Grid -->
        <section class="kpi-container">
            <div class="kpi-card k-1">
                <i class="fa-solid fa-wallet kpi-icon"></i>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['sales_volume']); ?></div>
                    <div class="kpi-label">مجموع فروش (تومان)</div>
                </div>
            </div>
            <div class="kpi-card k-2">
                <i class="fa-solid fa-file-circle-check kpi-icon"></i>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['orders_count']); ?></div>
                    <div class="kpi-label">سفارشات موفق</div>
                </div>
            </div>
            <div class="kpi-card k-3">
                <i class="fa-solid fa-users-viewfinder kpi-icon"></i>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="kpi-label">مشترکین فعال</div>
                </div>
            </div>
            <div class="kpi-card k-4">
                <i class="fa-solid fa-user-astronaut kpi-icon"></i>
                <div>
                    <div class="kpi-value"><?php echo number_format($stats['new_users_24h']); ?></div>
                    <div class="kpi-label">ثبت‌نام امروز</div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <div class="section-header">عملیات سریع</div>
        <section class="actions-grid">
            <a href="invoice.php" class="action-tile">
                <i class="fa-solid fa-list-check"></i>
                <span>مدیریت فاکتورها</span>
            </a>
            <a href="user.php" class="action-tile">
                <i class="fa-solid fa-user-group"></i>
                <span>لیست کاربران</span>
            </a>
            <a href="inbound.php" class="action-tile">
                <i class="fa-solid fa-network-wired"></i>
                <span>تنظیمات شبکه</span>
            </a>
            <a href="payment.php" class="action-tile">
                <i class="fa-solid fa-credit-card"></i>
                <span>تراکنش‌ها</span>
            </a>
            <a href="server_status.php" class="action-tile">
                <i class="fa-solid fa-memory"></i>
                <span>مانیتورینگ</span>
            </a>
            <a href="cancelService.php" class="action-tile t-red">
                <i class="fa-solid fa-ban"></i>
                <span>مسدودسازی</span>
            </a>
        </section>

        <!-- Filter Bar -->
        <form method="get" class="filter-bar">
            <div>
                <input type="text" id="dateRange" class="input-lg" placeholder="انتخاب بازه زمانی..." readonly>
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($filterFrom ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($filterTo ?? ''); ?>">
            </div>
            <div>
                <select name="status[]" multiple class="input-lg" style="padding-top:12px;">
                    <?php foreach($statusConfig as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $filterStatus) ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-neon">
                <i class="fa-solid fa-filter"></i> فیلتر
            </button>
        </form>

        <!-- Charts -->
        <div class="charts-row">
            <!-- Main Chart -->
            <div class="chart-card">
                <div class="chart-title"><i class="fa-solid fa-chart-line"></i> نمودار فروش</div>
                <div style="height: 400px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Side Charts -->
            <div style="display: flex; flex-direction: column; gap: 25px;">
                <div class="chart-card" style="flex: 1;">
                    <div class="chart-title"><i class="fa-solid fa-chart-pie"></i> وضعیت سرویس‌ها</div>
                    <div style="height: 250px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-card" style="flex: 1;">
                    <div class="chart-title"><i class="fa-solid fa-arrow-trend-up"></i> رشد کاربر</div>
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
            // Datepicker Logic (Same as before)
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

        // --- Chart Config: Neon Style ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.font.size = 14;
        Chart.defaults.color = '#ccc';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.1)';
        
        const dataSales = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dataPie = { labels: <?php echo json_encode($pieLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($pieValues); ?>, colors: <?php echo json_encode($pieColors); ?> };
        const dataUsers = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Neon Line)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gradSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gradSales.addColorStop(0, '#00F0FF');
        gradSales.addColorStop(1, 'rgba(0, 240, 255, 0.01)');

        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: dataSales.labels,
                datasets: [{
                    label: 'فروش',
                    data: dataSales.values,
                    borderColor: '#00F0FF',
                    backgroundColor: gradSales,
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#00F0FF',
                    pointRadius: 4,
                    pointHoverRadius: 8,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dataPie.labels,
                datasets: [{
                    data: dataPie.values,
                    backgroundColor: dataPie.colors,
                    borderColor: '#000',
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 15, padding: 15, font: { size: 12 }, color: '#fff' } }
                }
            }
        });

        // 3. User Chart
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dataUsers.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: dataUsers.values,
                    backgroundColor: '#BD00FF',
                    borderRadius: 6,
                    barThickness: 15
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