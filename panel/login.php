<?php
/**
 * Login Page - Cosmic Glass Edition
 * Matches the keyboard editor theme
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
            --bg-deep: #020617;
            --primary: #6366f1; /* Indigo */
            --primary-glow: rgba(99, 102, 241, 0.5);
            --accent: #d946ef; /* Fuchsia */
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-panel: rgba(15, 23, 42, 0.6);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; margin: 0;
        }

        /* --- BACKGROUND FX --- */
        .cosmic-bg {
            position: fixed; inset: 0; z-index: -2;
            background: radial-gradient(circle at 50% 120%, #1e1b4b, #020617 60%);
        }
        .orb {
            position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.4;
            animation: float 20s infinite ease-in-out;
        }
        .orb-1 { width: 500px; height: 500px; background: var(--primary); top: -20%; left: -10%; }
        .orb-2 { width: 400px; height: 400px; background: var(--accent); bottom: -10%; right: -10%; animation-delay: -5s; }
        
        .grid-overlay {
            position: fixed; inset: 0; z-index: -1; opacity: 0.2;
            background-image: linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            mask-image: radial-gradient(circle at center, black 40%, transparent 100%);
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, 50px); }
        }

        /* --- LOGIN CARD --- */
        .login-card {
            width: 100%; max-width: 420px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative; overflow: hidden;
            animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        
        /* Top Highlight */
        .login-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        }

        .logo-area {
            display: flex; flex-direction: column; align-items: center; margin-bottom: 30px;
        }
        .logo-box {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(217,70,239,0.2));
            border-radius: 24px; border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 40px rgba(99,102,241,0.3);
            margin-bottom: 16px;
        }
        .logo-box i { font-size: 36px; color: #a5b4fc; filter: drop-shadow(0 0 10px rgba(165,180,252,0.5)); }

        /* --- FORMS --- */
        .input-group { position: relative; margin-bottom: 20px; }
        
        .input-icon {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); transition: 0.3s;
        }
        
        .form-control {
            width: 100%;
            background: rgba(2, 6, 23, 0.5);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 14px 45px 14px 16px;
            color: white; font-family: inherit; font-size: 14px;
            transition: all 0.3s; outline: none;
        }
        .form-control:focus {
            background: rgba(2, 6, 23, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .form-control:focus + .input-icon { color: var(--primary); }
        .form-control::placeholder { color: rgba(148, 163, 184, 0.5); }

        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            color: white; border: none; border-radius: 16px;
            font-weight: 700; font-size: 15px; cursor: pointer;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
            transition: all 0.3s; margin-top: 10px;
            position: relative; overflow: hidden;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.6);
            filter: brightness(1.1);
        }
        .btn-login:active { transform: scale(0.98); }

        /* --- ERROR --- */
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5; padding: 12px; border-radius: 12px;
            font-size: 13px; text-align: center; margin-bottom: 20px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            animation: shake 0.5s;
        }
        
        .ip-card {
            text-align: center; padding: 20px;
        }
        .ip-badge {
            background: rgba(255,255,255,0.05); border: 1px dashed rgba(255,255,255,0.2);
            padding: 8px 16px; border-radius: 10px; font-family: monospace; letter-spacing: 1px;
            margin-top: 15px; display: inline-block; color: #a5b4fc;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>

<body>
    <div class="cosmic-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="grid-overlay"></div>

    <div class="container">
        <!-- Restricted Access View -->
        <?php if(!$check_ip): ?>
        <div class="login-card">
            <div class="logo-area">
                <div class="logo-box" style="border-color: rgba(239,68,68,0.3); background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(185,28,28,0.1)); box-shadow: 0 0 40px rgba(239,68,68,0.2);">
                    <i class="fa-solid fa-ban" style="color: #fca5a5;"></i>
                </div>
                <h1 class="text-2xl font-bold text-white mt-4">دسترسی محدود</h1>
            </div>
            
            <div class="text-center text-slate-400 text-sm leading-7 mb-6">
                <p>دسترسی شما به پنل مدیریت محدود شده است.</p>
                <p>جهت رفع محدودیت، وارد ربات شده و از بخش <b>تنظیمات</b>، دکمه <b>«تنظیم IP»</b> را انتخاب کنید.</p>
            </div>
            
            <div class="text-center">
                <span class="text-xs text-slate-500 uppercase tracking-wider">آی‌پی شما</span><br>
                <div class="ip-badge" id="user-ip" dir="ltr"><?php echo $user_ip; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Login Form View -->
        <?php if($check_ip): ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="login-card">
                <div class="logo-area">
                    <div class="logo-box">
                        <i class="fa-solid fa-cube animate__animated animate__pulse animate__infinite"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mt-2">پنل مدیریت</h2>
                    <p class="text-xs text-indigo-300 opacity-70 mt-1 uppercase tracking-[2px]">Mirza Bot Login</p>
                </div>

                <?php if(!empty($texterrr)): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo $texterrr; ?>
                </div>
                <?php endif; ?>

                <div class="input-group">
                    <input type="text" name="username" class="form-control" placeholder="نام کاربری" required autofocus autocomplete="off">
                    <i class="fa-regular fa-user input-icon"></i>
                </div>

                <div class="input-group">
                    <input type="password" name="password" class="form-control" placeholder="کلمه عبور" required>
                    <i class="fa-solid fa-lock input-icon"></i>
                </div>

                <button class="btn-login group" name="login" type="submit">
                    <span class="group-hover:hidden">ورود به سیستم</span>
                    <span class="hidden group-hover:inline-block"><i class="fa-solid fa-arrow-left"></i> ورود</span>
                </button>
                
                <div class="text-center mt-6">
                    <p class="text-[10px] text-slate-500">
                        &copy; طراحی و توسعه توسط تیم میرزا بات
                    </p>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

</body>
</html>