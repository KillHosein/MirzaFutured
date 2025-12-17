<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
// فراخوانی کتابخانه تاریخ شمسی
if (file_exists('../jdf.php')) require_once '../jdf.php';

// Authentication
if(!isset($_SESSION["user"])){ header('Location: login.php'); exit; }

$q = $pdo->prepare("SELECT * FROM admin WHERE username=:u");
$q->bindParam(':u', $_SESSION['user'], PDO::PARAM_STR);
$q->execute();
$adminRow = $q->fetch(PDO::FETCH_ASSOC);

if(!$adminRow){ header('Location: login.php'); exit; }

$saved = false;

// --- Export Settings ---
if(isset($_GET['export']) && $_GET['export']==='settings'){
    $generalAll = $pdo->query("SELECT * FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $shopRowsAll = $pdo->query("SELECT * FROM shopSetting")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $shopAll = [];
    foreach($shopRowsAll as $r){ $shopAll[$r['Namevalue']] = $r['value']; }
    
    $payload = [
        'general' => $generalAll,
        'shop' => $shopAll,
    ];
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=panel-settings-'.date('Y-m-d').'.json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- Save Actions ---
if($_SERVER['REQUEST_METHOD']==='POST'){
    // Save General
    if(isset($_POST['action']) && $_POST['action']==='save_general' && isset($_POST['general']) && is_array($_POST['general'])){
        foreach($_POST['general'] as $k=>$v){
            $stmt = $pdo->prepare("UPDATE setting SET `$k` = :v");
            $stmt->bindParam(':v',$v);
            $stmt->execute();
        }
        $saved = true;
    }
    
    // Save Shop
    if(isset($_POST['action']) && $_POST['action']==='save_shop' && isset($_POST['shop']) && is_array($_POST['shop'])){
        foreach($_POST['shop'] as $k=>$v){
            $stmt = $pdo->prepare("UPDATE shopSetting SET value = :v WHERE Namevalue = :n");
            $stmt->bindParam(':v',$v);
            $stmt->bindParam(':n',$k);
            $stmt->execute();
        }
        $saved = true;
    }
    
    // Save Keyboard
    if(isset($_POST['action']) && $_POST['action']==='save_keyboard'){
        if(isset($_POST['keyboard_reset']) && $_POST['keyboard_reset']==='1'){
            $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
            $pdo->exec("UPDATE setting SET keyboardmain = " . $pdo->quote($keyboardmain));
        } else if(isset($_POST['keyboard_json'])){
            $kb = trim($_POST['keyboard_json']);
            $pdo->exec("UPDATE setting SET keyboardmain = " . $pdo->quote($kb));
        }
        $saved = true;
    }
    
    // Import Settings
    if(isset($_POST['action']) && $_POST['action']==='import_settings'){
        $raw = $_POST['import_json'] ?? '';
        $data = json_decode($raw, true);
        if(is_array($data)){
            if(isset($data['general']) && is_array($data['general'])){
                foreach($data['general'] as $k=>$v){
                    $stmt = $pdo->prepare("UPDATE setting SET `$k` = :v");
                    $stmt->bindParam(':v',$v);
                    $stmt->execute();
                }
            }
            if(isset($data['shop']) && is_array($data['shop'])){
                foreach($data['shop'] as $k=>$v){
                    $stmt = $pdo->prepare("UPDATE shopSetting SET value = :v WHERE Namevalue = :n");
                    $stmt->bindParam(':v',$v);
                    $stmt->bindParam(':n',$k);
                    $stmt->execute();
                }
            }
            $saved = true;
        }
    }
}

// Fetch Data
$general = $pdo->query("SELECT * FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$shopRows = $pdo->query("SELECT * FROM shopSetting")->fetchAll(PDO::FETCH_ASSOC);
$shop = [];
foreach($shopRows as $r){ $shop[$r['Namevalue']] = $r['value']; }
$keyboardmain = $general['keyboardmain'] ?? '';

// Labels
$labelMapGeneral = [
  'iplogin' => 'آی‌پی مجاز ورود (IP Whitelist)',
  'Channel_Support' => 'کانال پشتیبانی',
  'Channel_Report' => 'کانال گزارشات',
  'domainhosts' => 'دامنه پنل',
  'bot_token' => 'توکن ربات',
  'admin_id' => 'آیدی عددی ادمین'
];
$labelMapShop = [
  'products_per_page' => 'محصول در صفحه',
  'currency' => 'واحد پول',
  'gateway' => 'درگاه پیش‌فرض',
  'support_contact' => 'تماس با پشتیبانی'
];

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تنظیمات سیستم</title>
    
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
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-gold: #fbbf24;
            --neon-cyan: #22d3ee;
            
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
                radial-gradient(circle at 5% 10%, rgba(34, 211, 238, 0.06) 0%, transparent 45%),
                radial-gradient(circle at 95% 90%, rgba(255, 42, 109, 0.06) 0%, transparent 45%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
        }

        /* --- Full Height Container --- */
        .container-fluid-custom {
            width: 100%; padding: 30px 4%; max-width: 1920px; margin: 0 auto;
            flex-grow: 1; display: flex; flex-direction: column; gap: 30px;
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

        /* --- Grid Layout for Settings --- */
        .settings-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;
        }
        .full-width { grid-column: 1 / -1; }

        /* --- Glass Cards --- */
        .setting-card {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 30px; box-shadow: var(--shadow-card); backdrop-filter: blur(25px);
            display: flex; flex-direction: column; transition: 0.3s;
        }
        .setting-card:hover { border-color: var(--border-highlight); transform: translateY(-5px); }
        
        .card-header {
            display: flex; align-items: center; gap: 15px; margin-bottom: 25px;
            padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .card-icon {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; background: rgba(255,255,255,0.05);
        }
        .card-title h3 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #fff; }
        .card-title span { font-size: 0.9rem; color: var(--text-sec); }

        /* --- Icons Colors --- */
        .ic-blue { color: var(--neon-blue); background: rgba(0, 242, 255, 0.1); }
        .ic-purple { color: var(--neon-purple); background: rgba(192, 38, 211, 0.1); }
        .ic-green { color: var(--neon-green); background: rgba(0, 255, 163, 0.1); }
        .ic-red { color: var(--neon-red); background: rgba(255, 42, 109, 0.1); }
        .ic-gold { color: var(--neon-gold); background: rgba(251, 191, 36, 0.1); }

        /* --- Inputs --- */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--text-sec); margin-bottom: 8px; font-weight: 600; font-size: 1rem; }
        
        .input-readable {
            width: 100%; height: 50px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 15px; border-radius: 14px;
            font-family: inherit; font-size: 1.1rem; transition: 0.3s;
        }
        .input-readable:focus { background: rgba(0,0,0,0.5); border-color: var(--neon-blue); outline: none; box-shadow: 0 0 15px rgba(0, 242, 255, 0.2); }
        
        .input-area {
            width: 100%; min-height: 150px; padding: 15px;
            background: #0d0d10; border: 1px solid rgba(255,255,255,0.1);
            color: #a6accd; border-radius: 14px; font-family: 'Consolas', monospace;
            font-size: 0.95rem; resize: vertical;
        }
        
        /* --- Buttons --- */
        .btn-act {
            height: 50px; padding: 0 25px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; color: #fff; font-size: 1.1rem; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            transition: 0.3s; text-decoration: none; width: 100%;
        }
        .btn-act:hover { transform: translateY(-2px); }
        
        .btn-save { background: var(--neon-blue); color: #000; border: none; }
        .btn-save:hover { box-shadow: 0 0 20px var(--neon-blue); }
        
        .btn-warn { border-color: var(--neon-gold); color: var(--neon-gold); }
        .btn-warn:hover { background: rgba(251, 191, 36, 0.15); box-shadow: 0 0 20px var(--neon-gold); }
        
        .btn-danger-glow { border-color: var(--neon-red); color: var(--neon-red); }
        .btn-danger-glow:hover { background: rgba(255, 42, 109, 0.15); box-shadow: 0 0 20px var(--neon-red); }

        .btn-ip { 
            position: absolute; left: 5px; top: 32px; height: 40px; 
            background: rgba(255,255,255,0.1); border:none; color:#fff; border-radius:10px;
        }

        /* --- Color Pickers --- */
        .color-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .color-item { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 12px; }
        .color-input { width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer; background: none; }

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

        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .dock { width: 95%; justify-content: flex-start; }
            .dock-item { width: 50px; height: 50px; font-size: 1.4rem; }
            .page-title h1 { font-size: 2.5rem; }
            .settings-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>تنظیمات سیستم</h1>
                <p>
                    <i class="fa-solid fa-sliders" style="color: var(--neon-gold);"></i>
                    پیکربندی کلی، فروشگاه و شخصی‌سازی
                </p>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
            </div>
        </header>

        <?php if($saved): ?>
            <div class="alert alert-success" style="background: rgba(0,255,163,0.1); border: 1px solid var(--neon-green); color: var(--neon-green); padding: 15px; border-radius: 12px; margin-bottom: 30px;">
                <i class="fa-solid fa-check-circle"></i> تنظیمات با موفقیت ذخیره شد.
            </div>
        <?php endif; ?>

        <div class="settings-grid anim d-1">
            
            <!-- 1. General Settings -->
            <div class="setting-card">
                <div class="card-header">
                    <div class="card-icon ic-blue"><i class="fa-solid fa-earth-americas"></i></div>
                    <div class="card-title">
                        <h3>تنظیمات عمومی</h3>
                        <span>اطلاعات پایه سیستم</span>
                    </div>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="save_general">
                    <?php foreach($general as $k=>$v){ if($k==='keyboardmain' || $k==='iplogin') continue; 
                        $label = $labelMapGeneral[$k] ?? $k; ?>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars($label); ?></label>
                            <input type="text" name="general[<?php echo $k; ?>]" class="input-readable" value="<?php echo htmlspecialchars($v); ?>">
                        </div>
                    <?php } ?>
                    <button type="submit" class="btn-act btn-save"><i class="fa-solid fa-save"></i> ذخیره عمومی</button>
                </form>
            </div>

            <!-- 2. Security (IP) -->
            <div class="setting-card">
                <div class="card-header">
                    <div class="card-icon ic-red"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="card-title">
                        <h3>امنیت و دسترسی</h3>
                        <span>محدودسازی آی‌پی مدیر</span>
                    </div>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="save_general">
                    <div class="form-group" style="position: relative;">
                        <label>آی‌پی مجاز (خالی = همه)</label>
                        <input type="text" id="iploginField" name="general[iplogin]" class="input-readable" style="padding-left: 120px;" value="<?php echo htmlspecialchars($general['iplogin'] ?? ''); ?>">
                        <button type="button" id="useMyIp" class="btn-ip">IP من: <?php echo $_SERVER['REMOTE_ADDR']; ?></button>
                    </div>
                    <button type="submit" class="btn-act btn-save"><i class="fa-solid fa-lock"></i> ذخیره امنیت</button>
                </form>
            </div>

            <!-- 3. Shop Settings -->
            <div class="setting-card">
                <div class="card-header">
                    <div class="card-icon ic-green"><i class="fa-solid fa-store"></i></div>
                    <div class="card-title">
                        <h3>تنظیمات فروشگاه</h3>
                        <span>پیکربندی درگاه و نمایش</span>
                    </div>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="save_shop">
                    <?php foreach($shop as $k=>$v){ 
                        $label = $labelMapShop[$k] ?? $k; ?>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars($label); ?></label>
                            <input type="text" name="shop[<?php echo $k; ?>]" class="input-readable" value="<?php echo htmlspecialchars($v); ?>">
                        </div>
                    <?php } ?>
                    <button type="submit" class="btn-act btn-save"><i class="fa-solid fa-cart-shopping"></i> ذخیره فروشگاه</button>
                </form>
            </div>

            <!-- 4. Keyboard Layout (Full Width) -->
            <div class="setting-card full-width">
                <div class="card-header">
                    <div class="card-icon ic-purple"><i class="fa-solid fa-keyboard"></i></div>
                    <div class="card-title">
                        <h3>چیدمان کیبورد ربات</h3>
                        <span>ساختار JSON دکمه‌های ربات تلگرام</span>
                    </div>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="save_keyboard">
                    <div class="form-group">
                        <textarea name="keyboard_json" class="input-area" rows="10"><?php echo htmlspecialchars($keyboardmain); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="keyboard_reset" value="1" class="custom-check">
                            بازنشانی به حالت پیش‌فرض
                        </label>
                    </div>
                    <button type="submit" class="btn-act btn-save"><i class="fa-solid fa-check"></i> ذخیره کیبورد</button>
                </form>
            </div>

            <!-- 5. Backup & Restore -->
            <div class="setting-card full-width">
                <div class="card-header">
                    <div class="card-icon ic-gold"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <div class="card-title">
                        <h3>پشتیبان‌گیری و بازگردانی</h3>
                        <span>مدیریت فایل تنظیمات</span>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <p style="color:#aaa; margin-bottom:15px;">دانلود فایل کامل تنظیمات فعلی:</p>
                        <a href="settings.php?export=settings" class="btn-act btn-warn" style="justify-content: center;">
                            <i class="fa-solid fa-download"></i> دانلود پشتیبان
                        </a>
                    </div>
                    <div>
                        <p style="color:#aaa; margin-bottom:15px;">بازگردانی تنظیمات از فایل JSON:</p>
                        <form method="post">
                            <input type="hidden" name="action" value="import_settings">
                            <textarea name="import_json" class="input-readable" style="height: 50px; padding-top:12px; margin-bottom:10px;" placeholder="کد JSON را اینجا جایگذاری کنید..."></textarea>
                            <button type="submit" class="btn-act btn-danger-glow"><i class="fa-solid fa-upload"></i> بازگردانی</button>
                        </form>
                    </div>
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
            <a href="settings.php" class="dock-item active">
                <div class="dock-icon"><i class="fa-solid fa-gear"></i></div>
                <span class="dock-label">تنظیمات</span>
            </a>
            <a href="login.php" class="dock-item" style="color: var(--neon-red);">
                <div class="dock-icon"><i class="fa-solid fa-power-off"></i></div>
                <span class="dock-label">خروج</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    
    <script>
        // Use My IP Logic
        document.getElementById('useMyIp').addEventListener('click', function(){
            var ip = "<?php echo $_SERVER['REMOTE_ADDR']; ?>";
            document.getElementById('iploginField').value = ip;
        });
        
        // JSON Validation Visual
        var kbTextarea = document.querySelector('textarea[name="keyboard_json"]');
        if(kbTextarea){
            kbTextarea.addEventListener('blur', function(){
                var v = kbTextarea.value.trim();
                if(v.length === 0) return;
                try{ JSON.parse(v); kbTextarea.style.borderColor = '#00ff88'; }
                catch(e){ kbTextarea.style.borderColor = '#ff004c'; }
            });
        }
    </script>

</body>
</html>