<?php
/**
 * Login Page - Quantum Glass Edition (Ultimate Supreme)
 * Ultra-Premium Animations, Particle Deep Space & Advanced Glassmorphism
 * Fully Responsive for Mobile & Tablet
 * Designed & Developed by KillHosein
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
        $texterrr = 'اطلاعات ورود نادرست است';
    } else {
        if ( $password == $result["password"]) {
            foreach ($admin_ids as $admin) {
                $texts = "🚀 ورود موفقیت‌آمیز:\nکاربر: <b>$username</b>\nآی‌پی: <code>$user_ip</code>";
                sendmessage($admin, $texts, null, 'html');
            }
            $_SESSION["user"] = $result["username"];
            header('Location: index.php');
            exit;
        } else {
            $texterrr = 'رمز عبور اشتباه است';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ورود به پنل مدیریت حرفه‌ای</title>

    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #8b5cf6; /* Violet */
            --primary-glow: rgba(139, 92, 246, 0.6);
            --secondary: #06b6d4; /* Cyan */
            --accent: #d946ef; /* Fuchsia */
            --bg-void: #02040a;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            color: #fff;
        }

        /* --- SUPREME SPACE BACKGROUND --- */
        .background-engine {
            position: fixed; inset: 0; z-index: -10;
            background: radial-gradient(circle at 50% 50%, #0f172a 0%, #02040a 100%);
        }

        .star-field {
            position: absolute; inset: 0;
            background-image: 
                radial-gradient(1.5px 1.5px at 25% 35%, #fff, transparent),
                radial-gradient(2px 2px at 50% 60%, #fff, transparent),
                radial-gradient(1.5px 1.5px at 75% 20%, #fff, transparent),
                radial-gradient(2px 2px at 15% 85%, #fff, transparent),
                radial-gradient(1.5px 1.5px at 85% 75%, #fff, transparent);
            background-size: 400px 400px;
            animation: moveStars 120s linear infinite;
            opacity: 0.5;
        }

        .nebula-core {
            position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.35;
            animation: nebulaFlow 40s infinite alternate ease-in-out;
        }
        .nc-1 { width: 80vw; height: 80vw; max-width: 800px; max-height: 800px; background: var(--primary); top: -25%; left: -15%; }
        .nc-2 { width: 70vw; height: 70vw; max-width: 700px; max-height: 700px; background: var(--accent); bottom: -15%; right: -15%; animation-delay: -8s; }

        @keyframes moveStars { from { background-position: 0 0; } to { background-position: 0 4000px; } }
        @keyframes nebulaFlow {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            100% { transform: translate(15%, 8%) scale(1.3) rotate(15deg); }
        }

        /* --- PREVIEW GLASS CARD --- */
        .card-container {
            position: relative;
            padding: 2px;
            border-radius: 40px;
            background: linear-gradient(145deg, rgba(255,255,255,0.15), transparent, rgba(255,255,255,0.05));
            animation: fadeInUp 1.2s cubic-bezier(0.22, 1, 0.36, 1);
            z-index: 100;
            width: 90%;
            max-width: 520px;
            margin: 20px auto;
        }

        .glass-card {
            width: 100%;
            background: rgba(10, 15, 28, 0.82);
            backdrop-filter: blur(35px) saturate(200%);
            -webkit-backdrop-filter: blur(35px) saturate(200%);
            border-radius: 38px;
            padding: 50px 40px;
            box-shadow: 0 60px 120px -30px rgba(0, 0, 0, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .glass-card::after {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%);
            pointer-events: none;
        }

        /* --- LOGO AREA --- */
        .avatar-section {
            display: flex; justify-content: center; margin-bottom: 35px;
        }
        .glowing-circle {
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.02);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 32px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 50px var(--primary-glow);
            position: relative;
            animation: floatIcon 6s ease-in-out infinite;
        }
        .glowing-circle i { font-size: 45px; color: #fff; filter: drop-shadow(0 0 20px var(--primary)); }
        
        .glowing-circle::before {
            content: ''; position: absolute; inset: -8px; border-radius: 40px;
            border: 2px solid var(--primary); opacity: 0.2; animation: pulseRing 4s infinite;
        }

        @keyframes floatIcon { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-15px) rotate(3deg); } }
        @keyframes pulseRing { 0% { transform: scale(1); opacity: 0.3; } 100% { transform: scale(1.4); opacity: 0; } }

        /* --- INPUTS --- */
        .input-wrap { position: relative; margin-bottom: 25px; }
        .input-item {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 18px 55px 18px 20px;
            color: #fff; font-size: 15px; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }
        .input-item:focus {
            border-color: var(--secondary);
            background: rgba(255, 255, 255, 0.07);
            box-shadow: 0 0 35px rgba(6, 182, 212, 0.25), inset 0 0 15px rgba(6, 182, 212, 0.05);
        }
        .item-icon {
            position: absolute; right: 22px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.3); transition: 0.4s; font-size: 18px;
        }
        .input-item:focus + .item-icon { color: var(--secondary); transform: translateY(-50%) scale(1.1); }

        /* --- SUPREME BUTTON --- */
        .btn-supreme {
            width: 100%; padding: 18px;
            background: linear-gradient(135deg, var(--primary) 0%, #6d28d9 100%);
            color: #fff; border: none; border-radius: 20px;
            font-weight: 800; font-size: 16px; cursor: pointer;
            box-shadow: 0 15px 40px var(--primary-glow);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative; overflow: hidden;
        }
        .btn-supreme:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 55px var(--primary-glow);
            filter: brightness(1.2);
        }
        .btn-supreme::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: 0.6s;
        }
        .btn-supreme:hover::after { left: 100%; }

        /* --- SUPREME FOOTER (KILLHOSEIN) --- */
        .supreme-footer {
            margin-top: 45px; text-align: center;
            border-top: 1.5px solid rgba(255,255,255,0.08);
            padding-top: 30px;
        }
        .developer-info {
            color: rgba(255,255,255,0.5); font-size: 14px;
            display: flex; flex-direction: column; align-items: center; gap: 10px;
            text-decoration: none; transition: 0.5s;
        }
        .developer-name {
            font-size: clamp(24px, 8vw, 42px); font-weight: 950;
            background: linear-gradient(90deg, #fff, #a78bfa, var(--secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: 1px; filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.5));
            display: flex; align-items: center; justify-content: center; gap: 12px;
        }
        .tg-symbol {
            font-size: clamp(28px, 10vw, 52px); color: #229ED9; 
            -webkit-text-fill-color: #229ED9;
            filter: drop-shadow(0 0 20px rgba(34, 158, 217, 0.7));
            transition: 0.4s;
        }
        .developer-info:hover { transform: scale(1.05); color: #fff; }
        .developer-info:hover .tg-symbol { transform: rotate(-12deg) scale(1.2); }

        .contact-hint {
            font-size: 11px; color: var(--secondary); margin-top: 5px;
            font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
            opacity: 0.8; animation: pulse 2s infinite;
            text-align: center; line-height: 1.6;
        }

        @keyframes pulse { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }

        /* --- SYSTEM ALERTS --- */
        .system-err {
            background: rgba(244, 63, 94, 0.12); border: 1.5px solid rgba(244, 63, 94, 0.35);
            color: #fda4af; padding: 15px; border-radius: 18px;
            font-size: 13px; text-align: center; margin-bottom: 25px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            animation: shakeX 1s;
        }
        .ip-key {
            background: rgba(0,0,0,0.6); border: 1.5px solid var(--secondary);
            padding: 12px 20px; border-radius: 15px; font-family: 'JetBrains Mono', monospace;
            margin: 15px 0; color: var(--secondary); font-weight: bold; font-size: 16px;
            display: inline-block; box-shadow: 0 0 30px rgba(6, 182, 212, 0.3);
            word-break: break-all;
        }

        /* --- MOBILE REFINEMENTS --- */
        @media (max-width: 480px) {
            .glass-card {
                padding: 40px 25px;
                border-radius: 32px;
            }
            .card-container {
                width: 95%;
                border-radius: 34px;
            }
            .avatar-section { margin-bottom: 25px; }
            .developer-name { gap: 8px; }
            .developer-info { font-size: 12px; }
        }
    </style>
</head>
<body>

    <!-- Dynamic Space Layers -->
    <div class="background-engine">
        <div class="star-field"></div>
        <div class="nebula-core nc-1"></div>
        <div class="nebula-core nc-2"></div>
    </div>

    <div class="w-full flex items-center justify-center p-4">
        
        <?php if(!$check_ip): ?>
        <!-- ACCESS CONTROL VIEW -->
        <div class="card-container">
            <div class="glass-card text-center">
                <div class="avatar-section">
                    <div class="glowing-circle" style="border-color: rgba(244,63,94,0.5); box-shadow: 0 0 50px rgba(244,63,94,0.3);">
                        <i class="fa-solid fa-user-lock text-rose-500"></i>
                    </div>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-white mb-4">دسترسی مسدود شد</h2>
                <p class="text-slate-400 text-sm leading-7 mb-4 px-2">
                    شناسه آی‌پی شما در لیست سفید سرور ثبت نشده است.<br>
                    لطفاً برای دسترسی، آی‌پی زیر را فعال نمایید:
                </p>
                <div class="ip-key animate__animated animate__pulse animate__infinite"><?php echo $user_ip; ?></div>
                
                <div class="supreme-footer">
                    <a href="https://t.me/KillHosein" class="developer-info" target="_blank">
                        <span class="font-medium opacity-80">Designed & Developed by</span>
                        <span class="developer-name">
                            <i class="fa-brands fa-telegram tg-symbol"></i> KillHosein
                        </span>
                        <span class="contact-hint">جهت ارتباط و پشتیبانی در تلگرام کلیک کنید</span>
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- SUPREME LOGIN FORM -->
        <div class="card-container">
            <div class="glass-card">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="avatar-section">
                        <div class="glowing-circle">
                            <i class="fa-solid fa-unlock-keyhole"></i>
                        </div>
                    </div>
                    
                    <h2 class="text-center text-3xl sm:text-4xl font-black text-white mb-2">خوش‌آمدید</h2>
                    <p class="text-center text-[10px] text-violet-400 uppercase tracking-[4px] mb-10 opacity-80 font-bold">Secured Management Panel</p>

                    <?php if(!empty($texterrr)): ?>
                    <div class="system-err">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $texterrr; ?>
                    </div>
                    <?php endif; ?>

                    <div class="input-wrap">
                        <input type="text" name="username" class="input-item" placeholder="نام کاربری" required autofocus autocomplete="off">
                        <i class="fa-solid fa-shield-user item-icon"></i>
                    </div>

                    <div class="input-wrap">
                        <input type="password" name="password" class="input-item" placeholder="رمز عبور" required>
                        <i class="fa-solid fa-key item-icon"></i>
                    </div>

                    <button class="btn-supreme" name="login" type="submit">
                        تایید و ورود به مدیریت <i class="fa-solid fa-arrow-left-long mr-3"></i>
                    </button>

                    <div class="supreme-footer">
                        <a href="https://t.me/KillHosein" class="developer-info" target="_blank">
                            <span class="font-medium opacity-80">طراحی و توسعه توسط</span>
                            <span class="developer-name">
                                <i class="fa-brands fa-telegram tg-symbol"></i> KillHosein
                            </span>
                            <span class="contact-hint">جهت ارتباط و پشتیبانی در تلگرام کلیک کنید</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>