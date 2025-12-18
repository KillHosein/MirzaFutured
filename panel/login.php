<?php
/**
 * Login Page - Quantum Glass Edition
 * Ultra-Premium Animations, Dynamic Deep Space & Advanced Glassmorphism
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل مدیریت حرفه‌ای</title>

    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.5);
            --secondary: #d946ef;
            --accent: #00f2ff;
            --bg-void: #02040a;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #fff;
        }

        /* --- QUANTUM BACKGROUND --- */
        .space-engine {
            position: fixed; inset: 0; z-index: -10;
            background: radial-gradient(circle at 50% 50%, #0f172a 0%, #02040a 100%);
        }

        .nebula {
            position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.3;
            animation: nebulaDrift 30s infinite alternate ease-in-out;
        }
        .n-1 { width: 700px; height: 700px; background: var(--primary); top: -20%; left: -10%; }
        .n-2 { width: 600px; height: 600px; background: var(--secondary); bottom: -10%; right: -10%; animation-delay: -7s; }
        .n-3 { width: 500px; height: 500px; background: var(--accent); top: 30%; left: 20%; opacity: 0.15; animation-delay: -15s; }

        @keyframes nebulaDrift {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            100% { transform: translate(100px, 50px) scale(1.2) rotate(10deg); }
        }

        .stars-container {
            position: absolute; inset: 0;
            background-image: 
                radial-gradient(1px 1px at 20px 30px, #fff, transparent),
                radial-gradient(1.5px 1.5px at 40px 70px, #fff, transparent),
                radial-gradient(1px 1px at 90px 40px, #fff, transparent),
                radial-gradient(2px 2px at 150px 150px, #fff, transparent);
            background-size: 300px 300px;
            animation: starsTwinkle 150s linear infinite;
            opacity: 0.4;
        }
        @keyframes starsTwinkle { from { background-position: 0 0; } to { background-position: 1000px 2000px; } }

        /* --- PREVIEW GLASS CARD --- */
        .glass-container {
            position: relative;
            padding: 3px;
            border-radius: 35px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent, rgba(255,255,255,0.1));
            animation: cardEntrance 1s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .glass-card {
            width: 100%; max-width: 420px;
            background: rgba(10, 15, 28, 0.8);
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 32px;
            padding: 50px 45px;
            box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
            z-index: 100;
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: scale(0.9) translateY(30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* --- LOGO FX --- */
        .logo-wrapper {
            display: flex; justify-content: center; margin-bottom: 35px;
        }
        .shield-icon {
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 30px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 40px var(--primary-glow);
            position: relative;
            animation: floatIcon 6s ease-in-out infinite;
        }
        .shield-icon i { font-size: 45px; color: #fff; filter: drop-shadow(0 0 15px var(--primary)); }
        
        .shield-icon::after {
            content: ''; position: absolute; inset: -5px; border-radius: 35px;
            border: 1px solid var(--primary); opacity: 0.3; animation: pulseRing 3s infinite;
        }

        @keyframes floatIcon { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
        @keyframes pulseRing { 0% { transform: scale(1); opacity: 0.3; } 100% { transform: scale(1.2); opacity: 0; } }

        /* --- INPUTS --- */
        .input-group { position: relative; margin-bottom: 25px; }
        .input-field {
            width: 100%;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 16px 52px 16px 18px;
            color: #fff; font-size: 14px; transition: all 0.4s; outline: none;
        }
        .input-field:focus {
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 25px rgba(0, 242, 255, 0.15), inset 0 0 10px rgba(0, 242, 255, 0.05);
        }
        .input-icon {
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.3); transition: 0.4s;
        }
        .input-field:focus + .input-icon { color: var(--accent); transform: translateY(-50%) scale(1.2); }

        /* --- SUBMIT BUTTON --- */
        .btn-quantum {
            width: 100%; padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            color: #fff; border: none; border-radius: 18px;
            font-weight: 800; font-size: 16px; cursor: pointer;
            box-shadow: 0 10px 30px var(--primary-glow);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative; overflow: hidden;
        }
        .btn-quantum:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px var(--primary-glow);
            filter: brightness(1.2);
        }
        .btn-quantum::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }
        .btn-quantum:hover::before { left: 100%; }

        /* --- SIGNATURE / CREDITS --- */
        .killhosein-footer {
            margin-top: 40px; text-align: center;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 25px;
        }
        .signature-text {
            color: rgba(255,255,255,0.5); font-size: 13px;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            text-decoration: none; transition: 0.4s;
        }
        .killhosein-brand {
            font-size: 22px; font-weight: 900;
            background: linear-gradient(90deg, #fff, var(--primary), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            letter-spacing: 1px; filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.3));
        }
        .signature-text:hover { transform: scale(1.05); color: #fff; }
        .signature-text:hover .killhosein-brand { filter: drop-shadow(0 0 15px var(--accent)); }

        /* --- ALERTS --- */
        .err-glow {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5; padding: 14px; border-radius: 16px;
            font-size: 13px; text-align: center; margin-bottom: 25px;
            animation: headShake 0.6s;
        }
        .ip-token {
            background: rgba(0,0,0,0.5); border: 1px solid var(--accent);
            padding: 10px 20px; border-radius: 14px; font-family: monospace;
            margin: 20px 0; color: var(--accent); font-weight: bold; font-size: 16px;
            display: inline-block; box-shadow: 0 0 20px rgba(0, 242, 255, 0.2);
        }
    </style>
</head>
<body>

    <!-- Space Background -->
    <div class="space-engine">
        <div class="stars-container"></div>
        <div class="nebula n-1"></div>
        <div class="nebula n-2"></div>
        <div class="nebula n-3"></div>
    </div>

    <div class="px-6 flex items-center justify-center min-h-screen w-full relative">
        
        <?php if(!$check_ip): ?>
        <!-- ACCESS DENIED -->
        <div class="glass-container">
            <div class="glass-card text-center">
                <div class="logo-wrapper">
                    <div class="shield-icon" style="border-color: rgba(239,68,68,0.5); box-shadow: 0 0 40px rgba(239,68,68,0.3);">
                        <i class="fa-solid fa-shield-virus text-rose-500"></i>
                    </div>
                </div>
                <h2 class="text-3xl font-black text-white mb-4">دسترسی مسدود</h2>
                <p class="text-slate-400 text-sm leading-8 mb-4">
                    شناسه آی‌پی شما مجاز به ورود نیست.<br>
                    لطفاً آی‌پی زیر را برای ادمین کل ارسال کنید:
                </p>
                <div class="ip-token animate__animated animate__pulse animate__infinite"><?php echo $user_ip; ?></div>
                
                <div class="killhosein-footer">
                    <a href="https://t.me/KillHosein" class="signature-text" target="_blank">
                        <span>طراحی و توسعه توسط</span>
                        <span class="killhosein-brand">KillHosein</span>
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- LOGIN FORM -->
        <div class="glass-container">
            <div class="glass-card">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="logo-wrapper">
                        <div class="shield-icon">
                            <i class="fa-solid fa-bolt-lightning"></i>
                        </div>
                    </div>
                    
                    <h2 class="text-center text-3xl font-black text-white mb-2">خوش‌آمدید</h2>
                    <p class="text-center text-[11px] text-indigo-300 uppercase tracking-[5px] mb-10 opacity-70">Secured Access Only</p>

                    <?php if(!empty($texterrr)): ?>
                    <div class="err-glow">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo $texterrr; ?>
                    </div>
                    <?php endif; ?>

                    <div class="input-group">
                        <input type="text" name="username" class="input-field" placeholder="نام کاربری" required autofocus autocomplete="off">
                        <i class="fa-solid fa-user-shield input-icon"></i>
                    </div>

                    <div class="input-group">
                        <input type="password" name="password" class="input-field" placeholder="رمز عبور" required>
                        <i class="fa-solid fa-unlock-keyhole input-icon"></i>
                    </div>

                    <button class="btn-quantum" name="login" type="submit">
                        ورود به پنل مدیریت <i class="fa-solid fa-arrow-left-long mr-3"></i>
                    </button>

                    <div class="killhosein-footer">
                        <a href="https://t.me/KillHosein" class="signature-text" target="_blank">
                            <span>طراحی و توسعه توسط</span>
                            <span class="killhosein-brand">KillHosein</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>