<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../botapi.php';
require_once '../function.php';

// Authentication
if (!isset($_SESSION["user"])) { header('Location: login.php'); exit; }

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!$result) { header('Location: login.php'); exit; }

// Fetch User Data
if (!isset($_GET["id"])) { die("User ID not provided."); }
$query = $pdo->prepare("SELECT * FROM user WHERE id=:id");
$query->bindParam("id", $_GET["id"], PDO::PARAM_STR);
$query->execute();
$user = $query->fetch(PDO::FETCH_ASSOC);

if (!$user) { die("User not found."); }

$setting = select("setting","*",null,null);
$otherservice = select("topicid","idreport","report","otherservice","select")['idreport'];
$paymentreports = select("topicid","idreport","report","paymentreport","select")['idreport'];

// --- Actions Logic (Preserved) ---

// Block/Unblock
if(isset($_GET['status']) && $_GET['status']){
    if($_GET['status'] == "block"){
        $textblok = "کاربر با آیدی عددی\n{$_GET['id']}  در ربات مسدود گردید \n\nادمین مسدود کننده : پنل تحت وب\nنام کاربری  : {$_SESSION['user']}";
        if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage',[
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $textblok,
                'parse_mode' => "HTML"
            ]);
        }
    }else{
        sendmessage($_GET['id'],"✳️ حساب کاربری شما از مسدودی خارج شد ✳️\nاکنون میتوانید از ربات استفاده کنید ", null, 'HTML');
    }
    update("user", "User_Status", $_GET['status'], "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

// Add Balance
if(isset($_GET['priceadd']) && $_GET['priceadd']){
    $priceadd = number_format($_GET['priceadd'],0);
    $textadd = "💎 کاربر عزیز مبلغ {$priceadd} تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($_GET['id'], $textadd, null, 'HTML');
     if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب افزایش داده است :\n\n🪪 اطلاعات ادمین افزایش دهنده موجودی : \nنام کاربری : {$_SESSION['user']}\n👤 اطلاعات کاربر دریافت کننده موجودی :\nآیدی عددی کاربر  : {$_GET['id']}\nمبلغ موجودی : $priceadd";
        telegram('sendmessage',[
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
    $value = intval($user['Balance'])+intval($_GET['priceadd']);
    update("user", "Balance", $value, "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

// Low Balance
if(isset($_GET['pricelow']) && $_GET['pricelow']){
    $priceadd = number_format($_GET['pricelow'],0);
     if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب کسر کرده است :\n\n🪪 اطلاعات ادمین کسر کننده موجودی : \nنام کاربری : {$_SESSION['user']}\n👤 اطلاعات کاربر :\nآیدی عددی کاربر  : {$_GET['id']}\nمبلغ موجودی : $priceadd";
        telegram('sendmessage',[
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
    $value = intval($user['Balance'])-intval($_GET['pricelow']);
    update("user", "Balance", $value, "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

// Change Agent
if(isset($_GET['agent']) && $_GET['agent']){
    update("user", "agent", $_GET['agent'], "id", $_GET['id']);
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

// Send Message
if(isset($_GET['textmessage']) && $_GET['textmessage']){
    $messagetext = "📥 یک پیام از مدیریت برای شما ارسال شد.\n\nمتن پیام : {$_GET['textmessage']}";
    sendmessage($_GET['id'], $messagetext, null, 'HTML');
     if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 از طریق پنل تحت وب یک پیام برای کاربر ارسال شد\n\n🪪 اطلاعات ادمین ارسال کننده  : \nنام کاربری : {$_SESSION['user']}\n👤 اطلاعات ارسال :\nآیدی عددی کاربر  : {$_GET['id']}\nمتن ارسال شده : {$_GET['textmessage']}";
        telegram('sendmessage',[
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
    header("Location: user.php?id={$_GET['id']}");
    exit;
}

// Status Display Logic
$statusKey = strtolower($user['User_Status']);
$status_user = ($statusKey == 'active') ? "فعال" : (($statusKey == 'block') ? "مسدود" : $user['User_Status']);
$statusClass = ($statusKey == 'active') ? 'status-active' : (($statusKey == 'block') ? 'status-block' : 'status-other');

if($user['number'] == "none") $user['number'] = "ثبت نشده";
$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پروفایل کاربر <?php echo $user['id']; ?> | پنل مدیریت</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <!-- Core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            /* Palette: Midnight Neon */
            --bg-body: #020204;
            --bg-glass: rgba(20, 20, 25, 0.85);
            --bg-dock: rgba(10, 10, 15, 0.95);
            --bg-modal: rgba(25, 25, 30, 0.98);
            
            --neon-blue: #00f3ff;
            --neon-purple: #bc13fe;
            --neon-green: #00ff88;
            --neon-red: #ff004c;
            --neon-amber: #ffb700;
            
            --text-main: #ffffff;
            --text-muted: #b0b0b0;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.12);
            --shadow-card: 0 15px 40px rgba(0,0,0,0.6);
            
            --radius-xl: 30px;
            --radius-lg: 20px;
            --radius-md: 14px;
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
                radial-gradient(circle at 10% 10%, rgba(188, 19, 254, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(0, 243, 255, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 160px; /* Space for dock */
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Animations --- */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .anim { animation: fadeIn 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; }

        /* --- Layout --- */
        .container-fluid-custom {
            width: 100%; padding: 40px 5%; max-width: 1600px; margin: 0 auto;
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

        /* --- Profile Grid --- */
        .profile-grid {
            display: grid; grid-template-columns: 1fr 2fr; gap: 30px;
        }
        
        .glass-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(25px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px; box-shadow: var(--shadow-card);
            height: 100%;
        }

        /* User Card (Left) */
        .user-card-header {
            text-align: center; margin-bottom: 30px; padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .avatar-circle {
            width: 110px; height: 110px; border-radius: 50%;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;
            font-size: 3.5rem; color: #fff; font-weight: bold;
            box-shadow: 0 0 30px rgba(188, 19, 254, 0.4);
            border: 3px solid rgba(255,255,255,0.1);
        }
        .user-id-badge {
            font-family: monospace; font-size: 1.4rem; color: var(--neon-blue); letter-spacing: 2px;
            background: rgba(0, 243, 255, 0.1); padding: 5px 15px; border-radius: 20px;
            display: inline-block; font-weight: 700;
        }
        .username { font-size: 2rem; font-weight: 800; margin: 15px 0 5px; color: #fff; }
        .telegram-link { color: var(--text-muted); font-size: 1.1rem; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; }
        .telegram-link:hover { color: var(--neon-blue); }

        /* Info List */
        .info-list { list-style: none; padding: 0; margin: 0; }
        .info-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 1.15rem;
        }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-weight: 500; }
        .info-value { font-weight: 700; color: #fff; }
        
        .val-money { color: var(--neon-amber); font-size: 1.3rem; text-shadow: 0 0 10px rgba(255, 183, 0, 0.3); }
        
        .status-badge { padding: 5px 15px; border-radius: 20px; font-size: 0.95rem; }
        .status-active { background: rgba(0, 255, 136, 0.15); color: var(--neon-green); border: 1px solid rgba(0, 255, 136, 0.3); }
        .status-block { background: rgba(255, 0, 76, 0.15); color: var(--neon-red); border: 1px solid rgba(255, 0, 76, 0.3); }
        .status-other { background: rgba(255, 255, 255, 0.1); color: #ccc; }

        /* --- Actions Grid (Right) --- */
        .actions-header { 
            font-size: 1.6rem; font-weight: 800; margin-bottom: 30px; color: #fff; 
            display: flex; align-items: center; gap: 12px;
            padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .actions-buttons {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px;
        }
        
        .btn-action {
            height: 80px; display: flex; align-items: center; justify-content: center; gap: 15px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 18px; color: #fff; font-size: 1.15rem; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: 0.3s; position: relative; overflow: hidden;
        }
        .btn-action:hover { transform: translateY(-5px); background: rgba(255,255,255,0.08); }
        .btn-action i { font-size: 1.8rem; }
        
        .act-green { border-color: rgba(0, 255, 136, 0.4); color: var(--neon-green); }
        .act-green:hover { box-shadow: 0 0 25px rgba(0, 255, 136, 0.2); }
        
        .act-red { border-color: rgba(255, 0, 76, 0.4); color: var(--neon-red); }
        .act-red:hover { box-shadow: 0 0 25px rgba(255, 0, 76, 0.2); }
        
        .act-blue { border-color: rgba(0, 243, 255, 0.4); color: var(--neon-blue); }
        .act-blue:hover { box-shadow: 0 0 25px rgba(0, 243, 255, 0.2); }
        
        .act-amber { border-color: rgba(255, 170, 0, 0.4); color: var(--neon-amber); }
        .act-amber:hover { box-shadow: 0 0 25px rgba(255, 170, 0, 0.2); }
        
        .act-purple { border-color: rgba(188, 19, 254, 0.4); color: var(--neon-purple); }
        .act-purple:hover { box-shadow: 0 0 25px rgba(188, 19, 254, 0.2); }

        /* --- Modals (Dark & Glassy) --- */
        .modal-content {
            background: var(--bg-modal);
            backdrop-filter: blur(40px);
            border: 1px solid var(--neon-blue);
            border-radius: var(--radius-lg);
            color: #fff;
            box-shadow: 0 0 60px rgba(0,0,0,0.9);
        }
        .modal-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding: 25px; }
        .modal-title { font-size: 1.6rem; font-weight: 800; color: var(--neon-blue); display: flex; align-items: center; gap: 10px; }
        .close { text-shadow: none; color: #fff; opacity: 0.8; font-size: 2.5rem; margin-top: -5px; font-weight: 300; }
        .modal-body { padding: 35px; }
        
        .form-control-dark {
            background: #050505; border: 2px solid #333; color: #fff;
            height: 60px; border-radius: 14px; padding: 0 20px; font-size: 1.2rem; width: 100%;
            transition: 0.3s;
        }
        .form-control-dark:focus { border-color: var(--neon-blue); outline: none; box-shadow: 0 0 20px rgba(0,243,255,0.25); }
        
        .btn-modal {
            width: 100%; height: 60px; background: var(--neon-blue); color: #000;
            border: none; border-radius: 14px; font-size: 1.3rem; font-weight: 800; cursor: pointer;
            margin-top: 25px; transition: 0.3s;
        }
        .btn-modal:hover { box-shadow: 0 0 40px var(--neon-blue); transform: translateY(-3px); }

        /* --- Floating Dock (Fixed Center) --- */
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
        .dock-item:hover .dock-icon {
            transform: translateY(-20px) scale(1.2); background: rgba(255,255,255,0.15); color: #fff;
        }
        .dock-item:hover .dock-label { opacity: 1; bottom: -45px; color: #fff; }
        .dock-item.active .dock-icon {
            color: var(--neon-blue); background: rgba(0, 243, 255, 0.15); box-shadow: 0 0 25px rgba(0, 243, 255, 0.4); transform: translateY(-10px);
        }
        .dock-item.active .dock-label { opacity: 1; bottom: -45px; color: var(--neon-blue); }

        @media (max-width: 992px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .page-title h1 { font-size: 2.2rem; }
            .dock { width: 95%; justify-content: space-between; padding: 10px 20px; gap: 5px; }
            .dock-icon { width: 45px; height: 45px; font-size: 1.5rem; }
            .actions-buttons { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>پروفایل کاربر</h1>
                <p>
                    <span style="color: var(--neon-blue); font-family:monospace; font-weight:700;">#<?php echo $user['id']; ?></span>
                    <span style="opacity:0.3; margin:0 15px;">|</span>
                    مدیریت و ویرایش اطلاعات مشترک
                </p>
            </div>
        </header>

        <div class="profile-grid">
            
            <!-- User Info (Left) -->
            <div class="glass-panel anim d-1">
                <div class="user-card-header">
                    <div class="avatar-circle">
                        <?php echo strtoupper(substr($user['username'] ?: 'U', 0, 1)); ?>
                    </div>
                    <div class="user-id-badge"><?php echo $user['id']; ?></div>
                    <div class="username"><?php echo $user['username'] ? $user['username'] : 'بدون نام کاربری'; ?></div>
                    <?php if($user['username']): ?>
                        <a href="https://t.me/<?php echo $user['username']; ?>" target="_blank" class="telegram-link">
                            <i class="fa-brands fa-telegram"></i> مشاهده در تلگرام
                        </a>
                    <?php endif; ?>
                </div>
                
                <ul class="info-list">
                    <li class="info-item">
                        <span class="info-label">شماره تماس</span>
                        <span class="info-value" style="font-family: monospace; font-size: 1.3rem;"><?php echo $user['number']; ?></span>
                    </li>
                    <li class="info-item">
                        <span class="info-label">موجودی کیف پول</span>
                        <span class="info-value val-money"><?php echo number_format($user['Balance']); ?> تومان</span>
                    </li>
                    <li class="info-item">
                        <span class="info-label">وضعیت حساب</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo $status_user; ?>
                            </span>
                        </span>
                    </li>
                    <li class="info-item">
                        <span class="info-label">نوع کاربری</span>
                        <span class="info-value"><?php echo ($user['agent']=='f') ? 'عادی' : (($user['agent']=='n')?'نماینده':'نماینده ارشد'); ?></span>
                    </li>
                    <li class="info-item">
                        <span class="info-label">زیرمجموعه‌ها</span>
                        <span class="info-value"><?php echo number_format($user['affiliatescount']); ?> نفر</span>
                    </li>
                    <li class="info-item">
                        <span class="info-label">معرف</span>
                        <span class="info-value" style="font-family: monospace;"><?php echo $user['affiliates'] ?: '---'; ?></span>
                    </li>
                </ul>
            </div>

            <!-- Actions (Right) -->
            <div class="glass-panel anim d-2" style="display: flex; flex-direction: column;">
                <div class="actions-header">
                    <i class="fa-solid fa-sliders" style="color: var(--neon-purple); font-size: 1.8rem;"></i>
                    پنل عملیات سریع
                </div>
                
                <div class="actions-buttons">
                    <?php if(strtolower($user['User_Status']) == 'active'): ?>
                        <a href="user.php?id=<?php echo $user['id'];?>&status=block" class="btn-action act-red" onclick="return confirm('آیا از مسدود کردن این کاربر اطمینان دارید؟');">
                            <i class="fa-solid fa-ban"></i> مسدود کردن
                        </a>
                    <?php else: ?>
                        <a href="user.php?id=<?php echo $user['id'];?>&status=active" class="btn-action act-green" onclick="return confirm('آیا از رفع مسدودی این کاربر اطمینان دارید؟');">
                            <i class="fa-solid fa-unlock"></i> رفع مسدودی
                        </a>
                    <?php endif; ?>
                    
                    <a href="#addbalance" data-toggle="modal" class="btn-action act-blue">
                        <i class="fa-solid fa-plus-circle"></i> افزایش موجودی
                    </a>
                    
                    <a href="#lowbalance" data-toggle="modal" class="btn-action act-amber">
                        <i class="fa-solid fa-minus-circle"></i> کسر موجودی
                    </a>
                    
                    <a href="#sendmessage" data-toggle="modal" class="btn-action act-blue">
                        <i class="fa-solid fa-paper-plane"></i> ارسال پیام
                    </a>
                    
                    <a href="#changeagent" data-toggle="modal" class="btn-action act-purple">
                        <i class="fa-solid fa-user-tag"></i> تغییر سطح
                    </a>
                    
                    <a href="user.php?id=<?php echo $user['id'];?>&agent=f" class="btn-action act-red" onclick="return confirm('تغییر به کاربر عادی؟');">
                        <i class="fa-solid fa-user-xmark"></i> حذف نمایندگی
                    </a>
                </div>
            </div>
            
        </div>

    </div>

    <!-- Modals -->
    
    <!-- Add Balance -->
    <div class="modal fade" id="addbalance" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title"><i class="fa-solid fa-wallet"></i> افزایش موجودی</h4>
                </div>
                <div class="modal-body">
                    <form action="user.php" method="GET">
                        <input type="hidden" name="id" value="<?php echo $user['id'];?>">
                        <div class="form-group">
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.1rem;">مبلغ (تومان)</label>
                            <input type="number" name="priceadd" class="form-control-dark" placeholder="مثلا: 50000" required>
                        </div>
                        <button type="submit" class="btn-modal">انجام عملیات</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Balance -->
    <div class="modal fade" id="lowbalance" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title" style="color: var(--neon-amber);"><i class="fa-solid fa-minus-circle"></i> کسر موجودی</h4>
                </div>
                <div class="modal-body">
                    <form action="user.php" method="GET">
                        <input type="hidden" name="id" value="<?php echo $user['id'];?>">
                        <div class="form-group">
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.1rem;">مبلغ کسر (تومان)</label>
                            <input type="number" name="pricelow" class="form-control-dark" placeholder="مثلا: 10000" required>
                        </div>
                        <button type="submit" class="btn-modal" style="background: var(--neon-amber);">انجام عملیات</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Message -->
    <div class="modal fade" id="sendmessage" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title"><i class="fa-solid fa-envelope"></i> ارسال پیام</h4>
                </div>
                <div class="modal-body">
                    <form action="user.php" method="GET">
                        <input type="hidden" name="id" value="<?php echo $user['id'];?>">
                        <div class="form-group">
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.1rem;">متن پیام</label>
                            <textarea name="textmessage" class="form-control-dark" style="height:150px; padding-top:15px;" placeholder="پیام خود را بنویسید..." required></textarea>
                        </div>
                        <button type="submit" class="btn-modal">ارسال</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Agent -->
    <div class="modal fade" id="changeagent" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title" style="color: var(--neon-purple);"><i class="fa-solid fa-user-tag"></i> تغییر سطح</h4>
                </div>
                <div class="modal-body">
                    <form action="user.php" method="GET">
                        <input type="hidden" name="id" value="<?php echo $user['id'];?>">
                        <div class="form-group">
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.1rem;">انتخاب سطح جدید</label>
                            <select name="agent" class="form-control-dark">
                                <option value="f" <?php echo ($user['agent']=='f')?'selected':''; ?>>کاربر عادی</option>
                                <option value="n" <?php echo ($user['agent']=='n')?'selected':''; ?>>نماینده معمولی</option>
                                <option value="n2" <?php echo ($user['agent']=='n2')?'selected':''; ?>>نماینده پیشرفته</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-modal" style="background: var(--neon-purple); color:#fff;">ذخیره تغییرات</button>
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
            <a href="user.php" class="dock-item active">
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

</body>
</html>