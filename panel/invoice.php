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
    <title>مدیریت سفارشات | نسخه الترا پلاس</title>
    
    <!-- Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet"/>

    <style>
        :root {
            /* Theme Core */
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.75);
            --bg-card-hover: rgba(35, 35, 45, 0.9);
            --bg-glass: rgba(20, 20, 25, 0.85);
            --bg-dock: rgba(10, 10, 15, 0.95);
            --bg-modal: rgba(20, 20, 25, 0.98);
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-gold: #fbbf24;
            
            /* Text */
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            
            /* Borders */
            --border-subtle: 1px solid rgba(255, 255, 255, 0.08);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.2);
            --shadow-card: 0 15px 50px rgba(0,0,0,0.6);
            
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
                radial-gradient(circle at 15% 15%, rgba(0, 242, 255, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(192, 38, 211, 0.08) 0%, transparent 45%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Full Height Container --- */
        .container-fluid-custom {
            width: 100%; max-width: 1920px; margin: 0 auto;
            padding: 30px 4%;
            flex-grow: 1;
            display: flex; flex-direction: column; gap: 30px;
        }

        /* --- Header Bigger --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .page-title h1 {
            font-size: 3rem; font-weight: 900; margin: 0; color: #fff;
            text-shadow: 0 0 30px rgba(255,255,255,0.1);
        }
        .page-title p { color: var(--text-sec); font-size: 1.2rem; margin-top: 5px; font-weight: 400; }
        
        .info-pill {
            background: rgba(255,255,255,0.03); border: var(--border-subtle);
            padding: 12px 25px; border-radius: 18px;
            display: flex; align-items: center; gap: 10px; font-size: 1.1rem;
            backdrop-filter: blur(10px); color: var(--text-sec);
        }

        /* --- Stats Cards (Bigger) --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;
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
        
        .c-rev { color: var(--neon-blue); filter: drop-shadow(0 0 10px rgba(0,243,255,0.3)); }
        .c-act { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0,255,136,0.3)); }
        .c-unp { color: var(--neon-gold); filter: drop-shadow(0 0 10px rgba(251,191,36,0.3)); }
        .c-tot { color: var(--neon-purple); filter: drop-shadow(0 0 10px rgba(192,38,211,0.3)); }

        /* --- Glass Panel (Spacious) --- */
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
            width: 100%; height: 55px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 20px; border-radius: 16px;
            font-family: inherit; font-size: 1.1rem; font-weight: 500;
            transition: 0.3s;
        }
        .input-readable:focus { border-color: var(--neon-blue); box-shadow: 0 0 20px rgba(0, 243, 255, 0.25); background: rgba(0,0,0,0.5); }
        select.input-readable { cursor: pointer; }
        select.input-readable option { background: #111; color: #fff; font-size: 1.1rem; }
        
        .btn-filter {
            height: 55px; padding: 0 35px;
            background: var(--neon-blue); color: #000; border: none; border-radius: 16px;
            font-size: 1.1rem; font-weight: 700; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 25px var(--neon-blue); transform: translateY(-3px); }

        /* --- Bulk Actions --- */
        .actions-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 25px; }
        
        .btn-act {
            height: 50px; padding: 0 22px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px; color: var(--text-sec); font-size: 1.05rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 10px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; transform: translateY(-2px); color: #fff; }
        
        .btn-green { color: var(--neon-green); border-color: rgba(0,255,163,0.4); }
        .btn-green:hover { background: rgba(0,255,163,0.1); box-shadow: 0 0 20px rgba(0,255,163,0.3); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255,42,109,0.4); }
        .btn-red:hover { background: rgba(255,42,109,0.1); box-shadow: 0 0 20px rgba(255,42,109,0.3); }
        
        .btn-blue { color: var(--neon-blue); border-color: rgba(0,243,255,0.4); }
        .btn-blue:hover { background: rgba(0,243,255,0.1); box-shadow: 0 0 20px rgba(0,243,255,0.3); }

        /* --- Table --- */
        .table-container-flex {
            flex-grow: 1;
            overflow-y: auto; overflow-x: auto;
            border-radius: 18px;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.04);
        }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 1.1rem; }
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
        .status-badge { padding: 8px 16px; border-radius: 50px; font-size: 0.95rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .status-active { background: rgba(0,255,163,0.15); color: var(--neon-green); border: 1px solid rgba(0,255,163,0.3); }
        .status-danger { background: rgba(255,42,109,0.15); color: var(--neon-red); border: 1px solid rgba(255,42,109,0.3); }
        .status-blue { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .status-purple { background: rgba(192,38,211,0.15); color: var(--neon-purple); border: 1px solid rgba(192,38,211,0.3); }
        .status-orange { background: rgba(249,115,22,0.15); color: #fb923c; border: 1px solid rgba(249,115,22,0.3); }
        .status-unpaid { background: rgba(251,191,36,0.15); color: var(--neon-gold); border: 1px solid rgba(251,191,36,0.3); }
        .status-other { background: rgba(255,255,255,0.05); color: #ccc; border: 1px solid rgba(255,255,255,0.1); }

        /* Checkbox */
        .custom-check { width: 24px; height: 24px; border: 2px solid #666; background: transparent; cursor: pointer; appearance: none; border-radius: 6px; position: relative; transition: 0.2s; }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after { content: '✔'; position: absolute; color: #000; top: -1px; left: 3px; font-size: 16px; font-weight: 800; }

        /* --- Floating Dock (Bigger) --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
        }
        .dock {
            pointer-events: auto; display: flex; align-items: center; gap: 12px;
            background: rgba(15, 15, 20, 0.9); backdrop-filter: blur(35px);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 30px; padding: 15px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.9);
        }
        .dock-item {
            width: 60px; height: 60px;
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
        .dock-item::before {
            content: attr(data-tooltip);
            position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(10px) scale(0.8);
            background: rgba(0,0,0,0.95); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; padding: 8px 16px; border-radius: 10px;
            font-size: 0.9rem; font-weight: 600; white-space: nowrap;
            opacity: 0; visibility: hidden; transition: 0.2s; pointer-events: none; margin-bottom: 15px;
        }
        .dock-item:hover::before { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0) scale(1); }
        .dock-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 6px; }

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
        
        .btn-modal {
            width: 100%; height: 65px; background: var(--neon-blue); color: #000;
            border: none; border-radius: 16px; font-size: 1.4rem; font-weight: 800; cursor: pointer;
            margin-top: 30px; transition: 0.3s;
        }
        .btn-modal:hover { box-shadow: 0 0 40px var(--neon-blue); transform: translateY(-3px); }

        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .dock { width: 95%; overflow-x: auto; justify-content: flex-start; }
            .dock-item { flex-shrink: 0; width: 50px; height: 50px; font-size: 1.4rem; }
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
                <h1>مدیریت سفارشات</h1>
                <p>
                    <i class="fa-solid fa-list-check" style="color: var(--neon-purple);"></i>
                    بررسی و مدیریت فاکتورها و سرویس‌ها
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
                    <a href="invoice.php" class="btn-act" style="margin-top: 32px; height: 55px; justify-content: center;">
                        <i class="fa-solid fa-rotate-right" style="font-size: 1.3rem;"></i>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Bulk Actions -->
            <div class="actions-row">
                <span id="selCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.2rem; margin-left: 20px;">0 انتخاب</span>
                
                <button class="btn-act" id="selectAll"><i class="fa-solid fa-check-double"></i> انتخاب همه</button>
                <button class="btn-act" id="deselectAll"><i class="fa-solid fa-minus"></i> لغو</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act btn-green" onclick="$('#modalBulkStatus').modal('show')"><i class="fa-solid fa-pen-to-square"></i> تغییر وضعیت</button>
                <button class="btn-act btn-blue" onclick="$('#modalBulkExtend').modal('show')"><i class="fa-solid fa-calendar-plus"></i> تمدید گروهی</button>
                <button class="btn-act btn-red" onclick="$('#modalBulkRemove').modal('show')"><i class="fa-solid fa-trash"></i> حذف گروهی</button>
                
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i> خروجی اکسل</a>
            </div>

            <!-- Table -->
            <?php if(empty($listinvoice)): ?>
                <div style="text-align: center; padding: 80px; color: var(--text-sec); flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                    <i class="fa-solid fa-file-circle-xmark" style="font-size: 5rem; margin-bottom: 25px; opacity: 0.3;"></i>
                    <h3 style="font-size: 2rem;">سفارشی یافت نشد</h3>
                    <p style="font-size: 1.2rem;">لطفاً فیلترها را تغییر دهید.</p>
                </div>
            <?php else: ?>
                <div class="table-container-flex">
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
                                <td style="font-weight: 800; color: #fff; font-size: 1.15rem;"><?php echo $row['username']; ?></td>
                                <td><?php echo $row['name_product']; ?></td>
                                <td style="font-size: 1.05rem;"><?php echo $time; ?></td>
                                <td style="color: var(--neon-gold); font-weight: 800; font-size: 1.2rem;"><?php echo $price; ?></td>
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
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">حجم اضافه (گیگابایت):</label>
                            <input type="number" name="volume_service" class="input-readable" placeholder="مثلا: 10">
                        </div>
                        <div class="form-group" style="margin-top: 25px;">
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">زمان اضافه (روز):</label>
                            <input type="number" name="time_service" class="input-readable" placeholder="مثلا: 30">
                        </div>
                        <button type="button" onclick="submitBulk('formBulkExtend')" class="btn-modal" style="background: var(--neon-green); color:#000;">تمدید سرویس‌ها</button>
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
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">نوع حذف:</label>
                            <select name="bulk_remove_type" id="removeType" class="input-readable">
                                <option value="one">حذف سرویس (بدون بازگشت وجه)</option>
                                <option value="tow">حذف سرویس + بازگشت وجه به کیف پول</option>
                                <option value="three">فقط حذف فاکتور (سرویس می‌ماند)</option>
                            </select>
                        </div>
                        <div class="form-group" id="refundAmountGroup" style="display:none; margin-top: 25px;">
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">مبلغ بازگشتی (تومان):</label>
                            <input type="number" name="amount" class="input-readable" placeholder="مبلغ برای هر سفارش">
                        </div>
                        <button type="button" onclick="submitBulk('formBulkRemove')" class="btn-modal" style="background: var(--neon-red); color:#fff;">تایید حذف</button>
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
            <div style="width: 2px; height: 40px; background: rgba(255,255,255,0.15);"></div>
            <a href="cancelService.php" class="dock-item" data-tooltip="مسدودها" style="color: var(--neon-red);">
                <div class="dock-icon"><i class="fa-solid fa-ban"></i></div>
                <span class="dock-label">مسدود</span>
            </a>
            <a href="payment.php" class="dock-item">
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
            <div style="width: 2px; height: 40px; background: rgba(255,255,255,0.15);"></div>
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