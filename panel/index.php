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
            /* Palette: Void & Neon */
            --bg-void: #050508;
            --bg-surface: rgba(22, 22, 28, 0.65);
            --bg-card: rgba(30, 30, 40, 0.4);
            --bg-dock: rgba(10, 10, 15, 0.85);
            
            --neon-blue: #00f2ff;
            --neon-purple: #d946ef;
            --neon-teal: #2dd4bf;
            --neon-amber: #f59e0b;
            --neon-pink: #ec4899;
            --neon-red: #ef4444;
            
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --border-hover: 1px solid rgba(255, 255, 255, 0.2);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --shadow-float: 0 15px 40px -10px rgba(0, 0, 0, 0.7);
        }

        /* --- Base Setup --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-void);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(217, 70, 239, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 242, 255, 0.08) 0%, transparent 40%);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 120px;
        }

        /* --- Animations --- */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.98) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .anim { animation: fadeInScale 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } 
        .d-2 { animation-delay: 0.2s; } 
        .d-3 { animation-delay: 0.3s; }

        /* --- Main Layout: Bento Grid Style --- */
        .dashboard-container {
            width: 100%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Header */
        .header-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 10px 20px 10px;
        }
        .welcome-text h1 { margin: 0; font-size: 2.5rem; font-weight: 900; letter-spacing: -1px; background: linear-gradient(to right, #fff, #cbd5e1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .welcome-text p { margin: 5px 0 0; color: var(--text-dim); display: flex; align-items: center; gap: 10px; }
        .live-badge { font-size: 0.75rem; background: rgba(0, 242, 255, 0.1); color: var(--neon-blue); padding: 2px 10px; border-radius: 20px; border: 1px solid rgba(0, 242, 255, 0.2); }

        /* User Profile Pill */
        .profile-pill {
            display: flex; align-items: center; gap: 15px;
            background: var(--bg-card); padding: 8px 8px 8px 24px;
            border-radius: 50px; border: var(--border-glass);
            backdrop-filter: blur(10px);
            transition: 0.3s;
        }
        .profile-pill:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); }
        .p-info { text-align: left; line-height: 1.2; }
        .p-name { font-weight: 700; color: #fff; font-size: 0.95rem; }
        .p-role { font-size: 0.75rem; color: var(--neon-purple); }
        .p-img { width: 44px; height: 44px; background: linear-gradient(135deg, #ddd, #fff); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #000; font-size: 1.2rem; }

        /* --- Stats Row --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .stat-box {
            background: var(--bg-surface);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 24px;
            position: relative;
            overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 160px;
            transition: 0.3s;
        }
        .stat-box::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at top right, rgba(255,255,255,0.05), transparent 60%); opacity: 0; transition: 0.3s; }
        .stat-box:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.2); box-shadow: var(--shadow-float); }
        .stat-box:hover::before { opacity: 1; }

        .sb-icon { font-size: 2rem; opacity: 0.8; margin-bottom: 15px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 14px; background: rgba(255,255,255,0.03); }
        .sb-val { font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1; }
        .sb-lbl { color: var(--text-dim); font-size: 0.95rem; font-weight: 500; margin-top: 5px; }
        
        /* Specific Colors */
        .sb-blue .sb-icon { color: var(--neon-blue); background: rgba(0, 242, 255, 0.08); }
        .sb-teal .sb-icon { color: var(--neon-teal); background: rgba(45, 212, 191, 0.08); }
        .sb-purple .sb-icon { color: var(--neon-purple); background: rgba(217, 70, 239, 0.08); }
        .sb-amber .sb-icon { color: var(--neon-amber); background: rgba(245, 158, 11, 0.08); }

        /* --- Main Content Grid (Bento) --- */
        .bento-grid {
            display: grid;
            grid-template-columns: 2.5fr 1fr; /* Main area vs Sidebar area */
            gap: 20px;
            min-height: 500px;
        }
        
        /* Left Column (Main Chart + Actions) */
        .main-col { display: flex; flex-direction: column; gap: 20px; }
        
        .chart-panel {
            background: var(--bg-surface);
            border: var(--border-glass);
            border-radius: var(--radius-xl);
            padding: 25px;
            position: relative;
            flex-grow: 1;
            min-height: 400px;
            display: flex; flex-direction: column;
        }
        
        /* Actions Grid (Tight & Clean) */
        .actions-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 15px;
        }
        .action-btn {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 18px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: var(--text-dim);
            transition: 0.3s;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
            height: 100px;
        }
        .action-btn:hover {
            background: rgba(255,255,255,0.08);
            border-color: var(--neon-blue);
            transform: translateY(-3px);
            color: #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .action-btn i { font-size: 1.5rem; transition: 0.3s; }
        .action-btn:hover i { transform: scale(1.2); color: var(--neon-blue); }
        .ab-red:hover { border-color: var(--neon-red); }
        .ab-red:hover i { color: var(--neon-red); }

        /* Right Column (Secondary Charts) */
        .side-col { display: flex; flex-direction: column; gap: 20px; }
        
        .mini-chart-card {
            background: var(--bg-surface);
            border: var(--border-glass);
            border-radius: var(--radius-xl);
            padding: 20px;
            flex: 1;
            display: flex; flex-direction: column;
            min-height: 250px;
        }

        .section-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
        }
        .sh-title { font-size: 1.1rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }

        /* --- Dock --- */
        .dock-wrapper {
            position: fixed; bottom: 20px; left: 0; right: 0;
            display: flex; justify-content: center; pointer-events: none; z-index: 100;
        }
        .glass-dock {
            pointer-events: auto;
            background: rgba(15, 15, 20, 0.6);
            backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 8px;
            border-radius: 24px;
            display: flex; gap: 6px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            transition: 0.3s;
        }
        .glass-dock:hover { background: rgba(15, 15, 20, 0.8); transform: translateY(-5px); }
        .dock-link {
            width: 45px; height: 45px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 16px;
            color: var(--text-dim);
            font-size: 1.2rem;
            transition: 0.2s;
            text-decoration: none;
            position: relative;
        }
        .dock-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: scale(1.1); }
        .dock-link.active { background: rgba(255,255,255,0.15); color: #fff; }
        .dock-link::after {
            content: attr(data-title);
            position: absolute; bottom: 120%; left: 50%; transform: translateX(-50%);
            background: #000; color: #fff; padding: 4px 10px; border-radius: 6px;
            font-size: 0.75rem; opacity: 0; visibility: hidden; transition: 0.2s; pointer-events: none; white-space: nowrap;
        }
        .dock-link:hover::after { opacity: 1; visibility: visible; bottom: 130%; }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .bento-grid { grid-template-columns: 1fr; }
            .side-col { flex-direction: row; }
        }
        @media (max-width: 768px) {
            .dashboard-container { padding: 15px; gap: 15px; }
            .header-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
            .profile-pill { margin-left: auto; }
            .stats-grid { grid-template-columns: 1fr; }
            .side-col { flex-direction: column; }
            .actions-strip { grid-template-columns: repeat(3, 1fr); }
            .glass-dock { max-width: 90vw; overflow-x: auto; padding: 10px; justify-content: flex-start; }
            .dock-link { flex-shrink: 0; }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- 1. Header Section -->
        <header class="header-bar anim">
            <div class="welcome-text">
                <h1><?php echo $greet; ?>، ادمین</h1>
                <p>
                    <span class="live-badge">Online</span>
                    <span><?php echo $today; ?></span>
                </p>
            </div>
            <div class="profile-pill">
                <div class="p-info">
                    <div class="p-name">مدیر کل سیستم</div>
                    <div class="p-role">Super Admin</div>
                </div>
                <div class="p-img"><i class="fa-solid fa-user"></i></div>
            </div>
        </header>

        <!-- 2. Stats Row (No Empty Space) -->
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

        <!-- 3. Main Bento Grid -->
        <section class="bento-grid anim d-2">
            
            <!-- Left Column -->
            <div class="main-col">
                <!-- Big Chart -->
                <div class="chart-panel">
                    <div class="section-header">
                        <div class="sh-title"><i class="fa-solid fa-chart-line" style="color: var(--neon-blue);"></i> گزارش فروش زنده</div>
                        <select style="background: rgba(255,255,255,0.05); border: none; color: #fff; border-radius: 8px; padding: 5px 10px;">
                            <option>۳۰ روز اخیر</option>
                        </select>
                    </div>
                    <div style="flex-grow: 1; min-height: 0;"><canvas id="salesChart"></canvas></div>
                </div>

                <!-- Action Strip (Compact Grid) -->
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
                    <a href="inbound.php" class="action-btn">
                        <i class="fa-solid fa-network-wired"></i><span>کانفیگ</span>
                    </a>
                    <a href="cancelService.php" class="action-btn ab-red">
                        <i class="fa-solid fa-ban"></i><span>مسدود</span>
                    </a>
                </div>
            </div>

            <!-- Right Column -->
            <div class="side-col">
                <!-- Pie Chart -->
                <div class="mini-chart-card">
                    <div class="section-header">
                        <div class="sh-title"><i class="fa-solid fa-chart-pie" style="color: var(--neon-purple);"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="flex-grow: 1; position: relative; min-height: 200px;">
                        <canvas id="statusChart"></canvas>
                        <div class="doughnut-center">
                            <div class="dc-val" style="font-size: 1.5rem; font-weight: bold; color: #fff; text-align: center; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"><?php echo $totalStatus; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Bar Chart -->
                <div class="mini-chart-card">
                    <div class="section-header">
                        <div class="sh-title"><i class="fa-solid fa-chart-column" style="color: var(--neon-teal);"></i> روند جذب کاربر</div>
                    </div>
                    <div style="flex-grow: 1; min-height: 180px;"><canvas id="usersChart"></canvas></div>
                </div>
            </div>

        </section>

    </div>

    <!-- 4. Glass Dock -->
    <div class="dock-wrapper anim d-3">
        <nav class="glass-dock">
            <a href="index.php" class="dock-link active" data-title="داشبورد"><i class="fa-solid fa-house"></i></a>
            <a href="users.php" class="dock-link" data-title="کاربران"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-link" data-title="سفارشات"><i class="fa-solid fa-file-contract"></i></a>
            <div style="width: 1px; background: rgba(255,255,255,0.1); margin: 5px;"></div>
            <a href="product.php" class="dock-link" data-title="محصولات"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-link" data-title="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
            <a href="payment.php" class="dock-link" data-title="مالی"><i class="fa-solid fa-wallet"></i></a>
            <a href="inbound.php" class="dock-link" data-title="کانفیگ"><i class="fa-solid fa-network-wired"></i></a>
            <a href="seeting_x_ui.php" class="dock-link" data-title="پنل X-UI"><i class="fa-solid fa-tower-broadcast"></i></a>
            <div style="width: 1px; background: rgba(255,255,255,0.1); margin: 5px;"></div>
            <a href="settings.php" class="dock-link" data-title="تنظیمات"><i class="fa-solid fa-gear"></i></a>
            <a href="logout.php" class="dock-link" style="color: var(--neon-red);" data-title="خروج"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart Config
        Chart.defaults.font.family = 'Vazirmatn'; 
        Chart.defaults.color = '#94a3b8'; 
        Chart.defaults.borderColor = 'rgba(255,255,255,0.03)';
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Area)
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 400);
        gradS.addColorStop(0, 'rgba(0, 242, 255, 0.2)');
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
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { display: false } }, // Minimal look
                    y: { grid: { color: 'rgba(255,255,255,0.02)' } }
                },
                interaction: { mode: 'index', intersect: false }
            }
        });

        // 2. Status Chart (Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels,
                datasets: [{
                    data: dPie.values,
                    backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#d946ef', '#f97316', '#334155'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } } // Hide legend to save space, rely on tooltips
            }
        });

        // 3. Users Chart (Bar)
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: dUsers.values,
                    backgroundColor: '#2dd4bf',
                    borderRadius: 4
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