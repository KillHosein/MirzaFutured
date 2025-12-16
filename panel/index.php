<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';

// --- Logic Section (Unchanged) ---
$datefirstday = time() - 86400;
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];
if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// Check Admin
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);
if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    return;
}

// Filter Logic
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
    $ph = [];
    foreach($selectedStatuses as $i => $st){
        $k = ":st$i";
        $ph[] = $k;
        $invoiceParams[$k] = $st;
    }
    $invoiceWhere[] = "status IN (".implode(',', $ph).")";
}else{
    $invoiceWhere[] = "(status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold')";
}

$invoiceWhereSql = implode(' AND ', $invoiceWhere);

// Total Sales Amount
$query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql");
$query->execute($invoiceParams);
$subinvoice = $query->fetch(PDO::FETCH_ASSOC);

// User Counts
$query = $pdo->prepare("SELECT * FROM user");
$query->execute();
$resultcount = $query->rowCount();

$time = strtotime(date('Y/m/d'));
$stmt = $pdo->prepare("SELECT * FROM user WHERE register > :time_register AND register != 'none'");
$stmt->bindParam(':time_register', $datefirstday);
$stmt->execute();
$resultcountday = $stmt->rowCount();

// Sales Count
$query = $pdo->prepare("SELECT  * FROM invoice WHERE $invoiceWhereSql");
$query->execute($invoiceParams);
$resultcontsell = $query->rowCount();

$subinvoice['SUM(price_product)'] = number_format($subinvoice['SUM(price_product)']);

// Chart Data: Sales
$salesData = [];
$grouped_data = [];
$max_amount = 1;

if($resultcontsell != 0){
    $query = $pdo->prepare("SELECT time_sell,price_product FROM invoice WHERE $invoiceWhereSql ORDER BY time_sell DESC;");
    $query->execute($invoiceParams);
    $salesData = $query->fetchAll();

    foreach ($salesData as $sell){
        if(count($grouped_data) > 15) break;
        if(!is_numeric($sell['time_sell'])) continue;
        
        $time = date('Y/m/d',$sell['time_sell']);
        $price = (int)$sell['price_product'];
        
        if (!isset($grouped_data[$time])) {
            $grouped_data[$time] = ['total_amount' => 0, 'order_count' => 0];
        }
        $grouped_data[$time]['total_amount'] += $price;
        $grouped_data[$time]['order_count'] += 1;
    }
    $max_amount = max(array_map(function($info) { return $info['total_amount']; }, $grouped_data)) ?: 1;
}

// Chart Data: Status
$statusLabels = [];
$statusData = [];
$statusColors = [];

$stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
$stmt->execute($invoiceParams);
$statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusMapFa = [
    'unpaid' => 'در انتظار پرداخت',
    'active' => 'فعال',
    'disabledn' => 'ناموجود',
    'end_of_time' => 'پایان زمان',
    'end_of_volume' => 'پایان حجم',
    'sendedwarn' => 'هشدار',
    'send_on_hold' => 'در انتظار اتصال',
    'removebyuser' => 'حذف توسط کاربر'
];
$colorMap = [
    'unpaid' => '#f59e0b',     // Amber
    'active' => '#10b981',     // Emerald
    'disabledn' => '#6b7280',  // Gray
    'end_of_time' => '#ef4444',// Red
    'end_of_volume' => '#3b82f6', // Blue
    'sendedwarn' => '#8b5cf6', // Violet
    'send_on_hold' => '#f97316', // Orange
    'removebyuser' => '#9ca3af' // Light Gray
];

foreach($statusRows as $r){
    $k = $r['status'];
    $statusLabels[] = isset($statusMapFa[$k]) ? $statusMapFa[$k] : $k;
    $statusData[] = (int)$r['cnt'];
    $statusColors[] = isset($colorMap[$k]) ? $colorMap[$k] : '#999999';
}

// Chart Data: New Users Trend
$daysBack = 14;
$userStart = ($fromDate && strtotime($fromDate)) ? strtotime($fromDate) : (strtotime(date('Y/m/d')) - ($daysBack-1)*86400);
$userEnd = ($toDate && strtotime($toDate)) ? strtotime($toDate.' 23:59:59') : strtotime(date('Y/m/d'));
$daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);

$stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register BETWEEN :ustart AND :uend");
$stmt->bindParam(':ustart',$userStart,PDO::PARAM_INT);
$stmt->bindParam(':uend',$userEnd,PDO::PARAM_INT);
$stmt->execute();
$regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userLabels = [];
$userCounts = [];
$indexByDate = [];

for($i=0;$i<$daysBack;$i++){
    $d = strtotime(date('Y/m/d', $userStart + $i*86400));
    $key = date('Y/m/d',$d);
    $indexByDate[$key] = count($userLabels);
    $userLabels[] = jdate('Y/m/d',$d);
    $userCounts[] = 0;
}

foreach($regRows as $row){
    if(!is_numeric($row['register'])) continue;
    $key = date('Y/m/d', (int)$row['register']);
    if(isset($indexByDate[$key])){
        $userCounts[$indexByDate[$key]]++;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت ربات میرزا</title>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    
    <!-- Bootstrap core CSS (Keep existing if needed for header.php, but we override heavily) -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --accent: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --font-main: 'Vazirmatn', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: var(--font-main);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Layout Override --- */
        #container { width: 100%; height: 100%; }
        #main-content { margin-right: 0px; /* Adjust based on your header.php sidebar width if needed */ padding-top: 60px; transition: all 0.3s; }
        .wrapper { padding: 20px; display: flex; flex-direction: column; gap: 24px; }

        /* --- Custom Scrollbar --- */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* --- Cards & Glassmorphism --- */
        .modern-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-color: rgba(255,255,255,0.15);
        }

        /* --- Typography --- */
        h1, h2, h3, h4, h5 { margin: 0; font-weight: 700; color: #fff; }
        .text-muted { color: var(--text-muted) !important; }

        /* --- Filter Section --- */
        .filter-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .filter-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: var(--bg-card); padding: 10px; border-radius: 12px; border: 1px solid var(--border); }
        
        .form-control-modern {
            background: #0f172a;
            border: 1px solid #334155;
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-family: var(--font-main);
            outline: none;
            transition: 0.2s;
        }
        .form-control-modern:focus { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-glow); }

        .btn-modern {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none !important;
        }
        .btn-modern:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid #334155; color: var(--text-muted); }
        .btn-outline:hover { background: #334155; color: #fff; }

        /* --- Stat Cards --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .stat-item { display: flex; align-items: center; gap: 15px; }
        .stat-icon {
            width: 56px; height: 56px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            background: rgba(255,255,255,0.05);
        }
        .stat-icon.blue { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .stat-icon.green { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .stat-icon.purple { color: #a855f7; background: rgba(168, 85, 247, 0.1); }
        .stat-icon.orange { color: #f97316; background: rgba(249, 115, 22, 0.1); }
        
        .stat-info h2 { font-size: 24px; margin-bottom: 4px; }
        .stat-info p { font-size: 14px; color: var(--text-muted); margin: 0; }

        /* --- Charts Grid --- */
        .charts-section { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 992px) { .charts-section { grid-template-columns: 1fr; } }
        
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .chart-title { font-size: 16px; font-weight: 600; color: var(--text-muted); }
        
        /* --- Action Grid --- */
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; }
        .action-card {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; padding: 25px 15px;
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
            color: var(--text-main); text-decoration: none;
            transition: 0.3s; position: relative; overflow: hidden;
        }
        .action-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            opacity: 0; transition: 0.3s;
        }
        .action-card:hover { background: #263346; transform: translateY(-4px); }
        .action-card:hover::before { opacity: 1; }
        .action-card i { font-size: 32px; margin-bottom: 12px; color: var(--text-muted); transition: 0.3s; }
        .action-card:hover i { color: var(--primary); transform: scale(1.1); }
        .action-card span { font-weight: 500; font-size: 15px; }

        /* --- Footer/Prefs --- */
        .prefs-box { margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px; }
        .checkbox-modern { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted); }
        .checkbox-modern input { accent-color: var(--primary); width: 16px; height: 16px; }

    </style>
</head>

<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <section class="wrapper">
            
            <!-- 1. Header & Filters -->
            <div class="filter-header">
                <div>
                    <h3 style="margin-bottom: 5px;">داشبورد مدیریت</h3>
                    <span class="text-muted small">نمای کلی وضعیت ربات و فروش‌ها</span>
                </div>
                
                <form class="filter-form" method="get" id="dashboardFilterForm">
                    <!-- Date Picker -->
                    <div style="position: relative;">
                        <input type="text" id="rangePicker" class="form-control-modern" placeholder="انتخاب بازه زمانی" style="min-width: 200px; text-align: center;">
                        <i class="icon-calendar" style="position: absolute; left: 10px; top: 10px; color: #64748b;"></i>
                    </div>
                    <input type="hidden" name="from" id="rangeFrom" value="<?php echo htmlspecialchars($fromDate ?? '', ENT_QUOTES); ?>">
                    <input type="hidden" name="to" id="rangeTo" value="<?php echo htmlspecialchars($toDate ?? '', ENT_QUOTES); ?>">

                    <!-- Status Select -->
                    <select name="status[]" multiple class="form-control-modern" style="min-width: 150px; height: 38px;">
                        <?php foreach($statusMapFa as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo in_array($sk, $selectedStatuses) ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-modern">
                        <i class="icon-filter"></i> اعمال
                    </button>
                    <a href="index.php" class="btn-modern btn-outline" title="پاک کردن فیلترها">
                        <i class="icon-refresh"></i>
                    </a>
                    
                    <!-- Quick Presets -->
                    <div class="btn-group" style="margin-right: 10px;">
                        <button class="btn-modern btn-outline btn-sm" id="preset7d" style="font-size: 12px; padding: 5px 10px;">هفته</button>
                        <button class="btn-modern btn-outline btn-sm" id="presetMonth" style="font-size: 12px; padding: 5px 10px;">ماه</button>
                    </div>
                </form>
            </div>

            <!-- 2. Statistics Cards -->
            <div class="stats-grid">
                <!-- Total Sales -->
                <div class="modern-card stat-item">
                    <div class="stat-icon blue"><i class="icon-bar-chart"></i></div>
                    <div class="stat-info">
                        <h2><?php echo $subinvoice['SUM(price_product)']; ?> <span style="font-size:14px; opacity:0.7">تومان</span></h2>
                        <p>جمع کل فروش فیلتر شده</p>
                    </div>
                </div>
                
                <!-- Sales Count -->
                <div class="modern-card stat-item">
                    <div class="stat-icon purple"><i class="icon-shopping-cart"></i></div>
                    <div class="stat-info">
                        <h2><?php echo number_format($resultcontsell); ?></h2>
                        <p>تعداد فاکتورهای فروش</p>
                    </div>
                </div>

                <!-- Total Users -->
                <div class="modern-card stat-item">
                    <div class="stat-icon orange"><i class="icon-group"></i></div>
                    <div class="stat-info">
                        <h2><?php echo number_format($resultcount); ?></h2>
                        <p>کاربران کل ربات</p>
                    </div>
                </div>

                <!-- New Users Today -->
                <div class="modern-card stat-item">
                    <div class="stat-icon green"><i class="icon-user"></i></div>
                    <div class="stat-info">
                        <h2><?php echo number_format($resultcountday); ?></h2>
                        <p>کاربران جدید (۲۴ ساعت)</p>
                    </div>
                </div>
            </div>

            <!-- 3. Charts Area -->
            <div class="charts-section" id="chartsArea">
                <!-- Sales Chart (Main) -->
                <?php if($resultcontsell != 0): ?>
                <div class="modern-card" style="grid-column: span 2;" v-show="show.sales">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-graph"></i> نمودار فروش روزانه</span>
                    </div>
                    <div style="height: 300px; position: relative;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Status Doughnut -->
                <div class="modern-card" v-show="show.status">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-pie-chart"></i> وضعیت سفارشات</span>
                    </div>
                    <div style="height: 250px; position: relative; display: flex; justify-content: center;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- New Users Line -->
                <div class="modern-card" v-show="show.users">
                    <div class="chart-header">
                        <span class="chart-title"><i class="icon-user-md"></i> روند ثبت نام کاربران</span>
                    </div>
                    <div style="height: 250px; position: relative;">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 4. Quick Actions Grid -->
            <h4 style="margin: 20px 0 10px 0; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                <i class="icon-bolt"></i> دسترسی سریع
            </h4>
            <div class="actions-grid">
                <a href="invoice.php" class="action-card">
                    <i class="icon-list-alt"></i>
                    <span>مدیریت سفارشات</span>
                </a>
                <a href="user.php" class="action-card">
                    <i class="icon-user"></i>
                    <span>مدیریت کاربران</span>
                </a>
                <a href="product.php" class="action-card">
                    <i class="icon-archive"></i>
                    <span>محصولات</span>
                </a>
                <a href="inbound.php" class="action-card">
                    <i class="icon-exchange"></i>
                    <span>ورودی‌ها</span>
                </a>
                <a href="payment.php" class="action-card">
                    <i class="icon-credit-card"></i>
                    <span>پرداخت‌ها</span>
                </a>
                <a href="cancelService.php" class="action-card" style="border-color: rgba(239, 68, 68, 0.3);">
                    <i class="icon-trash" style="color: var(--danger);"></i>
                    <span style="color: var(--danger);">حذف سرویس</span>
                </a>
                <a href="keyboard.php" class="action-card">
                    <i class="icon-th"></i>
                    <span>کیبورد</span>
                </a>
                <a href="productedit.php" class="action-card">
                    <i class="icon-edit"></i>
                    <span>ویرایش سریع</span>
                </a>
            </div>

            <!-- 5. Dashboard Preferences (Vue) -->
            <div class="prefs-box modern-card" id="dashPrefs" style="background: transparent; box-shadow: none; border: none; padding: 0;">
                <div class="row">
                    <div class="col-md-12 text-muted" style="margin-bottom: 10px; font-size: 13px;">نمایش/مخفی‌سازی نمودارها:</div>
                    <div class="col-sm-4"><label class="checkbox-modern"><input type="checkbox" v-model="show.status"> وضعیت سفارشات</label></div>
                    <div class="col-sm-4"><label class="checkbox-modern"><input type="checkbox" v-model="show.users"> کاربران جدید</label></div>
                    <div class="col-sm-4"><label class="checkbox-modern"><input type="checkbox" v-model="show.sales"> نمودار فروش</label></div>
                </div>
            </div>

        </section>
    </section>
</section>

<!-- JS Scripts -->
<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.scrollTo.min.js"></script>
<script src="js/jquery.nicescroll.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
<script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
<script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>
<script src="js/common-scripts.js"></script>

<!-- DatePicker Logic -->
<script>
$(function(){
    var from = $('#rangeFrom').val();
    var to = $('#rangeTo').val();
    var $input = $('#rangePicker');
    
    var start = from ? moment(from) : moment();
    var end = to ? moment(to) : moment();

    function cb(start, end) {
        $input.val(start.format('YYYY-MM-DD') + ' تا ' + end.format('YYYY-MM-DD'));
        $('#rangeFrom').val(start.format('YYYY-MM-DD'));
        $('#rangeTo').val(end.format('YYYY-MM-DD'));
    }

    $input.daterangepicker({
        startDate: start,
        endDate: end,
        opens: 'left',
        locale: { format: 'YYYY-MM-DD', separator: ' تا ', applyLabel: 'تایید', cancelLabel: 'لغو' }
    }, cb);

    if(from && to) { cb(start, end); }

    // Quick presets
    $('#preset7d').click(function(e){ e.preventDefault(); $('#rangeFrom').val(moment().subtract(6, 'days').format('YYYY-MM-DD')); $('#rangeTo').val(moment().format('YYYY-MM-DD')); $('#dashboardFilterForm').submit(); });
    $('#presetMonth').click(function(e){ e.preventDefault(); $('#rangeFrom').val(moment().startOf('month').format('YYYY-MM-DD')); $('#rangeTo').val(moment().endOf('month').format('YYYY-MM-DD')); $('#dashboardFilterForm').submit(); });
});
</script>

<!-- Vue & Chart.js Logic -->
<script>
(function(){
    // Setup Chart Defaults
    Chart.defaults.font.family = 'Vazirmatn';
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';

    // --- Data Preparation (PHP to JS) ---
    <?php if($resultcontsell != 0): ?>
    var salesLabels = <?php echo json_encode(array_values(array_map(function($d){ return jdate('Y/m/d', strtotime($d)); }, array_keys($grouped_data))), JSON_UNESCAPED_UNICODE); ?>;
    var salesAmount = <?php echo json_encode(array_values(array_map(function($i){ return $i['total_amount']; }, $grouped_data))); ?>;
    var salesCount = <?php echo json_encode(array_values(array_map(function($i){ return $i['order_count']; }, $grouped_data))); ?>;
    <?php else: ?>
    var salesLabels = [], salesAmount = [], salesCount = [];
    <?php endif; ?>

    var statusLabels = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>;
    var statusData = <?php echo json_encode($statusData); ?>;
    var statusColors = <?php echo json_encode($statusColors); ?>;

    var userLabels = <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>;
    var userCounts = <?php echo json_encode($userCounts); ?>;

    // --- Vue App ---
    const app = Vue.createApp({
        data() {
            return {
                show: JSON.parse(localStorage.getItem('dash_show') || '{"status":true,"users":true,"sales":true}')
            }
        },
        watch: {
            show: {
                deep: true,
                handler(v) { localStorage.setItem('dash_show', JSON.stringify(v)); }
            }
        },
        mounted() {
            this.initCharts();
        },
        methods: {
            initCharts() {
                // 1. Sales Chart
                if(document.getElementById('salesChart')) {
                    new Chart(document.getElementById('salesChart'), {
                        type: 'bar',
                        data: {
                            labels: salesLabels,
                            datasets: [
                                {
                                    type: 'bar',
                                    label: 'مبلغ فروش (تومان)',
                                    data: salesAmount,
                                    backgroundColor: 'rgba(99, 102, 241, 0.5)', // Primary Color
                                    borderColor: '#6366f1',
                                    borderWidth: 1,
                                    borderRadius: 6,
                                    yAxisID: 'y'
                                },
                                {
                                    type: 'line',
                                    label: 'تعداد سفارش',
                                    data: salesCount,
                                    borderColor: '#06b6d4', // Accent Color
                                    backgroundColor: 'rgba(6, 182, 212, 0.2)',
                                    borderWidth: 2,
                                    tension: 0.4,
                                    yAxisID: 'y1'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top' },
                                tooltip: {
                                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                    titleFont: { size: 13 },
                                    bodyFont: { size: 12 },
                                    padding: 10,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) label += ': ';
                                            if (context.parsed.y !== null) label += context.parsed.y.toLocaleString('fa-IR');
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { type: 'linear', display: true, position: 'left', grid: { color: 'rgba(255,255,255,0.05)' } },
                                y1: { type: 'linear', display: false, position: 'right', grid: { drawOnChartArea: false } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }

                // 2. Status Chart
                if(document.getElementById('statusChart')) {
                    new Chart(document.getElementById('statusChart'), {
                        type: 'doughnut',
                        data: {
                            labels: statusLabels,
                            datasets: [{
                                data: statusData,
                                backgroundColor: statusColors,
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: { position: 'right', labels: { boxWidth: 12, padding: 15 } }
                            }
                        }
                    });
                }

                // 3. Users Chart
                if(document.getElementById('usersChart')) {
                    new Chart(document.getElementById('usersChart'), {
                        type: 'line',
                        data: {
                            labels: userLabels,
                            datasets: [{
                                label: 'کاربران جدید',
                                data: userCounts,
                                borderColor: '#10b981',
                                backgroundColor: (ctx) => {
                                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 250);
                                    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
                                    gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                                    return gradient;
                                },
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2,
                                pointRadius: 0,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } },
                                y: { grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
                            }
                        }
                    });
                }
            }
        }
    });
    app.mount('#dashPrefs');
    
    // Mount app to body for v-show directives if needed, but since v-show is outside #dashPrefs container in HTML structure:
    // We need to wrap the main content or use a global mounting point. 
    // To keep it simple and working with the PHP structure, we used a specific ID for prefs.
    // However, to make v-show work on charts based on prefs, we need to apply the logic manually or wrap a larger area.
    // FIX: Let's simply hook the toggle logic to the charts visibility directly in the watcher above for simplicity in non-SPA pages.
    
    // Re-bind watcher for direct DOM manipulation since Vue is only on #dashPrefs
    app.$watch('show', function(newVal){
        const toggle = (id, show) => {
            const el = document.getElementById(id); 
            if(el) el.closest('.modern-card').style.display = show ? 'block' : 'none';
        };
        toggle('statusChart', newVal.status);
        toggle('usersChart', newVal.users);
        toggle('salesChart', newVal.sales);
    }, {deep: true, immediate: true});

})();
</script>

</body>
</html>