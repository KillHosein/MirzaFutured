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

// --- تنظیمات پیش‌فرض (فیلترها حذف شدند اما مقادیر پیش‌فرض برای کوئری نیاز است) ---
$dateYesterday = time() - 86400;
// مقادیر پیش‌فرض برای نمایش کل داده‌ها یا بازه خاص (در صورت نیاز می‌توانید در کد تغییر دهید)
$filterFrom = null; 
$filterTo = null;
$filterStatus = []; 

// 1. گارد امنیتی
if ($isConnected) {
    if (!isset($_SESSION["user"])) { header('Location: login.php'); exit; }
    try {
        $stmt = $pdo->prepare("SELECT id, username FROM admin WHERE username=:username LIMIT 1");
        $stmt->execute(['username' => $_SESSION["user"]]);
        if (!$stmt->fetch()) { session_destroy(); header('Location: login.php'); exit; }
    } catch (PDOException $e) { error_log("Security Error: " . $e->getMessage()); }
}

// 2. ساختار کوئری (ساده شده بدون ورودی‌های GET)
$whereClauses = ["name_product != 'سرویس تست'"];
$queryParams = [];

// نمایش همه وضعیت‌های فعال و مهم به صورت پیش‌فرض
$whereClauses[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";

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

        // Chart: Sales Trend (Last 30 transactions for better view without filter)
        if ($stats['orders'] > 0) {
            $stmt = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $whereSql AND status != 'unpaid' ORDER BY time_sell DESC LIMIT 50");
            $stmt->execute($queryParams);
            $rawSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rawSales as $row) {
                if (!is_numeric($row['time_sell'])) continue;
                $d = date('Y/m/d', (int)$row['time_sell']);
                if (!isset($grouped[$d])) $grouped[$d] = 0;
                $grouped[$d] += (int)$row['price_product'];
            }
            ksort($grouped); // Sort by date
            $chartData['sales'] = $grouped;
        }

        // Chart: Status Distribution
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM invoice WHERE $whereSql GROUP BY status");
        $stmt->execute($queryParams);
        $chartData['status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chart: User Growth (Last 14 days default)
        $uStart = time() - (14 * 86400);
        $uEnd = time();
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

// آماده‌سازی داده‌ها برای ویو
$salesLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['sales']));
$salesValues = array_values($chartData['sales']);
$userLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['growth']));
$userValues = array_values($chartData['growth']);

// پالت رنگی لوکس و جدید
$statusConfig = [
    'unpaid'       => ['label' => 'در انتظار پرداخت', 'color' => '#F59E0B'], // Amber
    'active'       => ['label' => 'فعال',             'color' => '#10B981'], // Emerald
    'disabledn'    => ['label' => 'غیرفعال',          'color' => '#64748B'], // Slate
    'end_of_time'  => ['label' => 'انقضای زمانی',     'color' => '#EF4444'], // Red
    'end_of_volume'=> ['label' => 'اتمام حجم',        'color' => '#3B82F6'], // Blue
    'sendedwarn'   => ['label' => 'هشدار',            'color' => '#8B5CF6'], // Purple
    'send_on_hold' => ['label' => 'در صف',            'color' => '#F97316'], // Orange
    'removebyuser' => ['label' => 'حذف شده',          'color' => '#475569']
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
    <title>پنل مدیریت سلطنتی | Luxury Admin</title>
    
    <!-- Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            /* --- Royal Obsidian Palette --- */
            --bg-body: #050505;
            --bg-sidebar: rgba(10, 10, 10, 0.9);
            
            /* Gold Accents */
            --gold-primary: #C5A059;
            --gold-light: #E6C786;
            --gold-dim: #8A6E3E;
            --gold-gradient: linear-gradient(135deg, #C5A059 0%, #E6C786 50%, #C5A059 100%);
            
            --text-main: #FFFFFF;
            --text-muted: #888888;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.06);
            --border-gold: 1px solid rgba(197, 160, 89, 0.3);
            
            --shadow-card: 0 15px 40px rgba(0,0,0,0.6);
            --glow-gold: 0 0 25px rgba(197, 160, 89, 0.15);
            
            --radius-xl: 32px;
            --radius-md: 20px;
            
            --sidebar-w: 90px;
            --sidebar-hover: 260px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: radial-gradient(circle at 50% 0%, rgba(197, 160, 89, 0.05) 0%, transparent 60%);
        }

        /* --- Sidebar --- */
        .sidebar {
            position: fixed; top: 25px; right: 25px; bottom: 25px;
            width: var(--sidebar-w);
            background: var(--bg-sidebar);
            backdrop-filter: blur(30px);
            border: var(--border-glass);
            border-radius: var(--radius-xl);
            display: flex; flex-direction: column; align-items: center;
            padding: 40px 0; z-index: 1000;
            transition: width 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
            overflow: hidden;
            box-shadow: -10px 0 40px rgba(0,0,0,0.5);
        }
        .sidebar:hover { width: var(--sidebar-hover); align-items: stretch; padding: 40px 20px; }

        .brand {
            font-size: 2rem; color: var(--gold-light); margin-bottom: 50px;
            display: flex; align-items: center; justify-content: center;
            filter: drop-shadow(0 0 10px rgba(197, 160, 89, 0.4));
            transition: 0.3s; width: 100%; white-space: nowrap;
        }
        .sidebar:hover .brand { justify-content: flex-start; padding-right: 10px; }
        .brand span { opacity: 0; font-size: 1.2rem; margin-right: 15px; font-weight: 700; transition: 0.3s; }
        .sidebar:hover .brand span { opacity: 1; }

        .nav-menu { display: flex; flex-direction: column; gap: 10px; width: 100%; padding: 0 10px; }
        .nav-item {
            height: 55px; border-radius: 16px;
            display: flex; align-items: center; padding: 0 15px;
            color: var(--text-muted); text-decoration: none;
            transition: 0.4s; position: relative; overflow: hidden;
        }
        .nav-item i { font-size: 1.3rem; min-width: 30px; text-align: center; transition: 0.3s; }
        .nav-text { opacity: 0; white-space: nowrap; margin-right: 15px; font-weight: 500; transition: 0.3s; transform: translateX(10px); }
        
        .sidebar:hover .nav-text { opacity: 1; transform: translateX(0); transition-delay: 0.1s; }
        
        .nav-item:hover { color: #fff; background: rgba(255,255,255,0.03); }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(197, 160, 89, 0.15), transparent);
            border-right: 3px solid var(--gold-primary);
            color: var(--gold-light);
        }
        .nav-item.active i { color: var(--gold-primary); filter: drop-shadow(0 0 8px var(--gold-primary)); }

        /* --- Main Content --- */
        .wrapper {
            margin-right: calc(var(--sidebar-w) + 30px);
            padding: 50px 0 50px 40px;
            transition: margin-right 0.5s;
        }

        /* --- Header --- */
        .header-section {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 60px; padding-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .title-area h1 { 
            font-size: 3.2rem; font-weight: 900; color: #fff; margin: 0; 
            letter-spacing: -1px;
        }
        .title-area p { color: var(--text-muted); font-size: 1.1rem; margin-top: 5px; }
        
        .large-date {
            font-size: 2.5rem; font-weight: 800;
            background: var(--gold-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 30px rgba(197, 160, 89, 0.2);
            text-align: left; line-height: 1;
        }
        .large-date span { display: block; font-size: 1rem; color: var(--text-muted); -webkit-text-fill-color: var(--text-muted); font-weight: 400; margin-top: 5px; text-align: right; }

        /* --- Cards (Bento Grid) --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 50px;
        }
        .stat-card {
            background: #080808;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            padding: 30px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 200px; transition: 0.4s;
        }
        .stat-card::after {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.04), transparent 50%);
            opacity: 0; transition: 0.4s;
        }
        .stat-card:hover {
            transform: translateY(-8px);
            border-color: rgba(197, 160, 89, 0.3);
            box-shadow: var(--shadow-card);
        }
        .stat-card:hover::after { opacity: 1; }

        .stat-icon { font-size: 2rem; color: #333; margin-bottom: 20px; transition: 0.4s; }
        .stat-card:hover .stat-icon { color: var(--gold-light); transform: scale(1.1); text-shadow: 0 0 20px var(--gold-primary); }
        
        .stat-value { font-size: 2.8rem; font-weight: 800; color: #fff; margin-bottom: 5px; letter-spacing: -1px; }
        .stat-label { font-size: 0.95rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }

        .card-gold { grid-column: span 2; background: linear-gradient(170deg, #111 0%, #000 100%); border: 1px solid rgba(197, 160, 89, 0.15); }
        .card-gold .stat-value { background: var(--gold-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* --- Quick Actions --- */
        .section-title { font-size: 1.5rem; color: #fff; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #222; }

        .actions-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; margin-bottom: 60px;
        }
        .action-btn {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 25px 15px; text-align: center;
            transition: 0.3s; cursor: pointer; text-decoration: none;
        }
        .action-btn:hover {
            background: rgba(197, 160, 89, 0.08);
            border-color: var(--gold-primary);
            transform: translateY(-5px);
        }
        .action-icon { font-size: 1.8rem; color: #555; margin-bottom: 15px; transition: 0.3s; }
        .action-btn:hover .action-icon { color: #fff; transform: scale(1.2); }
        .action-text { font-size: 1rem; font-weight: 600; color: #ccc; transition: 0.3s; }
        .action-btn:hover .action-text { color: var(--gold-light); }

        /* --- Charts --- */
        .chart-section { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .chart-box {
            background: #050505; border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl); padding: 35px;
        }
        .chart-top { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .chart-head { font-size: 1.3rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }

        /* --- Responsive --- */
        @media (max-width: 1400px) { .stats-grid { grid-template-columns: 1fr 1fr; } .chart-section { grid-template-columns: 1fr; } }
        @media (max-width: 1024px) { 
            .sidebar { width: 0; padding: 0; border: none; }
            .sidebar.active { width: 260px; padding: 40px 0; border: 1px solid var(--border-subtle); }
            .wrapper { margin-right: 0; padding: 25px; }
            .mobile-menu { display: flex; position: fixed; bottom: 30px; left: 30px; width: 60px; height: 60px; background: var(--gold-gradient); border-radius: 50%; align-items: center; justify-content: center; z-index: 2000; box-shadow: 0 10px 30px rgba(0,0,0,0.5); font-size: 1.5rem; color: #000; }
        }
        @media (min-width: 1025px) { .mobile-menu { display: none; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <i class="fa-solid fa-crown"></i>
            <span>ADMIN</span>
        </div>
        
        <nav class="nav-menu">
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-house"></i> <span class="nav-text">داشبورد</span>
            </a>
            <a href="invoice.php" class="nav-item">
                <i class="fa-solid fa-file-invoice"></i> <span class="nav-text">سفارشات</span>
            </a>
            <a href="user.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span class="nav-text">کاربران</span>
            </a>
            <a href="product.php" class="nav-item">
                <i class="fa-solid fa-box"></i> <span class="nav-text">محصولات</span>
            </a>
            <a href="server_status.php" class="nav-item">
                <i class="fa-solid fa-shield-halved"></i> <span class="nav-text">پنل ادمین</span>
            </a>
            <div style="flex: 1;"></div>
            <a href="logout.php" class="nav-item" style="color: #EF4444;">
                <i class="fa-solid fa-power-off"></i> <span class="nav-text">خروج</span>
            </a>
        </nav>
    </aside>

    <div class="mobile-menu" onclick="document.getElementById('sidebar').classList.toggle('active')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Content -->
    <div class="wrapper">
        
        <!-- Header -->
        <header class="header-section">
            <div class="title-area">
                <h1><?php echo $greet; ?>، سرورم</h1>
                <p>خوش‌آمدید به پنل مدیریت یکپارچه</p>
            </div>
            <div class="large-date">
                <?php echo $today; ?>
                <span>تاریخ امروز</span>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="stats-grid">
            <!-- Sales -->
            <div class="stat-card card-gold">
                <i class="fa-solid fa-sack-dollar stat-icon"></i>
                <div>
                    <div class="stat-value"><?php echo number_format($stats['sales']); ?></div>
                    <div class="stat-label">مجموع درآمد (تومان)</div>
                </div>
            </div>

            <!-- Orders -->
            <div class="stat-card">
                <i class="fa-solid fa-file-signature stat-icon"></i>
                <div>
                    <div class="stat-value"><?php echo number_format($stats['orders']); ?></div>
                    <div class="stat-label">سفارشات موفق</div>
                </div>
            </div>

            <!-- Users -->
            <div class="stat-card">
                <i class="fa-solid fa-users-viewfinder stat-icon"></i>
                <div>
                    <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
                    <div class="stat-label">کاربران فعال</div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <div class="section-title">دسترسی سریع</div>
        <section class="actions-grid">
            <a href="invoice.php" class="action-btn">
                <i class="fa-solid fa-list-ul action-icon"></i>
                <div class="action-text">لیست سفارشات</div>
            </a>
            <a href="user.php" class="action-btn">
                <i class="fa-solid fa-user-shield action-icon"></i>
                <div class="action-text">مدیریت کاربران</div>
            </a>
            <a href="inbound.php" class="action-btn">
                <i class="fa-solid fa-sliders action-icon"></i>
                <div class="action-text">کانفیگ‌ها</div>
            </a>
            <a href="payment.php" class="action-btn">
                <i class="fa-solid fa-wallet action-icon"></i>
                <div class="action-text">امور مالی</div>
            </a>
            <a href="server_status.php" class="action-btn">
                <i class="fa-solid fa-server action-icon"></i>
                <div class="action-text">پنل ادمین</div>
            </a>
            <a href="cancelService.php" class="action-btn" style="border-color: rgba(239,68,68,0.3);">
                <i class="fa-solid fa-ban action-icon" style="color: #EF4444;"></i>
                <div class="action-text" style="color: #EF4444;">مسدودی</div>
            </a>
        </section>

        <!-- Charts -->
        <section class="chart-section">
            <div class="chart-box">
                <div class="chart-top">
                    <div class="chart-head">
                        <i class="fa-solid fa-chart-line" style="color: var(--gold-primary);"></i>
                        تحلیل فروش
                    </div>
                </div>
                <div style="height: 400px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-top">
                        <div class="chart-head"><i class="fa-solid fa-chart-pie"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="height: 250px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-top">
                        <div class="chart-head"><i class="fa-solid fa-arrow-trend-up"></i> رشد کاربر</div>
                    </div>
                    <div style="height: 150px;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // --- Improved Chart Config ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.font.size = 14;
        Chart.defaults.color = '#777';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.04)';
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?>, colors: <?php echo json_encode($pieColors); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Luxury Gold Gradient)
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gS = ctxS.createLinearGradient(0, 0, 0, 400);
        gS.addColorStop(0, '#C5A059');
        gS.addColorStop(1, 'rgba(197, 160, 89, 0.02)');

        new Chart(ctxS, {
            type: 'line',
            data: {
                labels: dSales.labels,
                datasets: [{
                    label: 'فروش', data: dSales.values,
                    borderColor: '#C5A059', backgroundColor: gS,
                    borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#000', pointBorderColor: '#C5A059',
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

        // 2. Status Chart (Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels, datasets: [{
                    data: dPie.values, backgroundColor: dPie.colors,
                    borderWidth: 0, hoverOffset: 15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '85%',
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true, color: '#999' } } }
            }
        });

        // 3. Users Chart (Bar)
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels, datasets: [{
                    label: 'کاربر', data: dUsers.values,
                    backgroundColor: '#10B981', borderRadius: 6, barThickness: 15
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