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

// Delete Action
if(isset($_GET['removeid']) && $_GET['removeid']){
    $stmt = $pdo->prepare("DELETE FROM cancel_service WHERE id = :id");
    $stmt->bindParam(":id", $_GET['removeid'], PDO::PARAM_INT);
    $stmt->execute();
    header("Location: cancelService.php");
    exit;
}

// Fetch Data
$query = $pdo->prepare("SELECT * FROM cancel_service ORDER BY id DESC");
$query->execute();
$listcencel = $query->fetchAll();

// Stats
$totalRequests = count($listcencel);
$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>درخواست‌های لغو</title>
    
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
                radial-gradient(circle at 10% 10%, rgba(255, 42, 109, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 90% 90%, rgba(0, 242, 255, 0.08) 0%, transparent 45%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
        }

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

        /* --- Stats Card (Single Wide) --- */
        .stat-banner {
            background: var(--bg-card); border: var(--border-subtle); border-radius: 24px;
            padding: 30px 40px; display: flex; align-items: center; gap: 30px;
            box-shadow: var(--shadow-card); margin-bottom: 10px;
        }
        .sb-icon { 
            width: 80px; height: 80px; border-radius: 20px; 
            background: rgba(255, 42, 109, 0.1); color: var(--neon-red);
            display: flex; align-items: center; justify-content: center; font-size: 2.5rem;
        }
        .sb-info .val { font-size: 3rem; font-weight: 900; line-height: 1; color: #fff; }
        .sb-info .lbl { font-size: 1.2rem; color: var(--text-sec); margin-top: 5px; }

        /* --- Glass Panel (Spacious) --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 35px;
            flex-grow: 1; /* Key for filling space */
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
            min-height: 500px;
        }

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

        /* Buttons */
        .btn-act {
            height: 50px; padding: 0 25px;
            background: rgba(255, 7, 58, 0.1); border: 1px solid rgba(255, 7, 58, 0.3);
            border-radius: 14px; color: var(--neon-red); font-size: 1rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 10px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { 
            background: rgba(255, 7, 58, 0.2); 
            box-shadow: 0 0 20px rgba(255, 7, 58, 0.2); 
            transform: translateY(-2px); color: #fff; 
        }

        /* Badges */
        .badge {
            padding: 8px 16px; border-radius: 50px; font-size: 0.95rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-status { background: rgba(255, 193, 7, 0.15); color: var(--neon-amber); border: 1px solid rgba(255, 193, 7, 0.3); }

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
                <h1>درخواست‌های لغو</h1>
                <p>
                    <i class="fa-solid fa-user-xmark" style="color: var(--neon-red);"></i>
                    مدیریت درخواست‌های لغو سرویس کاربران
                </p>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
            </div>
        </header>

        <!-- Stats -->
        <div class="stat-banner anim d-1">
            <div class="sb-icon"><i class="fa-solid fa-trash-can"></i></div>
            <div class="sb-info">
                <div class="val"><?php echo number_format($totalRequests); ?></div>
                <div class="lbl">تعداد درخواست‌های در انتظار بررسی</div>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="glass-panel anim d-2">
            
            <?php if(empty($listcencel)): ?>
                <div style="text-align: center; padding: 80px; color: var(--text-sec); flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                    <i class="fa-solid fa-clipboard-check" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.3;"></i>
                    <h3 style="font-size: 2rem;">هیچ درخواستی وجود ندارد</h3>
                    <p style="font-size: 1.2rem;">لیست درخواست‌های لغو خالی است.</p>
                </div>
            <?php else: ?>
                <div class="table-container-flex">
                    <table class="glass-table" id="sample_1">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><i class="fa-solid fa-check"></i></th>
                                <th>شناسه</th>
                                <th>آیدی کاربر</th>
                                <th>نام کاربری</th>
                                <th>توضیحات</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($listcencel as $list): 
                                $cat = $list['category'] ?? 'ندارد';
                            ?>
                            <tr>
                                <td><input type="checkbox" class="custom-check" value="<?php echo $list['id']; ?>"></td>
                                <td style="font-family: monospace; opacity: 0.8;"><?php echo $list['id']; ?></td>
                                <td style="font-family: monospace; color: var(--neon-blue); font-weight: 700;"><?php echo $list['id_user']; ?></td>
                                <td style="font-weight: 800; font-size: 1.2rem; color: #fff;"><?php echo htmlspecialchars($list['username']); ?></td>
                                <td style="font-size: 1.1rem; max-width: 300px;"><?php echo htmlspecialchars($list['description']); ?></td>
                                <td><span class="badge badge-status"><?php echo $list['status']; ?></span></td>
                                <td>
                                    <a href="cancelService.php?removeid=<?php echo $list['id']; ?>" class="btn-act" onclick="return confirm('آیا از حذف این درخواست اطمینان دارید؟');">
                                        <i class="fa-solid fa-trash"></i> حذف درخواست
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
            <a href="product.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-box-open"></i></div>
                <span class="dock-label">محصولات</span>
            </a>
            <a href="service.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-server"></i></div>
                <span class="dock-label">سرویس‌ها</span>
            </a>
            <div class="dock-divider"></div>
            <a href="cancelService.php" class="dock-item active" style="color: var(--neon-red);">
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