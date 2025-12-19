
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mirza Web App</title>
    <script src="/app/js/telegram-web-app.js"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
    <style>
      /* Custom Beautification for Mirza Web App */
      
      /* Import Vazir Font if not already loaded correctly */
      @font-face {
        font-family: 'Vazir';
        src: url('/app/fonts/Vazir-Medium.woff2') format('woff2'),
             url('/app/fonts/Vazir-Medium.woff') format('woff');
        font-weight: 500;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'Vazir';
        src: url('/app/fonts/Vazir-Bold.woff2') format('woff2'),
             url('/app/fonts/Vazir-Bold.woff') format('woff');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'Vazir';
        src: url('/app/fonts/Vazir-Light.woff2') format('woff2'),
             url('/app/fonts/Vazir-Light.woff') format('woff');
        font-weight: 300;
        font-style: normal;
        font-display: swap;
      }

      :root {
        --tg-theme-bg-color: #ffffff;
        --tg-theme-text-color: #000000;
        --tg-theme-hint-color: #999999;
        --tg-theme-link-color: #2481cc;
        --tg-theme-button-color: #3390ec;
        --tg-theme-button-text-color: #ffffff;
        --tg-theme-secondary-bg-color: #f4f4f5;
      }

      body {
        font-family: 'Vazir', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
        background-color: var(--tg-theme-bg-color);
        color: var(--tg-theme-text-color);
        transition: background-color 0.3s ease, color 0.3s ease;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        overflow-x: hidden; /* Prevent horizontal scroll */
      }

      /* Subtle Background Pattern/Gradient */
      body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle at 50% 0%, 
          color-mix(in srgb, var(--tg-theme-button-color), transparent 92%) 0%, 
          transparent 70%);
        pointer-events: none;
        z-index: -1;
      }

      /* Smooth Scrolling & Custom Scrollbar */
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-track {
        background: transparent;
      }
      ::-webkit-scrollbar-thumb {
        background-color: var(--tg-theme-hint-color);
        border-radius: 10px;
        border: 2px solid transparent;
        background-clip: content-box;
      }
      ::-webkit-scrollbar-thumb:hover {
        background-color: var(--tg-theme-text-color);
      }

      /* Element Enhancements */
      #root {
        animation: pageFadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
      }

      /* Button Enhancements (Targeting common Tailwind classes or generic button) */
      button {
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
      }
      button:active {
        transform: scale(0.96);
        opacity: 0.9;
      }

      /* Card/Container Enhancements (Generic) */
      .bg-white, .bg-gray-50, .dark .bg-gray-800 {
        transition: background-color 0.3s ease, box-shadow 0.3s ease;
      }

      /* Input Enhancements */
      input, select, textarea {
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
      }
      input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--tg-theme-button-color) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--tg-theme-button-color), transparent 85%) !important;
      }

      @keyframes pageFadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
      }

      /* Selection Color */
      ::selection {
        background-color: color-mix(in srgb, var(--tg-theme-button-color), transparent 70%);
        color: var(--tg-theme-text-color);
      }
    </style>
  </head>
  <body>
    <div id="root"></div>
    <script>
      // Initialize Telegram Web App features
      if (window.Telegram && window.Telegram.WebApp) {
        var webApp = window.Telegram.WebApp;
        
        // Expand the app to full height
        webApp.expand();

        // Set colors to match the theme
        // 'bg_color' and 'secondary_bg_color' are standard keys
        webApp.setHeaderColor('secondary_bg_color'); 
        webApp.setBackgroundColor('bg_color');

        // Ensure the app is ready
        webApp.ready();
        
        // Listen for theme changes to update our custom styles if needed
        // (CSS variables handle most of it automatically)
      }
    </script>
  </body>
</html>
