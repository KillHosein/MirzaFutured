<?php
/**
 * Telegram Web App - Quantum Supreme Edition
 * Professional TWA Hosting with Cinematic Background
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
    <title>Mirza Pro</title>
    <base href="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>" />
    
    <!-- Libraries & Assets -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script src="<?php echo htmlspecialchars($assetPrefix . 'js/telegram-web-app.js', ENT_QUOTES); ?>"></script>
    
    <!-- Your Core App Styles -->
    <link rel="stylesheet" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/index-BoHBsj0Z.css', ENT_QUOTES); ?>">

    <style>
        :root {
            /* --- 1. Color Palette (Harmonious & Modern) --- */
            --bg-deep: #020617; /* Slate 950 */
            --bg-surface: #0f172a; /* Slate 900 */
            
            --primary-start: #4f46e5; /* Indigo 600 */
            --primary-end: #8b5cf6; /* Violet 500 */
            --primary-glow: rgba(99, 102, 241, 0.5);
            
            --accent-success: #10b981; /* Emerald 500 */
            --accent-danger: #f43f5e; /* Rose 500 */
            --accent-warning: #f59e0b; /* Amber 500 */
            
            --text-primary: #f8fafc; /* Slate 50 */
            --text-secondary: #94a3b8; /* Slate 400 */
            
            /* --- Glassmorphism Variables --- */
            --glass-panel: rgba(15, 23, 42, 0.65);
            --glass-card: rgba(30, 41, 59, 0.4);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-highlight: rgba(255, 255, 255, 0.03);
            
            /* --- Spacing & Radius --- */
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --spacing-container: 20px;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            outline: none !important;
        }

        /* --- 2. Typography & Base Layout --- */
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            margin: 0; 
            padding: 0;
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            
            /* Cinematic Gradient Background */
            background: radial-gradient(circle at top left, #1e1b4b 0%, #0f172a 40%, #020617 100%);
        }

        /* Interactive Canvas Background */
        #bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.5;
            pointer-events: none; /* Let clicks pass through */
        }

        /* --- 3. Splash Screen (Premium Animation) --- */
        #splash-screen {
            position: fixed;
            inset: 0;
            background: var(--bg-deep);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.8s;
        }
        
        .loader-ring {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            position: relative;
            background: conic-gradient(from 0deg, transparent 0%, var(--primary-end) 100%);
            animation: spin 1s linear infinite;
            box-shadow: 0 0 30px var(--primary-glow);
        }
        .loader-ring::before {
            content: '';
            position: absolute;
            inset: 4px;
            background: var(--bg-deep);
            border-radius: 50%;
        }
        
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .splash-text {
            margin-top: 24px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 4px;
            color: var(--text-primary);
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInUp 0.8s ease-out 0.3s forwards;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* --- 4. Component Overrides (The Core UI Improvements) --- */
        
        /* Transparent Containers */
        #root > div, .bg-zinc-900, .bg-slate-900, .bg-gray-900, .bg-black {
            background-color: transparent !important;
            background: transparent !important;
        }

        /* Cards & Surfaces (Glassmorphism) */
        .bg-zinc-800, .bg-slate-800, .bg-gray-800, 
        .card, [class*="card"], [class*="Card"], .bg-surface {
            background: var(--glass-card) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid var(--glass-border) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25) !important;
            border-radius: var(--radius-md) !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease !important;
        }
        
        /* Card Hover Effect (Desktop/Interaction) */
        /* Note: On mobile hover can be sticky, so we mostly rely on active states */
        
        /* Typography Polish */
        h1, h2, h3, .font-bold {
            color: var(--text-primary) !important;
            letter-spacing: -0.02em !important;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }
        
        p, span, .text-sm {
            color: var(--text-secondary) !important;
            line-height: 1.6;
        }

        /* --- 5. Buttons & Interactions --- */
        button, .btn {
            font-family: 'Vazirmatn', sans-serif !important;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border-radius: var(--radius-md) !important;
            font-weight: 700 !important;
            position: relative;
            overflow: hidden;
            border: none !important;
            cursor: pointer;
        }
        
        button:active, .btn:active {
            transform: scale(0.95) !important;
        }

        /* Primary Action Buttons */
        [class*="bg-primary"], .bg-blue-600, .bg-indigo-600 {
            background: linear-gradient(135deg, var(--primary-start), var(--primary-end)) !important;
            box-shadow: 0 4px 20px var(--primary-glow) !important;
            color: #fff !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
        }
        
        /* Secondary/Outline Buttons */
        .bg-transparent, .border {
             border-color: var(--glass-border) !important;
             background: rgba(255,255,255,0.03) !important;
        }

        /* --- 6. Form Elements (Inputs) --- */
        input, select, textarea {
            transition: all 0.3s ease !important;
            font-family: 'Vazirmatn', sans-serif !important;
            background: var(--glass-panel) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: var(--radius-md) !important;
            color: var(--text-primary) !important;
            padding: 12px 16px !important;
            font-size: 14px !important;
        }
        
        input:focus, select:focus, textarea:focus {
            transform: translateY(-2px) !important;
            border-color: var(--primary-end) !important;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15) !important;
            background: rgba(15, 23, 42, 0.9) !important;
        }
        
        ::placeholder {
            color: rgba(148, 163, 184, 0.5) !important;
        }

        /* --- 7. Navigation & Layout --- */
        /* Bottom Navigation Bar */
        .fixed.bottom-0, nav[class*="fixed"], [class*="bottom-nav"] {
            background: rgba(15, 23, 42, 0.8) !important;
            backdrop-filter: blur(25px) !important;
            -webkit-backdrop-filter: blur(25px) !important;
            border-top: 1px solid var(--glass-border) !important;
            padding-bottom: calc(env(safe-area-inset-bottom) + 10px) !important;
            padding-top: 10px !important;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.4) !important;
        }
        
        /* Active Nav Item Glow */
        [class*="text-primary"], .text-blue-500, .text-indigo-500 {
            color: #a78bfa !important; /* Lighter violet for better visibility on dark */
            text-shadow: 0 0 15px rgba(167, 139, 250, 0.4);
        }

        /* --- 8. Status & Indicators --- */
        /* Progress Bar */
        [role="progressbar"] > div, [class*="bg-blue-"], [class*="bg-indigo-"] {
            background: linear-gradient(90deg, #38bdf8, #818cf8) !important;
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.5) !important;
            border-radius: 10px !important;
        }

        /* --- 9. App Container Layout --- */
        #root {
            flex: 1;
            z-index: 10;
            position: relative;
            width: 100%;
            max-width: 500px; /* Mobile-first constraint */
            margin: 0 auto;
            padding: 20px; /* More breathing room */
            padding-bottom: 90px; /* Space for bottom nav */
        }

        /* --- 10. Footer --- */
        .professional-footer {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px 0 30px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
            /* border-top: 1px solid var(--glass-border); */ /* Removed for cleaner look */
            background: transparent;
            position: relative;
            z-index: 5;
        }

        .brand-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            padding: 10px 20px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255,255,255,0.03);
            backdrop-filter: blur(5px);
        }

        .brand-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255,255,255,0.1);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .brand-name {
            font-weight: 800;
            background: linear-gradient(135deg, #fff, #a5b4fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        /* Animation Keyframes */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .professional-footer { animation: fadeIn 1s ease-out; }
    </style>

    <script>
      window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    
    <!-- Your Core App Script -->
    <script type="module" crossorigin src="<?php echo htmlspecialchars($assetPrefix . 'assets/index-C-2a0Dur.js', ENT_QUOTES); ?>"></script>
    <link rel="modulepreload" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/vendor-CIGJ9g2q.js', ENT_QUOTES); ?>">
    
    <!-- UI Enhancer (Animations & Effects) -->
    <script src="<?php echo htmlspecialchars($assetPrefix . 'js/ui-enhancer.js', ENT_QUOTES); ?>"></script>
</head>
<body>
    
    <!-- Canvas Background -->
    <canvas id="bg-canvas"></canvas>

    <!-- Splash Screen -->
    <div id="splash-screen">
        <span class="loader"></span>
        <div class="splash-text">MIRZA PRO</div>
    </div>

    <!-- Application Mounting Point -->
    <div id="root"></div>

    <!-- Minimal Professional Footer -->
    <footer class="professional-footer">
        <a href="https://t.me/KillHosein" class="brand-link" target="_blank">
            <i class="fa-brands fa-telegram"></i>
            <span>Designed by</span>
            <span class="brand-name">KillHosein</span>
        </a>
    </footer>

</body>
</html>
