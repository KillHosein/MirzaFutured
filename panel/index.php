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

// Status Config
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
    <title>پنل مدیریت حرفه‌ای | Quantum Studio</title>
    
    <!-- Libraries -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.4);
            --secondary: #06b6d4;
            --accent: #d946ef;
            --bg-void: #000;
            --glass-base: rgba(10, 15, 28, 0.72);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-shine: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, transparent 100%);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            color: #fff;
            margin: 0; padding: 0;
            min-height: 100vh;
            padding-bottom: 120px;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- ANIMATED VIDEO BACKGROUND --- */
        .video-background {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -10;
            overflow: hidden;
        }
        .video-background video {
            width: 100%; height: 100%;
            object-fit: cover;
            filter: brightness(0.4) contrast(1.2) saturate(0.8);
        }
        .video-overlay {
            position: absolute; inset: 0;
            background: radial-gradient(circle at center, transparent 0%, #000 100%);
            opacity: 0.5;
        }

        .noise-layer {
            position: fixed; inset: 0; z-index: -5; opacity: 0.04; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* --- DASHBOARD ELEMENTS --- */
        .main-wrapper {
            width: 100%; max-width: 1700px; margin: 0 auto;
            padding: 40px 30px;
            display: flex; flex-direction: column; gap: 40px;
        }

        /* Glass Header */
        .glass-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 35px 40px; background: var(--glass-base); backdrop-filter: blur(30px);
            border-radius: 35px; border: 1px solid var(--glass-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative; overflow: hidden;
        }
        .header-title-block h1 { font-size: 2.8rem; font-weight: 950; margin: 0; letter-spacing: -1px; }
        .header-title-block p { color: #94a3b8; font-size: 1.1rem; margin-top: 8px; font-weight: 300; }

        .system-pill {
            background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 8px 18px; border-radius: 99px; display: flex; align-items: center; gap: 10px;
        }
        .status-pulse { width: 10px; height: 10px; background: #10b981; border-radius: 50%; box-shadow: 0 0 12px #10b981; animation: pulse 2s infinite; }

        /* Holo Stats */
        .stats-container {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;
        }
        .stat-card {
            background: var(--glass-base); border: 1px solid var(--glass-border);
            border-radius: 32px; padding: 35px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            transition: 0.4s cubic-bezier(0.2, 0.8, 0.2, 1); backdrop-filter: blur(20px);
        }
        .stat-card:hover {
            transform: translateY(-12px) scale(1.02);
            border-color: rgba(255,255,255,0.2);
            box-shadow: 0 40px 80px -20px rgba(0,0,0,0.6);
        }
        .stat-card .val { font-size: 2.4rem; font-weight: 900; color: #fff; margin-bottom: 4px; }
        .stat-card .lbl { color: #94a3b8; font-size: 0.95rem; font-weight: 500; }
        .stat-card .icon-wrap {
            width: 70px; height: 70px; border-radius: 20px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: var(--primary);
            transition: 0.4s;
        }

        /* Bento Grid */
        .bento-grid {
            display: grid; grid-template-columns: 2.2fr 1fr; gap: 30px;
        }
        .glass-panel {
            background: var(--glass-base); border: 1px solid var(--glass-border);
            border-radius: 40px; padding: 40px; backdrop-filter: blur(35px);
            box-shadow: 0 40px 100px -30px rgba(0,0,0,0.7);
        }
        .panel-head { font-size: 1.5rem; font-weight: 900; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .panel-head i { color: var(--secondary); filter: drop-shadow(0 0 8px var(--secondary)); }

        .tiles-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px;
        }
        .tile {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 24px; padding: 30px 15px; text-align: center;
            text-decoration: none !important; color: #cbd5e1; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tile:hover {
            background: rgba(255,255,255,0.08); border-color: var(--primary);
            transform: translateY(-8px); color: #fff;
        }

        /* Super Dock */
        .dock-wrapper {
            position: fixed; bottom: 35px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 10000;
        }
        .quantum-dock {
            background: rgba(5, 5, 10, 0.82); backdrop-filter: blur(25px) saturate(200%);
            border: 1px solid rgba(255,255,255,0.14); padding: 12px 15px; border-radius: 32px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.8);
        }
        .dock-btn {
            width: 58px; height: 58px; display: flex; align-items: center; justify-content: center;
            border-radius: 20px; color: #94a3b8; font-size: 1.6rem;
            transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none !important; position: relative;
        }
        .dock-btn:hover { transform: translateY(-20px) scale(1.3); color: #fff; background: rgba(255,255,255,0.12); }
        .dock-btn.active { color: var(--primary); background: rgba(139, 92, 246, 0.15); }

        /* Signature KillHosein */
        .signature-supreme {
            margin-top: 100px; padding-bottom: 80px; text-align: center;
            border-top: 1px solid rgba(255,255,255,0.06); padding-top: 50px;
        }
        .brand-link {
            text-decoration: none !important; display: inline-flex; flex-direction: column; align-items: center; gap: 20px;
            transition: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .brand-name {
            font-size: 52px; font-weight: 950;
            background: linear-gradient(90deg, #fff, #a78bfa, #22d3ee, #d946ef);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: 2px; filter: drop-shadow(0 0 25px rgba(139, 92, 246, 0.6));
            display: flex; align-items: center; gap: 25px;
        }
        .brand-tg {
            font-size: 65px; color: #229ED9;
            filter: drop-shadow(0 0 25px rgba(34, 158, 217, 0.8));
        }

        @keyframes pulse { 0% { transform: scale(0.9); opacity: 1; } 70% { transform: scale(1.3); opacity: 0; } 100% { transform: scale(0.9); opacity: 0; } }

        @media (max-width: 1200px) {
            .bento-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Video Background Container -->
    <div class="video-background">
        <video autoplay muted loop playsinline>
            <source src="https://assets.mixkit.co/videos/preview/mixkit-flying-through-stars-in-space-23214-large.mp4" type="video/mp4">
        </video>
        <div class="video-overlay"></div>
    </div>
    <div class="noise-layer"></div>

    <div class="main-wrapper">
        
        <!-- Header -->
        <header class="glass-header animate__animated animate__fadeInDown">
            <div class="header-title-block">
                <h1><?php echo $greet; ?>، مدیر ارشد</h1>
                <p>تمامی پارامترهای عملیاتی در وضعیت کوانتومی و پایدار قرار دارند.</p>
            </div>
            <div class="hidden lg:flex items-center gap-6">
                <div class="text-right">
                    <div class="text-white font-black text-lg"><?php echo $today; ?></div>
                    <div class="system-pill mt-2">
                        <div class="status-pulse"></div>
                        <span class="text-[11px] text-emerald-400 font-mono tracking-widest uppercase">Live Pulse Active</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="stats-container">
            <div class="stat-card animate__animated animate__zoomIn">
                <div>
                    <div class="val"><?php echo number_format($stats['sales']); ?></div>
                    <div class="lbl">درآمد ناخالص (تومان)</div>
                </div>
                <div class="icon-wrap"><i class="fa-solid fa-gem"></i></div>
            </div>
            <div class="stat-card animate__animated animate__zoomIn">
                <div>
                    <div class="val"><?php echo number_format($stats['orders']); ?></div>
                    <div class="lbl">تراکنش‌های موفق</div>
                </div>
                <div class="icon-wrap" style="color: var(--secondary);"><i class="fa-solid fa-bolt"></i></div>
            </div>
            <div class="stat-card animate__animated animate__zoomIn">
                <div>
                    <div class="val"><?php echo number_format($stats['users']); ?></div>
                    <div class="lbl">جمعیت کل کاربران</div>
                </div>
                <div class="icon-wrap" style="color: var(--accent);"><i class="fa-solid fa-dna"></i></div>
            </div>
        </section>

        <!-- Main Bento Grid -->
        <section class="bento-grid">
            <div class="flex flex-col gap-8">
                <!-- Analytics Chart -->
                <div class="glass-panel">
                    <div class="panel-head"><i class="fa-solid fa-chart-area"></i> آنالیز فروش و جریانات مالی</div>
                    <div style="height: 380px;"><canvas id="salesChart"></canvas></div>
                </div>

                <!-- Navigation Tiles -->
                <div class="glass-panel">
                    <div class="panel-head"><i class="fa-solid fa-compass"></i> هدایت‌گر عملیاتی سیستم</div>
                    <div class="tiles-grid">
                        <a href="users.php" class="tile"><i class="fa-solid fa-user-gear"></i><span>کاربران</span></a>
                        <a href="invoice.php" class="tile"><i class="fa-solid fa-file-shield"></i><span>سفارشات</span></a>
                        <a href="product.php" class="tile"><i class="fa-solid fa-box-tissue"></i><span>محصولات</span></a>
                        <a href="service.php" class="tile"><i class="fa-solid fa-microchip"></i><span>سرویس‌ها</span></a>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-8">
                <!-- Status Doughnut -->
                <div class="glass-panel text-center">
                    <div class="panel-head justify-center"><i class="fa-solid fa-circle-nodes"></i> یکپارچگی سرویس‌ها</div>
                    <div style="position: relative; height: 260px;">
                        <canvas id="statusChart"></canvas>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none;">
                            <div style="font-size: 2.8rem; font-weight: 950;"><?php echo $totalStatus; ?></div>
                            <div style="font-size: 0.75rem; color: #94a3b8; letter-spacing: 3px; font-weight: 700;">NODES</div>
                        </div>
                    </div>
                </div>

                <!-- User Trend -->
                <div class="glass-panel">
                    <div class="panel-head"><i class="fa-solid fa-user-plus"></i> رشد اکوسیستم</div>
                    <div style="height: 220px;"><canvas id="usersChart"></canvas></div>
                </div>
            </div>
        </section>

        <!-- Signature -->
        <div class="signature-supreme">
            <a href="https://t.me/KillHosein" class="brand-link" target="_blank">
                <span class="brand-name">
                    <i class="fa-brands fa-telegram brand-tg"></i> KillHosein
                </span>
            </a>
        </div>
    </div>

    <!-- The Super Dock -->
    <div class="dock-wrapper">
        <nav class="quantum-dock">
            <a href="index.php" class="dock-btn active" data-label="خانه"><i class="fa-solid fa-house-chimney-window"></i></a>
            <a href="users.php" class="dock-btn" data-label="کاربران"><i class="fa-solid fa-user-astronaut"></i></a>
            <a href="invoice.php" class="dock-btn" data-label="فاکتورها"><i class="fa-solid fa-receipt"></i></a>
            <a href="product.php" class="dock-btn" data-label="انبار"><i class="fa-solid fa-box-open"></i></a>
            <a href="login.php" class="dock-btn" data-label="خروج" style="color: #ef4444;"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = 'rgba(255,255,255,0.5)';
        Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.04)';

        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 380);
        gradS.addColorStop(0, 'rgba(139, 92, 246, 0.5)');
        gradS.addColorStop(1, 'rgba(139, 92, 246, 0)');

        new Chart(ctxS, {
            type: 'line',
            data: {
                labels: dSales.labels,
                datasets: [{
                    data: dSales.values,
                    borderColor: '#a78bfa',
                    borderWidth: 4,
                    backgroundColor: gradS,
                    fill: true,
                    tension: 0.45,
                    pointRadius: 0,
                    pointHoverRadius: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { display: false }, grid: { display: false } }, y: { display: false } }
            }
        });

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels,
                datasets: [{
                    data: dPie.values,
                    backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#d946ef', '#f97316', '#334155'],
                    borderWidth: 0,
                    hoverOffset: 20,
                    cutout: '84%',
                    spacing: 6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels,
                datasets: [{
                    data: dUsers.values,
                    backgroundColor: '#22d3ee',
                    borderRadius: 12,
                    barThickness: 12
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