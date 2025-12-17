<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../jdf.php';
require_once '../function.php';
require_once '../botapi.php';
require_once '../panels.php';

// Authentication
$q = $pdo->prepare("SELECT * FROM admin WHERE username=:u");
$q->bindParam(':u', $_SESSION['user'], PDO::PARAM_STR);
$q->execute();
$adminRow = $q->fetch(PDO::FETCH_ASSOC);
if( !isset($_SESSION["user"]) || !$adminRow ){ header('Location: login.php'); exit; }

// --- Bulk Actions ---

// 1. Bulk Status Change
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_status'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $newStatus = $_POST['bulk_status'];
    if(!empty($ids) && in_array($newStatus,['active','disablebyadmin','unpaid'])){
        $stmtBulk = $pdo->prepare("UPDATE invoice SET Status = :st WHERE id_invoice = :id");
        foreach($ids as $id){ $stmtBulk->execute([':st'=>$newStatus, ':id'=>$id]); }
    }
    header('Location: invoice.php');
    exit;
}

$ManagePanel = new ManagePanel();

// 2. Bulk Remove
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_remove_type'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $type = $_POST['bulk_remove_type'];
    $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
    
    if(!empty($ids) && in_array($type,['one','tow','three'])){
        $stmtInv = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice = :id');
        foreach($ids as $id){
            $stmtInv->execute([':id'=>$id]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            if(!$inv) continue;
            
            if($type==='one'){
                $pdo->prepare('UPDATE invoice SET Status = "removebyadmin" WHERE id_invoice = :id')->execute([':id'=>$id]);
                $ManagePanel->RemoveUser($inv['Service_location'], $inv['username']);
            } elseif($type==='tow'){
                if($amount>0){ 
                    $pdo->prepare('UPDATE user SET Balance = Balance + :b WHERE id = :uid')->execute([':b'=>$amount, ':uid'=>$inv['id_user']]); 
                }
                $pdo->prepare('UPDATE invoice SET Status = "removebyadmin" WHERE id_invoice = :id')->execute([':id'=>$id]);
                $ManagePanel->RemoveUser($inv['Service_location'], $inv['username']);
            } else {
                $pdo->prepare('DELETE FROM invoice WHERE id_invoice = :id')->execute([':id'=>$id]);
            }
        }
    }
    header('Location: invoice.php');
    exit;
}

// 3. Bulk Extend
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_extend'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $vol = isset($_POST['volume_service']) ? (int)$_POST['volume_service'] : 0;
    $days = isset($_POST['time_service']) ? (int)$_POST['time_service'] : 0;
    
    if(!empty($ids) && $vol>0 && $days>=0){
        $stmtInv = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice = :id');
        $stmtPanel = $pdo->prepare('SELECT * FROM marzban_panel WHERE name_panel = :np');
        $stmtInsert = $pdo->prepare('INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)');
        
        foreach($ids as $id){
            $stmtInv->execute([':id'=>$id]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            if(!$inv) continue;
            
            $stmtPanel->execute([':np'=>$inv['Service_location']]);
            $panel = $stmtPanel->fetch(PDO::FETCH_ASSOC);
            if(!$panel) continue;
            
            $ext = $ManagePanel->extend($panel['Methodextend'], $vol, $days, $inv['username'], 'custom_volume', $panel['code_panel']);
            
            if(isset($ext['status']) && $ext['status']!==false){
                $val = $vol.'_'.$days;
                $stmtInsert->execute([
                    ':id_user'=>$inv['id_user'], 
                    ':username'=>$inv['username'], 
                    ':value'=>$val, 
                    ':type'=>'extend_user_by_admin', 
                    ':time'=>date('Y/m/d H:i:s'), 
                    ':price'=>0, 
                    ':output'=>json_encode($ext)
                ]);
                $pdo->prepare('UPDATE invoice SET Status = "active" WHERE id_invoice = :id')->execute([':id'=>$id]);
            }
        }
    }
    header('Location: invoice.php');
    exit;
}

// --- View Preparation ---

$statuses = [
    'unpaid' => ['label' => 'در انتظار پرداخت', 'color' => '#fbbf24', 'class' => 'status-unpaid'],
    'active' => ['label' => 'فعال', 'color' => '#10b981', 'class' => 'status-active'],
    'disabledn' => ['label' => 'ناموجود در پنل', 'color' => '#6b7280', 'class' => 'status-other'],
    'end_of_time' => ['label' => 'پایان زمان', 'color' => '#ef4444', 'class' => 'status-danger'],
    'end_of_volume' => ['label' => 'پایان حجم', 'color' => '#3b82f6', 'class' => 'status-blue'],
    'sendedwarn' => ['label' => 'هشدار حجم/زمان', 'color' => '#8b5cf6', 'class' => 'status-purple'],
    'send_on_hold' => ['label' => 'در انتظار اتصال', 'color' => '#f97316', 'class' => 'status-orange'],
    'removebyuser' => ['label' => 'حذف توسط کاربر', 'color' => '#9ca3af', 'class' => 'status-other']
];

$where = [];
$params = [];

// Filters
if(!empty($_GET['status'])){
    $where[] = "Status = :status";
    $params[':status'] = $_GET['status'];
}
if(!empty($_GET['product'])){
    $where[] = "name_product = :product";
    $params[':product'] = $_GET['product'];
}
if(!empty($_GET['location'])){
    $where[] = "Service_location = :loc";
    $params[':loc'] = $_GET['location'];
}
if(!empty($_GET['q'])){
    $searchTerm = '%' . $_GET['q'] . '%';
    $where[] = "(id_user LIKE :q OR id_invoice LIKE :q OR username LIKE :q)";
    $params[':q'] = $searchTerm;
}
if(!empty($_GET['from']) && strtotime($_GET['from'])){
    $where[] = "time_sell >= :fromTs";
    $params[':fromTs'] = strtotime($_GET['from']);
}
if(!empty($_GET['to']) && strtotime($_GET['to'])){
    $where[] = "time_sell <= :toTs";
    $params[':toTs'] = strtotime($_GET['to'].' 23:59:59');
}

$sql = "SELECT * FROM invoice";
if(!empty($where)){
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY time_sell DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listinvoice = $stmt->fetchAll();

// CSV Export
if(isset($_GET['export']) && $_GET['export'] === 'csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=invoices-' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($output, ['ID User','Invoice ID','Username','Location','Product','Time','Price','Status']);
    foreach($listinvoice as $row){
        $time = is_numeric($row['time_sell']) ? jdate('Y/m/d H:i:s', $row['time_sell']) : $row['time_sell'];
        $price = $row['price_product'] == 0 ? 'رایگان' : number_format($row['price_product']);
        $status = isset($statuses[$row['Status']]) ? $statuses[$row['Status']]['label'] : $row['Status'];
        fputcsv($output, [
            $row['id_user'], $row['id_invoice'], $row['username'],
            $row['Service_location'], $row['name_product'], $time,
            $price, $status
        ]);
    }
    fclose($output);
    exit();
}

// Stats Calculation
$totalInvoices = count($listinvoice);
$activeCount = 0;
$unpaidCount = 0;
$totalRevenue = 0;

foreach($listinvoice as $inv) {
    if($inv['Status'] == 'active') $activeCount++;
    if($inv['Status'] == 'unpaid') $unpaidCount++;
    if(is_numeric($inv['price_product'])) $totalRevenue += $inv['price_product'];
}

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت سفارشات | پنل حرفه‌ای</title>
    
    <!-- Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

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
                radial-gradient(circle at 15% 15%, rgba(0, 243, 255, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(188, 19, 254, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 160px;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

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
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px; margin-bottom: 50px;
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
        
        .c-rev { color: var(--neon-blue); filter: drop-shadow(0 0 10px rgba(0,243,255,0.3)); }
        .c-act { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0,255,136,0.3)); }
        .c-unp { color: var(--neon-amber); filter: drop-shadow(0 0 10px rgba(255,170,0,0.3)); }
        .c-tot { color: var(--neon-purple); filter: drop-shadow(0 0 10px rgba(188,19,254,0.3)); }

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
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; color: #fff; font-size: 1rem; margin-bottom: 10px; font-weight: 700; }
        
        .input-readable {
            width: 100%; height: 55px;
            background: #000000; border: 2px solid #444; color: #ffffff;
            padding: 0 20px; border-radius: 14px; font-family: inherit; font-size: 1.1rem; font-weight: 500;
            transition: 0.3s;
        }
        .input-readable:focus { border-color: var(--neon-blue); box-shadow: 0 0 20px rgba(0, 243, 255, 0.25); }
        select.input-readable { cursor: pointer; }
        
        .btn-filter {
            height: 55px; padding: 0 40px; margin-top: 32px;
            background: var(--neon-blue); color: #000; border: none; border-radius: 14px;
            font-size: 1.2rem; font-weight: 800; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 30px var(--neon-blue); transform: translateY(-3px); }

        /* --- Bulk Actions --- */
        .actions-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 20px; }
        
        .btn-act {
            height: 50px; padding: 0 20px;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px; color: #fff; font-size: 1rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; transform: translateY(-2px); }
        
        .btn-green { color: var(--neon-green); border-color: rgba(10,255,136,0.4); }
        .btn-green:hover { background: rgba(10,255,136,0.1); box-shadow: 0 0 15px var(--neon-green); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255,7,58,0.4); }
        .btn-red:hover { background: rgba(255,7,58,0.1); box-shadow: 0 0 15px var(--neon-red); }
        
        .btn-amber { color: var(--neon-amber); border-color: rgba(255,183,0,0.4); }
        .btn-amber:hover { background: rgba(255,183,0,0.1); box-shadow: 0 0 15px var(--neon-amber); }

        /* --- Table --- */
        .table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; font-size: 1.15rem; }
        .glass-table th {
            text-align: right; padding: 20px; color: var(--text-muted);
            font-weight: 700; border-bottom: 2px solid rgba(255,255,255,0.05);
        }
        .glass-table tbody tr { background: rgba(255,255,255,0.03); transition: 0.3s; }
        .glass-table tbody tr:hover { background: rgba(255,255,255,0.08); transform: scale(1.005); box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .glass-table td {
            padding: 25px 20px; color: #fff; vertical-align: middle;
            border-top: 1px solid rgba(255,255,255,0.04); border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .glass-table td:first-child { border-radius: 0 16px 16px 0; border-right: 1px solid rgba(255,255,255,0.04); }
        .glass-table td:last-child { border-radius: 16px 0 0 16px; border-left: 1px solid rgba(255,255,255,0.04); }

        /* Status Badges */
        .status-badge { padding: 8px 16px; border-radius: 50px; font-size: 0.95rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .status-active { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .status-danger { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .status-blue { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .status-purple { background: rgba(139,92,246,0.15); color: #c084fc; border: 1px solid rgba(139,92,246,0.3); }
        .status-orange { background: rgba(249,115,22,0.15); color: #fb923c; border: 1px solid rgba(249,115,22,0.3); }
        .status-unpaid { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
        .status-other { background: rgba(255,255,255,0.1); color: #ccc; }

        /* Checkbox */
        .custom-check { width: 24px; height: 24px; border: 2px solid #666; background: transparent; cursor: pointer; appearance: none; border-radius: 6px; position: relative; }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after { content: '✔'; position: absolute; color: #000; top: -1px; left: 4px; font-size: 16px; font-weight: 900; }

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

        /* --- Modals (Dark) --- */
        .modal-content { background: #111; border: 1px solid var(--neon-blue); color: #fff; border-radius: 20px; }
        .modal-header { border-bottom: 1px solid #333; padding: 20px; }
        .modal-title { font-weight: 800; color: var(--neon-blue); }
        .modal-body { padding: 30px; }
        .close { color: #fff; opacity: 0.8; text-shadow: none; font-size: 2rem; }

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
                <h1>مدیریت سفارشات</h1>
                <p>
                    <i class="fa-solid fa-list-check" style="color: var(--neon-purple);"></i>
                    بررسی و مدیریت فاکتورها و سرویس‌ها
                    <span style="opacity:0.3; margin:0 15px;">|</span>
                    <?php echo $todayDate; ?>
                </p>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid anim d-1">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($totalInvoices); ?></div>
                    <div class="lbl">کل سفارشات</div>
                </div>
                <i class="fa-solid fa-folder-open stat-icon c-tot"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($activeCount); ?></div>
                    <div class="lbl">سرویس‌های فعال</div>
                </div>
                <i class="fa-solid fa-check-circle stat-icon c-act"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($unpaidCount); ?></div>
                    <div class="lbl">منتظر پرداخت</div>
                </div>
                <i class="fa-solid fa-clock stat-icon c-unp"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($totalRevenue); ?></div>
                    <div class="lbl">جمع کل (تومان)</div>
                </div>
                <i class="fa-solid fa-sack-dollar stat-icon c-rev"></i>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="glass-panel anim d-2">
            
            <!-- Filters -->
            <form method="get" class="filters-row" id="invFilterForm">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="q" class="input-readable" placeholder="آیدی کاربر، سفارش یا نام کاربری..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>تاریخ شروع</label>
                    <input type="text" name="from" id="dateFrom" class="input-readable" placeholder="انتخاب تاریخ..." value="<?php echo htmlspecialchars($_GET['from'] ?? ''); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>تاریخ پایان</label>
                    <input type="text" name="to" id="dateTo" class="input-readable" placeholder="انتخاب تاریخ..." value="<?php echo htmlspecialchars($_GET['to'] ?? ''); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status" class="input-readable">
                        <option value="">همه وضعیت‌ها</option>
                        <?php foreach($statuses as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] === $k) ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-filter">
                    <i class="fa-solid fa-search"></i> اعمال
                </button>
                
                <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['from'])): ?>
                    <a href="invoice.php" class="btn-act" style="margin-top: 32px; height: 60px;">
                        <i class="fa-solid fa-rotate-right" style="font-size: 1.4rem;"></i>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Bulk Actions -->
            <div class="actions-row">
                <span id="selCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.3rem; margin-left: 20px;">0 انتخاب</span>
                
                <button class="btn-act" id="selectAll"><i class="fa-solid fa-check-double"></i> انتخاب همه</button>
                <button class="btn-act" id="deselectAll"><i class="fa-solid fa-minus"></i> لغو</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act btn-green" onclick="$('#modalBulkStatus').modal('show')"><i class="fa-solid fa-pen-to-square"></i> تغییر وضعیت</button>
                <button class="btn-act btn-blue" onclick="$('#modalBulkExtend').modal('show')"><i class="fa-solid fa-calendar-plus"></i> تمدید گروهی</button>
                <button class="btn-act btn-red" onclick="$('#modalBulkRemove').modal('show')"><i class="fa-solid fa-trash"></i> حذف گروهی</button>
                
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i> خروجی اکسل</a>
            </div>

            <!-- Table -->
            <div style="margin-top: 40px;">
                <?php if(empty($listinvoice)): ?>
                    <div style="text-align: center; padding: 80px; color: var(--text-dim);">
                        <i class="fa-solid fa-file-circle-xmark" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.4;"></i>
                        <h3 style="font-size: 2rem;">سفارشی یافت نشد</h3>
                        <p style="font-size: 1.2rem;">لطفاً فیلترها را تغییر دهید.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="glass-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;"><i class="fa-solid fa-check"></i></th>
                                    <th>آیدی کاربر</th>
                                    <th>شناسه سفارش</th>
                                    <th>نام کاربری</th>
                                    <th>محصول</th>
                                    <th>تاریخ</th>
                                    <th>قیمت</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($listinvoice as $row): 
                                    $statusInfo = $statuses[$row['Status']] ?? ['label' => $row['Status'], 'class' => 'status-other'];
                                    $time = is_numeric($row['time_sell']) ? jdate('Y/m/d H:i', $row['time_sell']) : $row['time_sell'];
                                    $price = ($row['price_product'] == 0) ? 'رایگان' : number_format($row['price_product']);
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="<?php echo $row['id_invoice']; ?>" class="custom-check inv-check"></td>
                                    <td style="font-family: monospace; color: var(--neon-blue); letter-spacing: 1px; font-weight: 700;"><?php echo $row['id_user']; ?></td>
                                    <td style="font-family: monospace; opacity: 0.8;"><?php echo $row['id_invoice']; ?></td>
                                    <td style="font-weight: 800; color: #fff; font-size: 1.2rem;"><?php echo $row['username']; ?></td>
                                    <td><?php echo $row['name_product']; ?></td>
                                    <td style="font-size: 1.1rem;"><?php echo $time; ?></td>
                                    <td style="color: var(--neon-amber); font-weight: 800; font-size: 1.2rem;"><?php echo $price; ?></td>
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
                            <select name="bulk_status" class="input-readable">
                                <option value="active">فعال</option>
                                <option value="disablebyadmin">غیرفعال (توسط ادمین)</option>
                                <option value="unpaid">در انتظار پرداخت</option>
                            </select>
                        </div>
                        <button type="button" onclick="submitBulk('formBulkStatus')" class="btn-modal">اعمال تغییرات</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Extend Modal -->
    <div class="modal fade" id="modalBulkExtend" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">×</button>
                    <h4 class="modal-title" style="color: var(--neon-green);">تمدید گروهی سرویس‌ها</h4>
                </div>
                <div class="modal-body">
                    <form method="post" id="formBulkExtend">
                        <div id="hiddenIdsExtend"></div>
                        <input type="hidden" name="bulk_extend" value="1">
                        <div class="form-group">
                            <label>حجم اضافه (گیگابایت):</label>
                            <input type="number" name="volume_service" class="input-readable" placeholder="مثلا: 10">
                        </div>
                        <div class="form-group" style="margin-top: 15px;">
                            <label>زمان اضافه (روز):</label>
                            <input type="number" name="time_service" class="input-readable" placeholder="مثلا: 30">
                        </div>
                        <button type="button" onclick="submitBulk('formBulkExtend')" class="btn-modal" style="background: var(--neon-green);">تمدید سرویس‌ها</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Remove Modal -->
    <div class="modal fade" id="modalBulkRemove" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">×</button>
                    <h4 class="modal-title" style="color: var(--neon-red);">حذف گروهی سفارشات</h4>
                </div>
                <div class="modal-body">
                    <form method="post" id="formBulkRemove">
                        <div id="hiddenIdsRemove"></div>
                        <div class="form-group">
                            <label>نوع حذف:</label>
                            <select name="bulk_remove_type" id="removeType" class="input-readable">
                                <option value="one">حذف سرویس (بدون بازگشت وجه)</option>
                                <option value="tow">حذف سرویس + بازگشت وجه به کیف پول</option>
                                <option value="three">فقط حذف فاکتور (سرویس می‌ماند)</option>
                            </select>
                        </div>
                        <div class="form-group" id="refundAmountGroup" style="display:none; margin-top: 15px;">
                            <label>مبلغ بازگشتی (تومان):</label>
                            <input type="number" name="amount" class="input-readable" placeholder="مبلغ برای هر سفارش">
                        </div>
                        <button type="button" onclick="submitBulk('formBulkRemove')" class="btn-modal" style="background: var(--neon-red);">تایید حذف</button>
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
            <a href="invoice.php" class="dock-item active">
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
    <script src="assets/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>

    <script>
        $(document).ready(function(){
            // Datepicker
            $('#dateFrom, #dateTo').daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                locale: { format: 'YYYY-MM-DD' }
            });

            // Checkbox Logic
            function updateCount() {
                let cnt = $('.inv-check:checked').length;
                $('#selCount').text(cnt + ' انتخاب');
            }
            
            $('#selectAll').click(function(){ $('.inv-check').prop('checked', true); updateCount(); });
            $('#deselectAll').click(function(){ $('.inv-check').prop('checked', false); updateCount(); });
            $(document).on('change', '.inv-check', updateCount);

            // Refund Toggle
            $('#removeType').change(function(){
                if($(this).val() === 'tow') $('#refundAmountGroup').slideDown();
                else $('#refundAmountGroup').slideUp();
            });

            // Global Submit Handler
            window.submitBulk = function(formId) {
                let ids = [];
                $('.inv-check:checked').each(function(){ ids.push($(this).val()); });
                
                if(ids.length === 0){ alert('هیچ سفارشی انتخاب نشده است!'); return; }
                
                // Clear previous hiddens
                $('#'+formId).find('input[name="ids[]"]').remove();
                
                // Add new hiddens
                ids.forEach(id => {
                    $('#'+formId).append(`<input type="hidden" name="ids[]" value="${id}">`);
                });
                
                if(confirm('آیا از انجام این عملیات گروهی روی ' + ids.length + ' سفارش اطمینان دارید؟')){
                    $('#'+formId).submit();
                }
            };
        });
    </script>

</body>
</html>