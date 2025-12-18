
<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Mirza Web App</title>
    
    <!-- Telegram Web App -->
    <script src="/app/static/js/telegram-web-app.js"></script>
    
    <!-- Core Application -->
    <script type="module" crossorigin src="/app/static/js/main.js"></script>
    <link rel="modulepreload" crossorigin href="/app/static/js/vendor.js">
    
    <!-- Styles -->
    <link rel="stylesheet" crossorigin href="/app/static/css/style.css">
    <link rel="stylesheet" href="/app/static/css/theme.css">
    
    <style>
      /* Initial Loader Styles */
      #initial-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: var(--background, #ffffff);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.5s ease-out;
      }
      .loader-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid rgba(0, 0, 0, 0.1);
        border-left-color: #6366f1; /* Primary Color */
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }
      .dark #initial-loader {
        background-color: #0f172a;
      }
      .dark .loader-spinner {
        border-color: rgba(255, 255, 255, 0.1);
        border-left-color: #818cf8;
      }
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
    </style>
  </head>
  <body>
    <!-- Preloader -->
    <div id="initial-loader">
      <div class="loader-spinner"></div>
    </div>

    <!-- App Root -->
    <div id="root"></div>
    
    <!-- Theme Manager -->
    <script src="/app/static/js/theme-loader.js"></script>
    
    <script>
      // Remove loader when app is ready (approximate)
      window.addEventListener('load', () => {
        const loader = document.getElementById('initial-loader');
        setTimeout(() => {
          loader.style.opacity = '0';
          setTimeout(() => {
            loader.remove();
          }, 500);
        }, 300);
      });
    </script>
  </body>
</html>
