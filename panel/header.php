<?php
// ... [کدهای PHP شما بدون تغییر باقی می‌ماند] ...
// --- سیستم مدیریت سازمانی (Enterprise Core) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
if (file_exists('../config.php')) require_once '../config.php';
if (file_exists('../jdf.php')) require_once '../jdf.php';

$isConnected = isset($pdo) && ($pdo instanceof PDO);
$dateYesterday = time() - 86400;
// ... (ادامه منطق PHP شما برای آمار و نمودارها) ...
// برای کوتاه شدن کد، فرض می‌کنیم متغیرهای $stats, $chartData, $salesLabels و ... آماده هستند.
// ...
$hour = date('H');
$greet = ($hour < 12) ? "صبح بخیر" : (($hour < 18) ? "ظهر بخیر" : "عصر بخیر");
$today = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <?php include 'header.php'; ?>
    <title>داشبورد مدیریت</title>
</head>
<body>
    <div class="container-fluid-custom">
        <header style="margin-bottom: 50px;">
            <h1 style="font-size:3rem; font-weight:900; color:#fff;"><?php echo $greet; ?>، ادمین</h1>
            <p style="color:#aaa;"><?php echo $today; ?></p>
        </header>

        <!-- آمارها -->
        <section class="stats-deck anim d-1">
            <div class="stat-card s-blue">
                <div class="stat-top"><i class="fa-solid fa-sack-dollar stat-icon"></i><span style="background: rgba(34, 211, 238, 0.1); color: var(--neon-blue); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">درآمد کل</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['sales']); ?></div><div class="stat-lbl">تومان ایران</div></div>
            </div>
            <div class="stat-card s-teal">
                <div class="stat-top"><i class="fa-solid fa-file-invoice-dollar stat-icon"></i><span style="background: rgba(45, 212, 191, 0.1); color: var(--neon-teal); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">تراکنش‌ها</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['orders']); ?></div><div class="stat-lbl">سفارش موفق</div></div>
            </div>
            <div class="stat-card s-purple">
                <div class="stat-top"><i class="fa-solid fa-users-rays stat-icon"></i><span style="background: rgba(192, 132, 252, 0.1); color: var(--neon-purple); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">کاربران</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['users']); ?></div><div class="stat-lbl">مشترکین فعال</div></div>
            </div>
            <div class="stat-card s-amber">
                <div class="stat-top"><i class="fa-solid fa-user-plus stat-icon"></i><span style="background: rgba(251, 191, 36, 0.1); color: var(--neon-amber); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">امروز</span></div>
                <div><div class="stat-val"><?php echo number_format($stats['new_users']); ?></div><div class="stat-lbl">کاربر جدید</div></div>
            </div>
        </section>

        <!-- دسترسی سریع -->
        <div class="section-header">دسترسی سریع</div>
        <div class="action-deck">
            <a href="users.php" class="action-tile"><i class="fa-solid fa-users tile-icon"></i>کاربران</a>
            <a href="invoice.php" class="action-tile"><i class="fa-solid fa-file-invoice tile-icon"></i>سفارشات</a>
            <a href="product.php" class="action-tile"><i class="fa-solid fa-box-open tile-icon"></i>محصولات</a>
            <a href="inbound.php" class="action-tile"><i class="fa-solid fa-network-wired tile-icon"></i>ورودی‌ها</a>
            <a href="payment.php" class="action-tile"><i class="fa-solid fa-credit-card tile-icon"></i>تراکنش‌ها</a>
            <a href="service.php" class="action-tile"><i class="fa-solid fa-server tile-icon"></i>سرویس‌ها</a>
            <a href="metrics.php" class="action-tile"><i class="fa-solid fa-chart-pie tile-icon"></i>آمار</a>
            <a href="seeting_x_ui.php" class="action-tile"><i class="fa-solid fa-tower-broadcast tile-icon"></i>پنل X-UI</a>
            <a href="text.php" class="action-tile"><i class="fa-solid fa-file-lines tile-icon"></i>مدیریت متن</a>
            <a href="keyboard.php" class="action-tile"><i class="fa-solid fa-keyboard tile-icon"></i>کیبورد</a>
            <a href="settings.php" class="action-tile"><i class="fa-solid fa-sliders tile-icon"></i>تنظیمات</a>
            <a href="server_status.php" class="action-tile"><i class="fa-solid fa-shield-halved tile-icon"></i>وضعیت سرور</a>
            <a href="productedit.php" class="action-tile"><i class="fa-solid fa-pen-to-square tile-icon"></i>ویرایش محصول</a>
            <a href="cancelService.php" class="action-tile" style="color:#ff4444; border-color:rgba(255,0,0,0.3);"><i class="fa-solid fa-ban tile-icon"></i>مسدودی</a>
        </div>

        <!-- نمودارها -->
        <section class="charts-grid anim d-3">
            <div class="chart-box">
                <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-chart-area" style="color: var(--neon-blue);"></i>روند فروش</div></div>
                <div style="height: 450px; width: 100%;"><canvas id="salesChart"></canvas></div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 40px;">
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-chart-pie" style="color: var(--neon-purple);"></i>وضعیت‌ها</div></div>
                    <div style="height: 250px; position: relative;"><canvas id="statusChart"></canvas></div>
                </div>
                <div class="chart-box" style="flex: 1;">
                    <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-arrow-trend-up" style="color: var(--neon-teal);"></i>رشد کاربر</div></div>
                    <div style="height: 200px;"><canvas id="usersChart"></canvas></div>
                </div>
            </div>
        </section>
    </div>

    <!-- داک شناور -->
    <div class="dock-container">
        <div class="dock">
            <a href="index.php" class="dock-item active"><div class="dock-icon"><i class="fa-solid fa-house"></i></div><span class="dock-label">داشبورد</span></a>
            <a href="users.php" class="dock-item"><div class="dock-icon"><i class="fa-solid fa-users"></i></div><span class="dock-label">کاربران</span></a>
            <a href="invoice.php" class="dock-item"><div class="dock-icon"><i class="fa-solid fa-file-invoice"></i></div><span class="dock-label">سفارشات</span></a>
            <a href="product.php" class="dock-item"><div class="dock-icon"><i class="fa-solid fa-box"></i></div><span class="dock-label">محصولات</span></a>
            <a href="inbound.php" class="dock-item"><div class="dock-icon"><i class="fa-solid fa-network-wired"></i></div><span class="dock-label">ورودی</span></a>
            <a href="service.php" class="dock-item"><div class="dock-icon"><i class="fa-solid fa-server"></i></div><span class="dock-label">سرویس</span></a>
            <a href="settings.php" class="dock-item"><div class="dock-icon"><i class="fa-solid fa-gear"></i></div><span class="dock-label">تنظیمات</span></a>
            <a href="payment.php" class="dock-item"><div class="dock-icon"><i class="fa-solid fa-credit-card"></i></div><span class="dock-label">مالی</span></a>
            <div style="width:1px; height:30px; background:rgba(255,255,255,0.2);"></div>
            <a href="logout.php" class="dock-item" style="color:#ff4444;"><div class="dock-icon"><i class="fa-solid fa-power-off"></i></div><span class="dock-label">خروج</span></a>
        </div>
    </div>
    
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = 'Vazirmatn'; Chart.defaults.font.size = 15; Chart.defaults.font.weight = 'bold'; Chart.defaults.color = '#64748B'; Chart.defaults.borderColor = 'rgba(255,255,255,0.04)'; Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(5, 5, 10, 0.9)'; Chart.defaults.plugins.tooltip.padding = 18; Chart.defaults.plugins.tooltip.cornerRadius = 14; Chart.defaults.plugins.tooltip.titleFont = { size: 16, weight: 800 }; Chart.defaults.plugins.tooltip.bodyFont = { size: 14 };
        const dSales = { labels: <?php echo json_encode($salesLabels); ?>, values: <?php echo json_encode($salesValues); ?> };
        const dPie = { labels: <?php echo json_encode($pieLabels); ?>, values: <?php echo json_encode($pieValues); ?> };
        const dUsers = { labels: <?php echo json_encode($userLabels); ?>, values: <?php echo json_encode($userValues); ?> };
        const ctxS = document.getElementById('salesChart').getContext('2d');
        const gradS = ctxS.createLinearGradient(0, 0, 0, 500); gradS.addColorStop(0, '#22d3ee'); gradS.addColorStop(1, 'rgba(34, 211, 238, 0.02)');
        new Chart(ctxS, { type: 'line', data: { labels: dSales.labels, datasets: [{ label: 'فروش', data: dSales.values, borderColor: '#22d3ee', backgroundColor: gradS, borderWidth: 3, pointRadius: 0, pointHoverRadius: 8, pointBackgroundColor: '#fff', fill: true, tension: 0.45 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 13 } } }, y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { font: { size: 13 } } } } } });
        new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: { labels: dPie.labels, datasets: [{ data: dPie.values, backgroundColor: ['#fbbf24', '#10b981', '#64748b', '#ef4444', '#3b82f6', '#8b5cf6', '#f97316', '#334155'], borderWidth: 0, hoverOffset: 20 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '82%', plugins: { legend: { position: 'right', labels: { boxWidth: 12, color: '#94a3b8', font: {size: 13}, usePointStyle: true, padding: 15 } } } } });
        const ctxU = document.getElementById('usersChart').getContext('2d');
        const gradU = ctxU.createLinearGradient(0, 0, 0, 300); gradU.addColorStop(0, '#2dd4bf'); gradU.addColorStop(1, 'rgba(45, 212, 191, 0.3)');
        new Chart(ctxU, { type: 'bar', data: { labels: dUsers.labels, datasets: [{ label: 'کاربر', data: dUsers.values, backgroundColor: gradU, borderRadius: 6, barThickness: 20 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } } });
    </script>
</body>
</html>