<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fa-solid fa-robot"></i> مدیریت میرزا</h3>
        <p class="text-muted" style="font-size: 0.8rem;">پنل حرفه‌ای مدیریت</p>
    </div>

    <ul class="list-unstyled components">
        <li class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <a href="index.php">
                <i class="fa-solid fa-gauge-high"></i>
                داشبورد
            </a>
        </li>
        
        <li class="<?php echo in_array($current_page, ['users.php', 'user.php']) ? 'active' : ''; ?>">
            <a href="users.php">
                <i class="fa-solid fa-users-gear"></i>
                مدیریت کاربران
            </a>
        </li>

        <li class="<?php echo in_array($current_page, ['invoice.php', 'payment.php']) ? 'active' : ''; ?>">
            <a href="#financeSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fa-solid fa-wallet"></i>
                امور مالی
            </a>
            <ul class="collapse list-unstyled <?php echo in_array($current_page, ['invoice.php', 'payment.php']) ? 'show' : ''; ?>" id="financeSubmenu">
                <li><a href="invoice.php">سفارشات</a></li>
                <li><a href="payment.php">تراکنش‌ها</a></li>
            </ul>
        </li>

        <li class="<?php echo in_array($current_page, ['product.php', 'service.php', 'inbound.php', 'productedit.php']) ? 'active' : ''; ?>">
            <a href="#serviceSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fa-solid fa-layer-group"></i>
                محصولات و سرویس‌ها
            </a>
            <ul class="collapse list-unstyled <?php echo in_array($current_page, ['product.php', 'service.php', 'inbound.php']) ? 'show' : ''; ?>" id="serviceSubmenu">
                <li><a href="product.php">محصولات</a></li>
                <li><a href="service.php">سرویس‌های فعال</a></li>
                <li><a href="inbound.php">کانکشن‌ها (Inbounds)</a></li>
            </ul>
        </li>

        <li class="<?php echo in_array($current_page, ['metrics.php', 'server_status.php']) ? 'active' : ''; ?>">
            <a href="#statsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fa-solid fa-chart-line"></i>
                آمار و گزارشات
            </a>
            <ul class="collapse list-unstyled <?php echo in_array($current_page, ['metrics.php', 'server_status.php']) ? 'show' : ''; ?>" id="statsSubmenu">
                <li><a href="metrics.php">گزارش جامع</a></li>
                <li><a href="server_status.php">وضعیت سرور</a></li>
            </ul>
        </li>

        <li class="<?php echo in_array($current_page, ['settings.php', 'seeting_x_ui.php', 'text.php', 'keyboard.php']) ? 'active' : ''; ?>">
            <a href="#settingsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fa-solid fa-sliders"></i>
                تنظیمات سیستم
            </a>
            <ul class="collapse list-unstyled <?php echo in_array($current_page, ['settings.php', 'seeting_x_ui.php', 'text.php', 'keyboard.php']) ? 'show' : ''; ?>" id="settingsSubmenu">
                <li><a href="settings.php">تنظیمات اصلی</a></li>
                <li><a href="seeting_x_ui.php">اتصال X-UI</a></li>
                <li><a href="text.php">مدیریت متن‌ها</a></li>
                <li><a href="keyboard.php">مدیریت کیبورد</a></li>
            </ul>
        </li>

        <li class="mt-5">
            <a href="logout.php" class="text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                خروج از حساب
            </a>
        </li>
    </ul>
</nav>

<!-- Sidebar Toggler Button (Floating) -->
<button type="button" id="sidebarCollapse" class="sidebar-toggler">
    <i class="fa-solid fa-bars"></i>
</button>
<div class="overlay"></div>

<script>
    $(document).ready(function () {
        $('#sidebarCollapse').on('click', function () {
            $('#sidebar').toggleClass('active');
            $('#content').toggleClass('active');
            $('.overlay').toggleClass('active');
        });
        $('.overlay').on('click', function () {
            $('#sidebar').removeClass('active');
            $('#content').removeClass('active');
            $('.overlay').removeClass('active');
        });
    });
</script>
