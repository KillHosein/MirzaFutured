
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mirza Web App</title>
    <script src="/app/js/telegram-web-app.js?v=2"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js?v=2"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js?v=2">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css?v=2">
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

      /* Mobile & Pro UX Optimizations */
      * {
        -webkit-tap-highlight-color: transparent;
        box-sizing: border-box;
      }
      
      html {
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
      }

      /* Animated Mesh Gradient Background */
      body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
          radial-gradient(at 0% 0%, color-mix(in srgb, var(--tg-theme-button-color), transparent 90%) 0px, transparent 50%),
          radial-gradient(at 100% 0%, color-mix(in srgb, var(--tg-theme-button-color), transparent 95%) 0px, transparent 50%),
          radial-gradient(at 100% 100%, color-mix(in srgb, var(--tg-theme-secondary-bg-color), transparent 90%) 0px, transparent 50%),
          radial-gradient(at 0% 100%, color-mix(in srgb, var(--tg-theme-button-color), transparent 95%) 0px, transparent 50%);
        background-size: 200% 200%;
        animation: meshGradient 15s ease infinite;
        pointer-events: none;
        z-index: -1;
      }
      @keyframes meshGradient {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
      }

      /* Modern Typography & Persian Features */
      body {
        font-family: 'Vazir', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
        background-color: var(--tg-theme-bg-color);
        color: var(--tg-theme-text-color);
        transition: background-color 0.3s ease, color 0.3s ease;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        overflow-x: hidden;
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
        letter-spacing: -0.02em;
        line-height: 1.7;
        font-feature-settings: "ss01", "ss02"; /* Stylistic sets for Persian if available */
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

      /* Button Enhancements */
      button {
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative;
        overflow: hidden;
      }
      button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px color-mix(in srgb, var(--tg-theme-button-color), transparent 70%);
      }
      button:active {
        transform: scale(0.96) translateY(0);
        opacity: 0.9;
        box-shadow: none;
      }
      /* Ripple effect (optional, simple CSS version) */
      button::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.3) 10%, transparent 10.01%);
        transform: translate(-50%, -50%) scale(0);
        transition: transform 0.5s, opacity 0.5s;
        opacity: 0;
        pointer-events: none;
      }
      button:active::after {
        transform: translate(-50%, -50%) scale(4);
        opacity: 1;
        transition: 0s;
      }

      /* Card/Container Enhancements (Generic) */
      .bg-white, .bg-gray-50, .dark .bg-gray-800 {
        transition: background-color 0.3s ease, box-shadow 0.3s ease;
      }

      /* Input Enhancements */
      input, select, textarea {
        font-family: inherit;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid color-mix(in srgb, var(--tg-theme-text-color), transparent 85%);
      }
      input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--tg-theme-button-color) !important;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--tg-theme-button-color), transparent 90%) !important;
        transform: translateY(-1px);
      }

      /* Entrance Animation */
      #root > * {
        animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      }
      @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
      }

      /* Premium Double-Ring Spinner */
      .spinner {
        width: 56px;
        height: 56px;
        position: relative;
        margin-bottom: 24px;
      }
      .spinner::before, .spinner::after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 3.5px solid transparent;
        border-top-color: var(--tg-theme-button-color);
      }
      .spinner::before {
        z-index: 100;
        animation: spin 1s infinite;
      }
      .spinner::after {
        border: 3.5px solid color-mix(in srgb, var(--tg-theme-button-color), transparent 85%);
      }

      /* Branded Loader Text */
      .loader-text {
        font-family: 'Vazir', sans-serif;
        font-weight: 700;
        font-size: 18px;
        color: var(--tg-theme-text-color);
        background: linear-gradient(90deg, 
          var(--tg-theme-text-color) 0%, 
          var(--tg-theme-button-color) 50%, 
          var(--tg-theme-text-color) 100%);
        background-size: 200% auto;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: shimmer 3s linear infinite;
      }
      @keyframes shimmer {
        to { background-position: 200% center; }
      }

      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      /* Glassmorphism Utilities */
      .glass-effect {
        background: color-mix(in srgb, var(--tg-theme-bg-color), transparent 20%);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid color-mix(in srgb, var(--tg-theme-text-color), transparent 95%);
      }
    </style>
  </head>
  <body>
    <!-- Initial Loading Screen -->
    <div id="app-loader" class="app-loader" style="z-index: 2147483647 !important; flex-direction: column;">
      <div class="spinner"></div>
      <div class="loader-text">Mirza Web App</div>
      <div style="position: absolute; bottom: 30px; font-size: 11px; opacity: 0.4; letter-spacing: 1px;">POWERED BY MIRZA</div>
    </div>

    <div id="root"></div>
    <script>
      // Remove loader when app is ready (simulated or actual)
      window.addEventListener('load', () => {
        // Small delay to ensure smooth transition
        setTimeout(() => {
          const loader = document.getElementById('app-loader');
          if (loader) loader.classList.add('hidden');
        }, 300);
      });

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
