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

$statusmessage = false;
$infomesssage = "";
$id_product = htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8');
$product = select("product","*","id",$id_product,"select");

if($product == false){
    $statusmessage = true;
    $infomesssage ="محصول پیدا نشد";
} else {
    if(isset($_GET['action']) && $_GET['action'] == "save"){
        $name_product = htmlspecialchars($_POST['name_product'], ENT_QUOTES, 'UTF-8');
        $prodcutcheck = select("product","*","name_product",$name_product,"count");
        
        // Check if name changed and new name exists
        if($product['name_product'] != $name_product && $prodcutcheck != 0){
            $statusmessage = true;
            $infomesssage ="نام محصول تکراری است.";
        } else {
            if($product['name_product'] != $name_product) update("product","name_product",$name_product,"id",$id_product);
            
            $price_product = htmlspecialchars($_POST['price_product'], ENT_QUOTES, 'UTF-8');
            if(is_numeric($price_product) && $product['price_product'] != $price_product) update("product","price_product",$price_product,"id",$id_product);
            
            $Volume_constraint = htmlspecialchars($_POST['Volume_constraint'], ENT_QUOTES, 'UTF-8');
            if(is_numeric($Volume_constraint) && $product['Volume_constraint'] != $Volume_constraint) update("product","Volume_constraint",$Volume_constraint,"id",$id_product);
            
            $Service_time = htmlspecialchars($_POST['Service_time'], ENT_QUOTES, 'UTF-8');
            if(is_numeric($Service_time) && $product['Service_time'] != $Service_time) update("product","Service_time",$Service_time,"id",$id_product);
            
            $agent = htmlspecialchars($_POST['agent'], ENT_QUOTES, 'UTF-8');
            if(in_array($agent,['f','n','n2']) && $product['agent'] != $agent) update("product","agent",$agent,"id",$id_product);
            
            $category = htmlspecialchars($_POST['category'], ENT_QUOTES, 'UTF-8');
            if($product['category'] != $category) update("product","category",$category,"id",$id_product);
            
            $note = htmlspecialchars($_POST['note'], ENT_QUOTES, 'UTF-8');
            if($product['note'] != $note) update("product","note",$note,"id",$id_product);
            
            if(!$statusmessage){
                 header('Location: product.php');
                 exit;
            }
        }
    }
}

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ویرایش محصول</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            /* Theme Core */
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.75);
            --bg-glass: rgba(20, 20, 25, 0.85);
            --bg-dock: rgba(10, 10, 15, 0.95);
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-amber: #fbbf24;
            
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
                radial-gradient(circle at 10% 20%, rgba(34, 211, 238, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 90% 80%, rgba(192, 38, 211, 0.08) 0%, transparent 45%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
        }

        /* --- Full Height Container --- */
        .container-fluid-custom {
            width: 100%; padding: 30px 4%; max-width: 1200px; margin: 0 auto;
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

        /* --- Glass Panel --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 40px;
            flex-grow: 1;
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
        }

        /* --- Inputs --- */
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; color: var(--text-sec); font-size: 1.1rem; margin-bottom: 10px; font-weight: 600; }
        
        .input-readable {
            width: 100%; height: 60px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 20px; border-radius: 16px;
            font-family: inherit; font-size: 1.2rem; transition: 0.3s;
        }
        .input-readable:focus { background: rgba(0,0,0,0.5); border-color: var(--neon-blue); outline: none; box-shadow: 0 0 20px rgba(0, 242, 255, 0.2); }
        select.input-readable { cursor: pointer; }
        select.input-readable option { background: #111; color: #fff; }

        /* --- Buttons --- */
        .btn-act {
            height: 60px; padding: 0 30px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; color: var(--text-sec); font-size: 1.2rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            transition: 0.3s; text-decoration: none; width: 100%;
        }
        .btn-act:hover { transform: translateY(-3px); }
        
        .btn-green-glow { background: var(--neon-green); color: #000; border: none; font-weight: 800; }
        .btn-green-glow:hover { background: #fff; box-shadow: 0 0 30px var(--neon-green); color: #000; }
        
        .btn-back { border-color: var(--neon-blue); color: var(--neon-blue); }
        .btn-back:hover { background: rgba(0, 242, 255, 0.1); box-shadow: 0 0 20px var(--neon-blue); }

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
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>ویرایش محصول</h1>
                <p>
                    <i class="fa-solid fa-pen-to-square" style="color: var(--neon-blue);"></i>
                    تغییر مشخصات و تنظیمات محصول
                </p>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
            </div>
        </header>

        <!-- Alert -->
        <?php if($statusmessage): ?>
            <div style="background: rgba(255, 42, 109, 0.1); border: 1px solid var(--neon-red); color: var(--neon-red); padding: 20px; border-radius: 16px; margin-bottom: 30px; font-size: 1.2rem; display: flex; align-items: center; gap: 15px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem;"></i>
                <?php echo $infomesssage; ?>
            </div>
        <?php endif; ?>

        <!-- Main Form Panel -->
        <div class="glass-panel anim d-1">
            
            <?php if(!$statusmessage || $product): ?>
                <form method="post" action="productedit.php?action=save&id=<?php echo $id_product ?>">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px;">
                        <h3 style="margin:0; font-weight: 800; font-size: 1.8rem; color: #fff;">مشخصات اصلی</h3>
                        <a href="product.php" class="btn-act btn-back" style="width: auto; height: 50px; padding: 0 25px;">
                            <i class="fa-solid fa-arrow-left"></i> بازگشت
                        </a>
                    </div>

                    <div class="form-group">
                        <label>نام محصول</label>
                        <input type="text" name="name_product" class="input-readable" value="<?php echo htmlspecialchars($product['name_product']); ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div class="form-group">
                            <label>قیمت (تومان)</label>
                            <input type="number" name="price_product" class="input-readable" value="<?php echo htmlspecialchars($product['price_product']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>حجم (GB)</label>
                            <input type="number" name="Volume_constraint" class="input-readable" value="<?php echo htmlspecialchars($product['Volume_constraint']); ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div class="form-group">
                            <label>زمان (روز)</label>
                            <input type="number" name="Service_time" class="input-readable" value="<?php echo htmlspecialchars($product['Service_time']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>گروه کاربری</label>
                            <select name="agent" class="input-readable">
                                <option value="f" <?php echo $product['agent']==='f'?'selected':''; ?>>کاربر عادی</option>
                                <option value="n" <?php echo $product['agent']==='n'?'selected':''; ?>>نماینده</option>
                                <option value="n2" <?php echo $product['agent']==='n2'?'selected':''; ?>>نماینده ارشد</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>دسته‌بندی</label>
                        <input type="text" name="category" class="input-readable" value="<?php echo htmlspecialchars($product['category']); ?>">
                    </div>

                    <div class="form-group">
                        <label>توضیحات / یادداشت</label>
                        <input type="text" name="note" class="input-readable" value="<?php echo htmlspecialchars($product['note']); ?>">
                    </div>

                    <button type="submit" class="btn-act btn-green-glow" style="margin-top: 20px;">
                        <i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات
                    </button>

                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 50px;">
                    <a href="product.php" class="btn-act btn-back" style="width: auto;">بازگشت به لیست محصولات</a>
                </div>
            <?php endif; ?>
            
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

</body>
</html>