<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../function.php';
// فراخوانی کتابخانه تاریخ شمسی
if (file_exists('../jdf.php')) require_once '../jdf.php';

// Authentication
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if( !isset($_SESSION["user"]) || !$result ){ header('Location: login.php'); exit; }

// --- Bulk Actions Handler ---
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_ids'])){
    $ids = json_decode($_POST['bulk_delete_ids'], true);
    if(is_array($ids) && !empty($ids)){
        $stmt = $pdo->prepare("DELETE FROM product WHERE id = :id");
        foreach($ids as $id){
            $stmt->execute([':id' => intval($id)]);
        }
    }
    header("Location: product.php");
    exit;
}

// --- Form Handlers ---

// Add Product
if(isset($_POST['nameproduct']) && $_POST['nameproduct'] !== ''){
    $randomString = bin2hex(random_bytes(2));
    $userdata['data_limit_reset'] = "no_reset";
    
    // Check duplicate
    $product = select("product","*","name_product",$_POST['nameproduct'],"count");
    if($product != 0){
        echo "<script>alert('محصول از قبل وجود دارد'); window.location.href='product.php';</script>";
        exit;
    }
    
    $hidepanel = "{}";
    $oneBuy = isset($_POST['one_buy_status']) && $_POST['one_buy_status'] === '1' ? '1' : '0';
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,:one_buy_status)");
    
    $stmt->bindParam(':name_product', $_POST['nameproduct'], PDO::PARAM_STR);
    $stmt->bindParam(':code_product', $randomString);
    $stmt->bindParam(':price_product', $_POST['price_product'], PDO::PARAM_STR);
    $stmt->bindParam(':Volume_constraint', $_POST['volume_product'], PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $_POST['time_product'], PDO::PARAM_STR);
    $stmt->bindParam(':Location', $_POST['namepanel'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $_POST['agent_product'], PDO::PARAM_STR);
    $stmt->bindParam(':data_limit_reset', $userdata['data_limit_reset']);
    $stmt->bindParam(':category', $_POST['cetegory_product']  , PDO::PARAM_STR);
    $stmt->bindParam(':note', $_POST['note_product']  , PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $hidepanel);
    $stmt->bindParam(':one_buy_status', $oneBuy, PDO::PARAM_STR);
    $stmt->execute();
    
    header("Location: product.php");
    exit;
}

// Swap Product IDs (Move Row)
if(isset($_GET['oneproduct']) && isset($_GET['toweproduct'])){
    update("product", "id", 10000, "id", $_GET['oneproduct']);
    update("product", "id", intval($_GET['oneproduct']), "id", intval($_GET['toweproduct']));
    update("product", "id", intval($_GET['toweproduct']), "id", 10000);
    header("Location: product.php");
    exit;
}

// Delete Single Product
if(isset($_GET['removeid']) && $_GET['removeid']){
    $rid = (int) $_GET['removeid'];
    $stmt = $pdo->prepare("DELETE FROM product WHERE id = :id");
    $stmt->bindParam(':id',$rid,PDO::PARAM_INT);
    $stmt->execute();
    header("Location: product.php");
    exit;
}

// --- Fetch Data ---
$query = $pdo->prepare("SELECT * FROM product ORDER BY id DESC");
$query->execute();
$listinvoice = $query->fetchAll();

$query = $pdo->prepare("SELECT * FROM marzban_panel");
$query->execute();
$listpanel = $query->fetchAll();

// Export CSV
if(isset($_GET['export']) && $_GET['export']==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products-'.date('Y-m-d').'.csv');
    $out = fopen('php://output','w');
    fputs($out, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($out, ['ID','Code','Name','Price','Volume','Time','Location','Agent','Reset','Category']);
    foreach($listinvoice as $row){
        fputcsv($out, [
            $row['id'], $row['code_product'], $row['name_product'],
            $row['price_product'], $row['Volume_constraint'], $row['Service_time'],
            $row['Location'], $row['agent'], $row['data_limit_reset'],
            $row['category'] ?? ''
        ]);
    }
    fclose($out);
    exit();
}

// Stats Calculation
$totalProducts = count($listinvoice);
$totalValue = 0;
foreach($listinvoice as $p) {
    if(is_numeric($p['price_product'])) $totalValue += $p['price_product'];
}

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت محصولات</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">

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
                radial-gradient(circle at 80% 10%, rgba(188, 19, 254, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 20% 90%, rgba(0, 243, 255, 0.08) 0%, transparent 45%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Full Height Container --- */
        .container-fluid-custom {
            width: 100%; padding: 30px 4%; max-width: 1920px; margin: 0 auto;
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
        
        .c-prod { color: var(--neon-purple); filter: drop-shadow(0 0 10px rgba(188,19,254,0.3)); }
        .c-val { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0,255,136,0.3)); }

        /* --- Glass Panel (Spacious) --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 35px;
            flex-grow: 1; /* Key for filling space */
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
            min-height: 500px;
        }

        /* --- Action Toolbar --- */
        .toolbar-header {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center; margin-bottom: 30px;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        
        .search-box {
            flex: 1; min-width: 250px; position: relative;
        }
        .input-search {
            width: 100%; height: 60px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: #fff;
            padding: 0 55px 0 25px; border-radius: 16px; font-size: 1.2rem; transition: 0.3s;
        }
        .input-search:focus { border-color: var(--neon-blue); box-shadow: 0 0 20px rgba(0,243,255,0.25); background: rgba(0,0,0,0.5); }
        .search-icon { position: absolute; right: 25px; top: 20px; color: #888; font-size: 1.3rem; pointer-events: none; }

        .btn-act {
            height: 60px; padding: 0 30px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; color: var(--text-sec); font-size: 1.1rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 12px;
            transition: 0.3s; text-decoration: none; white-space: nowrap;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; transform: translateY(-3px); color: #fff; }
        
        .btn-primary-glow { background: var(--neon-blue); color: #000; border: none; font-weight: 800; }
        .btn-primary-glow:hover { background: #fff; box-shadow: 0 0 25px var(--neon-blue); color: #000; }
        
        .btn-danger-glow { color: var(--neon-red); border-color: rgba(255,7,58,0.4); }
        .btn-danger-glow:hover { background: rgba(255,7,58,0.15); box-shadow: 0 0 20px var(--neon-red); border-color: var(--neon-red); color: #fff; }

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

        .price-tag { color: var(--neon-gold); font-weight: 800; font-size: 1.25rem; font-family: monospace; }
        .code-tag { 
            background: rgba(255,255,255,0.1); padding: 6px 12px; border-radius: 8px; 
            font-family: monospace; font-size: 1rem; color: var(--neon-blue);
        }

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
            .toolbar-header { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
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
                <h1>مدیریت محصولات</h1>
                <p>
                    <i class="fa-solid fa-boxes-stacked" style="color: var(--neon-amber);"></i>
                    لیست سرویس‌ها و تنظیمات فروشگاه
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
                    <div class="val"><?php echo number_format($totalProducts); ?></div>
                    <div class="lbl">تعداد محصولات فعال</div>
                </div>
                <i class="fa-solid fa-cubes-stacked stat-icon c-prod"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($totalValue); ?></div>
                    <div class="lbl">ارزش کل محصولات</div>
                </div>
                <i class="fa-solid fa-coins stat-icon c-val"></i>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="glass-panel anim d-2">
            
            <!-- Toolbar -->
            <div class="toolbar-header">
                <div class="search-box">
                    <i class="fa-solid fa-search search-icon"></i>
                    <input type="text" id="prodQuickSearch" class="input-search" placeholder="جستجو در نام، کد یا قیمت...">
                </div>
                
                <a href="#addproduct" data-toggle="modal" class="btn-act btn-primary-glow">
                    <i class="fa-solid fa-plus-circle"></i> افزودن محصول جدید
                </a>
                
                <a href="#moveradif" data-toggle="modal" class="btn-act">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> جابجایی
                </a>
                
                <a href="productedit.php" class="nav-box">
  <i class="fa-solid fa-wallet"></i>
  <span>ویرایش</span>
</a>

<style>
  .nav-box {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 4px;

    width: 90px;
    height: 70px;

    background: linear-gradient(135deg, #6a7cff, #8f9bff);
    color: #fff;
    text-decoration: none;

    border-radius: 16px;
    box-shadow: 0 6px 18px rgba(106,124,255,.35);

    transition: all .2s ease;
  }

  .nav-box i {
    font-size: 18px;
  }

  .nav-box span {
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
    text-align: center;
  }

  .nav-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 22px rgba(106,124,255,.45);
  }
</style>

            </div>

            <!-- Bulk Selection Toolbar -->
            <div style="display: flex; gap: 15px; margin-bottom: 25px; align-items: center; flex-wrap: wrap;">
                <span id="prodSelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.2rem;">0 انتخاب</span>
                <button class="btn-act" id="prodSelectAll" style="height: 50px; font-size: 1rem;">همه</button>
                <button class="btn-act" id="prodDeselectAll" style="height: 50px; font-size: 1rem;">هیچ</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act" id="prodCopy" style="height: 50px; font-size: 1rem;">کپی ID</button>
                <button class="btn-act" id="prodCopyCodes" style="height: 50px; font-size: 1rem;">کپی کد</button>
                
                <!-- Hidden form for bulk delete -->
                <form method="POST" id="bulkDeleteForm" style="display:none;">
                    <input type="hidden" name="bulk_delete_ids" id="bulkDeleteInput">
                </form>
                <button class="btn-act btn-danger-glow" id="prodRemoveBulk" style="height: 50px; font-size: 1rem;">حذف گروهی</button>
            </div>

            <!-- Table -->
            <?php if(!$totalProducts): ?>
                <div style="text-align: center; padding: 80px; color: var(--text-sec); flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                    <i class="fa-solid fa-box-open" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.3;"></i>
                    <h3 style="font-size: 2rem;">محصولی یافت نشد</h3>
                    <p style="font-size: 1.2rem;">هنوز محصولی اضافه نکرده‌اید.</p>
                </div>
            <?php else: ?>
                <div class="table-container-flex">
                    <table class="glass-table" id="sample_1">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><i class="fa-solid fa-check"></i></th>
                                <th>شناسه</th>
                                <th>کد محصول</th>
                                <th>نام سرویس</th>
                                <th>قیمت (تومان)</th>
                                <th>حجم</th>
                                <th>زمان (روز)</th>
                                <th>لوکیشن</th>
                                <th>سطح</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($listinvoice as $list): 
                                $cat = $list['category'] ?? '---';
                            ?>
                            <tr>
                                <td><input type="checkbox" class="custom-check prod-check" value="<?php echo $list['id']; ?>" data-code="<?php echo $list['code_product']; ?>"></td>
                                <td style="font-family: monospace; color: var(--neon-blue); font-weight: 700;"><?php echo $list['id']; ?></td>
                                <td><span class="code-tag"><?php echo $list['code_product']; ?></span></td>
                                <td style="font-weight: 800; font-size: 1.2rem;"><?php echo htmlspecialchars($list['name_product']); ?></td>
                                <td class="price-tag"><?php echo number_format($list['price_product']); ?></td>
                                <td><?php echo $list['Volume_constraint']; ?> GB</td>
                                <td><?php echo $list['Service_time']; ?></td>
                                <td><?php echo $list['Location']; ?></td>
                                <td><?php echo ($list['agent']=='f')?'عادی':(($list['agent']=='n')?'نماینده':'ارشد'); ?></td>
                                <td>
                                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                        <a href="productedit.php?id=<?php echo $list['id']; ?>" class="btn-act" style="height: 45px; padding: 0 15px; background: rgba(0, 243, 255, 0.1); border-color: rgba(0, 243, 255, 0.3);">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="product.php?removeid=<?php echo $list['id']; ?>" class="btn-act" style="height: 45px; padding: 0 15px; background: rgba(255, 7, 58, 0.1); border-color: rgba(255, 7, 58, 0.3);" onclick="return confirm('حذف شود؟');">
                                            <i class="fa-solid fa-trash" style="color: var(--neon-red);"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
        </div>

    </div>

    <!-- Modals -->
    
    <!-- Add Product Modal -->
    <div class="modal fade" id="addproduct" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">×</button>
                    <h4 class="modal-title"><i class="fa-solid fa-plus-circle"></i> محصول جدید</h4>
                </div>
                <div class="modal-body">
                    <form action="product.php" method="POST">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color:#aaa; display:block; margin-bottom:8px;">نام محصول</label>
                            <input type="text" name="nameproduct" class="form-control-dark" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color:#aaa; display:block; margin-bottom:8px;">لوکیشن (پنل)</label>
                            <select name="namepanel" class="form-control-dark">
                                <option value="/all">همه پنل‌ها</option>
                                <?php foreach($listpanel as $p){ echo "<option value=\"{$p['name_panel']}\">{$p['name_panel']}</option>"; } ?>
                            </select>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:8px;">قیمت (تومان)</label>
                                <input type="number" name="price_product" class="form-control-dark" required>
                            </div>
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:8px;">حجم (GB)</label>
                                <input type="number" name="volume_product" class="form-control-dark" required>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:8px;">زمان (روز)</label>
                                <input type="number" name="time_product" class="form-control-dark" required>
                            </div>
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:8px;">سطح دسترسی</label>
                                <select name="agent_product" class="form-control-dark">
                                    <option value="f">کاربر عادی</option>
                                    <option value="n">نماینده</option>
                                    <option value="n2">نماینده ارشد</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color:#aaa; display:block; margin-bottom:8px;">توضیحات</label>
                            <input type="text" name="note_product" class="form-control-dark" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color:#aaa; display:block; margin-bottom:8px;">دسته‌بندی</label>
                            <input type="text" name="cetegory_product" class="form-control-dark" required>
                        </div>
                        <div class="form-group">
                            <label style="color:#fff; display:flex; align-items:center; gap:10px; cursor:pointer; font-size: 1.1rem;">
                                <input type="checkbox" name="one_buy_status" value="1" class="custom-check">
                                فقط برای خرید اول فعال باشد
                            </label>
                        </div>
                        <button type="submit" class="btn-modal">افزودن محصول</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Move Product Modal -->
    <div class="modal fade" id="moveradif" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">×</button>
                    <h4 class="modal-title" style="color: var(--neon-purple);"><i class="fa-solid fa-arrow-right-arrow-left"></i> جابجایی ردیف</h4>
                </div>
                <div class="modal-body">
                    <form action="product.php" method="GET">
                        <div class="form-group" style="margin-bottom: 25px;">
                            <label style="color:#aaa; display:block; margin-bottom:8px;">شناسه محصول اول</label>
                            <input type="number" name="oneproduct" class="form-control-dark" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 25px;">
                            <label style="color:#aaa; display:block; margin-bottom:8px;">شناسه محصول دوم</label>
                            <input type="number" name="toweproduct" class="form-control-dark" required>
                        </div>
                        <button type="submit" class="btn-modal" style="background: var(--neon-purple); color:#fff;">جابجایی</button>
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
            <a href="user.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-users"></i></div>
                <span class="dock-label">کاربران</span>
            </a>
            <a href="product.php" class="dock-item active">
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
            // Quick Search
            $('#prodQuickSearch').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $("#sample_1 tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });

            // Checkbox Logic
            function updateCount() {
                let cnt = $('.prod-check:checked').length;
                $('#prodSelCount').text(cnt + ' انتخاب');
            }
            $('#prodSelectAll').click(function(){ $('.prod-check').prop('checked', true); updateCount(); });
            $('#prodDeselectAll').click(function(){ $('.prod-check').prop('checked', false); updateCount(); });
            $(document).on('change', '.prod-check', updateCount);

            // Copy IDs
            $('#prodCopy').click(function(){
                let ids = [];
                $('.prod-check:checked').each(function(){ ids.push($(this).val()); });
                if(ids.length){ navigator.clipboard.writeText(ids.join(',')); alert('کپی شد!'); }
                else alert('هیچ موردی انتخاب نشده');
            });

            // Copy Codes
            $('#prodCopyCodes').click(function(){
                let codes = [];
                $('.prod-check:checked').each(function(){ codes.push($(this).data('code')); });
                if(codes.length){ navigator.clipboard.writeText(codes.join(',')); alert('کدها کپی شد!'); }
                else alert('هیچ موردی انتخاب نشده');
            });

            // Bulk Delete with POST
            $('#prodRemoveBulk').click(function(){
                let ids = [];
                $('.prod-check:checked').each(function(){ ids.push($(this).val()); });
                
                if(!ids.length) { alert('هیچ محصولی انتخاب نشده است!'); return; }
                
                if(!confirm('آیا از حذف گروهی ' + ids.length + ' محصول اطمینان دارید؟')) return;
                
                // Set the hidden input value to JSON string of IDs
                $('#bulkDeleteInput').val(JSON.stringify(ids));
                
                // Submit the form
                $('#bulkDeleteForm').submit();
            });
        });
    </script>

</body>
</html>