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
      body{margin:0;font-family:Vazir,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--tg-theme-bg-color);color:var(--tg-theme-text-color);padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;transition:background-color .2s ease,color .2s ease;overscroll-behavior-y:none}
      a{color:var(--tg-theme-link-color)}
      *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
      #root{min-height:var(--tg-viewport-stable-height)}
      :focus-visible{outline:2px solid var(--tg-theme-button-color);outline-offset:2px}
      #boot-loader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(180deg,color-mix(in oklab,var(--tg-theme-bg-color) 92%,#000 8%),var(--tg-theme-bg-color));z-index:2147483646;transition:opacity .25s ease,transform .25s ease}
      #boot-loader.boot-hidden{opacity:0;transform:scale(.985);pointer-events:none}
      #boot-card{display:flex;flex-direction:column;gap:14px;align-items:center;justify-content:center;padding:18px 16px;border-radius:16px;max-width:min(360px,calc(100vw - 28px));background:color-mix(in oklab,var(--tg-theme-secondary-bg-color) 72%,transparent);backdrop-filter:saturate(120%) blur(10px);box-shadow:0 10px 30px color-mix(in oklab,#000 18%,transparent);border:1px solid color-mix(in oklab,var(--tg-theme-hint-color) 18%,transparent)}
      #boot-title{font-weight:700;letter-spacing:-.3px;font-size:18px;line-height:1.2}
      #boot-sub{font-weight:500;font-size:13px;color:var(--tg-theme-hint-color);text-align:center}
      #boot-spinner{width:44px;height:44px;border-radius:999px;background:conic-gradient(from 90deg,var(--tg-theme-button-color),color-mix(in oklab,var(--tg-theme-button-color) 18%,transparent),var(--tg-theme-button-color));mask:radial-gradient(circle at center,transparent 55%,#000 57%);animation:bootspin .9s linear infinite}
      @keyframes bootspin{to{transform:rotate(360deg)}}
      #theme-toggle{position:fixed;left:12px;bottom:12px;z-index:2147483647;border:1px solid color-mix(in oklab,var(--tg-theme-hint-color) 22%,transparent);background:color-mix(in oklab,var(--tg-theme-secondary-bg-color) 82%,transparent);color:var(--tg-theme-text-color);border-radius:999px;padding:10px 12px;font:600 13px/1 Vazir,ui-sans-serif,system-ui;box-shadow:0 10px 24px color-mix(in oklab,#000 20%,transparent)}
      @media (prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:0s !important;transition-duration:0s !important}}
    </style>
  </head>
  <body>
    <div id="root"><div id="boot-loader"><div id="boot-card"><div id="boot-spinner"></div><div id="boot-title">Mirza Web App</div><div id="boot-sub">در حال آماده‌سازی…</div></div></div></div>
    <script src="/app/js/telegram-web-app.js"></script>
    <script>
      (function () {
        var tg = window.Telegram && window.Telegram.WebApp;
        var isTg = !!tg && typeof tg.ready === "function";
        var storageKey = "mirza_theme_mode";
        var mode = "telegram";

        function normalizePath() {
          var p = (location.pathname || "/").replace(/\/+$/, "");
          if (!p) return "/";
          return p;
        }

        function setVar(key, value) {
          if (typeof value !== "string" || !value) return;
          document.documentElement.style.setProperty(key, value);
        }

        function setMetaThemeColor(color) {
          if (typeof color !== "string" || !color) return;
          var meta = document.querySelector('meta[name="theme-color"]');
          if (meta) meta.setAttribute("content", color);
        }

        function getSavedMode() {
          try {
            var v = localStorage.getItem(storageKey);
            return v === "telegram" || v === "light" || v === "dark" ? v : null;
          } catch (e) {
            return null;
          }
        }

        function setSavedMode(nextMode) {
          try {
            localStorage.setItem(storageKey, nextMode);
          } catch (e) {}
        }

        var palettes = {
          light: {
            bg_color: "#ffffff",
            text_color: "#111827",
            hint_color: "#6b7280",
            link_color: "#2AABEE",
            button_color: "#2AABEE",
            button_text_color: "#ffffff",
            secondary_bg_color: "#f3f4f6"
          },
          dark: {
            bg_color: "#0b1220",
            text_color: "#e5e7eb",
            hint_color: "#94a3b8",
            link_color: "#7dd3fc",
            button_color: "#2AABEE",
            button_text_color: "#ffffff",
            secondary_bg_color: "#0f172a"
          }
        };

        function applyPalette(p, colorScheme) {
          Object.keys(p).forEach(function (k) {
            setVar("--tg-theme-" + k.replace(/_/g, "-"), p[k]);
          });
          document.documentElement.dataset.tgColorScheme = colorScheme || "";
          if (colorScheme) document.documentElement.style.colorScheme = colorScheme;
          setMetaThemeColor(p.bg_color);
          if (isTg) {
            try {
              tg.setBackgroundColor(p.bg_color);
            } catch (e) {}
            try {
              tg.setHeaderColor(p.bg_color);
            } catch (e) {}
          }
        }

        function applyThemeFromTelegram() {
          if (!isTg) return false;
          var tp = tg.themeParams || {};
          Object.keys(tp).forEach(function (k) {
            setVar("--tg-theme-" + k.replace(/_/g, "-"), tp[k]);
          });
          document.documentElement.dataset.tgColorScheme = tg.colorScheme || "";
          if (tg.colorScheme) document.documentElement.style.colorScheme = tg.colorScheme;
          if (tp.bg_color) setMetaThemeColor(tp.bg_color);
          try {
            if (tp.bg_color) tg.setBackgroundColor(tp.bg_color);
          } catch (e) {}
          try {
            tg.setHeaderColor("bg_color");
          } catch (e) {}
          try {
            if (tp.bottom_bar_bg_color) tg.setBottomBarColor(tp.bottom_bar_bg_color);
          } catch (e) {}
          return true;
        }

        function applyTheme() {
          if (isTg && mode === "telegram") {
            return applyThemeFromTelegram();
          }
          if (mode === "dark") return applyPalette(palettes.dark, "dark");
          if (mode === "light") return applyPalette(palettes.light, "light");
          var prefersDark = false;
          try {
            prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
          } catch (e) {}
          return applyPalette(prefersDark ? palettes.dark : palettes.light, prefersDark ? "dark" : "light");
        }

        function applyViewport() {
          if (isTg) {
            setVar("--tg-viewport-height", (tg.viewportHeight || window.innerHeight) + "px");
            setVar("--tg-viewport-stable-height", (tg.viewportStableHeight || tg.viewportHeight || window.innerHeight) + "px");
          } else {
            setVar("--tg-viewport-height", window.innerHeight + "px");
            setVar("--tg-viewport-stable-height", window.innerHeight + "px");
          }
        }

        function patchHistory() {
          var origPush = history.pushState;
          var origReplace = history.replaceState;
          if (origPush._mirzaPatched) return;
          history.pushState = function () {
            var r = origPush.apply(this, arguments);
            window.dispatchEvent(new Event("locationchange"));
            return r;
          };
          history.replaceState = function () {
            var r = origReplace.apply(this, arguments);
            window.dispatchEvent(new Event("locationchange"));
            return r;
          };
          history.pushState._mirzaPatched = true;
          window.addEventListener("popstate", function () {
            window.dispatchEvent(new Event("locationchange"));
          });
        }

        function updateBackButton() {
          if (!isTg || !tg.BackButton) return;
          var p = normalizePath();
          var isRoot = p === "/" || p === "/app" || p === "/app/index.php";
          try {
            if (isRoot) tg.BackButton.hide();
            else tg.BackButton.show();
          } catch (e) {}
        }

        function setupBackButton() {
          if (!isTg || !tg.BackButton) return;
          try {
            tg.BackButton.onClick(function () {
              try {
                tg.HapticFeedback && tg.HapticFeedback.selectionChanged && tg.HapticFeedback.selectionChanged();
              } catch (e) {}
              history.back();
            });
          } catch (e) {}
        }

        function setupHaptics() {
          if (!isTg || !tg.HapticFeedback) return;
          var last = 0;
          document.addEventListener(
            "click",
            function (e) {
              var now = Date.now();
              if (now - last < 80) return;
              last = now;
              var el = e.target;
              while (el && el !== document.documentElement) {
                var tag = el.tagName;
                if (tag === "BUTTON" || tag === "A" || el.getAttribute("role") === "button") {
                  try {
                    tg.HapticFeedback.impactOccurred("light");
                  } catch (e2) {}
                  return;
                }
                el = el.parentElement;
              }
            },
            { capture: true, passive: true }
          );
        }

        function setupSettingsButton() {
          if (!isTg || !tg.SettingsButton || typeof tg.showPopup !== "function") return;
          try {
            tg.SettingsButton.show();
          } catch (e) {}
          try {
            tg.SettingsButton.onClick(function () {
              try {
                tg.HapticFeedback && tg.HapticFeedback.selectionChanged && tg.HapticFeedback.selectionChanged();
              } catch (e2) {}
              tg.showPopup(
                {
                  title: "تنظیمات",
                  message: "انتخاب تم",
                  buttons: [
                    { id: "telegram", type: "default", text: "تم تلگرام" },
                    { id: "light", type: "default", text: "روشن" },
                    { id: "dark", type: "default", text: "تاریک" },
                    { type: "cancel" }
                  ]
                },
                function (buttonId) {
                  if (!buttonId) return;
                  if (buttonId !== "telegram" && buttonId !== "light" && buttonId !== "dark") return;
                  mode = buttonId;
                  setSavedMode(mode);
                  applyTheme();
                }
              );
            });
          } catch (e) {}
        }

        function setupBrowserThemeToggle() {
          if (isTg) return;
          var btn = document.createElement("button");
          btn.id = "theme-toggle";
          btn.type = "button";
          function label() {
            return mode === "dark" ? "تم: تاریک" : mode === "light" ? "تم: روشن" : "تم: خودکار";
          }
          btn.textContent = label();
          btn.addEventListener("click", function () {
            mode = mode === "light" ? "dark" : mode === "dark" ? "auto" : "light";
            btn.textContent = label();
            applyTheme();
          });
          document.body.appendChild(btn);
        }

        function hideBootLoader() {
          var loader = document.getElementById("boot-loader");
          if (!loader) return;
          loader.classList.add("boot-hidden");
          setTimeout(function () {
            if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
          }, 280);
        }

        function start() {
          mode = getSavedMode() || (isTg ? "telegram" : "auto");
          if (isTg) {
            try {
              tg.ready();
            } catch (e) {}
            try {
              tg.expand();
            } catch (e) {}
          }

          applyTheme();
          applyViewport();

          if (isTg) {
            try {
              tg.onEvent("themeChanged", function () {
                if (mode === "telegram") applyTheme();
              });
            } catch (e) {}
            try {
              tg.onEvent("viewportChanged", function () {
                applyViewport();
              });
            } catch (e) {}
          }

          patchHistory();
          window.addEventListener("locationchange", updateBackButton);
          updateBackButton();
          setupBackButton();
          setupHaptics();
          setupSettingsButton();
          setupBrowserThemeToggle();

          if (document.readyState === "complete") hideBootLoader();
          else window.addEventListener("load", hideBootLoader, { once: true });
        }

        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start, { once: true });
        else start();
      })();
    </script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
  </body>
</html>
