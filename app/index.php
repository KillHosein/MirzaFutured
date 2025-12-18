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
        /* Color Palette - Modern Deep Space */
        --primary-color: #3b82f6;
        --primary-glow: rgba(59, 130, 246, 0.5);
        --secondary-color: #8b5cf6;
        --accent-color: #06b6d4; /* Cyan */
        
        --bg-deep: #0f172a;
        --bg-surface: rgba(30, 41, 59, 0.7);
        --bg-glass: rgba(15, 23, 42, 0.6);
        
        --text-primary: #f8fafc;
        --text-secondary: #94a3b8;
        
        --border-light: rgba(255, 255, 255, 0.08);
        
        --font-family: 'Vazirmatn', 'Vazir', sans-serif;
        
        /* Telegram Overrides */
        --tg-theme-bg-color: #0f172a !important;
        --tg-theme-text-color: #f8fafc !important;
        --tg-theme-button-color: #3b82f6 !important;
        --tg-theme-button-text-color: #ffffff !important;
      }

      body {
        margin: 0;
        padding: 0;
        font-family: var(--font-family);
        background-color: var(--bg-deep);
        color: var(--text-primary);
        direction: rtl;
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
        padding-top: 70px; /* Header space */
        padding-bottom: 100px; /* Bottom bar space */
      }

      /* --- Animated Mesh Background --- */
      .mesh-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: -1;
        overflow: hidden;
        background: var(--bg-deep);
      }

      .mesh-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.4;
        animation: float 20s infinite alternate;
      }

      .blob-1 {
        top: -10%;
        left: -10%;
        width: 50vw;
        height: 50vw;
        background: var(--primary-color);
        animation-duration: 25s;
      }
      .blob-2 {
        bottom: -10%;
        right: -10%;
        width: 60vw;
        height: 60vw;
        background: var(--secondary-color);
        animation-duration: 30s;
        animation-delay: -5s;
      }
      .blob-3 {
        top: 40%;
        left: 40%;
        width: 40vw;
        height: 40vw;
        background: var(--accent-color);
        animation-duration: 20s;
        animation-delay: -10s;
        opacity: 0.2;
      }

      @keyframes float {
        0% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(30px, -50px) scale(1.1); }
        66% { transform: translate(-20px, 20px) scale(0.9); }
        100% { transform: translate(0, 0) scale(1); }
      }

      /* --- Glass Header --- */
      .glass-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 64px;
        background: var(--bg-glass);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid var(--border-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        z-index: 1000;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
      }

      .app-brand {
        display: flex;
        align-items: center;
        gap: 12px;
      }
      
      .brand-logo {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 15px var(--primary-glow);
      }
      
      .brand-title {
        font-size: 18px;
        font-weight: 700;
        background: linear-gradient(to right, #fff, #94a3b8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
      }

      .header-actions .btn-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-primary);
        border-radius: 12px;
      }

      /* --- Professional Buttons (Refined) --- */
      .btn {
        border: none;
        outline: none;
        cursor: pointer;
        font-family: var(--font-family);
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        user-select: none;
      }

      .btn:active { transform: scale(0.95); }

      .btn-nav {
        flex-direction: column;
        gap: 4px;
        background: transparent;
        color: var(--text-secondary);
        width: 64px;
        height: auto;
        padding: 4px 0;
        border-radius: 16px;
        font-size: 10px;
      }
      
      .btn-nav svg {
        width: 24px;
        height: 24px;
        stroke-width: 2px;
        transition: all 0.3s ease;
      }

      .btn-nav.active {
        color: var(--primary-color);
        background: rgba(59, 130, 246, 0.1);
      }
      
      .btn-nav.active svg {
        filter: drop-shadow(0 0 8px var(--primary-glow));
      }

      .btn-nav:hover {
        color: var(--text-primary);
      }

      /* --- Feature Buttons (Grid) --- */
      .btn-feature {
        flex-direction: column;
        gap: 8px;
        background: transparent;
        color: var(--text-secondary);
        width: 100%;
        height: auto;
        font-size: 11px;
        font-weight: 500;
      }

      .btn-feature .icon-box {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
      }
      
      .btn-feature .icon-box::before {
        content: '';
        position: absolute;
        inset: 0;
        background: currentColor;
        opacity: 0.1;
      }

      .btn-feature:hover .icon-box {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px -10px currentColor;
      }
      
      .btn-feature:active .icon-box {
        transform: scale(0.92);
      }

      /* --- Floating Bottom Bar --- */
      .bottom-nav {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(30, 41, 59, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--border-light);
        padding: 8px 16px;
        border-radius: 24px;
        display: flex;
        gap: 8px;
        z-index: 1000;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
      }

      /* --- Loading Screen --- */
      .loader-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--bg-deep);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        transition: opacity 0.5s ease;
      }
      
      .loader-logo {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border-radius: 20px;
        margin-bottom: 24px;
        animation: pulse 2s infinite;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 30px var(--primary-glow);
      }

      .loader-bar {
        width: 150px;
        height: 4px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
        overflow: hidden;
      }
      
      .loader-progress {
        width: 100%;
        height: 100%;
        background: var(--primary-color);
        transform: translateX(-100%);
        animation: loading 1.5s ease-in-out infinite;
      }

      @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 20px rgba(59, 130, 246, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
      }
      
      @keyframes loading {
        0% { transform: translateX(-100%); }
        50% { transform: translateX(0); }
        100% { transform: translateX(100%); }
      }

      /* --- Utility --- */
      .fade-out {
        opacity: 0;
        pointer-events: none;
      }
      
      #root {
        animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
        transform: translateY(20px);
        animation-delay: 0.2s;
      }

      @keyframes slideUp {
        to { opacity: 1; transform: translateY(0); }
      }
    </style>

    <script src="/app/js/telegram-web-app.js"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
  </head>
  <body>
    <!-- Animated Mesh Background -->
    <div class="mesh-background">
      <div class="mesh-blob blob-1"></div>
      <div class="mesh-blob blob-2"></div>
      <div class="mesh-blob blob-3"></div>
    </div>

    <!-- Glass Header -->
    <header class="glass-header">
      <div class="header-actions">
        <button class="btn btn-icon">
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </button>
      </div>
      
      <div class="app-brand">
        <span class="brand-title">میرزا پرو</span>
        <div class="brand-logo">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
      </div>
    </header>

    <div id="root">
        <div id="app-container">
            <!-- کارت موجودی اصلی -->
            <div class="vault-card stagger-item delay-2" style="background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid var(--border-light); padding: 24px; border-radius: 24px; margin: 20px;">
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">کل دارایی قابل برداشت</p>
                <div class="amount-large" style="font-size: 32px; font-weight: 800; color: var(--text-primary);">۱۵,۲۴۰,۰۰۰ <span style="font-size: 16px; color: var(--text-secondary); font-weight: 400;">تومان</span></div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 30px;">
                    <button class="btn" style="background: var(--primary-color); color: white; height: 48px; border-radius: 14px; gap: 8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                        افزایش
                    </button>
                    <button class="btn" style="background: rgba(255, 255, 255, 0.05); color: var(--text-primary); border: 1px solid var(--border-light); height: 48px; border-radius: 14px;">
                        تسویه
                    </button>
                </div>
            </div>

            <!-- بخش دکمه‌های ابزار (جدید) -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding: 0 20px; margin-bottom: 24px; justify-items: center;">
                <button class="btn btn-feature">
                    <div class="icon-box" style="color: var(--primary-color);">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    </div>
                    <span>تبدیل</span>
                </button>
                <button class="btn btn-feature">
                    <div class="icon-box" style="color: var(--secondary-color);">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <span>وام</span>
                </button>
                <button class="btn btn-feature">
                    <div class="icon-box" style="color: var(--accent-color);">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                    </div>
                    <span>قبوض</span>
                </button>
                <button class="btn btn-feature">
                    <div class="icon-box" style="color: var(--text-secondary);">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" /></svg>
                    </div>
                    <span>بیشتر</span>
                </button>
            </div>

            <!-- بخش لیست‌ها -->
            <div class="stagger-item delay-3" style="padding: 0 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-size: 18px; margin: 0; font-weight: 700;">فعالیت‌های اخیر</h3>
                    <button class="btn" style="color: var(--accent-color); font-size: 13px; font-weight: 700; background: none; padding: 0;">مشاهده همه</button>
                </div>

                <!-- نمونه تراکنش -->
                <div class="transaction-item" style="background: rgba(30, 41, 59, 0.4); border: 1px solid var(--border-light); padding: 16px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center; color: #10b981;">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
                        </div>
                        <div>
                            <p style="font-size: 14px; font-weight: 700; margin: 0 0 4px 0;">پاداش دعوت</p>
                            <p style="font-size: 11px; color: var(--text-secondary); margin: 0;">امروز، ۱۲:۴۰</p>
                        </div>
                    </div>
                    <div style="text-align: left;">
                        <p style="font-size: 15px; font-weight: 800; color: #10b981; margin: 0;">+۵۰,۰۰۰</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Loading Screen -->
    <div class="loader-container" id="appLoader">
      <div class="loader-logo">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="white">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
      </div>
      <div class="loader-bar">
        <div class="loader-progress"></div>
      </div>
    </div>

    <!-- Floating Bottom Navigation -->
    <nav class="bottom-nav">
      <button class="btn btn-nav active">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span>خانه</span>
      </button>
      
      <button class="btn btn-nav">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        </svg>
        <span>فروشگاه</span>
      </button>

      <button class="btn btn-nav">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>
        <span>کیف پول</span>
      </button>

      <button class="btn btn-nav">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span>پروفایل</span>
      </button>
    </nav>

    <script>
      // ادغام با محیط تلگرام
      if (window.Telegram && window.Telegram.WebApp) {
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();
        tg.setHeaderColor('#0f172a');
        tg.setBackgroundColor('#0f172a');
        
        // ایجاد لرزش ملایم هنگام کلیک (اگر دستگاه پشتیبانی کند)
        document.querySelectorAll('button, a').forEach(btn => {
          btn.addEventListener('click', () => {
            if (tg.HapticFeedback) tg.HapticFeedback.impactOccurred('light');
          });
        });
      }

      // Remove loader when app is ready (simulated)
      window.addEventListener('load', () => {
        setTimeout(() => {
          const loader = document.getElementById('appLoader');
          loader.classList.add('fade-out');
          setTimeout(() => {
            loader.style.display = 'none';
          }, 500);
        }, 1500); // Simulated delay
      });
    </script>
  </body>
</html>