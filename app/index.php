<?php
/**
 * Telegram Web App - Quantum Edition
 * Optimized for TWA with Ultra-Premium Theme
 * Designed & Developed by KillHosein
 */

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
if ($scriptDir === '.' || $scriptDir === '') {
    $scriptDir = '';
} elseif ($scriptDir !== '/') {
    $scriptDir = '/' . ltrim($scriptDir, '/');
    $scriptDir = rtrim($scriptDir, '/');
} else {
    $scriptDir = '/';
}
$basename = $scriptDir === '' ? '/' : $scriptDir;
$prefix = $basename === '/' ? '/' : $basename . '/';
$assetPrefix = $prefix;
$rootForApi = $basename === '/' ? '/' : rtrim(dirname($basename), '/');
if ($rootForApi === '' || $rootForApi === '.') {
    $rootForApi = '/';
}
$apiPath = $rootForApi === '/' ? '/api' : $rootForApi . '/api';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
if (is_string($forwardedProto) && $forwardedProto !== '') {
    $scheme = explode(',', $forwardedProto)[0];
} elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
    $scheme = $_SERVER['REQUEST_SCHEME'];
} else {
    $https = $_SERVER['HTTPS'] ?? '';
    $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiUrl = rtrim($scheme . '://' . $host, '/') . $apiPath;
$config = [
    'basename' => $basename,
    'prefix' => $prefix,
    'apiUrl' => $apiUrl,
    'assetPrefix' => $assetPrefix,
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Professional Web App</title>
    <base href="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>" />
    
    <!-- Libraries -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script src="<?php echo htmlspecialchars($assetPrefix . 'js/telegram-web-app.js', ENT_QUOTES); ?>"></script>
    
    <style>
        :root {
            --primary: #8b5cf6;
            --secondary: #06b6d4;
            --accent: #d946ef;
            --bg-void: #000;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            margin: 0; padding: 0;
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* --- SUPREME VIDEO BACKGROUND --- */
        .video-background {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -10; overflow: hidden;
        }
        .video-background video {
            width: 100%; height: 100%; object-fit: cover;
            filter: brightness(0.4) contrast(1.1);
        }
        .video-overlay {
            position: absolute; inset: 0;
            background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.7) 100%);
        }

        .starfield {
            position: fixed; inset: 0; z-index: -9;
            background-image: radial-gradient(1px 1px at 20px 30px, #fff, transparent), radial-gradient(1.5px 1.5px at 40px 70px, #fff, transparent);
            background-size: 300px 300px;
            animation: moveStars 150s linear infinite;
            opacity: 0.3;
        }
        @keyframes moveStars { from { background-position: 0 0; } to { background-position: 0 4000px; } }

        /* --- CONTENT WRAPPER --- */
        #root {
            flex: 1;
            z-index: 10;
            position: relative;
            /* این بخش اجازه می‌دهد اپلیکیشن شما بدون مشکل رندر شود */
        }

        /* --- SUPREME FOOTER (KILLHOSEIN) --- */
        .supreme-footer {
            padding: 50px 20px;
            text-align: center;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            border-top: 1px solid rgba(255,255,255,0.05);
            z-index: 20;
            position: relative;
        }
        .brand-link {
            text-decoration: none !important;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            transition: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .brand-hint {
            font-size: 14px;
            color: rgba(255,255,255,0.4);
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .brand-name {
            font-size: clamp(28px, 8vw, 48px);
            font-weight: 950;
            background: linear-gradient(90deg, #fff, #a78bfa, #22d3ee, #d946ef);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1.5px;
            filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.6));
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        .brand-tg {
            font-size: clamp(35px, 10vw, 60px);
            color: #229ED9;
            -webkit-text-fill-color: #229ED9;
            filter: drop-shadow(0 0 20px rgba(34, 158, 217, 0.8));
            transition: 0.5s;
        }
        .brand-link:hover { transform: scale(1.1); }
        .brand-link:hover .brand-tg { transform: rotate(-15deg) scale(1.2); }
        
        .contact-text {
            font-size: 11px;
            color: var(--secondary);
            margin-top: 5px;
            opacity: 0.7;
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 0.5; } 50% { opacity: 1; } }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    </style>

    <script>
      window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script type="module" crossorigin src="<?php echo htmlspecialchars($assetPrefix . 'assets/index-C-2a0Dur.js', ENT_QUOTES); ?>"></script>
    <link rel="modulepreload" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/vendor-CIGJ9g2q.js', ENT_QUOTES); ?>">
    <link rel="stylesheet" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/index-BoHBsj0Z.css', ENT_QUOTES); ?>">
</head>
<body>

    <!-- Space Background Engine -->
    <div class="video-background">
        <video autoplay muted loop playsinline>
            <source src="https://assets.mixkit.co/videos/preview/mixkit-flying-through-stars-in-space-23214-large.mp4" type="video/mp4">
        </video>
        <div class="video-overlay"></div>
    </div>
    <div class="starfield"></div>

    <!-- Application Root -->
    <div id="root"></div>

    <!-- Supreme Footer -->
    <footer class="supreme-footer">
        <a href="https://t.me/KillHosein" class="brand-link" target="_blank">
            <span class="brand-hint">Designed & Developed by</span>
            <span class="brand-name">
                <i class="fa-brands fa-telegram brand-tg"></i> KillHosein
            </span>
            <span class="contact-text">جهت ارتباط و پشتیبانی در تلگرام کلیک کنید</span>
        </a>
    </footer>

</body>
</html>