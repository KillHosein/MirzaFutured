<?php

// --- PHP Error Reporting (for debugging 500 errors) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- Configuration & Helpers ---
require_once '../config.php';
require_once '../jdf.php'; // Jalali date functions

// --- Critical Check: Database Connection ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Fatal Error: Database connection is not available. Please check your 'config.php'.");
}

// --- Authentication Function ---
function authenticate_admin(PDO $pdo): array {
    if (!isset($_SESSION["user"])) {
        header('Location: login.php');
        exit;
    }

    try {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->execute(['username' => $_SESSION["user"]]);
        $admin = $query->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            header('Location: login.php');
            exit;
        }
        return $admin;
    } catch (PDOException $e) {
        error_log("Authentication failed: " . $e->getMessage());
        die("Database error during authentication. Please check logs.");
    }
}

// --- Data Fetching & Processing ---
$adminUser = authenticate_admin($pdo);

// 1. Filters
$datefirstday = strtotime('today midnight'); // More readable way to get start of today
$fromDate = isset($_GET['from']) && !empty($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) && !empty($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [];

// 2. Build Invoice Filter Query
$invoiceWhere = ["name_product != 'سرویس تست'"];
$invoiceParams = [];

if ($fromDate && strtotime($fromDate)) {
    $invoiceWhere[] = "time_sell >= :fromTs";
    $invoiceParams[':fromTs'] = strtotime($fromDate);
}
if ($toDate && strtotime($toDate)) {
    $invoiceWhere[] = "time_sell <= :toTs";
    $invoiceParams[':toTs'] = strtotime($toDate . ' 23:59:59');
}

if (!empty($selectedStatuses)) {
    $placeholders = [];
    foreach ($selectedStatuses as $i => $status) {
        $placeholder = ":status_$i";
        $placeholders[] = $placeholder;
        $invoiceParams[$placeholder] = $status;
    }
    $invoiceWhere[] = "status IN (" . implode(', ', $placeholders) . ")";
} else {
    // Default statuses for a relevant initial view
    $defaultStatuses = ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid'];
    $invoiceWhere[] = "status IN ('" . implode("', '", $defaultStatuses) . "')";
}

$invoiceWhereSql = implode(' AND ', $invoiceWhere);

// 3. Fetch Statistics
try {
    // Total Sales (excluding unpaid)
    $stmt = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
    $stmt->execute($invoiceParams);
    $total_sales = $stmt->fetchColumn() ?? 0;

    // Total Orders (excluding unpaid)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
    $stmt->execute($invoiceParams);
    $total_orders = $stmt->fetchColumn();

    // Total Users (overall)
    $total_users = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();

    // New Users Today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :time_register AND register != 'none'");
    $stmt->execute([':time_register' => $datefirstday]);
    $new_users_today = $stmt->fetchColumn();

} catch (PDOException $e) {
    die("Database Error during data retrieval. Message: " . $e->getMessage());
}

$formatted_total_sales = number_format($total_sales);

// 4. Sales Trend Chart Data
$salesDataForChart = [];
$salesLabels = [];
$salesAmount = [];
if ($total_orders > 0) {
    try {
        $stmt = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid' ORDER BY time_sell ASC");
        $stmt->execute($invoiceParams);
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($salesData as $sell) {
            if (!is_numeric($sell['time_sell'])) continue;
            $day = date('Y/m/d', (int)$sell['time_sell']);
            if (!isset($salesDataForChart[$day])) {
                $salesDataForChart[$day] = 0;
            }
            $salesDataForChart[$day] += (int)$sell['price_product'];
        }
        
        $salesLabels = array_values(array_map(fn($d) => jdate('d M', strtotime($d)), array_keys($salesDataForChart)));
        $salesAmount = array_values($salesDataForChart);
    } catch (PDOException $e) {
        die("Error fetching sales trend: " . $e->getMessage());
    }
}

// 5. Status Distribution Chart Data
$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت', 'active' => 'فعال', 'disabledn' => 'غیرفعال', 'end_of_time' => 'پایان زمان',
    'end_of_volume' => 'پایان حجم', 'sendedwarn' => 'هشدار', 'send_on_hold' => 'در انتظار اتصال', 'removebyuser' => 'حذف شده'
];
$colorMap = [
    'unpaid' => '#fbbf24', 'active' => '#10b981', 'disabledn' => '#94a3b8', 'end_of_time' => '#ef4444',
    'end_of_volume' => '#3b82f6', 'sendedwarn' => '#a855f7', 'send_on_hold' => '#f97316', 'removebyuser' => '#475569'
];

$statusLabels = [];
$statusData = [];
$statusColors = [];
try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM invoice WHERE $invoiceWhereSql GROUP BY status");
    $stmt->execute($invoiceParams);
    $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statusRows as $row) {
        $status = $row['status'];
        $statusLabels[] = $statusMapFa[$status] ?? $status;
        $statusData[] = (int)$row['count'];
        $statusColors[] = $colorMap[$status] ?? '#64748b';
    }
} catch (PDOException $e) {
    die("Error fetching status distribution: " . $e->getMessage());
}

// 6. New Users Trend Chart Data
$userStart = ($fromDate) ? strtotime($fromDate) : (time() - 13 * 86400);
$userEnd = ($toDate) ? strtotime($toDate) : time();
$daysInRange = floor(($userEnd - $userStart) / 86400) + 1;

$userCounts = array_fill(0, $daysInRange, 0);
$userLabels = [];
$dateIndexMap = [];
for ($i = 0; $i < $daysInRange; $i++) {
    $timestamp = $userStart + ($i * 86400);
    $dateKey = date('Y/m/d', $timestamp);
    $userLabels[] = jdate('d M', $timestamp);
    $dateIndexMap[$dateKey] = $i;
}

try {
    $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :start AND register <= :end");
    $stmt->execute([':start' => $userStart, ':end' => $userEnd + 86400 - 1]);
    $regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($regRows as $row) {
        if (!is_numeric($row['register'])) continue;
        $dateKey = date('Y/m/d', (int)$row['register']);
        if (isset($dateIndexMap[$dateKey])) {
            $userCounts[$dateIndexMap[$dateKey]]++;
        }
    }
} catch (PDOException $e) {
    die("Error fetching new users trend: " . $e->getMessage());
}

// 7. Time-based Greeting
$hour = date('H');
if ($hour < 12) { $greeting = "صبح بخیر"; $greetIcon = "icon-sun"; } 
elseif ($hour < 18) { $greeting = "روز بخیر"; $greetIcon = "icon-coffee"; } 
else { $greeting = "عصر بخیر"; $greetIcon = "icon-moon"; }

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت | داشبورد</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    
    <!-- CSS Dependencies -->
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />
    <style>
        /* --- CSS Variables (Refined Dark/Glass Theme) --- */
        :root {
            --bg-body: #0a0e17; /* Even deeper space blue */
            --glass-bg: rgba(18, 25, 40, 0.75);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.3);
            --accent: #06b6d4; /* Cyan */
            
            --text-main: #f1f5f9; /* Lighter slate for softer text */
            --text-muted: #94a3b8;
            --text-hero: #fff;
            
            --font-main: 'Vazirmatn', sans-serif;
            --header-height: 70px;
            --border-radius-lg: 24px;
            --border-radius-md: 16px;
            --border-radius-sm: 12px;
        }
        
        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: var(--font-main);
            margin: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* --- Global Layout & Animations --- */
        #main-content { padding-top: var(--header-height); }
        .wrapper { padding: clamp(20px, 4vw, 30px); max-width: 1700px; margin: 0 auto; display: flex; flex-direction: column; gap: clamp(20px, 4vw, 30px); }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-enter { animation: fadeInUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .animate-enter[style*="--delay"] { animation-delay: var(--delay); }

        /* --- Header --- */
        .site-header {
            position: fixed; top: 0; right: 0; left: 0; height: var(--header-height); z-index: 100;
            background: rgba(11, 17, 33, 0.85); backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--glass-border);
            display: flex; align-items: center; padding: 0 clamp(20px, 4vw, 30px);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.5);
        }
        .header-title { color: var(--text-hero); font-size: 20px; font-weight: 800; }
        .header-nav { margin-right: auto; display: flex; gap: 10px; }
        .header-nav a { 
            color: var(--text-muted); text-decoration: none; padding: 8px 15px; border-radius: var(--border-radius-sm); 
            transition: all 0.2s ease; font-weight: 500; display: flex; align-items: center; gap: 8px;
        }
        .header-nav a:hover, .header-nav a.active { color: var(--text-hero); background: rgba(255, 255, 255, 0.1); }
        .header-nav a.active { font-weight: 700; }

        /* --- Glassmorphism Card Style --- */
        .modern-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-lg);
            padding: 28px;
            box-shadow: var(--glass-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .modern-card:hover { transform: translateY(-5px); box-shadow: 0 15px 45px rgba(0, 0, 0, 0.5); border-color: rgba(255,255,255,0.2); }

        /* --- Page Sections --- */
        .hero-banner { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 20px; }
        .hero-title h1 { 
            font-size: clamp(24px, 5vw, 32px); font-weight: 900; 
            background: linear-gradient(to right, #e2e8f0, #fff); 
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin: 0 0 8px 0;
        }
        .hero-subtitle { font-size: 16px; color: var(--text-muted); display: flex; align-items: center; flex-wrap: wrap; gap: 8px 12px; }
        .hero-subtitle i { color: var(--accent); }

        .filter-bar {
            background: rgba(18, 25, 40, 0.8); border: 1px solid var(--glass-border); border-radius: var(--border-radius-md);
            padding: 15px 25px; display: flex; flex-wrap: wrap; align-items: center; gap: 15px; justify-content: space-between;
        }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 30px; }
        .stat-card { display: flex; align-items: center; gap: 20px; }
        .stat-icon-wrapper { 
            width: 64px; height: 64px; border-radius: 50%; 
            display: grid; place-items: center; font-size: 26px; 
            background: linear-gradient(145deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            color: var(--icon-color, var(--accent));
        }
        .icon-grad-1 { --icon-color: #60a5fa; }
        .icon-grad-2 { --icon-color: #c084fc; }
        .icon-grad-3 { --icon-color: #fb923c; }
        .icon-grad-4 { --icon-color: #34d399; }

        .stat-content h3 { font-size: clamp(24px, 5vw, 32px); font-weight: 800; margin: 0 0 4px 0; color: var(--text-hero); letter-spacing: -1px; }
        .stat-content span { font-size: 15px; color: var(--text-muted); font-weight: 500; }

        #chartsArea { display: grid; gap: 30px; grid-template-columns: repeat(12, 1fr); }
        .chart-card { display: none; /* Controlled by JS */ flex-direction: column; width: 100%; }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .chart-title { font-size: 18px; font-weight: 700; color: var(--text-hero); display: flex; align-items: center; gap: 10px; }
        .chart-title i { color: var(--accent); }

        .section-header { 
            font-size: 20px; font-weight: 800; color: var(--text-hero); display: flex; align-items: center; gap: 15px; 
            margin-bottom: 25px; padding-bottom: 5px; border-bottom: 2px solid var(--primary); width: fit-content;
        }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px; }
        .action-btn {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 25px 15px; gap: 15px; background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.08); border-radius: var(--border-radius-md);
            text-decoration: none !important; color: var(--text-main);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); font-weight: 600;
        }
        .action-btn i { font-size: 32px; transition: 0.3s; color: var(--accent); }
        .action-btn:hover { 
            transform: translateY(-6px); background: rgba(40, 51, 69, 0.9);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5), 0 0 15px var(--primary-glow);
            border-color: var(--primary);
        }
        .action-btn:hover i { transform: scale(1.1); color: var(--primary); }
        .action-btn.danger i { color: #ef4444; }
        .action-btn.danger:hover { border-color: #ef4444; box-shadow: 0 10px 25px rgba(0,0,0,0.5), 0 0 15px rgba(239, 68, 68, 0.4); }
        .action-btn.danger:hover i { color: #ef4444; }

        /* --- Form Inputs & Buttons --- */
        .input-glass {
            background: rgba(45, 55, 72, 0.6); border: 1px solid #334155; color: var(--text-main);
            border-radius: var(--border-radius-sm); padding: 12px 16px; font-family: var(--font-main);
            outline: none; transition: 0.3s; width: 100%; box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);
        }
        .input-glass:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-glow), inset 0 1px 3px rgba(0,0,0,0.5); }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary), #6d28d9); color: white; border: none;
            padding: 12px 24px; border-radius: var(--border-radius-sm); font-weight: 700; cursor: pointer;
            transition: 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
            box-shadow: 0 5px 20px var(--primary-glow); display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-gradient:hover { transform: translateY(-3px); box-shadow: 0 8px 30px var(--primary-glow); filter: brightness(1.15); }

        .btn-glass {
            background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: var(--text-muted);
            padding: 10px 18px; border-radius: var(--border-radius-sm); transition: 0.2s; cursor: pointer;
        }
        .btn-glass:hover { background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.25); }
        
        /* --- Footer --- */
        #footer { 
            margin-top: 50px; padding: 25px; color: var(--text-muted); text-align: center; 
            font-size: 13px; border-top: 1px solid rgba(255, 255, 255, 0.05); background: rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
<section id="container">

    <header class="site-header">
        <h1 class="header-title">پنل مدیریت</h1>
        <nav class="header-nav">
            <a href="index.php" class="active"><i class="icon-dashboard"></i> داشبورد</a>
            <a href="invoice.php"><i class="icon-list-alt"></i> سفارشات</a>
            <a href="user.php"><i class="icon-group"></i> کاربران</a>
            <a href="logout.php"><i class="icon-off"></i> خروج</a>
        </nav>
    </header>

    <main id="main-content">
        <section class="wrapper">
            
            <div class="hero-banner animate-enter">
                <div class="hero-title">
                    <h1><?php echo htmlspecialchars($greeting); ?>، <?php echo htmlspecialchars($adminUser['username']); ?></h1>
                    <div class="hero-subtitle">
                        <i class="<?php echo $greetIcon; ?>"></i>
                        <span>امروز <?php echo jdate('l، j F Y'); ?></span>
                        <span style="opacity: 0.3;">|</span>
                        <span style="color: #34d399; display:flex; align-items:center; gap: 5px;"><i class="icon-ok-sign"></i> سیستم پایدار است</span>
                    </div>
                </div>
            </div>

            <div class="filter-bar animate-enter" style="--delay: 0.1s;">
                <form class="filter-inputs" method="get" id="dashboardFilterForm" style="display:contents;">
                    <div style="position: relative; flex: 1 1 250px;">
                        <input type="text" id="rangePicker" class="input-glass" placeholder="انتخاب محدوده تاریخ..." style="padding-right: 40px; text-align: right;">
                        <i class="icon-calendar" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"></i>
                    </div>
                    <input type="hidden" name="from" id="rangeFrom" value="<?php echo htmlspecialchars($fromDate ?? '', ENT_QUOTES); ?>">
                    <input type="hidden" name="to" id="rangeTo" value="<?php echo htmlspecialchars($toDate ?? '', ENT_QUOTES); ?>">
                    
                    <select name="status[]" multiple class="input-glass" style="flex: 1 1 250px;">
                        <?php foreach($statusMapFa as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo in_array($sk, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div style="display: flex; gap: 10px; flex-shrink: 0;">
                        <button type="submit" class="btn-gradient"><i class="icon-filter"></i> <span>فیلتر</span></button>
                        <?php if($fromDate || $toDate || !empty($selectedStatuses)): ?>
                            <a href="index.php" class="btn-glass" title="حذف فیلترها"><i class="icon-refresh"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="stats-grid animate-enter" style="--delay: 0.2s;">
                <div class="modern-card stat-card"><div class="stat-icon-wrapper icon-grad-1"><i class="icon-bar-chart"></i></div><div class="stat-content"><h3><?php echo $formatted_total_sales; ?></h3><span>مجموع فروش (تومان)</span></div></div>
                <div class="modern-card stat-card"><div class="stat-icon-wrapper icon-grad-2"><i class="icon-shopping-cart"></i></div><div class="stat-content"><h3><?php echo number_format($total_orders); ?></h3><span>تعداد سفارشات</span></div></div>
                <div class="modern-card stat-card"><div class="stat-icon-wrapper icon-grad-3"><i class="icon-group"></i></div><div class="stat-content"><h3><?php echo number_format($total_users); ?></h3><span>کل کاربران</span></div></div>
                <div class="modern-card stat-card"><div class="stat-icon-wrapper icon-grad-4"><i class="icon-user-plus"></i></div><div class="stat-content"><h3><?php echo number_format($new_users_today); ?></h3><span>کاربران جدید امروز</span></div></div>
            </div>

            <div id="dashboard-controls" class="modern-card animate-enter" style="--delay: 0.3s; padding: 15px 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <span style="font-size: 15px; font-weight: 500; color: #cbd5e1;"><i class="icon-cogs"></i> نمایش نمودارها:</span>
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <label class="custom-check"><input type="checkbox" v-model="show.sales"> روند فروش</label>
                    <label class="custom-check"><input type="checkbox" v-model="show.status"> توزیع وضعیت‌ها</label>
                    <label class="custom-check"><input type="checkbox" v-model="show.users"> جذب کاربر</label>
                </div>
            </div>

            <div id="chartsArea" class="animate-enter" style="--delay: 0.4s;">
                <div class="chart-card modern-card" id="salesChartContainer"><div class="chart-header"><span class="chart-title"><i class="icon-bar-chart"></i> تحلیل فروش روزانه</span></div><div style="height: 350px; width: 100%;"><canvas id="salesChart"></canvas></div></div>
                <div class="chart-card modern-card" id="statusChartContainer"><div class="chart-header"><span class="chart-title"><i class="icon-pie-chart"></i> وضعیت سفارشات</span></div><div style="height: 300px; display: flex; justify-content: center; position: relative;"><canvas id="statusChart"></canvas></div></div>
                <div class="chart-card modern-card" id="usersChartContainer"><div class="chart-header"><span class="chart-title"><i class="icon-line-chart"></i> روند ثبت نام کاربران</span></div><div style="height: 300px; width: 100%;"><canvas id="usersChart"></canvas></div></div>
            </div>
            
            <div class="animate-enter" style="--delay: 0.5s;">
                <div class="section-header"><i class="icon-bolt" style="color: var(--accent);"></i> عملیات سریع</div>
                <div class="actions-grid">
                    <a href="invoice.php" class="action-btn"><i class="icon-list-alt"></i><span>سفارشات</span></a>
                    <a href="user.php" class="action-btn"><i class="icon-users"></i><span>کاربران</span></a>
                    <a href="product.php" class="action-btn"><i class="icon-archive"></i><span>محصولات</span></a>
                    <a href="inbound.php" class="action-btn"><i class="icon-sitemap"></i><span>ورودی‌ها</span></a>
                    <a href="payment.php" class="action-btn"><i class="icon-credit-card"></i><span>پرداخت‌ها</span></a>
                    <a href="cancelService.php" class="action-btn danger"><i class="icon-trash"></i><span>حذف سرویس</span></a>
                </div>
            </div>

        </section>
        <footer id="footer">
            &copy; <?php echo date('Y'); ?> پنل مدیریت حرفه‌ای. تمامی حقوق محفوظ است.
        </footer>
    </main>

</section>

<!-- JS Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js"></script>
<script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

<script>
// --- Chart.js Global Configuration ---
Chart.defaults.font.family = 'Vazirmatn';
Chart.defaults.color = '#94a3b8';
Chart.defaults.plugins.legend.labels.boxWidth = 12;
Chart.defaults.plugins.legend.labels.padding = 15;
Chart.defaults.plugins.tooltip.rtl = true;
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(11, 17, 33, 0.9)';

// --- PHP Data to JS ---
const chartData = {
    sales: {
        labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>,
        amount: <?php echo json_encode($salesAmount); ?>
    },
    status: {
        labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>,
        data: <?php echo json_encode($statusData); ?>,
        colors: <?php echo json_encode($statusColors); ?>
    },
    users: {
        labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>,
        counts: <?php echo json_encode($userCounts); ?>
    }
};

// --- Vue.js Application for Interactivity ---
if (window.Vue) {
    const { createApp, ref, reactive, watch, onMounted } = Vue;

    createApp({
        setup() {
            // State
            const show = reactive({
                sales: true,
                status: true,
                users: true,
                ...JSON.parse(localStorage.getItem('dashboard_prefs') || '{}')
            });
            
            const charts = {
                sales: null,
                status: null,
                users: null
            };

            // Watch for changes in 'show' state to save to localStorage and toggle charts
            watch(show, (newShow) => {
                localStorage.setItem('dashboard_prefs', JSON.stringify(newShow));
                updateChartVisibilityAndLayout(newShow);
            });

            const updateChartVisibilityAndLayout = (currentShowState) => {
                const chartsArea = document.getElementById('chartsArea');
                const visibleCharts = Object.keys(currentShowState).filter(key => currentShowState[key]);
                
                chartsArea.style.display = visibleCharts.length > 0 ? 'grid' : 'none';

                Object.keys(charts).forEach(key => {
                    const container = document.getElementById(`${key}ChartContainer`);
                    if (!container) return;

                    if (currentShowState[key]) {
                        container.style.display = 'flex';
                        if (!charts[key]) {
                             // Render chart if it's the first time it's shown
                            renderChart(key);
                        }
                    } else {
                        container.style.display = 'none';
                    }
                });

                // Adjust grid layout based on visible charts
                adjustGridLayout(visibleCharts);
            };

            const adjustGridLayout = (visibleKeys) => {
                const salesEl = document.getElementById('salesChartContainer');
                const statusEl = document.getElementById('statusChartContainer');
                const usersEl = document.getElementById('usersChartContainer');

                // Reset styles
                [salesEl, statusEl, usersEl].forEach(el => el && (el.style.gridColumn = ''));

                if(visibleKeys.includes('sales')) {
                    salesEl.style.gridColumn = '1 / -1'; // Full width
                    if(visibleKeys.includes('status') && visibleKeys.includes('users')) {
                        statusEl.style.gridColumn = 'span 4'; // 1/3 width
                        usersEl.style.gridColumn = 'span 8'; // 2/3 width
                    } else if(visibleKeys.includes('status')) {
                        statusEl.style.gridColumn = '1 / -1';
                    } else if(visibleKeys.includes('users')) {
                        usersEl.style.gridColumn = '1 / -1';
                    }
                } else {
                    if(visibleKeys.includes('status') && visibleKeys.includes('users')) {
                        statusEl.style.gridColumn = 'span 5';
                        usersEl.style.gridColumn = 'span 7';
                    } else if (visibleKeys.length === 1) {
                         document.getElementById(`${visibleKeys[0]}ChartContainer`).style.gridColumn = '1 / -1';
                    }
                }
            };
            
            const renderChart = (key) => {
                const ctx = document.getElementById(`${key}Chart`)?.getContext('2d');
                if (!ctx) return;

                switch (key) {
                    case 'sales':
                        const gradSales = ctx.createLinearGradient(0, 0, 0, 300);
                        gradSales.addColorStop(0, 'rgba(79, 70, 229, 0.6)');
                        gradSales.addColorStop(1, 'rgba(79, 70, 229, 0.05)');
                        charts.sales = new Chart(ctx, {
                            type: 'bar',
                            data: { labels: chartData.sales.labels, datasets: [{ label: 'فروش (تومان)', data: chartData.sales.amount, backgroundColor: gradSales, borderColor: '#4f46e5', borderWidth: 2, borderRadius: 8 }] },
                            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.08)' }, ticks: { callback: (v) => v >= 1e6 ? `${v / 1e6}م` : (v >= 1e3 ? `${v / 1e3}ه` : v) } }, x: { grid: { display: false } } } }
                        });
                        break;
                    case 'status':
                        charts.status = new Chart(ctx, {
                            type: 'doughnut',
                            data: { labels: chartData.status.labels, datasets: [{ data: chartData.status.data, backgroundColor: chartData.status.colors, borderColor: 'var(--bg-body)', borderWidth: 5 }] },
                            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'right' } } }
                        });
                        break;
                    case 'users':
                        const gradUsers = ctx.createLinearGradient(0, 0, 0, 300);
                        gradUsers.addColorStop(0, 'rgba(6, 182, 212, 0.4)');
                        gradUsers.addColorStop(1, 'rgba(6, 182, 212, 0)');
                        charts.users = new Chart(ctx, {
                            type: 'line',
                            data: { labels: chartData.users.labels, datasets: [{ label: 'کاربر جدید', data: chartData.users.counts, borderColor: '#06b6d4', backgroundColor: gradUsers, fill: true, tension: 0.4, pointBackgroundColor: '#06b6d4', pointRadius: 4 }] },
                            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.08)' }, ticks: { precision: 0 } }, x: { grid: { display: false } } } }
                        });
                        break;
                }
            };

            onMounted(() => {
                // Initialize Date Range Picker
                const $input = $('#rangePicker');
                const start = chartData.from ? moment(chartData.from) : moment().subtract(13, 'days');
                const end = chartData.to ? moment(chartData.to) : moment();

                const cb = (start, end) => {
                    const text = start.format('YYYY/MM/DD') + '  تا  ' + end.format('YYYY/MM/DD');
                    $input.val(text);
                    $('#rangeFrom').val(start.format('YYYY-MM-DD'));
                    $('#rangeTo').val(end.format('YYYY-MM-DD'));
                };

                $input.daterangepicker({ startDate: start, endDate: end, opens: 'right', locale: { format: 'YYYY/MM/DD', applyLabel: 'تایید', cancelLabel: 'لغو' } }, cb);
                if (!chartData.from) $input.val('');

                // Initial setup
                updateChartVisibilityAndLayout(show);
                window.addEventListener('resize', () => updateChartVisibilityAndLayout(show));
            });

            return { show };
        }
    }).mount('#dashboard-controls');
}

</script>
</body>
</html>