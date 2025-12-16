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

// --- توابع ساختگی برای نمونه‌سازی نمودارها ---
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

$salesData = get_sales_data();
$statusData = get_status_data();
$userData = get_users_data();

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
            background-color: #f3f4f6; /* رنگ پس‌زمینه روشن */
        }
        /* کلاس برای تغییر رنگ آیتم‌های Vue */
        .switch-toggle:checked + .bg-gray-200 {
            background-color: #10B981 !important; /* emerald-500 */
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class', // یا media
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

    <!-- Header & Sidebar (Minimal structure for a single-page dash) -->
    <header class="bg-white shadow-md sticky top-0 z-10 dark:bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-extrabold text-primary">پنل مدیریت</h1>
            <div class="flex items-center space-s-4">
                <a href="#" class="text-sm font-medium hover:text-primary transition">خروج</a>
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
        <section id="quick-menu" class="mb-8 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg border-t-4 border-primary">
            <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">منوی سریع و اقدامات کلیدی</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- دکمه ۱: ثبت سفارش جدید -->
                <button onclick="window.location.href='new_order.php'" class="flex flex-col items-center justify-center p-4 bg-primary text-white rounded-lg hover:bg-indigo-700 transition duration-300 transform hover:scale-105 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-sm font-medium">ثبت سفارش جدید</span>
                </button>
                
                <!-- دکمه ۲: افزودن کاربر -->
                <button onclick="window.location.href='add_user.php'" class="flex flex-col items-center justify-center p-4 bg-secondary text-white rounded-lg hover:bg-emerald-600 transition duration-300 transform hover:scale-105 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-10 2H7M5 8h2m-2 4h2" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5H5a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v3m-3 6h6" />
                    </svg>
                    <span class="text-sm font-medium">افزودن کاربر</span>
                </button>

                <!-- دکمه ۳: گزارشات کامل -->
                <button onclick="window.location.href='reports.php'" class="flex flex-col items-center justify-center p-4 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition duration-300 transform hover:scale-105 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0h6m-6 0a2 2 0 002 2h2a2 2 0 002-2" />
                    </svg>
                    <span class="text-sm font-medium">گزارشات کامل</span>
                </button>

                <!-- دکمه ۴: تنظیمات سیستم -->
                <button onclick="window.location.href='settings.php'" class="flex flex-col items-center justify-center p-4 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-300 transform hover:scale-105 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-sm font-medium">تنظیمات سیستم</span>
                </button>
            </div>
        </section>

        <!-- 2. آمار خلاصه (Summary Metrics) -->
        <section id="summary-metrics" class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <!-- کارت آمار ۱: کل کاربران -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border-l-4 border-indigo-500 flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">کل کاربران</p>
                    <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1"><?php echo number_format($userData['total']); ?></p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-indigo-400 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H9a1 1 0 01-1-1v-1a2 2 0 012-2h4a2 2 0 012 2v1a1 1 0 01-1 1zm0 0h-6" />
                </svg>
            </div>

            <!-- کارت آمار ۲: کاربران جدید امروز -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border-l-4 border-emerald-500 flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">کاربران جدید امروز</p>
                    <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1"><?php echo number_format($userData['new']); ?></p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-emerald-400 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8m0 0l-4-4m4 4l-6 6" />
                </svg>
            </div>

            <!-- کارت آمار ۳: کاربران فعال -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border-l-4 border-yellow-500 flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">کاربران فعال</p>
                    <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1"><?php echo number_format($userData['active']); ?></p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-400 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.24a2 2 0 010 2.828l-8 8a2 2 0 01-2.828 0l-4-4a2 2 0 010-2.828 2 2 0 012.828 0l4 4 7.172-7.172a2 2 0 012.828 0z" />
                </svg>
            </div>
        </section>


        <!-- 3. نمودارها و ترجیحات نمایش -->
        <section id="dashPrefs" class="mb-8 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg border-t-4 border-gray-300 dark:border-gray-700">
            <h3 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">تنظیمات نمایش داشبورد</h3>
            
            <div v-if="!isVueLoaded" class="text-center py-4 text-gray-500">
                در حال بارگذاری تنظیمات...
            </div>

            <div v-cloak v-else class="flex flex-wrap gap-4 items-center">
                <!-- توگلر نمودار فروش -->
                <div class="flex items-center space-s-2 cursor-pointer" @click="show.sales = !show.sales">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="show.sales" class="sr-only peer switch-toggle">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">نمایش نمودار فروش</span>
                </div>

                <!-- توگلر نمودار وضعیت -->
                <div class="flex items-center space-s-2 cursor-pointer" @click="show.status = !show.status">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="show.status" class="sr-only peer switch-toggle">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">نمایش نمودار وضعیت‌ها</span>
                </div>

                <!-- توگلر نمودار کاربران -->
                <div class="flex items-center space-s-2 cursor-pointer" @click="show.users = !show.users">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="show.users" class="sr-only peer switch-toggle">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">نمایش آمار کاربران</span>
                </div>
            </div>
        </section>

        <!-- 4. کانتینر نمودارها -->
        <div id="chartContainers" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- نمودار فروش -->
            <div id="salesChartContainer" class="col-span-1 lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg transition duration-300" style="display: none;">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2 text-gray-900 dark:text-gray-100">نمودار فروش ماهانه</h3>
                <canvas id="salesChart" style="max-height: 400px;"></canvas>
            </div>

            <!-- نمودار وضعیت‌ها -->
            <div id="statusChartContainer" class="col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg transition duration-300" style="display: none;">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2 text-gray-900 dark:text-gray-100">درصد وضعیت‌ها</h3>
                <canvas id="statusChart" style="max-height: 400px;"></canvas>
            </div>
            
            <!-- نمودار آمار کاربران (ستون ۱ از ۳ در حالت دسکتاپ) -->
            <div id="usersChartContainer" class="col-span-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg transition duration-300" style="display: none;">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2 text-gray-900 dark:text-gray-100">آمار کاربران (نمونه)</h3>
                <div class="flex flex-col space-y-4">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/50 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-300">کل کاربران</p>
                        <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300 mt-1"><?php echo number_format($userData['total']); ?></p>
                    </div>
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/50 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-300">کاربران جدید امروز</p>
                        <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1"><?php echo number_format($userData['new']); ?></p>
                    </div>
                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/50 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-300">تعداد کاربران فعال</p>
                        <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300 mt-1"><?php echo number_format($userData['active']); ?></p>
                    </div>
                </div>
            </div>

        </div>

    </main>

<!-- JavaScript Section -->
<script>
const salesData = <?php echo json_encode($salesData); ?>;
const statusData = <?php echo json_encode($statusData); ?>;

// متغیرهای global برای نگهداری نمونه‌های نمودار
let salesChartInstance = null;
let statusChartInstance = null;

const chartRenderers = {
    'sales': function() {
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;
        if (salesChartInstance) salesChartInstance.destroy(); // حذف نمونه قبلی
        salesChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'مبلغ فروش (هزار)',
                    data: salesData.data,
                    backgroundColor: 'rgba(79, 70, 229, 0.7)', // Primary color with alpha
                    borderColor: 'rgb(79, 70, 229)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return Intl.NumberFormat('fa-IR').format(value); }
                        }
                    },
                    x: {
                        // تنظیمات RTL برای نمودار
                        reverse: true, // برای نمودارهای افقی
                        ticks: { mirror: false }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { family: 'Vazirmatn' }
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
    'status': function() {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;
        if (statusChartInstance) statusChartInstance.destroy(); // حذف نمونه قبلی
        statusChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.data,
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.8)', // Yellow (در حال بررسی)
                        'rgba(16, 185, 129, 0.8)', // Emerald (تأیید شده)
                        'rgba(239, 68, 68, 0.8)'  // Red (لغو شده)
                    ],
                    hoverOffset: 4
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
                            padding: 20
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
        // اما برای سازگاری با تابع toggleCharts باقی می‌ماند.
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
                // رندر نمودار فقط زمانی که قابل مشاهده است
                if (id !== 'users') { // کاربران نمودار Chart.js ندارند
                    chartRenderers[id]();
                }
            } else {
                el.style.display = 'none';
            }
        }
    });
}

(async function() {
    // Vue.js Application
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
        chartRenderers['users']();

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