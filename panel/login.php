<?php
/**
 * Login Page - Nebula Pro Edition
 * Professional Animations, Moving Background & Glassmorphism Pro
 * Designed for MirzaBot Studio
 */

ini_set('session.cookie_httponly', true);
session_start();
session_regenerate_id(true);

// Load Logic
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

$allowed_ips = select("setting","*",null,null,"select");

$user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $user_ip = trim($forwardedIps[0]);
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $user_ip = $_SERVER['HTTP_X_REAL_IP'];
}

$allowed_ip_value = isset($allowed_ips['iplogin']) ? trim($allowed_ips['iplogin']) : '';
if ($allowed_ip_value === '0') {
    $allowed_ip_value = '';
}
$allowed_list = array_filter(preg_split('/[\s,]+/', $allowed_ip_value), 'strlen');

$check_ip = empty($allowed_list) || in_array($user_ip, $allowed_list, true);
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
$texterrr = "";
$_SESSION["user"] = null;

if (isset($_POST['login'])) {
    $username = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
    $password = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8');
    $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $username, PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    if ( !$result ) {
        $texterrr = 'نام کاربری یا رمزعبور اشتباه است';
    } else {
        if ( $password == $result["password"]) {
            foreach ($admin_ids as $admin) {
                $texts = "🔐 ورود به پنل:\nمدیر: <b>$username</b>\nآی‌پی: <code>$user_ip</code>";
                sendmessage($admin, $texts, null, 'html');
            }
            $_SESSION["user"] = $result["username"];
            header('Location: index.php');
            exit;
        } else {
            $texterrr = 'رمز عبور صحیح نمی‌باشد';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به مدیریت | MirzaBot</title>

    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --secondary: #d946ef;
            --secondary-glow: rgba(217, 70, 239, 0.3);
            --bg-dark: #020617;
            --text-silver: #e2e8f0;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-dark);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: var(--text-silver);
        }

        /* --- ADVANCED NEBULA BACKGROUND --- */
        .background-container {
            position: fixed; inset: 0; z-index: -10;
            background: radial-gradient(circle at center, #0f172a 0%, #020617 100%);
        }

        .nebula {
            position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.25;
            animation: moveNebula 25s infinite alternate ease-in-out;
        }
        .nebula-1 { width: 600px; height: 600px; background: var(--primary); top: -10%; left: -10%; }
        .nebula-2 { width: 500px; height: 500px; background: var(--secondary); bottom: -10%; right: -10%; animation-delay: -5s; }
        .nebula-3 { width: 400px; height: 400px; background: #3b82f6; top: 40%; left: 30%; animation-delay: -10s; }

        @keyframes moveNebula {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, 80px) scale(1.1); }
        }

        .stars {
            position: absolute; inset: 0;
            background: transparent url('https://www.transparenttextures.com/patterns/stardust.png') repeat;
            opacity: 0.3; animation: starTwinkle 100s linear infinite;
        }
        @keyframes starTwinkle { from { background-position: 0 0; } to { background-position: 1000px 1000px; } }

        /* --- GLASSMOPHISM PRO CARD --- */
        .glass-box {
            width: 100%; max-width: 410px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px) saturate(160%);
            -webkit-backdrop-filter: blur(20px) saturate(160%);
            border-radius: 30px;
            padding: 45px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.6),
                inset 0 0 1px 1px rgba(255, 255, 255, 0.1);
            position: relative;
            animation: zoomIn 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 50;
        }

        .logo-glow-container {
            display: flex; justify-content: center; margin-bottom: 30px;
        }
        .logo-main {
            width: 95px; height: 95px;
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(217,70,239,0.2));
            border-radius: 28px; border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 35px var(--primary-glow);
            animation: float 6s ease-in-out infinite;
        }
        .logo-main i { font-size: 42px; color: #fff; filter: drop-shadow(0 0 12px var(--primary)); }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        /* --- INPUT STYLING --- */
        .field-group { position: relative; margin-bottom: 22px; }
        .input-item {
            width: 100%;
            background: rgba(2, 6, 23, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.05);
            border-radius: 18px;
            padding: 15px 48px 15px 16px;
            color: #fff; font-family: inherit; font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }
        .input-item:focus {
            border-color: var(--primary);
            background: rgba(2, 6, 23, 0.7);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.2), inset 0 0 5px rgba(255,255,255,0.05);
            transform: scale(1.01);
        }
        .item-icon {
            position: absolute; right: 18px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); transition: 0.3s; pointer-events: none;
        }
        .input-item:focus + .item-icon { color: var(--primary); transform: translateY(-50%) scale(1.1); }

        /* --- BUTTON --- */
        .btn-modern {
            width: 100%; padding: 15px;
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            color: #fff; border: none; border-radius: 18px;
            font-weight: 800; font-size: 16px; cursor: pointer;
            box-shadow: 0 8px 25px var(--primary-glow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
        }
        .btn-modern:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px var(--primary-glow);
            filter: brightness(1.1);
        }
        .btn-modern:active { transform: translateY(-1px) scale(0.98); }
        .btn-modern::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.6s; transform: skewX(-25deg);
        }
        .btn-modern:hover::before { left: 150%; }

        /* --- CREDITS --- */
        .dev-footer {
            margin-top: 35px; text-align: center;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 20px;
        }
        .dev-link {
            text-decoration: none; color: rgba(255,255,255,0.4);
            font-size: 11px; transition: 0.3s; display: inline-flex; align-items: center; gap: 6px;
        }
        .dev-link:hover { color: var(--text-silver); text-shadow: 0 0 8px white; }
        .dev-link strong { color: var(--primary); }

        /* --- ERROR & IP ALERTS --- */
        .alert-error {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5; padding: 12px; border-radius: 14px;
            font-size: 13px; text-align: center; margin-bottom: 22px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            animation: headShake 0.6s;
        }
        .ip-box {
            background: rgba(0,0,0,0.4); border: 1px dashed rgba(255,255,255,0.15);
            padding: 10px 20px; border-radius: 12px; font-family: monospace; letter-spacing: 1.5px;
            margin-top: 15px; color: #67e8f9; font-weight: bold; display: inline-block;
        }
    </style>
</head>
<body>

    <!-- Background Layers -->
    <div class="background-container">
        <div class="stars"></div>
        <div class="nebula nebula-1"></div>
        <div class="nebula nebula-2"></div>
        <div class="nebula nebula-3"></div>
    </div>

    <!-- Main Wrapper -->
    <div class="px-6 flex items-center justify-center min-h-screen w-full relative">
        
        <?php if(!$check_ip): ?>
        <!-- RESTRICTED ACCESS -->
        <div class="glass-box text-center">
            <div class="logo-glow-container">
                <div class="logo-main" style="border-color: rgba(239,68,68,0.3); box-shadow: 0 0 35px rgba(239,68,68,0.3);">
                    <i class="fa-solid fa-user-shield text-rose-400"></i>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-white mb-3">دسترسی محدود</h2>
            <p class="text-slate-400 text-sm leading-7 mb-6">
                آی‌پی شما در لیست سفید قرار ندارد.<br>
                لطفاً آی‌پی زیر را در تنظیمات ربات ثبت کنید:
            </p>
            <div class="ip-box animate__animated animate__pulse animate__infinite"><?php echo $user_ip; ?></div>
            <div class="dev-footer">
                <a href="https://t.me/KillHosein" class="dev-link" target="_blank">
                    Developed by <i class="fa-brands fa-telegram text-sky-500"></i> <strong>KillHosein</strong>
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- LOGIN FORM -->
        <div class="glass-box">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="logo-glow-container">
                    <div class="logo-main">
                        <i class="fa-solid fa-lock-open"></i>
                    </div>
                </div>
                
                <h2 class="text-center text-2xl font-black text-white mb-1">ورود به پنل</h2>
                <p class="text-center text-[10px] text-indigo-400 uppercase tracking-[4px] mb-8 opacity-80">MirzaBot Management</p>

                <?php if(!empty($texterrr)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $texterrr; ?>
                </div>
                <?php endif; ?>

                <div class="field-group">
                    <input type="text" name="username" class="input-item" placeholder="نام کاربری" required autofocus autocomplete="off">
                    <i class="fa-solid fa-user-ninja item-icon"></i>
                </div>

                <div class="field-group">
                    <input type="password" name="password" class="input-item" placeholder="رمز عبور" required>
                    <i class="fa-solid fa-key item-icon"></i>
                </div>

                <button class="btn-modern" name="login" type="submit">
                    تایید و ورود <i class="fa-solid fa-arrow-left-long mr-2"></i>
                </button>

                <div class="dev-footer">
                    <a href="https://t.me/KillHosein" class="dev-link" target="_blank">
                        طراحی و توسعه توسط <i class="fa-brands fa-telegram text-sky-500"></i> <strong>KillHosein</strong>
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>