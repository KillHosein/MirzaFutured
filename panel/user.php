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
// فراخوانی کتابخانه تاریخ شمسی
if (file_exists('../jdf.php')) require_once '../jdf.php';

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
// Handle potential missing keys gracefully
$otherservice = isset(select("topicid","idreport","report","otherservice","select")['idreport']) ? select("topicid","idreport","report","otherservice","select")['idreport'] : null;
$paymentreports = isset(select("topicid","idreport","report","paymentreport","select")['idreport']) ? select("topicid","idreport","report","paymentreport","select")['idreport'] : null;

// --- Actions Logic (Preserved) ---

// Block/Unblock
if(isset($_GET['status']) && $_GET['status']){
    if($_GET['status'] == "block"){
        $textblok = "کاربر با آیدی عددی\n{$_GET['id']}  در ربات مسدود گردید \n\nادمین مسدود کننده : پنل تحت وب\nنام کاربری  : {$_SESSION['user']}";
        if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0 && $otherservice) {
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
     if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0 && $paymentreports) {
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
     if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0 && $paymentreports) {
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
     if (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0 && $otherservice) {
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
    <title>پروفایل کاربر <?php echo $user['id']; ?> | نسخه الترا پلاس</title>
    
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

        /* --- Base --- */
        * { box-sizing: border-box; outline: none; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(192, 38, 211, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(0, 242, 255, 0.08) 0%, transparent 45%);
            color: var(--text-pri);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            padding-bottom: 150px; /* Space for dock */
            overflow-x: hidden;
        }

        /* --- Container --- */
        .container-fluid-custom {
            width: 100%; max-width: 1600px; margin: 0 auto;
            padding: 40px 4%;
        }

        /* --- Header --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 25px;
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

        /* --- Profile Grid --- */
        .profile-grid {
            display: grid; grid-template-columns: 1fr 2fr; gap: 30px;
        }
        
        .glass-panel {
            background: var(--bg-card);
            backdrop-filter: blur(30px);
            border: var(--border-subtle);
            border-radius: var(--radius-main);
            padding: 35px; box-shadow: var(--shadow-card);
            display: flex; flex-direction: column;
            height: 100%; transition: 0.3s;
        }
        .glass-panel:hover { border-color: var(--border-highlight); }

        /* User Card (Left) */
        .user-card-header {
            text-align: center; margin-bottom: 30px; padding-bottom: 25px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .avatar-circle {
            width: 130px; height: 130px; border-radius: 50%;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;
            font-size: 4rem; color: #fff; font-weight: 900;
            box-shadow: 0 0 40px rgba(188, 19, 254, 0.4);
            border: 4px solid rgba(255,255,255,0.15);
        }
        .user-id-badge {
            font-family: monospace; font-size: 1.6rem; color: var(--neon-blue); letter-spacing: 2px;
            background: rgba(0, 243, 255, 0.1); padding: 8px 20px; border-radius: 20px;
            display: inline-block; font-weight: 700; border: 1px solid rgba(0, 243, 255, 0.2);
        }
        .username { font-size: 2.2rem; font-weight: 800; margin: 20px 0 10px; color: #fff; }
        .telegram-link { 
            color: var(--text-sec); font-size: 1.2rem; transition: 0.3s; 
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
            border-radius: 12px; background: rgba(255,255,255,0.03);
        }
        .telegram-link:hover { color: var(--neon-blue); background: rgba(0, 243, 255, 0.1); }

        /* Info List */
        .info-list { list-style: none; padding: 0; margin: 0; }
        .info-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 22px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 1.25rem;
        }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: var(--text-sec); font-weight: 500; }
        .info-value { font-weight: 700; color: #fff; }
        
        .val-money { color: var(--neon-gold); font-size: 1.5rem; text-shadow: 0 0 15px rgba(251, 191, 36, 0.3); }
        
        .status-badge { padding: 6px 18px; border-radius: 30px; font-size: 1rem; }
        .status-active { background: rgba(0, 255, 163, 0.15); color: var(--neon-green); border: 1px solid rgba(0, 255, 163, 0.3); }
        .status-block { background: rgba(255, 42, 109, 0.15); color: var(--neon-red); border: 1px solid rgba(255, 42, 109, 0.3); }
        .status-other { background: rgba(255, 255, 255, 0.1); color: #ccc; }

        /* --- Actions Grid (Right) --- */
        .actions-header { 
            font-size: 1.8rem; font-weight: 800; margin-bottom: 35px; color: #fff; 
            display: flex; align-items: center; gap: 15px;
            padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .actions-buttons {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 30px;
        }
        
        .btn-action {
            height: 90px; display: flex; align-items: center; justify-content: center; gap: 15px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 22px; color: #fff; font-size: 1.3rem; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: 0.3s; position: relative; overflow: hidden;
        }
        .btn-action:hover { transform: translateY(-8px); background: rgba(255,255,255,0.08); }
        .btn-action i { font-size: 2rem; }
        
        .act-green { border-color: rgba(0, 255, 163, 0.4); color: var(--neon-green); }
        .act-green:hover { box-shadow: 0 0 30px rgba(0, 255, 163, 0.25); }
        
        .act-red { border-color: rgba(255, 42, 109, 0.4); color: var(--neon-red); }
        .act-red:hover { box-shadow: 0 0 30px rgba(255, 42, 109, 0.25); }
        
        .act-blue { border-color: rgba(0, 243, 255, 0.4); color: var(--neon-blue); }
        .act-blue:hover { box-shadow: 0 0 30px rgba(0, 243, 255, 0.25); }
        
        .act-amber { border-color: rgba(251, 191, 36, 0.4); color: var(--neon-gold); }
        .act-amber:hover { box-shadow: 0 0 30px rgba(251, 191, 36, 0.25); }
        
        .act-purple { border-color: rgba(192, 38, 211, 0.4); color: var(--neon-purple); }
        .act-purple:hover { box-shadow: 0 0 30px rgba(192, 38, 211, 0.25); }

        /* --- Modals (Bigger Inputs) --- */
        .modal-content {
            background: var(--bg-modal);
            backdrop-filter: blur(40px);
            border: 1px solid var(--neon-blue);
            border-radius: var(--radius-lg);
            color: #fff;
            box-shadow: 0 0 80px rgba(0,0,0,0.95);
        }
        .modal-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding: 30px; }
        .modal-title { font-size: 1.8rem; font-weight: 800; color: var(--neon-blue); display: flex; align-items: center; gap: 15px; }
        .close { text-shadow: none; color: #fff; opacity: 0.8; font-size: 3rem; margin-top: -10px; font-weight: 300; }
        .modal-body { padding: 40px; }
        
        .input-readable-modal {
            width: 100%; height: 65px; /* Bigger */
            background: #050505; border: 2px solid #333; color: #fff;
            border-radius: 16px; padding: 0 25px; font-size: 1.3rem; 
            transition: 0.3s;
        }
        .input-readable-modal:focus { border-color: var(--neon-blue); outline: none; box-shadow: 0 0 25px rgba(0,243,255,0.25); }
        
        .btn-modal {
            width: 100%; height: 65px; background: var(--neon-blue); color: #000;
            border: none; border-radius: 16px; font-size: 1.4rem; font-weight: 800; cursor: pointer;
            margin-top: 30px; transition: 0.3s;
        }
        .btn-modal:hover { box-shadow: 0 0 40px var(--neon-blue); transform: translateY(-3px); }

        /* --- Super Dock (Bigger) --- */
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

        @media (max-width: 992px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .page-title h1 { font-size: 2.5rem; }
            .dock { width: 95%; overflow-x: auto; justify-content: flex-start; }
            .dock-item { flex-shrink: 0; width: 50px; height: 50px; font-size: 1.4rem; }
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
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
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
                        <span class="info-value" style="font-family: monospace; font-size: 1.4rem;"><?php echo $user['number']; ?></span>
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
                    <i class="fa-solid fa-sliders" style="color: var(--neon-purple); font-size: 2rem;"></i>
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

    <!-- Modals (Bigger Inputs) -->
    
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
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">مبلغ (تومان)</label>
                            <input type="number" name="priceadd" class="input-readable-modal" placeholder="مثلا: 50000" required>
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
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">مبلغ کسر (تومان)</label>
                            <input type="number" name="pricelow" class="input-readable-modal" placeholder="مثلا: 10000" required>
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
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">متن پیام</label>
                            <textarea name="textmessage" class="input-readable-modal" style="height:180px; padding-top:20px;" placeholder="پیام خود را بنویسید..." required></textarea>
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
                            <label style="color:#fff; margin-bottom:15px; font-size: 1.2rem;">انتخاب سطح جدید</label>
                            <select name="agent" class="input-readable-modal">
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

    <!-- Super Dock -->
    <div class="dock-container">
        <nav class="dock">
            <a href="index.php" class="dock-item" data-tooltip="داشبورد"><i class="fa-solid fa-house"></i></a>
            <div class="dock-divider"></div>
            <a href="users.php" class="dock-item active" data-tooltip="کاربران"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-item" data-tooltip="سفارشات"><i class="fa-solid fa-file-contract"></i></a>
            <a href="product.php" class="dock-item" data-tooltip="محصولات"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-item" data-tooltip="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
            <div class="dock-divider"></div>
            <a href="cancelService.php" class="dock-item" data-tooltip="مسدودها" style="color: var(--neon-red);"><i class="fa-solid fa-ban"></i></a>
            <a href="payment.php" class="dock-item" data-tooltip="مالی"><i class="fa-solid fa-wallet"></i></a>
            <a href="inbound.php" class="dock-item" data-tooltip="کانفیگ"><i class="fa-solid fa-network-wired"></i></a>
            <a href="seeting_x_ui.php" class="dock-item" data-tooltip="پنل X-UI"><i class="fa-solid fa-tower-broadcast"></i></a>
            <div class="dock-divider"></div>
            <a href="settings.php" class="dock-item" data-tooltip="تنظیمات"><i class="fa-solid fa-gear"></i></a>
            <a href="logout.php" class="dock-item" data-tooltip="خروج" style="color: var(--neon-red);"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>

</body>
</html>