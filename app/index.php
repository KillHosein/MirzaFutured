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
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg-dark: #0f172a;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(15, 23, 42, 0.6);
            --glass-border: rgba(255, 255, 255, 0.08);
            --card-glass: rgba(30, 41, 59, 0.4);
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-dark);
            margin: 0; 
            padding: 0;
            min-height: 100vh;
            color: var(--text-main);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            
            /* Professional Gradient Background */
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(79, 70, 229, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(6, 182, 212, 0.15) 0%, transparent 40%);
            background-attachment: fixed;
        }

        /* Global UI Polish & Overrides */
        
        /* Force transparent backgrounds on main containers to show our gradient */
        #root > div, .bg-zinc-900, .bg-slate-900, .bg-gray-900, .bg-black {
            background-color: transparent !important;
            background: transparent !important;
        }

        /* Glassmorphism Cards */
        .bg-zinc-800, .bg-slate-800, .bg-gray-800, 
        .card, [class*="card"], [class*="Card"] {
            background: var(--card-glass) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            border: 1px solid var(--glass-border) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2) !important;
            border-radius: 16px !important;
        }

        /* Typography Improvements */
        h1, h2, h3, .text-xl, .text-2xl, .text-3xl {
            font-weight: 800 !important;
            letter-spacing: -0.5px !important;
            color: #fff !important;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        p, .text-gray-400, .text-zinc-400 {
            color: var(--text-muted) !important;
            line-height: 1.6 !important;
        }

        /* Buttons */
        button, .btn {
            font-family: 'Vazirmatn', sans-serif !important;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
        }
        
        button:active, .btn:active {
            transform: scale(0.96) !important;
        }

        /* Specific Primary Button Style Override */
        [class*="bg-primary"], .bg-blue-600, .bg-indigo-600 {
            background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
            box-shadow: 0 4px 15px var(--primary-glow) !important;
        }

        /* Inputs */
        input, select, textarea {
            transition: all 0.2s ease !important;
            font-family: 'Vazirmatn', sans-serif !important;
            background: rgba(0, 0, 0, 0.2) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: 12px !important;
            color: #fff !important;
        }
        input:focus, select:focus, textarea:focus {
            transform: translateY(-1px) !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 2px var(--primary-glow) !important;
            outline: none !important;
        }

        /* Bottom Navigation Bar Override */
        .fixed.bottom-0, nav[class*="fixed"], [class*="bottom-nav"] {
            background: rgba(15, 23, 42, 0.85) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border-top: 1px solid var(--glass-border) !important;
            padding-bottom: env(safe-area-inset-bottom) !important;
        }

        /* Progress Bar */
        [role="progressbar"] > div, [class*="bg-blue-"], [class*="bg-indigo-"] {
            background: linear-gradient(90deg, #22d3ee, #8b5cf6) !important;
            box-shadow: 0 0 10px rgba(34, 211, 238, 0.5) !important;
        }

        /* Icons */
        i, svg {
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        /* App Container */
        #root {
            flex: 1;
            z-index: 10;
            position: relative;
            width: 100%;
            /* Center on desktop but keep full width on mobile */
            max-width: 500px;
            margin: 0 auto;
            padding-bottom: 20px;
        }

        /* Modern Minimal Footer */
        .professional-footer {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 15px 0 25px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            border-top: 1px solid var(--glass-border);
            background: linear-gradient(to top, var(--bg-dark), transparent);
        }

        .brand-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            color: var(--text-muted);
            transition: all 0.3s ease;
            padding: 6px 12px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid transparent;
        }

        .brand-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--glass-border);
            transform: translateY(-1px);
        }

        .brand-name {
            font-weight: 600;
            background: linear-gradient(135deg, #e2e8f0, #a5b4fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Refined Scrollbar */
        ::-webkit-scrollbar { width: 3px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .professional-footer {
            animation: fadeIn 0.8s ease-out;
        }
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
