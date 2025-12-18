
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
        /* Theme Variables from Panel */
        --primary-color: #3498db;
        --error-color: #e74c3c;
        --background-color: #f8f9fa;
        --card-bg: #ffffff;
        --text-color: #333333;
        --text-strong: #111827;
        --text-body: #374151;
        --text-muted: #6b7280;
        --btn-primary: #3b82f6;
        --font-family-sans: 'Vazirmatn', 'Vazir', sans-serif;
        
        /* Telegram Theme Overrides */
        --tg-theme-bg-color: var(--background-color);
        --tg-theme-text-color: var(--text-color);
        --tg-theme-button-color: var(--btn-primary);
        --tg-theme-button-text-color: #ffffff;
        --tg-theme-hint-color: var(--text-muted);
        --tg-theme-link-color: var(--primary-color);
        --tg-theme-secondary-bg-color: var(--card-bg);
        
        /* Additional Colors */
        --color-primary: var(--primary-color);
        --color-background: var(--background-color);
        --color-text: var(--text-color);
      }

      /* Dark Mode Support matching Panel */
      @media (prefers-color-scheme: dark) {
        :root {
          --background-color: #111827; /* Darker background for app feel */
          --card-bg: #1f2937;
          --text-color: #e5e7eb;
          --text-strong: #f9fafb;
          --text-body: #d1d5db;
          --text-muted: #9ca3af;
          --btn-primary: #60a5fa;
          
          --tg-theme-bg-color: var(--background-color);
          --tg-theme-text-color: var(--text-color);
          --tg-theme-button-color: var(--btn-primary);
          --tg-theme-secondary-bg-color: var(--card-bg);
        }
      }

      body {
        font-family: var(--font-family-sans) !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        background: linear-gradient(180deg, #f7f9fc 0%, #eef2f7 100%); /* Panel Gradient */
        color: var(--text-body);
        direction: rtl; /* Ensure RTL for Persian */
        overflow-x: hidden;
        margin: 0;
        padding: 0;
      }
      
      @media (prefers-color-scheme: dark) {
        body {
          background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
        }
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
        background: var(--text-muted);
        border-radius: 10px;
        opacity: 0.5;
      }
      
      /* Smooth Entry */
      #root {
        opacity: 0;
        animation: fadeIn 0.4s ease-out forwards;
      }

      @keyframes fadeIn {
        to { opacity: 1; }
      }

      /* Prevent text selection on UI elements for app-like feel */
      .no-select {
        user-select: none;
        -webkit-user-select: none;
      }
      
      /* Loading Screen Styles */
      .loading-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100vh;
        width: 100%;
        background: inherit;
        color: var(--text-strong);
      }
      .loading-spinner {
        animation: spin 1s linear infinite;
        height: 48px;
        width: 48px;
        color: var(--btn-primary);
        margin-bottom: 16px;
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
