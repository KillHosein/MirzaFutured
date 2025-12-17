<?php
// --- سیستم مدیریت سازمانی (Enterprise Core) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

session_start();

// --- لایه وابستگی‌ها ---
$configPath = '../config.php';
$jdfPath = '../jdf.php';

if (file_exists($configPath)) require_once $configPath;
if (file_exists($jdfPath)) require_once $jdfPath;

$isConnected = isset($pdo) && ($pdo instanceof PDO);

// --- تنظیمات زمانی ---
$dateYesterday = time() - 86400;
$filterFrom = isset($_GET['from']) ? $_GET['from'] : null;
$filterTo = isset($_GET['to']) ? $_GET['to'] : null;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : [];

if (!is_array($filterStatus)) $filterStatus = (!empty($filterStatus)) ? [$filterStatus] : [];

// 1. گارد امنیتی
if ($isConnected) {
    if (!isset($_SESSION["user"])) { header('Location: login.php'); exit; }
    try {
        $stmt = $pdo->prepare("SELECT id, username FROM admin WHERE username=:username LIMIT 1");
        $stmt->execute(['username' => $_SESSION["user"]]);
        if (!$stmt->fetch()) { session_destroy(); header('Location: login.php'); exit; }
    } catch (PDOException $e) { error_log("Security Error: " . $e->getMessage()); }
}

// 2. ساختار کوئری
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
    $placeholders = [];
    foreach ($filterStatus as $i => $status) {
        $ph = ":status_$i";
        $placeholders[] = $ph;
        $queryParams[$ph] = $status;
    }
    $whereClauses[] = "status IN (" . implode(', ', $placeholders) . ")";
} else {
    $whereClauses[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";
}

$whereSql = implode(' AND ', $whereClauses);

// 3. استخراج داده‌های آماری
$stats = ['sales' => 0, 'orders' => 0, 'users' => 0, 'new_users' => 0];
$chartData = ['sales' => [], 'status' => [], 'growth' => []];

if ($isConnected) {
    try {
        // KPI: Sales Volume
        $stmt = $pdo->prepare("SELECT SUM(price_product) as total FROM invoice WHERE $whereSql AND status != 'unpaid'");
        $stmt->execute($queryParams);
        $stats['sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // KPI: Users
        $stats['users'] = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();

        // KPI: New Users (24h)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :ts AND register != 'none'");
        $stmt->execute([':ts' => $dateYesterday]);
        $stats['new_users'] = $stmt->fetchColumn();

        // KPI: Orders Count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $whereSql AND status != 'unpaid'");
        $stmt->execute($queryParams);
        $stats['orders'] = $stmt->fetchColumn();

        // Chart: Sales Trend
        if ($stats['orders'] > 0) {
            $stmt = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $whereSql AND status != 'unpaid' ORDER BY time_sell DESC LIMIT 60");
            $stmt->execute($queryParams);
            $rawSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($rawSales as $row) {
                if (!is_numeric($row['time_sell'])) continue;
                $d = date('Y/m/d', (int)$row['time_sell']);
                if (!isset($grouped[$d])) $grouped[$d] = 0;
                $grouped[$d] += (int)$row['price_product'];
            }
            ksort($grouped);
            $chartData['sales'] = $grouped;
        }

        // Chart: Status Distribution
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM invoice WHERE $whereSql GROUP BY status");
        $stmt->execute($queryParams);
        $chartData['status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chart: User Growth
        $uStart = ($filterFrom && strtotime($filterFrom)) ? strtotime($filterFrom) : (time() - (13 * 86400));
        $uEnd = ($filterTo && strtotime($filterTo)) ? strtotime($filterTo . ' 23:59:59') : time();
        
        $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register BETWEEN :s AND :e");
        $stmt->execute([':s' => $uStart, ':e' => $uEnd]);
        $rawUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $period = new DatePeriod(new DateTime("@$uStart"), new DateInterval('P1D'), new DateTime("@$uEnd"));
        $userTrend = [];
        foreach ($period as $dt) $userTrend[$dt->format('Y/m/d')] = 0;
        
        foreach ($rawUsers as $u) {
            if (!is_numeric($u['register'])) continue;
            $d = date('Y/m/d', (int)$u['register']);
            if (isset($userTrend[$d])) $userTrend[$d]++;
        }
        $chartData['growth'] = $userTrend;

    } catch (Exception $e) {}
}

// Data Preparation
$salesLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['sales']));
$salesValues = array_values($chartData['sales']);
$userLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['growth']));
$userValues = array_values($chartData['growth']);

// Status Colors & Labels
$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت', 'active' => 'فعال', 'disabledn' => 'غیرفعال',
    'end_of_time' => 'پایان زمان', 'end_of_volume' => 'پایان حجم', 'sendedwarn' => 'هشدار تمدید',
    'send_on_hold' => 'در انتظار اتصال', 'removebyuser' => 'حذف شده'
];
$statusColors = [
    'unpaid' => '#fbbf24', 'active' => '#10b981', 'disabledn' => '#64748b',
    'end_of_time' => '#ef4444', 'end_of_volume' => '#3b82f6', 'sendedwarn' => '#a855f7',
    'send_on_hold' => '#f97316', 'removebyuser' => '#334155'
];

$pieLabels = []; $pieValues = []; $pieColorsList = [];
foreach ($chartData['status'] as $r) {
    $k = $r['status'];
    $pieLabels[] = $statusMapFa[$k] ?? $k;
    $pieValues[] = (int)$r['cnt'];
    $pieColorsList[] = $statusColors[$k] ?? '#fff';
}

$hour = date('H');
$greet = ($hour < 12) ? "صبح بخیر" : (($hour < 18) ? "ظهر بخیر" : "عصر بخیر");
$today = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل مدیریت | نسخه حرفه‌ای</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* --- Deep Ocean Palette --- */
            --bg-body: #0B1120;        /* Deepest Slate */
            --bg-sidebar: #111827;     /* Dark Gray */
            --bg-card: rgba(31, 41, 55, 0.7); /* Glassy Panel */
            
            --primary: #6366f1;        /* Indigo */
            --primary-glow: rgba(99, 102, 241, 0.4);
            --accent: #06b6d4;         /* Cyan */
            --success: #10b981;        /* Emerald */
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --shadow-glass: 0 8px 32px rgba(0, 0, 0, 0.4);
            
            --radius-lg: 20px;
            --radius-md: 14px;
            --sidebar-w: 260px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            overflow-x: hidden;
            /* Modern Mesh Gradient */
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        
        /* --- Animations --- */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; }

        /* --- Layout --- */
        .app-container { display: flex; min-height: 100vh; }
        .main-content {
            flex: 1;
            margin-right: var(--sidebar-w);
            padding: 30px 40px;
            transition: margin-right 0.3s;
        }

        /* --- Sidebar --- */
        .sidebar {
            position: fixed; top: 0; right: 0; height: 100%;
            width: var(--sidebar-w);
            background: var(--bg-sidebar);
            border-left: var(--border-glass);
            display: flex; flex-direction: column;
            z-index: 1000;
            padding: 25px;
            transition: width 0.3s;
        }
        
        .brand {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 40px; padding: 0 10px;
            color: #fff; font-size: 1.4rem; font-weight: 800;
        }
        .brand i { color: var(--primary); font-size: 1.6rem; text-shadow: 0 0 15px var(--primary-glow); }

        .nav-menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .nav-item {
            display: flex; align-items: center; gap: 15px;
            padding: 14px 18px; border-radius: var(--radius-md);
            color: var(--text-muted); font-weight: 500;
            transition: 0.3s;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        .nav-item.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border-right: 3px solid var(--primary);
        }
        .nav-item i { font-size: 1.2rem; width: 24px; text-align: center; }

        .user-mini {
            margin-top: auto; padding: 15px;
            background: rgba(255,255,255,0.03);
            border-radius: var(--radius-md);
            display: flex; align-items: center; gap: 12px;
        }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #818cf8);
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; color: #fff;
        }

        /* --- Header --- */
        .top-bar {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 40px;
        }
        .greeting h1 { font-size: 2rem; font-weight: 800; color: #fff; margin: 0; }
        .greeting p { color: var(--text-muted); margin-top: 5px; font-size: 0.95rem; }
        
        .date-badge {
            background: rgba(15, 23, 42, 0.6); border: var(--border-glass);
            padding: 10px 20px; border-radius: 50px;
            display: flex; align-items: center; gap: 10px;
            color: var(--text-main); font-size: 0.9rem;
        }

        /* --- Stats Cards --- */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px; margin-bottom: 40px;
        }
        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 25px;
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; gap: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
            border-color: rgba(255,255,255,0.15);
        }
        
        /* Glass Shine Effect */
        .stat-card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(120deg, rgba(255,255,255,0) 30%, rgba(255,255,255,0.05) 50%, rgba(255,255,255,0) 70%);
            transform: translateX(-100%); transition: 0.6s;
        }
        .stat-card:hover::after { transform: translateX(100%); }

        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: #fff;
        }
        .stat-val { font-size: 1.8rem; font-weight: 800; color: #fff; }
        .stat-label { font-size: 0.9rem; color: var(--text-muted); }

        /* Colors */
        .bg-indigo { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
        .bg-emerald { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .bg-rose { background: rgba(244, 63, 94, 0.15); color: #fb7185; }
        .bg-cyan { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }

        /* --- Quick Actions --- */
        .actions-header { font-size: 1.1rem; color: #fff; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .actions-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 20px; margin-bottom: 40px;
        }
        .action-btn {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex; flex-direction: column; align-items: center; gap: 12px;
            text-decoration: none; color: var(--text-muted);
            transition: 0.3s;
        }
        .action-btn:hover {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--primary);
            transform: translateY(-4px);
            color: #fff;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .action-btn i { font-size: 1.8rem; transition: 0.3s; }
        .action-btn:hover i { transform: scale(1.15); color: var(--primary); }
        .action-btn.danger:hover { border-color: #ef4444; }
        .action-btn.danger:hover i { color: #ef4444; }

        /* --- Filter --- */
        .filter-glass {
            background: rgba(17, 24, 39, 0.6);
            border: 1px dashed rgba(255,255,255,0.1);
            border-radius: var(--radius-lg); padding: 15px;
            display: flex; flex-wrap: wrap; gap: 15px;
            margin-bottom: 30px; align-items: center;
        }
        .input-glass {
            flex: 1; min-width: 200px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 12px 15px; border-radius: 12px;
            transition: 0.3s;
        }
        .input-glass:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); }
        
        .btn-glass {
            background: var(--primary); color: #fff; border: none;
            padding: 12px 25px; border-radius: 12px; font-weight: 600;
            cursor: pointer; box-shadow: 0 4px 15px var(--primary-glow);
            transition: 0.3s; display: flex; align-items: center; gap: 8px;
        }
        .btn-glass:hover { transform: translateY(-2px); box-shadow: 0 8px 25px var(--primary-glow); }

        /* --- Charts --- */
        .charts-section { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .chart-card {
            background: var(--bg-card);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 25px;
        }
        .chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-title { font-size: 1.1rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { width: 0; padding: 0; overflow: hidden; }
            .sidebar.active { width: 260px; padding: 25px; box-shadow: 0 0 100px rgba(0,0,0,0.8); }
            .main-content { margin-right: 0; padding: 20px; }
            .charts-section { grid-template-columns: 1fr; }
            .mobile-trigger { display: flex; position: fixed; bottom: 20px; left: 20px; width: 50px; height: 50px; background: var(--primary); border-radius: 50%; color: #fff; align-items: center; justify-content: center; z-index: 2000; box-shadow: 0 5px 20px rgba(0,0,0,0.5); font-size: 1.4rem; cursor: pointer; }
        }
        @media (min-width: 1025px) { .mobile-trigger { display: none; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <i class="fa-solid fa-cube"></i>
            <span>ADMIN PANEL</span>
        </div>
        
        <nav class="nav-menu">
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-house"></i> <span>داشبورد</span>
            </a>
            <a href="invoice.php" class="nav-item">
                <i class="fa-solid fa-file-invoice-dollar"></i> <span>سفارشات</span>
            </a>
            <a href="user.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>کاربران</span>
            </a>
            <a href="product.php" class="nav-item">
                <i class="fa-solid fa-box-open"></i> <span>محصولات</span>
            </a>
            <a href="server_status.php" class="nav-item">
                <i class="fa-solid fa-server"></i> <span>وضعیت سرور</span>
            </a>
            <a href="support.php" class="nav-item">
                <i class="fa-solid fa-headset"></i> <span>پشتیبانی</span>
            </a>
        </nav>

        <div class="user-mini">
            <div class="user-avatar">AD</div>
            <div style="font-size: 0.9rem;">
                <div style="color: #fff; font-weight: bold;">مدیر کل</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Super Admin</div>
            </div>
            <a href="logout.php" style="margin-right: auto; color: #ef4444;"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </aside>

    <div class="mobile-trigger" onclick="document.getElementById('sidebar').classList.toggle('active')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Header -->
        <header class="top-bar anim">
            <div class="greeting">
                <h1><?php echo $greet; ?></h1>
                <p>خوش آمدید، وضعیت سیستم پایدار است.</p>
            </div>
            <div class="date-badge">
                <i class="fa-regular fa-calendar-alt" style="color: var(--accent);"></i>
                <?php echo $today; ?>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-row anim d-1">
            <div class="stat-card">
                <div style="display: flex; justify-content: space-between;">
                    <div class="stat-icon bg-indigo"><i class="fa-solid fa-wallet"></i></div>
                    <span style="color: #34d399; font-size: 0.8rem; background: rgba(16,185,129,0.1); padding: 2px 8px; border-radius: 10px; height: fit-content;">+12%</span>
                </div>
                <div>
                    <div class="stat-val"><?php echo $formatted_total_sales; ?></div>
                    <div class="stat-label">فروش کل (تومان)</div>
                </div>
            </div>

            <div class="stat-card">
                <div style="display: flex; justify-content: space-between;">
                    <div class="stat-icon bg-emerald"><i class="fa-solid fa-receipt"></i></div>
                </div>
                <div>
                    <div class="stat-val"><?php echo number_format($resultcontsell); ?></div>
                    <div class="stat-label">سفارشات موفق</div>
                </div>
            </div>

            <div class="stat-card">
                <div style="display: flex; justify-content: space-between;">
                    <div class="stat-icon bg-rose"><i class="fa-solid fa-users"></i></div>
                </div>
                <div>
                    <div class="stat-val"><?php echo number_format($resultcount); ?></div>
                    <div class="stat-label">کاربران سیستم</div>
                </div>
            </div>

            <div class="stat-card">
                <div style="display: flex; justify-content: space-between;">
                    <div class="stat-icon bg-cyan"><i class="fa-solid fa-user-plus"></i></div>
                </div>
                <div>
                    <div class="stat-val"><?php echo number_format($resultcountday); ?></div>
                    <div class="stat-label">ثبت نام امروز</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-header anim d-2">
            <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> دسترسی سریع
        </div>
        <div class="actions-grid anim d-2">
            <a href="invoice.php" class="action-btn">
                <i class="fa-solid fa-file-invoice"></i>
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
                <span>کانفیگ</span>
            </a>
            <a href="payment.php" class="action-btn">
                <i class="fa-solid fa-credit-card"></i>
                <span>تراکنش‌ها</span>
            </a>
            <a href="server_status.php" class="action-btn">
                <i class="fa-solid fa-microchip"></i>
                <span>وضعیت سرور</span>
            </a>
            <a href="cancelService.php" class="action-btn danger">
                <i class="fa-solid fa-ban"></i>
                <span>مسدودسازی</span>
            </a>
        </div>

        <!-- Filter -->
        <form method="get" class="filter-glass anim d-3">
            <div style="flex: 2; position: relative;">
                <input type="text" id="dateRange" class="input-glass" placeholder="انتخاب تاریخ..." readonly>
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($filterFrom ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($filterTo ?? ''); ?>">
                <i class="fa-solid fa-calendar-day" style="position: absolute; left: 15px; top: 14px; color: var(--text-muted); pointer-events: none;"></i>
            </div>
            
            <div style="flex: 2;">
                <select name="status[]" multiple class="input-glass" style="min-height: 48px;">
                    <?php foreach($statusMapFa as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $filterStatus) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-glass">
                <i class="fa-solid fa-filter"></i> فیلتر
            </button>
            <?php if($filterFrom || !empty($filterStatus)): ?>
                <a href="index.php" class="btn-glass" style="background: rgba(255,255,255,0.1); width: auto;">
                    <i class="fa-solid fa-rotate-right"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Charts -->
        <div class="charts-section anim d-3">
            <div class="chart-card">
                <div class="chart-head">
                    <div class="chart-title"><i class="fa-solid fa-chart-area" style="color: var(--primary);"></i> نمودار فروش</div>
                </div>
                <div style="height: 350px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 25px;">
                <div class="chart-card" style="flex: 1;">
                    <div class="chart-head">
                        <div class="chart-title"><i class="fa-solid fa-chart-pie" style="color: var(--accent);"></i> وضعیت‌ها</div>
                    </div>
                    <div style="height: 200px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-card" style="flex: 1;">
                    <div class="chart-head">
                        <div class="chart-title"><i class="fa-solid fa-arrow-trend-up" style="color: var(--success);"></i> رشد کاربران</div>
                    </div>
                    <div style="height: 150px;">
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
            var start = moment().subtract(13, 'days');
            var end = moment();
            var phpFrom = '<?php echo $filterFrom; ?>';
            var phpTo = '<?php echo $filterTo; ?>';
            
            if(phpFrom && phpTo){ start = moment(phpFrom); end = moment(phpTo); }

            $('#dateRange').daterangepicker({
                startDate: start, endDate: end, opens: 'left',
                locale: { format: 'YYYY/MM/DD', applyLabel: 'تایید', cancelLabel: 'لغو' }
            }, function(s, e) {
                $('#dateRange').val(s.format('YYYY/MM/DD') + ' - ' + e.format('YYYY/MM/DD'));
                $('#inputFrom').val(s.format('YYYY-MM-DD'));
                $('#inputTo').val(e.format('YYYY-MM-DD'));
            });
            
            if(phpFrom) $('#dateRange').val(start.format('YYYY/MM/DD') + ' - ' + end.format('YYYY/MM/DD'));
        });

        // --- Modern Chart Config ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.03)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        
        const salesData = { labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($salesValues); ?> };
        const statusData = { labels: <?php echo json_encode($pieLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($pieValues); ?>, colors: <?php echo json_encode($pieColorsList); ?> };
        const userData = { labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Gradient Area)
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const gradSales = ctxSales.createLinearGradient(0, 0, 0, 400);
        gradSales.addColorStop(0, 'rgba(99, 102, 241, 0.5)');
        gradSales.addColorStop(1, 'rgba(99, 102, 241, 0)');

        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'فروش',
                    data: salesData.values,
                    borderColor: '#6366f1',
                    backgroundColor: gradSales,
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
                scales: { 
                    x: { grid: { display: false } }, 
                    y: { beginAtZero: true, border: { display: false } } 
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
                    borderWidth: 0, hoverOffset: 15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true, color: '#94a3b8' } } }
            }
        });

        // 3. User Chart (Bar)
        const ctxUser = document.getElementById('usersChart').getContext('2d');
        const gradUser = ctxUser.createLinearGradient(0, 0, 0, 200);
        gradUser.addColorStop(0, '#10b981');
        gradUser.addColorStop(1, 'rgba(16, 185, 129, 0.2)');

        new Chart(ctxUser, {
            type: 'bar',
            data: {
                labels: userData.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userData.values,
                    backgroundColor: gradUser,
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