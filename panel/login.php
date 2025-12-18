<?php
/**
 * Login Page - Cosmic Glass Edition (Ultimate)
 * Professional Animations & Centered Layout
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
            // Send Notification
            foreach ($admin_ids as $admin) {
                $texts = "⚠️ هشدار امنیتی:\nکاربری با نام <b>$username</b> و آی‌پی <code>$user_ip</code> وارد پنل مدیریت شد.";
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
    <title>ورود به پنل مدیریت | MirzaBot</title>

    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --bg-deep: #0f172a;
            --primary: #6366f1; /* Indigo 500 */
            --primary-glow: rgba(99, 102, 241, 0.6);
            --accent: #d946ef; /* Fuchsia 500 */
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-surface: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; margin: 0;
            position: relative;
        }

        /* --- ADVANCED ANIMATED BACKGROUND --- */
        .universe {
            position: fixed; inset: 0; z-index: -2;
            background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%);
            overflow: hidden;
        }
        
        /* Floating Orbs Animation */
        .orb {
            position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.6;
            animation: floatOrb 10s infinite ease-in-out alternate;
        }
        .orb-1 { width: 400px; height: 400px; background: var(--primary); top: -100px; left: -100px; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; background: var(--accent); bottom: -50px; right: -50px; animation-delay: -2s; }
        .orb-3 { width: 200px; height: 200px; background: #3b82f6; top: 40%; left: 40%; opacity: 0.3; animation: pulseOrb 8s infinite; }

        @keyframes floatOrb {
            0% { transform: translate(0, 0); }
            100% { transform: translate(30px, 50px); }
        }
        @keyframes pulseOrb {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.2); opacity: 0.5; }
        }

        /* --- LOGIN CARD --- */
        .glass-card {
            width: 100%; max-width: 400px;
            background: rgba(17, 25, 40, 0.75);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.125);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative; z-index: 10;
            animation: zoomIn 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
            transform-style: preserve-3d;
        }
        
        /* Gradient Border Trick */
        .glass-card::before {
            content: ''; position: absolute; inset: 0; border-radius: 24px; padding: 1px;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none;
        }

        .logo-container {
            display: flex; justify-content: center; margin-bottom: 25px;
        }
        .logo-circle {
            width: 90px; height: 90px;
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(217,70,239,0.2));
            border-radius: 50%; border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 40px var(--primary-glow);
            animation: floatLogo 6s ease-in-out infinite;
        }
        .logo-circle i { font-size: 40px; color: #fff; text-shadow: 0 0 15px var(--primary); }

        @keyframes floatLogo {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* --- INPUTS --- */
        .input-box { position: relative; margin-bottom: 20px; }
        .input-field {
            width: 100%;
            background: rgba(30, 41, 59, 0.5);
            border: 2px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 14px 45px 14px 15px;
            color: white; font-family: inherit; font-size: 14px;
            transition: all 0.3s; outline: none;
        }
        .input-field:focus {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
        }
        .field-icon {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); transition: 0.3s;
        }
        .input-field:focus + .field-icon { color: var(--primary); }

        /* --- BUTTON --- */
        .btn-submit {
            width: 100%; padding: 14px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white; border: none; border-radius: 14px;
            font-weight: 700; font-size: 15px; cursor: pointer;
            box-shadow: 0 5px 20px rgba(99, 102, 241, 0.4);
            transition: all 0.3s; margin-top: 10px;
            position: relative; overflow: hidden;
        }
        .btn-submit::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(99, 102, 241, 0.6); }
        .btn-submit:hover::after { left: 100%; }

        /* --- CREDIT --- */
        .credit-link {
            display: block; text-align: center; margin-top: 25px;
            font-size: 11px; color: rgba(255,255,255,0.4);
            transition: 0.3s; text-decoration: none;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 15px;
        }
        .credit-link:hover { color: white; text-shadow: 0 0 10px white; }
        .credit-link i { margin-left: 4px; color: #0088cc; }

        /* --- ERROR & IP --- */
        .err-msg {
            background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5; padding: 12px; border-radius: 12px;
            font-size: 13px; text-align: center; margin-bottom: 20px;
            animation: headShake 0.8s;
        }
        .ip-display {
            background: rgba(0,0,0,0.3); border: 1px dashed rgba(255,255,255,0.2);
            padding: 10px; border-radius: 10px; font-family: monospace; letter-spacing: 1px;
            margin-top: 10px; color: #a5b4fc; display: inline-block;
        }
    </style>
</head>

<body>

    <!-- Background Elements -->
    <div class="universe">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="container mx-auto px-4 flex justify-center items-center h-full">
        
        <!-- BLOCK VIEW -->
        <?php if(!$check_ip): ?>
        <div class="glass-card text-center">
            <div class="logo-container">
                <div class="logo-circle" style="border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.1); box-shadow: 0 0 30px rgba(239,68,68,0.4);">
                    <i class="fa-solid fa-shield-halved text-red-400"></i>
                </div>
            </div>
            
            <h2 class="text-2xl font-bold text-white mb-2 animate__animated animate__fadeInDown">دسترسی محدود شده</h2>
            <p class="text-slate-400 text-sm mb-6 leading-6 animate__animated animate__fadeIn">
                دسترسی آی‌پی شما به پنل مدیریت مسدود است.<br>
                برای رفع این مشکل، وارد ربات شده و آی‌پی زیر را در بخش تنظیمات تایید کنید.
            </p>
            
            <div class="ip-display animate__animated animate__pulse animate__infinite" dir="ltr"><?php echo $user_ip; ?></div>
            
            <a href="https://t.me/KillHosein" class="credit-link">
                طراحی شده توسط <i class="fa-brands fa-telegram"></i> KillHosein
            </a>
        </div>
        <?php endif; ?>

        <!-- LOGIN VIEW -->
        <?php if($check_ip): ?>
        <div class="glass-card">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="logo-container">
                    <div class="logo-circle">
                        <i class="fa-solid fa-fingerprint"></i>
                    </div>
                </div>
                
                <h2 class="text-center text-2xl font-bold text-white mb-1">خوش‌آمدید</h2>
                <p class="text-center text-xs text-indigo-300 mb-6 uppercase tracking-[3px] opacity-70">Admin Panel</p>

                <?php if(!empty($texterrr)): ?>
                <div class="err-msg">
                    <i class="fa-solid fa-triangle-exclamation ml-1"></i> <?php echo $texterrr; ?>
                </div>
                <?php endif; ?>

                <div class="input-box">
                    <input type="text" name="username" class="input-field" placeholder="نام کاربری" required autofocus autocomplete="off">
                    <i class="fa-regular fa-user field-icon"></i>
                </div>

                <div class="input-box">
                    <input type="password" name="password" class="input-field" placeholder="رمز عبور" required>
                    <i class="fa-solid fa-lock field-icon"></i>
                </div>

                <button class="btn-submit" name="login" type="submit">
                    ورود به پنل <i class="fa-solid fa-arrow-left mr-2"></i>
                </button>

                <a href="https://t.me/KillHosein" class="credit-link" target="_blank">
                    طراحی و توسعه توسط <span class="font-bold text-indigo-400">KillHosein</span>
                    <i class="fa-brands fa-telegram"></i>
                </a>
            </form>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>