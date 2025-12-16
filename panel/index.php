<?php
// --- خطایابی و گزارش‌دهی PHP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// فرض می‌کنیم این فایل‌ها در دسترس هستند و حاوی توابع مورد نیازند
require_once '../config.php';
require_once '../jdf.php';

// --- بررسی حیاتی: اطمینان از تعریف متغیر اتصال به دیتابیس ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Fatal Error: Database connection variable (\\$pdo) is not defined or is not a PDO object. Please check 'config.php'.");
}

// --- Logic Section ---
// توابع و متغیرهای مورد نیاز برای نمایش آمار (کد کامل در اینجا نیست، اما فرض می‌کنیم اجرا می‌شوند)
$datefirstday = time() - 86400; // Time yesterday (برای محاسبه کاربران جدید)
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];

if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];

// 1. Authentication Check
try {
    if( !isset($_SESSION["user"]) ){
        header('Location: login.php');
        exit;
    }
    
    // فرض می‌کنیم این کوئری برای اعتبارسنجی کاربر است
    $query = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
    // ... ادامه اعتبارسنجی و فیلتر کردن اطلاعات
    
} catch (\PDOException $e) {
    // خطای دیتابیس
    echo "Database Error: " . $e->getMessage();
    exit;
}

// --- توابع ساختگی برای نمونه‌سازی نمودارها و داده‌های جدید ---
function get_sales_data() {
    // داده‌های نمونه برای نمودار فروش
    return [
        'labels' => ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور'],
        'data' => [1200, 1900, 3000, 5000, 2500, 3700]
    ];
}

function get_status_data() {
    // داده‌های نمونه برای وضعیت‌ها
    return [
        'labels' => ['در حال بررسی', 'تأیید شده', 'لغو شده'],
        'data' => [5, 15, 3]
    ];
}

function get_users_data() {
    // داده‌های نمونه برای کاربران
    return [
        'total' => 452,
        'new' => 12,
        'active' => 310
    ];
}

function get_recent_activities() {
    // داده‌های نمونه برای جدول فعالیت‌های اخیر
    return [
        ['id' => 101, 'type' => 'سفارش جدید', 'desc' => 'ثبت سفارش توسط کاربر جدید A.', 'time' => '15:30', 'date' => 'امروز', 'status_color' => 'bg-indigo-100 text-indigo-800'],
        ['id' => 205, 'type' => 'تغییر وضعیت', 'desc' => 'سفارش #201 به وضعیت "تأیید شده" تغییر یافت.', 'time' => '12:00', 'date' => 'امروز', 'status_color' => 'bg-emerald-100 text-emerald-800'],
        ['id' => 312, 'type' => 'ورود مدیر', 'desc' => 'مدیر سیستم (John Doe) وارد پنل شد.', 'time' => '08:00', 'date' => 'دیروز', 'status_color' => 'bg-gray-100 text-gray-800'],
        ['id' => 100, 'type' => 'لغو سفارش', 'desc' => 'سفارش #100 توسط مشتری لغو شد.', 'time' => '17:45', 'date' => 'دیروز', 'status_color' => 'bg-red-100 text-red-800'],
        ['id' => 450, 'type' => 'به‌روزرسانی محصول', 'desc' => 'قیمت محصول X به روز شد.', 'time' => '09:00', 'date' => 'دیروز', 'status_color' => 'bg-blue-100 text-blue-800'],
    ];
}

$salesData = get_sales_data();
$statusData = get_status_data();
$userData = get_users_data();
$recentActivities = get_recent_activities();

// --- پایان Logic Section ---
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت - سیستم</title>
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <!-- Vue.js (برای مدیریت ترجیحات) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        /* تنظیم فونت Inter (مناسب برای اعداد و حروف لاتین) و فونت‌های فارسی */
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap');
        body {
            font-family: 'Vazirmatn', sans-serif;
            transition: background-color 0.3s, color 0.3s; /* انتقال نرم برای حالت شب */
        }
        /* کلاس برای تغییر رنگ آیتم‌های Vue و Dark Mode */
        .switch-toggle:checked + .bg-gray-200, .dark-mode-switch:checked + .bg-gray-700 {
            background-color: #4F46E5 !important; /* primary color */
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class', // فعال‌سازی حالت شب با کلاس
            theme: {
                extend: {
                    colors: {
                        'primary': '#4F46E5', // indigo-600
                        'secondary': '#10B981', // emerald-500
                    },
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 antialiased">

    <!-- Header & Dark Mode Toggle -->
    <header class="bg-white shadow-lg sticky top-0 z-10 dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            <h1 class="text-2xl font-extrabold text-primary">پنل مدیریت</h1>
            <div class="flex items-center space-s-4">
                
                <!-- Dark Mode Toggle -->
                <div class="flex items-center space-s-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <label class="relative inline-flex items-center cursor-pointer" onclick="toggleDarkMode()">
                        <input type="checkbox" id="darkModeToggle" class="sr-only peer dark-mode-switch">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </div>

                <a href="#" class="text-sm font-medium text-gray-500 hover:text-primary transition dark:text-gray-300 dark:hover:text-primary">خروج</a>
                <span class="text-sm text-gray-500 dark:text-gray-400 hidden sm:block">خوش آمدید، مدیر!</span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- عنوان اصلی و تاریخ -->
        <div class="px-4 py-2 sm:px-0 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">داشبورد خلاصه</h2>
                <span class="text-gray-500 text-sm mt-2 sm:mt-0">امروز: <?php echo jdate('l, d F Y'); ?></span>
            </div>
        </div>

        <!-- 1. منوی سریع (Quick Menu) - منتقل شده به بالای محتوا -->
        <section id="quick-menu" class="mb-8 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-t-8 border-primary transition duration-300">
            <h3 class="text-xl font-extrabold mb-5 text-gray-900 dark:text-gray-100">منوی سریع و اقدامات کلیدی</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                
                <?php
                $quickMenu = [
                    ['url' => 'new_order.php', 'title' => 'ثبت سفارش جدید', 'color' => 'primary', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />'],
                    ['url' => 'add_user.php', 'title' => 'افزودن کاربر', 'color' => 'secondary', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-10 2H7M5 8h2m-2 4h2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5H5a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v3m-3 6h6" />'],
                    ['url' => 'reports.php', 'title' => 'گزارشات کامل', 'color' => 'yellow-500', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0h6m-6 0a2 2 0 002 2h2a2 2 0 002-2" />'],
                    ['url' => 'settings.php', 'title' => 'تنظیمات سیستم', 'color' => 'gray-500', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />'],
                ];
                
                foreach ($quickMenu as $item) {
                    $colorClass = $item['color'] === 'primary' ? 'bg-primary hover:bg-indigo-700' : ($item['color'] === 'secondary' ? 'bg-secondary hover:bg-emerald-600' : 'bg-' . $item['color'] . ' hover:bg-' . $item['color'] . '/80');
                    $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">' . $item['icon'] . '</svg>';
                    echo '
                    <button onclick="window.location.href=\'' . $item['url'] . '\'" class="flex flex-col items-center justify-center p-4 ' . $colorClass . ' text-white rounded-xl transition duration-300 transform hover:scale-[1.02] shadow-xl">
                        ' . $iconSvg . '
                        <span class="text-sm font-bold">' . $item['title'] . '</span>
                    </button>';
                }
                ?>
            </div>
        </section>

        <!-- 2. آمار خلاصه (Summary Metrics) -->
        <section id="summary-metrics" class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <?php
            $metricCards = [
                ['title' => 'کل کاربران', 'value' => $userData['total'], 'color' => 'indigo', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H9a1 1 0 01-1-1v-1a2 2 0 012-2h4a2 2 0 012 2v1a1 1 0 01-1 1zm0 0h-6" />'],
                ['title' => 'کاربران جدید امروز', 'value' => $userData['new'], 'color' => 'emerald', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8m0 0l-4-4m4 4l-6 6" />'],
                ['title' => 'کاربران فعال', 'value' => $userData['active'], 'color' => 'yellow', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.24a2 2 0 010 2.828l-8 8a2 2 0 01-2.828 0l-4-4a2 2 0 010-2.828 2 2 0 012.828 0l4 4 7.172-7.172a2 2 0 012.828 0z" />'],
            ];

            foreach ($metricCards as $card) {
                $formattedValue = number_format($card['value']);
                echo '
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-xl border-t-4 border-' . $card['color'] . '-500 flex justify-between items-center transition duration-300 hover:shadow-2xl">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">' . $card['title'] . '</p>
                        <p class="text-4xl font-extrabold text-gray-900 dark:text-white mt-1">' . $formattedValue . '</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-' . $card['color'] . '-400 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        ' . $card['icon'] . '
                    </svg>
                </div>';
            }
            ?>
        </section>


        <!-- 3. نمودارها و ترجیحات نمایش -->
        <section id="dashPrefs" class="mb-8 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg border-t-4 border-gray-300 dark:border-gray-700">
            <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">تنظیمات نمایش داشبورد</h3>
            
            <div v-if="!isVueLoaded" class="text-center py-4 text-gray-500 dark:text-gray-400">
                در حال بارگذاری تنظیمات...
            </div>

            <div v-cloak v-else class="flex flex-wrap gap-6 items-center">
                <!-- توگلر نمودار فروش -->
                <div class="flex items-center space-s-2 cursor-pointer transition duration-150 hover:opacity-80" @click="show.sales = !show.sales">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="show.sales" class="sr-only peer switch-toggle">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">نمایش نمودار فروش</span>
                </div>

                <!-- توگلر نمودار وضعیت -->
                <div class="flex items-center space-s-2 cursor-pointer transition duration-150 hover:opacity-80" @click="show.status = !show.status">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="show.status" class="sr-only peer switch-toggle">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">نمایش نمودار وضعیت‌ها</span>
                </div>

                <!-- توگلر نمودار کاربران -->
                <div class="flex items-center space-s-2 cursor-pointer transition duration-150 hover:opacity-80" @click="show.users = !show.users">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="show.users" class="sr-only peer switch-toggle">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">نمایش آمار کاربران</span>
                </div>
            </div>
        </section>

        <!-- 4. کانتینر نمودارها -->
        <div id="chartContainers" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- نمودار فروش -->
            <div id="salesChartContainer" class="col-span-1 lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-xl transition duration-300" style="display: none;">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2 border-gray-100 dark:border-gray-700 text-gray-900 dark:text-gray-100">نمودار فروش ماهانه (هزار تومان)</h3>
                <canvas id="salesChart" style="max-height: 400px;"></canvas>
            </div>

            <!-- نمودار وضعیت‌ها -->
            <div id="statusChartContainer" class="col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-xl transition duration-300" style="display: none;">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2 border-gray-100 dark:border-gray-700 text-gray-900 dark:text-gray-100">درصد وضعیت سفارشات</h3>
                <canvas id="statusChart" style="max-height: 400px;"></canvas>
            </div>
            
            <!-- آمار کاربران (این بخش کارت‌های آماری است) -->
            <div id="usersChartContainer" class="col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-xl transition duration-300" style="display: none;">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2 border-gray-100 dark:border-gray-700 text-gray-900 dark:text-gray-100">آمار کاربران (خلاصه)</h3>
                <div class="flex flex-col space-y-4">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/50 rounded-lg border-s-4 border-indigo-400">
                        <p class="text-sm text-gray-600 dark:text-gray-300">کل کاربران ثبت‌نام شده</p>
                        <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300 mt-1"><?php echo number_format($userData['total']); ?></p>
                    </div>
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/50 rounded-lg border-s-4 border-emerald-400">
                        <p class="text-sm text-gray-600 dark:text-gray-300">کاربران جدید امروز</p>
                        <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1"><?php echo number_format($userData['new']); ?></p>
                    </div>
                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/50 rounded-lg border-s-4 border-yellow-400">
                        <p class="text-sm text-gray-600 dark:text-gray-300">تعداد کاربران فعال (آخرین ۳۰ روز)</p>
                        <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300 mt-1"><?php echo number_format($userData['active']); ?></p>
                    </div>
                </div>
            </div>

        </div>
        
        <!-- 5. فعالیت‌های اخیر (New Section) -->
        <section id="recent-activities">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-2xl transition duration-300">
                <h3 class="text-xl font-extrabold mb-5 border-b pb-3 border-gray-100 dark:border-gray-700 text-gray-900 dark:text-gray-100">فعالیت‌های اخیر سیستم</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">عملیات</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300 hidden sm:table-cell">شناسه</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">توضیحات</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">زمان</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            <?php foreach ($recentActivities as $activity): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo htmlspecialchars($activity['status_color']); ?>">
                                            <?php echo htmlspecialchars($activity['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 hidden sm:table-cell">
                                        #<?php echo htmlspecialchars($activity['id']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo htmlspecialchars($activity['desc']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <time datetime="<?php echo htmlspecialchars($activity['date'] . ' ' . $activity['time']); ?>">
                                            <?php echo htmlspecialchars($activity['time']); ?>
                                            <span class="text-xs font-light block"><?php echo htmlspecialchars($activity['date']); ?></span>
                                        </time>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-6">
                    <a href="log.php" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl shadow-sm text-white bg-primary hover:bg-indigo-700 transition duration-150 dark:ring-offset-gray-900 ring-4 ring-primary/50">
                        مشاهده همه فعالیت‌ها
                    </a>
                </div>

            </div>
        </section>

    </main>

<!-- JavaScript Section -->
<script>
const salesData = <?php echo json_encode($salesData); ?>;
const statusData = <?php echo json_encode($statusData); ?>;

// --- Dark Mode Logic ---

/**
 * اولیه سازی حالت شب بر اساس ترجیح کاربر یا ذخیره محلی
 */
function initDarkMode() {
    // بررسی localStorage یا ترجیح سیستم
    const isDark = localStorage.getItem('theme') === 'dark' || 
                   (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', isDark);
    
    // تنظیم وضعیت سوئیچ در بارگذاری اولیه
    const toggle = document.getElementById('darkModeToggle');
    if (toggle) {
        toggle.checked = isDark;
    }
    return isDark;
}

/**
 * جابه‌جایی بین حالت شب و روز
 */
function toggleDarkMode() {
    const isDark = document.documentElement.classList.contains('dark');
    const newMode = !isDark ? 'dark' : 'light';
    
    document.documentElement.classList.toggle('dark', !isDark);
    localStorage.setItem('theme', newMode);
    
    // نیاز به رندر مجدد نمودارها در حالت شب/روز برای به‌روزرسانی رنگ متن و بک‌گراند
    if (salesChartInstance) chartRenderers['sales'](newMode);
    if (statusChartInstance) chartRenderers['status'](newMode);
}

// --- Chart.js Logic ---

// متغیرهای global برای نگهداری نمونه‌های نمودار
let salesChartInstance = null;
let statusChartInstance = null;

const chartRenderers = {
    'sales': function(mode = null) {
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;
        
        // تشخیص رنگ متن بر اساس حالت روز/شب
        const isDark = mode === 'dark' || (mode === null && document.documentElement.classList.contains('dark'));
        const textColor = isDark ? '#f3f4f6' : '#1f2937'; // gray-100 / gray-800
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        
        if (salesChartInstance) salesChartInstance.destroy(); // حذف نمونه قبلی
        salesChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'مبلغ فروش (هزار)',
                    data: salesData.data,
                    backgroundColor: 'rgba(79, 70, 229, 0.9)', // Primary color with alpha
                    borderColor: 'rgb(79, 70, 229)',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: 'rgba(79, 70, 229, 1)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return Intl.NumberFormat('fa-IR').format(value); },
                            color: textColor,
                            font: { family: 'Vazirmatn' }
                        },
                        grid: { color: gridColor }
                    },
                    x: {
                        reverse: true, // برای نمودارهای راست‌چین
                        ticks: {
                            color: textColor,
                            font: { family: 'Vazirmatn' }
                        },
                        grid: { color: gridColor }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { family: 'Vazirmatn' },
                            color: textColor
                        }
                    },
                    tooltip: {
                        rtl: true,
                        titleFont: { family: 'Vazirmatn' },
                        bodyFont: { family: 'Vazirmatn' },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                label += Intl.NumberFormat('fa-IR').format(context.raw) + ' هزار تومان';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    },
    'status': function(mode = null) {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;
        
        // تشخیص رنگ متن بر اساس حالت روز/شب
        const isDark = mode === 'dark' || (mode === null && document.documentElement.classList.contains('dark'));
        const textColor = isDark ? '#f3f4f6' : '#1f2937';
        
        if (statusChartInstance) statusChartInstance.destroy(); // حذف نمونه قبلی
        statusChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.data,
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.9)', // Yellow (در حال بررسی)
                        'rgba(16, 185, 129, 0.9)', // Emerald (تأیید شده)
                        'rgba(239, 68, 68, 0.9)'  // Red (لغو شده)
                    ],
                    hoverOffset: 8,
                    borderColor: isDark ? '#1f2937' : '#ffffff', // مرز سفید در روز، تیره در شب
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { family: 'Vazirmatn' },
                            usePointStyle: true,
                            padding: 20,
                            color: textColor
                        }
                    },
                    tooltip: {
                        rtl: true,
                        titleFont: { family: 'Vazirmatn' },
                        bodyFont: { family: 'Vazirmatn' },
                    }
                }
            }
        });
    },
    'users': function() {
        // این بخش فقط نمایش کارت آمار است و نمودار Chart.js ندارد
    }
};

/**
 * مدیریت نمایش/عدم نمایش کانتینر نمودارها و رندر نمودار در صورت نیاز.
 * @param {object} show - آبجکت Vue.js شامل وضعیت نمایش نمودارها
 */
function toggleCharts(show) {
    const chartIds = ['sales', 'status', 'users'];
    chartIds.forEach(id => {
        const el = document.getElementById(`${id}ChartContainer`);
        if (el) {
            if (show[id]) {
                el.style.display = 'block';
                // رندر نمودار فقط زمانی که قابل مشاهده است و در DOM قرار دارد
                if (id !== 'users' && el.style.display !== 'none') { 
                    chartRenderers[id]();
                }
            } else {
                el.style.display = 'none';
            }
        }
    });
}

(async function() {
    // 1. Initial Dark Mode setup
    initDarkMode();

    // 2. Vue.js Application for preferences
    if (typeof Vue !== 'undefined') {
        const storedPrefs = localStorage.getItem('dash_prefs');
        let initialShow = { sales: true, status: true, users: true };
        if (storedPrefs) {
            try {
                initialShow = JSON.parse(storedPrefs);
            } catch (e) {
                console.error("Error parsing stored dashboard preferences:", e);
            }
        }

        const app = Vue.createApp({
            data() {
                return {
                    show: initialShow,
                    isVueLoaded: true // برای مخفی کردن پیام loading
                };
            },
            watch:{ 
                show:{ 
                    deep:true, 
                    handler:function(v){ 
                        localStorage.setItem('dash_prefs', JSON.stringify(v)); 
                        toggleCharts(v); 
                    } 
                } 
            },
            mounted(){ 
                // اعمال اولیه دیداری و رندر نمودارها
                toggleCharts(this.show); 
                window.addEventListener('resize', () => toggleCharts(this.show));
            }
        });
        app.mount('#dashPrefs');
    } else {
        // Fallback: اگر Vue.js بارگذاری نشده است، همه نمودارها را رندر و نمایش بده
        console.warn("Vue.js not loaded. Falling back to default chart rendering.");
        chartRenderers['sales']();
        chartRenderers['status']();
        
        const salesEl = document.getElementById('salesChartContainer');
        const statusEl = document.getElementById('statusChartContainer');
        const usersEl = document.getElementById('usersChartContainer');
        if (salesEl) salesEl.style.display = 'block';
        if (statusEl) statusEl.style.display = 'block';
        if (usersEl) usersEl.style.display = 'block';
    }
})();
</script>

</body>
</html>