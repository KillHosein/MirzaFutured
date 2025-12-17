<?php
/**
 * Professional Admin Dashboard
 * Optimized for Security, Performance, and UX
 */

// 1. Security & Configuration
// ---------------------------------------------------------
// در محیط واقعی، display_errors باید 0 باشد تا مسیر فایل‌ها لو نرود.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); 

session_start();

// تنظیم منطقه زمانی
date_default_timezone_set('Asia/Tehran');

// بارگذاری فایل‌های وابسته با بررسی وجود فایل
$dependencies = ['../config.php', '../jdf.php'];
foreach ($dependencies as $file) {
    if (file_exists($file)) require_once $file;
}

// تابع کمکی برای فرمت تاریخ (پشتیبانی از شمسی و میلادی)
function format_date($timestamp, $format = 'Y/m/d') {
    if (function_exists('jdate')) {
        return jdate($format, $timestamp);
    }
    return date($format, $timestamp);
}

// 2. Authentication Check
// ---------------------------------------------------------
if (!isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

// بررسی اتصال دیتابیس
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // اگر فایل کانفیگ مشکل داشته باشد یا متصل نباشد
    die('<div style="direction:rtl; text-align:center; padding:50px; font-family:tahoma;">خطا در اتصال به پایگاه داده. لطفاً فایل config.php را بررسی کنید.</div>');
}

// بررسی اعتبار مدیر
try {
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE username=:username LIMIT 1");
    $stmt->execute(['username' => $_SESSION["user"]]);
    if (!$stmt->fetch()) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Login Check Error: " . $e->getMessage());
    die("Authentication Error");
}

// 3. Request Handling & Filters
// ---------------------------------------------------------
$fromDate = $_GET['from'] ?? null;
$toDate = $_GET['to'] ?? null;
$selectedStatuses = $_GET['status'] ?? [];

// اطمینان از آرایه بودن وضعیت‌ها
if (!is_array($selectedStatuses)) {
    $selectedStatuses = $selectedStatuses ? [$selectedStatuses] : [];
}

// ساخت کوئری دینامیک
$whereConditions = ["name_product != 'سرویس تست'"];
$queryParams = [];

// فیلتر تاریخ
if ($fromDate && strtotime($fromDate)) {
    $whereConditions[] = "time_sell >= :fromTs";
    $queryParams[':fromTs'] = strtotime($fromDate);
}
if ($toDate && strtotime($toDate)) {
    $whereConditions[] = "time_sell <= :toTs";
    // تا پایان روز انتخاب شده
    $queryParams[':toTs'] = strtotime($toDate . ' 23:59:59');
}

// فیلتر وضعیت
if (!empty($selectedStatuses)) {
    $statusPlaceholders = [];
    foreach ($selectedStatuses as $index => $status) {
        $ph = ":status_$index";
        $statusPlaceholders[] = $ph;
        $queryParams[$ph] = $status;
    }
    $whereConditions[] = "status IN (" . implode(', ', $statusPlaceholders) . ")";
} else {
    // وضعیت‌های پیش‌فرض برای نمایش
    $defaultStatuses = ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid'];
    $placeholders = [];
    foreach ($defaultStatuses as $i => $s) {
        $ph = ":def_status_$i";
        $placeholders[] = $ph;
        $queryParams[$ph] = $s;
    }
    $whereConditions[] = "status IN (" . implode(', ', $placeholders) . ")";
}

$whereSQL = implode(' AND ', $whereConditions);

// 4. Data Fetching
// ---------------------------------------------------------
$stats = [
    'total_sales' => 0,
    'total_invoices' => 0,
    'total_users' => 0,
    'new_users_today' => 0
];

$chartData = ['sales' => [], 'status' => [], 'users' => []];

try {
    // الف) آمار کلی فروش
    $stmt = $pdo->prepare("SELECT SUM(price_product) as total_amount, COUNT(id) as total_count FROM invoice WHERE $whereSQL AND status != 'unpaid'");
    $stmt->execute($queryParams);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_sales'] = $row['total_amount'] ?? 0;
    $stats['total_invoices'] = $row['total_count'] ?? 0;

    // ب) آمار کاربران
    $stmt = $pdo->query("SELECT COUNT(id) FROM user");
    $stats['total_users'] = $stmt->fetchColumn();

    $todayStart = strtotime('today midnight');
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM user WHERE register >= :today AND register != 'none'");
    $stmt->execute([':today' => $todayStart]);
    $stats['new_users_today'] = $stmt->fetchColumn();

    // ج) داده‌های نمودار فروش (روزانه)
    // نکته: برای بهینه‌سازی، گروه‌بندی را در PHP انجام می‌دهیم تا دیتابیس درگیر تبدیل تاریخ نشود
    $stmt = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $whereSQL AND status != 'unpaid' ORDER BY time_sell ASC");
    $stmt->execute($queryParams);
    $rawSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedSales = [];
    foreach ($rawSales as $sale) {
        if (!is_numeric($sale['time_sell'])) continue;
        $dateKey = format_date((int)$sale['time_sell'], 'Y/m/d');
        if (!isset($groupedSales[$dateKey])) $groupedSales[$dateKey] = 0;
        $groupedSales[$dateKey] += (int)$sale['price_product'];
    }
    $chartData['sales'] = $groupedSales;

    // د) داده‌های نمودار وضعیت
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM invoice WHERE $whereSQL GROUP BY status");
    $stmt->execute($queryParams);
    $chartData['status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [status => count]

    // ه) داده‌های رشد کاربر (14 روز اخیر یا بازه انتخابی)
    $userStart = ($fromDate) ? strtotime($fromDate) : strtotime('-13 days');
    $userEnd = ($toDate) ? strtotime($toDate . ' 23:59:59') : time();
    
    $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :ustart AND register <= :uend");
    $stmt->execute([':ustart' => $userStart, ':uend' => $userEnd]);
    $rawUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // پر کردن روزهای خالی با صفر
    $period = new DatePeriod(
        new DateTime('@' . $userStart),
        new DateInterval('P1D'),
        new DateTime('@' . $userEnd)
    );
    
    foreach ($period as $dt) {
        $k = format_date($dt->getTimestamp(), 'Y/m/d');
        $chartData['users'][$k] = 0;
    }
    // اضافه کردن روز آخر چون DatePeriod پایان باز است
    $kLast = format_date($userEnd, 'Y/m/d');
    if(!isset($chartData['users'][$kLast])) $chartData['users'][$kLast] = 0;

    foreach ($rawUsers as $ts) {
        if (!is_numeric($ts)) continue;
        $k = format_date((int)$ts, 'Y/m/d');
        if (isset($chartData['users'][$k])) {
            $chartData['users'][$k]++;
        }
    }

} catch (PDOException $e) {
    // خطا را لاگ کنید اما کاربر را نترسانید
    error_log("Dashboard Data Error: " . $e->getMessage());
}

// 5. View Configuration (Mappers)
// ---------------------------------------------------------
$statusConfig = [
    'active'        => ['label' => 'فعال', 'color' => '#10b981'], // Emerald
    'unpaid'        => ['label' => 'منتظر پرداخت', 'color' => '#f59e0b'], // Amber
    'end_of_time'   => ['label' => 'پایان زمان', 'color' => '#ef4444'], // Red
    'end_of_volume' => ['label' => 'پایان حجم', 'color' => '#3b82f6'], // Blue
    'sendedwarn'    => ['label' => 'هشدار ارسال شده', 'color' => '#8b5cf6'], // Violet
    'disabledn'     => ['label' => 'غیرفعال', 'color' => '#64748b'], // Slate
    'send_on_hold'  => ['label' => 'در انتظار اتصال', 'color' => '#f97316'], // Orange
    'removebyuser'  => ['label' => 'حذف شده', 'color' => '#1e293b'], // Dark
];

// Greeting Logic
$hour = date('H');
$greeting = ($hour < 12) ? "صبح بخیر" : (($hour < 17) ? "ظهر بخیر" : "عصر بخیر");
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت | پنل حرفه‌ای</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    
    <!-- Styles -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        :root {
            /* Palette: Deep Modern Navy */
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-card-hover: #334155;
            --primary: #6366f1; /* Indigo */
            --primary-rgb: 99, 102, 241;
            --accent: #38bdf8; /* Sky */
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --radius: 16px;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            overflow-x: hidden;
            margin: 0;
            background-image: radial-gradient(circle at top right, rgba(99,102,241,0.15), transparent 40%);
        }

        a { text-decoration: none; transition: 0.3s; }

        /* --- Header --- */
        .glass-header {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            position: sticky; top: 0; z-index: 100;
            display: flex; justify-content: space-between; align-items: center;
        }
        .logo-area { font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; gap: 10px; color: #fff; }
        .nav-link-custom {
            color: var(--text-muted); padding: 8px 16px; border-radius: 12px; font-size: 0.9rem;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .nav-link-custom:hover, .nav-link-custom.active { background: rgba(255,255,255,0.05); color: #fff; }
        .btn-logout { color: #f43f5e !important; }
        .btn-logout:hover { background: rgba(244, 63, 94, 0.15) !important; }

        /* --- Main Content --- */
        .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 30px; }

        .welcome-section { margin-bottom: 30px; display: flex; align-items: flex-end; justify-content: space-between; }
        .welcome-title h1 { font-size: 1.75rem; font-weight: 700; margin: 0; background: linear-gradient(90deg, #fff, #cbd5e1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .current-date { background: var(--bg-card); padding: 8px 16px; border-radius: 50px; font-size: 0.85rem; border: 1px solid var(--border); color: var(--text-muted); }

        /* --- Stats Grid --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 24px; position: relative; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5); border-color: rgba(255,255,255,0.2); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 16px; background: rgba(255,255,255,0.05);
        }
        .stat-value { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; color: #fff; }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); }
        
        /* Colors for stats */
        .st-blue .stat-icon { color: #60a5fa; background: rgba(59, 130, 246, 0.1); }
        .st-green .stat-icon { color: #34d399; background: rgba(16, 185, 129, 0.1); }
        .st-purple .stat-icon { color: #a78bfa; background: rgba(139, 92, 246, 0.1); }
        .st-orange .stat-icon { color: #fb923c; background: rgba(249, 115, 22, 0.1); }

        /* --- Quick Actions --- */
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .action-btn {
            background: rgba(30, 41, 59, 0.5); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 20px 10px; text-align: center; color: var(--text-muted); transition: 0.2s; display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .action-btn i { font-size: 1.4rem; transition: 0.2s; }
        .action-btn:hover { background: var(--bg-card-hover); color: #fff; border-color: var(--primary); }
        .action-btn:hover i { transform: scale(1.1); color: var(--primary); }

        /* --- Filter Bar --- */
        .filter-bar {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 15px 20px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;
        }
        .input-dark {
            background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff;
            padding: 10px 15px; border-radius: 10px; outline: none; min-width: 220px;
        }
        .input-dark:focus { border-color: var(--primary); }
        .btn-filter {
            background: var(--primary); color: #fff; border: none; padding: 10px 25px; border-radius: 10px;
            font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        .btn-filter:hover { background: #4f46e5; box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4); }

        /* --- Charts Area --- */
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 992px) { .charts-row { grid-template-columns: 1fr; } }
        
        .chart-box {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 25px; height: 100%; min-height: 350px; display: flex; flex-direction: column;
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-title { font-size: 1rem; font-weight: 700; color: #e2e8f0; display: flex; align-items: center; gap: 8px; }

        /* Custom Daterangepicker Override */
        .daterangepicker {
            background-color: var(--bg-card) !important; border-color: var(--border) !important;
            color: #fff !important; font-family: 'Vazirmatn', sans-serif !important;
        }
        .daterangepicker .calendar-table { background: transparent !important; border: none !important; }
        .daterangepicker td.off { background: transparent !important; color: #475569 !important; }
        .daterangepicker td.active, .daterangepicker td.active:hover { background-color: var(--primary) !important; }
        .daterangepicker td:hover { background-color: rgba(255,255,255,0.1) !important; }
        
        /* Animations */
        .fade-up { animation: fadeUp 0.5s ease forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .d-1 { animation-delay: 0.1s; }
        .d-2 { animation-delay: 0.2s; }
        .d-3 { animation-delay: 0.3s; }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="glass-header">
        <div class="logo-area">
            <i class="icon-dashboard" style="color: var(--primary);"></i>
            <span>پنل مدیریت</span>
        </div>
        <nav class="d-none d-md-flex">
            <a href="index.php" class="nav-link-custom active"><i class="icon-home"></i> داشبورد</a>
            <a href="invoice.php" class="nav-link-custom"><i class="icon-shopping-cart"></i> سفارشات</a>
            <a href="user.php" class="nav-link-custom"><i class="icon-users"></i> کاربران</a>
            <div style="width:1px; background:var(--border); margin: 0 10px;"></div>
            <a href="logout.php" class="nav-link-custom btn-logout"><i class="icon-signout"></i></a>
        </nav>
        <!-- Mobile Menu Toggle could go here -->
    </header>

    <div class="dashboard-container">
        
        <!-- Welcome -->
        <div class="welcome-section fade-up">
            <div class="welcome-title">
                <h1><?php echo $greeting; ?>، مدیر عزیز</h1>
                <span style="color: var(--text-muted); font-size: 0.9rem;">وضعیت سیستم در یک نگاه</span>
            </div>
            <div class="current-date">
                <i class="icon-calendar"></i> 
                <?php echo format_date(time(), 'l، j F Y'); ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions fade-up d-1">
            <a href="invoice.php" class="action-btn"><i class="icon-list-alt"></i><span>لیست سفارشات</span></a>
            <a href="user.php" class="action-btn"><i class="icon-user"></i><span>کاربران</span></a>
            <a href="product.php" class="action-btn"><i class="icon-cube"></i><span>محصولات</span></a>
            <a href="server_status.php" class="action-btn"><i class="icon-hdd"></i><span>وضعیت سرور</span></a>
            <a href="payment.php" class="action-btn"><i class="icon-credit-card"></i><span>تراکنش‌ها</span></a>
            <a href="inbound.php" class="action-btn"><i class="icon-exchange"></i><span>ورودی‌ها</span></a>
        </div>

        <!-- Filters -->
        <div class="filter-bar fade-up d-1">
            <form method="get" style="display:contents; width:100%;">
                <div style="position:relative; flex-grow:1;">
                    <i class="icon-calendar" style="position:absolute; left:15px; top:13px; color:var(--text-muted);"></i>
                    <input type="text" id="dateRange" class="input-dark" placeholder="بازه زمانی را انتخاب کنید..." readonly style="width:100%; direction: ltr; text-align: right;">
                    <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
                    <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
                </div>

                <div style="flex-grow:1;">
                    <select name="status[]" multiple class="input-dark" style="width:100%; height: 42px;">
                        <?php foreach($statusConfig as $key => $conf): ?>
                            <option value="<?php echo $key; ?>" <?php echo in_array($key, $selectedStatuses) ? 'selected' : ''; ?>>
                                <?php echo $conf['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter"><i class="icon-filter"></i> اعمال فیلتر</button>
                <?php if($fromDate || !empty($selectedStatuses)): ?>
                    <a href="index.php" class="btn-filter" style="background:rgba(255,255,255,0.1); width:auto;"><i class="icon-refresh"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="stats-grid fade-up d-2">
            <div class="stat-card st-blue">
                <div class="stat-icon"><i class="icon-money"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_sales']); ?> <span style="font-size:12px; opacity:0.7">تومان</span></div>
                <div class="stat-label">فروش کل (فیلتر شده)</div>
            </div>
            <div class="stat-card st-purple">
                <div class="stat-icon"><i class="icon-shopping-cart"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_invoices']); ?></div>
                <div class="stat-label">تعداد فاکتورها</div>
            </div>
            <div class="stat-card st-green">
                <div class="stat-icon"><i class="icon-group"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">کل کاربران</div>
            </div>
            <div class="stat-card st-orange">
                <div class="stat-icon"><i class="icon-user-md"></i></div>
                <div class="stat-value"><?php echo number_format($stats['new_users_today']); ?></div>
                <div class="stat-label">ثبت‌نام‌های امروز</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row fade-up d-3">
            <!-- Sales Chart -->
            <div class="chart-box">
                <div class="chart-header">
                    <div class="chart-title"><i class="icon-bar-chart" style="color:var(--primary)"></i> نمودار فروش</div>
                </div>
                <div style="flex-grow:1; position:relative;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Status Chart -->
            <div class="chart-box">
                <div class="chart-header">
                    <div class="chart-title"><i class="icon-pie-chart" style="color:var(--accent)"></i> وضعیت سرویس‌ها</div>
                </div>
                <div style="flex-grow:1; position:relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <!-- User Growth Chart (Full Width) -->
            <div class="chart-box" style="grid-column: 1/-1; margin-top:20px;">
                 <div class="chart-header">
                    <div class="chart-title"><i class="icon-line-chart" style="color:#10b981"></i> روند ثبت نام کاربران</div>
                </div>
                <div style="height:250px; position:relative;">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <!-- Chart.js 4+ or 3+ -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>

    <script>
        // --- Chart Configuration ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        
        // Data from PHP
        const salesData = <?php echo json_encode($chartData['sales']); ?>;
        const statusData = <?php echo json_encode($chartData['status']); ?>;
        const usersData = <?php echo json_encode($chartData['users']); ?>;
        const statusConfig = <?php echo json_encode($statusConfig); ?>;

        // 1. Sales Chart
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 400);
        gradS.addColorStop(0, 'rgba(99, 102, 241, 0.5)');
        gradS.addColorStop(1, 'rgba(99, 102, 241, 0)');

        new Chart(ctxS, {
            type: 'bar',
            data: {
                labels: Object.keys(salesData),
                datasets: [{
                    label: 'فروش (تومان)',
                    data: Object.values(salesData),
                    backgroundColor: gradS,
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    borderRadius: 4,
                    hoverBackgroundColor: '#818cf8'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#fff',
                        bodyFont: { size: 13 },
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                return new Intl.NumberFormat('fa-IR').format(context.raw) + ' تومان';
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Status Chart
        const statusKeys = Object.keys(statusData);
        const statusCounts = Object.values(statusData);
        const statusColors = statusKeys.map(k => statusConfig[k] ? statusConfig[k].color : '#ccc');
        const statusLabels = statusKeys.map(k => statusConfig[k] ? statusConfig[k].label : k);

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusColors,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, padding: 15, font: {size: 11} } }
                }
            }
        });

        // 3. Users Chart
        const ctxU = document.getElementById('usersChart').getContext('2d');
        const gradU = ctxU.createLinearGradient(0, 0, 0, 300);
        gradU.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
        gradU.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(ctxU, {
            type: 'line',
            data: {
                labels: Object.keys(usersData),
                datasets: [{
                    label: 'کاربر جدید',
                    data: Object.values(usersData),
                    borderColor: '#10b981',
                    backgroundColor: gradU,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { display: false }
                }
            }
        });

        // --- Date Picker Logic ---
        $(function(){
            // تعریف بازه‌ها (شمسی یا میلادی بسته به نیاز - اینجا لیبل‌ها فارسی است)
            var ranges = {
                'امروز': [moment(), moment()],
                'دیروز': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                '7 روز اخیر': [moment().subtract(6, 'days'), moment()],
                '30 روز اخیر': [moment().subtract(29, 'days'), moment()],
                'این ماه': [moment().startOf('month'), moment().endOf('month')]
            };

            var start = moment().subtract(29, 'days');
            var end = moment();
            
            // اگر مقادیر PHP ست شده بود
            var pFrom = '<?php echo $fromDate; ?>';
            var pTo = '<?php echo $toDate; ?>';
            if(pFrom && pTo) {
                start = moment(pFrom);
                end = moment(pTo);
            } else {
                // پاک کردن تکست باکس در حالت دیفالت
                $('#dateRange').val('');
            }

            function cb(start, end) {
                $('#dateRange').val(start.format('YYYY/MM/DD') + ' - ' + end.format('YYYY/MM/DD'));
                $('#inputFrom').val(start.format('YYYY-MM-DD'));
                $('#inputTo').val(end.format('YYYY-MM-DD'));
            }

            $('#dateRange').daterangepicker({
                startDate: start,
                endDate: end,
                ranges: ranges,
                opens: 'left',
                autoUpdateInput: false, // جلوگیری از پر شدن خودکار
                locale: {
                    format: 'YYYY/MM/DD',
                    applyLabel: "تایید",
                    cancelLabel: "لغو",
                    customRangeLabel: "انتخاب دستی",
                    daysOfWeek: ["یک", "دو", "سه", "چهار", "پنج", "جمعه", "شنبه"],
                    monthNames: ["ژانویه", "فوریه", "مارس", "آوریل", "مه", "ژوئن", "ژوئیه", "آگوست", "سپتامبر", "اکتبر", "نوامبر", "دسامبر"],
                    firstDay: 6
                }
            }, cb);

            // اعمال دستی برای نمایش اولیه اگر فیلتر ست شده بود
            if(pFrom) cb(start, end);
            
            $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
                cb(picker.startDate, picker.endDate);
            });
        });
    </script>
</body>
</html>