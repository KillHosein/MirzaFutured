<?php
// --- خطایابی و گزارش‌دهی PHP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- بارگذاری فایل‌های مورد نیاز ---
if (file_exists('../config.php')) require_once '../config.php';
if (file_exists('../jdf.php')) require_once '../jdf.php';

// --- بررسی اتصال دیتابیس (جهت جلوگیری از کرش کردن در دمو) ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // در محیط عملیاتی واقعی، این بخش را فعال کنید:
    // die("Fatal Error: Database connection variable (\$pdo) is not defined.");
}

// --- Logic Section ---
$datefirstday = time() - 86400;
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];

if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// 1. Authentication Check
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        if( !isset($_SESSION["user"]) ){
            header('Location: login.php');
            exit;
        }
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->execute(['username' => $_SESSION["user"]]); 
        $result = $query->fetch(PDO::FETCH_ASSOC);
        if(!$result ){
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Auth failed: " . $e->getMessage());
    }
}

// 2. Filter Logic
$invoiceWhere = ["name_product != 'سرویس تست'"];
$invoiceParams = [];

if($fromDate && strtotime($fromDate)){
    $invoiceWhere[] = "time_sell >= :fromTs";
    $invoiceParams[':fromTs'] = strtotime($fromDate);
}
if($toDate && strtotime($toDate)){
    $invoiceWhere[] = "time_sell <= :toTs";
    $invoiceParams[':toTs'] = strtotime($toDate.' 23:59:59');
}

if(!empty($selectedStatuses)){
    $placeholders = [];
    foreach ($selectedStatuses as $i => $status) {
        $placeholder = ":status_$i";
        $placeholders[] = $placeholder;
        $invoiceParams[$placeholder] = $status;
    }
    $invoiceWhere[] = "status IN (" . implode(', ', $placeholders) . ")";
}else{
    $invoiceWhere[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";
}

$invoiceWhereSql = implode(' AND ', $invoiceWhere);

// 3. Sales and Data Retrieval
$total_sales = 0;
$resultcount = 0;
$resultcountday = 0;
$resultcontsell = 0;
$grouped_data = [];
$statusRows = [];
$regRows = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Sales Amount
        $query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
        $query->execute($invoiceParams);
        $total_sales = $query->fetch(PDO::FETCH_ASSOC)['SUM(price_product)'] ?? 0;

        // Total Users
        $query = $pdo->prepare("SELECT COUNT(*) FROM user");
        $query->execute();
        $resultcount = $query->fetchColumn();

        // New Users Today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :time_register AND register != 'none'");
        $stmt->execute([':time_register' => $datefirstday]); 
        $resultcountday = $stmt->fetchColumn();

        // Invoice Count
        $query = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'");
        $query->execute($invoiceParams);
        $resultcontsell = $query->fetchColumn();

        // Chart: Sales Trend
        if($resultcontsell > 0){
            $query = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid' ORDER BY time_sell DESC;");
            $query->execute($invoiceParams);
            $salesData = $query->fetchAll(PDO::FETCH_ASSOC);

            foreach ($salesData as $sell){
                if(!is_numeric($sell['time_sell'])) continue; 
                $day = date('Y/m/d', (int)$sell['time_sell']);
                if (!isset($grouped_data[$day])) $grouped_data[$day] = ['total_amount' => 0];
                $grouped_data[$day]['total_amount'] += (int)$sell['price_product'];
            }
            ksort($grouped_data);
        }

        // Chart: Status
        $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
        $stmt->execute($invoiceParams);
        $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Chart: New Users Trend
        $userStart = ($fromDate && strtotime($fromDate)) ? strtotime(date('Y/m/d', strtotime($fromDate))) : (strtotime(date('Y/m/d')) - (13 * 86400));
        $userEnd = ($toDate && strtotime($toDate)) ? strtotime(date('Y/m/d', strtotime($toDate))) : strtotime(date('Y/m/d'));
        
        $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :ustart AND register <= :uend");
        $stmt->execute([':ustart' => $userStart, ':uend' => $userEnd + 86400 - 1]);
        $regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { /* Handle Error */ }
}

// Data Processing for Charts
$salesLabels = []; $salesAmount = [];
if (!empty($grouped_data)) {
    $salesLabels = array_values(array_map(function($d){ return function_exists('jdate') ? jdate('Y/m/d', strtotime($d)) : $d; }, array_keys($grouped_data)));
    $salesAmount = array_values(array_map(function($i){ return $i['total_amount']; }, $grouped_data));
}

$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت', 'active' => 'فعال', 'disabledn' => 'غیرفعال',
    'end_of_time' => 'پایان زمان', 'end_of_volume' => 'پایان حجم', 'sendedwarn' => 'هشدار',
    'send_on_hold' => 'در انتظار اتصال', 'removebyuser' => 'حذف شده'
];
// Updated Modern Color Palette
$colorMap = [
    'active' => '#10b981', 'unpaid' => '#f59e0b', 'end_of_time' => '#ef4444',
    'end_of_volume' => '#3b82f6', 'sendedwarn' => '#8b5cf6', 'disabledn' => '#64748b',
    'send_on_hold' => '#f97316', 'removebyuser' => '#1e293b'
];

$statusLabels = []; $statusData = []; $statusColors = [];
foreach($statusRows as $r){
    $statusLabels[] = $statusMapFa[$r['status']] ?? $r['status'];
    $statusData[] = (int)$r['cnt'];
    $statusColors[] = $colorMap[$r['status']] ?? '#94a3b8';
}

// User Trend Processing
$daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);
$userLabels = []; $userCounts = []; $indexByDate = [];

for($i=0;$i<$daysBack;$i++){
    $d = $userStart + $i*86400;
    $key = date('Y/m/d',$d);
    $indexByDate[$key] = count($userLabels);
    $userLabels[] = function_exists('jdate') ? jdate('Y/m/d',$d) : date('Y/m/d',$d);
    $userCounts[] = 0;
}
foreach($regRows as $row){
    if(!is_numeric($row['register'])) continue;
    $key = date('Y/m/d', (int)$row['register']);
    if(isset($indexByDate[$key])) $userCounts[$indexByDate[$key]]++;
}

// Greeting
$h = date('H');
$greeting = ($h < 12) ? "صبح بخیر" : (($h < 17) ? "ظهر بخیر" : "عصر بخیر");
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت | داشبورد</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- CSS Dependencies -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        :root {
            /* Palette: Modern Deep Space */
            --bg-body: #0b1120;
            --bg-surface: rgba(30, 41, 59, 0.4); 
            --bg-surface-hover: rgba(51, 65, 85, 0.5);
            
            --primary: #6366f1; /* Indigo 500 */
            --primary-glow: rgba(99, 102, 241, 0.4);
            --accent: #06b6d4; /* Cyan 500 */
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            
            --border-subtle: 1px solid rgba(255, 255, 255, 0.06);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.15);
            
            --radius-md: 12px;
            --radius-lg: 20px;
            --shadow-card: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(6, 182, 212, 0.1) 0px, transparent 50%);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            overflow-x: hidden;
            margin: 0; padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Header & Nav --- */
        .glass-header {
            position: sticky; top: 0; z-index: 100;
            background: rgba(11, 17, 32, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: var(--border-subtle);
            padding: 15px 40px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .brand { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .nav-items { display: flex; gap: 15px; }
        .nav-btn {
            color: var(--text-muted); text-decoration: none; padding: 8px 16px; border-radius: var(--radius-md); font-size: 14px; font-weight: 500; transition: 0.3s;
            display: flex; align-items: center; gap: 8px;
        }
        .nav-btn:hover, .nav-btn.active { background: rgba(255,255,255,0.05); color: #fff; }
        .nav-btn.logout { color: #f43f5e; }
        .nav-btn.logout:hover { background: rgba(244, 63, 94, 0.1); }

        .container-fluid { padding: 30px 40px; max-width: 1600px; margin: 0 auto; }

        /* --- Animations --- */
        .fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
        
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        /* --- Hero Section --- */
        .hero-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
        .welcome-text h1 { font-size: 28px; font-weight: 800; margin: 0; background: linear-gradient(to right, #fff, #cbd5e1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .date-badge { 
            background: rgba(255,255,255,0.03); border: var(--border-subtle); 
            padding: 8px 16px; border-radius: 50px; font-size: 13px; color: var(--text-muted); 
            display: flex; align-items: center; gap: 8px;
        }

        /* --- Quick Actions (Top Grid) --- */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-card {
            background: var(--bg-surface);
            border: var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 15px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative; overflow: hidden;
        }
        .action-card::before {
            content: ''; position: absolute; inset: 0; 
            background: linear-gradient(135deg, rgba(255,255,255,0.05), transparent);
            opacity: 0; transition: 0.3s;
        }
        .action-card:hover { 
            transform: translateY(-4px); 
            border-color: rgba(99, 102, 241, 0.5); 
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.2);
        }
        .action-card:hover::before { opacity: 1; }
        
        .action-icon-box {
            width: 45px; height: 45px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; transition: 0.3s;
        }
        /* Icon Colors */
        .ai-blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .ai-purple { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
        .ai-green { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .ai-orange { background: rgba(249, 115, 22, 0.15); color: #fb923c; }
        .ai-red { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        
        .action-label { color: var(--text-main); font-size: 13px; font-weight: 500; }

        /* --- Filters --- */
        .glass-filter {
            background: rgba(30, 41, 59, 0.3);
            border: var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 15px 20px;
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center;
            margin-bottom: 30px;
        }
        .modern-input {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 10px 15px; border-radius: 10px;
            outline: none; font-family: inherit; transition: 0.3s;
            min-width: 200px;
        }
        .modern-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); }
        
        .btn-glow {
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            color: white; border: none; padding: 10px 25px; border-radius: 10px;
            font-weight: 600; cursor: pointer; transition: 0.3s;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        .btn-glow:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5); }

        /* --- Stats Cards --- */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: var(--bg-surface);
            backdrop-filter: blur(10px);
            border: var(--border-highlight);
            border-radius: var(--radius-lg);
            padding: 25px;
            display: flex; align-items: center; gap: 20px;
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); background: var(--bg-surface-hover); border-color: rgba(255,255,255,0.25); }
        .stat-icon-lg {
            width: 60px; height: 60px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
        }
        .stat-info h3 { margin: 0; font-size: 24px; font-weight: 800; color: #fff; }
        .stat-info p { margin: 0; font-size: 13px; color: var(--text-muted); margin-top: 4px; }

        /* --- Charts Area --- */
        .charts-wrapper { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media(max-width: 1024px) { .charts-wrapper { grid-template-columns: 1fr; } }

        .chart-box {
            background: var(--bg-surface);
            border: var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 25px;
            position: relative;
        }
        .chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-head h4 { font-size: 16px; font-weight: 700; color: #e2e8f0; margin: 0; display: flex; align-items: center; gap: 10px; }
        .badge-soft { background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 6px; font-size: 11px; }

        @media (max-width: 768px) {
            .glass-header { padding: 15px 20px; }
            .nav-btn span { display: none; }
            .container-fluid { padding: 20px; }
            .hero-section { flex-direction: column; align-items: flex-start; gap: 10px; }
            .quick-actions-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <header class="glass-header">
        <div class="brand">
            <i class="icon-dashboard" style="color: var(--accent);"></i>
            <span>پنل مدیریت</span>
        </div>
        <nav class="nav-items">
            <a href="index.php" class="nav-btn active"><i class="icon-home"></i> <span>داشبورد</span></a>
            <a href="invoice.php" class="nav-btn"><i class="icon-file-text-alt"></i> <span>سفارشات</span></a>
            <a href="user.php" class="nav-btn"><i class="icon-group"></i> <span>کاربران</span></a>
            <div style="width:1px; background:rgba(255,255,255,0.1); height:24px; margin:0 5px;"></div>
            <a href="logout.php" class="nav-btn logout"><i class="icon-signout"></i></a>
        </nav>
    </header>

    <div class="container-fluid">
        
        <!-- Hero -->
        <div class="hero-section fade-in-up">
            <div class="welcome-text">
                <h1><?php echo $greeting; ?>، مدیر عزیز</h1>
                <span style="color: var(--text-muted); font-size: 14px; margin-top: 5px; display:block;">به پنل مدیریت خوش آمدید</span>
            </div>
            <div class="date-badge">
                <i class="icon-calendar" style="color: var(--primary);"></i>
                <?php echo function_exists('jdate') ? jdate('l، j F Y') : date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Quick Actions (Modern Grid) -->
        <div class="quick-actions-grid fade-in-up delay-1">
            <a href="invoice.php" class="action-card">
                <div class="action-icon-box ai-blue"><i class="icon-shopping-cart"></i></div>
                <span class="action-label">سفارشات</span>
            </a>
            <a href="user.php" class="action-card">
                <div class="action-icon-box ai-purple"><i class="icon-user"></i></div>
                <span class="action-label">کاربران</span>
            </a>
            <a href="product.php" class="action-card">
                <div class="action-icon-box ai-green"><i class="icon-cube"></i></div>
                <span class="action-label">محصولات</span>
            </a>
            <a href="inbound.php" class="action-card">
                <div class="action-icon-box ai-orange"><i class="icon-exchange"></i></div>
                <span class="action-label">ورودی‌ها</span>
            </a>
            <a href="payment.php" class="action-card">
                <div class="action-icon-box ai-blue"><i class="icon-credit-card"></i></div>
                <span class="action-label">پرداخت‌ها</span>
            </a>
            <a href="keyboard.php" class="action-card">
                <div class="action-icon-box ai-purple"><i class="icon-keyboard"></i></div>
                <span class="action-label">کیبورد</span>
            </a>
            <a href="server_status.php" class="action-card">
                <div class="action-icon-box ai-green"><i class="icon-hdd"></i></div>
                <span class="action-label">وضعیت سرور</span>
            </a>
            <a href="cancelService.php" class="action-card">
                <div class="action-icon-box ai-red"><i class="icon-ban-circle"></i></div>
                <span class="action-label">حذف سرویس</span>
            </a>
        </div>

        <!-- Filter Bar -->
        <div class="glass-filter fade-in-up delay-1">
            <form id="filterForm" method="get" style="display: contents;">
                <div style="position: relative; flex-grow: 1; max-width: 300px;">
                    <input type="text" id="dateRange" class="modern-input" placeholder="انتخاب بازه زمانی..." style="width: 100%; padding-right: 40px;" readonly>
                    <i class="icon-calendar" style="position: absolute; right: 15px; top: 12px; color: var(--text-muted);"></i>
                    <input type="hidden" name="from" id="inputFrom" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>">
                    <input type="hidden" name="to" id="inputTo" value="<?php echo htmlspecialchars($toDate ?? ''); ?>">
                </div>

                <select name="status[]" multiple class="modern-input" style="height: 42px;">
                    <?php foreach($statusMapFa as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo in_array($k, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-glow">
                    <i class="icon-filter"></i> فیلتر
                </button>
                
                <?php if($fromDate || !empty($selectedStatuses)): ?>
                    <a href="index.php" class="btn-glow" style="background: rgba(255,255,255,0.1); box-shadow: none; padding: 10px 15px;">
                        <i class="icon-refresh"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- KPI Stats -->
        <div class="stats-row fade-in-up delay-2">
            <div class="stat-card">
                <div class="stat-icon-lg ai-blue"><i class="icon-money"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_sales); ?></h3>
                    <p>فروش کل (تومان)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-lg ai-purple"><i class="icon-shopping-cart"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($resultcontsell); ?></h3>
                    <p>تعداد سفارشات</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-lg ai-green"><i class="icon-group"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($resultcount); ?></h3>
                    <p>کاربران کل</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-lg ai-orange"><i class="icon-user-md"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($resultcountday); ?></h3>
                    <p>ثبت نام امروز</p>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-wrapper fade-in-up delay-3">
            <div class="chart-box">
                <div class="chart-head">
                    <h4><i class="icon-bar-chart" style="color: var(--primary);"></i> نمودار فروش</h4>
                    <span class="badge-soft">30 روز اخیر</span>
                </div>
                <div style="height: 300px; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="chart-box" style="display: flex; flex-direction: column;">
                <div class="chart-head">
                    <h4><i class="icon-pie-chart" style="color: var(--accent);"></i> وضعیت‌ها</h4>
                </div>
                <div style="flex-grow: 1; min-height: 250px; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <div class="chart-box" style="grid-column: 1 / -1;">
                <div class="chart-head">
                    <h4><i class="icon-line-chart" style="color: #10b981;"></i> رشد کاربران</h4>
                </div>
                <div style="height: 250px; width: 100%;">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>

    <script>
        // --- Daterangepicker Logic ---
        $(function(){
            var start = moment().subtract(13, 'days');
            var end = moment();
            var phpFrom = '<?php echo $fromDate; ?>';
            var phpTo = '<?php echo $toDate; ?>';
            
            if(phpFrom && phpTo){ start = moment(phpFrom); end = moment(phpTo); }

            function cb(start, end) {
                $('#dateRange').val(start.format('YYYY/MM/DD') + ' - ' + end.format('YYYY/MM/DD'));
                $('#inputFrom').val(start.format('YYYY-MM-DD'));
                $('#inputTo').val(end.format('YYYY-MM-DD'));
            }

            $('#dateRange').daterangepicker({
                startDate: start, endDate: end, opens: 'left',
                locale: { format: 'YYYY/MM/DD', applyLabel: 'تایید', cancelLabel: 'لغو' }
            }, cb);

            if(phpFrom) cb(start, end);
        });

        // --- Chart.js Pro Config ---
        Chart.defaults.font.family = 'Vazirmatn';
        Chart.defaults.color = '#64748b';
        Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.04)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.1)';
        Chart.defaults.plugins.tooltip.padding = 12;

        // Sales Chart
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 300);
        gradS.addColorStop(0, '#6366f1'); gradS.addColorStop(1, 'rgba(99, 102, 241, 0)');

        new Chart(ctxS, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'فروش',
                    data: <?php echo json_encode($salesAmount); ?>,
                    backgroundColor: gradS,
                    borderRadius: 4,
                    barThickness: 15,
                    hoverBackgroundColor: '#818cf8'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, border: {display: false}, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusData); ?>,
                    backgroundColor: <?php echo json_encode($statusColors); ?>,
                    borderWidth: 0, hoverOffset: 8
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '75%',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 20, font: {size: 11} } } }
            }
        });

        // Users Chart
        const ctxU = document.getElementById('usersChart').getContext('2d');
        const gradU = ctxU.createLinearGradient(0, 0, 0, 250);
        gradU.addColorStop(0, 'rgba(16, 185, 129, 0.2)'); gradU.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(ctxU, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($userCounts); ?>,
                    borderColor: '#10b981',
                    backgroundColor: gradU,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4, // Smooth curve
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#fff'
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