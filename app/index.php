
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
  </head>
  <body>
    <div id="root"></div>
    
    <!-- Wallet Button Injection -->
    <script>
        // Wait for Telegram Web App to be ready
        if (window.Telegram && window.Telegram.WebApp) {
            window.Telegram.WebApp.ready();
            window.Telegram.WebApp.expand(); // Expand to full height
        }

        function addWalletButton() {
            // Check if button already exists
            if (document.getElementById('custom-wallet-btn')) return;

            const btn = document.createElement('div');
            btn.id = 'custom-wallet-btn';
            btn.innerHTML = '<span>💳</span> <span style="margin-right: 5px;">کیف پول</span>';
            btn.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px; /* Left side for RTL context or just choice */
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 24px;
                border-radius: 50px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.3);
                cursor: pointer;
                z-index: 9999;
                font-family: 'Vazir', Tahoma, sans-serif;
                display: flex;
                align-items: center;
                font-weight: bold;
                transition: transform 0.2s;
            `;

            btn.onmouseover = function() {
                this.style.transform = 'scale(1.05)';
            };
            btn.onmouseout = function() {
                this.style.transform = 'scale(1)';
            };

            btn.onclick = function() {
                const user = window.Telegram?.WebApp?.initDataUnsafe?.user;
                if (user && user.id) {
                    const params = new URLSearchParams({
                        user_id: user.id,
                        first_name: user.first_name || '',
                        last_name: user.last_name || '',
                        username: user.username || ''
                    });
                    window.location.href = 'wallet.php?' + params.toString();
                } else {
                    // Fallback for testing outside Telegram
                    // alert('اطلاعات کاربر یافت نشد. لطفا از داخل تلگرام باز کنید.');
                    // For development convenience, we might want to allow a fallback or just alert
                    if (confirm('اطلاعات کاربر یافت نشد. آیا میخواهید با شناسه تست وارد شوید؟')) {
                         window.location.href = 'wallet.php?user_id=123456789&first_name=Test&last_name=User';
                    }
                }
            };

            document.body.appendChild(btn);
        }

        // Add button after a short delay to ensure DOM is ready
        setTimeout(addWalletButton, 1000);
    </script>
  </body>
</html>
