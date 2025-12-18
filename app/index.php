
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
        --secondary-color: #8b5cf6; /* Violet */
        --background-color: #0f172a; /* Deep Slate */
        --surface-color: #1e293b; /* Slate 800 */
        --surface-hover: #334155;
        --text-color: #f1f5f9; /* Slate 100 */
        --text-secondary: #94a3b8; /* Slate 400 */
        --border-color: #334155;
        --success-color: #10b981;
        --danger-color: #ef4444;
        
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
      }

      /* Enhanced Buttons (More "Button-like") */
      button, .btn {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
        border-radius: var(--btn-radius);
        padding: 12px 20px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }
      
      button:active, .btn:active {
        transform: scale(0.96);
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
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
        <style>
          @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        </style>
      </div>
    </div>
  </body>
</html>
