
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Mirza Web App</title>
    
    <!-- Vazirmatn Font (Professional Persian Font) -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />

    <style>
      :root {
        --font-family-sans: 'Vazirmatn', sans-serif;
        --color-primary: #3b82f6;
      }

      body {
        font-family: var(--font-family-sans) !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        background-color: var(--tg-theme-bg-color, #fff);
        color: var(--tg-theme-text-color, #000);
        overflow-x: hidden;
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
        background: var(--tg-theme-hint-color, rgba(0, 0, 0, 0.2));
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

      /* Prevent text selection on UI elements for app-like feel */
      .no-select {
        user-select: none;
        -webkit-user-select: none;
      }
    </style>

    <script src="/app/js/telegram-web-app.js"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
  </head>
  <body>
    <div id="root">
      <!-- Initial Loading State -->
      <div style="display:flex;flex-direction:column;justify-content:center;align-items:center;height:100vh;width:100%;background-color:var(--tg-theme-bg-color, #fff);color:var(--tg-theme-text-color, #000);">
        <svg style="animation:spin 1s linear infinite;height:48px;width:48px;color:var(--tg-theme-button-color, #3b82f6);margin-bottom:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span style="font-family:'Vazirmatn',sans-serif;font-size:14px;opacity:0.7;">در حال بارگذاری...</span>
        <style>
          @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        </style>
      </div>
    </div>
  </body>
</html>
