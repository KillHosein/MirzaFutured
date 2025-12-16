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
    die("Fatal Error: Database connection variable (\$pdo) is not defined or is not a PDO object. Please check 'config.php'.");
}

// --- Logic Section ---
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
    
    $query = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    // This assumes the username is stored in the session and is a string. Adjust as necessary.
    // For security, do not rely on session data without validation/sanitization in a real app.
    $query->execute([$_SESSION["user"]]); 
    $admin = $query->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        // User session exists but user not found in DB
        session_destroy();
        header('Location: login.php');
        exit;
    }

} catch(PDOException $e) {
    // Log the error (not to the screen in production)
    // echo "Database Error: " . $e->getMessage();
    die("Database Error. Please check logs.");
} catch(Exception $e) {
    // Other errors
    die("An unexpected error occurred.");
}


// 2. Data Fetching Functions
// Function to safely execute a query and return results or an empty array
function fetchData($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // In a real application, log this error instead of echoing
        // error_log("DB Error: " . $e->getMessage());
        return [];
    }
}

// Function to safely execute a COUNT query
function fetchCount($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}


// 3. Stats Calculation (Example placeholder data structure)

// --- A. Total Users ---
$totalUsers = fetchCount($pdo, "SELECT COUNT(*) FROM user");
$newUsersYesterday = fetchCount($pdo, "SELECT COUNT(*) FROM user WHERE timestamp >= ?", [$datefirstday]);

// --- B. Total Sales/Invoices ---
$totalSalesAmount = fetchCount($pdo, "SELECT SUM(amount) FROM invoice WHERE status = 'paid'"); // Assuming 'paid' is the success status
if (!$totalSalesAmount) $totalSalesAmount = 0;
$totalInvoicesCount = fetchCount($pdo, "SELECT COUNT(*) FROM invoice");
$paidInvoicesCount = fetchCount($pdo, "SELECT COUNT(*) FROM invoice WHERE status = 'paid'");

// --- C. End-of-Time Users ---
$expiringUsersCount = fetchCount($pdo, "SELECT COUNT(*) FROM user WHERE end_time < ?", [time() + (86400 * 7)]); // Users expiring in the next 7 days

// --- D. Unpaid Invoices ---
$unpaidInvoicesCount = fetchCount($pdo, "SELECT COUNT(*) FROM invoice WHERE status = 'unpaid'");

// 4. User Table Data Fetching (Limited for dashboard view)
$userQuery = "SELECT id, username, status, end_time FROM user ORDER BY id DESC LIMIT 10";
$recentUsers = fetchData($pdo, $userQuery);

// 5. Chart Data Generation (Placeholders for Chart.js)
// Example: Sales trend for the last 7 days
$salesData = [];
for ($i = 6; $i >= 0; $i--) {
    $dayTimestamp = strtotime("-$i days midnight");
    $dayLabel = jdate('d F', $dayTimestamp, '', 'Asia/Tehran', 'en'); // Day and Month in Persian
    
    // Simulate fetching actual data for the chart
    // Example: fetchCount($pdo, "SELECT COUNT(*) FROM invoice WHERE timestamp >= ? AND timestamp < ?", [$dayTimestamp, $dayTimestamp + 86400]);
    $salesData[] = [
        'label' => $dayLabel, 
        'count' => rand(10, 50) // Placeholder count
    ];
}

$statusData = [
    'active' => fetchCount($pdo, "SELECT COUNT(*) FROM user WHERE status = 'active'"),
    'inactive' => fetchCount($pdo, "SELECT COUNT(*) FROM user WHERE status = 'inactive'"),
    'expired' => fetchCount($pdo, "SELECT COUNT(*) FROM user WHERE status = 'expired'")
];

// PHP to JavaScript data translation
$salesLabels = json_encode(array_column($salesData, 'label'), JSON_UNESCAPED_UNICODE);
$salesCounts = json_encode(array_column($salesData, 'count'));
$statusCounts = json_encode(array_values($statusData));
$statusLabels = json_encode(['فعال', 'غیرفعال', 'منقضی شده'], JSON_UNESCAPED_UNICODE);
$statusColors = json_encode(['#10b981', '#f59e0b', '#ef4444']); // green, yellow, red


?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت - حرفه‌ای</title>
    
    <!-- فونت Vazirmatn برای زیبایی و خوانایی -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.0.3/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <!-- آیکون‌های Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" xintegrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Chart.js برای نمودارها -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Vue.js 3 برای تعاملات UI (اختیارات داشبورد) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    <style>
        /* --- استایل‌های اصلی و Glassmorphism حرفه‌ای --- */
        :root {
            --bg-dark: #0d1117; /* پس‌زمینه بسیار تیره */
            --card-bg: rgba(255, 255, 255, 0.05); /* پس‌زمینه کارت نیمه‌شفاف */
            --glass-border: rgba(255, 255, 255, 0.1); /* مرز شیشه‌ای ظریف */
            --text-primary: #ffffff;
            --text-secondary: #a8b3cf; /* متن فرعی */
            --color-accent: #3b82f6; /* رنگ اصلی آبی */
            --color-success: #10b981; /* سبز */
            --color-warning: #f59e0b; /* زرد */
            --color-danger: #ef4444; /* قرمز */
        }
        
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            display: flex;
            min-height: 100vh;
        }

        /* --- طرح‌بندی (Layout) --- */
        .dashboard-container {
            flex-grow: 1;
            padding: 2rem;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* --- استایل کارت‌های شیشه‌ای (Glassmorphism) --- */
        .glass-card {
            background-color: var(--card-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2), 0 10px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden; /* برای کنترل محتوای داخلی */
        }
        
        .glass-card:hover {
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.3), 0 15px 20px rgba(0, 0, 0, 0.15);
        }

        /* --- Header و ناوبری --- */
        .glass-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--glass-border);
            margin-bottom: 2rem;
        }
        
        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* --- شبکه‌بندی آمار (Stats Grid) --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-icon {
            font-size: 1.8rem;
            padding: 0.6rem;
            border-radius: 8px;
            color: var(--bg-dark); /* رنگ آیکون تیره روی پس‌زمینه رنگی */
            background-color: var(--color-accent); /* پیش‌فرض */
            opacity: 0.8;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-top: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .stat-footer {
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--color-success);
        }

        /* --- دکمه‌های فرم فیلتر --- */
        .filter-form {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-form input[type="date"], .filter-form button {
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .filter-form input[type="date"] {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            appearance: none;
            -webkit-appearance: none;
        }

        .filter-form button.btn-primary {
            background-color: var(--color-accent);
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .filter-form button.btn-primary:hover {
            background-color: #2563eb; /* آبی تیره‌تر */
        }

        /* --- جدول کاربران اخیر (Table) --- */
        .user-table {
            width: 100%;
            border-collapse: separate; /* برای rounded corners در Tbody */
            border-spacing: 0 10px; /* فاصله بین سطرها */
            font-size: 0.95rem;
        }
        
        .user-table th {
            text-align: right;
            color: var(--text-secondary);
            padding: 0.75rem 1rem;
            font-weight: 500;
            border-bottom: 1px solid var(--glass-border);
        }

        .user-table td {
            padding: 1rem;
            background-color: var(--card-bg);
            border: 1px solid var(--glass-border);
        }

        .user-table tr:first-child td {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        /* --- وضعیت‌ها (Badges) --- */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
        }
        
        .badge-active { 
            background-color: rgba(16, 185, 129, 0.2); 
            color: var(--color-success); 
            border: 1px solid rgba(16, 185, 129, 0.5);
        }
        .badge-inactive { 
            background-color: rgba(245, 158, 11, 0.2); 
            color: var(--color-warning); 
            border: 1px solid rgba(245, 158, 11, 0.5);
        }
        .badge-expired { 
            background-color: rgba(239, 68, 68, 0.2); 
            color: var(--color-danger); 
            border: 1px solid rgba(239, 68, 68, 0.5);
        }
        .badge-end-of-time {
            background-color: rgba(239, 68, 68, 0.1); 
            color: var(--color-danger); 
            border: 1px solid var(--color-danger);
        }

        /* --- نمودارها --- */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .chart-container {
            min-height: 300px; /* حداقل ارتفاع برای نمایش بهتر نمودار */
            display: flex; /* تنظیمات پیش‌فرض به دلیل Vue.js */
            flex-direction: column;
        }
        
        /* --- Sidebar / Preferences --- */
        .sidebar {
            width: 250px;
            background-color: #1a202c;
            padding: 2rem 1rem;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-item {
            display: block;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
        }
        
        .sidebar-item.active {
            background-color: var(--color-accent);
            color: white;
            font-weight: 600;
        }

        .dashboard-prefs label {
            display: flex;
            align-items: center;
            cursor: pointer;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            transition: color 0.2s;
        }

        .dashboard-prefs label:hover {
            color: var(--text-primary);
        }

        .dashboard-prefs input[type="checkbox"] {
            margin-left: 0.5rem;
            width: 18px;
            height: 18px;
            accent-color: var(--color-accent);
        }
        
        /* --- واکنش‌گرایی (Mobile Adjustments) --- */
        @media (max-width: 1024px) {
            .sidebar {
                width: 200px;
            }
            .dashboard-container {
                padding: 1rem;
            }
            .header-title {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                padding: 1rem;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: space-around;
                border-bottom: 1px solid var(--glass-border);
            }
            .sidebar-nav {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            .sidebar-item {
                padding: 0.5rem 0.75rem;
                margin-bottom: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
        }

    </style>
</head>
<body>

<!-- نوار کناری (Sidebar) -->
<aside class="sidebar">
    <div class="sidebar-nav">
        <!-- آیکون‌ها به Font Awesome 6 به‌روز شدند -->
        <a href="#" class="sidebar-item active">
            <i class="fa-solid fa-gauge fa-fw"></i>
            <span class="ml-2">داشبورد</span>
        </a>
        <a href="users.php" class="sidebar-item">
            <i class="fa-solid fa-users fa-fw"></i>
            <span class="ml-2">کاربران</span>
        </a>
        <a href="invoices.php" class="sidebar-item">
            <i class="fa-solid fa-file-invoice-dollar fa-fw"></i>
            <span class="ml-2">فاکتورها</span>
        </a>
        <a href="settings.php" class="sidebar-item">
            <i class="fa-solid fa-gear fa-fw"></i>
            <span class="ml-2">تنظیمات</span>
        </a>
    </div>
    
    <div class="mt-8 pt-4 border-t border-gray-700 dashboard-prefs" id="dashPrefs">
        <h4 class="text-lg font-bold mb-4">تنظیمات داشبورد</h4>
        <div v-cloak>
            <label>
                <input type="checkbox" v-model="show.sales" />
                <span>نمودار فروش</span>
            </label>
            <label>
                <input type="checkbox" v-model="show.status" />
                <span>نمودار وضعیت</span>
            </label>
            <label>
                <input type="checkbox" v-model="show.users" />
                <span>نمودار کاربران</span>
            </label>
        </div>
    </div>

    <div class="mt-auto pt-4 border-t border-gray-700">
        <a href="logout.php" class="sidebar-item">
            <i class="fa-solid fa-right-from-bracket fa-fw"></i>
            <span class="ml-2">خروج</span>
        </a>
    </div>
</aside>

<!-- محتوای اصلی داشبورد -->
<main class="dashboard-container">
    
    <!-- Header -->
    <header class="glass-header">
        <h1 class="header-title">داشبورد مدیریت سیستم</h1>
        <p class="text-secondary hidden md:block">خوش آمدید، <?= htmlspecialchars($admin['username']) ?>!</p>
    </header>

    <!-- فیلتر تاریخ و وضعیت -->
    <div class="glass-card mb-6">
        <form method="get" class="filter-form">
            <input type="date" name="from" placeholder="از تاریخ" value="<?= htmlspecialchars($fromDate) ?>">
            <input type="date" name="to" placeholder="تا تاریخ" value="<?= htmlspecialchars($toDate) ?>">
            
            <select name="status[]" multiple style="min-width: 150px; padding: 0.6rem 1rem; border-radius: 8px; background-color: rgba(255, 255, 255, 0.1); border: 1px solid var(--glass-border); color: var(--text-primary);">
                <option value="active" <?= in_array('active', $selectedStatuses) ? 'selected' : '' ?>>فعال</option>
                <option value="inactive" <?= in_array('inactive', $selectedStatuses) ? 'selected' : '' ?>>غیرفعال</option>
                <option value="expired" <?= in_array('expired', $selectedStatuses) ? 'selected' : '' ?>>منقضی شده</option>
            </select>
            
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-filter"></i> اعمال فیلتر
            </button>
        </form>
    </div>

    <!-- شبکه‌بندی آمار و کارت‌ها -->
    <div class="stats-grid">
        <!-- 1. مجموع فروش -->
        <div class="glass-card stat-card">
            <div class="stat-card-header">
                <span class="stat-label">مجموع فروش (ریال)</span>
                <i class="fa-solid fa-sack-dollar stat-icon" style="background-color: #10b981;"></i>
            </div>
            <div class="stat-value"><?= number_format($totalSalesAmount) ?></div>
            <div class="stat-footer">
                <?= number_format($paidInvoicesCount) ?> فاکتور پرداخت شده
            </div>
        </div>

        <!-- 2. تعداد کاربران -->
        <div class="glass-card stat-card">
            <div class="stat-card-header">
                <span class="stat-label">کل کاربران</span>
                <i class="fa-solid fa-users stat-icon" style="background-color: #3b82f6;"></i>
            </div>
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-footer">
                +<?= number_format($newUsersYesterday) ?> کاربر جدید دیروز
            </div>
        </div>

        <!-- 3. فاکتورهای پرداخت نشده -->
        <div class="glass-card stat-card">
            <div class="stat-card-header">
                <span class="stat-label">فاکتورهای پرداخت نشده</span>
                <i class="fa-solid fa-bell stat-icon" style="background-color: #f59e0b;"></i>
            </div>
            <div class="stat-value"><?= number_format($unpaidInvoicesCount) ?></div>
            <div class="stat-footer" style="color: var(--color-warning);">
                نیاز به پیگیری دارند
            </div>
        </div>

        <!-- 4. کاربران در آستانه انقضا -->
        <div class="glass-card stat-card">
            <div class="stat-card-header">
                <span class="stat-label">کاربران با پایان زمان نزدیک</span>
                <i class="fa-solid fa-clock stat-icon" style="background-color: #ef4444;"></i>
            </div>
            <div class="stat-value"><?= number_format($expiringUsersCount) ?></div>
            <div class="stat-footer" style="color: var(--color-danger);">
                منقضی شده یا در ۷ روز آینده
            </div>
        </div>
    </div>
    
    <!-- بخش نمودارها -->
    <div class="charts-grid mb-8">
        <!-- نمودار فروش -->
        <div class="glass-card chart-container" id="salesChartContainer" style="display: none;">
            <h3 class="font-bold text-lg mb-4">روند فروش (۷ روز اخیر)</h3>
            <canvas id="salesChart"></canvas>
        </div>
        
        <!-- نمودار وضعیت کاربران -->
        <div class="glass-card chart-container" id="statusChartContainer" style="display: none;">
            <h3 class="font-bold text-lg mb-4">توزیع وضعیت کاربران</h3>
            <canvas id="statusChart"></canvas>
        </div>
        
        <!-- نمودار کاربران جدید (نمونه سوم) -->
        <div class="glass-card chart-container" id="usersChartContainer" style="display: none;">
            <h3 class="font-bold text-lg mb-4">کاربران جدید ماهانه (نمونه)</h3>
            <canvas id="usersChart"></canvas>
        </div>
    </div>

    <!-- جدول کاربران اخیر -->
    <div class="glass-card">
        <h3 class="font-bold text-xl mb-6">آخرین فعالیت کاربران</h3>
        
        <div style="overflow-x: auto;">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام کاربری</th>
                        <th>وضعیت</th>
                        <th>پایان زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr><td colspan="4" class="text-center text-secondary">هیچ کاربری یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $user): ?>
                            <?php 
                                $statusClass = '';
                                $statusText = '';
                                if ($user['status'] == 'active') {
                                    $statusClass = 'badge-active';
                                    $statusText = 'فعال';
                                } elseif ($user['status'] == 'inactive') {
                                    $statusClass = 'badge-inactive';
                                    $statusText = 'غیرفعال';
                                } else {
                                    $statusClass = 'badge-expired';
                                    $statusText = 'منقضی شده';
                                }

                                $endTimeTimestamp = (int)$user['end_time'];
                                $endTimeText = jdate('Y/m/d H:i', $endTimeTimestamp);
                                if ($endTimeTimestamp < time()) {
                                    $endTimeText .= ' (پایان یافته)';
                                    // می‌توان یک کلاس اضافی برای سطر پایان یافته در نظر گرفت
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td>
                                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td style="direction: ltr; text-align: left;"><?= $endTimeText ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
// --- کدهای JavaScript برای نمودارها و تعاملات (بدون تغییر منطق) ---

(function() {
    const chartRenderers = {};
    const salesLabels = <?= $salesLabels ?>;
    const salesCounts = <?= $salesCounts ?>;
    const statusLabels = <?= $statusLabels ?>;
    const statusCounts = <?= $statusCounts ?>;
    const statusColors = <?= $statusColors ?>;

    // رندر نمودار فروش (خطی)
    chartRenderers['sales'] = function() {
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'تعداد فروش',
                    data: salesCounts,
                    borderColor: 'rgb(59, 130, 246)', // var(--color-accent)
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: 'var(--text-secondary)',
                            font: { family: 'Vazirmatn' }
                        }
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: 'var(--text-secondary)', font: { family: 'Vazirmatn' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: 'var(--text-secondary)', font: { family: 'Vazirmatn' } }
                    }
                }
            }
        });
        ctx.chart = chart; // ذخیره شیء نمودار
    };

    // رندر نمودار وضعیت کاربران (دایره‌ای/Pie)
    chartRenderers['status'] = function() {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;

        const chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'توزیع کاربران',
                    data: statusCounts,
                    backgroundColor: statusColors,
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
                            color: 'var(--text-secondary)',
                            font: { family: 'Vazirmatn' }
                        }
                    },
                    tooltip: {
                         // RTL Tooltip fix
                        rtl: true,
                        titleFont: { family: 'Vazirmatn' },
                        bodyFont: { family: 'Vazirmatn' }
                    }
                }
            }
        });
        ctx.chart = chart;
    };
    
    // رندر نمودار کاربران جدید (ستون/Bar) - (نمونه سوم)
    chartRenderers['users'] = function() {
        const ctx = document.getElementById('usersChart');
        if (!ctx) return;
        
        const placeholderLabels = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد"];
        const placeholderData = [120, 190, 300, 50, 200];

        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: placeholderLabels,
                datasets: [{
                    label: 'تعداد کاربران جدید',
                    data: placeholderData,
                    backgroundColor: 'rgba(255, 165, 0, 0.6)', // نارنجی ملایم
                    borderColor: 'rgb(255, 165, 0)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: 'var(--text-secondary)',
                            font: { family: 'Vazirmatn' }
                        }
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: 'var(--text-secondary)', font: { family: 'Vazirmatn' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: 'var(--text-secondary)', font: { family: 'Vazirmatn' } }
                    }
                }
            }
        });
        ctx.chart = chart; 
    };
    
    // تابع کمکی برای حذف/اضافه و رندر مجدد نمودارها
    function toggleCharts(showPrefs) {
        const containers = {
            sales: document.getElementById('salesChartContainer'),
            status: document.getElementById('statusChartContainer'),
            users: document.getElementById('usersChartContainer')
        };
        
        for (const key in showPrefs) {
            const container = containers[key];
            if (!container) continue;

            const isShown = showPrefs[key];
            
            if (isShown) {
                container.style.display = 'flex';
                // Lazy initialize/render the chart if it hasn't been rendered or if it needs redraw
                const canvas = container.querySelector('canvas');
                if (!canvas.chart) {
                    chartRenderers[key]();
                } else {
                    // Re-render on resize might be needed for perfect responsiveness
                    canvas.chart.resize();
                }
            } else {
                container.style.display = 'none';
            }
        }
    }


    // --- Vue.js Application for Dashboard Preferences ---
    if (typeof Vue !== 'undefined') {
        // Load initial preferences from localStorage
        let initialPrefs = { sales: true, status: true, users: true };
        try {
            const storedPrefs = localStorage.getItem('dash_prefs');
            if (storedPrefs) {
                const parsedPrefs = JSON.parse(storedPrefs);
                // Ensure all keys are present, merging stored with default
                initialPrefs = { ...initialPrefs, ...parsedPrefs };
            }
        } catch(e) {
            console.error("Error loading localStorage prefs:", e);
        }

        const app = Vue.createApp({
            data() {
                return {
                    show: initialPrefs
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
                // Initial application of visibility and layout
                toggleCharts(this.show); 
                // Since we render charts on first show, no separate lazyInit is strictly needed,
                // but we keep the concept for future use or heavy charts.
                window.addEventListener('resize', () => toggleCharts(this.show));
            }
        });
        app.mount('#dashPrefs');
    } else {
        // Fallback: If Vue.js is not loaded, just render all charts initially
        chartRenderers['sales']();
        chartRenderers['status']();
        chartRenderers['users']();
        // Since Vue is not running, we set initial display inline (already done in HTML for safety)
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