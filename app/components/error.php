<!-- Error Page -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card text-center fade-in">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                    </div>
                    <h2 class="card-title text-danger">خطا</h2>
                    <h5 class="card-subtitle mb-3"><?php echo SecurityManager::sanitize($title); ?></h5>
                    <p class="card-text text-muted mb-4">
                        <?php echo SecurityManager::sanitize($message); ?>
                    </p>
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-house"></i>
                            بازگشت به خانه
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                            <i class="bi bi-arrow-left"></i>
                            بازگشت به صفحه قبل
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (ENVIRONMENT === 'development'): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-bug"></i>
                        اطلاعات فنی (فقط در حالت توسعه)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small text-muted">
                        <strong>زمان خطا:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                        <strong>آدرس:</strong> <?php echo SecurityManager::sanitize($_SERVER['REQUEST_URI']); ?><br>
                        <strong>مرجع:</strong> <?php echo SecurityManager::sanitize($_SERVER['HTTP_REFERER'] ?? 'مشخص نیست'); ?><br>
                        <strong>مرورگر:</strong> <?php echo SecurityManager::sanitize($_SERVER['HTTP_USER_AGENT']); ?><br>
                        <strong>آی‌پی:</strong> <?php echo SecurityManager::sanitize($_SERVER['REMOTE_ADDR']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-redirect to home after 10 seconds
setTimeout(function() {
    window.location.href = 'index.php';
}, 10000);

// Show countdown
let countdown = 10;
const countdownElement = document.createElement('div');
countdownElement.className = 'text-center mt-3';
countdownElement.innerHTML = `<small class="text-muted">انتقال خودکار به صفحه اصلی در <span class="fw-bold">${countdown}</span> ثانیه...</small>`;
document.querySelector('.card-body').appendChild(countdownElement);

const countdownInterval = setInterval(function() {
    countdown--;
    countdownElement.innerHTML = `<small class="text-muted">انتقال خودکار به صفحه اصلی در <span class="fw-bold">${countdown}</span> ثانیه...</small>`;
    
    if (countdown <= 0) {
        clearInterval(countdownInterval);
    }
}, 1000);
</script>