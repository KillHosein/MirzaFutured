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
    <title>مدیریت تراکنش‌ها | نسخه الترا پلاس</title>
    
    <!-- Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            /* Palette: Midnight Neon */
            --bg-body: #020204;
            --bg-card: rgba(23, 23, 30, 0.75);
            --bg-card-hover: rgba(35, 35, 45, 0.9);
            --bg-glass: rgba(20, 20, 25, 0.85);
            --bg-dock: rgba(10, 10, 15, 0.95);
            --bg-modal: rgba(20, 20, 25, 0.98);
            
            /* Neons */
            --neon-blue: #00f3ff;
            --neon-purple: #bc13fe;
            --neon-green: #00ff88;
            --neon-red: #ff004c;
            --neon-amber: #ffaa00;
            
            /* Text */
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            
            /* Borders */
            --border-subtle: 1px solid rgba(255, 255, 255, 0.08);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.2);
            --shadow-card: 0 15px 50px rgba(0,0,0,0.9);
            
            --radius-main: 28px;
            --radius-lg: 24px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        body {
            background-color: var(--bg-body);
            color: var(--text-pri);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(255, 170, 0, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(0, 243, 255, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
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
            width: 100%; padding: 30px 4%; max-width: 1920px; margin: 0 auto;
            flex-grow: 1;
            display: flex; flex-direction: column; gap: 30px;
        }

        /* --- Header --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .page-title h1 {
            font-size: 3rem; font-weight: 900; margin: 0; color: #fff;
            text-shadow: 0 0 30px rgba(255,255,255,0.15);
        }
        .page-title p { color: var(--text-sec); font-size: 1.2rem; margin-top: 5px; font-weight: 400; }
        
        .info-pill {
            background: rgba(255,255,255,0.03); border: var(--border-subtle);
            padding: 12px 25px; border-radius: 18px;
            display: flex; align-items: center; gap: 10px; font-size: 1.1rem;
            backdrop-filter: blur(10px); color: var(--text-sec);
        }

        /* --- Stats Cards --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px; margin-bottom: 50px;
        }
        .stat-card {
            background: var(--bg-card); border: var(--border-subtle); border-radius: 24px;
            padding: 30px 35px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .stat-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.15); box-shadow: 0 15px 35px rgba(0,0,0,0.5); }
        
        .stat-info .val { font-size: 2.5rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 5px; }
        .stat-info .lbl { font-size: 1.1rem; color: var(--text-sec); font-weight: 500; }
        .stat-icon { font-size: 3rem; opacity: 0.9; }
        
        .c-income { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0,255,136,0.3)); }
        .c-total { color: var(--neon-blue); filter: drop-shadow(0 0 10px rgba(0,243,255,0.3)); }
        .c-wait { color: var(--neon-amber); filter: drop-shadow(0 0 10px rgba(255,170,0,0.3)); }
        .c-fail { color: var(--neon-red); filter: drop-shadow(0 0 10px rgba(255,0,76,0.3)); }

        /* --- Glass Panel --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 35px;
            flex-grow: 1; /* Key for filling space */
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
            min-height: 500px;
        }

        /* --- Filters (High Readability) --- */
        .filters-row {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.06); margin-bottom: 25px;
        }
        .form-group { flex: 1; min-width: 220px; }
        .form-group label { display: block; color: var(--text-sec); font-size: 1.05rem; margin-bottom: 10px; font-weight: 600; }
        
        .input-readable {
            width: 100%; height: 60px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 20px; border-radius: 16px;
            font-family: inherit; font-size: 1.1rem; font-weight: 500;
            transition: 0.3s;
        }
        .input-readable:focus { border-color: var(--neon-blue); box-shadow: 0 0 20px rgba(0, 243, 255, 0.25); background: rgba(0,0,0,0.5); }
        select.input-readable { cursor: pointer; }
        select.input-readable option { background: #111; color: #fff; font-size: 1.1rem; }
        
        .btn-filter {
            height: 60px; padding: 0 40px;
            background: var(--neon-blue); color: #000; border: none; border-radius: 16px;
            font-size: 1.2rem; font-weight: 700; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 30px var(--neon-blue); transform: translateY(-3px); }

        /* --- Bulk Actions --- */
        .actions-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 25px; }
        
        .btn-act {
            height: 55px; padding: 0 25px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px; color: var(--text-sec); font-size: 1.1rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 10px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; transform: translateY(-2px); color: #fff; }
        
        .btn-green { color: var(--neon-green); border-color: rgba(0,255,136,0.4); }
        .btn-green:hover { background: rgba(0,255,136,0.15); box-shadow: 0 0 20px var(--neon-green); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255,7,58,0.4); }
        .btn-red:hover { background: rgba(255,7,58,0.15); box-shadow: 0 0 20px var(--neon-red); }
        
        .btn-blue { color: var(--neon-blue); border-color: rgba(0,243,255,0.4); }
        .btn-blue:hover { background: rgba(0,243,255,0.15); box-shadow: 0 0 20px var(--neon-blue); }

        /* --- Table --- */
        .table-container-flex {
            flex-grow: 1;
            overflow-y: auto; overflow-x: auto;
            border-radius: 18px;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.04);
        }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 1.15rem; }
        .glass-table th {
            text-align: right; padding: 22px 25px; color: var(--text-sec);
            font-weight: 600; background: rgba(255,255,255,0.03);
            position: sticky; top: 0; z-index: 10; backdrop-filter: blur(15px);
        }
        .glass-table tbody tr { transition: 0.15s; }
        .glass-table tbody tr:hover { background: rgba(255,255,255,0.05); }
        .glass-table td {
            padding: 22px 25px; color: #fff; vertical-align: middle;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        /* Status Badges */
        .status-badge { padding: 8px 16px; border-radius: 50px; font-size: 1rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .status-paid { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .status-unpaid { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .status-waiting { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .status-reject { background: rgba(249,115,22,0.15); color: #fb923c; border: 1px solid rgba(249,115,22,0.3); }
        .status-expire { background: rgba(255,255,255,0.1); color: #ccc; border: 1px solid rgba(255,255,255,0.2); }

        /* Checkbox */
        .custom-check { width: 24px; height: 24px; border: 2px solid #666; background: transparent; cursor: pointer; appearance: none; border-radius: 6px; position: relative; transition: 0.2s; }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after { content: '✔'; position: absolute; color: #000; top: -1px; left: 3px; font-size: 16px; font-weight: 800; }

        /* --- Floating Dock (Bigger & Labels Top) --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
        }
        .dock {
            pointer-events: auto; display: flex; align-items: center; gap: 12px;
            background: rgba(15, 15, 20, 0.9); backdrop-filter: blur(35px);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 30px; padding: 15px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.9);
            max-width: 95vw; overflow-x: auto; scrollbar-width: none; /* Hide scrollbar */
        }
        .dock::-webkit-scrollbar { display: none; } /* Hide scrollbar Chrome/Safari */
        
        .dock-item {
            width: 60px; height: 60px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 20px;
            color: var(--text-sec); font-size: 1.6rem;
            text-decoration: none; position: relative; background: transparent;
            transition: all 0.25s cubic-bezier(0.3, 0.7, 0.4, 1.5);
        }
        .dock-item:hover {
            width: 75px; height: 75px; margin: 0 6px;
            background: rgba(255,255,255,0.1); color: #fff;
            transform: translateY(-12px);
        }
        .dock-item.active {
            color: var(--neon-blue); background: rgba(0, 242, 255, 0.1);
        }
        
        /* Unified Dock Labels (Labels Top) */
        .dock-label { 
            font-size: 0.9rem; font-weight: 600; opacity: 0; position: absolute; 
            bottom: 100%; /* Shows ABOVE */
            transition: 0.3s; white-space: nowrap; 
            background: rgba(0,0,0,0.9); padding: 4px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2);
            color: #fff; pointer-events: none; margin-bottom: 15px;
        }
        .dock-item:hover .dock-label { opacity: 1; bottom: 100%; transform: translateY(-5px); }
        .dock-item.active .dock-label { opacity: 1; bottom: 100%; color: var(--neon-blue); }

        .dock-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 6px; flex-shrink: 0; }

        /* --- Modals (Dark & Bigger) --- */
        .modal-content {
            background: var(--bg-modal);
            backdrop-filter: blur(40px);
            border: 1px solid var(--neon-blue);
            border-radius: var(--radius-lg);
            color: #fff;
            box-shadow: 0 0 80px rgba(0,0,0,0.95);
        }
        .modal-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding: 30px; }
        .modal-title { font-size: 1.8rem; font-weight: 800; color: var(--neon-blue); }
        .modal-body { padding: 40px; }
        .close { text-shadow: none; color: #fff; opacity: 0.8; font-size: 3rem; margin-top: -10px; font-weight: 300; }
        
        .form-control-dark {
            background: #050505; border: 2px solid #333; color: #fff;
            height: 60px; border-radius: 16px; padding: 0 25px; font-size: 1.2rem; width: 100%; transition: 0.3s;
        }
        .form-control-dark:focus { border-color: var(--neon-blue); outline: none; box-shadow: 0 0 25px rgba(0,243,255,0.25); }
        
        .btn-modal { width: 100%; height: 65px; background: var(--neon-blue); color: #000; border: none; border-radius: 16px; font-size: 1.4rem; font-weight: 800; margin-top: 30px; transition: 0.3s; }
        .btn-modal:hover { box-shadow: 0 0 40px var(--neon-blue); transform: translateY(-3px); }

        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .dock { width: 95%; justify-content: flex-start; }
            .dock-item { width: 50px; height: 50px; font-size: 1.4rem; }
            .filters-row { flex-direction: column; align-items: stretch; }
            .actions-row { flex-direction: column; align-items: stretch; }
            .btn-act { justify-content: center; }
            .page-title h1 { font-size: 2.5rem; }
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
                </p>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
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
                    <i class="fa-solid fa-search"></i> اعمال
                </button>
                
                <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['method'])): ?>
                    <a href="payment.php" class="btn-act" style="margin-top: 32px; height: 60px; justify-content: center;">
                        <i class="fa-solid fa-rotate-right" style="font-size: 1.3rem;"></i>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Bulk Actions -->
            <div class="actions-row">
                <span id="paySelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.2rem; margin-left: 20px;">0 انتخاب</span>
                
                <button class="btn-act" id="selectAll"><i class="fa-solid fa-check-double"></i> انتخاب همه</button>
                <button class="btn-act" id="deselectAll"><i class="fa-solid fa-minus"></i> لغو</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act btn-blue" onclick="$('#modalBulkStatus').modal('show')"><i class="fa-solid fa-pen-to-square"></i> تغییر وضعیت گروهی</button>
                <button class="btn-act" id="payCopy"><i class="fa-solid fa-copy"></i> کپی شماره‌ها</button>
                
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i> خروجی اکسل</a>
            </div>

            <!-- Table -->
            <?php if(empty($listpayment)): ?>
                <div style="text-align: center; padding: 80px; color: var(--text-sec); flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                    <i class="fa-solid fa-file-invoice" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.3;"></i>
                    <h3 style="font-size: 2rem;">تراکنشی یافت نشد</h3>
                    <p style="font-size: 1.2rem;">هنوز تراکنشی ثبت نشده یا با فیلترها همخوانی ندارد.</p>
                </div>
            <?php else: ?>
                <div class="table-container-flex">
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
                                $time = function_exists('jdate') ? jdate('Y/m/d H:i:s', $row['time']) : date('Y-m-d H:i:s', $row['time']);
                            ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo $row['id_order']; ?>" class="custom-check pay-check"></td>
                                <td style="font-family: monospace; color: var(--neon-blue); letter-spacing: 1px; font-weight: 700;"><?php echo $row['id_user']; ?></td>
                                <td style="font-family: monospace; opacity: 0.9;"><?php echo $row['id_order']; ?></td>
                                <td style="color: var(--neon-amber); font-weight: 800; font-size: 1.25rem;"><?php echo number_format($row['price']); ?></td>
                                <td style="font-size: 1.1rem;"><?php echo $time; ?></td>
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
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">وضعیت جدید را انتخاب کنید:</label>
                            <select name="bulk_payment_status" class="form-control-dark">
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

    <!-- Floating Dock -->
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
            <a href="users.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-users"></i></div>
                <span class="dock-label">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-box-open"></i></div>
                <span class="dock-label">محصولات</span>
            </a>
            <a href="service.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-server"></i></div>
                <span class="dock-label">سرویس‌ها</span>
            </a>
            <div class="dock-divider"></div>
            <a href="cancelService.php" class="dock-item" style="color: var(--neon-red);">
                <div class="dock-icon"><i class="fa-solid fa-ban"></i></div>
                <span class="dock-label">مسدود</span>
            </a>
            <a href="payment.php" class="dock-item active">
                <div class="dock-icon"><i class="fa-solid fa-credit-card"></i></div>
                <span class="dock-label">مالی</span>
            </a>
            <a href="inbound.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-network-wired"></i></div>
                <span class="dock-label">کانفیگ</span>
            </a>
            <a href="seeting_x_ui.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-tower-broadcast"></i></div>
                <span class="dock-label">پنل X-UI</span>
            </a>
            <div class="dock-divider"></div>
            <a href="settings.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-gear"></i></div>
                <span class="dock-label">تنظیمات</span>
            </a>
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