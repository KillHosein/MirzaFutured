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

// 2. ساختار کوئری
$whereClauses = ["name_product != 'سرویس تست'"];
$queryParams = [];
$whereClauses[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";
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

        // Chart: Sales (Limited to recent for clarity)
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

    <style>
        :root {
            /* --- The Palette of Power --- */
            --bg-void: #030303;
            --bg-panel: #0a0a0a;
            --bg-card: rgba(18, 18, 18, 0.65);
            
            /* Accents */
            --gold-main: #D4AF37;
            --gold-light: #F9E79F;
            --gold-dark: #8E7026;
            --gold-grad: linear-gradient(135deg, #D4AF37 0%, #F2D06B 100%);
            
            /* Text */
            --text-white: #FFFFFF;
            --text-muted: #888888;
            --text-dim: #444444;
            
            /* UI Elements */
            --border-glass: 1px solid rgba(255, 255, 255, 0.06);
            --border-gold: 1px solid rgba(212, 175, 55, 0.25);
            
            --shadow-float: 0 20px 50px -10px rgba(0,0,0,0.8);
            --glow-gold: 0 0 30px rgba(212, 175, 55, 0.1);
            
            --radius-box: 24px;
            --sidebar-w: 90px;
            --sidebar-w-open: 260px;
        }

        /* --- Reset & Base --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-void);
            color: var(--text-white);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            /* Subtle Noise Texture + Gradient */
            background-image: 
                url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E"),
                radial-gradient(circle at 50% -20%, rgba(212, 175, 55, 0.08) 0%, transparent 60%);
            background-attachment: fixed;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
        h1, h2, h3, h4 { margin: 0; font-weight: 800; letter-spacing: -0.5px; }

        /* --- Sidebar Navigation --- */
        .sidebar {
            position: fixed; top: 30px; right: 30px; bottom: 30px;
            width: var(--sidebar-w);
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: var(--border-glass);
            border-radius: 30px;
            z-index: 1000;
            display: flex; flex-direction: column; align-items: center;
            padding: 40px 0;
            transition: width 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            box-shadow: -15px 0 40px rgba(0,0,0,0.6);
        }
        .sidebar:hover { width: var(--sidebar-w-open); align-items: stretch; padding: 40px 20px; }

        .brand-box {
            margin-bottom: 60px; width: 100%; display: flex; align-items: center; justify-content: center;
            transition: 0.4s; padding-left: 5px;
        }
        .sidebar:hover .brand-box { justify-content: flex-start; padding-right: 15px; }
        
        .brand-icon {
            font-size: 2rem; color: var(--gold-main); 
            filter: drop-shadow(0 0 15px rgba(212, 175, 55, 0.4));
            flex-shrink: 0;
        }
        .brand-text {
            font-size: 1.2rem; font-weight: 900; color: #fff;
            margin-right: 15px; opacity: 0; transition: 0.3s;
            white-space: nowrap; transform: translateX(10px);
        }
        .sidebar:hover .brand-text { opacity: 1; transform: translateX(0); transition-delay: 0.1s; }

        .nav-list { display: flex; flex-direction: column; gap: 12px; width: 100%; padding: 0 12px; }
        .nav-link {
            height: 56px; border-radius: 18px;
            display: flex; align-items: center; padding: 0 16px;
            color: var(--text-muted); position: relative;
            transition: all 0.3s;
        }
        .nav-link i { font-size: 1.4rem; min-width: 28px; text-align: center; transition: 0.3s; }
        .link-text { margin-right: 18px; font-weight: 500; font-size: 1rem; opacity: 0; white-space: nowrap; transition: 0.3s; }
        
        .sidebar:hover .link-text { opacity: 1; }
        
        .nav-link:hover { color: #fff; background: rgba(255,255,255,0.04); }
        .nav-link.active {
            background: linear-gradient(90deg, rgba(212, 175, 55, 0.12), transparent);
            color: var(--gold-light);
            border-right: 3px solid var(--gold-main);
        }
        .nav-link.active i { color: var(--gold-main); filter: drop-shadow(0 0 8px rgba(212, 175, 55, 0.6)); }

        /* --- Main Content Area --- */
        .workspace {
            margin-right: calc(var(--sidebar-w) + 40px);
            padding: 60px 0 60px 50px;
            transition: margin-right 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* --- Header --- */
        .header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 80px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            padding-bottom: 30px;
        }
        .greeting h1 {
            font-size: 4rem; font-weight: 900; line-height: 1;
            background: linear-gradient(to bottom right, #fff, #999);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        .greeting p { font-size: 1.2rem; color: var(--text-muted); font-weight: 300; letter-spacing: 0.5px; }
        
        .date-display {
            text-align: left;
        }
        .date-val {
            font-size: 2.2rem; font-weight: 800; color: var(--gold-main);
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.2);
            font-family: 'Vazirmatn', sans-serif;
        }
        .date-lbl { font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 2px; margin-top: 5px; display: block; text-align: right; }

        /* --- Stats Bento Grid --- */
        .bento-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); 
            gap: 25px; margin-bottom: 60px;
        }
        .bento-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-box);
            padding: 35px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 240px; transition: all 0.4s ease;
        }
        
        /* Subtle spotlight effect on hover */
        .bento-card::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.05), transparent 70%);
            opacity: 0; transition: 0.4s;
        }
        .bento-card:hover {
            transform: translateY(-10px);
            border-color: rgba(212, 175, 55, 0.3);
            box-shadow: var(--shadow-float);
        }
        .bento-card:hover::before { opacity: 1; }

        .b-icon { font-size: 2.5rem; color: #333; transition: 0.4s; margin-bottom: 20px; }
        .bento-card:hover .b-icon { color: var(--gold-light); transform: scale(1.1); filter: drop-shadow(0 0 15px var(--gold-main)); }
        
        .b-value { 
            font-size: 3.5rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 5px; 
            font-feature-settings: "tnum"; font-variant-numeric: tabular-nums;
        }
        .b-label { font-size: 1rem; color: var(--text-muted); font-weight: 400; letter-spacing: 1px; }

        /* Special Card Styling */
        .card-prime {
            grid-column: span 2;
            background: linear-gradient(160deg, #111 0%, #050505 100%);
            border: 1px solid rgba(212, 175, 55, 0.15);
        }
        .card-prime .b-value {
            background: var(--gold-grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* --- Action Deck --- */
        .section-label { 
            font-size: 1.4rem; color: #fff; margin-bottom: 30px; font-weight: 700; 
            display: flex; align-items: center; gap: 15px;
        }
        .section-label::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.1); }

        .deck-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
            gap: 20px; margin-bottom: 80px;
        }
        .deck-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-glass);
            border-radius: 20px; padding: 30px 20px;
            display: flex; flex-direction: column; align-items: center; gap: 15px;
            cursor: pointer; transition: 0.3s; text-decoration: none;
        }
        .deck-item:hover {
            background: rgba(212, 175, 55, 0.05);
            border-color: var(--gold-main);
            transform: translateY(-5px);
        }
        .deck-icon { font-size: 2rem; color: var(--text-dim); transition: 0.3s; }
        .deck-item:hover .deck-icon { color: #fff; transform: scale(1.2); text-shadow: 0 0 20px var(--gold-main); }
        
        .deck-title { font-size: 1.1rem; font-weight: 600; color: var(--text-muted); transition: 0.3s; }
        .deck-item:hover .deck-title { color: var(--gold-light); }

        /* --- Charts Area --- */
        .charts-container {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px;
        }
        .chart-pod {
            background: #080808; border: 1px solid var(--border-glass);
            border-radius: var(--radius-box); padding: 40px;
            position: relative;
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .chart-title { font-size: 1.6rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 15px; }
        .chart-title i { color: var(--gold-main); font-size: 1.4rem; }

        /* --- Responsive --- */
        @media (max-width: 1600px) { .bento-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 1200px) { .charts-container { grid-template-columns: 1fr; } }
        @media (max-width: 1024px) {
            .sidebar { width: 0; padding: 0; border: none; }
            .sidebar.active { width: 280px; padding: 40px 0; border: 1px solid var(--border-glass); }
            .wrapper { margin-right: 0; padding: 30px; }
            .greeting h1 { font-size: 3rem; }
            .bento-grid { grid-template-columns: 1fr; }
            .card-prime { grid-column: auto; }
            .mobile-btn { display: flex; position: fixed; bottom: 30px; left: 30px; width: 65px; height: 65px; background: var(--gold-grad); border-radius: 50%; align-items: center; justify-content: center; z-index: 2000; box-shadow: 0 10px 40px rgba(0,0,0,0.5); font-size: 1.8rem; color: #000; }
        }
        @media (min-width: 1025px) { .mobile-btn { display: none; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-box">
            <i class="fa-solid fa-crown brand-icon"></i>
            <span class="brand-text">ROYAL ADMIN</span>
        </div>
        
        <nav class="nav-list">
            <a href="index.php" class="nav-link active">
                <i class="fa-solid fa-house-chimney"></i> <span class="link-text">داشبورد</span>
            </a>
            <a href="invoice.php" class="nav-link">
                <i class="fa-solid fa-file-invoice-dollar"></i> <span class="link-text">سفارشات</span>
            </a>
            <a href="user.php" class="nav-link">
                <i class="fa-solid fa-users-line"></i> <span class="link-text">کاربران</span>
            </a>
            <a href="product.php" class="nav-link">
                <i class="fa-solid fa-box-open"></i> <span class="link-text">محصولات</span>
            </a>
            <a href="server_status.php" class="nav-link">
                <i class="fa-solid fa-user-shield"></i> <span class="link-text">ادمین</span>
            </a>
            <div style="flex: 1;"></div>
            <a href="logout.php" class="nav-link" style="color: #EF4444;">
                <i class="fa-solid fa-power-off"></i> <span class="link-text">خروج</span>
            </a>
        </nav>
    </aside>

    <div class="mobile-btn" onclick="document.getElementById('sidebar').classList.toggle('active')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Content -->
    <main class="wrapper">
        
        <!-- Header -->
        <header class="header">
            <div class="greeting">
                <h1><?php echo $greet; ?></h1>
                <p>به پنل مدیریت اختصاصی خوش آمدید</p>
            </div>
            <div class="date-display">
                <div class="date-val"><?php echo $today; ?></div>
                <span class="date-lbl">تاریخ امروز</span>
            </div>
        </header>

        <!-- Stats Bento Grid -->
        <section class="bento-grid">
            <!-- Sales -->
            <div class="bento-card card-prime">
                <i class="fa-solid fa-sack-dollar b-icon"></i>
                <div>
                    <div class="b-value"><?php echo number_format($stats['sales']); ?></div>
                    <div class="b-label" style="color: var(--gold-light);">درآمد کل (تومان)</div>
                </div>
            </div>

            <!-- Orders -->
            <div class="bento-card">
                <i class="fa-solid fa-file-circle-check b-icon"></i>
                <div>
                    <div class="b-value"><?php echo number_format($stats['orders']); ?></div>
                    <div class="b-label">سفارش موفق</div>
                </div>
            </div>

            <!-- Users -->
            <div class="bento-card">
                <i class="fa-solid fa-users-viewfinder b-icon"></i>
                <div>
                    <div class="b-value"><?php echo number_format($stats['users']); ?></div>
                    <div class="b-label">کاربران فعال</div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <div class="section-label">دسترسی سریع</div>
        <section class="deck-grid">
            <a href="invoice.php" class="deck-item">
                <i class="fa-solid fa-list-check deck-icon"></i>
                <span class="deck-title">لیست سفارشات</span>
            </a>
            <a href="user.php" class="deck-item">
                <i class="fa-solid fa-user-group deck-icon"></i>
                <span class="deck-title">مدیریت کاربران</span>
            </a>
            <a href="inbound.php" class="deck-item">
                <i class="fa-solid fa-sliders deck-icon"></i>
                <span class="deck-title">کانفیگ‌ها</span>
            </a>
            <a href="payment.php" class="deck-item">
                <i class="fa-solid fa-wallet deck-icon"></i>
                <span class="deck-title">امور مالی</span>
            </a>
            <a href="server_status.php" class="deck-item">
                <i class="fa-solid fa-user-secret deck-icon"></i>
                <span class="deck-title">پنل ادمین</span>
            </a>
            <a href="cancelService.php" class="deck-item" style="border-color: rgba(239,68,68,0.3);">
                <i class="fa-solid fa-ban deck-icon" style="color: #EF4444;"></i>
                <span class="deck-title" style="color: #EF4444;">مسدودسازی</span>
            </a>
        </section>

        <!-- Charts -->
        <section class="charts-container">
            <div class="chart-pod">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fa-solid fa-chart-line"></i>
                        تحلیل فروش
                    </div>
                </div>
                <div style="height: 450px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="chart-pod" style="flex: 1;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-chart-pie"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="height: 250px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-pod" style="flex: 1;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-arrow-trend-up"></i> رشد کاربر</div>
                    </div>
                    <div style="height: 200px;">
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
        // --- Premium Chart Config ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.font.size = 14;
        Chart.defaults.color = '#666';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.03)';
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?>, colors: <?php echo json_encode($pieColors); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // 1. Sales Chart (Luxurious Gold Area)
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gS = ctxS.createLinearGradient(0, 0, 0, 500);
        gS.addColorStop(0, 'rgba(212, 175, 55, 0.4)');
        gS.addColorStop(1, 'rgba(212, 175, 55, 0)');

        new Chart(ctxS, {
            type: 'line',
            data: {
                labels: dSales.labels,
                datasets: [{
                    label: 'فروش', data: dSales.values,
                    borderColor: '#D4AF37', backgroundColor: gS,
                    borderWidth: 2, pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#fff',
                    fill: true, tension: 0.4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111', titleColor: '#D4AF37', padding: 15, cornerRadius: 8 } },
                scales: { 
                    x: { grid: { display: false } }, 
                    y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(255,255,255,0.02)' } } 
                }
            }
        });

        // 2. Status Chart (Minimal Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels, datasets: [{
                    data: dPie.values, backgroundColor: dPie.colors,
                    borderWidth: 0, hoverOffset: 15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '80%',
                plugins: { legend: { position: 'right', labels: { boxWidth: 8, usePointStyle: true, color: '#888', font: { size: 12 } } } }
            }
        });

        // 3. Users Chart (Gradient Bar)
        const ctxU = document.getElementById('usersChart').getContext('2d');
        const gU = ctxU.createLinearGradient(0, 0, 0, 300);
        gU.addColorStop(0, '#10B981');
        gU.addColorStop(1, '#059669');

        new Chart(ctxU, {
            type: 'bar',
            data: {
                labels: dUsers.labels, datasets: [{
                    label: 'کاربر', data: dUsers.values,
                    backgroundColor: gU, borderRadius: 4, barThickness: 12
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