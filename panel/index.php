<?php
// --- خطایابی و گزارش‌دهی PHP (برای پیدا کردن علت خطای 500) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// فرض می‌کنیم این فایل‌ها در دسترس هستند و حاوی توابع مورد نیازند
require_once '../config.php';
require_once '../jdf.php';

// --- بررسی حیاتی: اطمینان از تعریف متغیر اتصال به دیتابیس ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Fatal Error: Database connection variable (\$pdo) is not defined or is not a PDO object. Please check 'config.php'.");
}

// --- Logic Section ---
$datefirstday = time() - 86400; // Time yesterday (for new users calculation)
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];

if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// 1. Authentication Check
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
    die("Database Error during authentication check. Please check logs. Message: " . $e->getMessage());
}


// 2. Filter Logic for Invoices
$invoiceWhere = ["name_product != 'سرویس تست'"];
$invoiceParams = [];

// Date Filtering
if($fromDate && strtotime($fromDate)){
    $invoiceWhere[] = "time_sell >= :fromTs";
    $invoiceParams[':fromTs'] = strtotime($fromDate);
}
if($toDate && strtotime($toDate)){
    $invoiceWhere[] = "time_sell <= :toTs";
    // Adding 23:59:59 to include the entire 'to' day
    $invoiceParams[':toTs'] = strtotime($toDate.' 23:59:59');
}

// Status Filtering
if(!empty($selectedStatuses)){
    $placeholders = [];
    foreach ($selectedStatuses as $i => $status) {
        $placeholder = ":status_$i";
        $placeholders[] = $placeholder;
        $invoiceParams[$placeholder] = $status;
    }
    $invoiceWhere[] = "status IN (" . implode(', ', $placeholders) . ")";
}else{
    // Default statuses to include most relevant orders if no filter is applied
    $invoiceWhere[] = "status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'unpaid')";
}

$invoiceWhereSql = implode(' AND ', $invoiceWhere);

// 3. Sales and User Counts

try {
    // Total Sales Amount
    $query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'"); // Exclude unpaid from total sales
    $query->execute($invoiceParams);
    $subinvoice = $query->fetch(PDO::FETCH_ASSOC);
    $total_sales = $subinvoice['SUM(price_product)'] ?? 0;

    // Total User Counts (Overall)
    $query = $pdo->prepare("SELECT COUNT(*) FROM user");
    $query->execute();
    $resultcount = $query->fetchColumn();

    // New Users Today (Fix applied here in previous turn: using execute array instead of bindParam)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register >= :time_register AND register != 'none'");
    $stmt->execute([':time_register' => $datefirstday]); 
    $resultcountday = $stmt->fetchColumn();

    // Sales Count (Filtered)
    $query = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid'"); // Exclude unpaid from order count
    $query->execute($invoiceParams);
    $resultcontsell = $query->fetchColumn();
} catch (PDOException $e) {
    die("Database Error during data retrieval. Message: " . $e->getMessage());
}

$formatted_total_sales = number_format($total_sales);

// 4. Chart Data: Sales Trend
$grouped_data = [];
if($resultcontsell > 0){
    try {
        // Fetch only paid sales data for the chart
        $query = $pdo->prepare("SELECT time_sell, price_product FROM invoice WHERE $invoiceWhereSql AND status != 'unpaid' ORDER BY time_sell DESC;");
        $query->execute($invoiceParams);
        $salesData = $query->fetchAll(PDO::FETCH_ASSOC);

        foreach ($salesData as $sell){
            if(!is_numeric($sell['time_sell'])) continue; 
            
            $time_sell_day = date('Y/m/d', (int)$sell['time_sell']);
            $price = (int)$sell['price_product'];
            
            if (!isset($grouped_data[$time_sell_day])) {
                $grouped_data[$time_sell_day] = ['total_amount' => 0, 'order_count' => 0];
            }
            $grouped_data[$time_sell_day]['total_amount'] += $price;
            $grouped_data[$time_sell_day]['order_count'] += 1;
        }
        ksort($grouped_data); // Sort by date ascending for chart
    } catch (PDOException $e) {
        die("Database Error while fetching Sales Trend. Message: " . $e->getMessage());
    }
}

// Convert Gregorian dates to Persian for chart labels
$salesLabels = array_values(array_map(function($d){ 
    return jdate('Y/m/d', strtotime($d)); 
}, array_keys($grouped_data)));
$salesAmount = array_values(array_map(function($i){ return $i['total_amount']; }, $grouped_data));

// 5. Chart Data: Status Distribution
$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت',
    'active' => 'فعال',
    'disabledn' => 'غیرفعال', // Changed from 'ناموجود' to 'غیرفعال' for better context
    'end_of_time' => 'پایان زمان',
    'end_of_volume' => 'پایان حجم',
    'sendedwarn' => 'هشدار',
    'send_on_hold' => 'در انتظار اتصال',
    'removebyuser' => 'حذف شده'
];
$colorMap = [
    'unpaid' => '#fbbf24', // Amber
    'active' => '#10b981', // Emerald (Slightly darker for better contrast)
    'disabledn' => '#94a3b8', // Slate
    'end_of_time' => '#ef4444', // Red
    'end_of_volume' => '#3b82f6', // Blue
    'sendedwarn' => '#a855f7', // Violet
    'send_on_hold' => '#f97316', // Orange
    'removebyuser' => '#475569' // Dark Slate
];

try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
    $stmt->execute($invoiceParams);
    $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error while fetching Status Distribution. Message: " . $e->getMessage());
}

$statusLabels = [];
$statusData = [];
$statusColors = [];

foreach($statusRows as $r){
    $k = $r['status'];
    $statusLabels[] = isset($statusMapFa[$k]) ? $statusMapFa[$k] : $k;
    $statusData[] = (int)$r['cnt'];
    $statusColors[] = isset($colorMap[$k]) ? $colorMap[$k] : '#64748b';
}

// 6. Chart Data: New Users Trend
$userStart = ($fromDate && strtotime($fromDate)) ? strtotime(date('Y/m/d', strtotime($fromDate))) : (strtotime(date('Y/m/d')) - (13 * 86400));
$userEnd = ($toDate && strtotime($toDate)) ? strtotime(date('Y/m/d', strtotime($toDate))) : strtotime(date('Y/m/d'));
$daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);

try {
    $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register >= :ustart AND register <= :uend");
    $stmt->execute([
        ':ustart' => $userStart,
        ':uend' => $userEnd + 86400 - 1
    ]);
    $regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error while fetching New Users Trend. Message: " . $e->getMessage());
}

$userLabels = [];
$userCounts = [];
$indexByDate = [];

// Prepare labels for the time range
for($i=0;$i<$daysBack;$i++){
    $d = $userStart + $i*86400;
    $key = date('Y/m/d',$d);
    $indexByDate[$key] = count($userLabels);
    $userLabels[] = jdate('Y/m/d',$d); // Persian date label
    $userCounts[] = 0;
}

// Count registrations
foreach($regRows as $row){
    if(!is_numeric($row['register'])) continue;
    $key = date('Y/m/d', (int)$row['register']);
    if(isset($indexByDate[$key])){
        $userCounts[$indexByDate[$key]]++;
    }
}

// 7. Time Greeting Logic
$hour = date('H');
if ($hour < 12) { $greeting = "صبح بخیر"; $greetIcon = "icon-sun"; }
elseif ($hour < 17) { $greeting = "ظهر بخیر"; $greetIcon = "icon-coffee"; }
else { $greeting = "عصر بخیر"; $greetIcon = "icon-moon"; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت حرفه‌ای</title>
    
    <!-- Fonts: Vazirmatn is standard for Persian typography -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- CSS Dependencies -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
/* === Qutumcore Dark Admin (official) === */
:root{
  --bg: #0b1220;
  --panel: #0f172a;
  --panel-2: #111c33;
  --muted: #94a3b8;
  --text: #e5e7eb;
  --border: #1f2a44;
  --accent: #3b82f6;
  --accent-2:#06b6d4;
  --danger:#ef4444;
  --success:#22c55e;
  --shadow: 0 12px 40px rgba(0,0,0,.35);
  --radius: 16px;
}

*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family: Vazirmatn, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  background: radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.25), transparent 60%),
              radial-gradient(900px 500px at 10% 0%, rgba(6,182,212,.18), transparent 55%),
              var(--bg);
  color:var(--text);
}

/* scrollbar */
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:rgba(148,163,184,.25);border-radius:999px}
*::-webkit-scrollbar-thumb:hover{background:rgba(148,163,184,.35)}
*::-webkit-scrollbar-track{background:rgba(15,23,42,.35)}

/* Layout */
.layout{
  min-height:100vh;
  display:flex;
  gap:0;
}
.sidebar{
  width:280px;
  flex:0 0 280px;
  background: linear-gradient(180deg, rgba(15,23,42,.92), rgba(10,16,30,.92));
  border-left: 1px solid var(--border);
  padding:18px 14px;
  position:sticky;
  top:0;
  height:100vh;
  overflow:auto;
  backdrop-filter: blur(10px);
}
.brand{
  padding:14px 12px 10px;
  border:1px solid var(--border);
  border-radius: var(--radius);
  background: rgba(17,28,51,.55);
  box-shadow: var(--shadow);
}
.brand-title{font-size:18px;font-weight:800;letter-spacing:.2px}
.brand-sub{font-size:12px;color:var(--muted);margin-top:4px}

.side-nav{margin-top:14px;display:flex;flex-direction:column;gap:8px}
.side-nav a{
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px 12px;
  border-radius: 14px;
  color:var(--text);
  text-decoration:none;
  border:1px solid transparent;
  background: transparent;
  transition: transform .12s ease, background .12s ease, border-color .12s ease;
}
.side-nav a i{opacity:.9}
.side-nav a:hover{
  transform: translateX(-2px);
  background: rgba(59,130,246,.10);
  border-color: rgba(59,130,246,.28);
}
.side-nav a.active{
  background: rgba(59,130,246,.16);
  border-color: rgba(59,130,246,.40);
  box-shadow: 0 10px 30px rgba(59,130,246,.12);
}
.side-footer{
  margin-top:18px;
  padding-top:12px;
  border-top:1px solid var(--border);
}
.side-footer a{
  display:flex;justify-content:center;align-items:center;
  padding:10px 12px;border-radius:14px;
  text-decoration:none;color:var(--text);
  background: rgba(239,68,68,.10);
  border: 1px solid rgba(239,68,68,.25);
}
.side-footer a:hover{background: rgba(239,68,68,.16); border-color: rgba(239,68,68,.35)}

/* App (main) */
.app{
  flex:1;
  min-width:0;
  display:flex;
  flex-direction:column;
}
.topbar{
  position:sticky;
  top:0;
  z-index:20;
  background: rgba(11,18,32,.72);
  backdrop-filter: blur(12px);
  border-bottom:1px solid var(--border);
  padding:14px 18px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}
.topbar-title{font-weight:800;font-size:16px}
.topbar-actions{display:flex;align-items:center;gap:10px}
.icon-btn{
  width:40px;height:40px;border-radius:14px;
  display:inline-flex;align-items:center;justify-content:center;
  border:1px solid var(--border);
  background: rgba(15,23,42,.55);
  color:var(--text);
  cursor:pointer;
  transition: transform .12s ease, background .12s ease, border-color .12s ease;
}
.icon-btn:hover{transform: translateY(-1px); background: rgba(59,130,246,.10); border-color: rgba(59,130,246,.25)}
.icon-btn:active{transform: translateY(0px)}

#main-content{
  padding:18px;
}
.wrapper{padding:0}

/* Cards / panels */
.hero-banner,
.card,
.panel,
.box,
.well,
.content-panel,
.quick-actions,
.dashboard-card{
  background: rgba(15,23,42,.70) !important;
  border:1px solid var(--border) !important;
  border-radius: var(--radius) !important;
  box-shadow: var(--shadow);
}

/* Typography */
h1,h2,h3,h4,h5{color:var(--text)}
small,.text-muted{color:var(--muted)!important}

/* Links */
a{color:var(--accent)}
a:hover{color:#60a5fa}

/* Forms */
input,select,textarea{
  background: rgba(17,28,51,.65) !important;
  border: 1px solid var(--border) !important;
  border-radius: 14px !important;
  color: var(--text) !important;
  outline:none !important;
}
input::placeholder,textarea::placeholder{color: rgba(148,163,184,.65)}

/* Buttons */
.btn, button, .btn-glass{
  border-radius: 14px !important;
}
.btn-primary{
  background: rgba(59,130,246,.95) !important;
  border-color: rgba(59,130,246,.85) !important;
}
.btn-primary:hover{filter: brightness(1.05)}
.btn-danger{background: rgba(239,68,68,.9) !important; border-color: rgba(239,68,68,.8) !important}
.btn-success{background: rgba(34,197,94,.9) !important; border-color: rgba(34,197,94,.8) !important}

/* Tables */
table{color:var(--text)!important}
.table, .table-striped{
  background: transparent !important;
}
.table thead th{
  background: rgba(17,28,51,.70) !important;
  border-bottom: 1px solid var(--border) !important;
  color: var(--text) !important;
}
.table td, .table th{
  border-top: 1px solid rgba(31,42,68,.75) !important;
}
.table-striped>tbody>tr:nth-of-type(odd){
  background: rgba(17,28,51,.35) !important;
}

/* Footer */
#footer{
  margin: 22px 18px 18px;
  padding: 14px 16px;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: rgba(15,23,42,.55);
  color: var(--muted);
}

/* Responsive */
@media (max-width: 992px){
  .sidebar{
    position:fixed;
    right:0; /* RTL */
    transform: translateX(100%);
    transition: transform .2s ease;
    z-index:50;
  }
  .sidebar.is-open{transform: translateX(0)}
  .layout{display:block}
  .app{min-height:100vh}
}
</style>
</head>

<body>


    <!-- Header -->
    <div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-title">پنل ادمین</div>
      <div class="brand-sub">مدیریت رسمی • تم تیره</div>
    </div>

    <nav class="side-nav">
      <a href="index.php" class="active"><i class="icon-dashboard"></i> داشبورد</a>
      <a href="invoice.php"><i class="icon-list-alt"></i> سفارشات</a>
      <a href="user.php"><i class="icon-group"></i> کاربران</a>
    </nav>

    <div class="side-footer">
      <a href="logout.php"><i class="icon-off"></i> خروج</a>
    </div>
  </aside>

  <div class="app">
    <header class="topbar">
      <div class="topbar-title">داشبورد</div>
      <div class="topbar-actions">
        <button class="icon-btn" id="sidebarToggle" type="button" title="منو">
          <i class="icon-reorder"></i>
        </button>
      </div>
    </header>


    <section id="main-content">
        <section class="wrapper">
            
            <!-- Hero Section (Greeting and Date) -->
            <div class="hero-banner animate-enter">
                <div class="hero-title">
                    <h1><?php echo $greeting; ?>، مدیر عزیز</h1>
                    <div class="hero-subtitle">
                        <i class="<?php echo $greetIcon; ?>"></i>
                        <span>امروز: <?php echo jdate('l، j F Y'); ?></span>
                        <span style="margin: 0 10px; opacity: 0.3;">|</span>
                        <span>وضعیت: <span style="color: #34d399;">سیستم پایدار است</span></span>
                    </div>
                </div>
                <!-- Time Range Presets -->
                <div class="time-presets-group">
                    <button class="btn-glass" id="preset7d">۷ روز</button>
                    <button class="btn-glass" id="presetMonth">ماه اخیر</button>
                    <button class="btn-glass" id="presetYear">سال اخیر</button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar animate-enter delay-1">
                <form class="filter-inputs" method="get" id="dashboardFilterForm">
                    <!-- Date Picker Input -->
                    <div style="position: relative; flex-grow: 1; max-width: 300px;">
                        <input type="text" id="rangePicker" class="input-glass" placeholder="انتخاب محدوده تاریخ..." style="padding-right: 40px; text-align: right; width: 100%;">
                        <i class="icon-calendar" style="position: absolute; right: 15px; top: 14px; color: var(--text-muted); pointer-events: none;"></i>
                    </div>
                    <!-- Hidden fields to store date range values for submission -->
                    <input type="hidden" name="from" id="rangeFrom" value="<?php echo htmlspecialchars($fromDate ?? '', ENT_QUOTES); ?>">
                    <input type="hidden" name="to" id="rangeTo" value="<?php echo htmlspecialchars($toDate ?? '', ENT_QUOTES); ?>">

                    <!-- Status Multi-Select -->
                    <select name="status[]" multiple class="input-glass" style="height: auto; min-height: 46px; flex-grow: 1; max-width: 300px;">
                        <!-- Populate status options from PHP data -->
                        <?php foreach($statusMapFa as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo in_array($sk, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-gradient">
                            <i class="icon-filter"></i> 
                            <span>اعمال فیلتر</span>
                        </button>
                        
                        <?php if($fromDate || $toDate || !empty($selectedStatuses)): ?>
                        <!-- Reset Filter Button -->
                        <a href="index.php" class="btn-glass" title="حذف فیلترها" style="display: flex; align-items: center; justify-content: center; padding: 12px 18px;">
                            <i class="icon-refresh"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid animate-enter delay-2">
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-1"><i class="icon-bar-chart"></i></div>
                    <div class="stat-content">
                        <h3><?php echo $formatted_total_sales; ?></h3>
                        <span>مجموع فروش (تومان)</span>
                    </div>
                </div>
                
                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-2"><i class="icon-shopping-bag"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcontsell); ?></h3>
                        <span>تعداد سفارشات</span>
                    </div>
                </div>

                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-3"><i class="icon-users"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcount); ?></h3>
                        <span>کل کاربران سیستم</span>
                    </div>
                </div>

                <div class="modern-card stat-card">
                    <div class="stat-icon-wrapper icon-grad-4"><i class="icon-user-plus"></i></div>
                    <div class="stat-content">
                        <h3><?php echo number_format($resultcountday); ?></h3>
                        <span>کاربران جدید امروز</span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Preferences -->
            <div class="modern-card animate-enter delay-2" id="dashPrefs" style="padding: 15px 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <span class="text-muted" style="font-size: 15px; font-weight: 500; color: #cbd5e1;"><i class="icon-cogs"></i> نمایش نمودارها:</span>
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <!-- Checkboxes bound to Vue 'show' state -->
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.sales"> 
                        روند فروش
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.status"> 
                        توزیع وضعیت‌ها
                    </label>
                    <label class="custom-check">
                        <input type="checkbox" v-model="show.users"> 
                        جذب کاربر
                    </label>
                </div>
            </div>

            <!-- Charts Section (Dynamically controlled by Vue) -->
            <div class="charts-grid animate-enter delay-3" id="chartsArea">
                <!-- Sales Chart (Bar) -->
                <div class="chart-card modern-card" data-chart="sales" id="salesChartContainer" style="grid-column: 1 / -1; display: none;">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-bar-chart"></i> تحلیل فروش روزانه</span>
                    </div>
                    <div style="height: 350px; width: 100%;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Status Doughnut Chart -->
                <div class="chart-card modern-card" data-chart="status" id="statusChartContainer" style="grid-column: span 1; display: none;">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-pie-chart"></i> توزیع وضعیت سفارشات</span>
                    </div>
                    <div style="height: 300px; display: flex; justify-content: center; position: relative; min-width: 0;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Users Line Chart -->
                <div class="chart-card modern-card" data-chart="users" id="usersChartContainer" style="grid-column: span 2; display: none;">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-line-chart"></i> روند ثبت نام کاربران جدید</span>
                    </div>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="animate-enter delay-4">
                <div class="section-header">
                    <i class="icon-bolt" style="color: var(--accent);"></i> عملیات سریع
                </div>
                <div class="actions-grid">
                    <a href="invoice.php" class="action-btn">
                        <i class="icon-list-alt"></i>
                        <span>مشاهده سفارشات</span>
                    </a>
                    <a href="user.php" class="action-btn">
                        <i class="icon-users"></i>
                        <span>مدیریت کاربران</span>
                    </a>
                    <a href="product.php" class="action-btn">
                        <i class="icon-archive"></i>
                        <span>تعریف محصولات</span>
                    </a>
                    <a href="inbound.php" class="action-btn">
                        <i class="icon-exchange"></i>
                        <span>تعیین ورودی‌ها</span>
                    </a>
                    <a href="payment.php" class="action-btn">
                        <i class="icon-credit-card"></i>
                        <span>لیست پرداخت‌ها</span>
                    </a>
                    <a href="cancelService.php" class="action-btn danger">
                        <i class="icon-trash"></i>
                        <span>حذف سرویس</span>
                    </a>
                    <a href="keyboard.php" class="action-btn">
                        <i class="icon-th"></i>
                        <span>تنظیمات کیبورد</span>
                    </a>
                    <a href="productedit.php" class="action-btn">
                        <i class="icon-edit"></i>
                        <span>ویرایش سریع</span>
                    </a>
                </div>
            </div>

        </section>
        <footer id="footer">
            2024 &copy; پنل مدیریت حرفه‌ای. تمامی حقوق محفوظ است.
        </footer>
    </section>
</div>
</div>

<!-- Scripts -->
<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.scrollTo.min.js"></script>
<script src="js/jquery.nicescroll.js"></script>
<!-- Essential Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/vue@3"></script> 
<!-- Daterange picker dependencies -->
<script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
<script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>
<!-- The original common-scripts.js is expected to be present -->
<script src="js/common-scripts.js"></script>

<script>
$(function(){
    // Date Picker Logic
    var from = $('#rangeFrom').val();
    var to = $('#rangeTo').val();
    var $input = $('#rangePicker');
    
    // Set initial dates based on current filter or defaults (last 13 days + today)
    var start = from ? moment(from) : moment().subtract(13, 'days');
    var end = to ? moment(to) : moment();

    function cb(start, end) {
        // Update input field display (Gregorian format for submission clarity, but user sees Persian via jdf)
        // Since jdf is PHP-based, we keep moment formats for internal use
        $input.val(start.format('YYYY/MM/DD') + '  تا  ' + end.format('YYYY/MM/DD'));
        // Update hidden fields for submission
        $('#rangeFrom').val(start.format('YYYY-MM-DD'));
        $('#rangeTo').val(end.format('YYYY-MM-DD'));
    }

    $input.daterangepicker({
        startDate: start,
        endDate: end,
        opens: 'right', // Changed to right for better RTL compatibility
        locale: { format: 'YYYY/MM/DD', separator: ' - ', applyLabel: 'تایید', cancelLabel: 'لغو' }
    }, cb);

    // Initial display of dates if they were set
    if(from && to) { cb(start, end); } else { $input.val(''); }

    // Preset buttons functionality to automatically submit the form
    $('#preset7d').click(function(e){ 
        e.preventDefault(); 
        $('#rangeFrom').val(moment().subtract(6, 'days').format('YYYY-MM-DD')); 
        $('#rangeTo').val(moment().format('YYYY-MM-DD')); 
        $('#dashboardFilterForm').submit(); 
    });
    $('#presetMonth').click(function(e){ 
        e.preventDefault(); 
        $('#rangeFrom').val(moment().subtract(30, 'days').format('YYYY-MM-DD')); 
        $('#rangeTo').val(moment().format('YYYY-MM-DD')); 
        $('#dashboardFilterForm').submit(); 
    });
    $('#presetYear').click(function(e){ 
        e.preventDefault(); 
        $('#rangeFrom').val(moment().subtract(365, 'days').format('YYYY-MM-DD')); 
        $('#rangeTo').val(moment().format('YYYY-MM-DD')); 
        $('#dashboardFilterForm').submit(); 
    });
});
</script>

<script>
(function(){
    // Chart.js Global Config for Vazirmatn and Dark Theme
    Chart.defaults.font.family = 'Vazirmatn';
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.08)';

    // Data from PHP, safely encoded
    var salesLabels = <?php echo json_encode($salesLabels, JSON_UNESCAPED_UNICODE); ?>;
    var salesAmount = <?php echo json_encode($salesAmount); ?>;
    var statusLabels = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>;
    var statusData = <?php echo json_encode($statusData); ?>;
    var statusColors = <?php echo json_encode($statusColors); ?>;
    var userLabels = <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>;
    var userCounts = <?php echo json_encode($userCounts); ?>;

    const initializedCharts = new Set();
    const chartRenderers = {};
    const chartContainers = document.querySelectorAll('.chart-card');


    // 1. Render Sales Chart
    chartRenderers['sales'] = function() {
        if(initializedCharts.has('sales')) return;
        var ctx = document.getElementById('salesChart').getContext('2d');
        var grad = ctx.createLinearGradient(0, 0, 0, 300);
        grad.addColorStop(0, 'rgba(79, 70, 229, 0.5)'); // Primary Indigo
        grad.addColorStop(1, 'rgba(79, 70, 229, 0.05)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'فروش (تومان)',
                    data: salesAmount,
                    backgroundColor: grad,
                    borderColor: '#4f46e5',
                    borderWidth: 1,
                    borderRadius: 8,
                    hoverBackgroundColor: '#818cf8'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        backgroundColor: 'rgba(30, 41, 59, 0.9)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 14 },
                        callbacks: {
                            label: function(c) { 
                                return ' ' + Number(c.raw).toLocaleString('fa-IR') + ' تومان'; 
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        border: { display: false }, 
                        grid: { color: 'rgba(255,255,255,0.08)' },
                        ticks: {
                            color: '#cbd5e1',
                            callback: function(value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'م';
                                if (value >= 1000) return (value / 1000).toFixed(0) + 'هز';
                                return value;
                            }
                        }
                    },
                    x: { grid: { display: false }, ticks: { color: '#cbd5e1' } }
                }
            }
        });
        initializedCharts.add('sales');
    };

    // 2. Render Status Chart
    chartRenderers['status'] = function() {
        if(initializedCharts.has('status')) return;
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: statusColors,
                    borderWidth: 4,
                    borderColor: 'var(--bg-body)' // Border matches body background for floating effect
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { 
                        position: 'right', 
                        labels: { 
                            boxWidth: 12, 
                            padding: 15,
                            font: { size: 14 }
                        } 
                    },
                    tooltip: { rtl: true, backgroundColor: 'rgba(30, 41, 59, 0.9)' }
                }
            }
        });
        initializedCharts.add('status');
    };

    // 3. Render Users Chart
    chartRenderers['users'] = function() {
        if(initializedCharts.has('users')) return;
        var ctxU = document.getElementById('usersChart').getContext('2d');
        var gradU = ctxU.createLinearGradient(0, 0, 0, 300);
        gradU.addColorStop(0, 'rgba(6, 182, 212, 0.3)'); // Cyan
        gradU.addColorStop(1, 'rgba(6, 182, 212, 0)');

        new Chart(ctxU, {
            type: 'line',
            data: {
                labels: userLabels,
                datasets: [{
                    label: 'کاربر جدید',
                    data: userCounts,
                    borderColor: '#06b6d4',
                    backgroundColor: gradU,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#06b6d4',
                    pointBorderColor: '#1e293b',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: { rtl: true, backgroundColor: 'rgba(30, 41, 59, 0.9)' }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        border: { display: false }, 
                        padding: { top: 10, bottom: 0 }, 
                        grid: { color: 'rgba(255,255,255,0.08)' },
                        ticks: { precision: 0, color: '#cbd5e1' }
                    },
                    x: { 
                        grid: { display: true, color: 'rgba(255,255,255,0.08)' }, 
                        ticks: { maxRotation: 0, autoSkipPadding: 20, color: '#cbd5e1' } 
                    }
                }
            }
        });
        initializedCharts.add('users');
    };

    // --- Chart Visibility and Layout Logic (Vue integration) ---

    // Array of chart keys
    const chartKeys = ['sales', 'status', 'users'];

    /**
     * Toggles chart visibility and updates grid layout based on Vue state
     * @param {object} s - The 'show' state object from Vue: {sales: bool, status: bool, users: bool}
     */
    function toggleCharts(s){
        const visibleKeys = chartKeys.filter(key => s[key]);
        const activeCount = visibleKeys.length;
        const chartsArea = document.getElementById('chartsArea');
        
        // Hide/Show elements based on preference and render if needed
        chartKeys.forEach(key => {
            const el = document.getElementById(key + 'ChartContainer');
            if (el) {
                if (s[key]) {
                    el.style.display = 'flex';
                    if (chartRenderers[key]) chartRenderers[key](); // Render on first show
                } else {
                    el.style.display = 'none';
                }
                el.style.gridColumn = 'unset'; // Reset column span
            }
        });
        
        if (activeCount === 0) {
            chartsArea.style.display = 'none';
            return;
        } else {
            chartsArea.style.display = 'grid';
        }

        // --- Layout Adjustment for Desktop (1200px+) ---
        if (window.innerWidth > 1200) {
            const salesEl = document.getElementById('salesChartContainer');
            const statusEl = document.getElementById('statusChartContainer');
            const usersEl = document.getElementById('usersChartContainer');
            
            if (s.sales) {
                // Sales (Row 1) takes full width (span 3)
                salesEl.style.gridColumn = '1 / -1'; 
                
                // Row 2: Status and Users
                if (s.status && s.users) {
                    // Status 1/3, Users 2/3 (3 columns total)
                    statusEl.style.gridColumn = 'span 1';
                    usersEl.style.gridColumn = 'span 2';
                } else if (s.status) {
                    // Status takes full width (span 3)
                    statusEl.style.gridColumn = '1 / -1'; 
                } else if (s.users) {
                    // Users takes full width (span 3)
                    usersEl.style.gridColumn = '1 / -1'; 
                }
            } else if (activeCount > 0) {
                // Sales is hidden. Status and Users share the 3 columns.
                if (s.status && s.users) {
                    // Status 1/3, Users 2/3
                    statusEl.style.gridColumn = 'span 1';
                    usersEl.style.gridColumn = 'span 2';
                } else if (s.status) {
                    statusEl.style.gridColumn = '1 / -1';
                } else if (s.users) {
                    usersEl.style.gridColumn = '1 / -1';
                }
            }
        }
        // --- Layout Adjustment for Tablet (768px-1200px) ---
        else if (window.innerWidth > 768 && window.innerWidth <= 1200) {
            // Charts share 2 columns equally.
            visibleKeys.forEach(key => {
                const el = document.getElementById(key + 'ChartContainer');
                if (el) el.style.gridColumn = 'span 1';
            });
            // If only one chart is visible, make it full width
            if (activeCount === 1) {
                document.getElementById(visibleKeys[0] + 'ChartContainer').style.gridColumn = '1 / -1';
            }
        }
    }


    // Vue App for Preferences
    if(window.Vue) {
        var app = Vue.createApp({
            data(){ 
                const defaultPrefs = {'sales':true, 'status':true, 'users':true};
                let storedPrefs;
                try {
                    storedPrefs = JSON.parse(localStorage.getItem('dash_prefs'));
                } catch (e) {
                    storedPrefs = null;
                }
                return { 
                    // Load from localStorage or use default. Ensure all keys exist.
                    show: {...defaultPrefs, ...storedPrefs} 
                } 
            },
            watch:{ 
                show:{ 
                    deep:true, 
                    handler:function(v){ 
                        localStorage.setItem('dash_prefs', JSON.stringify(v)); 
                        toggleCharts(v); 
                    } 
                } 
            },
            mounted(){ 
                // Initial application of visibility and layout
                toggleCharts(this.show); 
                // Since we render charts on first show, no separate lazyInit is strictly needed,
                // but we keep the concept for future use or heavy charts.
                window.addEventListener('resize', () => toggleCharts(this.show));
            }
        });
        app.mount('#dashPrefs');
    } else {
        // Fallback: If Vue.js is not loaded, just render all charts initially
        chartRenderers['sales']();
        chartRenderers['status']();
        chartRenderers['users']();
        // Since Vue is not running, we set initial display inline (already done in HTML for safety)
        const salesEl = document.getElementById('salesChartContainer');
        const statusEl = document.getElementById('statusChartContainer');
        const usersEl = document.getElementById('usersChartContainer');
        if (salesEl) salesEl.style.display = 'flex';
        if (statusEl) statusEl.style.display = 'flex';
        if (usersEl) usersEl.style.display = 'flex';
    }
})();
</script>


<script>
(function(){
  var btn = document.getElementById('sidebarToggle');
  var sb  = document.getElementById('sidebar');
  if(!btn || !sb) return;

  btn.addEventListener('click', function(){
    sb.classList.toggle('is-open');
  });

  // close on outside click (mobile)
  document.addEventListener('click', function(e){
    if(window.innerWidth > 992) return;
    if(sb.classList.contains('is-open')){
      var inside = sb.contains(e.target) || btn.contains(e.target);
      if(!inside) sb.classList.remove('is-open');
    }
  });
})();
</script>

</body>
</html>