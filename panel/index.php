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
        // die("خطای امنیتی سیستم");
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
    // وضعیت‌های پیش‌فرض قابل نمایش
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

        // Chart 1: روند فروش (Aggregation)
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
        
        // پر کردن روزهای خالی
        $period = new DatePeriod(
            new DateTime("@$uStart"),
            new DateInterval('P1D'),
            new DateTime("@$uEnd")
        );
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

// آماده‌سازی داده‌ها برای ویو (View Models)
$salesLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['sales']));
$salesValues = array_values($chartData['sales']);

$userLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['growth']));
$userValues = array_values($chartData['growth']);

// دیکشنری وضعیت‌ها و رنگ‌بندی سازمانی
$statusConfig = [
    'unpaid'       => ['label' => 'در انتظار پرداخت', 'color' => '#F59E0B'],
    'active'       => ['label' => 'فعال و جاری',      'color' => '#10B981'],
    'disabledn'    => ['label' => 'غیرفعال شده',      'color' => '#64748B'],
    'end_of_time'  => ['label' => 'انقضای زمانی',     'color' => '#EF4444'],
    'end_of_volume'=> ['label' => 'اتمام ترافیک',     'color' => '#3B82F6'],
    'sendedwarn'   => ['label' => 'هشدار ارسال شده',  'color' => '#8B5CF6'],
    'send_on_hold' => ['label' => 'در صف اتصال',      'color' => '#F97316'],
    'removebyuser' => ['label' => 'لغو توسط کاربر',   'color' => '#475569']
];

$pieLabels = [];
$pieValues = [];
$pieColors = [];

foreach ($chartData['status'] as $row) {
    $st = $row['status'];
    $pieLabels[] = $statusConfig[$st]['label'] ?? $st;
    $pieValues[] = (int)$row['count'];
    $pieColors[] = $statusConfig[$st]['color'] ?? '#94a3b8';
}

// منطق خوش‌آمدگویی هوشمند
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
    <title>مرکز کنترل عملیات | Admin Console</title>
    
    <!-- Core Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* --- Enterprise Palette --- */
            --bg-deep: #020617;        /* Slate 950 */
            --bg-panel: #0f172a;       /* Slate 900 */
            --bg-glass: rgba(15, 23, 42, 0.75);
            
            --border-subtle: rgba(255, 255, 255, 0.06);
            --border-highlight: rgba(255, 255, 255, 0.12);
            
            --primary: #6366F1;        /* Indigo */
            --primary-dark: #4F46E5;
            --primary-glow: rgba(99, 102, 241, 0.4);
            
            --accent-gold: #D4AF37;    /* Metallic Gold */
            --accent-teal: #14B8A6;    /* Teal */
            
            --text-primary: #F8FAFC;
            --text-secondary: #94A3B8;
            --text-tertiary: #64748B;
            
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            --shadow-glow: 0 0 20px rgba(99, 102, 241, 0.15);
            
            --radius-card: 20px;
            --radius-btn: 12px;
            
            --sidebar-w-collapsed: 80px;
            --sidebar-w-expanded: 280px;
            --header-height: 80px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-deep);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            overflow-x: hidden;
            /* Sophisticated Gradient Background */
            background-image: 
                radial-gradient(circle at 15% 0%, rgba(99, 102, 241, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 85% 100%, rgba(212, 175, 55, 0.04) 0%, transparent 40%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        a { text-decoration: none; color: inherit; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* --- Sidebar Navigation (Intelligent) --- */
        .sidebar {
            position: fixed; top: 0; right: 0; height: 100vh;
            width: var(--sidebar-w-collapsed);
            background: var(--bg-glass);
            backdrop-filter: blur(20px) saturate(180%);
            border-left: 1px solid var(--border-subtle);
            z-index: 1000;
            display: flex; flex-direction: column;
            transition: width 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
            box-shadow: -5px 0 30px rgba(0,0,0,0.5);
        }
        .sidebar:hover { width: var(--sidebar-w-expanded); }

        .brand-section {
            height: var(--header-height);
            display: flex; align-items: center; justify-content: flex-start;
            padding: 0 24px; margin-bottom: 20px;
            border-bottom: 1px solid var(--border-subtle);
            white-space: nowrap; overflow: hidden;
        }
        .brand-logo {
            font-size: 1.8rem; color: var(--primary);
            min-width: 32px; display: flex; justify-content: center;
            filter: drop-shadow(0 0 8px var(--primary-glow));
        }
        .brand-name {
            margin-right: 15px; font-weight: 800; font-size: 1.1rem; letter-spacing: -0.5px;
            opacity: 0; transform: translateX(10px); transition: 0.3s;
        }
        .sidebar:hover .brand-name { opacity: 1; transform: translateX(0); }

        .nav-items { flex: 1; padding: 0 12px; display: flex; flex-direction: column; gap: 8px; }
        .nav-item {
            height: 50px; border-radius: var(--radius-btn);
            display: flex; align-items: center; padding: 0 14px;
            color: var(--text-secondary); cursor: pointer;
            position: relative; overflow: hidden;
        }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.15), transparent);
            color: #fff;
        }
        .nav-item.active::before {
            content: ''; position: absolute; right: 0; top: 10%; bottom: 10%;
            width: 3px; background: var(--primary); border-radius: 4px;
        }
        
        .nav-icon { font-size: 1.25rem; min-width: 30px; display: flex; justify-content: center; }
        .nav-label { margin-right: 15px; font-size: 0.95rem; font-weight: 500; white-space: nowrap; opacity: 0; transition: 0.2s; }
        .sidebar:hover .nav-label { opacity: 1; transition-delay: 0.1s; }

        .user-profile {
            margin-top: auto; padding: 20px;
            border-top: 1px solid var(--border-subtle);
            display: flex; align-items: center;
            background: rgba(0,0,0,0.2);
        }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #818CF8);
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; color: #fff; font-size: 1.1rem;
        }
        .user-info { margin-right: 12px; opacity: 0; transition: 0.3s; white-space: nowrap; }
        .sidebar:hover .user-info { opacity: 1; }
        .user-role { font-size: 0.75rem; color: var(--accent-gold); letter-spacing: 0.5px; }
        .user-name { font-size: 0.9rem; font-weight: 700; color: #fff; }

        /* --- Main Layout --- */
        .main-wrapper {
            margin-right: var(--sidebar-w-collapsed);
            padding: 0 40px 40px 40px;
            transition: margin-right 0.4s;
        }

        /* --- Header --- */
        .top-header {
            height: var(--header-height);
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .welcome-msg h1 { font-size: 1.8rem; font-weight: 800; margin: 0; color: #fff; }
        .welcome-msg p { margin: 5px 0 0; color: var(--text-secondary); font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        
        .header-actions { display: flex; gap: 15px; }
        .action-btn {
            width: 44px; height: 44px; border-radius: 12px;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); cursor: pointer; transition: 0.3s;
        }
        .action-btn:hover { background: rgba(255,255,255,0.08); color: #fff; border-color: var(--border-highlight); transform: translateY(-2px); }
        .date-badge {
            padding: 0 20px; width: auto; font-size: 0.9rem; font-weight: 500;
            background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.2);
            color: #A5B4FC;
        }

        /* --- KPI Cards (Glass) --- */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px; margin-bottom: 40px;
        }
        .kpi-card {
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.6) 0%, rgba(15, 23, 42, 0.8) 100%);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            padding: 24px; position: relative; overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-highlight);
            box-shadow: var(--shadow-lg), 0 0 20px rgba(99, 102, 241, 0.1);
        }
        .kpi-card::before {
            content: ''; position: absolute; top: 0; right: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--card-accent), transparent);
            opacity: 0.7;
        }

        .kpi-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .kpi-icon {
            width: 48px; height: 48px; border-radius: 14px;
            background: rgba(255,255,255,0.03);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: var(--card-accent);
        }
        .kpi-trend { font-size: 0.8rem; color: #10B981; display: flex; align-items: center; gap: 4px; background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 20px; }
        
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; letter-spacing: -1px; margin-bottom: 5px; }
        .kpi-label { font-size: 0.9rem; color: var(--text-secondary); }

        /* KPI Colors */
        .k-sales { --card-accent: #6366F1; }
        .k-orders { --card-accent: #14B8A6; }
        .k-users { --card-accent: #EC4899; }
        .k-new { --card-accent: #F59E0B; }

        /* --- Operations Grid (Quick Actions) --- */
        .ops-section { margin-bottom: 40px; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .section-title i { color: var(--accent-gold); }

        .ops-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .op-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-subtle);
            border-radius: 16px; padding: 20px;
            display: flex; align-items: center; gap: 15px;
            transition: 0.3s; cursor: pointer; text-decoration: none;
        }
        .op-card:hover {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--primary);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            transform: translateY(-3px);
        }
        .op-icon {
            width: 45px; height: 45px; border-radius: 12px;
            background: rgba(99, 102, 241, 0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: var(--primary); transition: 0.3s;
        }
        .op-card:hover .op-icon { background: var(--primary); color: #fff; }
        
        .op-info { display: flex; flex-direction: column; }
        .op-title { font-weight: 700; color: var(--text-primary); font-size: 0.95rem; }
        .op-desc { font-size: 0.8rem; color: var(--text-tertiary); margin-top: 2px; }

        /* --- Filter Bar --- */
        .filter-dock {
            background: rgba(15, 23, 42, 0.4); border: 1px dashed var(--border-subtle);
            border-radius: var(--radius-card); padding: 20px;
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
            margin-bottom: 40px;
        }
        .input-glass {
            flex: 1; min-width: 200px; height: 48px;
            background: rgba(0,0,0,0.3); border: 1px solid var(--border-subtle);
            border-radius: 12px; color: #fff; padding: 0 15px;
            transition: 0.3s; font-family: inherit;
        }
        .input-glass:focus { border-color: var(--primary); background: rgba(0,0,0,0.5); }
        .btn-glass {
            height: 48px; padding: 0 30px; border-radius: 12px; border: none;
            background: var(--primary); color: #fff; font-weight: 600;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-glass:hover { background: var(--primary-dark); transform: translateY(-2px); }

        /* --- Charts Layout --- */
        .charts-wrapper {
            display: grid; grid-template-columns: 2fr 1fr; gap: 24px;
        }
        .chart-panel {
            background: var(--bg-glass); border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card); padding: 25px;
            display: flex; flex-direction: column;
        }
        .chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-label { font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }

        /* --- Animation --- */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; }

        /* --- Responsive --- */
        @media (max-width: 1024px) {
            .sidebar { width: 0; }
            .sidebar.mobile-open { width: var(--sidebar-w-expanded); }
            .main-wrapper { margin-right: 0; padding: 20px; }
            .charts-wrapper { grid-template-columns: 1fr; }
            .menu-toggle { display: flex; position: fixed; bottom: 20px; left: 20px; width: 50px; height: 50px; background: var(--primary); border-radius: 50%; z-index: 1100; align-items: center; justify-content: center; color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        }
        @media (min-width: 1025px) { .menu-toggle { display: none; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-section">
            <div class="brand-logo"><i class="fa-solid fa-layer-group"></i></div>
            <div class="brand-name">Admin Console</div>
        </div>

        <nav class="nav-items">
            <a href="index.php" class="nav-item active">
                <div class="nav-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="nav-label">مرکز کنترل</div>
            </a>
            <a href="invoice.php" class="nav-item">
                <div class="nav-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div class="nav-label">مدیریت سفارشات</div>
            </a>
            <a href="user.php" class="nav-item">
                <div class="nav-icon"><i class="fa-solid fa-users-gear"></i></div>
                <div class="nav-label">مشترکین</div>
            </a>
            <a href="product.php" class="nav-item">
                <div class="nav-icon"><i class="fa-solid fa-box-archive"></i></div>
                <div class="nav-label">کاتالوگ محصولات</div>
            </a>
            <a href="server_status.php" class="nav-item">
                <div class="nav-icon"><i class="fa-solid fa-server"></i></div>
                <div class="nav-label">منابع و سرور</div>
            </a>
            <a href="support.php" class="nav-item">
                <div class="nav-icon"><i class="fa-solid fa-headset"></i></div>
                <div class="nav-label">پشتیبانی</div>
            </a>
        </nav>

        <div class="user-profile">
            <div class="user-avatar">A</div>
            <div class="user-info">
                <div class="user-name">مدیر ارشد</div>
                <div class="user-role">Super Admin</div>
            </div>
            <a href="logout.php" title="خروج" style="margin-right: auto; color: var(--text-tertiary); hover: color: #EF4444;"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </aside>

    <!-- Mobile Toggle -->
    <div class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('mobile-open')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        
        <!-- Header -->
        <header class="top-header animate">
            <div class="welcome-msg">
                <h1><?php echo $greet; ?></h1>
                <p>
                    <i class="<?php echo $greetIcon; ?>" style="color: var(--accent-gold);"></i>
                    سامانه نظارت بر عملکرد و فروش
                </p>
            </div>
            <div class="header-actions">
                <div class="action-btn date-badge">
                    <i class="fa-regular fa-calendar-check" style="margin-left: 8px;"></i>
                    <?php echo $todayDate; ?>
                </div>
                <div class="action-btn" title="اعلان‌ها"><i class="fa-regular fa-bell"></i></div>
                <div class="action-btn" title="تنظیمات"><i class="fa-solid fa-gear"></i></div>
            </div>
        </header>

        <!-- KPI Cards -->
        <section class="kpi-grid animate d-1">
            <!-- Sales -->
            <div class="kpi-card k-sales">
                <div class="kpi-header">
                    <div class="kpi-icon"><i class="fa-solid fa-coins"></i></div>
                    <div class="kpi-trend"><i class="fa-solid fa-arrow-trend-up"></i> +12%</div>
                </div>
                <div class="kpi-value"><?php echo number_format($stats['sales_volume']); ?></div>
                <div class="kpi-label">درآمد کل دوره (تومان)</div>
            </div>

            <!-- Orders -->
            <div class="kpi-card k-orders">
                <div class="kpi-header">
                    <div class="kpi-icon"><i class="fa-solid fa-receipt"></i></div>
                </div>
                <div class="kpi-value"><?php echo number_format($stats['orders_count']); ?></div>
                <div class="kpi-label">سفارشات موفق</div>
            </div>

            <!-- Users -->
            <div class="kpi-card k-users">
                <div class="kpi-header">
                    <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="kpi-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="kpi-label">مشترکین فعال سیستم</div>
            </div>

            <!-- New Users -->
            <div class="kpi-card k-new">
                <div class="kpi-header">
                    <div class="kpi-icon"><i class="fa-solid fa-user-plus"></i></div>
                    <div class="kpi-trend" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">امروز</div>
                </div>
                <div class="kpi-value"><?php echo number_format($stats['new_users_24h']); ?></div>
                <div class="kpi-label">ورودی جدید</div>
            </div>
        </section>

        <!-- Operations -->
        <section class="ops-section animate d-2">
            <div class="section-title"><i class="fa-solid fa-sliders"></i> عملیات اجرایی</div>
            <div class="ops-grid">
                <a href="invoice.php" class="op-card">
                    <div class="op-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                    <div class="op-info">
                        <span class="op-title">لیست سفارشات</span>
                        <span class="op-desc">بررسی و مدیریت فاکتورها</span>
                    </div>
                </a>
                <a href="user.php" class="op-card">
                    <div class="op-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <div class="op-info">
                        <span class="op-title">مدیریت کاربران</span>
                        <span class="op-desc">کنترل دسترسی‌ها</span>
                    </div>
                </a>
                <a href="inbound.php" class="op-card">
                    <div class="op-icon"><i class="fa-solid fa-network-wired"></i></div>
                    <div class="op-info">
                        <span class="op-title">کانفیگ شبکه</span>
                        <span class="op-desc">تنظیمات اتصال سرور</span>
                    </div>
                </a>
                <a href="server_status.php" class="op-card">
                    <div class="op-icon"><i class="fa-solid fa-microchip"></i></div>
                    <div class="op-info">
                        <span class="op-title">مانیتورینگ</span>
                        <span class="op-desc">وضعیت منابع سخت‌افزاری</span>
                    </div>
                </a>
                <a href="cancelService.php" class="op-card" style="border-color: rgba(239, 68, 68, 0.3);">
                    <div class="op-icon" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;"><i class="fa-solid fa-ban"></i></div>
                    <div class="op-info">
                        <span class="op-title">مسدودسازی</span>
                        <span class="op-desc">قطع دسترسی سرویس</span>
                    </div>
                </a>
            </div>
        </section>

        <!-- Filters -->
        <form method="get" class="filter-dock animate d-3">
            <div style="flex: 2; position: relative;">
                <input type="text" id="dateRange" class="input-glass" placeholder="انتخاب بازه زمانی..." readonly>
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($filterFrom ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($filterTo ?? ''); ?>">
                <i class="fa-regular fa-calendar" style="position: absolute; left: 15px; top: 14px; color: var(--text-tertiary);"></i>
            </div>
            <div style="flex: 2;">
                <select name="status[]" multiple class="input-glass">
                    <?php foreach($statusConfig as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $filterStatus) ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-glass">
                <i class="fa-solid fa-filter"></i>
                اعمال فیلتر
            </button>
            <?php if($filterFrom || !empty($filterStatus)): ?>
                <a href="index.php" class="btn-glass" style="background: rgba(255,255,255,0.1); width: auto;">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Charts -->
        <div class="charts-wrapper animate d-3">
            <div class="chart-panel">
                <div class="chart-head">
                    <div class="chart-label"><i class="fa-solid fa-chart-area" style="color: var(--primary);"></i> تحلیل جامع درآمد</div>
                </div>
                <div style="height: 320px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 24px;">
                <div class="chart-panel" style="flex: 1;">
                    <div class="chart-head">
                        <div class="chart-label"><i class="fa-solid fa-chart-pie" style="color: var(--accent-gold);"></i> تفکیک وضعیت‌ها</div>
                    </div>
                    <div style="height: 180px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-panel" style="flex: 1;">
                    <div class="chart-head">
                        <div class="chart-label"><i class="fa-solid fa-user-plus" style="color: var(--accent-teal);"></i> روند رشد کاربر</div>
                    </div>
                    <div style="height: 140px;">
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
        // --- Date Picker Logic ---
        $(function(){
            var start = moment().subtract(13, 'days');
            var end = moment();
            var phpFrom = '<?php echo $filterFrom; ?>';
            var phpTo = '<?php echo $filterTo; ?>';
            
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

        // --- Premium Chart Config ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#94A3B8';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.04)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(2, 6, 23, 0.95)';
        Chart.defaults.plugins.tooltip.padding = 16;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.titleFont = { size: 14, weight: 'bold' };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 13 };
        Chart.defaults.plugins.tooltip.displayColors = false;

        // Data Injection
        const dataSales = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dataPie = { labels: <?php echo json_encode($pieLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($pieValues); ?>, colors: <?php echo json_encode($pieColors); ?> };
        const dataUsers = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Executive Area)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gradSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gradSales.addColorStop(0, '#6366F1');
        gradSales.addColorStop(1, 'rgba(99, 102, 241, 0.05)');

        new Chart(ctxSales, {
            type: 'line', // Changed to line for smoother look
            data: {
                labels: dataSales.labels,
                datasets: [{
                    label: 'فروش',
                    data: dataSales.values,
                    borderColor: '#6366F1',
                    backgroundColor: gradSales,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    borderWidth: 2
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

        // 2. Status Chart (Refined Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dataPie.labels,
                datasets: [{
                    data: dataPie.values,
                    backgroundColor: dataPie.colors,
                    borderWidth: 0,
                    hoverOffset: 12
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '80%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 8, padding: 12, font: { size: 11 }, usePointStyle: true, color: '#94A3B8' } }
                }
            }
        });

        // 3. User Chart (Minimalist Bar)
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        new Chart(ctxUser, {
            type: 'bar',
            data: {
                labels: dataUsers.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: dataUsers.values,
                    backgroundColor: '#14B8A6',
                    borderRadius: 4,
                    barThickness: 10
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