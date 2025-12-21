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
      /* Responsive Advanced Buttons */
      @media (max-width:480px){
        #btn-demo{bottom:70px;right:8px;left:8px;max-width:none}
        .btn-group{justify-content:center}
        .btn-advanced{font-size:13px;padding:8px 12px}
        .btn-lg{font-size:15px;padding:12px 16px}
      }
      @media (max-width:360px){
        .btn-group{flex-wrap:wrap}
        .btn-advanced{min-width:calc(33.33% - 4px);font-size:12px}
      }
      /* Advanced Buttons */
      .btn-advanced{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border-radius:12px;font:500 14px/1.2 Vazir,ui-sans-serif,system-ui;border:1px solid color-mix(in oklab,var(--tg-theme-hint-color) 22%,transparent);background:color-mix(in oklab,var(--tg-theme-secondary-bg-color) 88%,transparent);color:var(--tg-theme-text-color);transition:transform .14s ease,background-color .14s ease,border-color .14s ease,box-shadow .14s ease;user-select:none;cursor:pointer;position:relative;overflow:hidden;outline:none}
      .btn-advanced:hover{background:color-mix(in oklab,var(--tg-theme-secondary-bg-color) 100%,transparent);border-color:color-mix(in oklab,var(--tg-theme-hint-color) 35%,transparent)}
      .btn-advanced:active{transform:scale(.97)}
      .btn-advanced:focus-visible{outline:2px solid var(--tg-theme-button-color);outline-offset:2px}
      .btn-advanced svg{width:18px;height:18px;stroke-width:1.8;flex-shrink:0}
      .btn-primary{background:var(--tg-theme-button-color);color:var(--tg-theme-button-text-color);border-color:var(--tg-theme-button-color)}
      .btn-primary:hover{background:color-mix(in oklab,var(--tg-theme-button-color) 92%,#000);border-color:color-mix(in oklab,var(--tg-theme-button-color) 92%,#000)}
      .btn-primary:active{transform:scale(.97)}
      .btn-danger{background:color-mix(in oklab,#ef4444 90%,transparent);color:#fff;border-color:color-mix(in oklab,#ef4444 80%,transparent)}
      .btn-danger:hover{background:#ef4444;border-color:#ef4444}
      .btn-success{background:color-mix(in oklab,#10b981 90%,transparent);color:#fff;border-color:color-mix(in oklab,#10b981 80%,transparent)}
      .btn-success:hover{background:#10b981;border-color:#10b981}
      .btn-warning{background:color-mix(in oklab,#f59e0b 90%,transparent);color:#fff;border-color:color-mix(in oklab,#f59e0b 80%,transparent)}
      .btn-warning:hover{background:#f59e0b;border-color:#f59e0b}
      .btn-icon-only{padding:10px}
      .btn-icon-only svg{width:20px;height:20px}
      .btn-sm{padding:6px 10px;font-size:12px;border-radius:10px}
      .btn-sm svg{width:16px;height:16px}
      .btn-lg{padding:14px 18px;font-size:16px;border-radius:14px}
      .btn-lg svg{width:22px;height:22px}
      .btn-group{display:inline-flex;align-items:center;border-radius:12px;border:1px solid color-mix(in oklab,var(--tg-theme-hint-color) 22%,transparent);overflow:hidden}
      .btn-group .btn-advanced{border:none;border-radius:0;margin:0}
      .btn-group .btn-advanced:not(:last-child){border-left:1px solid color-mix(in oklab,var(--tg-theme-hint-color) 22%,transparent)}
      .btn-group .btn-advanced:hover{z-index:1}
      .btn-group .btn-advanced:active{z-index:2}
      .btn-ripple{position:absolute;border-radius:50%;background:color-mix(in oklab,var(--tg-theme-text-color) 18%,transparent);transform:scale(0);animation:ripple .5s ease-out;pointer-events:none}
      @keyframes ripple{to{transform:scale(4);opacity:0}}
      @media (prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:0s !important;transition-duration:0s !important}}
    </style>
  </head>
  <body>
    <div id="root">
      <div id="boot-loader"><div id="boot-card"><div id="boot-spinner"></div><div id="boot-title">Mirza Web App</div><div id="boot-sub">در حال آماده‌سازی…</div></div></div>
      <!-- Advanced Buttons Demo -->
      <div id="btn-demo" style="display:none;position:fixed;bottom:60px;right:12px;z-index:2147483646;display:flex;flex-direction:column;gap:10px;max-width:min(280px,calc(100vw - 24px));font-family:Vazir,ui-sans-serif,system-ui">
        <div class="btn-group">
          <button class="btn-advanced btn-sm" id="btn-home" aria-label="خانه"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> خانه</button>
          <button class="btn-advanced btn-sm" id="btn-cart" aria-label="سبد خرید"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> سبد</button>
          <button class="btn-advanced btn-sm" id="btn-profile" aria-label="پروفایل"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></button>
        </div>
        <div class="btn-group">
          <button class="btn-advanced btn-primary btn-sm" id="btn-primary" aria-label="ثبت سفارش"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> ثبت</button>
          <button class="btn-advanced btn-danger btn-sm" id="btn-danger" aria-label="حذف"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg></button>
        </div>
        <div class="btn-group">
          <button class="btn-advanced btn-success btn-sm" id="btn-success" aria-label="موفقیت"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></button>
          <button class="btn-advanced btn-warning btn-sm" id="btn-warning" aria-label="هشدار"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg></button>
          <button class="btn-advanced btn-icon-only btn-sm" id="btn-info" aria-label="اطلاعات"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg></button>
        </div>
        <div class="btn-group">
          <button class="btn-advanced btn-lg" id="btn-large" aria-label="عمل بزرگ"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg> عمل بزرگ</button>
        </div>
      </div>
    </div>
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

        function rippleEffect(e) {
          var btn = e.currentTarget;
          var rect = btn.getBoundingClientRect();
          var size = Math.max(rect.width, rect.height);
          var x = e.clientX - rect.left - size / 2;
          var y = e.clientY - rect.top - size / 2;
          var ripple = document.createElement("span");
          ripple.className = "btn-ripple";
          ripple.style.width = ripple.style.height = size + "px";
          ripple.style.left = x + "px";
          ripple.style.top = y + "px";
          btn.appendChild(ripple);
          ripple.addEventListener("animationend", function () {
            ripple.remove();
          });
        }

        function attachRipple() {
          document.querySelectorAll(".btn-advanced").forEach(function (btn) {
            btn.addEventListener("mousedown", rippleEffect, { passive: true });
          });
        }

        function attachButtonHandlers() {
          document.getElementById("btn-home").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("light"); } catch (_) {}
            console.log("[Mirza] Home clicked");
            try { window.ReactRouter && window.ReactRouter.navigate("/"); } catch (_) {}
          });
          document.getElementById("btn-cart").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("light"); } catch (_) {}
            console.log("[Mirza] Cart clicked");
            try { window.ReactRouter && window.ReactRouter.navigate("/buy"); } catch (_) {}
          });
          document.getElementById("btn-profile").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("light"); } catch (_) {}
            console.log("[Mirza] Profile clicked");
            try { window.ReactRouter && window.ReactRouter.navigate("/account"); } catch (_) {}
          });
          document.getElementById("btn-primary").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("medium"); } catch (_) {}
            console.log("[Mirza] Primary action");
            try { if (isTg && tg.showPopup) tg.showPopup({ title: "ثبت سفارش", message: "آیا مطمئن هستید؟", buttons: [{ type: "default", text: "بله" }, { type: "cancel" }] }, function (id) { if (id) console.log("[Mirza] Confirmed"); }); } catch (_) {}
          });
          document.getElementById("btn-danger").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("heavy"); } catch (_) {}
            console.log("[Mirza] Danger action");
            try { if (isTg && tg.showConfirm) tg.showConfirm("آیا می‌خواهید حذف کنید؟", function (ok) { if (ok) console.log("[Mirza] Deleted"); }); } catch (_) {}
          });
          document.getElementById("btn-success").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.notificationOccurred("success"); } catch (_) {}
            console.log("[Mirza] Success action");
            try { if (isTg && tg.showAlert) tg.showAlert("عملیات با موفقیت انجام شد!"); } catch (_) {}
          });
          document.getElementById("btn-warning").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.notificationOccurred("warning"); } catch (_) {}
            console.log("[Mirza] Warning action");
            try { if (isTg && tg.showAlert) tg.showAlert("هشدار: لطفاً بررسی کنید"); } catch (_) {}
          });
          document.getElementById("btn-info").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("light"); } catch (_) {}
            console.log("[Mirza] Info action");
            try { if (isTg && tg.showScanQrPopup) tg.showScanQrPopup({ text: "QR را اسکن کنید" }, function (text) { if (text) console.log("[Mirza] QR:", text); }); } catch (_) {}
          });
          document.getElementById("btn-large").addEventListener("click", function (e) {
            try { if (isTg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("medium"); } catch (_) {}
            console.log("[Mirza] Large action");
            try { if (isTg && tg.showPopup) tg.showPopup({ title: "عمل بزرگ", message: "این یک دکمه بزرگ است", buttons: [{ type: "default", text: "ادامه" }, { type: "cancel" }] }, function (id) { if (id) console.log("[Mirza] Large confirmed"); }); } catch (_) {}
          });
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
          attachRipple();
          attachButtonHandlers();
          // Show buttons after boot
          setTimeout(function(){
            var demo = document.getElementById("btn-demo");
            if (demo) demo.style.display = "flex";
          }, 400);
        }

        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start, { once: true });
        else start();
      })();
    </script>
    <script type="module" crossorigin src="/app/assets/index-C-2a0Dur.js"></script>
  </body>
</html>
