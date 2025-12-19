
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Mirza Web App</title>
    <script src="/app/js/telegram-web-app.js"></script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css">
    <link rel="stylesheet" href="/app/assets/custom.css?v=<?php echo time(); ?>">
    <style>
      /* Critical CSS for Preloader */
      #app-preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: #0B0E14; /* Matches deep navy theme */
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
      }
      .spinner {
        width: 50px;
        height: 50px;
        border: 3px solid rgba(59, 130, 246, 0.3);
        border-radius: 50%;
        border-top-color: #3b82f6;
        animation: spin 1s ease-in-out infinite;
      }
      @keyframes spin {
        to { transform: rotate(360deg); }
      }
      .loading-text {
        margin-top: 20px;
        color: #3b82f6;
        font-family: sans-serif; /* Fallback until Vazir loads */
        font-size: 0.9rem;
        letter-spacing: 2px;
        font-weight: 600;
        animation: pulse 1.5s infinite;
      }
      @keyframes pulse {
        0%, 100% { opacity: 0.6; }
        50% { opacity: 1; }
      }
      .hidden-preloader {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
      }
    </style>
  </head>
  <body>
    <div id="app-preloader">
      <div class="spinner"></div>
      <div class="loading-text">MIRZA PRO</div>
    </div>
    <div id="root"></div>
    <script>
      // Remove preloader when window loads or after a max timeout
      window.addEventListener('load', () => {
        setTimeout(() => {
          const preloader = document.getElementById('app-preloader');
          if (preloader) preloader.classList.add('hidden-preloader');
        }, 800); // Small delay for smoothness
      });
      // Fallback
      setTimeout(() => {
        const preloader = document.getElementById('app-preloader');
        if (preloader && !preloader.classList.contains('hidden-preloader')) {
          preloader.classList.add('hidden-preloader');
        }
      }, 3000);
    </script>
  </body>
</html>
