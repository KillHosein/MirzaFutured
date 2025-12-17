<?php
// --- خطایابی و گزارش‌دهی PHP (برای پیدا کردن علت خطای 500) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// فرض می‌کنیم این فایل‌ها در دسترس هستند و حاوی توابع مورد نیازند
require_once '../config.php';
require_once '../jdf.php';

// --- بررسی حیاتی: اطمینان از تعریف متغیر اتصال به دیتابیس ---
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // این خطا در صورت عدم وجود config.php یا عدم تعریف $pdo در آن رخ می‌دهد
    die("Fatal Error: Database connection variable (\$pdo) is not defined or is not a PDO object. Please check 'config.php'.");
}

// -------------------------------------------------------------------------------------
// --- Logic Section - [شما باید تمام منطق PHP بیش از ۱۰۰۰ خطی خود را در این بخش قرار دهید] ---
// --- لطفا متغیرهای KPI و ChartData را با نتایج کوئری‌های واقعی دیتابیس خود جایگزین کنید ---
// -------------------------------------------------------------------------------------

$datefirstday = time() - 86400; // Time yesterday (for new users calculation)
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
    
    // --- START: Mock Data (برای اجرا و نمایش اولیه، باید با داده‌های واقعی جایگزین شود) ---
    
    // KPI Data (آمارهای اصلی)
    $totalUsers = 1520;
    $newUsersToday = 78;
    $totalSales = 42100000; // ریال یا تومان
    $totalOrders = 850;
    $newOrdersToday = 25;
    
    // Statuses data (برای نمایش جزئیات وضعیت‌ها در KPI و فیلترها)
    $statuses = [
        ['name' => 'در انتظار پرداخت', 'count' => 150, 'color' => 'bg-yellow-100 text-yellow-800'],
        ['name' => 'لغوشده', 'count' => 22, 'color' => 'bg-red-100 text-red-800'],
    ];

    // Chart Data Generation (داده‌هایی که به جاوا اسکریپت پاس داده می‌شوند)
    
    // Sales Data (for Line Chart) - مثال: فروش ماهانه (به میلیون)
    $salesData = [
        'labels' => ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور'],
        'data' => [150, 220, 310, 450, 380, 520]
    ];
    $chartDataSales = json_encode($salesData);

    // Status Data (for Doughnut Chart) - مثال: تعداد سفارشات بر اساس وضعیت
    $statusData = [
        'labels' => ['در انتظار پرداخت', 'آماده‌سازی', 'ارسال شده', 'لغوشده'],
        'data' => [150, 100, 580, 20]
    ];
    $chartDataStatus = json_encode($statusData);

    // Users Data (for Bar Chart) - مثال: کاربران جدید هفتگی
    $usersData = [
        'labels' => ['هفته ۱', 'هفته ۲', 'هفته ۳', 'هفته ۴'],
        'data' => [78, 95, 120, 110]
    ];
    $chartDataUsers = json_encode($usersData);
    
    // --- END: Mock Data ---

    // Find cancelled count for KPI 4 display
    $cancelledCountItem = array_filter($statuses, fn($s) => $s['name'] == 'لغوشده');
    $cancelled = reset($cancelledCountItem);

    // Query Example (authentication query)
    $query = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
    // ... execution logic here ...

} catch (PDOException $e) {
    // Handle database errors
    error_log("Database Error: " . $e->getMessage());
    // Reset data on error
    $totalUsers = $totalSales = $totalOrders = $newUsersToday = $newOrdersToday = 0;
    $cancelled = ['count' => 0];
    $chartDataSales = $chartDataStatus = $chartDataUsers = json_encode([]);
} catch (Exception $e) {
    // Handle other errors
    error_log("General Error: " . $e->getMessage());
    $totalUsers = $totalSales = $totalOrders = $newUsersToday = $newOrdersToday = 0;
    $cancelled = ['count' => 0];
    $chartDataSales = $chartDataStatus = $chartDataUsers = json_encode([]);
}

// -------------------------------------------------------------------------------------
// --- پایان بخش منطق PHP ---
// -------------------------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <!-- Vue.js CDN (Needed for the existing preference logic) -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31/dist/vue.global.prod.js"></script>
    <style>
        /* Custom styles for appearance */
        /* استفاده از فونت Vazirmatn برای خوانایی بهتر در فارسی */
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap');
        body {
            font-family: 'Vazirmatn', sans-serif; 
            background-color: #f8fafc; /* پس‌زمینه خاکستری روشن */
        }
        /* تعریف استایل کارت‌های داشبورد */
        .dashboard-card {
            /* bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition duration-300 border border-gray-100 */
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 0.75rem; /* rounded-xl */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-lg */
            transition: all 0.3s ease-in-out;
            border: 1px solid #f3f4f6;
        }
        .dashboard-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); /* hover:shadow-xl */
        }
        /* استایل دکمه‌های تنظیمات */
        .pref-btn {
            /* px-4 py-2 font-medium rounded-lg text-sm transition duration-150 ease-in-out */
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.15s ease-in-out;
        }
        .pref-btn.active {
            /* bg-blue-600 text-white shadow-md hover:bg-blue-700 */
            background-color: #2563eb;
            color: #fff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .pref-btn:not(.active) {
            /* bg-gray-200 text-gray-700 hover:bg-gray-300 */
            background-color: #e5e7eb;
            color: #374151;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6', // blue-500
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen">

    <!-- Main Header -->
    <header class="bg-white shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-3xl font-extrabold text-gray-900">
                🚀 داشبورد مدیریت
            </h1>
            <!-- Logout Button -->
            <a href="logout.php" class="text-base font-medium text-red-600 hover:text-red-800 transition duration-150">
                خروج از سیستم
            </a>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Preferences and Filters Section (This contains the buttons and is mounted by Vue) -->
        <div id="dashPrefs" class="dashboard-card mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">تنظیمات داشبورد و فیلترها</h2>

            <!-- Existing Preference Buttons (Functionality is handled by Vue.js) -->
            <div class="flex flex-wrap gap-4 items-center mb-6">
                <p class="text-gray-600 font-medium whitespace-nowrap">نمایش بخش‌ها:</p>
                <!-- دکمه‌های اصلی که عملکرد آنها نباید تغییر کند، فقط ظاهر آنها بهتر شده است -->
                <button @click="show.sales = !show.sales" :class="['pref-btn', {'active': show.sales}]" data-chart="sales">
                    گزارش فروش
                </button>
                <button @click="show.status = !show.status" :class="['pref-btn', {'active': show.status}]" data-chart="status">
                    وضعیت سفارشات
                </button>
                <button @click="show.users = !show.users" :class="['pref-btn', {'active': show.users}]" data-chart="users">
                    آمار کاربران
                </button>
            </div>

            <!-- Date/Status Filters -->
            <form action="" method="get" class="grid grid-cols-2 md:grid-cols-5 gap-4 items-end">
                <!-- فیلتر تاریخ از -->
                <div class="col-span-1">
                    <label for="from" class="block text-sm font-medium text-gray-700 mb-1">از تاریخ:</label>
                    <input type="text" name="from" id="from" value="<?php echo htmlspecialchars($fromDate ?? ''); ?>"
                           class="w-full p-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary transition duration-150"
                           placeholder="مثال: 1402/01/01">
                </div>
                <!-- فیلتر تاریخ تا -->
                <div class="col-span-1">
                    <label for="to" class="block text-sm font-medium text-gray-700 mb-1">تا تاریخ:</label>
                    <input type="text" name="to" id="to" value="<?php echo htmlspecialchars($toDate ?? ''); ?>"
                           class="w-full p-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary transition duration-150"
                           placeholder="مثال: 1402/12/29">
                </div>
                <!-- فیلتر وضعیت -->
                <div class="col-span-2 md:col-span-1">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">وضعیت سفارش:</label>
                    <select name="status[]" id="status" multiple
                            class="w-full p-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary transition duration-150 h-24">
                        <option value="pending" <?php if(in_array('pending', $selectedStatuses)) echo 'selected'; ?>>در انتظار پرداخت</option>
                        <option value="processing" <?php if(in_array('processing', $selectedStatuses)) echo 'selected'; ?>>در حال پردازش</option>
                        <option value="shipped" <?php if(in_array('shipped', $selectedStatuses)) echo 'selected'; ?>>ارسال شده</option>
                        <option value="cancelled" <?php if(in_array('cancelled', $selectedStatuses)) echo 'selected'; ?>>لغوشده</option>
                    </select>
                </div>
                <!-- دکمه اعمال فیلتر -->
                <div class="col-span-2 md:col-span-1 flex justify-end md:justify-start">
                    <button type="submit" class="bg-primary text-white font-semibold py-2 px-6 rounded-lg shadow-md hover:bg-blue-600 transition duration-150 w-full md:w-auto">
                        اعمال فیلتر
                    </button>
                </div>
            </form>
        </div>

        <!-- 1. Key Performance Indicators (KPIs) - Responsive Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Card 1: Total Users -->
            <div class="dashboard-card flex flex-col justify-between" style="border-right: 4px solid #3b82f6;">
                <p class="text-sm font-medium text-gray-500">کل کاربران</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo number_format($totalUsers ?? 0); ?></p>
                <p class="text-sm mt-2 text-green-600 font-medium">
                    +<?php echo number_format($newUsersToday ?? 0); ?> امروز
                </p>
            </div>

            <!-- Card 2: Total Sales (Assumed Toman/Rial) -->
            <div class="dashboard-card flex flex-col justify-between" style="border-right: 4px solid #10b981;">
                <p class="text-sm font-medium text-gray-500">مجموع فروش</p>
                <!-- فرض بر این است که $totalSales به ریال/تومان است -->
                <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo number_format($totalSales ?? 0); ?></p>
                <p class="text-sm mt-2 text-gray-500 font-medium">
                    ریال / تومان
                </p>
            </div>

            <!-- Card 3: Total Orders -->
            <div class="dashboard-card flex flex-col justify-between" style="border-right: 4px solid #f59e0b;">
                <p class="text-sm font-medium text-gray-500">کل سفارشات</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo number_format($totalOrders ?? 0); ?></p>
                <p class="text-sm mt-2 text-green-600 font-medium">
                    +<?php echo number_format($newOrdersToday ?? 0); ?> امروز
                </p>
            </div>

            <!-- Card 4: Order Status Summary (Cancelled) -->
            <div class="dashboard-card flex flex-col justify-between" style="border-right: 4px solid #ef4444;">
                <p class="text-sm font-medium text-gray-500">لغو شده</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-1"><?php echo number_format($cancelled['count'] ?? 0); ?></p>
                <p class="text-sm mt-2 text-gray-500 font-medium">
                    از کل <?php echo number_format($totalOrders ?? 0); ?> سفارش
                </p>
            </div>
        </div>

        <!-- 2. Charts Section - Responsive Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Chart 1: Sales Chart Container -->
            <div id="salesChartContainer" class="dashboard-card lg:col-span-2 min-h-[400px] flex flex-col" style="display: flex;"> 
                <h2 class="text-xl font-semibold text-gray-800 mb-4">نمودار فروش (Sales)</h2>
                <div class="flex-grow">
                    <canvas id="salesChart" class="w-full h-full"></canvas>
                </div>
            </div>

            <!-- Chart 2: Status Chart Container -->
            <div id="statusChartContainer" class="dashboard-card lg:col-span-1 min-h-[400px] flex flex-col" style="display: flex;">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">وضعیت سفارشات (Status)</h2>
                <div class="flex-grow">
                    <canvas id="statusChart" class="w-full h-full"></canvas>
                </div>
            </div>

            <!-- Chart 3: Users Chart Container -->
            <div id="usersChartContainer" class="dashboard-card col-span-1 lg:col-span-3 min-h-[400px] flex flex-col" style="display: none;">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">نمودار رشد کاربران (Users)</h2>
                <div class="flex-grow">
                    <canvas id="usersChart" class="w-full h-full"></canvas>
                </div>
            </div>

        </div>

    </main>

<!-- PHP and JS Logic Section -->
<script>
    // 1. Data passed from PHP
    // استفاده از JSON.parse برای تبدیل رشته JSON PHP به شیء جاوا اسکریپت
    const rawSalesData = JSON.parse('<?php echo $chartDataSales ?? '{}'; ?>');
    const rawStatusData = JSON.parse('<?php echo $chartDataStatus ?? '{}'; ?>');
    const rawUsersData = JSON.parse('<?php echo $chartDataUsers ?? '{}'; ?>');

    // 2. Chart Renderers (Updated to use PHP data)
    const chartRenderers = {
        'sales': function() {
            const ctx = document.getElementById('salesChart');
            if (!ctx) return;
            // استفاده از داده‌های دریافتی از PHP (با مقادیر پیش‌فرض برای جلوگیری از خطا)
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: rawSalesData.labels || [],
                    datasets: [{
                        label: 'فروش (میلیون تومان)',
                        data: rawSalesData.data || [],
                        borderColor: '#3b82f6', 
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        tension: 0.3,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top', labels: { usePointStyle: true } } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        },
        'status': function() {
            const ctx = document.getElementById('statusChart');
            if (!ctx) return;
            // استفاده از داده‌های دریافتی از PHP
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: rawStatusData.labels || [],
                    datasets: [{
                        data: rawStatusData.data || [],
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { usePointStyle: true } } }
                }
            });
        },
        'users': function() {
            const ctx = document.getElementById('usersChart');
            if (!ctx) return;
            // استفاده از داده‌های دریافتی از PHP
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: rawUsersData.labels || [],
                    datasets: [{
                        label: 'کاربران جدید',
                        data: rawUsersData.data || [],
                        backgroundColor: '#a855f7', // purple
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    };

    // 3. Toggle Charts Function (Kept from original structure)
    function toggleCharts(show) {
        const containers = {
            sales: document.getElementById('salesChartContainer'),
            status: document.getElementById('statusChartContainer'),
            users: document.getElementById('usersChartContainer')
        };

        ['sales', 'status', 'users'].forEach(key => {
            const container = containers[key];
            if (container) {
                if (show[key]) {
                    container.style.display = 'flex'; // Use flex for better layout control
                    // Render the chart only when it becomes visible for the first time
                    if (!container.hasAttribute('data-rendered')) {
                        chartRenderers[key]();
                        container.setAttribute('data-rendered', 'true');
                    }
                } else {
                    container.style.display = 'none';
                }
            }
        });
    }

    // 4. Vue.js Initialization (Kept from original structure)
    (function() { // استفاده از IIFE برای مطابقت با ساختار قبلی
        if (typeof Vue !== 'undefined') {
            const app = Vue.createApp({
                data() {
                    // Load preferences from localStorage or use defaults
                    const saved = localStorage.getItem('dash_prefs');
                    return {
                        show: saved ? JSON.parse(saved) : {
                            sales: true,
                            status: true,
                            users: true
                        }
                    }
                },
                watch: {
                    show: {
                        deep: true,
                        handler: function(v) {
                            localStorage.setItem('dash_prefs', JSON.stringify(v));
                            toggleCharts(v);
                        }
                    }
                },
                mounted() {
                    // Initial application of visibility and layout
                    toggleCharts(this.show);
                    window.addEventListener('resize', () => toggleCharts(this.show));
                }
            });
            app.mount('#dashPrefs');
        } else {
            // Fallback: If Vue.js is not loaded, just render all charts initially
            chartRenderers['sales']();
            chartRenderers['status']();
            chartRenderers['users']();
            const salesEl = document.getElementById('salesChartContainer');
            const statusEl = document.getElementById('statusChartContainer');
            const usersEl = document.getElementById('usersChartContainer');
            if (salesEl) salesEl.style.display = 'flex';
            if (statusEl) statusEl.style.display = 'flex';
            if (usersEl) usersEl.style.display = 'flex';
        }
    })();
</script>

</body>
</html>