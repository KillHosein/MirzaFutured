<?php
/**
 * Telegram Web App - Quantum Supreme Edition v2.0
 * Professional TWA Hosting with Cinematic "Deep Space" Design
 * Designed & Developed by KillHosein
 */

// --- PHP Routing Logic (Keep Intact) ---
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
    <title>Mirza Pro | Quantum Edition</title>
    <base href="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>" />
    
    <!-- Libraries -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script src="<?php echo htmlspecialchars($assetPrefix . 'js/telegram-web-app.js', ENT_QUOTES); ?>"></script>
    
    <!-- Core App Styles -->
    <link rel="stylesheet" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/index-BoHBsj0Z.css', ENT_QUOTES); ?>">

    <style>
        :root {
            /* --- 1. Quantum Color Palette --- */
            --bg-void: #000000;       /* Absolute Black */
            --bg-deep: #050511;       /* Deep Space Blue */
            
            /* Accents: Neon Cyan & Electric Purple */
            --primary-glow: #4f46e5;
            --accent-cyan: #06b6d4;   /* Cyan 500 */
            --accent-purple: #a855f7; /* Purple 500 */
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            
            /* --- Glassmorphism 2.0 --- */
            --glass-bg: rgba(20, 20, 35, 0.6);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-shine: rgba(255, 255, 255, 0.03);
            --glass-blur: 24px;
            
            --radius-xl: 24px;
            --radius-lg: 16px;
        }

        /* --- 2. Global Reset & Body --- */
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            outline: none !important;
            user-select: none; /* App-like feel */
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            color: var(--text-main);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            
            /* Deep Space Gradient */
            background: radial-gradient(circle at 50% 0%, #1e1b4b 0%, #020617 60%, #000000 100%);
        }

        /* Ambient Noise Texture (Adds premium feel) */
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: -1;
        }

        /* Canvas for Starfield */
        #bg-canvas {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
            opacity: 0.8;
            pointer-events: none;
        }

        /* --- 3. Cinematic Splash Screen --- */
        #splash-screen {
            position: fixed;
            inset: 0;
            background: #020617;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            transition: opacity 0.6s ease, visibility 0.6s;
        }

        .splash-logo-container {
            position: relative;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pulse-ring {
            position: absolute;
            width: 100%; height: 100%;
            border-radius: 50%;
            border: 2px solid var(--accent-purple);
            opacity: 0;
            animation: pulseWave 2s infinite;
        }
        .pulse-ring:nth-child(2) { animation-delay: 0.5s; border-color: var(--accent-cyan); }

        @keyframes pulseWave {
            0% { transform: scale(0.5); opacity: 0; }
            50% { opacity: 0.5; }
            100% { transform: scale(2); opacity: 0; }
        }

        .splash-text {
            margin-top: 30px;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 2px;
            background: linear-gradient(135deg, #fff, var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(6, 182, 212, 0.5);
            animation: textGlow 2s ease-in-out infinite alternate;
        }
        @keyframes textGlow { from { text-shadow: 0 0 10px rgba(6,182,212,0.3); } to { text-shadow: 0 0 25px rgba(6,182,212,0.8); } }

        /* --- 4. Advanced Component Overrides --- */
        
        /* Make React App containers transparent */
        #root > div, .bg-zinc-900, .bg-slate-900, .bg-gray-900, .bg-black, .bg-\[\#18181b\] {
            background: transparent !important;
        }

        /* Glass Cards - Targeting specific classes seen in screenshot */
        .card, 
        .bg-zinc-800, 
        .bg-slate-800, 
        .bg-surface,
        /* Target the specific card structure likely used in the screenshot */
        .rounded-2xl.bg-zinc-900, 
        .rounded-xl.bg-zinc-900,
        .bg-\[\#18181b\], /* Common Tailwind arbitrary value for dark backgrounds */
        .bg-\[\#09090b\],
        .bg-card {
            background: var(--glass-bg) !important;
            backdrop-filter: blur(var(--glass-blur)) !important;
            -webkit-backdrop-filter: blur(var(--glass-blur)) !important;
            border: 1px solid var(--glass-border) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
            border-radius: var(--radius-lg) !important;
            position: relative;
            overflow: hidden;
        }

        /* Override specific text colors for better contrast on glass */
        .text-zinc-500, .text-gray-500, .text-slate-500 {
            color: var(--text-muted) !important;
        }
        
        /* Force icons to be colorful */
        i, svg {
            filter: drop-shadow(0 0 5px rgba(255,255,255,0.2));
        }

        /* Shine Effect on Cards */
        .card::after, .bg-zinc-800::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--glass-shine), transparent);
        }

        /* Typography */
        h1, h2, h3, .font-bold {
            color: #fff !important;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        p, .text-sm { color: var(--text-muted) !important; }

        /* --- 5. Modern Buttons (Neumorphic Glass) --- */
        button, .btn {
            font-family: 'Vazirmatn', sans-serif !important;
            font-weight: 700 !important;
            border-radius: 16px !important; /* Softer corners */
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
        }
        
        button:active, .btn:active { 
            transform: scale(0.94) translateY(2px) !important; 
        }

        /* Primary Button (Neon Gradient + Inner Glow) */
        [class*="bg-primary"], .bg-blue-600, .bg-indigo-600 {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.9), rgba(168, 85, 247, 0.9)) !important;
            box-shadow: 
                0 4px 15px rgba(79, 70, 229, 0.4),
                inset 0 1px 1px rgba(255, 255, 255, 0.3),
                inset 0 -2px 5px rgba(0, 0, 0, 0.2) !important;
            border: 1px solid rgba(255,255,255,0.15) !important;
            color: white !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        [class*="bg-primary"]:hover, .bg-blue-600:hover {
            box-shadow: 
                0 8px 25px rgba(79, 70, 229, 0.6),
                inset 0 1px 1px rgba(255, 255, 255, 0.4) !important;
            transform: translateY(-2px);
        }

        /* Secondary/Ghost Buttons */
        .bg-transparent, .border {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-muted) !important;
        }
        .bg-transparent:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-color: var(--accent-cyan) !important;
        }

        /* --- 6. Form Inputs (Animated Borders) --- */
        input, textarea, select {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: 16px !important;
            color: white !important;
            transition: all 0.3s ease !important;
            padding: 14px 16px !important;
            font-size: 15px !important;
        }
        input:focus, textarea:focus, select:focus {
            border-color: var(--accent-cyan) !important;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.15) !important;
            background: rgba(15, 23, 42, 0.9) !important;
            transform: translateY(-2px);
        }

        /* --- 7. Floating Bottom Navigation (Island Dock) --- */
        .fixed.bottom-0, nav[class*="fixed"], [class*="bottom-nav"] {
            /* Reset default full-width styles */
            left: 50% !important;
            transform: translateX(-50%) !important;
            bottom: 20px !important; /* Floating effect */
            width: 90% !important;
            max-width: 400px !important;
            border-radius: 24px !important;
            
            /* Glassmorphism */
            background: rgba(10, 10, 20, 0.75) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            
            /* Glow & Shadow */
            box-shadow: 
                0 10px 30px rgba(0,0,0,0.5),
                0 0 0 1px rgba(255,255,255,0.05) !important;
                
            padding: 10px 20px !important;
            display: flex !important;
            justify-content: space-around !important;
            align-items: center !important;
            z-index: 999 !important;
        }
        
        /* Active Nav Item */
        [class*="text-primary"], .text-blue-500, .text-indigo-500 {
            color: var(--accent-cyan) !important;
            position: relative;
        }
        /* Active Indicator Dot */
        [class*="text-primary"]::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            background: var(--accent-cyan);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent-cyan);
        }

        /* --- 8. Footer --- */
        .pro-footer {
            margin-top: auto;
            padding: 20px;
            text-align: center;
            position: relative;
            z-index: 10;
        }
        .credit-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 50px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 12px;
            backdrop-filter: blur(10px);
            transition: 0.3s;
        }
        .credit-badge:hover {
            background: rgba(255,255,255,0.08);
            transform: translateY(-2px);
            border-color: rgba(255,255,255,0.15);
            color: #fff;
        }
        .credit-badge i { color: var(--accent-cyan); }

        /* --- 9. Animations --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <script>
      window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    
    <!-- React Entry Point -->
    <script type="module" crossorigin src="<?php echo htmlspecialchars($assetPrefix . 'assets/index-C-2a0Dur.js', ENT_QUOTES); ?>"></script>
    <link rel="modulepreload" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/vendor-CIGJ9g2q.js', ENT_QUOTES); ?>">
    
    <!-- UI Enhancer -->
    <script src="<?php echo htmlspecialchars($assetPrefix . 'js/ui-enhancer.js', ENT_QUOTES); ?>"></script>
</head>
<body>
    
    <canvas id="bg-canvas"></canvas>

    <!-- Cinematic Splash -->
    <div id="splash-screen">
        <div class="splash-logo-container">
            <div class="pulse-ring"></div>
            <div class="pulse-ring"></div>
            <i class="fa-solid fa-bolt fa-2x" style="color: #fff; z-index: 2;"></i>
        </div>
        <div class="splash-text">MIRZA PRO</div>
    </div>

    <div id="root"></div>

    <footer class="pro-footer">
        <a href="https://t.me/KillHosein" class="credit-badge" target="_blank">
            <i class="fa-solid fa-code"></i>
            <span>Designed by <b>KillHosein</b></span>
        </a>
    </footer>

</body>
</html>