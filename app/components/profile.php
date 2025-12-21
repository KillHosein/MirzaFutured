<!-- Profile Page -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-circle"></i>
                        پروفایل کاربری
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center mb-4">
                        <div class="col-auto">
                            <div class="avatar">
                                <?php echo substr($user['first_name'], 0, 1); ?>
                            </div>
                        </div>
                        <div class="col">
                            <h4 class="mb-1"><?php echo SecurityManager::sanitize($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                            <p class="text-muted mb-0">
                                @<?php echo SecurityManager::sanitize($user['username'] ?? 'بدون نام کاربری'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">شناسه تلگرم:</label>
                                <div class="form-control-plaintext"><?php echo SecurityManager::sanitize($user['telegram_id']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">زبان:</label>
                                <div class="form-control-plaintext">
                                    <?php 
                                    $languages = [
                                        'en' => 'English',
                                        'fa' => 'فارسی',
                                        'ru' => 'Русский',
                                        'ar' => 'العربية'
                                    ];
                                    echo $languages[$user['language_code']] ?? $user['language_code']; 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">تاریخ عضویت:</label>
                                <div class="form-control-plaintext">
                                    <?php echo jdate('Y/m/d H:i', strtotime($user['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">آخرین بازدید:</label>
                                <div class="form-control-plaintext">
                                    <?php echo jdate('Y/m/d H:i', strtotime($user['last_seen'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up"></i>
                        آمار و فعالیت‌ها
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-primary"><?php echo $stats['total_sessions']; ?></div>
                                <div class="small text-muted">کل نشست‌ها</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-success"><?php echo $stats['days_active']; ?></div>
                                <div class="small text-muted">روز فعال</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-info">
                                    <?php echo floor((time() - strtotime($user['created_at'])) / 86400); ?>
                                </div>
                                <div class="small text-muted">روز عضویت</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-warning">
                                    <?php echo floor((time() - strtotime($user['last_seen'])) / 3600); ?>
                                </div>
                                <div class="small text-muted">ساعت پیش</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Timeline -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history"></i>
                        فعالیت‌های اخیر
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-check fs-1 text-muted mb-3"></i>
                        <p class="text-muted">در حال بارگذاری فعالیت‌ها...</p>
                        <button class="btn btn-outline-primary" onclick="loadActivity()">
                            <i class="bi bi-arrow-clockwise"></i>
                            بارگذاری فعالیت‌ها
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadActivity() {
    showLoading();
    
    // Simulate API call
    setTimeout(function() {
        hideLoading();
        
        // Replace with actual activity data
        const activityContainer = document.querySelector('.card-body .text-center').parentElement;
        activityContainer.innerHTML = `
            <div class="timeline">
                <div class="timeline-item mb-3">
                    <div class="timeline-marker bg-success"></div>
                    <div class="timeline-content">
                        <h6 class="mb-1">ورود به سیستم</h6>
                        <p class="small text-muted mb-0">ورود موفق از طریق تلگرم</p>
                        <small class="text-muted">همین حالا</small>
                    </div>
                </div>
                <div class="timeline-item mb-3">
                    <div class="timeline-marker bg-primary"></div>
                    <div class="timeline-content">
                        <h6 class="mb-1">بازدید از پروفایل</h6>
                        <p class="small text-muted mb-0">مشاهده اطلاعات کاربری</p>
                        <small class="text-muted">چند دقیقه پیش</small>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-marker bg-info"></div>
                    <div class="timeline-content">
                        <h6 class="mb-1">به‌روزرسانی تنظیمات</h6>
                        <p class="small text-muted mb-0">تغییر زبان به فارسی</p>
                        <small class="text-muted">دیروز</small>
                    </div>
                </div>
            </div>
        `;
        
        // Add timeline styles
        const style = document.createElement('style');
        style.textContent = `
            .timeline {
                position: relative;
                padding-left: 30px;
            }
            .timeline-item {
                position: relative;
                padding-left: 20px;
            }
            .timeline-item::before {
                content: '';
                position: absolute;
                left: -8px;
                top: 0;
                bottom: 0;
                width: 2px;
                background: #e9ecef;
            }
            .timeline-item:last-child::before {
                bottom: auto;
                height: 20px;
            }
            .timeline-marker {
                position: absolute;
                left: -12px;
                top: 5px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                border: 2px solid #fff;
                box-shadow: 0 0 0 2px #e9ecef;
            }
            .timeline-content {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                border-left: 3px solid #007bff;
            }
        `;
        document.head.appendChild(style);
    }, 1000);
}

// Add Persian date function (if not available)
function jdate(format, timestamp) {
    // Simple Persian date conversion
    const date = new Date(timestamp * 1000);
    const persianMonths = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
    ];
    
    if (format === 'Y/m/d H:i') {
        return date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
    }
    
    return date.toLocaleDateString('fa-IR');
}
</script>