<?php
// --- سیستم مدیریت سازمانی (Enterprise Core) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

session_start();

// --- لایه وابستگی‌ها ---
if (file_exists('../config.php')) require_once '../config.php';
if (file_exists('../jdf.php')) require_once '../jdf.php';

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
        // KPIs
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

        // Chart: Sales
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

        // Chart: Status
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM invoice WHERE $whereSql GROUP BY status");
        $stmt->execute($queryParams);
        $chartData['status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chart: Users
        $uStart = ($filterFrom && strtotime($filterFrom)) ? strtotime($filterFrom) : (time() - (14 * 86400));
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

// Luxury Status Colors
$statusConfig = [
    'unpaid'       => ['label' => 'در انتظار', 'color' => '#EAB308'],
    'active'       => ['label' => 'فعال',      'color' => '#10B981'],
    'disabledn'    => ['label' => 'مسدود',     'color' => '#64748B'],
    'end_of_time'  => ['label' => 'انقضا',     'color' => '#EF4444'],
    'end_of_volume'=> ['label' => 'حجم',       'color' => '#3B82F6'],
    'sendedwarn'   => ['label' => 'هشدار',     'color' => '#8B5CF6'],
    'send_on_hold' => ['label' => 'صف',        'color' => '#F97316'],
    'removebyuser' => ['label' => 'لغو',       'color' => '#475569']
];

$pieLabels = []; $pieValues = []; $pieColors = [];
foreach ($chartData['status'] as $r) {
    $k = $r['status'];
    $pieLabels[] = $statusConfig[$k]['label'] ?? $k;
    $pieValues[] = (int)$r['cnt'];
    $pieColors[] = $statusConfig[$k]['color'] ?? '#fff';
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
    <title>پنل مدیریت | نسخه پرمیوم</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* Palette: Deep Midnight & Neon Glow */
            --bg-void: #020205; /* Slightly lighter than pure black */
            --bg-surface: rgba(20, 20, 28, 0.6);
            --bg-dock: rgba(10, 10, 15, 0.85);
            
            /* High Contrast Neons */
            --neon-blue: #22d3ee;
            --neon-purple: #c084fc;
            --neon-teal: #2dd4bf;
            --neon-amber: #fbbf24;
            --neon-pink: #f472b6;
            --neon-red: #ef4444;
            
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            
            /* Glass Effects */
            --border-glass: 1px solid rgba(255, 255, 255, 0.06);
            --border-glow: 1px solid rgba(255, 255, 255, 0.12);
            
            --shadow-float: 0 20px 60px -10px rgba(0,0,0,0.8);
            --glow-text: 0 0 20px rgba(34, 211, 238, 0.3);
            
            --radius-lg: 24px;
            --radius-pill: 100px;
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
            /* New Background Pattern */
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                radial-gradient(circle at 50% 0%, rgba(34, 211, 238, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 85% 90%, rgba(192, 132, 252, 0.05) 0%, transparent 40%);
            background-size: 40px 40px, 40px 40px, 100% 100%, 100% 100%;
            background-attachment: fixed;
            padding-bottom: 180px; /* Space for the new taller dock */
        }

        a { text-decoration: none; color: inherit; transition: 0.3s ease; }

        /* --- Animations --- */
        @keyframes floatIn {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .anim-stagger { opacity: 0; animation: floatIn 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.15s; } .d-3 { animation-delay: 0.2s; } .d-4 { animation-delay: 0.25s; }

        /* --- Layout --- */
        .container-fluid-custom {
            width: 100%;
            padding: 40px 5%;
            max-width: 1800px;
            margin: 0 auto;
        }

        /* --- Header --- */
        .header-top {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 60px;
            padding-bottom: 25px; 
            border-bottom: 1px solid rgba(255,255,255,0.05);
            position: relative;
        }
        .header-top::after {
            content: ''; position: absolute; bottom: -1px; left: 0; width: 100px; height: 1px;
            background: linear-gradient(90deg, var(--neon-blue), transparent);
        }
        .header-title h1 {
            font-size: 3.5rem; font-weight: 900; color: #fff; margin: 0; line-height: 1.2;
            background: linear-gradient(to bottom right, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: -1.5px;
        }
        .header-title p {
            color: var(--text-dim); margin-top: 5px; font-size: 1.2rem; font-weight: 300;
            display: flex; align-items: center; gap: 12px;
        }
        
        .user-pill {
            background: rgba(255,255,255,0.03); border: var(--border-glass);
            padding: 8px 12px 8px 20px; border-radius: var(--radius-pill);
            display: flex; align-items: center; gap: 12px;
            backdrop-filter: blur(10px); font-size: 1rem; color: #fff;
            transition: 0.3s;
        }
        .user-pill:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.15); }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 15px rgba(34, 211, 238, 0.3); font-size: 1.1rem; color: #000;
        }

        /* --- Stats Grid --- */
        .stats-deck {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px; margin-bottom: 60px;
        }
        .stat-card {
            background: var(--bg-surface);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 200px; position: relative; overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .stat-card:hover {
            transform: translateY(-10px);
            background: rgba(30, 30, 40, 0.7);
            border-color: rgba(255,255,255,0.15);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5), 0 0 20px var(--glow-c);
        }
        .stat-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, var(--glow-c) 0%, transparent 70%);
            opacity: 0.05; transition: 0.5s; pointer-events: none;
        }
        .stat-card:hover::before { opacity: 0.15; }

        .stat-top { display: flex; justify-content: space-between; align-items: flex-start; z-index: 1; margin-bottom: 15px; }
        .stat-icon { font-size: 2.2rem; color: rgba(255,255,255,0.2); transition: 0.4s; }
        .stat-card:hover .stat-icon { color: #fff; transform: scale(1.1); text-shadow: 0 0 15px var(--glow-c); }
        
        .stat-val { 
            font-size: 3rem; font-weight: 800; color: #fff; margin-top: auto; z-index: 1; line-height: 1;
            font-feature-settings: "tnum"; letter-spacing: -1px;
        }
        .stat-lbl { font-size: 1rem; color: var(--text-dim); margin-top: 5px; z-index: 1; font-weight: 500; }

        .s-blue { --glow-c: var(--neon-blue); }
        .s-teal { --glow-c: var(--neon-teal); }
        .s-purple { --glow-c: var(--neon-purple); }
        .s-amber { --glow-c: var(--neon-amber); }

        /* --- Quick Actions --- */
        .section-header { font-size: 1.6rem; color: #fff; font-weight: 800; margin-bottom: 30px; display: flex; align-items: center; gap: 12px; }
        .section-header::after { content: ''; height: 2px; width: 40px; background: var(--neon-purple); border-radius: 2px; display: block; opacity: 0.7; }

        .action-deck {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px; margin-bottom: 60px;
        }
        .action-tile {
            background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%);
            border: var(--border-glass); border-radius: 20px;
            padding: 25px 15px; text-align: center;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
            transition: 0.4s; cursor: pointer; height: 150px;
            position: relative; overflow: hidden; text-decoration: none;
        }
        .action-tile:hover {
            background: rgba(255,255,255,0.06);
            border-color: var(--neon-blue);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }
        
        .tile-icon { font-size: 2.5rem; color: var(--text-dim); transition: 0.3s; }
        .action-tile:hover .tile-icon { color: #fff; transform: scale(1.1); filter: drop-shadow(0 0 10px var(--neon-blue)); }
        .tile-text { font-size: 1rem; font-weight: 600; color: var(--text-main); transition: 0.3s; }
        .action-tile:hover .tile-text { color: #fff; }

        /* Danger Tile Override */
        .tile-danger:hover { border-color: var(--neon-red); }
        .tile-danger:hover .tile-icon { filter: drop-shadow(0 0 10px var(--neon-red)); }

        /* --- Charts Area --- */
        .charts-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px;
        }
        .chart-box {
            background: var(--bg-surface);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px; position: relative;
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .chart-title { font-size: 1.4rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }

        /* --- NEW FLOATING DOCK (REDESIGNED) --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
            padding: 0 20px;
        }
        .dock {
            pointer-events: auto;
            display: flex; align-items: flex-end; /* Bottom align for pop effect */
            gap: 8px;
            background: var(--bg-dock);
            backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.1);
            border-top: 1px solid rgba(255,255,255,0.15);
            border-radius: 24px;
            padding: 10px 15px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
            transition: all 0.4s ease;
            max-width: 100%;
            overflow-x: auto; /* Allow scroll if needed */
            scrollbar-width: none;
        }
        .dock::-webkit-scrollbar { display: none; }
        
        .dock-item {
            position: relative;
            flex-shrink: 0;
            width: 70px; /* Base width */
            height: 70px; /* Base height */
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            border-radius: 16px;
            color: var(--text-dim);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: transparent;
        }
        
        .dock-item i {
            font-size: 1.6rem;
            margin-bottom: 4px;
            transition: 0.3s cubic-bezier(0.3, 0.7, 0.4, 1.5);
            z-index: 2;
        }
        
        .dock-label {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-dim);
            opacity: 0.7;
            transition: 0.3s;
            white-space: nowrap;
        }
        
        /* Hover State */
        .dock-item:hover {
            width: 80px; /* Expand slightly */
            background: rgba(255,255,255,0.05);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transform: translateY(-5px);
        }
        .dock-item:hover i {
            color: #fff;
            transform: translateY(-2px) scale(1.1);
            filter: drop-shadow(0 0 8px rgba(255,255,255,0.3));
        }
        .dock-item:hover .dock-label {
            color: #fff;
            opacity: 1;
        }

        /* Active State */
        .dock-item.active {
            background: rgba(34, 211, 238, 0.1);
            border: 1px solid rgba(34, 211, 238, 0.1);
        }
        .dock-item.active i { color: var(--neon-blue); }
        .dock-item.active .dock-label { color: var(--neon-blue); opacity: 1; font-weight: 700; }
        
        /* Danger Item */
        .dock-danger:hover i { color: var(--neon-red); filter: drop-shadow(0 0 8px var(--neon-red)); }
        .dock-danger:hover .dock-label { color: var(--neon-red); }

        @media (max-width: 1200px) { .charts-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 4%; padding-bottom: 140px; }
            .header-top { flex-direction: column; align-items: flex-start; gap: 20px; }
            .header-title h1 { font-size: 2.5rem; }
            .stats-deck { grid-template-columns: 1fr; gap: 20px; }
            /* Mobile Dock */
            .dock-container { bottom: 15px; padding: 0 10px; }
            .dock { justify-content: flex-start; padding: 8px 10px; gap: 5px; }
            .dock-item { width: 60px; height: 60px; }
            .dock-item i { font-size: 1.4rem; }
            .dock-label { font-size: 0.65rem; }
            .dock-item:hover { width: 70px; }
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
                    سیستم: <span style="color: var(--neon-teal); font-weight: 700;">آنلاین</span>
                    <span style="margin: 0 10px; color: rgba(255,255,255,0.1);">|</span>
                    <?php echo $today; ?>
                </p>
            </div>
            <div class="user-pill">
                <span>مدیر ارشد</span>
                <div class="user-avatar"><i class="fa-solid fa-user-astronaut"></i></div>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="stats-deck anim d-1">
            <div class="stat-card s-blue">
                <div class="stat-top"><i class="fa-solid fa-sack-dollar stat-icon"></i><span style="background: rgba(34, 211, 238, 0.1); color: var(--neon-blue); padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: bold;">کل</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['sales']); ?></div><div class="stat-lbl">تومان درآمد</div></div>
            </div>
            <div class="stat-card s-teal">
                <div class="stat-top"><i class="fa-solid fa-file-invoice-dollar stat-icon"></i><span style="background: rgba(45, 212, 191, 0.1); color: var(--neon-teal); padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: bold;">تراکنش</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['orders']); ?></div><div class="stat-lbl">سفارش موفق</div></div>
            </div>
            <div class="stat-card s-purple">
                <div class="stat-top"><i class="fa-solid fa-users-rays stat-icon"></i><span style="background: rgba(192, 132, 252, 0.1); color: var(--neon-purple); padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: bold;">فعال</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['users']); ?></div><div class="stat-lbl">کل کاربران</div></div>
            </div>
            <div class="stat-card s-amber">
                <div class="stat-top"><i class="fa-solid fa-user-plus stat-icon"></i><span style="background: rgba(251, 191, 36, 0.1); color: var(--neon-amber); padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: bold;">+ امروز</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['new_users']); ?></div><div class="stat-lbl">کاربر جدید</div></div>
            </div>
        </section>

        <!-- Quick Actions (Expanded) -->
        <div class="section-header anim d-2">دسترسی سریع</div>
        <section class="action-deck anim d-2">
            <a href="invoice.php" class="action-tile">
                <i class="fa-solid fa-file-contract tile-icon"></i><span class="tile-text">سفارشات</span>
            </a>
            <a href="users.php" class="action-tile">
                <i class="fa-solid fa-users-gear tile-icon"></i><span class="tile-text">کاربران</span>
            </a>
            <a href="product.php" class="action-tile">
                <i class="fa-solid fa-box-open tile-icon"></i><span class="tile-text">محصولات</span>
            </a>
            <a href="payment.php" class="action-tile">
                <i class="fa-solid fa-wallet tile-icon"></i><span class="tile-text">مالی</span>
            </a>
            <a href="inbound.php" class="action-tile">
                <i class="fa-solid fa-network-wired tile-icon"></i><span class="tile-text">کانفیگ‌ها</span>
            </a>
            <a href="service.php" class="action-tile">
                <i class="fa-solid fa-server tile-icon"></i><span class="tile-text">سرویس‌ها</span>
            </a>
            <a href="metrics.php" class="action-tile">
                <i class="fa-solid fa-chart-simple tile-icon"></i><span class="tile-text">آمار</span>
            </a>
            <a href="settings.php" class="action-tile">
                <i class="fa-solid fa-sliders tile-icon"></i><span class="tile-text">تنظیمات</span>
            </a>
            <a href="seeting_x_ui.php" class="action-tile">
                <i class="fa-solid fa-tower-broadcast tile-icon"></i><span class="tile-text">پنل X-UI</span>
            </a>
            <a href="text.php" class="action-tile">
                <i class="fa-solid fa-file-lines tile-icon"></i><span class="tile-text">متن‌ها</span>
            </a>
            <a href="keyboard.php" class="action-tile">
                <i class="fa-solid fa-keyboard tile-icon"></i><span class="tile-text">کیبورد</span>
            </a>
            <a href="cancelService.php" class="action-tile tile-danger" style="border-color: rgba(244, 114, 182, 0.3);">
                <i class="fa-solid fa-ban tile-icon" style="color: var(--neon-pink);"></i><span class="tile-text" style="color: var(--neon-pink);">مسدودی</span>
            </a>
        </section>

        <!-- Charts -->
        <section class="charts-grid anim d-3">
            <div class="chart-box">
                <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-chart-area" style="color: var(--neon-blue);"></i>روند فروش</div></div>
                <div style="height: 450px; width: 100%;"><canvas id="salesChart"></canvas></div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-chart-pie" style="color: var(--neon-purple);"></i>وضعیت‌ها</div></div>
                    <div style="height: 250px; position: relative;"><canvas id="statusChart"></canvas></div>
                </div>
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-arrow-trend-up" style="color: var(--neon-teal);"></i>رشد کاربر</div></div>
                    <div style="height: 200px;"><canvas id="usersChart"></canvas></div>
                </div>
            </div>
        </section>

    </div>

    <!-- Redesigned Floating Dock -->
    <div class="dock-container anim d-4">
        <nav class="dock">
            <a href="index.php" class="dock-item active">
                <i class="fa-solid fa-house-chimney"></i><span class="dock-label">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <i class="fa-solid fa-file-contract"></i><span class="dock-label">سفارشات</span>
            </a>
            <a href="users.php" class="dock-item">
                <i class="fa-solid fa-users-gear"></i><span class="dock-label">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <i class="fa-solid fa-box-open"></i><span class="dock-label">محصولات</span>
            </a>
            <a href="payment.php" class="dock-item">
                <i class="fa-solid fa-wallet"></i><span class="dock-label">مالی</span>
            </a>
            <a href="inbound.php" class="dock-item">
                <i class="fa-solid fa-network-wired"></i><span class="dock-label">کانفیگ</span>
            </a>
            <a href="service.php" class="dock-item">
                <i class="fa-solid fa-server"></i><span class="dock-label">سرویس</span>
            </a>
            <a href="metrics.php" class="dock-item">
                <i class="fa-solid fa-chart-simple"></i><span class="dock-label">آمار</span>
            </a>
            <a href="settings.php" class="dock-item">
                <i class="fa-solid fa-sliders"></i><span class="dock-label">تنظیمات</span>
            </a>
            <a href="seeting_x_ui.php" class="dock-item">
                <i class="fa-solid fa-tower-broadcast"></i><span class="dock-label">X-UI</span>
            </a>
            <a href="text.php" class="dock-item">
                <i class="fa-solid fa-file-lines"></i><span class="dock-label">متن‌ها</span>
            </a>
            <a href="keyboard.php" class="dock-item">
                <i class="fa-solid fa-keyboard"></i><span class="dock-label">کیبورد</span>
            </a>
            <a href="cancelService.php" class="dock-item dock-danger">
                <i class="fa-solid fa-ban"></i><span class="dock-label">مسدودی</span>
            </a>
            <div style="width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 5px;"></div>
            <a href="logout.php" class="dock-item dock-danger">
                <i class="fa-solid fa-power-off"></i><span class="dock-label">خروج</span>
            </a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = 'Vazirmatn'; Chart.defaults.font.size = 14; Chart.defaults.font.weight = '500'; Chart.defaults.color = '#94a3b8'; Chart.defaults.borderColor = 'rgba(255,255,255,0.03)'; Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 15, 20, 0.95)'; Chart.defaults.plugins.tooltip.padding = 16; Chart.defaults.plugins.tooltip.cornerRadius = 12; Chart.defaults.plugins.tooltip.titleFont = { size: 15, weight: 700 }; Chart.defaults.plugins.tooltip.bodyFont = { size: 13 };
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 500); gradS.addColorStop(0, '#22d3ee'); gradS.addColorStop(1, 'rgba(34, 211, 238, 0.0)');
        new Chart(ctxS, { type: 'line', data: { labels: dSales.labels, datasets: [{ label: 'فروش', data: dSales.values, borderColor: '#22d3ee', backgroundColor: gradS, borderWidth: 2, pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#fff', fill: true, tension: 0.4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 12 } } }, y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { font: { size: 12 } } } } } });
        new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: { labels: dPie.labels, datasets: [{ data: dPie.values, backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#8b5cf6', '#f97316', '#334155'], borderWidth: 0, hoverOffset: 15 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '85%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, color: '#94a3b8', font: {size: 12}, usePointStyle: true, padding: 12 } } } } });
        const ctxU = document.getElementById('usersChart').getContext('2d');
        const gradU = ctxU.createLinearGradient(0, 0, 0, 200); gradU.addColorStop(0, '#2dd4bf'); gradU.addColorStop(1, 'rgba(45, 212, 191, 0.2)');
        new Chart(ctxU, { type: 'bar', data: { labels: dUsers.labels, datasets: [{ label: 'کاربر', data: dUsers.values, backgroundColor: gradU, borderRadius: 4, barThickness: 15 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } } });
    </script>
</body>
</html>