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
            /* Palette: Deep Nebula */
            --bg-void: #050508;
            --bg-surface: rgba(30, 30, 40, 0.55);
            --bg-glass: rgba(255, 255, 255, 0.03);
            
            --neon-blue: #00f2ff;
            --neon-purple: #bd00ff;
            --neon-teal: #00ff9d;
            --neon-amber: #ffae00;
            --neon-red: #ff0055;
            
            --text-main: #ffffff;
            --text-dim: #94a3b8;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.06);
            --border-active: 1px solid rgba(255, 255, 255, 0.2);
            --radius-xl: 32px;
            --radius-lg: 24px;
            --shadow-glow: 0 0 20px rgba(0,0,0,0.5);
        }

        /* --- Base Setup --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-void);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(88, 28, 135, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(15, 118, 110, 0.15) 0%, transparent 50%);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 140px;
        }

        /* --- Animations --- */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim { animation: fadeSlideUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } 
        .d-2 { animation-delay: 0.2s; } 
        .d-3 { animation-delay: 0.3s; }

        .dashboard-container {
            width: 100%; max-width: 1800px; margin: 0 auto;
            padding: 30px; display: flex; flex-direction: column; gap: 30px;
        }

        /* --- Header: Clean & Modern --- */
        .header-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 10px 20px 10px;
        }
        .welcome-text h1 {
            margin: 0; font-size: 2.8rem; font-weight: 900; letter-spacing: -1.5px;
            background: linear-gradient(135deg, #fff 30%, #94a3b8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 20px rgba(255,255,255,0.1));
        }
        .welcome-text p { margin: 8px 0 0; color: var(--text-dim); display: flex; align-items: center; gap: 12px; font-size: 0.95rem; }
        .live-badge { 
            font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
            background: rgba(0, 242, 255, 0.08); color: var(--neon-blue);
            padding: 4px 12px; border-radius: 20px; border: 1px solid rgba(0, 242, 255, 0.2);
            box-shadow: 0 0 10px rgba(0, 242, 255, 0.1);
        }

        /* Profile Pill */
        .profile-pill {
            display: flex; align-items: center; gap: 15px;
            background: rgba(255, 255, 255, 0.03); padding: 8px 8px 8px 24px;
            border-radius: 100px; border: var(--border-glass);
            backdrop-filter: blur(12px); transition: 0.3s;
        }
        .profile-pill:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.2); transform: scale(1.02); }
        .p-info { text-align: left; }
        .p-name { font-weight: 700; color: #fff; font-size: 1rem; }
        .p-role { font-size: 0.75rem; color: var(--neon-purple); font-weight: 600; }
        .p-img { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; color: #000; font-size: 1.2rem; }

        /* --- Stats Row: Glass Cards with Color Pop --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;
        }
        .stat-box {
            background: linear-gradient(160deg, rgba(30,30,40,0.7) 0%, rgba(20,20,25,0.6) 100%);
            border: var(--border-glass); border-radius: var(--radius-lg);
            padding: 28px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 180px; transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .stat-box::before { /* Glow spot */
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, var(--glow-color) 0%, transparent 60%);
            opacity: 0; transition: opacity 0.4s ease; mix-blend-mode: screen; pointer-events: none;
        }
        .stat-box:hover { transform: translateY(-8px); border-color: rgba(255,255,255,0.2); }
        .stat-box:hover::before { opacity: 0.15; }

        .sb-icon { 
            font-size: 2.2rem; width: 60px; height: 60px; 
            display: flex; align-items: center; justify-content: center; 
            border-radius: 18px; margin-bottom: 20px; transition: 0.3s;
        }
        .stat-box:hover .sb-icon { transform: scale(1.1) rotate(-5deg); background: rgba(255,255,255,0.1); }
        
        .sb-val { font-size: 2.5rem; font-weight: 800; color: #fff; line-height: 1; letter-spacing: -1px; }
        .sb-lbl { color: var(--text-dim); font-size: 0.95rem; font-weight: 500; margin-top: 8px; }

        /* Color Variations */
        .sb-blue { --glow-color: var(--neon-blue); }
        .sb-blue .sb-icon { color: var(--neon-blue); background: rgba(0, 242, 255, 0.05); }
        .sb-blue:hover { box-shadow: 0 15px 40px -10px rgba(0, 242, 255, 0.2); }

        .sb-teal { --glow-color: var(--neon-teal); }
        .sb-teal .sb-icon { color: var(--neon-teal); background: rgba(0, 255, 157, 0.05); }
        .sb-teal:hover { box-shadow: 0 15px 40px -10px rgba(0, 255, 157, 0.2); }

        .sb-purple { --glow-color: var(--neon-purple); }
        .sb-purple .sb-icon { color: var(--neon-purple); background: rgba(189, 0, 255, 0.05); }
        .sb-purple:hover { box-shadow: 0 15px 40px -10px rgba(189, 0, 255, 0.2); }

        .sb-amber { --glow-color: var(--neon-amber); }
        .sb-amber .sb-icon { color: var(--neon-amber); background: rgba(255, 174, 0, 0.05); }
        .sb-amber:hover { box-shadow: 0 15px 40px -10px rgba(255, 174, 0, 0.2); }

        /* --- Bento Grid --- */
        .bento-grid {
            display: grid; grid-template-columns: 2.5fr 1fr; gap: 24px; min-height: 500px;
        }
        .main-col { display: flex; flex-direction: column; gap: 24px; }
        .side-col { display: flex; flex-direction: column; gap: 24px; }

        /* Chart Panels */
        .chart-panel, .mini-chart-card {
            background: var(--bg-surface);
            border: var(--border-glass); border-radius: var(--radius-xl);
            padding: 30px; position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            backdrop-filter: blur(20px);
        }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .sh-title { font-size: 1.2rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 12px; }
        .sh-title i { filter: drop-shadow(0 0 8px currentColor); }

        /* Action Buttons: The "Jewels" of the UI */
        .actions-strip {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
        }
        .action-btn {
            position: relative; height: 120px;
            background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
            border: 1px solid rgba(255,255,255,0.05); border-radius: 24px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
            text-decoration: none; color: var(--text-dim); overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .action-btn::before { /* Subtle pattern overlay */
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px; opacity: 0; transition: 0.3s;
        }
        .action-btn:hover {
            transform: translateY(-5px); border-color: rgba(255,255,255,0.2);
            box-shadow: 0 15px 30px -5px rgba(0,0,0,0.5);
            color: #fff; background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.03) 100%);
        }
        .action-btn:hover::before { opacity: 0.3; }
        
        .action-btn i { font-size: 2rem; transition: 0.3s; z-index: 1; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
        .action-btn:hover i { transform: scale(1.1); color: var(--neon-blue); filter: drop-shadow(0 0 10px var(--neon-blue)); }
        
        /* Specific Button Colors */
        .ab-red:hover { border-color: rgba(239, 68, 68, 0.4); }
        .ab-red:hover i { color: var(--neon-red); filter: drop-shadow(0 0 10px var(--neon-red)); }

        /* --- Dock --- */
        .dock-wrapper {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; pointer-events: none; z-index: 1000;
        }
        .glass-dock {
            pointer-events: auto;
            background: rgba(10, 10, 12, 0.75);
            backdrop-filter: blur(20px) saturate(200%);
            border: 1px solid rgba(255,255,255,0.12);
            padding: 12px 18px; border-radius: 32px;
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
            transition: 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .glass-dock:hover { background: rgba(15, 15, 20, 0.9); border-color: rgba(255,255,255,0.2); }
        
        .dock-link {
            width: 52px; height: 52px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 20px; color: var(--text-dim); font-size: 1.5rem;
            transition: all 0.25s cubic-bezier(0.25, 0.8, 0.5, 1);
            text-decoration: none; position: relative;
            background: transparent;
        }
        .dock-link:hover { 
            background: rgba(255,255,255,0.1); color: #fff; 
            transform: translateY(-10px) scale(1.1); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.4);
        }
        .dock-link.active { 
            background: rgba(255,255,255,0.1); color: #fff; 
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: inset 0 0 15px rgba(255,255,255,0.05);
        }
        .dock-link::after { /* Tooltip */
            content: attr(data-title);
            position: absolute; bottom: 130%; left: 50%; transform: translateX(-50%) scale(0.9);
            background: #000; color: #fff; padding: 6px 14px; border-radius: 12px;
            font-size: 0.85rem; font-weight: 600; opacity: 0; visibility: hidden; 
            transition: 0.2s; pointer-events: none; white-space: nowrap; border: 1px solid #333;
        }
        .dock-link:hover::after { opacity: 1; visibility: visible; bottom: 150%; transform: translateX(-50%) scale(1); }

        .dock-sep { width: 1px; height: 32px; background: rgba(255,255,255,0.1); margin: 0 6px; }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .bento-grid { grid-template-columns: 1fr; }
            .side-col { flex-direction: row; }
            .actions-strip { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 992px) { .actions-strip { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .dashboard-container { padding: 20px; }
            .header-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
            .profile-pill { margin-left: auto; }
            .stats-grid { grid-template-columns: 1fr; }
            .side-col { flex-direction: column; }
            .glass-dock { max-width: 92vw; overflow-x: auto; justify-content: flex-start; padding: 10px; }
            .dock-link { flex-shrink: 0; width: 48px; height: 48px; }
            .dock-link:hover { transform: none; }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- 1. Header -->
        <header class="header-bar anim">
            <div class="welcome-text">
                <h1><?php echo $greet; ?>، ادمین</h1>
                <p>
                    <span class="live-badge">System Online</span>
                    <span><i class="fa-regular fa-calendar"></i> <?php echo $today; ?></span>
                </p>
            </div>
            <div class="profile-pill">
                <div class="p-info">
                    <div class="p-name">مدیر سیستم</div>
                    <div class="p-role">Super Admin</div>
                </div>
                <div class="p-img"><i class="fa-solid fa-user-astronaut"></i></div>
            </div>
        </header>

        <!-- 2. Stats Row -->
        <section class="stats-grid anim d-1">
            <div class="stat-box sb-blue">
                <div class="sb-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                    <div class="sb-val"><?php echo number_format($stats['sales']); ?></div>
                    <div class="sb-lbl">درآمد کل (تومان)</div>
                </div>
            </div>
            <div class="stat-box sb-teal">
                <div class="sb-icon"><i class="fa-solid fa-receipt"></i></div>
                <div>
                    <div class="sb-val"><?php echo number_format($stats['orders']); ?></div>
                    <div class="sb-lbl">تعداد سفارشات</div>
                </div>
            </div>
            <div class="stat-box sb-purple">
                <div class="sb-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="sb-val"><?php echo number_format($stats['users']); ?></div>
                    <div class="sb-lbl">کل کاربران</div>
                </div>
            </div>
            <div class="stat-box sb-amber">
                <div class="sb-icon"><i class="fa-solid fa-user-plus"></i></div>
                <div>
                    <div class="sb-val"><?php echo number_format($stats['new_users']); ?></div>
                    <div class="sb-lbl">ثبت‌نام امروز</div>
                </div>
            </div>
        </section>

        <!-- 3. Bento Grid -->
        <section class="bento-grid anim d-2">
            
            <div class="main-col">
                <!-- Sales Chart -->
                <div class="chart-panel">
                    <div class="section-header">
                        <div class="sh-title"><i class="fa-solid fa-chart-line" style="color: var(--neon-blue);"></i> گزارش فروش زنده</div>
                        <select style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 12px; padding: 6px 12px; font-family: 'Vazirmatn';">
                            <option>۳۰ روز اخیر</option>
                        </select>
                    </div>
                    <div style="flex-grow: 1; min-height: 0;"><canvas id="salesChart"></canvas></div>
                </div>

                <!-- Action Tiles -->
                <div class="actions-strip">
                    <a href="users.php" class="action-btn">
                        <i class="fa-solid fa-users-gear"></i><span>کاربران</span>
                    </a>
                    <a href="invoice.php" class="action-btn">
                        <i class="fa-solid fa-file-invoice"></i><span>سفارشات</span>
                    </a>
                    <a href="product.php" class="action-btn">
                        <i class="fa-solid fa-box-open"></i><span>محصولات</span>
                    </a>
                    <a href="service.php" class="action-btn">
                        <i class="fa-solid fa-server"></i><span>سرویس‌ها</span>
                    </a>
                    <a href="payment.php" class="action-btn">
                        <i class="fa-solid fa-wallet"></i><span>امور مالی</span>
                    </a>
                    <a href="inbound.php" class="action-btn">
                        <i class="fa-solid fa-network-wired"></i><span>کانفیگ‌ها</span>
                    </a>
                    <a href="seeting_x_ui.php" class="action-btn">
                        <i class="fa-solid fa-tower-broadcast"></i><span>پنل X-UI</span>
                    </a>
                    <a href="cancelService.php" class="action-btn ab-red">
                        <i class="fa-solid fa-ban"></i><span>مسدودها</span>
                    </a>
                </div>
            </div>

            <div class="side-col">
                <!-- Status Chart -->
                <div class="mini-chart-card">
                    <div class="section-header">
                        <div class="sh-title"><i class="fa-solid fa-chart-pie" style="color: var(--neon-purple);"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="flex-grow: 1; position: relative; min-height: 200px;">
                        <canvas id="statusChart"></canvas>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                            <div style="font-size: 2rem; font-weight: 800; color: #fff; text-shadow: 0 0 10px rgba(255,255,255,0.3);"><?php echo $totalStatus; ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-dim);">سرویس</div>
                        </div>
                    </div>
                </div>

                <!-- Users Bar -->
                <div class="mini-chart-card">
                    <div class="section-header">
                        <div class="sh-title"><i class="fa-solid fa-chart-column" style="color: var(--neon-teal);"></i> روند جذب کاربر</div>
                    </div>
                    <div style="flex-grow: 1; min-height: 180px;"><canvas id="usersChart"></canvas></div>
                </div>
            </div>

        </section>

    </div>

    <!-- 4. Dock -->
    <div class="dock-wrapper anim d-3">
        <nav class="glass-dock">
            <a href="index.php" class="dock-link active" data-title="داشبورد"><i class="fa-solid fa-house"></i></a>
            <div class="dock-sep"></div>
            <a href="users.php" class="dock-link" data-title="کاربران"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-link" data-title="سفارشات"><i class="fa-solid fa-file-contract"></i></a>
            <a href="product.php" class="dock-link" data-title="محصولات"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-link" data-title="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
            <div class="dock-sep"></div>
            <a href="cancelService.php" class="dock-link" data-title="مسدودسازی" style="color: var(--neon-red);"><i class="fa-solid fa-ban"></i></a>
            <a href="payment.php" class="dock-link" data-title="مالی"><i class="fa-solid fa-wallet"></i></a>
            <a href="inbound.php" class="dock-link" data-title="کانفیگ"><i class="fa-solid fa-network-wired"></i></a>
            <a href="seeting_x_ui.php" class="dock-link" data-title="پنل X-UI"><i class="fa-solid fa-tower-broadcast"></i></a>
            <div class="dock-sep"></div>
            <a href="settings.php" class="dock-link" data-title="تنظیمات"><i class="fa-solid fa-gear"></i></a>
            <a href="logout.php" class="dock-link" style="color: var(--neon-red);" data-title="خروج"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Modern Chart Config
        Chart.defaults.font.family = 'Vazirmatn'; 
        Chart.defaults.color = '#94a3b8'; 
        Chart.defaults.borderColor = 'rgba(255,255,255,0.04)';
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Glow Line)
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 400);
        gradS.addColorStop(0, 'rgba(0, 242, 255, 0.25)');
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
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', titleFont: {size: 14}, bodyFont: {size: 14}, padding: 10, cornerRadius: 8, displayColors: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { display: false } },
                    y: { grid: { color: 'rgba(255,255,255,0.04)' }, border: { display: false } }
                },
                interaction: { mode: 'index', intersect: false }
            }
        });

        // 2. Status Chart (Neon Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels,
                datasets: [{
                    data: dPie.values,
                    backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#d946ef', '#f97316', '#334155'],
                    borderWidth: 0,
                    hoverOffset: 15,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '80%',
                plugins: { legend: { display: false } }
            }
        });

        // 3. Users Chart (Rounded Bar)
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: dUsers.values,
                    backgroundColor: '#00ff9d',
                    borderRadius: 6,
                    barThickness: 10
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