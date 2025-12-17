<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../jdf.php'; // اطمینان از وجود کتابخانه تاریخ شمسی

// بررسی احراز هویت
$q = $pdo->prepare("SELECT * FROM admin WHERE username=:u");
$q->bindParam(':u', $_SESSION['user'], PDO::PARAM_STR);
$q->execute();
$adminRow = $q->fetch(PDO::FETCH_ASSOC);
if( !isset($_SESSION["user"]) || !$adminRow ){ header('Location: login.php'); exit; }

// --- Bulk Actions ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_payment_status'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $st = $_POST['bulk_payment_status'];
    if(!empty($ids) && in_array($st,['paid','Unpaid','waiting','reject','expire'])){
        $stmt = $pdo->prepare('UPDATE Payment_report SET payment_Status = :st WHERE id_order = :id');
        foreach($ids as $id){ $stmt->execute([':st'=>$st, ':id'=>$id]); }
    }
    header('Location: payment.php');
    exit;
}

// --- Definitions ---
$statuses = [
    'paid' => ['label' => "پرداخت شده", 'color' => '#10b981', 'class' => 'status-paid'],
    'Unpaid' => ['label' => "پرداخت نشده", 'color' => '#ef4444', 'class' => 'status-unpaid'],
    'expire' => ['label' => "منقضی شده", 'color' => '#6b7280', 'class' => 'status-expire'],
    'reject' => ['label' => "رد شده", 'color' => '#f59e0b', 'class' => 'status-reject'],
    'waiting' => ['label' => "در انتظار تایید", 'color' => '#3b82f6', 'class' => 'status-waiting']
];

$methods = [
    'cart to cart' => "کارت به کارت",
    'low balance by admin' => "کسر توسط ادمین",
    'add balance by admin' => "افزایش توسط ادمین",
    'Currency Rial 1' => "درگاه ریالی 1",
    'Currency Rial tow' => "درگاه ریالی 2",
    'Currency Rial 3' => "درگاه ریالی 3",
    'aqayepardakht' => "آقای پرداخت",
    'zarinpal' => "زرین پال",
    'plisio' => "Plisio",
    'arze digital offline' => "ارز دیجیتال آفلاین",
    'Star Telegram' => "استار تلگرام",
    'nowpayment' => 'NowPayment'
];

// --- Filtering ---
$where = [];
$params = [];

if(!empty($_GET['status'])){
    $where[] = "payment_Status = :status";
    $params[':status'] = $_GET['status'];
}
if(!empty($_GET['method'])){
    $where[] = "Payment_Method = :method";
    $params[':method'] = $_GET['method'];
}
if(!empty($_GET['q'])){
    $searchTerm = '%' . $_GET['q'] . '%';
    $where[] = "(id_user LIKE :q OR id_order LIKE :q)";
    $params[':q'] = $searchTerm;
}

$sql = "SELECT * FROM Payment_report";
if(!empty($where)){
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY time DESC";

$query = $pdo->prepare($sql);
$query->execute($params);
$listpayment = $query->fetchAll();

// --- CSV Export ---
if(isset($_GET['export']) && $_GET['export'] === 'csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=payments-' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($output, ['ID User', 'Order ID', 'Price', 'Time', 'Method', 'Status']);
    foreach($listpayment as $row){
        fputcsv($output, [
            $row['id_user'],
            $row['id_order'],
            $row['price'],
            $row['time'],
            isset($methods[$row['Payment_Method']]) ? $methods[$row['Payment_Method']] : $row['Payment_Method'],
            isset($statuses[$row['payment_Status']]) ? $statuses[$row['payment_Status']]['label'] : $row['payment_Status']
        ]);
    }
    fclose($output);
    exit();
}

// --- Stats ---
$totalTx = count($listpayment);
$paidCount = 0;
$unpaidCount = 0;
$waitingCount = 0;
$totalIncome = 0;

foreach($listpayment as $p){
    if($p['payment_Status'] === 'paid'){ 
        $paidCount++; 
        $totalIncome += (float)$p['price']; 
    }
    elseif($p['payment_Status'] === 'Unpaid') $unpaidCount++;
    elseif($p['payment_Status'] === 'waiting') $waitingCount++;
}

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت تراکنش‌ها | پنل حرفه‌ای</title>
    
    <!-- Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            /* Palette: Midnight Neon */
            --bg-body: #020204;
            --bg-glass: rgba(20, 20, 25, 0.85);
            --bg-dock: rgba(10, 10, 15, 0.95);
            --bg-modal: rgba(20, 20, 25, 0.98);
            
            --neon-blue: #00f3ff;
            --neon-purple: #bc13fe;
            --neon-green: #00ff88;
            --neon-red: #ff004c;
            --neon-amber: #ffaa00;
            
            --text-main: #ffffff;
            --text-muted: #b0b0b0;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.12);
            --shadow-float: 0 15px 50px rgba(0,0,0,0.9);
            
            --radius-lg: 24px;
            --radius-md: 16px;
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
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(255, 170, 0, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(0, 243, 255, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 160px;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Animations --- */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.98) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .anim { animation: fadeInScale 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; }

        /* --- Layout --- */
        .container-fluid-custom {
            width: 100%; padding: 40px 4%; max-width: 1900px; margin: 0 auto;
        }

        /* --- Header --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 25px;
        }
        .page-title h1 {
            font-size: 3rem; font-weight: 900; margin: 0; color: #fff;
            text-shadow: 0 0 25px rgba(255,255,255,0.15);
        }
        .page-title p { color: var(--text-muted); font-size: 1.3rem; margin-top: 10px; }

        /* --- Stats Cards --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px; margin-bottom: 50px;
        }
        .stat-card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px); border: var(--border-glass);
            border-radius: var(--radius-lg); padding: 30px;
            display: flex; align-items: center; justify-content: space-between;
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-8px); border-color: rgba(255,255,255,0.3); box-shadow: 0 15px 40px rgba(0,0,0,0.6); }
        
        .stat-info .val { font-size: 2.8rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 5px; }
        .stat-info .lbl { font-size: 1.1rem; color: var(--text-muted); font-weight: 500; }
        .stat-icon { font-size: 3.5rem; opacity: 0.9; }
        
        .c-income { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0,255,136,0.3)); }
        .c-total { color: var(--neon-blue); filter: drop-shadow(0 0 10px rgba(0,243,255,0.3)); }
        .c-wait { color: var(--neon-amber); filter: drop-shadow(0 0 10px rgba(255,170,0,0.3)); }
        .c-fail { color: var(--neon-red); filter: drop-shadow(0 0 10px rgba(255,0,76,0.3)); }

        /* --- Glass Panel --- */
        .glass-panel {
            background: var(--bg-glass); backdrop-filter: blur(30px);
            border: var(--border-glass); border-radius: var(--radius-lg);
            padding: 30px; margin-bottom: 40px; box-shadow: var(--shadow-float);
        }

        /* --- Filters (High Readability) --- */
        .filters-row {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
            padding-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 30px;
        }
        .form-group { flex: 1; min-width: 250px; }
        .form-group label { display: block; color: #fff; font-size: 1rem; margin-bottom: 10px; font-weight: 700; }
        
        .input-readable {
            width: 100%; height: 60px;
            background: #000000; border: 2px solid #444; color: #ffffff;
            padding: 0 20px; border-radius: 14px; font-family: inherit; font-size: 1.2rem; font-weight: 500;
            transition: 0.3s;
        }
        .input-readable:focus { border-color: var(--neon-blue); box-shadow: 0 0 20px rgba(0, 243, 255, 0.25); }
        select.input-readable { cursor: pointer; }
        
        .btn-filter {
            height: 60px; padding: 0 45px; margin-top: 32px;
            background: var(--neon-blue); color: #000; border: none; border-radius: 14px;
            font-size: 1.3rem; font-weight: 800; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 30px var(--neon-blue); transform: translateY(-3px); }

        /* --- Bulk Actions --- */
        .actions-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
        
        .btn-act {
            height: 55px; padding: 0 25px;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 14px; color: #fff; font-size: 1.1rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; transform: translateY(-2px); }
        
        .btn-green { color: var(--neon-green); border-color: rgba(10,255,136,0.4); }
        .btn-green:hover { background: rgba(10,255,136,0.15); box-shadow: 0 0 20px var(--neon-green); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255,7,58,0.4); }
        .btn-red:hover { background: rgba(255,7,58,0.15); box-shadow: 0 0 20px var(--neon-red); }
        
        .btn-blue { color: var(--neon-blue); border-color: rgba(0,243,255,0.4); }
        .btn-blue:hover { background: rgba(0,243,255,0.15); box-shadow: 0 0 20px var(--neon-blue); }

        /* --- Table --- */
        .table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0 15px; font-size: 1.2rem; }
        .glass-table th {
            text-align: right; padding: 20px 25px; color: var(--text-muted);
            font-weight: 700; border-bottom: 2px solid rgba(255,255,255,0.05);
        }
        .glass-table tbody tr { background: rgba(255,255,255,0.03); transition: 0.3s; }
        .glass-table tbody tr:hover { background: rgba(255,255,255,0.08); transform: scale(1.005); box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .glass-table td {
            padding: 25px; color: #fff; vertical-align: middle;
            border-top: 1px solid rgba(255,255,255,0.04); border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .glass-table td:first-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(255,255,255,0.04); }
        .glass-table td:last-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(255,255,255,0.04); }

        /* Status Badges */
        .status-badge { padding: 8px 16px; border-radius: 50px; font-size: 1rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .status-paid { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .status-unpaid { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .status-waiting { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .status-reject { background: rgba(249,115,22,0.15); color: #fb923c; border: 1px solid rgba(249,115,22,0.3); }
        .status-expire { background: rgba(255,255,255,0.1); color: #ccc; border: 1px solid rgba(255,255,255,0.2); }

        /* Checkbox */
        .custom-check { width: 28px; height: 28px; border: 2px solid #666; background: transparent; cursor: pointer; appearance: none; border-radius: 8px; position: relative; }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after { content: '✔'; position: absolute; color: #000; top: 0; left: 5px; font-size: 18px; font-weight: 900; }

        /* --- Floating Dock (Centered) --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
        }
        .dock {
            pointer-events: auto; display: flex; align-items: center; gap: 20px;
            background: var(--bg-dock); backdrop-filter: blur(35px);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 40px; padding: 15px 35px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.9);
        }
        .dock-item {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            color: var(--text-muted); text-decoration: none; transition: 0.3s; position: relative; padding: 0 5px;
        }
        .dock-icon {
            font-size: 2rem; transition: 0.3s;
            width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;
            border-radius: 20px;
        }
        .dock-label { 
            font-size: 1rem; font-weight: 600; opacity: 0; position: absolute; 
            bottom: -35px; transition: 0.3s; white-space: nowrap; 
            background: #000; padding: 4px 10px; border-radius: 6px; border: 1px solid #333;
        }
        .dock-item:hover .dock-icon { transform: translateY(-20px) scale(1.2); background: rgba(255,255,255,0.15); color: #fff; }
        .dock-item:hover .dock-label { opacity: 1; bottom: -45px; color: #fff; }
        .dock-item.active .dock-icon { color: var(--neon-blue); background: rgba(0,243,255,0.15); box-shadow: 0 0 25px rgba(0,243,255,0.4); transform: translateY(-10px); }
        .dock-item.active .dock-label { opacity: 1; bottom: -45px; color: var(--neon-blue); }

        /* --- Modals --- */
        .modal-content { background: #111; border: 1px solid var(--neon-blue); color: #fff; border-radius: 20px; }
        .modal-header { border-bottom: 1px solid #333; padding: 25px; }
        .modal-title { font-weight: 800; color: var(--neon-blue); }
        .modal-body { padding: 30px; }
        .close { color: #fff; opacity: 0.8; text-shadow: none; font-size: 2rem; }
        .btn-modal { width: 100%; height: 60px; background: var(--neon-blue); color: #000; border: none; border-radius: 14px; font-size: 1.3rem; font-weight: 800; margin-top: 20px; }

        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .dock { width: 95%; justify-content: space-between; padding: 10px 20px; gap: 5px; }
            .dock-icon { width: 45px; height: 45px; font-size: 1.5rem; }
            .filters-row { flex-direction: column; align-items: stretch; }
            .actions-row { flex-direction: column; align-items: stretch; }
            .btn-act { justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>تراکنش‌های مالی</h1>
                <p>
                    <i class="fa-solid fa-money-bill-transfer" style="color: var(--neon-amber);"></i>
                    نظارت بر پرداخت‌ها و وضعیت مالی
                    <span style="opacity:0.3; margin:0 15px;">|</span>
                    <?php echo $todayDate; ?>
                </p>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid anim d-1">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($totalIncome); ?></div>
                    <div class="lbl">درآمد کل (تومان)</div>
                </div>
                <i class="fa-solid fa-sack-dollar stat-icon c-income"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($totalTx); ?></div>
                    <div class="lbl">تعداد کل تراکنش‌ها</div>
                </div>
                <i class="fa-solid fa-list-ol stat-icon c-total"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($waitingCount); ?></div>
                    <div class="lbl">در انتظار تایید</div>
                </div>
                <i class="fa-solid fa-hourglass-half stat-icon c-wait"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($unpaidCount); ?></div>
                    <div class="lbl">پرداخت‌های ناموفق</div>
                </div>
                <i class="fa-solid fa-circle-xmark stat-icon c-fail"></i>
            </div>
        </div>

        <!-- Main Glass Panel -->
        <div class="glass-panel anim d-2">
            
            <!-- Filters -->
            <form method="get" class="filters-row">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="q" class="input-readable" placeholder="شماره سفارش یا آیدی کاربر..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت پرداخت</label>
                    <select name="status" class="input-readable">
                        <option value="">همه وضعیت‌ها</option>
                        <?php foreach($statuses as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] === $k) ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>روش پرداخت</label>
                    <select name="method" class="input-readable">
                        <option value="">همه روش‌ها</option>
                        <?php foreach($methods as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo (isset($_GET['method']) && $_GET['method'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-filter">
                    <i class="fa-solid fa-search"></i> جستجو
                </button>
                
                <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['method'])): ?>
                    <a href="payment.php" class="btn-act" style="margin-top: 32px; height: 60px;">
                        <i class="fa-solid fa-rotate-right" style="font-size: 1.4rem;"></i>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Bulk Actions -->
            <div class="actions-row">
                <span id="paySelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.3rem; margin-left: 20px;">0 انتخاب</span>
                
                <button class="btn-act" id="selectAll"><i class="fa-solid fa-check-double"></i> انتخاب همه</button>
                <button class="btn-act" id="deselectAll"><i class="fa-solid fa-minus"></i> لغو</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act btn-blue" onclick="$('#modalBulkStatus').modal('show')"><i class="fa-solid fa-pen-to-square"></i> تغییر وضعیت گروهی</button>
                <button class="btn-act" id="payCopy"><i class="fa-solid fa-copy"></i> کپی شماره‌ها</button>
                
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i> خروجی اکسل</a>
            </div>

            <!-- Table -->
            <div style="margin-top: 40px;">
                <?php if(empty($listpayment)): ?>
                    <div style="text-align: center; padding: 80px; color: var(--text-dim);">
                        <i class="fa-solid fa-file-invoice" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.4;"></i>
                        <h3 style="font-size: 2rem;">تراکنشی یافت نشد</h3>
                        <p style="font-size: 1.2rem;">لطفاً فیلترها را تغییر دهید.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="glass-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;"><i class="fa-solid fa-check"></i></th>
                                    <th>آیدی کاربر</th>
                                    <th>شماره سفارش</th>
                                    <th>مبلغ (تومان)</th>
                                    <th>زمان</th>
                                    <th>روش پرداخت</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($listpayment as $row): 
                                    $statusInfo = $statuses[$row['payment_Status']] ?? ['label' => $row['payment_Status'], 'class' => 'status-other'];
                                    $methodLabel = $methods[$row['Payment_Method']] ?? $row['Payment_Method'];
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="<?php echo $row['id_order']; ?>" class="custom-check pay-check"></td>
                                    <td style="font-family: monospace; color: var(--neon-blue); letter-spacing: 1.5px; font-weight: 700;"><?php echo $row['id_user']; ?></td>
                                    <td style="font-family: monospace; opacity: 0.9;"><?php echo $row['id_order']; ?></td>
                                    <td style="color: var(--neon-amber); font-weight: 800; font-size: 1.25rem;"><?php echo number_format($row['price']); ?></td>
                                    <td style="font-size: 1.1rem;"><?php echo $row['time']; ?></td>
                                    <td><?php echo $methodLabel; ?></td>
                                    <td><span class="status-badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['label']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>

    </div>

    <!-- Modals -->
    
    <!-- Bulk Status Modal -->
    <div class="modal fade" id="modalBulkStatus" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">×</button>
                    <h4 class="modal-title">تغییر وضعیت گروهی</h4>
                </div>
                <div class="modal-body">
                    <form method="post" id="formBulkStatus">
                        <div id="hiddenIdsStatus"></div>
                        <div class="form-group">
                            <label>وضعیت جدید را انتخاب کنید:</label>
                            <select name="bulk_payment_status" class="input-readable">
                                <option value="paid">پرداخت شده</option>
                                <option value="Unpaid">پرداخت نشده</option>
                                <option value="waiting">در انتظار تایید</option>
                                <option value="reject">رد شده</option>
                                <option value="expire">منقضی شده</option>
                            </select>
                        </div>
                        <button type="button" onclick="submitBulk('formBulkStatus')" class="btn-modal">اعمال تغییرات</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Dock (Fixed Center) -->
    <div class="dock-container anim d-3">
        <div class="dock">
            <a href="index.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-house-chimney"></i></div>
                <span class="dock-label">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <span class="dock-label">سفارشات</span>
            </a>
            <a href="user.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-users"></i></div>
                <span class="dock-label">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-box-open"></i></div>
                <span class="dock-label">محصولات</span>
            </a>
            <a href="server_status.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <span class="dock-label">ادمین</span>
            </a>
            <a href="payment.php" class="dock-item active">
                <div class="dock-icon"><i class="fa-solid fa-credit-card"></i></div>
                <span class="dock-label">مالی</span>
            </a>
            <div style="width: 2px; height: 35px; background: rgba(255,255,255,0.15);"></div>
            <a href="logout.php" class="dock-item" style="color: var(--neon-red);">
                <div class="dock-icon"><i class="fa-solid fa-power-off"></i></div>
                <span class="dock-label">خروج</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function(){
            // Checkbox Logic
            function updateCount() {
                let cnt = $('.pay-check:checked').length;
                $('#paySelCount').text(cnt + ' انتخاب');
            }
            
            $('#selectAll').click(function(){ $('.pay-check').prop('checked', true); updateCount(); });
            $('#deselectAll').click(function(){ $('.pay-check').prop('checked', false); updateCount(); });
            $(document).on('change', '.pay-check', updateCount);

            // Copy IDs
            $('#payCopy').click(function(){
                let ids = [];
                $('.pay-check:checked').each(function(){
                    let row = $(this).closest('tr');
                    ids.push(row.find('td').eq(2).text().trim());
                });
                if(ids.length > 0){
                    navigator.clipboard.writeText(ids.join(', '));
                    alert(ids.length + ' شماره سفارش کپی شد.');
                } else {
                    alert('هیچ موردی انتخاب نشده است.');
                }
            });

            // Global Submit Handler
            window.submitBulk = function(formId) {
                let ids = [];
                $('.pay-check:checked').each(function(){ ids.push($(this).val()); });
                
                if(ids.length === 0){ alert('هیچ تراکنشی انتخاب نشده است!'); return; }
                
                // Clear previous hiddens
                $('#'+formId).find('input[name="ids[]"]').remove();
                
                // Add new hiddens
                ids.forEach(id => {
                    $('#'+formId).append(`<input type="hidden" name="ids[]" value="${id}">`);
                });
                
                if(confirm('آیا از انجام این عملیات گروهی روی ' + ids.length + ' تراکنش اطمینان دارید؟')){
                    $('#'+formId).submit();
                }
            };
        });
    </script>

</body>
</html>