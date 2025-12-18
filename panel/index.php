<?php
// --- سیستم مدیریت سازمانی (Enterprise Core) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);

// تنظیم منطقه زمانی برای حل مشکل تاریخ
date_default_timezone_set('Asia/Tehran');

session_start();

// --- لایه وابستگی‌ها ---
if (file_exists('../config.php')) require_once '../config.php';
if (file_exists('../jdf.php')) require_once '../jdf.php';

$isConnected = isset($pdo) && ($pdo instanceof PDO);

// --- تنظیمات زمانی ---
$startOfToday = strtotime('today'); 
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
        $stmt->execute([':ts' => $startOfToday]);
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

// Status Colors
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
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.65);
            --bg-card-hover: rgba(35, 35, 45, 0.85);
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-gold: #fbbf24;
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            --border-subtle: 1px solid rgba(255, 255, 255, 0.08);
            --radius-main: 24px;
        }

        * { box-sizing: border-box; outline: none; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        body {
            background-color: var(--bg-body);
            background-image: linear-gradient(rgba(5, 5, 9, 0.6), rgba(5, 5, 9, 0.6)), url('https://i.giphy.com/media/v1.Y2lkPTc5MGI3NjExNHJndXIzcXV2dzN2N2J2ZjFmNHJndXIzcXV2dzN2N2J2ZjFmNHJndXImZXA9djFfaW50ZXJuYWxfZ2lmX2J5X2lkJmN0PWc/3o7TKVUn7iM8FMEU24/giphy.gif');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            color: var(--text-pri);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            padding-bottom: 140px;
            overflow-x: hidden;
        }

        .dashboard-container {
            width: 100%; max-width: 1920px; margin: 0 auto;
            padding: 35px 5%; display: flex; flex-direction: column; gap: 35px;
            position: relative; z-index: 1;
        }

        /* Header */
        .header-section { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .ht-sup { font-size: 0.9rem; color: var(--neon-blue); font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .ht-sup::before { content: ''; width: 20px; height: 2px; background: var(--neon-blue); border-radius: 2px; }
        .ht-main { font-size: 3rem; font-weight: 900; margin: 0; background: linear-gradient(to right, #fff 40%, #94a3b8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .info-pill { background: rgba(255,255,255,0.05); border: var(--border-subtle); padding: 10px 20px; border-radius: 16px; backdrop-filter: blur(15px); }
        .user-avatar-ring { width: 55px; height: 55px; border-radius: 50%; padding: 3px; background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple)); box-shadow: 0 0 20px rgba(0, 242, 255, 0.3); position: relative; }
        .user-avatar-img { width: 100%; height: 100%; background: #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; }

        /* Stats Card */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; }
        .holo-card { background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main); padding: 25px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(15px); transition: 0.4s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .holo-card:hover { transform: translateY(-5px) scale(1.02); background: var(--bg-card-hover); border-color: rgba(255,255,255,0.2); }
        .hc-val { font-size: 2.2rem; font-weight: 800; }
        .hc-icon-box { width: 60px; height: 60px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: rgba(255,255,255,0.05); }

        /* Bento */
        .bento-wrapper { display: grid; grid-template-columns: 2.2fr 1fr; gap: 30px; }
        .col-main, .col-side { display: flex; flex-direction: column; gap: 30px; }
        .glass-panel { background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main); padding: 30px; backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(0,0,0,0.5); }
        .actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .nav-tile { height: 110px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; text-decoration: none; color: var(--text-sec); transition: 0.3s; backdrop-filter: blur(10px); }
        .nav-tile:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.4); color: #fff; }

        /* --- SUPER DOCK (RESTORED VERSION) --- */
        .dock-container { position: fixed; bottom: 30px; left: 0; right: 0; display: flex; justify-content: center; pointer-events: none; z-index: 9999; }
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
            border-radius: 18px; color: var(--text-sec); font-size: 1.5rem;
            text-decoration: none; position: relative;
            background: transparent;
            transition: all 0.25s cubic-bezier(0.3, 0.7, 0.4, 1.5); /* فنری بودن ورژن اصلی */
        }
        .dock-item:hover {
            width: 65px; height: 65px;
            margin: 0 5px;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            transform: translateY(-15px);
        }
        .dock-item.active { color: var(--neon-blue); background: rgba(0, 242, 255, 0.1); border: 1px solid rgba(0, 242, 255, 0.2); }
        
        /* Tooltips */
        .dock-item::before {
            content: attr(data-tooltip);
            position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(10px) scale(0.8);
            background: rgba(0,0,0,0.9); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; padding: 6px 14px; border-radius: 10px; font-size: 0.8rem;
            opacity: 0; visibility: hidden; transition: 0.2s; pointer-events: none; margin-bottom: 15px;
        }
        .dock-item:hover::before { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0) scale(1); }
        .dock-divider { width: 1px; height: 35px; background: rgba(255,255,255,0.1); margin: 0 5px; }

        @media (max-width: 1400px) { .bento-wrapper { grid-template-columns: 1fr; } }
        @media (max-width: 992px) { .actions-grid { grid-template-columns: repeat(2, 1fr); } .stats-row { grid-template-columns: repeat(2, 1fr); } .ht-main { font-size: 2.2rem; } }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <header class="header-section">
            <div class="header-titles">
                <div class="ht-sup">پنل فرماندهی سیستم</div>
                <h1 class="ht-main"><?php echo $greet; ?>، ادمین</h1>
                <div class="ht-sub">وضعیت کلی سیستم در حالت پایدار است.</div>
            </div>
            <div class="header-actions">
                <div class="info-pill"><i class="fa-regular fa-calendar me-2"></i><span><?php echo $today; ?></span></div>
                <div class="user-avatar-ring"><div class="user-avatar-img"><i class="fa-solid fa-user-astronaut"></i></div></div>
            </div>
        </header>

        <section class="stats-row">
            <div class="holo-card" style="--c: var(--neon-blue);">
                <div><div class="hc-val"><?php echo number_format($stats['sales']); ?></div><div class="hc-lbl">درآمد (تومان)</div></div>
                <div class="hc-icon-box" style="color: var(--neon-blue);"><i class="fa-solid fa-sack-dollar"></i></div>
            </div>
            <div class="holo-card">
                <div><div class="hc-val"><?php echo number_format($stats['orders']); ?></div><div class="hc-lbl">سفارشات</div></div>
                <div class="hc-icon-box" style="color: var(--neon-green);"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <div class="holo-card">
                <div><div class="hc-val"><?php echo number_format($stats['users']); ?></div><div class="hc-lbl">کل کاربران</div></div>
                <div class="hc-icon-box" style="color: var(--neon-purple);"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="holo-card">
                <div><div class="hc-val"><?php echo number_format($stats['new_users']); ?></div><div class="hc-lbl">ثبت‌نام امروز</div></div>
                <div class="hc-icon-box" style="color: var(--neon-gold);"><i class="fa-solid fa-user-plus"></i></div>
            </div>
        </section>

        <section class="bento-wrapper">
            <div class="col-main">
                <div class="glass-panel" style="flex-grow: 1;"><div class="panel-head"><div class="ph-title"><i class="fa-solid fa-chart-line"></i> نمودار فروش</div></div><div style="height: 350px;"><canvas id="salesChart"></canvas></div></div>
                <div class="actions-grid">
                    <a href="users.php" class="nav-tile"><i class="fa-solid fa-users-gear fa-2x"></i><span>کاربران</span></a>
                    <a href="invoice.php" class="nav-tile"><i class="fa-solid fa-file-invoice fa-2x"></i><span>سفارشات</span></a>
                    <a href="product.php" class="nav-tile"><i class="fa-solid fa-box-open fa-2x"></i><span>محصولات</span></a>
                    <a href="service.php" class="nav-tile"><i class="fa-solid fa-server fa-2x"></i><span>سرویس‌ها</span></a>
                </div>
            </div>
            <div class="col-side">
                <div class="glass-panel"><div class="panel-head"><div class="ph-title"><i class="fa-solid fa-chart-pie"></i> وضعیت</div></div><div style="height: 220px; display: flex; align-items: center; justify-content: center; position: relative;"><canvas id="statusChart"></canvas><div style="position: absolute; font-size: 2rem; font-weight: 800;"><?php echo $totalStatus; ?></div></div></div>
                <div class="glass-panel"><div class="panel-head"><div class="ph-title"><i class="fa-solid fa-chart-column"></i> جذب</div></div><div style="height: 180px;"><canvas id="usersChart"></canvas></div></div>
            </div>
        </section>
    </div>

    <!-- The Super Dock (Restored Hover Effects) -->
    <div class="dock-container">
        <nav class="super-dock">
            <a href="index.php" class="dock-item active" data-tooltip="داشبورد"><i class="fa-solid fa-house"></i></a>
            <div class="dock-divider"></div>
            <a href="users.php" class="dock-item" data-tooltip="کاربران"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-item" data-tooltip="سفارشات"><i class="fa-solid fa-file-contract"></i></a>
            <a href="product.php" class="dock-item" data-tooltip="محصولات"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-item" data-tooltip="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
            <div class="dock-divider"></div>
            <a href="payment.php" class="dock-item" data-tooltip="امور مالی"><i class="fa-solid fa-wallet"></i></a>
            <a href="settings.php" class="dock-item" data-tooltip="تنظیمات"><i class="fa-solid fa-gear"></i></a>
            <a href="login.php" class="dock-item" data-tooltip="خروج" style="color: var(--neon-red);"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = 'Vazirmatn'; 
        Chart.defaults.color = '#94a3b8'; 
        
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };

        new Chart(document.getElementById('salesChart').getContext('2d'), {
            type: 'line',
            data: { labels: dSales.labels, datasets: [{ data: dSales.values, borderColor: '#00f2ff', backgroundColor: 'rgba(0, 242, 255, 0.1)', borderWidth: 3, fill: true, tension: 0.4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: { labels: dPie.labels, datasets: [{ data: dPie.values, backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#d946ef', '#f97316', '#334155'], borderWidth: 0, cutout: '80%' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('usersChart'), {
            type: 'bar',
            data: { labels: dUsers.labels, datasets: [{ data: dUsers.values, backgroundColor: '#00ff9d', borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    </script>
</body>
</html>