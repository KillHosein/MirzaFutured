<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../function.php';

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
    <title>مدیریت محصولات | پنل حرفه‌ای</title>
    
    <!-- Fonts & Icons -->
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
                radial-gradient(circle at 80% 10%, rgba(188, 19, 254, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 20% 90%, rgba(0, 243, 255, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 160px;
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Animations --- */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .anim { animation: fadeIn 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; }

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

        /* --- Stats Grid --- */
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
        
        .c-prod { color: var(--neon-purple); filter: drop-shadow(0 0 10px rgba(188,19,254,0.3)); }
        .c-val { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0,255,136,0.3)); }

        /* --- Glass Panel --- */
        .glass-panel {
            background: var(--bg-glass); backdrop-filter: blur(30px);
            border: var(--border-glass); border-radius: var(--radius-lg);
            padding: 30px; margin-bottom: 40px; box-shadow: var(--shadow-float);
        }

        /* --- Action Toolbar --- */
        .toolbar-header {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center; margin-bottom: 30px;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .search-box {
            flex: 1; min-width: 250px; position: relative;
        }
        .input-search {
            width: 100%; height: 55px; background: #000; border: 2px solid #444; color: #fff;
            padding: 0 50px 0 20px; border-radius: 14px; font-size: 1.1rem; transition: 0.3s;
        }
        .input-search:focus { border-color: var(--neon-blue); box-shadow: 0 0 15px rgba(0,243,255,0.2); }
        .search-icon { position: absolute; right: 20px; top: 18px; color: #888; font-size: 1.2rem; pointer-events: none; }

        .btn-act {
            height: 55px; padding: 0 25px;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 14px; color: #fff; font-size: 1rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 10px;
            transition: 0.3s; text-decoration: none; white-space: nowrap;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); transform: translateY(-3px); }
        
        .btn-primary-glow { background: var(--neon-blue); color: #000; border: none; }
        .btn-primary-glow:hover { background: #fff; box-shadow: 0 0 20px var(--neon-blue); color: #000; }
        
        .btn-danger-glow { color: var(--neon-red); border-color: rgba(255,7,58,0.4); }
        .btn-danger-glow:hover { background: rgba(255,7,58,0.15); box-shadow: 0 0 20px var(--neon-red); border-color: var(--neon-red); color: #fff; }

        /* --- Table --- */
        .table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; font-size: 1.15rem; }
        .glass-table th {
            text-align: right; padding: 20px 25px; color: var(--text-muted);
            font-weight: 700; border-bottom: 2px solid rgba(255,255,255,0.05); white-space: nowrap;
        }
        .glass-table tbody tr { background: rgba(255,255,255,0.03); transition: 0.3s; }
        .glass-table tbody tr:hover { background: rgba(255,255,255,0.08); transform: scale(1.005); box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .glass-table td {
            padding: 25px 20px; color: #fff; vertical-align: middle;
            border-top: 1px solid rgba(255,255,255,0.04); border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .glass-table td:first-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(255,255,255,0.04); }
        .glass-table td:last-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(255,255,255,0.04); }

        .price-tag { color: var(--neon-amber); font-weight: 800; font-size: 1.25rem; font-family: monospace; }
        .code-tag { 
            background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 6px; 
            font-family: monospace; font-size: 0.95rem; color: var(--neon-blue);
        }

        /* Checkbox */
        .custom-check { width: 24px; height: 24px; border: 2px solid #666; background: transparent; cursor: pointer; appearance: none; border-radius: 6px; position: relative; }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after { content: '✔'; position: absolute; color: #000; top: 0; left: 4px; font-size: 16px; font-weight: 900; }

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
        .modal-content { background: #111; border: 1px solid var(--neon-blue); color: #fff; border-radius: 20px; box-shadow: 0 0 60px rgba(0,0,0,0.8); }
        .modal-header { border-bottom: 1px solid #333; padding: 25px; }
        .modal-title { font-weight: 800; color: var(--neon-blue); }
        .modal-body { padding: 30px; }
        .close { color: #fff; opacity: 0.8; text-shadow: none; font-size: 2rem; margin-top: -5px; }
        
        .form-control-dark {
            background: #050505; border: 2px solid #333; color: #fff;
            height: 55px; border-radius: 12px; padding: 0 20px; font-size: 1.1rem; width: 100%; transition: 0.3s;
        }
        .form-control-dark:focus { border-color: var(--neon-blue); outline: none; box-shadow: 0 0 20px rgba(0,243,255,0.25); }
        
        .btn-modal { width: 100%; height: 55px; background: var(--neon-blue); color: #000; border: none; border-radius: 12px; font-size: 1.2rem; font-weight: 800; margin-top: 20px; transition: 0.3s; }
        .btn-modal:hover { box-shadow: 0 0 30px var(--neon-blue); transform: translateY(-3px); }

        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .dock { width: 95%; padding: 10px 15px; gap: 5px; justify-content: space-between; }
            .dock-icon { width: 45px; height: 45px; font-size: 1.5rem; }
            .toolbar-header { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .actions-row { justify-content: center; }
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
                    <span style="opacity:0.3; margin:0 15px;">|</span>
                    <?php echo $todayDate; ?>
                </p>
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
                
                <a href="?export=csv" class="btn-act">
                    <i class="fa-solid fa-file-csv"></i> اکسل
                </a>
            </div>

            <!-- Bulk Selection Toolbar -->
            <div style="display: flex; gap: 15px; margin-bottom: 25px; align-items: center; flex-wrap: wrap;">
                <span id="prodSelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.1rem;">0 انتخاب</span>
                <button class="btn-act" id="prodSelectAll" style="height: 45px; font-size: 0.9rem;">همه</button>
                <button class="btn-act" id="prodDeselectAll" style="height: 45px; font-size: 0.9rem;">هیچ</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act" id="prodCopy" style="height: 45px; font-size: 0.9rem;">کپی ID</button>
                <button class="btn-act" id="prodCopyCodes" style="height: 45px; font-size: 0.9rem;">کپی کد</button>
                
                <!-- Hidden form for bulk delete -->
                <form method="POST" id="bulkDeleteForm" style="display:none;">
                    <input type="hidden" name="bulk_delete_ids" id="bulkDeleteInput">
                </form>
                <button class="btn-act btn-danger-glow" id="prodRemoveBulk" style="height: 45px; font-size: 0.9rem;">حذف گروهی</button>
            </div>

            <!-- Table -->
            <?php if(!$totalProducts): ?>
                <div style="text-align: center; padding: 80px; color: var(--text-dim);">
                    <i class="fa-solid fa-box-open" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.4;"></i>
                    <h3 style="font-size: 2rem;">محصولی یافت نشد</h3>
                    <p style="font-size: 1.2rem;">هنوز محصولی اضافه نکرده‌اید.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
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
                                        <a href="productedit.php?id=<?php echo $list['id']; ?>" class="btn-act" style="height: 40px; padding: 0 15px; background: rgba(0, 243, 255, 0.1); border-color: rgba(0, 243, 255, 0.3);">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="product.php?removeid=<?php echo $list['id']; ?>" class="btn-act" style="height: 40px; padding: 0 15px; background: rgba(255, 7, 58, 0.1); border-color: rgba(255, 7, 58, 0.3);" onclick="return confirm('حذف شود؟');">
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
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="color:#aaa; display:block; margin-bottom:5px;">نام محصول</label>
                            <input type="text" name="nameproduct" class="form-control-dark" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="color:#aaa; display:block; margin-bottom:5px;">لوکیشن (پنل)</label>
                            <select name="namepanel" class="form-control-dark">
                                <option value="/all">همه پنل‌ها</option>
                                <?php foreach($listpanel as $p){ echo "<option value=\"{$p['name_panel']}\">{$p['name_panel']}</option>"; } ?>
                            </select>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:5px;">قیمت (تومان)</label>
                                <input type="number" name="price_product" class="form-control-dark" required>
                            </div>
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:5px;">حجم (GB)</label>
                                <input type="number" name="volume_product" class="form-control-dark" required>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:5px;">زمان (روز)</label>
                                <input type="number" name="time_product" class="form-control-dark" required>
                            </div>
                            <div>
                                <label style="color:#aaa; display:block; margin-bottom:5px;">سطح دسترسی</label>
                                <select name="agent_product" class="form-control-dark">
                                    <option value="f">کاربر عادی</option>
                                    <option value="n">نماینده</option>
                                    <option value="n2">نماینده ارشد</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="color:#aaa; display:block; margin-bottom:5px;">توضیحات</label>
                            <input type="text" name="note_product" class="form-control-dark" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="color:#aaa; display:block; margin-bottom:5px;">دسته‌بندی</label>
                            <input type="text" name="cetegory_product" class="form-control-dark" required>
                        </div>
                        <div class="form-group">
                            <label style="color:#fff; display:flex; align-items:center; gap:10px; cursor:pointer;">
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
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color:#aaa; display:block; margin-bottom:5px;">شناسه محصول اول</label>
                            <input type="number" name="oneproduct" class="form-control-dark" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color:#aaa; display:block; margin-bottom:5px;">شناسه محصول دوم</label>
                            <input type="number" name="toweproduct" class="form-control-dark" required>
                        </div>
                        <button type="submit" class="btn-modal" style="background: var(--neon-purple); color:#fff;">جابجایی</button>
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
            <a href="product.php" class="dock-item active">
                <div class="dock-icon"><i class="fa-solid fa-box-open"></i></div>
                <span class="dock-label">محصولات</span>
            </a>
            <a href="server_status.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <span class="dock-label">ادمین</span>
            </a>
            <a href="payment.php" class="dock-item">
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
    </scr