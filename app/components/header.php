<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Telegram Web App -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/app/assets/css/style.css">
    
    <!-- Vazir Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
        }
        
        .main-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        .telegram-theme {
            background: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
        }
        
        .btn-primary {
            background-color: var(--tg-theme-button-color, #0088cc);
            border-color: var(--tg-theme-button-color, #0088cc);
            color: var(--tg-theme-button-text-color, #ffffff);
        }
        
        .btn-secondary {
            background-color: var(--tg-theme-secondary-bg-color, #f1f1f1);
            color: var(--tg-theme-text-color, #000000);
            border: 1px solid var(--tg-theme-hint-color, #999999);
        }
        
        .card {
            background: var(--tg-theme-secondary-bg-color, #f8f9fa);
            border: 1px solid var(--tg-theme-hint-color, #dee2e6);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-control, .form-select {
            background: var(--tg-theme-bg-color, #ffffff);
            border: 1px solid var(--tg-theme-hint-color, #ced4da);
            color: var(--tg-theme-text-color, #000000);
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--tg-theme-button-color, #0088cc);
            box-shadow: 0 0 0 0.2rem rgba(0, 136, 204, 0.25);
        }
        
        .navbar {
            background: var(--tg-theme-header-bg-color, #0088cc);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .navbar-brand {
            color: var(--tg-theme-header-text-color, #ffffff) !important;
            font-weight: 600;
        }
        
        .nav-link {
            color: var(--tg-theme-header-text-color, #ffffff) !important;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--tg-theme-button-color, #0088cc);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--tg-theme-button-text-color, #ffffff);
            font-weight: 600;
            font-size: 18px;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-up {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        
        @media (max-width: 576px) {
            .main-container {
                padding: 15px;
            }
            
            .card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body class="telegram-theme">
    <div class="main-container">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-telegram"></i>
                    <?php echo APP_NAME; ?>
                </a>
                
                <?php if (isset($authenticated) && $authenticated): ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house"></i> خانه
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?action=profile">
                                <i class="bi bi-person"></i> پروفایل
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?action=settings">
                                <i class="bi bi-gear"></i> تنظیمات
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i>
            <?php echo SecurityManager::sanitize($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <?php echo SecurityManager::sanitize($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">در حال بارگذاری...</span>
            </div>
        </div>