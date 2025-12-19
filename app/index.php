<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mirza Pro WebApp</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        navy: { 800: '#0f172a', 900: '#0B0E14' },
                        primary: '#3b82f6',
                        accent: '#06b6d4'
                    },
                    fontFamily: {
                        sans: ['Vazir', 'ui-sans-serif', 'system-ui']
                    }
                }
            }
        }
    </script>

    <!-- Custom Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css" rel="stylesheet" type="text/css" />

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/custom.css?v=<?php echo time(); ?>">
</head>
<body class="antialiased min-h-screen pb-20">

    <!-- Preloader -->
    <div id="app-preloader">
        <div class="spinner"></div>
        <div class="loading-text">MIRZA PRO</div>
    </div>

    <!-- Main Content -->
    <main id="main-app" class="opacity-0 transition-opacity duration-500 p-4">
        
        <!-- Header -->
        <header class="flex items-center justify-between mb-8 pt-2">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <img id="user-avatar" src="assets/avatar-placeholder.png" onerror="this.src='https://via.placeholder.com/150'" class="w-12 h-12 rounded-full border-2 border-primary/50 shadow-lg object-cover" alt="Profile">
                    <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-[#0B0E14] rounded-full"></div>
                </div>
                <div>
                    <h1 id="user-name" class="text-lg font-bold leading-tight">بارگذاری...</h1>
                    <p id="user-id" class="text-xs text-gray-400 font-mono">ID: ---</p>
                </div>
            </div>
            <button class="p-2 rounded-full glass-panel text-gray-300 hover:text-white transition-colors ripple-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </button>
        </header>

        <!-- Balance Card -->
        <section class="mb-8">
            <div class="glass-panel p-6 relative overflow-hidden animate-float">
                <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-primary/20 blur-3xl rounded-full pointer-events-none"></div>
                <div class="relative z-10 text-center">
                    <p class="text-sm text-gray-400 mb-1">موجودی کیف پول</p>
                    <h2 class="text-4xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">
                        <span id="user-balance">0</span> <span class="text-lg font-normal text-gray-500">تومان</span>
                    </h2>
                    <div class="mt-4 flex justify-center gap-3">
                        <button class="px-6 py-2 rounded-xl bg-primary/20 border border-primary/50 text-primary text-sm font-bold hover:bg-primary/30 transition-all ripple-btn">
                            افزایش موجودی
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Grid -->
        <section class="grid grid-cols-2 gap-4 mb-8">
            <div class="stat-card glass-panel p-4 rounded-2xl flex flex-col items-center justify-center text-center">
                <div class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400 mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-2xl font-bold text-white" id="active-services">0</span>
                <span class="text-xs text-gray-400">سرویس فعال</span>
            </div>
            <div class="stat-card glass-panel p-4 rounded-2xl flex flex-col items-center justify-center text-center">
                <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400 mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-2xl font-bold text-white" id="total-spent">0</span>
                <span class="text-xs text-gray-400">مجموع خرید</span>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="mb-6">
            <h3 class="text-sm font-bold text-gray-400 mb-4 px-1">دسترسی سریع</h3>
            <div class="grid grid-cols-3 gap-3">
                <a href="#" class="glass-panel p-3 rounded-xl flex flex-col items-center gap-2 hover:bg-white/5 transition-colors ripple-btn">
                    <div class="p-2 bg-blue-500/20 rounded-lg text-blue-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <span class="text-xs">خرید سرویس</span>
                </a>
                <a href="#" class="glass-panel p-3 rounded-xl flex flex-col items-center gap-2 hover:bg-white/5 transition-colors ripple-btn">
                    <div class="p-2 bg-purple-500/20 rounded-lg text-purple-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <span class="text-xs">سفارشات</span>
                </a>
                <a href="#" class="glass-panel p-3 rounded-xl flex flex-col items-center gap-2 hover:bg-white/5 transition-colors ripple-btn">
                    <div class="p-2 bg-pink-500/20 rounded-lg text-pink-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <span class="text-xs">پشتیبانی</span>
                </a>
            </div>
        </section>

        <!-- Products Section -->
        <section class="mb-20">
            <h3 class="text-sm font-bold text-gray-400 mb-4 px-1">سرویس‌های محبوب</h3>
            <div id="products-list" class="space-y-3">
                <!-- Skeleton Loader -->
                <div class="animate-pulse glass-panel h-20 w-full rounded-xl"></div>
                <div class="animate-pulse glass-panel h-20 w-full rounded-xl"></div>
            </div>
        </section>

    </main>

    <!-- Scripts -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
