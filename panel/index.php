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
    <title>داشبورد مدیریت حرفه‌ای</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #8b5cf6; /* Violet */
            --primary-glow: rgba(139, 92, 246, 0.5);
            --secondary: #06b6d4; /* Cyan */
            --accent: #d946ef; /* Fuchsia */
            --bg-void: #02040a;
            --glass: rgba(10, 15, 28, 0.75);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            color: #fff;
            margin: 0; padding: 0;
            min-height: 100vh;
            padding-bottom: 120px;
            overflow-x: hidden;
        }

        /* --- QUANTUM BACKGROUND --- */
        .background-engine {
            position: fixed; inset: 0; z-index: -10;
            background: radial-gradient(circle at 50% 50%, #0f172a 0%, #02040a 100%);
        }
        .star-field {
            position: absolute; inset: 0;
            background-image: 
                radial-gradient(1.5px 1.5px at 25% 35%, #fff, transparent),
                radial-gradient(2px 2px at 50% 60%, #fff, transparent),
                radial-gradient(1.5px 1.5px at 75% 20%, #fff, transparent);
            background-size: 400px 400px;
            animation: moveStars 150s linear infinite;
            opacity: 0.4;
        }
        .nebula-core {
            position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.25;
            animation: nebulaFlow 40s infinite alternate ease-in-out;
        }
        .nc-1 { width: 800px; height: 800px; background: var(--primary); top: -20%; left: -10%; }
        .nc-2 { width: 700px; height: 700px; background: var(--accent); bottom: -15%; right: -5%; }

        @keyframes moveStars { from { background-position: 0 0; } to { background-position: 0 4000px; } }
        @keyframes nebulaFlow {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            100% { transform: translate(100px, 50px) scale(1.2) rotate(10deg); }
        }

        /* --- DASHBOARD CONTAINER --- */
        .dashboard-container {
            width: 100%; max-width: 1600px; margin: 0 auto;
            padding: 40px 20px;
            display: flex; flex-direction: column; gap: 40px;
        }

        /* --- GLASS HEADER --- */
        .header-section {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding: 30px; background: var(--glass); backdrop-filter: blur(20px);
            border-radius: 30px; border: 1px solid var(--glass-border);
            animation: fadeInDown 0.8s ease-out;
        }
        .ht-main { font-size: 2.5rem; font-weight: 900; margin: 0; color: #fff; }
        .ht-sub { color: #94a3b8; font-size: 1rem; margin-top: 5px; }

        /* --- HOLO STATS --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;
        }
        .holo-card {
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 30px; padding: 30px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            transition: 0.4s; backdrop-filter: blur(15px);
        }
        .holo-card:hover { transform: translateY(-10px); border-color: var(--primary); box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .hc-val { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 5px; }
        .hc-lbl { color: #94a3b8; font-size: 0.9rem; }
        .hc-icon { width: 60px; height: 60px; border-radius: 18px; background: rgba(255,255,255,0.03); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--primary); }

        /* --- MAIN GRID --- */
        .bento-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px;
        }
        .glass-panel {
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 35px; padding: 35px; backdrop-filter: blur(25px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
        }
        .panel-title { font-size: 1.3rem; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; }
        .panel-title i { color: var(--secondary); }

        /* --- NAVIGATION TILES --- */
        .actions-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px;
            margin-top: 30px;
        }
        .nav-tile {
            background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 25px 15px; text-align: center;
            text-decoration: none; color: #cbd5e1; transition: 0.3s;
        }
        .nav-tile:hover { background: rgba(255,255,255,0.06); border-color: var(--primary); transform: scale(1.05); color: #fff; }
        .nt-icon { font-size: 1.8rem; margin-bottom: 12px; display: block; }
        .nt-label { font-size: 0.85rem; font-weight: 600; }

        /* --- SUPER DOCK --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 9999;
        }
        .super-dock {
            background: rgba(10, 10, 15, 0.85); backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.12); padding: 12px; border-radius: 32px;
            display: flex; align-items: center; gap: 10px; box-shadow: 0 20px 60px rgba(0,0,0,0.8);
        }
        .dock-item {
            width: 55px; height: 55px; display: flex; align-items: center; justify-content: center;
            border-radius: 20px; color: #94a3b8; font-size: 1.5rem; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none; position: relative;
        }
        .dock-item:hover { transform: translateY(-15px) scale(1.2); color: #fff; background: rgba(255,255,255,0.1); }
        .dock-item.active { color: var(--primary); background: rgba(139, 92, 246, 0.1); }

        /* --- KILLHOSEIN SIGNATURE --- */
        .killhosein-signature {
            margin-top: 80px; padding-bottom: 60px; text-align: center;
            border-top: 1px solid rgba(255,255,255,0.05); padding-top: 40px;
        }
        .signature-link {
            text-decoration: none; display: inline-flex; flex-direction: column; align-items: center; gap: 15px;
            transition: 0.4s;
        }
        .sig-text { font-size: 15px; color: rgba(255,255,255,0.5); font-weight: 500; }
        .sig-brand {
            font-size: 45px; font-weight: 950;
            background: linear-gradient(90deg, #fff, #a78bfa, var(--secondary), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: 2px; filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.5));
            display: flex; align-items: center; gap: 20px;
        }
        .tg-icon {
            font-size: 55px; color: #229ED9;
            -webkit-text-fill-color: #229ED9;
            filter: drop-shadow(0 0 20px rgba(34, 158, 217, 0.7));
            transition: 0.4s;
        }
        .signature-link:hover { transform: scale(1.08); }
        .signature-link:hover .tg-icon { transform: rotate(-12deg) scale(1.2); }

        /* Chart Customization */
        canvas { filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3)); }

        @media (max-width: 1100px) {
            .bento-grid { grid-template-columns: 1fr; }
            .ht-main { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <!-- Space Engine -->
    <div class="background-engine">
        <div class="star-field"></div>
        <div class="nebula-core nc-1"></div>
        <div class="nebula-core nc-2"></div>
    </div>

    <div class="dashboard-container">
        
        <!-- 1. Header -->
        <header class="header-section">
            <div>
                <h1 class="ht-main"><?php echo $greet; ?>، ادمین</h1>
                <div class="ht-sub">داشبورد فرماندهی سیستم آماده استفاده است.</div>
            </div>
            <div class="hidden md:flex flex-col items-end">
                <span class="text-white font-bold opacity-90"><?php echo $today; ?></span>
                <span class="text-[11px] text-cyan-400 font-mono tracking-widest uppercase mt-1">System Online</span>
            </div>
        </header>

        <!-- 2. Stats Row -->
        <section class="stats-grid">
            <div class="holo-card">
                <div>
                    <div class="hc-val"><?php echo number_format($stats['sales']); ?></div>
                    <div class="hc-lbl">درآمد کل (تومان)</div>
                </div>
                <div class="hc-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            </div>
            <div class="holo-card">
                <div>
                    <div class="hc-val"><?php echo number_format($stats['orders']); ?></div>
                    <div class="hc-lbl">سفارشات موفق</div>
                </div>
                <div class="hc-icon" style="color: var(--secondary);"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <div class="holo-card">
                <div>
                    <div class="hc-val"><?php echo number_format($stats['users']); ?></div>
                    <div class="hc-lbl">کل کاربران</div>
                </div>
                <div class="hc-icon" style="color: var(--accent);"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="holo-card">
                <div>
                    <div class="hc-val"><?php echo number_format($stats['new_users']); ?></div>
                    <div class="hc-lbl">ثبت‌نام امروز</div>
                </div>
                <div class="hc-icon" style="color: #fbbf24;"><i class="fa-solid fa-user-plus"></i></div>
            </div>
        </section>

        <!-- 3. Main Bento -->
        <section class="bento-grid">
            <div class="flex flex-col gap-8">
                <!-- Sales Chart -->
                <div class="glass-panel">
                    <div class="panel-title"><i class="fa-solid fa-chart-line"></i> نمودار فروش ۳۰ روز اخیر</div>
                    <div style="height: 350px;"><canvas id="salesChart"></canvas></div>
                </div>

                <!-- Navigation Tiles -->
                <div class="glass-panel">
                    <div class="panel-title"><i class="fa-solid fa-layer-group"></i> دسترسی سریع به بخش‌ها</div>
                    <div class="actions-grid">
                        <a href="users.php" class="nav-tile"><i class="fa-solid fa-users-gear nt-icon"></i><span class="nt-label">کاربران</span></a>
                        <a href="invoice.php" class="nav-tile"><i class="fa-solid fa-file-invoice nt-icon"></i><span class="nt-label">سفارشات</span></a>
                        <a href="product.php" class="nav-tile"><i class="fa-solid fa-box-open nt-icon"></i><span class="nt-label">محصولات</span></a>
                        <a href="service.php" class="nav-tile"><i class="fa-solid fa-server nt-icon"></i><span class="nt-label">سرویس‌ها</span></a>
                        <a href="payment.php" class="nav-tile"><i class="fa-solid fa-wallet nt-icon"></i><span class="nt-label">مالی</span></a>
                        <a href="keyboard.php" class="nav-tile"><i class="fa-solid fa-network-wired nt-icon"></i><span class="nt-label">دکمه‌ها</span></a>
                        <a href="seeting_x_ui.php" class="nav-tile"><i class="fa-solid fa-tower-broadcast nt-icon"></i><span class="nt-label">X-UI</span></a>
                        <a href="cancelService.php" class="nav-tile" style="border-color: rgba(239,68,68,0.2);"><i class="fa-solid fa-ban nt-icon" style="color: #ef4444;"></i><span class="nt-label">مسدودها</span></a>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-8">
                <!-- Status Doughnut -->
                <div class="glass-panel text-center">
                    <div class="panel-title justify-center"><i class="fa-solid fa-chart-pie"></i> وضعیت سرویس‌ها</div>
                    <div style="position: relative; height: 250px;">
                        <canvas id="statusChart"></canvas>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none;">
                            <div style="font-size: 2.2rem; font-weight: 800;"><?php echo $totalStatus; ?></div>
                            <div style="font-size: 0.7rem; opacity: 0.6; tracking-widest: 2px;">TOTAL</div>
                        </div>
                    </div>
                </div>

                <!-- User Growth -->
                <div class="glass-panel">
                    <div class="panel-title"><i class="fa-solid fa-chart-column"></i> جذب کاربر جدید</div>
                    <div style="height: 200px;"><canvas id="usersChart"></canvas></div>
                </div>
            </div>
        </section>

        <!-- 4. Signature -->
        <div class="killhosein-signature">
            <a href="https://t.me/KillHosein" class="signature-link" target="_blank">
                <span class="sig-text">Designed & Developed by</span>
                <span class="sig-brand">
                    <i class="fa-brands fa-telegram tg-icon"></i> KillHosein
                </span>
            </a>
        </div>
    </div>

    <!-- Super Dock -->
    <div class="dock-container">
        <nav class="super-dock">
            <a href="index.php" class="dock-item active"><i class="fa-solid fa-house"></i></a>
            <a href="users.php" class="dock-item"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-item"><i class="fa-solid fa-receipt"></i></a>
            <a href="product.php" class="dock-item"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-item"><i class="fa-solid fa-server"></i></a>
            <a href="settings.php" class="dock-item"><i class="fa-solid fa-gear"></i></a>
            <a href="login.php" class="dock-item" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart.js Configuration
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = 'rgba(255,255,255,0.4)';
        Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.03)';

        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 350);
        gradS.addColorStop(0, 'rgba(139, 92, 246, 0.4)');
        gradS.addColorStop(1, 'rgba(139, 92, 246, 0)');

        new Chart(ctxS, {
            type: 'line',
            data: {
                labels: dSales.labels,
                datasets: [{
                    label: 'فروش (تومان)',
                    data: dSales.values,
                    borderColor: '#8b5cf6',
                    backgroundColor: gradS,
                    borderWidth: 4,
                    pointRadius: 0,
                    pointHoverRadius: 10,
                    pointHoverBackgroundColor: '#fff',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { display: false }, grid: { display: false } }, y: { display: false } }
            }
        });

        // 2. Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels,
                datasets: [{
                    data: dPie.values,
                    backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#d946ef', '#f97316', '#334155'],
                    borderWidth: 0,
                    hoverOffset: 15,
                    cutout: '82%',
                    spacing: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // 3. Users Chart
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels,
                datasets: [{
                    data: dUsers.values,
                    backgroundColor: '#06b6d4',
                    borderRadius: 8,
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