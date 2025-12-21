<!-- Settings Page -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear"></i>
                        تنظیمات حساب کاربری
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?action=settings" id="settingsForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <!-- Language Settings -->
                        <div class="mb-4">
                            <label for="language" class="form-label">
                                <i class="bi bi-translate"></i>
                                زبان اپلیکیشن
                            </label>
                            <select class="form-select" id="language" name="language">
                                <option value="fa" <?php echo ($user['language_code'] === 'fa') ? 'selected' : ''; ?>>
                                    فارسی
                                </option>
                                <option value="en" <?php echo ($user['language_code'] === 'en') ? 'selected' : ''; ?>>
                                    English
                                </option>
                                <option value="ru" <?php echo ($user['language_code'] === 'ru') ? 'selected' : ''; ?>>
                                    Русский
                                </option>
                                <option value="ar" <?php echo ($user['language_code'] === 'ar') ? 'selected' : ''; ?>>
                                    العربية
                                </option>
                            </select>
                            <div class="form-text">
                                زبان مورد نظر برای رابط کاربری اپلیکیشن
                            </div>
                        </div>
                        
                        <!-- Notification Settings -->
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="bi bi-bell"></i>
                                اعلان‌ها
                            </h6>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="notifications" checked>
                                <label class="form-check-label" for="notifications">
                                    فعال‌سازی اعلان‌ها
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="sound" checked>
                                <label class="form-check-label" for="sound">
                                    صدای اعلان‌ها
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="vibration">
                                <label class="form-check-label" for="vibration">
                                    لرزش اعلان‌ها
                                </label>
                            </div>
                        </div>
                        
                        <!-- Privacy Settings -->
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="bi bi-shield-lock"></i>
                                حریم خصوصی
                            </h6>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="onlineStatus" checked>
                                <label class="form-check-label" for="onlineStatus">
                                    نمایش وضعیت آنلاین
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="profileVisibility" checked>
                                <label class="form-check-label" for="profileVisibility">
                                    نمایش پروفایل عمومی
                                </label>
                            </div>
                        </div>
                        
                        <!-- Data Settings -->
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="bi bi-database"></i>
                                مدیریت داده‌ها
                            </h6>
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="exportData()">
                                    <i class="bi bi-download"></i>
                                    خروجی گرفتن از داده‌ها
                                </button>
                                
                                <button type="button" class="btn btn-outline-warning" onclick="clearCache()">
                                    <i class="bi bi-trash"></i>
                                    پاک‌سازی حافظه پنهان
                                </button>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i>
                                ذخیره تغییرات
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="resetSettings()">
                                <i class="bi bi-arrow-clockwise"></i>
                                بازگشت به تنظیمات پیش‌فرض
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Advanced Settings -->
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-sliders"></i>
                        تنظیمات پیشرفته
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-info" onclick="showSessionInfo()">
                            <i class="bi bi-info-circle"></i>
                            اطلاعات نشست فعلی
                        </button>
                        
                        <button type="button" class="btn btn-outline-primary" onclick="showApiInfo()">
                            <i class="bi bi-code-slash"></i>
                            اطلاعات API
                        </button>
                        
                        <button type="button" class="btn btn-outline-secondary" onclick="showThemeInfo()">
                            <i class="bi bi-palette"></i>
                            اطلاعات تم
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="card border-danger fade-in">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        منطقه خطر
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        این اقدامات غیرقابل بازگشت هستند. لطفاً با احتیاط عمل کنید.
                    </p>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-danger" onclick="deleteAccount()">
                            <i class="bi bi-person-x"></i>
                            حذف حساب کاربری
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function resetSettings() {
    showConfirm('آیا مطمئن هستید که می‌خواهید تنظیمات را به حالت پیش‌فرض بازگردانید؟', function() {
        // Reset form to default values
        document.getElementById('language').value = 'fa';
        document.getElementById('notifications').checked = true;
        document.getElementById('sound').checked = true;
        document.getElementById('vibration').checked = false;
        document.getElementById('onlineStatus').checked = true;
        document.getElementById('profileVisibility').checked = true;
        
        showAlert('تنظیمات به حالت پیش‌فرض بازگردانده شدند.');
    });
}

function exportData() {
    showLoading();
    
    // Simulate data export
    setTimeout(function() {
        hideLoading();
        
        const userData = {
            user: <?php echo json_encode($user); ?>,
            exportDate: new Date().toISOString(),
            version: '<?php echo APP_VERSION; ?>'
        };
        
        const dataStr = JSON.stringify(userData, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = `user-data-${Date.now()}.json`;
        link.click();
        
        URL.revokeObjectURL(url);
        
        showAlert('داده‌ها با موفقیت خروجی گرفته شدند.');
    }, 1500);
}

function clearCache() {
    showConfirm('آیا مطمئن هستید که می‌خواهید حافظه پنهان را پاک کنید؟', function() {
        showLoading();
        
        // Simulate cache clearing
        setTimeout(function() {
            hideLoading();
            
            // Clear localStorage and sessionStorage
            localStorage.clear();
            sessionStorage.clear();
            
            showAlert('حافظه پنهان با موفقیت پاک شد.');
        }, 1000);
    });
}

function showSessionInfo() {
    const sessionInfo = {
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        language: navigator.language,
        cookieEnabled: navigator.cookieEnabled,
        online: navigator.onLine,
        screenResolution: `${screen.width}x${screen.height}`,
        viewport: `${window.innerWidth}x${window.innerHeight}`,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        sessionStart: new Date(sessionStorage.getItem('sessionStart') || Date.now()).toLocaleString('fa-IR')
    };
    
    let infoText = 'اطلاعات نشست فعلی:\n\n';
    for (const [key, value] of Object.entries(sessionInfo)) {
        infoText += `${key}: ${value}\n`;
    }
    
    showAlert(infoText);
}

function showApiInfo() {
    apiRequest('user')
        .then(data => {
            const user = data.user;
            showAlert(`
                اطلاعات API:
                
                شناسه کاربر: ${user.id}
                شناسه تلگرم: ${user.telegram_id}
                نام: ${user.first_name} ${user.last_name || ''}
                نام کاربری: @${user.username || 'ندارد'}
                زبان: ${user.language_code}
                
                نسخه API: v1
                وضعیت: فعال
            `);
        })
        .catch(error => {
            showAlert('خطا در دریافت اطلاعات API');
        });
}

function showThemeInfo() {
    if (window.Telegram && window.Telegram.WebApp) {
        const theme = window.Telegram.WebApp.themeParams;
        let themeText = 'اطلاعات تم تلگرم:\n\n';
        
        for (const [key, value] of Object.entries(theme)) {
            themeText += `${key}: ${value}\n`;
        }
        
        showAlert(themeText);
    } else {
        showAlert('این قابلیت فقط در تلگرم کار می‌کند.');
    }
}

function deleteAccount() {
    showConfirm('آیا مطمئن هستید که می‌خواهید حساب کاربری خود را حذف کنید؟ این عمل غیرقابل بازگشت است!', function() {
        showConfirm('برای تأیید حذف حساب، لطفاً دوباره تأیید کنید.', function() {
            showLoading();
            
            // Simulate account deletion
            setTimeout(function() {
                hideLoading();
                
                // Logout user
                sessionStorage.clear();
                
                showAlert('حساب کاربری شما با موفقیت حذف شد.');
                
                // Redirect to home
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            }, 2000);
        });
    });
}

// Store session start time
if (!sessionStorage.getItem('sessionStart')) {
    sessionStorage.setItem('sessionStart', Date.now());
}

// Form submission
$('#settingsForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    showLoading();
    
    // Simulate form submission
    setTimeout(function() {
        hideLoading();
        
        // Show success message
        showAlert('تنظیمات با موفقیت ذخیره شدند.');
        
        // Reload page after 1 second
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    }, 1500);
});
</script>