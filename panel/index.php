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
        $stmt = $pdo->prepare("SELECT SUM(price_product) as total FROM invoice WHERE $whereSql AND status != 'unpaid'");
        $stmt->execute($queryParams);
        $stats['sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        $stats['users'] = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :ts AND register != 'none'");
        $stmt->execute([':ts' => $dateYesterday]);
        $stats['new_users'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $whereSql AND status != 'unpaid'");
        $stmt->execute($queryParams);
        $stats['orders'] = $stmt->fetchColumn();

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

        $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM invoice WHERE $whereSql GROUP BY status");
        $stmt->execute($queryParams);
        $chartData['status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت', 'active' => 'فعال', 'disabledn' => 'غیرفعال',
    'end_of_time' => 'پایان زمان', 'end_of_volume' => 'پایان حجم', 'sendedwarn' => 'هشدار تمدید',
    'send_on_hold' => 'در انتظار اتصال', 'removebyuser' => 'حذف شده'
];
$pieLabels = []; $pieValues = [];
foreach ($chartData['status'] as $r) {
    $k = $r['status'];
    $pieLabels[] = $statusMapFa[$k] ?? $k;
    $pieValues[] = (int)$r['cnt'];
}

$hour = date('H');
$greet = ($hour < 12) ? "صبح بخیر" : (($hour < 18) ? "روز خوش" : "عصر بخیر");
$today = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل مدیریت | نسخه شناور</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* Palette: Deep Space & Neon */
            --bg-void: #02040a;
            --bg-surface: rgba(18, 24, 38, 0.75);
            --bg-dock: rgba(10, 10, 15, 0.85);
            
            --neon-blue: #3b82f6;
            --neon-purple: #8b5cf6;
            --neon-teal: #14b8a6;
            --neon-amber: #f59e0b;
            
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            
            --border-subtle: 1px solid rgba(255, 255, 255, 0.06);
            --border-glow: 1px solid rgba(255, 255, 255, 0.12);
            
            --shadow-float: 0 15px 40px rgba(0,0,0,0.6);
            --glow-soft: 0 0 20px rgba(59, 130, 246, 0.15);
            
            --radius-lg: 24px;
            --radius-pill: 50px;
        }

        /* --- Base Setup --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-void);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            /* Starry Background */
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(59, 130, 246, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.06) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 120px; /* Space for dock */
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Animations --- */
        @keyframes floatUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 10px var(--glow-color, rgba(59,130,246,0.3)); }
            50% { box-shadow: 0 0 25px var(--glow-color, rgba(59,130,246,0.6)); }
        }
        
        .anim-stagger { opacity: 0; animation: floatUp 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
        .d-1 { animation-delay: 0.1s; }
        .d-2 { animation-delay: 0.2s; }
        .d-3 { animation-delay: 0.3s; }
        .d-4 { animation-delay: 0.4s; }

        /* --- Layout: Full Width & Fluid --- */
        .container-fluid-custom {
            width: 100%;
            padding: 40px 5%; /* 5% padding sides */
            max-width: 1800px;
            margin: 0 auto;
        }

        /* --- Header --- */
        .header-top {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px;
            padding-bottom: 20px; border-bottom: var(--border-subtle);
        }
        .header-title h1 {
            font-size: 3rem; font-weight: 900; color: #fff; margin: 0;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-title p {
            color: var(--text-dim); margin-top: 5px; font-size: 1.1rem; 
            display: flex; align-items: center; gap: 10px;
        }
        
        .user-pill {
            background: rgba(255,255,255,0.05); border: var(--border-glass);
            padding: 8px 16px; border-radius: var(--radius-pill);
            display: flex; align-items: center; gap: 10px;
            backdrop-filter: blur(10px);
        }
        .user-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            display: flex; align-items: center; justify-content: center; font-weight: bold;
        }

        /* --- Stats Grid (Full Width) --- */
        .stats-deck {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px; margin-bottom: 50px;
        }
        .stat-card {
            background: var(--bg-surface);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 180px; position: relative; overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .stat-card:hover {
            transform: translateY(-10px);
            border-color: rgba(255,255,255,0.15);
            box-shadow: var(--shadow-float), 0 0 20px var(--glow-c);
        }
        /* Background Glow */
        .stat-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, var(--glow-c) 0%, transparent 70%);
            opacity: 0.05; transition: 0.5s; pointer-events: none;
        }
        .stat-card:hover::before { opacity: 0.15; }

        .stat-top { display: flex; justify-content: space-between; align-items: flex-start; z-index: 1; }
        .stat-icon { font-size: 2rem; color: var(--text-dim); transition: 0.3s; }
        .stat-card:hover .stat-icon { color: #fff; transform: scale(1.2); text-shadow: 0 0 15px var(--glow-c); }
        
        .stat-val { font-size: 2.8rem; font-weight: 800; color: #fff; margin-top: auto; z-index: 1; }
        .stat-lbl { font-size: 1rem; color: var(--text-dim); margin-top: 5px; z-index: 1; }

        /* Colors */
        .s-blue { --glow-c: var(--neon-blue); }
        .s-teal { --glow-c: var(--neon-teal); }
        .s-purple { --glow-c: var(--neon-purple); }
        .s-amber { --glow-c: var(--neon-amber); }

        /* --- Quick Actions (Tiles) --- */
        .action-deck {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px; margin-bottom: 60px;
        }
        .action-tile {
            background: rgba(255,255,255,0.02);
            border: var(--border-glass); border-radius: 20px;
            padding: 25px; text-align: center;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px;
            transition: 0.4s; cursor: pointer; height: 160px;
        }
        .action-tile:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--neon-blue);
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .tile-icon { font-size: 2.5rem; color: var(--text-dim); transition: 0.3s; }
        .action-tile:hover .tile-icon { color: #fff; transform: translateY(-5px); filter: drop-shadow(0 0 8px var(--neon-blue)); }
        .tile-text { font-size: 1.1rem; font-weight: 600; color: var(--text-main); }

        /* --- Charts Area (Full Width) --- */
        .charts-grid {
            display: grid; grid-template-columns: 2.5fr 1fr; gap: 30px; margin-bottom: 40px;
        }
        .chart-box {
            background: var(--bg-surface);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px; position: relative;
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .chart-title { font-size: 1.4rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 12px; }

        /* --- Floating Dock (The Star) --- */
        .dock-wrapper {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            z-index: 2000; perspective: 1000px;
        }
        .dock {
            display: flex; align-items: center; gap: 12px;
            background: var(--bg-dock);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px; padding: 12px 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        .dock:hover { border-color: rgba(255,255,255,0.2); }

        .dock-item {
            position: relative;
            width: 55px; height: 55px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 16px;
            color: var(--text-dim);
            font-size: 1.6rem;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-decoration: none;
        }
        /* Dock Hover Effect (MacOS style) */
        .dock-item:hover {
            width: 70px; height: 70px; /* Scale up */
            font-size: 2rem;
            color: #fff;
            background: rgba(255,255,255,0.1);
            transform: translateY(-15px); /* Lift up */
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            margin: 0 10px; /* Push neighbors */
        }
        .dock-item.active {
            color: var(--neon-blue);
            background: rgba(59, 130, 246, 0.15);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.2);
        }
        .dock-item.active::after {
            content: ''; position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
            width: 5px; height: 5px; background: var(--neon-blue); border-radius: 50%;
            box-shadow: 0 0 5px var(--neon-blue);
        }

        /* Tooltip for Dock */
        .dock-tooltip {
            position: absolute; top: -45px; left: 50%; transform: translateX(-50%) scale(0.8);
            background: #000; color: #fff; padding: 6px 12px; border-radius: 8px;
            font-size: 0.85rem; opacity: 0; transition: 0.2s; pointer-events: none;
            border: 1px solid rgba(255,255,255,0.1); white-space: nowrap; font-weight: bold;
        }
        .dock-item:hover .dock-tooltip { opacity: 1; transform: translateX(-50%) scale(1); top: -55px; }

        /* --- Responsive --- */
        @media (max-width: 1200px) {
            .charts-grid { grid-template-columns: 1fr; }
            .dock { padding: 10px; gap: 8px; }
            .dock-item { width: 45px; height: 45px; font-size: 1.4rem; }
            .dock-item:hover { width: 55px; height: 55px; font-size: 1.6rem; margin: 0 5px; }
        }
        @media (max-width: 768px) {
            .header-top { flex-direction: column; align-items: flex-start; gap: 20px; }
            .stats-deck { grid-template-columns: 1fr; }
            .dock-wrapper { width: 90%; bottom: 20px; }
            .dock { width: 100%; justify-content: space-around; }
            .dock-item:hover { transform: translateY(-5px); margin: 0; width: 45px; height: 45px; font-size: 1.4rem; } /* Reduce effect on mobile */
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="header-top anim">
            <div class="header-title">
                <h1><?php echo $greet; ?>، ادمین</h1>
                <p>
                    <i class="<?php echo ($hour < 18) ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; ?>" style="color: var(--neon-amber);"></i>
                    وضعیت سیستم: <span style="color: var(--neon-teal);">پایدار و آنلاین</span>
                    <span style="margin: 0 10px; color: var(--text-dim);">|</span>
                    <?php echo $today; ?>
                </p>
            </div>
            <div class="user-pill">
                <span>مدیر سیستم</span>
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
            </div>
        </header>

        <!-- Stats Grid (Full Width) -->
        <section class="stats-deck anim d-1">
            <!-- Sales -->
            <div class="stat-card s-blue">
                <div class="stat-top">
                    <i class="fa-solid fa-sack-dollar stat-icon"></i>
                    <span style="background: rgba(59,130,246,0.1); color: var(--neon-blue); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">درآمد</span>
                </div>
                <div>
                    <div class="stat-val"><?php echo number_format($stats['sales']); ?></div>
                    <div class="stat-lbl">تومان فروش کل</div>
                </div>
            </div>

            <!-- Orders -->
            <div class="stat-card s-teal">
                <div class="stat-top">
                    <i class="fa-solid fa-file-invoice-dollar stat-icon"></i>
                    <span style="background: rgba(20,184,166,0.1); color: var(--neon-teal); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">تراکنش</span>
                </div>
                <div>
                    <div class="stat-val"><?php echo number_format($stats['orders']); ?></div>
                    <div class="stat-lbl">سفارش موفق</div>
                </div>
            </div>

            <!-- Users -->
            <div class="stat-card s-purple">
                <div class="stat-top">
                    <i class="fa-solid fa-users stat-icon"></i>
                    <span style="background: rgba(139,92,246,0.1); color: var(--neon-purple); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">کاربران</span>
                </div>
                <div>
                    <div class="stat-val"><?php echo number_format($stats['users']); ?></div>
                    <div class="stat-lbl">مشترکین فعال</div>
                </div>
            </div>

            <!-- Today -->
            <div class="stat-card s-amber">
                <div class="stat-top">
                    <i class="fa-solid fa-user-plus stat-icon"></i>
                    <span style="background: rgba(245,158,11,0.1); color: var(--neon-amber); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">امروز</span>
                </div>
                <div>
                    <div class="stat-val"><?php echo number_format($stats['new_users']); ?></div>
                    <div class="stat-lbl">ورودی جدید</div>
                </div>
            </div>
        </section>

        <!-- Action Tiles -->
        <section class="anim d-2">
            <h3 style="color: #fff; margin-bottom: 25px; font-size: 1.5rem;">عملیات سریع</h3>
            <div class="action-deck">
                <a href="invoice.php" class="action-tile">
                    <i class="fa-solid fa-list-check tile-icon"></i>
                    <span class="tile-text">سفارشات</span>
                </a>
                <a href="user.php" class="action-tile">
                    <i class="fa-solid fa-user-group tile-icon"></i>
                    <span class="tile-text">مدیریت کاربران</span>
                </a>
                <a href="inbound.php" class="action-tile">
                    <i class="fa-solid fa-sliders tile-icon"></i>
                    <span class="tile-text">کانفیگ‌ها</span>
                </a>
                <a href="payment.php" class="action-tile">
                    <i class="fa-solid fa-credit-card tile-icon"></i>
                    <span class="tile-text">امور مالی</span>
                </a>
                <a href="server_status.php" class="action-tile">
                    <i class="fa-solid fa-server tile-icon"></i>
                    <span class="tile-text">وضعیت ادمین</span>
                </a>
                <a href="cancelService.php" class="action-tile" style="border-color: rgba(239,68,68,0.3);">
                    <i class="fa-solid fa-ban tile-icon" style="color: #ef4444;"></i>
                    <span class="tile-text" style="color: #ef4444;">مسدودسازی</span>
                </a>
            </div>
        </section>

        <!-- Charts (Full width split) -->
        <section class="charts-grid anim d-3">
            <div class="chart-box">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fa-solid fa-chart-area" style="color: var(--neon-blue);"></i>
                        روند فروش و درآمد
                    </div>
                </div>
                <div style="height: 400px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-chart-pie" style="color: var(--neon-purple);"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="height: 220px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-arrow-trend-up" style="color: var(--neon-teal);"></i> رشد کاربر</div>
                    </div>
                    <div style="height: 180px;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Floating Dock (Bottom Navigation) -->
    <div class="dock-wrapper anim d-4">
        <nav class="dock">
            <a href="index.php" class="dock-item active">
                <i class="fa-solid fa-house"></i>
                <span class="dock-tooltip">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span class="dock-tooltip">سفارشات</span>
            </a>
            <a href="user.php" class="dock-item">
                <i class="fa-solid fa-users"></i>
                <span class="dock-tooltip">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <i class="fa-solid fa-box-open"></i>
                <span class="dock-tooltip">محصولات</span>
            </a>
            <a href="server_status.php" class="dock-item">
                <i class="fa-solid fa-server"></i>
                <span class="dock-tooltip">ادمین</span>
            </a>
            <div style="width: 1px; height: 30px; background: rgba(255,255,255,0.1); margin: 0 5px;"></div>
            <a href="logout.php" class="dock-item" style="color: #ef4444;">
                <i class="fa-solid fa-power-off"></i>
                <span class="dock-tooltip">خروج</span>
            </a>
        </nav>
    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // --- Chart Config: High-End Fluid ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.font.size = 14;
        Chart.defaults.color = '#64748B';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.03)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';
        Chart.defaults.plugins.tooltip.padding = 15;
        Chart.defaults.plugins.tooltip.cornerRadius = 10;
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales (Smooth Area Gradient)
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 400);
        gradS.addColorStop(0, '#3b82f6');
        gradS.addColorStop(1, 'rgba(59, 130, 246, 0.05)');

        new Chart(ctxS, {
            type: 'line',
            data: {
                labels: dSales.labels,
                datasets: [{
                    label: 'فروش', data: dSales.values,
                    borderColor: '#3b82f6', backgroundColor: gradS,
                    borderWidth: 3, pointRadius: 0, pointHoverRadius: 8, pointBackgroundColor: '#fff',
                    fill: true, tension: 0.4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    x: { grid: { display: false } }, 
                    y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(255,255,255,0.02)' } } 
                }
            }
        });

        // 2. Status (Neon Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels,
                datasets: [{
                    data: dPie.values,
                    backgroundColor: ['#f59e0b', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#8b5cf6', '#f97316', '#334155'],
                    borderWidth: 0, hoverOffset: 15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '80%',
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, color: '#94a3b8', font: {size: 12} } } }
            }
        });

        // 3. User Growth (Bar)
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels,
                datasets: [{
                    label: 'کاربر', data: dUsers.values,
                    backgroundColor: '#14b8a6', borderRadius: 4, barThickness: 15
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