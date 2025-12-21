<!-- Home Page -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card mb-4 fade-in">
                <div class="card-body text-center">
                    <?php if ($authenticated): ?>
                        <div class="avatar mx-auto mb-3">
                            <?php echo substr($user['first_name'], 0, 1); ?>
                        </div>
                        <h2 class="card-title">خوش آمدید، <?php echo SecurityManager::sanitize($user['first_name']); ?>!</h2>
                        <p class="card-text text-muted">
                            به <?php echo APP_NAME; ?> خوش آمدید. از امکانات اپلیکیشن استفاده کنید.
                        </p>
                        
                        <div class="row mt-4">
                            <div class="col-6">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-person-circle fs-1 text-primary mb-2"></i>
                                        <h5>پروفایل</h5>
                                        <p class="small text-muted">مشاهده اطلاعات کاربری</p>
                                        <a href="index.php?action=profile" class="btn btn-primary btn-sm">
                                            مشاهده پروفایل
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-gear fs-1 text-primary mb-2"></i>
                                        <h5>تنظیمات</h5>
                                        <p class="small text-muted">تنظیمات شخصی</p>
                                        <a href="index.php?action=settings" class="btn btn-primary btn-sm">
                                            باز کردن تنظیمات
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <i class="bi bi-person-circle fs-1 text-primary mb-3"></i>
                            <h2 class="card-title">خوش آمدید، کاربر مهمان!</h2>
                            <p class="card-text text-muted">
                                شما در حالت مهمان در حال استفاده از اپلیکیشن هستید.
                            </p>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body text-center">
                                            <i class="bi bi-unlock fs-1 text-warning mb-2"></i>
                                            <h5>دسترسی محدود</h5>
                                            <p class="small text-muted">برای دسترسی کامل، از طریق تلگرم وارد شوید</p>
                                            <button class="btn btn-outline-primary btn-sm" onclick="loginWithTelegram()">
                                                <i class="bi bi-telegram"></i>
                                                ورود با تلگرم
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i>
                                این اپلیکیشن بدون محدودیت قابل استفاده است، اما برخی ویژگی‌ها نیاز به ورود دارند.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($authenticated): ?>
            <!-- Quick Actions -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning"></i>
                        دسترسی سریع
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100" onclick="showUserInfo()">
                                <i class="bi bi-person-lines-fill"></i>
                                اطلاعات کاربری
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100" onclick="showStats()">
                                <i class="bi bi-graph-up"></i>
                                آمار استفاده
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-secondary w-100" onclick="shareApp()">
                                <i class="bi bi-share"></i>
                                اشتراک‌گذاری
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-secondary w-100" onclick="contactSupport()">
                                <i class="bi bi-headset"></i>
                                پشتیبانی
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Guest Actions -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-unlock"></i>
                        امکانات مهمان
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100" onclick="showGuestInfo()">
                                <i class="bi bi-person-lines-fill"></i>
                                اطلاعات مهمان
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100" onclick="showGuestStats()">
                                <i class="bi bi-graph-up"></i>
                                آمار عمومی
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-secondary w-100" onclick="shareApp()">
                                <i class="bi bi-share"></i>
                                اشتراک‌گذاری
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-secondary w-100" onclick="contactSupport()">
                                <i class="bi bi-headset"></i>
                                پشتیبانی
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showUserInfo() {
    apiRequest('user')
        .then(data => {
            const user = data.user;
            showAlert(`
                اطلاعات کاربری:
                نام: ${user.first_name} ${user.last_name || ''}
                نام کاربری: @${user.username || 'ندارد'}
                زبان: ${user.language_code}
            `);
        })
        .catch(error => {
            console.error('Error fetching user info:', error);
        });
}

function showGuestInfo() {
    showAlert(`
        اطلاعات مهمان:
        نوع کاربر: مهمان
        سطح دسترسی: محدود
        برای دسترسی کامل می‌توانید از طریق تلگرم وارد شوید
    `);
}

function showGuestStats() {
    showAlert(`
        آمار عمومی:
        تعداد کاربران: در حال محاسبه...
        تعداد بازدیدها: در حال محاسبه...
        وضعیت سیستم: فعال
    `);
}

function loginWithTelegram() {
    if (window.Telegram && window.Telegram.WebApp) {
        // If already in Telegram WebApp, try to authenticate
        if (window.Telegram.WebApp.initData) {
            window.location.href = 'index.php?initData=' + encodeURIComponent(window.Telegram.WebApp.initData);
        } else {
            showAlert('لطفاً این اپلیکیشن را از طریق تلگرم باز کنید.');
        }
    } else {
        // Show instructions for opening in Telegram
        showAlert('برای ورود با تلگرم، این اپلیکیشن را در تلگرم باز کنید.');
    }
}

function showStats() {
    apiRequest('stats')
        .then(data => {
            const stats = data.stats;
            showAlert(`
                آمار استفاده:
                مجموع نشست‌ها: ${stats.total_sessions}
                اولین بازدید: ${stats.first_seen}
                آخرین بازدید: ${stats.last_seen}
                روزهای فعال: ${stats.days_active}
            `);
        })
        .catch(error => {
            console.error('Error fetching stats:', error);
        });
}

function shareApp() {
    if (window.Telegram && window.Telegram.WebApp) {
        const shareUrl = '<?php echo APP_URL; ?>';
        const shareText = `با این اپلیکیشن عالی آشنا شوید! ${shareUrl}`;
        
        if (tg.shareMessage) {
            tg.shareMessage(shareText);
        } else {
            showAlert('لینک اشتراک‌گذاری کپی شد!');
            navigator.clipboard.writeText(shareText);
        }
    } else {
        showAlert('این قابلیت فقط در تلگرم کار می‌کند.');
    }
}

function contactSupport() {
    showConfirm('آیا می‌خواهید با پشتیبانی تماس بگیرید؟', function() {
        // Open support chat or show contact info
        window.open('https://t.me/your_support_bot', '_blank');
    });
}
</script>