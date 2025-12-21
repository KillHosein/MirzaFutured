<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#2AABEE" />
    <title>Mirza Web App</title>
    <link rel="preload" href="/app/fonts/Vazir-Medium.woff2" as="font" type="font/woff2" />
    <link rel="preload" href="/app/fonts/Vazir-Light.woff2" as="font" type="font/woff2" />
    <link rel="modulepreload" crossorigin href="/app/assets/vendor-CIGJ9g2q.js" />
    <link rel="stylesheet" crossorigin href="/app/assets/index-BoHBsj0Z.css" />
    <style>
      @font-face{font-family:Vazir;src:url("/app/fonts/Vazir-Light.woff2") format("woff2"),url("/app/fonts/Vazir-Light.woff") format("woff");font-weight:300;font-style:normal;font-display:swap}
      @font-face{font-family:Vazir;src:url("/app/fonts/Vazir-Medium.woff2") format("woff2"),url("/app/fonts/Vazir-Medium.woff") format("woff");font-weight:500;font-style:normal;font-display:swap}
      @font-face{font-family:Vazir;src:url("/app/fonts/Vazir-Bold.woff2") format("woff2"),url("/app/fonts/Vazir-Bold.woff") format("woff");font-weight:700;font-style:normal;font-display:swap}
      :root{--tg-theme-bg-color:#fff;--tg-theme-text-color:#111;--tg-theme-hint-color:#707579;--tg-theme-link-color:#2aabee;--tg-theme-button-color:#2aabee;--tg-theme-button-text-color:#fff;--tg-theme-secondary-bg-color:#f2f2f2;--tg-viewport-height:100vh;--tg-viewport-stable-height:100vh}
      html,body{height:100%}
      body{margin:0;font-family:Vazir,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--tg-theme-bg-color);color:var(--tg-theme-text-color);padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;transition:background-color .2s ease,color .2s ease}
      a{color:var(--tg-theme-link-color)}
      *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
      #root{min-height:var(--tg-viewport-stable-height)}
      :focus-visible{outline:2px solid var(--tg-theme-button-color);outline-offset:2px}
      @media (prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:0s !important;transition-duration:0s !important}}
    </style>
  </head>
  <body>
    <div id="root"></div>
    <script src="/app/js/telegram-web-app.js"></script>
    <script>
      (function () {
        var tg = window.Telegram && window.Telegram.WebApp;
        if (!tg) return;
        function setVar(key, value) {
          if (typeof value !== "string" || !value) return;
          document.documentElement.style.setProperty(key, value);
        }
        function applyTheme() {
          var tp = tg.themeParams || {};
          Object.keys(tp).forEach(function (k) {
            setVar("--tg-theme-" + k.replace(/_/g, "-"), tp[k]);
          });
          document.documentElement.dataset.tgColorScheme = tg.colorScheme || "";
          try {
            if (tp.bg_color) tg.setBackgroundColor(tp.bg_color);
          } catch (e) {}
          try {
            tg.setHeaderColor("bg_color");
          } catch (e) {}
          try {
            if (tp.bottom_bar_bg_color) tg.setBottomBarColor(tp.bottom_bar_bg_color);
          } catch (e) {}
        }
        function applyViewport() {
          setVar("--tg-viewport-height", (tg.viewportHeight || window.innerHeight) + "px");
          setVar("--tg-viewport-stable-height", (tg.viewportStableHeight || tg.viewportHeight || window.innerHeight) + "px");
        }
        try {
          tg.ready();
        } catch (e) {}
        try {
          tg.expand();
        } catch (e) {}
        applyTheme();
        applyViewport();
        try {
          tg.onEvent("themeChanged", function () {
            applyTheme();
          });
          tg.onEvent("viewportChanged", function () {
            applyViewport();
          });
        } catch (e) {}
      })();
    </script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
  </body>
</html>
