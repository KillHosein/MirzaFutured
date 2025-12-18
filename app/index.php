<?php
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mirza Pro Panel</title>
    <base href="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>" />
    
    <!-- Telegram Web App -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    <!-- Config -->
    <script>
        window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        vazir: ['Vazir', 'sans-serif'],
                    },
                    colors: {
                        gray: {
                            800: '#1f2937',
                            900: '#111827',
                        }
                    },
                    padding: {
                        'safe': 'env(safe-area-inset-bottom)',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetPrefix . 'assets/css/modern.css', ENT_QUOTES); ?>">
    <!-- Fonts (Vazir) - Reusing existing fonts if possible or fallback -->
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('<?php echo htmlspecialchars($assetPrefix . 'fonts/Vazir.woff2', ENT_QUOTES); ?>') format('woff2');
            font-weight: normal;
        }
        @font-face {
            font-family: 'Vazir';
            src: url('<?php echo htmlspecialchars($assetPrefix . 'fonts/Vazir-Bold.woff2', ENT_QUOTES); ?>') format('woff2');
            font-weight: bold;
        }
    </style>
    
    <!-- Icons (Phosphor Icons or Feather via CDN for now) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-900 text-white font-vazir antialiased select-none">
    
    <!-- Loading Screen -->
    <div id="app-loading" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 transition-opacity duration-300">
        <div class="text-center">
            <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-gray-400 text-sm animate-pulse">در حال بارگذاری...</p>
        </div>
    </div>

    <!-- App Root -->
    <div id="app" class="hidden min-h-screen flex flex-col pb-20">
        <!-- Header -->
        <header class="sticky top-0 z-40 bg-gray-800/80 backdrop-blur-md border-b border-gray-700 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-lg">
                    M
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white leading-tight">Mirza Pro</h1>
                    <p class="text-xs text-green-400 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        آنلاین
                    </p>
                </div>
            </div>
            <button id="theme-toggle" class="p-2 rounded-lg bg-gray-700 text-gray-300 hover:text-white transition-colors">
                <i class="ph ph-moon text-xl"></i>
            </button>
        </header>

        <!-- Main Content -->
        <main id="main-content" class="flex-1 p-4 space-y-6">
            <!-- Dynamic Content will be injected here -->
        </main>

        <!-- Bottom Navigation -->
        <nav class="fixed bottom-0 left-0 right-0 bg-gray-800/90 backdrop-blur-lg border-t border-gray-700 pb-safe pt-2 px-6 z-40">
            <ul class="flex justify-between items-center">
                <li>
                    <a href="#home" class="nav-item active flex flex-col items-center gap-1 text-gray-400 hover:text-blue-400 transition-colors p-2">
                        <i class="ph ph-house text-2xl mb-0.5"></i>
                        <span class="text-[10px] font-medium">خانه</span>
                    </a>
                </li>
                <li>
                    <a href="#services" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-blue-400 transition-colors p-2">
                        <i class="ph ph-lightning text-2xl mb-0.5"></i>
                        <span class="text-[10px] font-medium">سرویس‌ها</span>
                    </a>
                </li>
                <li>
                    <a href="#invoices" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-blue-400 transition-colors p-2">
                        <i class="ph ph-receipt text-2xl mb-0.5"></i>
                        <span class="text-[10px] font-medium">صورتحساب</span>
                    </a>
                </li>
                <li>
                    <a href="#profile" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-blue-400 transition-colors p-2">
                        <i class="ph ph-user text-2xl mb-0.5"></i>
                        <span class="text-[10px] font-medium">پروفایل</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Notification Container -->
    <div id="toast-container" class="fixed top-4 left-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- Scripts -->
    <script type="module" src="<?php echo htmlspecialchars($assetPrefix . 'assets/js/modern.js', ENT_QUOTES); ?>"></script>
</body>
</html>
