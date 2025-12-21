<!-- Guest Settings Page -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear"></i>
                        تنظیمات مهمان
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        کاربران مهمان نمی‌توانند تنظیمات را تغییر دهند.
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi bi-info-circle"></i>
                                        اطلاعات فعلی
                                    </h6>
                                    <ul class="list-unstyled">
                                        <li><strong>نوع کاربر:</strong> مهمان</li>
                                        <li><strong>زبان:</strong> فارسی (پیش‌فرض)</li>
                                        <li><strong>وضعیت:</strong> فقط خواندنی</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        برای تغییر تنظیمات، لطفاً با تلگرم وارد شوید.
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="loginWithTelegram()">
                            <i class="bi bi-telegram"></i>
                            ورود با تلگرم
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            بازگشت به خانه
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loginWithTelegram() {
    if (window.Telegram && window.Telegram.WebApp) {
        if (window.Telegram.WebApp.initData) {
            window.location.href = 'index.php?initData=' + encodeURIComponent(window.Telegram.WebApp.initData);
        } else {
            showAlert('لطفاً این اپلیکیشن را از طریق تلگرم باز کنید.');
        }
    } else {
        showAlert('برای ورود با تلگرم، این اپلیکیشن را در تلگرم باز کنید.');
    }
}
</script>