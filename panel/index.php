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

// --- تنظیمات فیلتر و زمان ---
$dateYesterday = time() - 86400;
$filterFrom = $_GET['from'] ?? null;
$filterTo = $_GET['to'] ?? null;
$filterStatus = $_GET['status'] ?? [];
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

// 2. ساختار کوئری (Dynamic Query Builder)
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
    foreach ($filterStatus as $i => $s) {
        $ph = ":st_$i";
        $placeholders[] = $ph;
        $queryParams[$ph] = $s;
    }
    $whereClauses[] = "status IN (" . implode(', ', $placeholders) . ")";
} else {
    $whereClauses[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";
}

$whereSql = implode(' AND ', $whereClauses);

// 3. استخراج داده‌های آماری (Data Mining)
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

        // Chart: Sales Trend
        if ($stats['orders'] > 0) {
            $stmt = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $whereSql AND status != 'unpaid' ORDER BY time_sell DESC");
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

        // Chart: Status Distribution
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM invoice WHERE $whereSql GROUP BY status");
        $stmt->execute($queryParams);
        $chartData['status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chart: User Growth
        $uStart = ($filterFrom) ? strtotime($filterFrom) : (time() - (13 * 86400));
        $uEnd = ($filterTo) ? strtotime($filterTo . ' 23:59:59') : time();
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

// آماده‌سازی برای فرانت‌اند
$salesLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['sales']));
$salesValues = array_values($chartData['sales']);
$userLabels = array_map(function($d) { return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($chartData['growth']));
$userValues = array_values($chartData['growth']);

// پالت رنگی لوکس
$statusConfig = [
    'unpaid'       => ['label' => 'در انتظار پرداخت', 'color' => '#D4AF37'], // Gold
    'active'       => ['label' => 'فعال',             'color' => '#10B981'], // Emerald
    'disabledn'    => ['label' => 'غیرفعال',          'color' => '#64748B'], // Slate
    'end_of_time'  => ['label' => 'انقضای زمانی',     'color' => '#EF4444'], // Red
    'end_of_volume'=> ['label' => 'اتمام حجم',        'color' => '#3B82F6'], // Blue
    'sendedwarn'   => ['label' => 'هشدار',            'color' => '#A855F7'], // Purple
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
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* --- Luxury Onyx & Gold Palette --- */
            --bg-void: #000000;
            --bg-panel: #050505;
            --glass-black: rgba(10, 10, 10, 0.85);
            
            --gold-primary: #D4AF37;
            --gold-light: #F4C430;
            --gold-dark: #AA6C39;
            --gold-gradient: linear-gradient(135deg, #BF953F, #FCF6BA, #B38728, #FBF5B7, #AA771C);
            
            --text-main: #FFFFFF;
            --text-muted: #888888;
            
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-gold: rgba(212, 175, 55, 0.3);
            
            --shadow-lux: 0 20px 50px rgba(0,0,0,0.7);
            --glow-gold: 0 0 20px rgba(212, 175, 55, 0.15);
            
            --radius-xl: 30px;
            --radius-md: 18px;
            
            --sidebar-w: 100px;
            --sidebar-w-hover: 280px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-void);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            /* Subtle Grain Texture */
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.05'/%3E%3C/svg%3E");
        }
        
        /* Gold Text Gradient Class */
        .text-gold {
            background: var(--gold-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
        }

        /* --- Sidebar (Floating Dock Style) --- */
        .sidebar {
            position: fixed; top: 20px; right: 20px; bottom: 20px;
            width: var(--sidebar-w);
            background: var(--glass-black);
            backdrop-filter: blur(40px);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            display: flex; flex-direction: column; align-items: center;
            padding: 40px 0; z-index: 1000;
            transition: width 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            overflow: hidden;
            box-shadow: var(--shadow-lux);
        }
        .sidebar:hover { width: var(--sidebar-w-hover); align-items: stretch; padding: 40px 20px; }

        .brand-icon {
            font-size: 2.5rem; margin-bottom: 50px;
            background: var(--gold-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.3));
            transition: 0.3s; flex-shrink: 0; text-align: center; width: 100%;
        }
        .sidebar:hover .brand-icon { transform: scale(0.9); margin-bottom: 30px; text-align: right; }

        .nav-menu { display: flex; flex-direction: column; gap: 12px; width: 100%; padding: 0 10px; }
        .nav-item {
            display: flex; align-items: center; height: 56px;
            padding: 0 16px; border-radius: 16px;
            color: var(--text-muted); text-decoration: none;
            transition: 0.4s; white-space: nowrap; position: relative;
        }
        .nav-item i { font-size: 1.4rem; min-width: 30px; text-align: center; transition: 0.3s; }
        .nav-text { opacity: 0; transform: translateX(20px); transition: 0.3s; font-weight: 500; margin-right: 15px; }
        
        .sidebar:hover .nav-text { opacity: 1; transform: translateX(0); transition-delay: 0.1s; }
        .nav-item:hover { color: #fff; background: rgba(255,255,255,0.03); }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(212, 175, 55, 0.15), transparent);
            border-right: 3px solid var(--gold-primary);
            color: var(--gold-light);
        }
        .nav-item.active i { color: var(--gold-primary); text-shadow: 0 0 15px var(--gold-primary); }

        /* --- Main Content --- */
        .wrapper {
            margin-right: calc(var(--sidebar-w) + 40px);
            padding: 40px 0 40px 40px;
            transition: margin-right 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        }

        /* --- Header --- */
        .lux-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 60px; padding-bottom: 20px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .title h1 { font-size: 3.5rem; font-weight: 900; color: #fff; letter-spacing: -1px; margin: 0; }
        .title p { color: var(--text-muted); font-size: 1.1rem; margin-top: 5px; font-weight: 300; }
        
        .date-display {
            font-size: 1.2rem; font-weight: 300; letter-spacing: 1px;
            color: var(--gold-light); border: 1px solid var(--border-gold);
            padding: 10px 30px; border-radius: 50px;
            background: rgba(212, 175, 55, 0.05);
        }

        /* --- Bento Grid (KPIs) --- */
        .bento-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 50px;
        }
        .bento-card {
            background: #080808;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            padding: 32px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 220px; transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .bento-card:hover {
            transform: translateY(-8px);
            border-color: rgba(255,255,255,0.15);
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }
        /* Spotlight Effect */
        .bento-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 60%);
            opacity: 0; transition: 0.5s; pointer-events: none;
        }
        .bento-card:hover::before { opacity: 1; }

        .b-icon { 
            font-size: 2.2rem; color: var(--text-muted); margin-bottom: 20px; 
            transition: 0.4s; display: inline-block;
        }
        .bento-card:hover .b-icon { color: var(--gold-light); transform: scale(1.1); filter: drop-shadow(0 0 10px var(--gold-primary)); }
        
        .b-val { font-size: 2.8rem; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 5px; }
        .b-label { font-size: 1rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
        
        .b-sales { grid-column: span 2; background: linear-gradient(160deg, #0f0f0f 0%, #000 100%); border-color: rgba(212, 175, 55, 0.2); }
        .b-sales .b-val { color: var(--gold-light); }

        /* --- Actions Deck --- */
        .section-header { 
            font-size: 1.8rem; margin-bottom: 30px; font-weight: 700; color: #fff;
            display: flex; align-items: center; gap: 15px;
        }
        .section-header::after { content: ''; flex: 1; height: 1px; background: var(--border-subtle); }

        .deck-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-bottom: 60px;
        }
        .deck-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 25px; text-align: center;
            transition: 0.4s; cursor: pointer;
        }
        .deck-card:hover {
            background: rgba(212, 175, 55, 0.05);
            border-color: var(--gold-primary);
            transform: translateY(-5px);
        }
        .deck-icon { font-size: 2rem; color: var(--text-muted); margin-bottom: 15px; transition: 0.3s; }
        .deck-card:hover .deck-icon { color: #fff; transform: scale(1.2); }
        .deck-text { font-size: 1.1rem; font-weight: 600; color: #fff; }

        /* --- Filter Bar --- */
        .control-bar {
            background: #080808; border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl); padding: 25px;
            display: flex; gap: 20px; align-items: center; margin-bottom: 50px;
        }
        .input-lux {
            flex: 1; background: #000; border: 1px solid #222; height: 55px;
            border-radius: 14px; padding: 0 20px; color: #fff; font-size: 1.1rem;
            transition: 0.3s;
        }
        .input-lux:focus { border-color: var(--gold-primary); box-shadow: 0 0 15px rgba(212, 175, 55, 0.1); }
        
        .btn-lux {
            height: 55px; padding: 0 40px; background: var(--gold-gradient);
            color: #000; border: none; border-radius: 14px;
            font-weight: 800; font-size: 1.1rem; cursor: pointer;
            box-shadow: 0 5px 20px rgba(212, 175, 55, 0.2); transition: 0.3s;
        }
        .btn-lux:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4); }

        /* --- Charts Section --- */
        .chart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .chart-panel {
            background: #050505; border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl); padding: 40px;
        }
        .chart-header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .chart-title { font-size: 1.5rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 15px; }

        /* --- Responsive --- */
        @media (max-width: 1400px) { .bento-grid { grid-template-columns: 1fr 1fr; } .chart-layout { grid-template-columns: 1fr; } }
        @media (max-width: 1024px) { 
            .sidebar { width: 0; padding: 0; border: none; }
            .sidebar.show { width: 280px; padding: 40px 20px; border: 1px solid var(--border-subtle); }
            .wrapper { margin-right: 0; padding: 20px; }
            .control-bar { flex-direction: column; }
            .mobile-trigger { display: flex; position: fixed; bottom: 30px; left: 30px; width: 60px; height: 60px; background: var(--gold-gradient); border-radius: 50%; z-index: 2000; align-items: center; justify-content: center; font-size: 1.5rem; color: #000; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        }
        @media (min-width: 1025px) { .mobile-trigger { display: none; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-icon">
            <i class="fa-solid fa-crown"></i>
        </div>
        
        <nav class="nav-menu">
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-house-chimney"></i> <span class="nav-text">داشبورد</span>
            </a>
            <a href="invoice.php" class="nav-item">
                <i class="fa-solid fa-file-invoice-dollar"></i> <span class="nav-text">مدیریت مالی</span>
            </a>
            <a href="user.php" class="nav-item">
                <i class="fa-solid fa-users-line"></i> <span class="nav-text">مشترکین</span>
            </a>
            <a href="product.php" class="nav-item">
                <i class="fa-solid fa-box-open"></i> <span class="nav-text">محصولات</span>
            </a>
            <a href="server_status.php" class="nav-item">
                <i class="fa-solid fa-server"></i> <span class="nav-text">وضعیت سرور</span>
            </a>
            <div style="flex: 1;"></div>
            <a href="logout.php" class="nav-item" style="color: #EF4444;">
                <i class="fa-solid fa-power-off"></i> <span class="nav-text">خروج</span>
            </a>
        </nav>
    </aside>

    <div class="mobile-trigger" onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="fa-solid fa-bars"></i>
    </div>

    <!-- Main Content -->
    <div class="wrapper">
        
        <header class="lux-header">
            <div class="title">
                <h1><?php echo $greet; ?>، <span class="text-gold">سرورم</span></h1>
                <p>مرکز کنترل و نظارت بر عملکرد سیستم</p>
            </div>
            <div class="date-display">
                <?php echo $today; ?>
            </div>
        </header>

        <!-- Bento Grid -->
        <section class="bento-grid">
            <!-- Total Sales (Large) -->
            <div class="bento-card b-sales">
                <div>
                    <i class="fa-solid fa-sack-dollar b-icon"></i>
                    <div class="b-label">مجموع درآمد کل</div>
                </div>
                <div>
                    <div class="b-val"><?php echo number_format($stats['sales']); ?></div>
                    <div style="color: var(--gold-primary); font-size: 0.9rem;">تومان ایران</div>
                </div>
            </div>

            <!-- Orders -->
            <div class="bento-card">
                <i class="fa-solid fa-file-signature b-icon"></i>
                <div>
                    <div class="b-val"><?php echo number_format($stats['orders']); ?></div>
                    <div class="b-label">سفارشات موفق</div>
                </div>
            </div>

            <!-- Active Users -->
            <div class="bento-card">
                <i class="fa-solid fa-users-rays b-icon"></i>
                <div>
                    <div class="b-val"><?php echo number_format($stats['users']); ?></div>
                    <div class="b-label">کاربران فعال</div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <div class="section-header">عملیات اجرایی</div>
        <section class="deck-grid">
            <a href="invoice.php" class="deck-card">
                <i class="fa-solid fa-list-check deck-icon"></i>
                <div class="deck-text">سفارشات</div>
            </a>
            <a href="user.php" class="deck-card">
                <i class="fa-solid fa-user-shield deck-icon"></i>
                <div class="deck-text">کاربران</div>
            </a>
            <a href="inbound.php" class="deck-card">
                <i class="fa-solid fa-network-wired deck-icon"></i>
                <div class="deck-text">کانفیگ‌ها</div>
            </a>
            <a href="payment.php" class="deck-card">
                <i class="fa-solid fa-credit-card deck-icon"></i>
                <div class="deck-text">تراکنش‌ها</div>
            </a>
            <a href="server_status.php" class="deck-card">
                <i class="fa-solid fa-microchip deck-icon"></i>
                <div class="deck-text">سرور</div>
            </a>
            <a href="cancelService.php" class="deck-card" style="border-color: rgba(239,68,68,0.3);">
                <i class="fa-solid fa-ban deck-icon" style="color: #EF4444;"></i>
                <div class="deck-text" style="color: #EF4444;">مسدودی</div>
            </a>
        </section>

        <!-- Filter Console -->
        <form method="get" class="control-bar">
            <div style="flex: 1; display: flex; align-items: center; gap: 15px;">
                <i class="fa-solid fa-calendar-days" style="color: var(--text-muted); font-size: 1.5rem;"></i>
                <input type="text" id="dateRange" class="input-lux" placeholder="محدوده زمانی..." readonly>
                <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($filterFrom ?? ''); ?>">
                <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($filterTo ?? ''); ?>">
            </div>
            <div style="flex: 1;">
                <select name="status[]" multiple class="input-lux" style="padding-top: 14px;">
                    <?php foreach($statusConfig as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $filterStatus) ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-lux">
                <i class="fa-solid fa-filter" style="margin-left: 10px;"></i> اعمال
            </button>
        </form>

        <!-- Charts Area -->
        <section class="chart-layout">
            <div class="chart-panel">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fa-solid fa-chart-line" style="color: var(--gold-primary);"></i>
                        تحلیل درآمد
                    </div>
                </div>
                <div style="height: 400px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 30px;">
                <div class="chart-panel" style="flex: 1;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-chart-pie"></i> وضعیت سرویس‌ها</div>
                    </div>
                    <div style="height: 250px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="chart-panel" style="flex: 1;">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-arrow-trend-up"></i> رشد کاربر</div>
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
    <script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>

    <script>
        $(function(){
            var start = moment().subtract(13, 'days');
            var end = moment();
            var pf = '<?php echo $filterFrom; ?>';
            var pt = '<?php echo $filterTo; ?>';
            if(pf && pt){ start = moment(pf); end = moment(pt); }
            
            $('#dateRange').daterangepicker({ startDate: start, endDate: end, opens: 'left', locale: { format: 'YYYY/MM/DD', applyLabel: 'تایید', cancelLabel: 'لغو' } }, function(s, e) {
                $('#dateRange').val(s.format('YYYY/MM/DD') + '  -  ' + e.format('YYYY/MM/DD'));
                $('#inputFrom').val(s.format('YYYY-MM-DD'));
                $('#inputTo').val(e.format('YYYY-MM-DD'));
            });
            if(pf) $('#dateRange').val(start.format('YYYY/MM/DD') + '  -  ' + end.format('YYYY/MM/DD'));
        });

        // --- Luxury Chart Config ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.font.size = 13;
        Chart.defaults.color = '#666';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.03)';
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?>, colors: <?php echo json_encode($pieColors); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        // Sales (Gold Gradient)
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gS = ctxS.createLinearGradient(0, 0, 0, 400);
        gS.addColorStop(0, '#D4AF37');
        gS.addColorStop(1, 'rgba(212, 175, 55, 0.05)');

        new Chart(ctxS, {
            type: 'line',
            data: {
                labels: dSales.labels,
                datasets: [{
                    label: 'فروش', data: dSales.values,
                    borderColor: '#D4AF37', backgroundColor: gS,
                    borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#000', pointBorderColor: '#D4AF37',
                    fill: true, tension: 0.4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, border: { display: false } } }
            }
        });

        // Status (Clean Ring)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: dPie.labels, datasets: [{
                    data: dPie.values, backgroundColor: dPie.colors,
                    borderWidth: 0, hoverOffset: 10
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '85%',
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true, color: '#888' } } }
            }
        });

        // Users (Bar)
        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: {
                labels: dUsers.labels, datasets: [{
                    label: 'کاربر', data: dUsers.values,
                    backgroundColor: '#10B981', borderRadius: 4, barThickness: 15
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