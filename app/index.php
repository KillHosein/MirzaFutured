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
<body class="antialiased min-h-screen">

    <!-- Preloader -->
    <div id="app-preloader">
        <div class="spinner"></div>
        <div class="loading-text">MIRZA PRO</div>
    </div>

    <!-- Main Content -->
    <main id="main-app" class="opacity-0 transition-opacity duration-500 p-4 pb-24">
        
        <!-- Header -->
        <header class="flex items-center justify-between mb-8 pt-2 sticky top-0 z-40 bg-[#0B0E14]/80 backdrop-blur-md py-2 -mx-4 px-4 border-b border-white/5">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <img id="user-avatar" src="assets/avatar-placeholder.png" onerror="this.src='https://via.placeholder.com/150'" class="w-10 h-10 rounded-full border-2 border-primary/50 shadow-lg object-cover" alt="Profile">
                    <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-[#0B0E14] rounded-full"></div>
                </div>
                <div>
                    <h1 id="user-name" class="text-sm font-bold leading-tight">بارگذاری...</h1>
                    <p id="user-id" class="text-[10px] text-gray-400 font-mono">ID: ---</p>
                </div>
            </div>
            <button onclick="WebApp.loadDashboard()" class="p-2 rounded-full glass-panel text-gray-300 hover:text-white transition-colors ripple-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </header>

        <div id="views-container">
            <!-- View: Dashboard -->
            <div id="view-dashboard" class="view-section active">
                <!-- Balance Card -->
                <section class="mb-8">
                    <div class="glass-panel p-6 relative overflow-hidden animate-float">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-primary/20 blur-3xl rounded-full pointer-events-none"></div>
                        <div class="relative z-10 text-center">
                            <p class="text-xs text-gray-400 mb-1">موجودی کیف پول</p>
                            <h2 class="text-3xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">
                                <span id="user-balance">0</span> <span class="text-base font-normal text-gray-500">تومان</span>
                            </h2>
                            <div class="mt-4 flex justify-center gap-3">
                                <button onclick="WebApp.openDepositModal()" class="px-6 py-2 rounded-xl bg-primary/20 border border-primary/50 text-primary text-sm font-bold hover:bg-primary/30 transition-all ripple-btn shadow-[0_0_15px_rgba(59,130,246,0.3)]">
                                    افزایش موجودی
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Stats Grid -->
                <section class="grid grid-cols-2 gap-4 mb-8">
                    <div class="stat-card glass-panel p-4 rounded-2xl flex flex-col items-center justify-center text-center hover:bg-white/5 transition-colors">
                        <div class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-white" id="active-services">0</span>
                        <span class="text-[10px] text-gray-400">سرویس فعال</span>
                    </div>
                    <div class="stat-card glass-panel p-4 rounded-2xl flex flex-col items-center justify-center text-center hover:bg-white/5 transition-colors">
                        <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-white" id="total-spent">0</span>
                        <span class="text-[10px] text-gray-400">مجموع خرید</span>
                    </div>
                </section>

                <!-- Products Section -->
                <section class="mb-20">
                    <div class="flex items-center justify-between mb-4 px-1">
                        <h3 class="text-sm font-bold text-gray-300">سرویس‌های ویژه</h3>
                        <span class="text-xs text-primary cursor-pointer" onclick="WebApp.switchView('orders')">مشاهده همه</span>
                    </div>
                    <div id="products-list" class="space-y-3">
                        <!-- Skeleton Loader -->
                        <div class="animate-pulse glass-panel h-20 w-full rounded-xl"></div>
                        <div class="animate-pulse glass-panel h-20 w-full rounded-xl"></div>
                    </div>
                </section>
            </div>

            <!-- View: Orders -->
            <div id="view-orders" class="view-section">
                <h2 class="text-xl font-bold mb-4 px-1">سفارشات من</h2>
                <div class="flex gap-2 mb-4 overflow-x-auto pb-2 no-scrollbar">
                    <button class="px-4 py-1.5 rounded-full bg-primary text-white text-xs font-bold whitespace-nowrap">همه</button>
                    <button class="px-4 py-1.5 rounded-full glass-panel text-gray-400 text-xs font-bold whitespace-nowrap">فعال</button>
                    <button class="px-4 py-1.5 rounded-full glass-panel text-gray-400 text-xs font-bold whitespace-nowrap">پایان یافته</button>
                </div>
                <div id="orders-list-full" class="space-y-3">
                    <div class="text-center text-gray-500 py-10">در حال بارگذاری...</div>
                </div>
            </div>

            <!-- View: Profile -->
            <div id="view-profile" class="view-section">
                <div class="glass-panel p-6 rounded-2xl mb-6 text-center relative overflow-hidden">
                    <div class="w-20 h-20 mx-auto rounded-full border-4 border-white/10 p-1 mb-3 relative">
                        <img id="profile-avatar-large" src="assets/avatar-placeholder.png" class="w-full h-full rounded-full object-cover">
                    </div>
                    <h2 id="profile-name" class="text-xl font-bold text-white mb-1">...</h2>
                    <p id="profile-username" class="text-sm text-gray-400 mb-4">@...</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white/5 rounded-xl p-2">
                            <div class="text-xs text-gray-400">شناسه عددی</div>
                            <div id="profile-id" class="text-sm font-mono text-white">---</div>
                        </div>
                        <div class="bg-white/5 rounded-xl p-2">
                            <div class="text-xs text-gray-400">تاریخ عضویت</div>
                            <div id="profile-joined" class="text-sm font-mono text-white">---</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <button class="w-full glass-panel p-4 rounded-xl flex items-center justify-between ripple-btn group" onclick="WebApp.openSupportModal()">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-pink-500/20 flex items-center justify-center text-pink-400 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium">پشتیبانی آنلاین</span>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    
                    <button class="w-full glass-panel p-4 rounded-xl flex items-center justify-between ripple-btn group">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center text-blue-400 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium">قوانین و مقررات</span>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="nav-indicator"></div>
        <div class="nav-item active ripple-btn" data-target="dashboard" onclick="WebApp.switchView('dashboard')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>خانه</span>
        </div>
        <div class="nav-item ripple-btn" data-target="orders" onclick="WebApp.switchView('orders')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span>سفارشات</span>
        </div>
        <div class="nav-item ripple-btn" data-target="profile" onclick="WebApp.switchView('profile')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>پروفایل</span>
        </div>
    </nav>

    <!-- Modals -->
    <div id="modal-overlay" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300" onclick="WebApp.closeModal()"></div>
    
    <div id="modal-container" class="fixed bottom-0 left-0 w-full bg-[#1e293b] rounded-t-2xl z-50 transform translate-y-full transition-transform duration-300 max-h-[90vh] overflow-y-auto border-t border-white/10 pb-8">
        <div class="p-4">
            <div class="w-12 h-1 bg-gray-600 rounded-full mx-auto mb-6"></div>
            <div id="modal-content"></div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg z-[100] transition-all duration-300 opacity-0 translate-y-[-20px] pointer-events-none flex items-center gap-2 border border-white/10">
        <span id="toast-icon"></span>
        <span id="toast-message" class="text-sm font-medium"></span>
    </div>

    <!-- Scripts -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>