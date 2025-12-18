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
$greet = ($hour < 12) ? "صبح زیباتون بخیر" : (($hour < 18) ? "روز خوش" : "عصر بخیر");
$today = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل مدیریت</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* Theme Core: Deep Void & Electric Accents */
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.65);
            --bg-card-hover: rgba(35, 35, 45, 0.8);
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-gold: #fbbf24;
            
            /* Text */
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            
            /* Borders & Shadows */
            --border-subtle: 1px solid rgba(255, 255, 255, 0.05);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.15);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            --glow-soft: 0 0 25px rgba(0, 242, 255, 0.05);
            
            --radius-main: 24px;
        }

        /* --- Base & Scrollbar --- */
        * { box-sizing: border-box; outline: none; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(192, 38, 211, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(0, 242, 255, 0.08) 0%, transparent 45%);
            color: var(--text-pri);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            padding-bottom: 140px;
            overflow-x: hidden;
        }

        /* --- Container --- */
        .dashboard-container {
            width: 100%; max-width: 1920px; margin: 0 auto;
            padding: 35px 5%; 
            display: flex; flex-direction: column; gap: 35px;
        }

        /* --- 1. Header Enhanced --- */
        .header-section {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.03);
            position: relative;
        }
        
        /* Left: Titles */
        .header-titles { display: flex; flex-direction: column; gap: 5px; }
        .ht-sup {
            font-size: 0.9rem; color: var(--neon-blue); font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; display: flex; align-items: center; gap: 8px;
        }
        .ht-sup::before { content: ''; width: 20px; height: 2px; background: var(--neon-blue); border-radius: 2px; }
        
        .ht-main {
            font-size: 3rem; font-weight: 900; margin: 0; line-height: 1.1;
            background: linear-gradient(to right, #ffffff 40%, #94a3b8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .ht-sub { color: var(--text-sec); font-size: 1.1rem; font-weight: 300; margin-top: 5px; }

        /* Right: Info Pills */
        .header-actions { display: flex; gap: 15px; align-items: center; }
        
        .info-pill {
            background: rgba(255,255,255,0.03); border: var(--border-subtle);
            padding: 10px 20px; border-radius: 16px;
            display: flex; align-items: center; gap: 10px;
            backdrop-filter: blur(10px); transition: 0.3s;
        }
        .info-pill:hover { background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.2); transform: translateY(-2px); }
        .ip-icon { color: var(--text-sec); font-size: 1.1rem; }
        .ip-text { font-size: 0.95rem; font-weight: 500; }
        
        .user-avatar-ring {
            width: 55px; height: 55px; border-radius: 50%;
            padding: 3px; background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            box-shadow: 0 0 20px rgba(0, 242, 255, 0.3);
            position: relative;
        }
        .user-avatar-img {
            width: 100%; height: 100%; background: #000; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: #fff; border: 2px solid #000;
        }
        .status-dot {
            position: absolute; bottom: 0; right: 0; width: 14px; height: 14px;
            background: #10b981; border: 2px solid #000; border-radius: 50%;
            box-shadow: 0 0 10px #10b981;
        }

        /* --- 2. Stats Grid (Holo Cards) --- */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px;
        }
        .holo-card {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 25px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .holo-card::after {
            content: ''; position: absolute; inset: 0; border-radius: var(--radius-main);
            padding: 1px; background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor; pointer-events: none;
        }
        .holo-card:hover {
            transform: translateY(-5px) scale(1.02);
            background: var(--bg-card-hover);
            box-shadow: 0 15px 40px -10px rgba(0,0,0,0.6);
            border-color: rgba(255,255,255,0.1);
        }
        
        .hc-info { z-index: 2; }
        .hc-val { font-size: 2.2rem; font-weight: 800; color: #fff; margin-bottom: 5px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .hc-lbl { color: var(--text-sec); font-size: 0.9rem; font-weight: 500; }
        
        .hc-icon-box {
            width: 60px; height: 60px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; z-index: 2;
            background: rgba(255,255,255,0.03);
            box-shadow: inset 0 0 20px rgba(255,255,255,0.02);
            backdrop-filter: blur(5px);
            transition: 0.4s;
        }
        .holo-card:hover .hc-icon-box { transform: rotate(-10deg) scale(1.1); }
        
        /* Decorative Glows */
        .hc-glow {
            position: absolute; width: 150px; height: 150px; border-radius: 50%;
            filter: blur(60px); opacity: 0.15; top: -50px; right: -50px; transition: 0.4s;
        }
        .holo-card:hover .hc-glow { opacity: 0.3; }

        /* Colors */
        .hc-blue .hc-icon-box { color: var(--neon-blue); border: 1px solid rgba(0, 242, 255, 0.2); }
        .hc-blue .hc-glow { background: var(--neon-blue); }
        
        .hc-green .hc-icon-box { color: var(--neon-green); border: 1px solid rgba(0, 255, 163, 0.2); }
        .hc-green .hc-glow { background: var(--neon-green); }
        
        .hc-purple .hc-icon-box { color: var(--neon-purple); border: 1px solid rgba(192, 38, 211, 0.2); }
        .hc-purple .hc-glow { background: var(--neon-purple); }
        
        .hc-gold .hc-icon-box { color: var(--neon-gold); border: 1px solid rgba(251, 191, 36, 0.2); }
        .hc-gold .hc-glow { background: var(--neon-gold); }

        /* --- 3. Bento Layout --- */
        .bento-wrapper {
            display: grid; grid-template-columns: 2.2fr 1fr; gap: 30px;
        }
        .col-main { display: flex; flex-direction: column; gap: 30px; }
        .col-side { display: flex; flex-direction: column; gap: 30px; }

        /* Glass Panel Generic */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 30px; display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
        }
        .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .ph-title { font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 12px; }
        .ph-title i { color: var(--neon-blue); filter: drop-shadow(0 0 5px var(--neon-blue)); }

        /* Actions Grid (Buttons) */
        .actions-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
        }
        .nav-tile {
            position: relative; height: 110px;
            background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px; overflow: hidden;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
            text-decoration: none; color: var(--text-sec);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav-tile::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.05), transparent);
            opacity: 0; transition: 0.3s;
        }
        .nav-tile:hover {
            transform: translateY(-5px); border-color: rgba(255,255,255,0.3);
            color: #fff; background: rgba(255,255,255,0.05);
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.5), inset 0 0 20px rgba(255,255,255,0.02);
        }
        .nav-tile:hover::before { opacity: 1; }
        
        .nt-icon { font-size: 2rem; transition: 0.3s; z-index: 2; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5)); }
        .nav-tile:hover .nt-icon { transform: scale(1.15); filter: drop-shadow(0 0 10px currentColor); }
        .nt-label { z-index: 2; font-size: 0.9rem; font-weight: 500; }

        /* Danger Button Specific */
        .nt-danger:hover { border-color: var(--neon-red); }
        .nt-danger:hover .nt-icon { color: var(--neon-red); }

        /* --- 4. Super Dock (MacOS Style) --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; pointer-events: none; z-index: 9999;
        }
        .super-dock {
            pointer-events: auto;
            background: rgba(15, 15, 20, 0.85);
            backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.1);
            padding: 12px; border-radius: 28px;
            display: flex; align-items: center; gap: 8px;
            transition: 0.3s;
        }
        .dock-item {
            width: 54px; height: 54px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 18px;
            color: var(--text-sec); font-size: 1.5rem;
            text-decoration: none; position: relative;
            background: transparent;
            transition: all 0.25s cubic-bezier(0.3, 0.7, 0.4, 1.5); /* Bouncy effect */
            border: 1px solid transparent;
        }
        
        /* Advanced Hover */
        .dock-item:hover {
            width: 65px; height: 65px; /* Scale Up */
            margin: 0 5px; /* Push neighbors */
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
            border-color: rgba(255,255,255,0.3);
            color: #fff;
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            transform: translateY(-15px);
        }
        
        .dock-item.active {
            color: var(--neon-blue);
            background: rgba(0, 242, 255, 0.1);
            border-color: rgba(0, 242, 255, 0.3);
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.15);
        }
        
        /* Tooltip */
        .dock-item::before {
            content: attr(data-tooltip);
            position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(10px) scale(0.8);
            background: rgba(0,0,0,0.9); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; padding: 6px 14px; border-radius: 10px;
            font-size: 0.8rem; font-weight: 600; white-space: nowrap;
            opacity: 0; visibility: hidden; transition: 0.2s; pointer-events: none;
            margin-bottom: 15px;
        }
        .dock-item:hover::before { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0) scale(1); }

        .dock-divider { width: 1px; height: 35px; background: rgba(255,255,255,0.1); margin: 0 5px; }

        /* --- Responsive --- */
        @media (max-width: 1400px) {
            .bento-wrapper { grid-template-columns: 1fr; }
            .col-side { flex-direction: row; }
        }
        @media (max-width: 992px) {
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .col-side { flex-direction: column; }
            .header-section { flex-direction: column; align-items: flex-start; gap: 20px; }
            .header-actions { width: 100%; justify-content: space-between; }
        }
        @media (max-width: 768px) {
            .dashboard-container { padding: 20px; }
            .dock-container { bottom: 15px; }
            .super-dock { max-width: 95vw; overflow-x: auto; justify-content: flex-start; padding: 10px; border-radius: 20px; }
            .dock-item { flex-shrink: 0; width: 48px; height: 48px; }
            .dock-item:hover { width: 48px; height: 48px; margin: 0; transform: translateY(-5px); }
            .ht-main { font-size: 2rem; }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- 1. Cinematic Header -->
        <header class="header-section">
            <div class="header-titles">
                <div class="ht-sup">پنل فرماندهی سیستم</div>
                <h1 class="ht-main"><?php echo $greet; ?>، ادمین</h1>
                <div class="ht-sub">همه سیستم‌ها پایدار و در وضعیت نرمال هستند.</div>
            </div>
            
            <div class="header-actions">
                <div class="info-pill">
                    <i class="fa-regular fa-calendar ip-icon"></i>
                    <span class="ip-text"><?php echo $today; ?></span>
                </div>
                <div class="user-avatar-ring">
                    <div class="user-avatar-img"><i class="fa-solid fa-user-astronaut"></i></div>
                    <div class="status-dot"></div>
                </div>
            </div>
        </header>

        <!-- 2. Holographic Stats -->
        <section class="stats-row">
            <!-- Sales -->
            <div class="holo-card hc-blue">
                <div class="hc-glow"></div>
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($stats['sales']); ?></div>
                    <div class="hc-lbl">درآمد کل (تومان)</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-sack-dollar"></i></div>
            </div>
            <!-- Orders -->
            <div class="holo-card hc-green">
                <div class="hc-glow"></div>
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($stats['orders']); ?></div>
                    <div class="hc-lbl">تعداد سفارشات</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <!-- Users -->
            <div class="holo-card hc-purple">
                <div class="hc-glow"></div>
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($stats['users']); ?></div>
                    <div class="hc-lbl">کل کاربران</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-users"></i></div>
            </div>
            <!-- New Users -->
            <div class="holo-card hc-gold">
                <div class="hc-glow"></div>
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($stats['new_users']); ?></div>
                    <div class="hc-lbl">ثبت‌نام امروز</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-user-plus"></i></div>
            </div>
        </section>

        <!-- 3. Main Dashboard Grid -->
        <section class="bento-wrapper">
            
            <div class="col-main">
                <!-- Main Chart -->
                <div class="glass-panel" style="flex-grow: 1;">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fa-solid fa-chart-line"></i> نمودار فروش زنده</div>
                        <div style="background: rgba(255,255,255,0.05); padding: 5px 15px; border-radius: 10px; font-size: 0.85rem; color: #fff;">۳۰ روز اخیر</div>
                    </div>
                    <div style="width: 100%; height: 350px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Navigation Tiles -->
                <div class="actions-grid">
                    <a href="users.php" class="nav-tile">
                        <i class="fa-solid fa-users-gear nt-icon"></i>
                        <span class="nt-label">کاربران</span>
                    </a>
                    <a href="invoice.php" class="nav-tile">
                        <i class="fa-solid fa-file-invoice nt-icon"></i>
                        <span class="nt-label">سفارشات</span>
                    </a>
                    <a href="product.php" class="nav-tile">
                        <i class="fa-solid fa-box-open nt-icon"></i>
                        <span class="nt-label">محصولات</span>
                    </a>
                    <a href="service.php" class="nav-tile">
                        <i class="fa-solid fa-server nt-icon"></i>
                        <span class="nt-label">سرویس‌ها</span>
                    </a>
                    <a href="payment.php" class="nav-tile">
                        <i class="fa-solid fa-wallet nt-icon"></i>
                        <span class="nt-label">امور مالی</span>
                    </a>
                    <a href="keyboard.php" class="nav-tile">
                        <i class="fa-solid fa-network-wired nt-icon"></i>
                        <span class="nt-label">مدیریت دکمه های ربات</span>
                    </a>
                    <a href="seeting_x_ui.php" class="nav-tile">
                        <i class="fa-solid fa-tower-broadcast nt-icon"></i>
                        <span class="nt-label">پنل X-UI</span>
                    </a>
                    <a href="cancelService.php" class="nav-tile nt-danger">
                        <i class="fa-solid fa-ban nt-icon"></i>
                        <span class="nt-label">مسدودها</span>
                    </a>
                </div>
            </div>

            <div class="col-side">
                <!-- Status Doughnut -->
                <div class="glass-panel" style="flex: 1;">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fa-solid fa-chart-pie"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="position: relative; height: 220px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="statusChart"></canvas>
                        <div style="position: absolute; text-align: center; pointer-events: none;">
                            <div style="font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1;"><?php echo $totalStatus; ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-sec); opacity: 0.7;">TOTAL</div>
                        </div>
                    </div>
                </div>

                <!-- User Growth Bar -->
                <div class="glass-panel" style="flex: 1;">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fa-solid fa-chart-column"></i> جذب کاربر</div>
                    </div>
                    <div style="height: 180px;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

        </section>

    </div>

    <!-- 4. The Super Dock -->
    <div class="dock-container">
        <nav class="super-dock">
            <a href="index.php" class="dock-item active" data-tooltip="داشبورد"><i class="fa-solid fa-house"></i></a>
            <div class="dock-divider"></div>
            <a href="users.php" class="dock-item" data-tooltip="کاربران"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-item" data-tooltip="سفارشات"><i class="fa-solid fa-file-contract"></i></a>
            <a href="product.php" class="dock-item" data-tooltip="محصولات"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-item" data-tooltip="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
            <div class="dock-divider"></div>
            <a href="cancelService.php" class="dock-item" data-tooltip="لیست مسدود" style="color: var(--neon-red);"><i class="fa-solid fa-ban"></i></a>
            <a href="payment.php" class="dock-item" data-tooltip="امور مالی"><i class="fa-solid fa-wallet"></i></a>
            <a href="inbound.php" class="dock-item" data-tooltip="کانفیگ سرور"><i class="fa-solid fa-network-wired"></i></a>
            <a href="seeting_x_ui.php" class="dock-item" data-tooltip="پنل X-UI"><i class="fa-solid fa-tower-broadcast"></i></a>
            <div class="dock-divider"></div>
            <a href="settings.php" class="dock-item" data-tooltip="تنظیمات"><i class="fa-solid fa-gear"></i></a>
            <a href="login.php" class="dock-item" data-tooltip="خروج" style="color: var(--neon-red);"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // --- High-End Chart Config ---
        Chart.defaults.font.family = 'Vazirmatn'; 
        Chart.defaults.color = '#64748b'; 
        Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.02)';
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Smooth Gradient Area)
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 350);
        gradS.addColorStop(0, 'rgba(0, 242, 255, 0.4)');
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
                    borderWidth: 3,
                    pointRadius: 0,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#00f2ff',
                    pointHoverBorderWidth: 3,
                    fill: true,
                    tension: 0.45 // Smoother curve
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { 
                    backgroundColor: 'rgba(15, 23, 42, 0.9)', 
                    titleColor: '#fff', 
                    bodyColor: '#cbd5e1',
                    padding: 12, 
                    cornerRadius: 12, 
                    displayColors: false,
                    borderWidth: 1,
                    borderColor: 'rgba(255,255,255,0.1)'
                } },
                scales: {
                    x: { grid: { display: false }, ticks: { display: false } },
                    y: { border: { display: false }, ticks: { display: false } } // Cleaner look
                },
                interaction: { mode: 'index', intersect: false }
            }
        });

        // 2. Status Chart (Neon Rings)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels,
                datasets: [{
                    data: dPie.values,
                    backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#d946ef', '#f97316', '#334155'],
                    borderWidth: 0,
                    hoverOffset: 10,
                    borderRadius: 20, // Rounded ends
                    spacing: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '85%',
                plugins: { legend: { display: false }, tooltip: { 
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    bodyFont: { size: 13 }
                } }
            }
        });

        // 3. Users Chart (Glow Bars)
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: dUsers.values,
                    backgroundColor: '#00ff9d',
                    borderRadius: 10,
                    borderSkipped: false,
                    barThickness: 12,
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