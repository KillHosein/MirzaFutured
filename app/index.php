<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    <title>Mirza Premium Ecosystem</title>
    
    <!-- فونت استاندارد وزیرمتن با تمام وزن‌ها -->
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet" />

    <style>
      :root {
        /* پالت رنگی فوق‌تیره و لوکس */
        --bg-deep: #000000;
        --bg-surface: #0a0a0c;
        --accent: #6366f1;
        --accent-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        --accent-glow: rgba(99, 102, 241, 0.4);
        
        --glass-bg: rgba(255, 255, 255, 0.03);
        --glass-border: rgba(255, 255, 255, 0.08);
        
        --text-high: #ffffff;
        --text-mid: #a1a1aa;
        --text-low: #52525b;
        
        --safe-bottom: env(safe-area-inset-bottom);
        --ease-premium: cubic-bezier(0.16, 1, 0.3, 1);
      }

      * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
        outline: none;
      }

      body {
        font-family: 'Vazirmatn', sans-serif;
        background-color: var(--bg-deep);
        background-image: 
            radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.1) 0%, transparent 40%),
            radial-gradient(circle at 100% 100%, rgba(168, 85, 247, 0.08) 0%, transparent 40%);
        color: var(--text-high);
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        min-height: 100vh;
      }

      /* انیمیشن ورود پله‌ای برای اجزای صفحه */
      .stagger-item {
        opacity: 0;
        transform: translateY(20px);
        animation: slideUp 0.8s var(--ease-premium) forwards;
      }

      .delay-1 { animation-delay: 0.1s; }
      .delay-2 { animation-delay: 0.2s; }
      .delay-3 { animation-delay: 0.3s; }

      @keyframes slideUp {
        to { opacity: 1; transform: translateY(0); }
      }

      /* کانتینر اصلی */
      #app-container {
        padding: 20px 16px calc(110px + var(--safe-bottom));
      }

      /* هدر حرفه‌ای */
      .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 10px 5px;
      }

      .profile-zone {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: var(--accent-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        box-shadow: 0 0 20px var(--accent-glow);
      }

      /* کارت موجودی شیشه‌ای (Vault Style) */
      .vault-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 32px;
        padding: 30px;
        text-align: center;
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
      }

      .vault-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: conic-gradient(from 0deg, transparent, rgba(255,255,255,0.05), transparent);
        animation: rotateGlow 10s linear infinite;
      }

      @keyframes rotateGlow {
        to { transform: rotate(360deg); }
      }

      /* سیستم دکمه‌های تراز اول */
      .btn-action {
        background: var(--accent-gradient);
        color: white;
        border: none;
        padding: 16px;
        border-radius: 20px;
        font-size: 16px;
        font-weight: 800;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s var(--ease-premium);
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
      }

      .btn-action:active {
        transform: scale(0.95);
      }

      /* لیست تراکنش‌های لوکس */
      .transaction-item {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 20px;
        padding: 16px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid transparent;
        transition: var(--ease-premium);
      }

      .transaction-item:hover {
        background: rgba(255, 255, 255, 0.04);
        border-color: var(--glass-border);
      }

      /* نوار ناوبری معلق (Floating Dock) */
      .dock {
        position: fixed;
        bottom: calc(25px + var(--safe-bottom));
        left: 50%;
        transform: translateX(-50%);
        width: 92%;
        max-width: 420px;
        height: 76px;
        background: rgba(18, 18, 22, 0.85);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 38px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        z-index: 9999;
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8);
      }

      .dock-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        color: var(--text-low);
        text-decoration: none;
        transition: all 0.4s var(--ease-premium);
        position: relative;
        width: 60px;
      }

      .dock-item svg {
        width: 24px;
        height: 24px;
        transition: all 0.4s var(--ease-premium);
      }

      .dock-item span {
        font-size: 10px;
        font-weight: 700;
        margin-top: 4px;
        opacity: 0;
        transform: translateY(5px);
        transition: all 0.4s var(--ease-premium);
      }

      .dock-item.active {
        color: var(--accent);
      }

      .dock-item.active svg {
        transform: translateY(-8px);
        filter: drop-shadow(0 0 10px var(--accent-glow));
      }

      .dock-item.active span {
        opacity: 1;
        transform: translateY(-5px);
      }

      /* لودینگ اولیه سفارشی */
      .preloader {
        position: fixed;
        inset: 0;
        background: var(--bg-deep);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 10000;
      }

      .loader-circle {
        width: 64px;
        height: 64px;
        border: 2px solid rgba(255,255,255,0.05);
        border-top: 2px solid var(--accent);
        border-radius: 50%;
        animation: spin 1s var(--ease-premium) infinite;
      }

      @keyframes spin { to { transform: rotate(360deg); } }

      /* تایپوگرافی پلاس */
      .amount-large {
        font-size: 38px;
        font-weight: 900;
        background: linear-gradient(to bottom, #fff, #a1a1aa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
      }

    </style>

    <script src="/app/js/telegram-web-app.js"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
  </head>
  <body>

    <!-- لودینگ اختصاصی -->
    <div id="mirza-preloader" class="preloader">
        <div class="loader-circle"></div>
        <p style="margin-top: 20px; font-weight: 900; letter-spacing: 2px; color: var(--text-mid);">MIRZA</p>
    </div>

    <div id="root">
        <div id="app-container">
            <!-- هدر -->
            <header class="header stagger-item delay-1">
                <div class="profile-zone">
                    <div class="avatar">M</div>
                    <div>
                        <h4 style="margin:0; font-size: 16px;">میرزا پنل</h4>
                        <p style="font-size: 12px; color: var(--text-muted);">حساب ویژه پریمیوم</p>
                    </div>
                </div>
                <div style="background: var(--glass-bg); padding: 8px 12px; border-radius: 12px; border: 1px solid var(--glass-border);">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                </div>
            </header>

            <!-- کارت موجودی اصلی -->
            <div class="vault-card stagger-item delay-2">
                <p style="font-size: 13px; color: var(--text-mid); margin-bottom: 8px;">کل دارایی قابل برداشت</p>
                <div class="amount-large">۱۵,۲۴۰,۰۰۰ <span style="font-size: 16px; color: var(--text-low);">تومان</span></div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 30px;">
                    <button class="btn-action">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                        افزایش
                    </button>
                    <button class="btn-action" style="background: var(--glass-bg); border: 1px solid var(--glass-border); box-shadow: none;">
                        تسویه
                    </button>
                </div>
            </div>

            <!-- بخش لیست‌ها -->
            <div class="stagger-item delay-3">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-size: 18px; margin: 0;">فعالیت‌های اخیر</h3>
                    <a href="#" style="color: var(--accent); font-size: 13px; text-decoration: none; font-weight: 700;">مشاهده همه</a>
                </div>

                <!-- نمونه تراکنش -->
                <div class="transaction-item">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div style="width: 48px; height: 48px; border-radius: 16px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center; color: #10b981;">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                        </div>
                        <div>
                            <p style="font-size: 14px; font-weight: 700; margin-bottom: 2px;">پاداش دعوت</p>
                            <p style="font-size: 11px; color: var(--text-low);">امروز، ۱۲:۴۰</p>
                        </div>
                    </div>
                    <div style="text-align: left;">
                        <p style="font-size: 15px; font-weight: 900; color: #10b981;">+۵۰,۰۰۰</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نوار ناوبری داک -->
    <nav class="dock">
        <a href="#" class="dock-item active">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            <span>خانه</span>
        </a>
        <a href="#" class="dock-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            <span>آمار</span>
        </a>
        <a href="#" class="dock-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12z" /></svg>
            <span>جوایز</span>
        </a>
        <a href="#" class="dock-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            <span>پروفایل</span>
        </a>
    </nav>

    <script>
      // ادغام با محیط تلگرام
      if (window.Telegram && window.Telegram.WebApp) {
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();
        tg.setHeaderColor('#000000');
        tg.setBackgroundColor('#000000');
        
        // ایجاد لرزش ملایم هنگام کلیک (اگر دستگاه پشتیبانی کند)
        document.querySelectorAll('button, a').forEach(btn => {
          btn.addEventListener('click', () => {
            if (tg.HapticFeedback) tg.HapticFeedback.impactOccurred('light');
          });
        });
      }

      // حذف لودینگ پس از بارگذاری کامل
      window.addEventListener('load', () => {
        const loader = document.getElementById('mirza-preloader');
        setTimeout(() => {
            loader.style.transition = 'all 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
            loader.style.opacity = '0';
            loader.style.filter = 'blur(20px)';
            setTimeout(() => loader.remove(), 600);
        }, 1500);
      });
    </script>
  </body>
</html>