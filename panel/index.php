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
$totalStatus = 0;
foreach ($chartData['status'] as $r) {
    $k = $r['status'];
    $pieLabels[] = $statusConfig[$k]['label'] ?? $k;
    $val = (int)$r['cnt'];
    $pieValues[] = $val;
    $totalStatus += $val;
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
    <title>پنل مدیریت | نسخه الترا</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* Palette: Void & Neon (Midnight Theme Preserved) */
            --bg-void: #020204; /* Darker void */
            --bg-surface: rgba(18, 18, 24, 0.65);
            --bg-dock: rgba(10, 10, 14, 0.9);
            
            /* Enhanced Neons - Saturation Boosted */
            --neon-blue: #00f2ff;
            --neon-purple: #d946ef;
            --neon-teal: #14b8a6;
            --neon-amber: #f59e0b;
            --neon-pink: #ec4899;
            --neon-red: #ef4444;
            
            --text-main: #ffffff;
            --text-dim: #94a3b8;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --shadow-float: 0 30px 60px -15px rgba(0, 0, 0, 0.9);
            --radius-lg: 36px;
        }

        /* --- Base & Background --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-void);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 180px;
            position: relative;
            font-weight: 500;
        }

        /* Ambient Orbs - More intense */
        .ambient-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.18;
            z-index: -1;
            animation: orbFloat 25s infinite alternate ease-in-out;
        }
        .orb-1 { top: -15%; left: -10%; width: 60vw; height: 60vw; background: var(--neon-purple); animation-delay: 0s; }
        .orb-2 { bottom: -15%; right: -10%; width: 70vw; height: 70vw; background: var(--neon-blue); animation-delay: -5s; }
        .orb-3 { top: 30%; left: 40%; width: 40vw; height: 40vw; background: var(--neon-teal); opacity: 0.1; animation-delay: -10s; }

        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(40px, 60px) scale(1.15); }
        }

        /* --- Animations --- */
        @keyframes staggerUp {
            from { opacity: 0; transform: translateY(50px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .anim { opacity: 0; animation: staggerUp 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; } .d-4 { animation-delay: 0.4s; }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(0, 242, 255, 0.5); }
            70% { box-shadow: 0 0 0 12px rgba(0, 242, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 242, 255, 0); }
        }

        /* --- Layout --- */
        .container-fluid-custom {
            width: 100%;
            max-width: 2100px; /* Wider container */
            margin: 0 auto;
            padding: 60px 3%; /* Less side padding to fill space */
        }

        /* --- Header --- */
        .header-top {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 60px; padding-bottom: 40px;
            border-bottom: 2px solid rgba(255,255,255,0.04);
            position: relative;
        }
        .header-top::after {
            content: ''; position: absolute; bottom: -2px; left: 0; width: 200px; height: 4px;
            background: linear-gradient(90deg, var(--neon-blue), var(--neon-purple), transparent);
            border-radius: 10px;
        }
        .header-title h1 {
            font-size: 5.5rem; font-weight: 900; color: #fff; margin: 0; line-height: 1;
            letter-spacing: -3px;
            background: linear-gradient(135deg, #fff 40%, #94a3b8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-title p {
            color: var(--text-dim); margin-top: 15px; font-size: 1.6rem; font-weight: 400;
            display: flex; align-items: center; gap: 25px;
        }
        
        .status-badge {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(0, 242, 255, 0.05); border: 1px solid rgba(0, 242, 255, 0.2);
            padding: 8px 20px; border-radius: 30px; color: var(--neon-blue); font-weight: 800; font-size: 1rem;
        }
        .status-dot { width: 10px; height: 10px; background: var(--neon-blue); border-radius: 50%; animation: pulseGlow 2s infinite; }

        .user-pill {
            background: rgba(255,255,255,0.03); border: var(--border-glass);
            padding: 15px 15px 15px 35px; border-radius: 100px;
            display: flex; align-items: center; gap: 20px;
            backdrop-filter: blur(25px); transition: 0.3s;
        }
        .user-pill:hover { background: rgba(255,255,255,0.08); transform: scale(1.02); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .user-info { text-align: left; }
        .user-name { display: block; font-size: 1.4rem; font-weight: 800; color: #fff; }
        .user-role { display: block; font-size: 1rem; color: var(--text-dim); font-weight: 400; }
        .user-avatar {
            width: 65px; height: 65px; border-radius: 50%;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: #000; box-shadow: 0 0 25px rgba(0, 242, 255, 0.5);
        }

        /* --- Stats Grid (Massive) --- */
        .stats-deck {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); /* Wider cards */
            gap: 40px; margin-bottom: 80px;
        }
        .stat-card {
            background: linear-gradient(170deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.01) 100%);
            backdrop-filter: blur(30px); border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-lg); padding: 45px;
            display: flex; flex-direction: column; min-height: 280px;
            position: relative; overflow: hidden; transition: 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.3);
        }
        .stat-card:hover {
            transform: translateY(-15px) scale(1.02);
            border-color: var(--glow-c);
            background: linear-gradient(170deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%);
            box-shadow: 0 30px 70px -10px rgba(0,0,0,0.8), 0 0 40px rgba(var(--rgb-c), 0.15);
        }

        .stat-top { display: flex; justify-content: space-between; margin-bottom: 30px; align-items: center; }
        .stat-icon { font-size: 3.5rem; color: rgba(255,255,255,0.1); transition: 0.5s; }
        .stat-card:hover .stat-icon { color: var(--glow-c); transform: scale(1.15) rotate(-8deg); filter: drop-shadow(0 0 20px var(--glow-c)); }
        
        .stat-val { 
            font-size: 5rem; font-weight: 900; color: #fff; line-height: 0.9; letter-spacing: -3px; margin-top: auto; 
            text-shadow: 0 5px 20px rgba(0,0,0,0.5);
        }
        .stat-lbl { font-size: 1.5rem; color: var(--text-dim); margin-top: 10px; font-weight: 600; }
        
        .stat-prog { width: 100%; height: 6px; background: rgba(255,255,255,0.05); margin-top: 30px; border-radius: 10px; overflow: hidden; }
        .stat-fill { height: 100%; background: var(--glow-c); width: 0; animation: fillBar 1.5s ease-out forwards; border-radius: 10px; box-shadow: 0 0 15px var(--glow-c); }
        @keyframes fillBar { to { width: 65%; } }

        .s-blue { --glow-c: var(--neon-blue); --rgb-c: 0, 242, 255; }
        .s-teal { --glow-c: var(--neon-teal); --rgb-c: 20, 184, 166; }
        .s-purple { --glow-c: var(--neon-purple); --rgb-c: 217, 70, 239; }
        .s-amber { --glow-c: var(--neon-amber); --rgb-c: 245, 158, 11; }

        /* --- Quick Actions (Giant & Full) --- */
        .section-title { font-size: 3rem; font-weight: 900; color: #fff; margin-bottom: 60px; display: flex; align-items: center; gap: 25px; }
        .section-title::after { content: ''; height: 4px; flex-grow: 1; background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent); border-radius: 2px; }

        .action-deck {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); /* Much wider tiles */
            gap: 35px; margin-bottom: 100px;
        }
        .action-tile {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05); border-radius: 32px;
            padding: 40px 25px; text-align: center;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 25px;
            transition: 0.4s cubic-bezier(0.3, 0.7, 0.2, 1); cursor: pointer; 
            height: 280px; /* Taller tiles */
            position: relative; overflow: hidden; text-decoration: none;
        }
        .action-tile:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--neon-blue);
            transform: translateY(-12px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.6);
        }
        .tile-icon { font-size: 4.5rem; color: var(--text-dim); transition: 0.4s; }
        .action-tile:hover .tile-icon { color: #fff; transform: scale(1.25); filter: drop-shadow(0 0 30px var(--neon-blue)); }
        .tile-text { font-size: 1.8rem; font-weight: 800; color: #fff; transition: 0.3s; }
        .tile-sub { font-size: 1.1rem; color: var(--text-dim); opacity: 0; transform: translateY(15px); transition: 0.3s; font-weight: 500; }
        .action-tile:hover .tile-sub { opacity: 1; transform: translateY(0); }

        .tile-danger:hover { border-color: var(--neon-red); background: rgba(239, 68, 68, 0.05); }
        .tile-danger:hover .tile-icon { filter: drop-shadow(0 0 30px var(--neon-red)); }

        /* --- Charts (Professional) --- */
        .charts-grid {
            display: grid; grid-template-columns: 2.2fr 1fr; gap: 40px; margin-bottom: 80px;
        }
        .chart-box {
            background: rgba(15, 15, 20, 0.7);
            backdrop-filter: blur(40px); border: 1px solid rgba(255,255,255,0.06);
            border-radius: var(--radius-lg); padding: 50px;
            position: relative; display: flex; flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .chart-title { font-size: 2rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 20px; }
        
        /* Doughnut Center */
        .doughnut-center {
            position: absolute; top: 58%; left: 50%; transform: translate(-50%, -50%);
            text-align: center; pointer-events: none;
        }
        .dc-val { font-size: 3rem; font-weight: 900; color: #fff; line-height: 1; letter-spacing: -2px; }
        .dc-lbl { font-size: 1.2rem; color: var(--text-dim); font-weight: 600; margin-top: 5px; }

        /* --- Floating Dock (Sleek) --- */
        .dock-container {
            position: fixed; bottom: 40px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
            padding: 0 20px;
        }
        .dock {
            pointer-events: auto;
            display: flex; align-items: flex-end;
            gap: 15px;
            background: var(--bg-dock);
            backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.12);
            border-top: 1px solid rgba(255,255,255,0.2);
            border-radius: 36px; padding: 18px 30px;
            box-shadow: 0 40px 90px rgba(0,0,0,0.9);
            transition: 0.4s; overflow-x: auto; scrollbar-width: none;
        }
        .dock::-webkit-scrollbar { display: none; }
        
        .dock-item {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            width: 80px; height: 80px; flex-shrink: 0;
            color: var(--text-dim); text-decoration: none; border-radius: 22px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dock-item i { font-size: 2rem; margin-bottom: 6px; transition: 0.3s; }
        .dock-label { font-size: 0.85rem; font-weight: 700; opacity: 0.6; }
        
        .dock-item:hover {
            width: 95px; background: rgba(255,255,255,0.08); transform: translateY(-15px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
        }
        .dock-item:hover i { color: #fff; transform: scale(1.2); }
        .dock-item:hover .dock-label { color: #fff; opacity: 1; }
        
        .dock-item.active { background: rgba(0, 242, 255, 0.1); border: 1px solid rgba(0, 242, 255, 0.25); }
        .dock-item.active i { color: var(--neon-blue); text-shadow: 0 0 15px var(--neon-blue); }
        .dock-item.active .dock-label { color: var(--neon-blue); opacity: 1; }

        .dock-danger:hover i { color: var(--neon-red); text-shadow: 0 0 15px var(--neon-red); }
        .dock-danger:hover .dock-label { color: var(--neon-red); }

        @media (max-width: 1600px) { .charts-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 40px 5%; }
            .header-title h1 { font-size: 4rem; }
            .stats-deck { grid-template-columns: 1fr; }
            .action-deck { grid-template-columns: 1fr 1fr; } /* Stack slightly on mobile */
            .dock { padding: 12px 20px; gap: 8px; justify-content: flex-start; }
            .dock-item { width: 70px; height: 70px; }
        }
    </style>
</head>
<body>

    <!-- Background Decoration -->
    <div class="ambient-orb orb-1"></div>
    <div class="ambient-orb orb-2"></div>
    <div class="ambient-orb orb-3"></div>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="header-top anim">
            <div class="header-title">
                <h1><?php echo $greet; ?>، ادمین</h1>
                <p>
                    <span class="status-badge"><span class="status-dot"></span>سیستم آنلاین و پایدار</span>
                    <span style="opacity: 0.2;">|</span>
                    <i class="fa-regular fa-calendar-check"></i> <?php echo $today; ?>
                </p>
            </div>
            <div class="user-pill">
                <div class="user-info">
                    <span class="user-name">مدیر ارشد</span>
                    <span class="user-role">دسترسی کامل (Root)</span>
                </div>
                <div class="user-avatar"><i class="fa-solid fa-user-astronaut"></i></div>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="stats-deck anim d-1">
            <div class="stat-card s-blue">
                <div class="stat-top">
                    <span class="stat-lbl">درآمد کل سیستم</span>
                    <i class="fa-solid fa-sack-dollar stat-icon"></i>
                </div>
                <div class="stat-val"><?php echo number_format($stats['sales']); ?></div>
                <div class="stat-prog"><div class="stat-fill"></div></div>
            </div>
            <div class="stat-card s-teal">
                <div class="stat-top">
                    <span class="stat-lbl">تعداد تراکنش‌ها</span>
                    <i class="fa-solid fa-file-invoice-dollar stat-icon"></i>
                </div>
                <div class="stat-val"><?php echo number_format($stats['orders']); ?></div>
                <div class="stat-prog"><div class="stat-fill" style="width: 80%; animation-duration: 1.8s; background: var(--neon-teal);"></div></div>
            </div>
            <div class="stat-card s-purple">
                <div class="stat-top">
                    <span class="stat-lbl">کاربران فعال</span>
                    <i class="fa-solid fa-users-rays stat-icon"></i>
                </div>
                <div class="stat-val"><?php echo number_format($stats['users']); ?></div>
                <div class="stat-prog"><div class="stat-fill" style="width: 45%; animation-duration: 2s; background: var(--neon-purple);"></div></div>
            </div>
            <div class="stat-card s-amber">
                <div class="stat-top">
                    <span class="stat-lbl">ثبت نام امروز</span>
                    <i class="fa-solid fa-user-plus stat-icon"></i>
                </div>
                <div class="stat-val"><?php echo number_format($stats['new_users']); ?></div>
                <div class="stat-prog"><div class="stat-fill" style="width: 30%; animation-duration: 1.2s; background: var(--neon-amber);"></div></div>
            </div>
        </section>

        <!-- Quick Actions -->
        <div class="section-title anim d-2"><i class="fa-solid fa-bolt" style="color: var(--neon-amber);"></i> دسترسی سریع</div>
        <section class="action-deck anim d-2">
            <a href="invoice.php" class="action-tile">
                <i class="fa-solid fa-file-contract tile-icon"></i>
                <div>
                    <div class="tile-text">مدیریت سفارشات</div>
                    <div class="tile-sub">مشاهده و ویرایش فاکتورها</div>
                </div>
            </a>
            <a href="users.php" class="action-tile">
                <i class="fa-solid fa-users-gear tile-icon"></i>
                <div>
                    <div class="tile-text">کاربران سیستم</div>
                    <div class="tile-sub">لیست و مدیریت مشترکین</div>
                </div>
            </a>
            <a href="product.php" class="action-tile">
                <i class="fa-solid fa-box-open tile-icon"></i>
                <div>
                    <div class="tile-text">محصولات</div>
                    <div class="tile-sub">تعریف پلن‌های فروش</div>
                </div>
            </a>
            <a href="service.php" class="action-tile">
                <i class="fa-solid fa-server tile-icon"></i>
                <div>
                    <div class="tile-text">سرویس‌ها</div>
                    <div class="tile-sub">وضعیت سرورها</div>
                </div>
            </a>
            <a href="seeting_x_ui.php" class="action-tile">
                <i class="fa-solid fa-tower-broadcast tile-icon"></i>
                <div>
                    <div class="tile-text">پنل X-UI</div>
                    <div class="tile-sub">تنظیمات هسته</div>
                </div>
            </a>
            <a href="metrics.php" class="action-tile">
                <i class="fa-solid fa-chart-simple tile-icon"></i>
                <div>
                    <div class="tile-text">آمار پیشرفته</div>
                    <div class="tile-sub">گزارشات دقیق سیستم</div>
                </div>
            </a>
            <a href="settings.php" class="action-tile">
                <i class="fa-solid fa-sliders tile-icon"></i>
                <div>
                    <div class="tile-text">تنظیمات</div>
                    <div class="tile-sub">پیکربندی سامانه</div>
                </div>
            </a>
            <a href="cancelService.php" class="action-tile tile-danger">
                <i class="fa-solid fa-ban tile-icon" style="color: var(--neon-pink);"></i>
                <div>
                    <div class="tile-text" style="color: var(--neon-pink);">مسدودی‌ها</div>
                    <div class="tile-sub">مدیریت لیست سیاه</div>
                </div>
            </a>
        </section>

        <!-- Charts Grid -->
        <section class="charts-grid anim d-3">
            <div class="chart-box" style="flex: 2;">
                <div class="chart-header">
                    <div class="chart-title"><i class="fa-solid fa-chart-area" style="color: var(--neon-blue);"></i> نمودار فروش ۳۰ روزه</div>
                    <div style="background: rgba(0, 242, 255, 0.1); color: var(--neon-blue); padding: 8px 20px; border-radius: 12px; font-size: 1rem; border: 1px solid rgba(0, 242, 255, 0.2); font-weight: bold;">زنده</div>
                </div>
                <div style="height: 550px; width: 100%;"><canvas id="salesChart"></canvas></div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 40px; flex: 1;">
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-chart-pie" style="color: var(--neon-purple);"></i> وضعیت سرویس‌ها</div></div>
                    <div style="height: 350px; position: relative;">
                        <canvas id="statusChart"></canvas>
                        <div class="doughnut-center">
                            <div class="dc-val"><?php echo $totalStatus; ?></div>
                            <div class="dc-lbl">کل</div>
                        </div>
                    </div>
                </div>
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-arrow-trend-up" style="color: var(--neon-teal);"></i> جذب کاربر</div></div>
                    <div style="height: 300px;"><canvas id="usersChart"></canvas></div>
                </div>
            </div>
        </section>

    </div>

    <!-- Floating Dock -->
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
            <div style="width: 1px; height: 50px; background: rgba(255,255,255,0.1); margin: 0 10px;"></div>
            <a href="logout.php" class="dock-item dock-danger">
                <i class="fa-solid fa-power-off"></i><span class="dock-label">خروج</span>
            </a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = 'Vazirmatn'; 
        Chart.defaults.font.size = 18; /* Bigger Fonts in Charts */
        Chart.defaults.font.weight = '600'; 
        Chart.defaults.color = '#94a3b8'; 
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        
        // Professional Tooltip
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 15, 20, 0.95)';
        Chart.defaults.plugins.tooltip.titleColor = '#fff';
        Chart.defaults.plugins.tooltip.padding = 24;
        Chart.defaults.plugins.tooltip.cornerRadius = 20;
        Chart.defaults.plugins.tooltip.titleFont = { size: 20, weight: 800 };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 16 };
        Chart.defaults.plugins.tooltip.displayColors = true;
        Chart.defaults.plugins.tooltip.boxWidth = 10;
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.1)';

        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // Sales Chart
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 600); 
        gradS.addColorStop(0, 'rgba(0, 242, 255, 0.4)'); 
        gradS.addColorStop(0.5, 'rgba(0, 242, 255, 0.05)'); 
        gradS.addColorStop(1, 'rgba(0, 242, 255, 0)');
        
        new Chart(ctxS, { 
            type: 'line', 
            data: { 
                labels: dSales.labels, 
                datasets: [{ 
                    label: 'فروش (تومان)', 
                    data: dSales.values, 
                    borderColor: '#00f2ff', 
                    backgroundColor: gradS, 
                    borderWidth: 5, 
                    pointRadius: 0, 
                    pointHoverRadius: 12, 
                    pointBackgroundColor: '#000', 
                    pointBorderColor: '#00f2ff',
                    pointBorderWidth: 4,
                    fill: true, 
                    tension: 0.45 
                }] 
            }, 
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } }, 
                scales: { 
                    x: { grid: { display: false }, ticks: { padding: 15, color: '#64748b' } }, 
                    y: { 
                        beginAtZero: true, 
                        border: { display: false, dash: [5, 5] }, 
                        grid: { color: 'rgba(255,255,255,0.03)', borderDash: [5, 5] }, 
                        ticks: { padding: 20, color: '#64748b' } 
                    } 
                },
                interaction: { mode: 'index', intersect: false }
            } 
        });

        // Status Chart (Doughnut)
        new Chart(document.getElementById('statusChart'), { 
            type: 'doughnut', 
            data: { 
                labels: dPie.labels, 
                datasets: [{ 
                    data: dPie.values, 
                    backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#d946ef', '#f97316', '#334155'], 
                    borderWidth: 2, 
                    borderColor: 'rgba(15, 15, 20, 0.8)',
                    hoverOffset: 30,
                    borderRadius: 8
                }] 
            }, 
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                cutout: '82%', 
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 18, color: '#fff', font: {size: 15, weight: 600}, usePointStyle: true, padding: 25 } } 
                },
                layout: { padding: 20 }
            } 
        });

        // User Chart (Bar)
        const ctxU = document.getElementById('usersChart').getContext('2d');
        const gradU = ctxU.createLinearGradient(0, 0, 0, 400); 
        gradU.addColorStop(0, '#14b8a6'); 
        gradU.addColorStop(1, 'rgba(20, 184, 166, 0.1)');
        
        new Chart(ctxU, { 
            type: 'bar', 
            data: { 
                labels: dUsers.labels, 
                datasets: [{ 
                    label: 'کاربر جدید', 
                    data: dUsers.values, 
                    backgroundColor: gradU, 
                    borderRadius: 12, 
                    barThickness: 35,
                    hoverBackgroundColor: '#fff'
                }] 
            }, 
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } }, 
                scales: { x: { display: false }, y: { display: false } } 
            } 
        });
    </script>
</body>
</html>