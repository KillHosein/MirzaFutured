<!-- Guest Profile Page -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-circle"></i>
                        پروفایل مهمان
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar mx-auto mb-3 bg-warning">
                            <i class="bi bi-person"></i>
                        </div>
                        <h4><?php echo SecurityManager::sanitize($user['first_name']); ?></h4>
                        <p class="text-muted">کاربر مهمان</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi bi-info-circle"></i>
                                        اطلاعات حساب
                                    </h6>
                                    <ul class="list-unstyled">
                                        <li><strong>نوع کاربر:</strong> مهمان</li>
                                        <li><strong>زبان:</strong> فارسی</li>
                                        <li><strong>تاریخ ایجاد:</strong> <?php echo SecurityManager::sanitize($user['created_at']); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        برای دسترسی به تمام امکانات اپلیکیشن، می‌توانید با تلگرم وارد شوید.
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