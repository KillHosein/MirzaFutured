<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Mirza Professional Web App</title>
    
    <!-- فونت وزیرمتن (فونت استاندارد و حرفه‌ای فارسی) -->
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet" type="text/css" />

    <style>
      :root {
        /* پالت رنگی مدرن و تیره (Deep Midnight) */
        --bg-color: #050505;
        --surface-color: #0f0f12;
        --surface-light: #18181b;
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        --accent-glow: rgba(99, 102, 241, 0.3);
        
        --text-main: #ffffff;
        --text-dim: #a1a1aa;
        --border-color: rgba(255, 255, 255, 0.08);
        
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        
        --font-main: 'Vazirmatn', sans-serif;
        --radius-lg: 20px;
        --radius-md: 14px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
      }

      body {
        font-family: var(--font-main);
        background-color: var(--bg-color);
        color: var(--text-main);
        margin: 0;
        padding: 0;
        line-height: 1.6;
        overflow-x: hidden;
        min-height: 100vh;
        padding-bottom: 90px; /* فضا برای نوار شناور پایین */
      }

      /* انیمیشن محو شدن کلی صفحه */
      #root {
        animation: pageAppear 0.6s ease-out;
      }

      @keyframes pageAppear {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }

      /* =========================================
         کارت‌ها و بخش‌های اصلی
         ========================================= */
      .card {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 20px;
        margin: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        transition: var(--transition);
      }

      .card:hover {
        border-color: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
      }

      /* =========================================
         سیستم دکمه‌های حرفه‌ای
         ========================================= */
      .btn {
        font-family: var(--font-main);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 14px 24px;
        font-size: 15px;
        font-weight: 700;
        border-radius: var(--radius-md);
        border: none;
        cursor: pointer;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
      }

      .btn-primary {
        background: var(--primary-gradient);
        color: white;
        box-shadow: 0 4px 15px var(--accent-glow);
      }

      .btn-primary:active {
        transform: scale(0.97);
        filter: brightness(0.9);
      }

      .btn-glass {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid var(--border-color);
        color: var(--text-main);
      }

      .btn-glass:hover {
        background: rgba(255, 255, 255, 0.1);
      }

      /* =========================================
         نوار ناوبری شناور (Glassmorphism)
         ========================================= */
      .floating-nav {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(15, 15, 18, 0.8);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 8px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 1000;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
        width: 90%;
        max-width: 400px;
      }

      .nav-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 10px;
        color: var(--text-dim);
        border-radius: 22px;
        text-decoration: none;
        font-size: 11px;
        font-weight: 500;
        transition: var(--transition);
      }

      .nav-item.active {
        background: rgba(99, 102, 241, 0.15);
        color: #818cf8;
      }

      .nav-item svg {
        width: 22px;
        height: 22px;
        margin-bottom: 4px;
      }

      /* =========================================
         استایل‌های فرم و ورودی‌ها
         ========================================= */
      input, select, textarea {
        width: 100%;
        background: var(--surface-light) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-main) !important;
        border-radius: var(--radius-md) !important;
        padding: 14px 18px !important;
        font-family: var(--font-main) !important;
        transition: var(--transition);
        font-size: 15px;
      }

      input:focus {
        border-color: #6366f1 !important;
        outline: none;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
      }

      /* =========================================
         لودینگ (Professional Loader)
         ========================================= */
      .loader-wrap {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
        background: var(--bg-color);
      }

      .spinner {
        width: 50px;
        height: 50px;
        border: 3px solid rgba(99, 102, 241, 0.1);
        border-radius: 50%;
        border-top-color: #6366f1;
        animation: spin 1s ease-in-out infinite;
        filter: drop-shadow(0 0 10px var(--accent-glow));
      }

      @keyframes spin {
        to { transform: rotate(360deg); }
      }

      .loading-text {
        margin-top: 20px;
        font-weight: 600;
        letter-spacing: 0.5px;
        color: var(--text-dim);
        animation: pulse 1.5s infinite;
      }

      @keyframes pulse {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 1; }
      }

      /* مخفی کردن اسکرول‌بار برای زیبایی بیشتر */
      ::-webkit-scrollbar { width: 0px; background: transparent; }
    </style>

    <!-- اسکریپت‌های سیستمی تلگرام و فایل‌های اصلی برنامه -->
    <script src="/app/js/telegram-web-app.js"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
  </head>
  <body>
    <div id="root">
      <!-- حالت پیش‌فرض لودینگ تا زمانی که JS اصلی بارگذاری شود -->
      <div class="loader-wrap">
        <div class="spinner"></div>
        <div class="loading-text">در حال ورود به میرزا...</div>
      </div>
    </div>

    <!-- نوار ناوبری پایینی (Floating Bottom Navigation) -->
    <div class="floating-nav">
      <a href="#" class="nav-item active">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>خانه</span>
      </a>
      
      <a href="#" class="nav-item">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>کیف پول</span>
      </a>

      <a href="#" class="nav-item">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span>دعوت</span>
      </a>

      <a href="#" class="nav-item">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span>تنظیمات</span>
      </a>
    </div>

    <script>
      // مقداردهی اولیه تلگرام برای تنظیم رنگ تم سیستمی در صورت نیاز
      if (window.Telegram && window.Telegram.WebApp) {
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand(); // باز کردن کامل صفحه وب‌اپ
        
        // تنظیم رنگ نوار وضعیت تلگرام هماهنگ با طراحی جدید
        tg.setHeaderColor('#050505');
        tg.setBackgroundColor('#050505');
      }
    </script>
  </body>
</html>