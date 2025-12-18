<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    <title>Mirza Premium App</title>
    
    <!-- Professional Typography -->
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet" />

    <style>
      :root {
        /* Ultra Dark Luxury Palette */
        --bg-pure: #000000;
        --bg-mesh: radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%), 
                   radial-gradient(at 100% 100%, rgba(168, 85, 247, 0.1) 0px, transparent 50%);
        --card-bg: rgba(23, 23, 26, 0.7);
        --card-border: rgba(255, 255, 255, 0.06);
        
        --primary: #6366f1;
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        --primary-soft: rgba(99, 102, 241, 0.1);
        
        --text-main: #f4f4f5;
        --text-muted: #71717a;
        --text-dim: #52525b;
        
        --safe-area-bottom: env(safe-area-inset-bottom);
        --radius-premium: 24px;
        --transition-smooth: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
      }

      * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
        outline: none;
      }

      body {
        font-family: 'Vazirmatn', sans-serif;
        background-color: var(--bg-pure);
        background-image: var(--bg-mesh);
        color: var(--text-main);
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
        min-height: 100vh;
      }

      /* Container for Smooth Entrance */
      #root {
        padding: 16px;
        padding-bottom: calc(100px + var(--safe-area-bottom));
        opacity: 0;
        animation: entrance 0.8s ease forwards;
      }

      @keyframes entrance {
        from { opacity: 0; transform: translateY(15px); filter: blur(10px); }
        to { opacity: 1; transform: translateY(0); filter: blur(0); }
      }

      /* Premium Card Style */
      .card {
        background: var(--card-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-premium);
        padding: 24px;
        margin-bottom: 16px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
      }

      /* Button System: High-End */
      .btn-premium {
        position: relative;
        background: var(--primary-gradient);
        color: #fff;
        border: none;
        padding: 16px 28px;
        border-radius: 18px;
        font-size: 16px;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        transition: var(--transition-smooth);
        width: 100%;
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
      }

      .btn-premium:active {
        transform: scale(0.96);
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
      }

      .btn-secondary {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--card-border);
        color: var(--text-main);
        padding: 14px;
        border-radius: 18px;
        font-weight: 600;
        transition: var(--transition-smooth);
      }

      .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.08);
      }

      /* Floating Bottom Navigation (Island Style) */
      .nav-island {
        position: fixed;
        bottom: calc(20px + var(--safe-area-bottom));
        left: 20px;
        right: 20px;
        height: 72px;
        background: rgba(15, 15, 18, 0.85);
        backdrop-filter: blur(25px);
        -webkit-backdrop-filter: blur(25px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 35px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        z-index: 1000;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
      }

      .nav-link {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: var(--text-muted);
        transition: var(--transition-smooth);
        padding: 8px 12px;
      }

      .nav-link svg {
        width: 24px;
        height: 24px;
        margin-bottom: 4px;
        transition: var(--transition-smooth);
      }

      .nav-link span {
        font-size: 10px;
        font-weight: 600;
        opacity: 0.8;
      }

      .nav-link.active {
        color: var(--primary);
      }

      .nav-link.active svg {
        transform: translateY(-4px);
        filter: drop-shadow(0 0 8px var(--primary));
      }

      /* Professional Skeleton/Loader */
      .loader-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
        background: var(--bg-pure);
      }

      .premium-spinner {
        width: 60px;
        height: 60px;
        position: relative;
      }

      .premium-spinner::before, .premium-spinner::after {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        border-radius: 50%;
        border: 2px solid transparent;
        border-top-color: var(--primary);
        animation: spin 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
      }

      .premium-spinner::after {
        border-top-color: #a855f7;
        animation-delay: 0.3s;
      }

      @keyframes spin { to { transform: rotate(360deg); } }

      /* Visual Polish for Content */
      .balance-pill {
        background: var(--primary-soft);
        color: var(--primary);
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 12px;
        font-weight: 700;
        display: inline-block;
        margin-bottom: 8px;
      }

      h1, h2 {
        margin: 0 0 8px 0;
        font-weight: 900;
        letter-spacing: -0.5px;
      }

      p {
        color: var(--text-muted);
        font-size: 14px;
        margin: 0;
      }

      /* Hide Scrollbar */
      ::-webkit-scrollbar { width: 0; }
    </style>

    <script src="/app/js/telegram-web-app.js"></script>
    <!-- Keep your original assets -->
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
  </head>
  <body>
    <!-- Main Content Root -->
    <div id="root">
      
      <!-- Premium App Header Demo -->
      <div style="padding: 10px 4px 20px 4px; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <span class="balance-pill">سطح VIP</span>
          <h1 style="font-size: 24px;">سلام، کاربر گرامی</h1>
          <p>به نسخه پریمیوم میرزا خوش آمدید</p>
        </div>
        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--primary-gradient); border: 3px solid var(--card-border); display: flex; align-items: center; justify-content: center; font-weight: bold;">M</div>
      </div>

      <!-- Demo Balance Card -->
      <div class="card">
        <p style="text-align: center; margin-bottom: 5px;">موجودی کل</p>
        <h2 style="text-align: center; font-size: 32px; font-weight: 900; color: #fff;">۱۲,۵۰۰,۰۰۰ <span style="font-size: 14px; color: var(--text-muted);">تومان</span></h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px;">
          <button class="btn-premium">افزایش</button>
          <button class="btn-secondary" style="border-radius: 18px; font-family: 'Vazirmatn';">برداشت</button>
        </div>
      </div>

      <!-- Action Items Demo -->
      <div class="card" style="display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: 14px; color: #10b981;">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 15px;">گزارش تراکنش‌ها</h4>
                <p style="font-size: 12px;">بررسی ورودی و خروجی</p>
            </div>
        </div>
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--text-dim)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      </div>

      <!-- App Loading State (Displayed until JS initialization) -->
      <div id="initial-loader" class="loader-container" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 2000;">
        <div class="premium-spinner"></div>
        <div style="margin-top: 25px; color: var(--text-muted); font-size: 14px; letter-spacing: 1px; font-weight: 600;">MIRZA PREMIUM</div>
      </div>

    </div>

    <!-- Navigation Island -->
    <nav class="nav-island">
      <a href="#" class="nav-link active">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
        <span>ویترین</span>
      </a>
      <a href="#" class="nav-link">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span>مالی</span>
      </a>
      <a href="#" class="nav-link">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        <span>تیم ما</span>
      </a>
      <a href="#" class="nav-link">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><circle cx="12" cy="12" r="3" stroke-width="2" /></svg>
        <span>تنظیمات</span>
      </a>
    </nav>

    <script>
      // Handling Telegram WebApp Theme Integration
      if (window.Telegram && window.Telegram.WebApp) {
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();
        tg.setHeaderColor('#000000');
        tg.setBackgroundColor('#000000');
      }

      // Hide initial loader after some delay or when asset loads
      window.addEventListener('load', () => {
        setTimeout(() => {
          const loader = document.getElementById('initial-loader');
          if(loader) {
            loader.style.transition = 'opacity 0.5s ease';
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 500);
          }
        }, 1200);
      });
    </script>
  </body>
</html>