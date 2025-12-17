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
            --bg-void: #000000;
            --bg-surface: rgba(15, 15, 20, 0.6);
            --bg-dock: rgba(5, 5, 10, 0.95);
            
            /* High Contrast Neons */
            --neon-blue: #22d3ee;
            --neon-purple: #c084fc;
            --neon-teal: #2dd4bf;
            --neon-amber: #fbbf24;
            --neon-pink: #f472b6;
            --neon-red: #ef4444;
            
            --text-main: #ffffff;
            --text-dim: #94a3b8;
            
            /* Glass Effects */
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --border-glow: 1px solid rgba(255, 255, 255, 0.15);
            
            --shadow-float: 0 20px 60px -10px rgba(0,0,0,0.9);
            --glow-text: 0 0 20px rgba(34, 211, 238, 0.3);
            
            --radius-lg: 30px;
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
            background-image: 
                radial-gradient(circle at 15% 10%, rgba(34, 211, 238, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 85% 90%, rgba(192, 132, 252, 0.06) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 160px; /* More space for dock */
        }

        a { text-decoration: none; color: inherit; transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }

        /* --- Animations --- */
        @keyframes floatIn {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .anim-stagger { opacity: 0; animation: floatIn 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; } .d-4 { animation-delay: 0.4s; }

        /* --- Layout --- */
        .container-fluid-custom {
            width: 100%;
            padding: 50px 6%;
            max-width: 1920px;
            margin: 0 auto;
        }

        /* --- Header --- */
        .header-top {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 70px;
            padding-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .header-title h1 {
            font-size: 4rem; font-weight: 900; color: #fff; margin: 0; line-height: 1.1;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }
        .header-title p {
            color: var(--text-dim); margin-top: 10px; font-size: 1.4rem; font-weight: 300;
            display: flex; align-items: center; gap: 15px;
        }
        
        .user-pill {
            background: rgba(255,255,255,0.03); border: var(--border-glass);
            padding: 12px 24px; border-radius: var(--radius-pill);
            display: flex; align-items: center; gap: 15px;
            backdrop-filter: blur(15px); font-size: 1.1rem;
        }
        .user-avatar {
            width: 45px; height: 45px; border-radius: 50%;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            display: flex; align-items: center; justify-content: center; font-weight: bold;
            box-shadow: 0 0 20px rgba(34, 211, 238, 0.4); font-size: 1.2rem;
        }

        /* --- Stats Grid --- */
        .stats-deck {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px; margin-bottom: 80px;
        }
        .stat-card {
            background: var(--bg-surface);
            backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 40px;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 220px; position: relative; overflow: hidden;
            transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        }
        .stat-card:hover {
            transform: translateY(-15px) scale(1.02);
            border-color: rgba(255,255,255,0.2);
            box-shadow: var(--shadow-float), 0 0 40px var(--glow-c);
        }
        .stat-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, var(--glow-c) 0%, transparent 65%);
            opacity: 0.08; transition: 0.6s; pointer-events: none;
        }
        .stat-card:hover::before { opacity: 0.2; }

        .stat-top { display: flex; justify-content: space-between; align-items: center; z-index: 1; margin-bottom: 20px; }
        .stat-icon { font-size: 2.8rem; color: var(--text-dim); transition: 0.4s; }
        .stat-card:hover .stat-icon { color: #fff; transform: scale(1.1) rotate(-5deg); text-shadow: 0 0 20px var(--glow-c); }
        
        .stat-val { 
            font-size: 3.5rem; font-weight: 800; color: #fff; margin-top: auto; z-index: 1; line-height: 1;
            font-feature-settings: "tnum"; font-variant-numeric: tabular-nums;
        }
        .stat-lbl { font-size: 1.2rem; color: var(--text-dim); margin-top: 8px; z-index: 1; font-weight: 400; }

        .s-blue { --glow-c: var(--neon-blue); }
        .s-teal { --glow-c: var(--neon-teal); }
        .s-purple { --glow-c: var(--neon-purple); }
        .s-amber { --glow-c: var(--neon-amber); }

        /* --- Quick Actions (Extended) --- */
        .section-header { font-size: 2rem; color: #fff; font-weight: 800; margin-bottom: 40px; display: flex; align-items: center; gap: 15px; }
        .section-header::after { content: ''; height: 2px; width: 60px; background: var(--neon-purple); border-radius: 2px; display: block; }

        .action-deck {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px; margin-bottom: 80px;
        }
        .action-tile {
            background: rgba(255,255,255,0.02);
            border: var(--border-glass); border-radius: 26px;
            padding: 30px 20px; text-align: center;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px;
            transition: 0.5s; cursor: pointer; height: 180px;
            position: relative; overflow: hidden; text-decoration: none;
        }
        .action-tile:hover {
            background: rgba(255,255,255,0.04);
            border-color: var(--neon-blue);
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
        }
        
        .tile-icon { font-size: 3rem; color: var(--text-dim); transition: 0.4s; }
        .action-tile:hover .tile-icon { color: #fff; transform: scale(1.15); filter: drop-shadow(0 0 15px var(--neon-blue)); }
        .tile-text { font-size: 1.2rem; font-weight: 700; color: var(--text-highlight); transition: 0.3s; }
        .action-tile:hover .tile-text { color: #fff; }

        /* Danger Tile Override */
        .tile-danger:hover { border-color: var(--neon-red); }
        .tile-danger:hover .tile-icon { filter: drop-shadow(0 0 15px var(--neon-red)); }

        /* --- Charts Area --- */
        .charts-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-bottom: 60px;
        }
        .chart-box {
            background: var(--bg-surface);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 40px; position: relative;
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .chart-title { font-size: 1.8rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 15px; }

        /* --- Floating Dock (Centered & Responsive) --- */
        .dock-container {
            position: fixed; bottom: 40px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
        }
        .dock {
            pointer-events: auto; display: flex; align-items: center; gap: 8px;
            background: var(--bg-dock);
            backdrop-filter: blur(40px) saturate(200%);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 30px; padding: 15px 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.15);
            transition: all 0.4s ease;
            max-width: 95vw;
            overflow-x: auto; /* Enable scrolling if too many items */
            scrollbar-width: none; /* Firefox hide scrollbar */
        }
        .dock::-webkit-scrollbar { display: none; /* Chrome hide scrollbar */ }
        
        .dock-item {
            position: relative;
            flex-shrink: 0; /* Prevent shrinking */
            width: 60px; height: 60px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 18px;
            color: var(--text-dim);
            font-size: 1.6rem;
            transition: all 0.3s cubic-bezier(0.3, 0.7, 0.4, 1.5);
            text-decoration: none;
        }
        .dock-item:hover {
            width: 75px; height: 75px; font-size: 2.2rem; color: #fff;
            background: rgba(255,255,255,0.1); transform: translateY(-20px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4); margin: 0 8px; z-index: 10;
        }
        .dock-item.active {
            color: var(--neon-blue); background: rgba(34, 211, 238, 0.15);
            box-shadow: 0 0 25px rgba(34, 211, 238, 0.2);
        }
        .dock-item.active::after {
            content: ''; position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
            width: 5px; height: 5px; background: var(--neon-blue); border-radius: 50%;
            box-shadow: 0 0 8px var(--neon-blue);
        }
        .dock-tooltip {
            position: absolute; top: -55px; left: 50%; transform: translateX(-50%) scale(0.8);
            background: rgba(0,0,0,0.9); color: #fff; padding: 6px 14px; border-radius: 10px;
            font-size: 0.9rem; opacity: 0; transition: 0.2s; pointer-events: none;
            border: 1px solid rgba(255,255,255,0.2); white-space: nowrap; font-weight: bold;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5); visibility: hidden;
        }
        .dock-item:hover .dock-tooltip { opacity: 1; visibility: visible; transform: translateX(-50%) scale(1); top: -70px; }

        @media (max-width: 1200px) { .charts-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 4%; padding-bottom: 120px; }
            .header-top { flex-direction: column; align-items: flex-start; gap: 20px; }
            .header-title h1 { font-size: 2.5rem; }
            .stats-deck { grid-template-columns: 1fr; gap: 25px; }
            /* Mobile Dock Adjustments */
            .dock { width: 95%; justify-content: flex-start; padding: 10px 15px; gap: 5px; bottom: 20px; }
            .dock-item { width: 50px; height: 50px; font-size: 1.4rem; }
            .dock-item:hover { width: 60px; height: 60px; transform: translateY(-10px); }
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
                    وضعیت سیستم: <span style="color: var(--neon-teal); font-weight: 700; text-shadow: 0 0 10px rgba(45, 212, 191, 0.4);">پایدار و آنلاین</span>
                    <span style="margin: 0 15px; color: rgba(255,255,255,0.2);">|</span>
                    <?php echo $today; ?>
                </p>
            </div>
            <div class="user-pill">
                <span>مدیر ارشد سیستم</span>
                <div class="user-avatar"><i class="fa-solid fa-user-astronaut"></i></div>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="stats-deck anim d-1">
            <div class="stat-card s-blue">
                <div class="stat-top"><i class="fa-solid fa-sack-dollar stat-icon"></i><span style="background: rgba(34, 211, 238, 0.1); color: var(--neon-blue); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">درآمد کل</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['sales']); ?></div><div class="stat-lbl">تومان ایران</div></div>
            </div>
            <div class="stat-card s-teal">
                <div class="stat-top"><i class="fa-solid fa-file-invoice-dollar stat-icon"></i><span style="background: rgba(45, 212, 191, 0.1); color: var(--neon-teal); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">تراکنش‌ها</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['orders']); ?></div><div class="stat-lbl">سفارش موفق</div></div>
            </div>
            <div class="stat-card s-purple">
                <div class="stat-top"><i class="fa-solid fa-users-rays stat-icon"></i><span style="background: rgba(192, 132, 252, 0.1); color: var(--neon-purple); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">کاربران</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['users']); ?></div><div class="stat-lbl">مشترکین فعال</div></div>
            </div>
            <div class="stat-card s-amber">
                <div class="stat-top"><i class="fa-solid fa-user-plus stat-icon"></i><span style="background: rgba(251, 191, 36, 0.1); color: var(--neon-amber); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">امروز</span></div>
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
            <div style="display: flex; flex-direction: column; gap: 40px;">
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

    <!-- Floating Dock (Full Quick Actions) -->
    <div class="dock-container anim d-4">
        <nav class="dock">
            <a href="index.php" class="dock-item active">
                <i class="fa-solid fa-house-chimney"></i><span class="dock-tooltip">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <i class="fa-solid fa-file-contract"></i><span class="dock-tooltip">سفارشات</span>
            </a>
            <a href="users.php" class="dock-item">
                <i class="fa-solid fa-users-gear"></i><span class="dock-tooltip">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <i class="fa-solid fa-box-open"></i><span class="dock-tooltip">محصولات</span>
            </a>
            <a href="payment.php" class="dock-item">
                <i class="fa-solid fa-wallet"></i><span class="dock-tooltip">مالی</span>
            </a>
            <a href="inbound.php" class="dock-item">
                <i class="fa-solid fa-network-wired"></i><span class="dock-tooltip">کانفیگ‌ها</span>
            </a>
            <a href="service.php" class="dock-item">
                <i class="fa-solid fa-server"></i><span class="dock-tooltip">سرویس‌ها</span>
            </a>
            <a href="metrics.php" class="dock-item">
                <i class="fa-solid fa-chart-simple"></i><span class="dock-tooltip">آمار</span>
            </a>
            <a href="settings.php" class="dock-item">
                <i class="fa-solid fa-sliders"></i><span class="dock-tooltip">تنظیمات</span>
            </a>
            <a href="seeting_x_ui.php" class="dock-item">
                <i class="fa-solid fa-tower-broadcast"></i><span class="dock-tooltip">X-UI</span>
            </a>
            <a href="text.php" class="dock-item">
                <i class="fa-solid fa-file-lines"></i><span class="dock-tooltip">متن‌ها</span>
            </a>
            <a href="keyboard.php" class="dock-item">
                <i class="fa-solid fa-keyboard"></i><span class="dock-tooltip">کیبورد</span>
            </a>
            <a href="cancelService.php" class="dock-item" style="color: var(--neon-pink);">
                <i class="fa-solid fa-ban"></i><span class="dock-tooltip">مسدودی</span>
            </a>
            <div style="width: 2px; height: 30px; background: rgba(255,255,255,0.1); margin: 0 4px;"></div>
            <a href="logout.php" class="dock-item" style="color: var(--neon-red);">
                <i class="fa-solid fa-power-off"></i><span class="dock-tooltip">خروج</span>
            </a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = 'Vazirmatn'; Chart.defaults.font.size = 15; Chart.defaults.font.weight = 'bold'; Chart.defaults.color = '#64748B'; Chart.defaults.borderColor = 'rgba(255,255,255,0.04)'; Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(5, 5, 10, 0.9)'; Chart.defaults.plugins.tooltip.padding = 18; Chart.defaults.plugins.tooltip.cornerRadius = 14; Chart.defaults.plugins.tooltip.titleFont = { size: 16, weight: 800 }; Chart.defaults.plugins.tooltip.bodyFont = { size: 14 };
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 500); gradS.addColorStop(0, '#22d3ee'); gradS.addColorStop(1, 'rgba(34, 211, 238, 0.02)');
        new Chart(ctxS, { type: 'line', data: { labels: dSales.labels, datasets: [{ label: 'فروش', data: dSales.values, borderColor: '#22d3ee', backgroundColor: gradS, borderWidth: 3, pointRadius: 0, pointHoverRadius: 8, pointBackgroundColor: '#fff', fill: true, tension: 0.45 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 13 } } }, y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { font: { size: 13 } } } } } });
        new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: { labels: dPie.labels, datasets: [{ data: dPie.values, backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#8b5cf6', '#f97316', '#334155'], borderWidth: 0, hoverOffset: 20 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '82%', plugins: { legend: { position: 'right', labels: { boxWidth: 12, color: '#94a3b8', font: {size: 13}, usePointStyle: true, padding: 15 } } } } });
        const ctxU = document.getElementById('usersChart').getContext('2d');
        const gradU = ctxU.createLinearGradient(0, 0, 0, 300); gradU.addColorStop(0, '#2dd4bf'); gradU.addColorStop(1, 'rgba(45, 212, 191, 0.3)');
        new Chart(ctxU, { type: 'bar', data: { labels: dUsers.labels, datasets: [{ label: 'کاربر', data: dUsers.values, backgroundColor: gradU, borderRadius: 6, barThickness: 20 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } } });
    </script>
</body>
</html>