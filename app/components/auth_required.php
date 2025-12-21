<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نیاز به احراز هویت - Mirza Web App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Vazirmatn', sans-serif;
        }
        .auth-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .auth-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        .telegram-input {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 16px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .telegram-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }
        .btn-telegram {
            background: linear-gradient(45deg, #0088cc, #229ed9);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-telegram:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 136, 204, 0.3);
            color: white;
        }
        .info-text {
            color: #6c757d;
            font-size: 14px;
            margin-top: 20px;
            line-height: 1.6;
        }
        .telegram-id-example {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 13px;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-icon">
            <i class="bi bi-shield-lock"></i>
        </div>
        
        <h4 class="mb-3">احراز هویت مورد نیاز است</h4>
        <p class="text-muted mb-4">
            برای دسترسی به اپلیکیشن، لطفاً شناسه تلگرام خود را وارد کنید
        </p>
        
        <form method="GET" action="index.php">
            <div class="mb-3">
                <input type="text" 
                       class="telegram-input" 
                       name="telegram_id" 
                       placeholder="مثال: 123456789"
                       required
                       pattern="[0-9]{6,}"
                       title="لطفاً فقط اعداد انگلیسی وارد کنید (حداقل 6 رقم)">
            </div>
            
            <button type="submit" class="btn btn-telegram">
                <i class="bi bi-telegram me-2"></i>
                ورود به اپلیکیشن
            </button>
        </form>
        
        <div class="telegram-id-example">
            <strong>نمونه:</strong> index.php?telegram_id=123456789
        </div>
        
        <div class="info-text">
            <i class="bi bi-info-circle me-1"></i>
            شناسه تلگرام خود را می‌توانید از ربات @userinfobot دریافت کنید
        </div>
        
        <div class="info-text">
            <i class="bi bi-robot me-1"></i>
            فقط کاربرانی که ربات را استارت کرده‌اند می‌توانند وارد شوند
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>