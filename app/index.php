
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Mirza Web App</title>
    
    <!-- Vazirmatn Font (Professional Persian Font) -->
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet" type="text/css" />

    <style>
      :root {
        /* Forced Dark Theme (Professional & Sleek) */
        --primary-color: #3b82f6; /* Vivid Blue */
        --primary-hover: #2563eb;
        --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        --secondary-color: #64748b; /* Slate 500 */
        --secondary-hover: #475569;
        --background-color: #0f172a; /* Deep Slate */
        --surface-color: #1e293b; /* Slate 800 */
        --surface-hover: #334155;
        --text-color: #f8fafc; /* Slate 50 */
        --text-secondary: #94a3b8; /* Slate 400 */
        --border-color: #334155;
        --success-color: #10b981;
        --danger-color: #ef4444;
        --warning-color: #f59e0b;
        
        --font-family-sans: 'Vazirmatn', 'Vazir', sans-serif;
        
        /* Telegram Theme Overrides - FORCE DARK */
        --tg-theme-bg-color: var(--background-color) !important;
        --tg-theme-text-color: var(--text-color) !important;
        --tg-theme-button-color: var(--primary-color) !important;
        --tg-theme-button-text-color: #ffffff !important;
        --tg-theme-hint-color: var(--text-secondary) !important;
        --tg-theme-link-color: var(--primary-color) !important;
        --tg-theme-secondary-bg-color: var(--surface-color) !important;
        --tg-theme-header-bg-color: var(--background-color) !important;
        
        /* App Specific */
        --color-primary: var(--primary-color);
        --color-background: var(--background-color);
        --color-text: var(--text-color);
        
        /* UI Dimensions */
        --btn-radius: 12px;
        --card-radius: 16px;
      }

      body {
        font-family: var(--font-family-sans) !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        background-color: var(--background-color) !important;
        color: var(--text-color) !important;
        direction: rtl;
        overflow-x: hidden;
        margin: 0;
        padding: 0;
        padding-bottom: 80px; /* Space for the floating bar */
      }

      /* =========================================
         PROFESSIONAL BUTTON SYSTEM
         ========================================= */
      
      /* Base Button Style */
      button, .btn {
        font-family: var(--font-family-sans);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        font-size: 14px;
        font-weight: 600;
        border-radius: var(--btn-radius);
        border: none;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
        outline: none;
        letter-spacing: 0.5px;
      }

      /* Active/Click Effect */
      button:active, .btn:active {
        transform: scale(0.96);
      }

      /* Focus Ring */
      button:focus-visible, .btn:focus-visible {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
      }

      /* --- Variants --- */

      /* Primary Button */
      .btn-primary {
        background: var(--primary-gradient);
        color: white;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
      }
      .btn-primary:hover {
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        filter: brightness(1.1);
        transform: translateY(-1px);
      }
      .btn-primary:active {
        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.3);
      }

      /* Secondary Button */
      .btn-secondary {
        background: var(--surface-hover);
        color: var(--text-color);
        border: 1px solid var(--border-color);
      }
      .btn-secondary:hover {
        background: #475569;
        border-color: #64748b;
      }

      /* Success Button */
      .btn-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
      }
      .btn-success:hover {
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
        filter: brightness(1.1);
        transform: translateY(-1px);
      }

      /* Danger Button */
      .btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
      }
      .btn-danger:hover {
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
        filter: brightness(1.1);
        transform: translateY(-1px);
      }

      /* Ghost/Text Button */
      .btn-ghost {
        background: transparent;
        color: var(--text-secondary);
        box-shadow: none;
        padding: 8px 16px;
      }
      .btn-ghost:hover {
        color: var(--primary-color);
        background: rgba(59, 130, 246, 0.1);
      }

      /* Icon Button */
      .btn-icon {
        padding: 12px;
        border-radius: 50%;
        width: 48px;
        height: 48px;
      }

      /* Disabled State */
      button:disabled, .btn:disabled, .btn.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        filter: grayscale(1);
        transform: none !important;
        box-shadow: none !important;
      }

      /* Loading State */
      .btn-loading {
        position: relative;
        color: transparent !important;
        pointer-events: none;
      }
      .btn-loading::after {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-top: -8px;
        margin-left: -8px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
      }

      /* --- Floating Action Bar (Demo of "More Buttons") --- */
      .floating-bar {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(30, 41, 59, 0.8); /* Glassmorphism */
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 10px 16px;
        border-radius: 24px;
        display: flex;
        gap: 12px;
        z-index: 9999;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        width: max-content;
        max-width: 90%;
        overflow-x: auto;
      }

      /* Enhanced Cards */
      .card, .box {
        background: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: var(--card-radius);
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        padding: 20px;
        margin-bottom: 16px;
      }

      /* Enhanced Inputs */
      input, select, textarea {
        background-color: var(--surface-hover) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-color) !important;
        border-radius: var(--btn-radius) !important;
        padding: 12px 16px !important;
        transition: all 0.2s ease;
        font-family: var(--font-family-sans) !important;
      }
      
      input:focus, select:focus, textarea:focus {
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
        outline: none;
      }

      /* Professional Scrollbar */
      ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
      }
      ::-webkit-scrollbar-track {
        background: transparent;
      }
      ::-webkit-scrollbar-thumb {
        background: var(--surface-hover);
        border-radius: 10px;
      }
      
      /* Smooth Entry */
      #root {
        opacity: 0;
        animation: fadeIn 0.4s ease-out forwards;
      }

      @keyframes fadeIn {
        to { opacity: 1; }
      }

      /* Prevent text selection */
      .no-select {
        user-select: none;
        -webkit-user-select: none;
      }
      
      /* Loading Screen */
      .loading-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
        width: 100%;
        background: var(--background-color);
        color: var(--text-color);
      }
      .loading-spinner {
        animation: spin 1s linear infinite;
        height: 48px;
        width: 48px;
        color: var(--primary-color);
        margin-bottom: 16px;
        filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
      }
      
      @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>

    <script src="/app/js/telegram-web-app.js"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
  </head>
  <body>
    <div id="root">
      <!-- Initial Loading State matching Panel Theme -->
      <div class="loading-container">
        <svg class="loading-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span style="font-family:'Vazirmatn',sans-serif;font-size:14px;opacity:0.8;font-weight:500;">در حال بارگذاری...</span>
      </div>
    </div>

    <!-- Floating Action Bar (Demo of New Buttons) -->
    <div class="floating-bar">
      <button class="btn btn-primary btn-icon" aria-label="Home">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
      </button>
      
      <button class="btn btn-secondary btn-icon" aria-label="Settings">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </button>

      <button class="btn btn-success">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <span>پشتیبانی</span>
      </button>

      <button class="btn btn-danger btn-icon" aria-label="Exit">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </button>
    </div>
  </body>
</html>
