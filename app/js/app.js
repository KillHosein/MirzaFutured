// Initialize Telegram WebApp
const tg = window.Telegram.WebApp;

// Haptics & Ripple Logic
const initUI = () => {
    // Expand
    tg.expand();
    
    // Theme Colors
    if (tg.colorScheme === 'dark') {
        document.documentElement.classList.add('dark');
    }
    if (tg.headerColor) {
        document.documentElement.style.setProperty('--tg-theme-header-bg-color', tg.headerColor);
    }
    if (tg.backgroundColor) {
        document.documentElement.style.setProperty('--tg-theme-bg-color', tg.backgroundColor);
    }

    // Ripple Event Handler
    const createRipple = (e) => {
        const button = e.currentTarget;
        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${e.clientX - button.getBoundingClientRect().left - radius}px`;
        circle.style.top = `${e.clientY - button.getBoundingClientRect().top - radius}px`;
        circle.classList.add("ripple");

        const existingRipple = button.getElementsByClassName("ripple")[0];
        if (existingRipple) {
            existingRipple.remove();
        }

        button.appendChild(circle);

        // Haptic Feedback
        if(tg.HapticFeedback) {
            tg.HapticFeedback.impactOccurred('light');
        }
    };

    // Attach to all ripple-btn elements
    const buttons = document.getElementsByClassName("ripple-btn");
    for (const button of buttons) {
        button.addEventListener("click", createRipple);
    }
};

// Data Fetching Logic
const loadData = async () => {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ initData: tg.initData })
        });

        const data = await response.json();

        if (data.ok) {
            const user = data.user;
            const stats = data.stats || {};

            // Update User Info
            const nameEl = document.getElementById('user-name');
            if(nameEl) nameEl.textContent = user.username || user.first_name || 'کاربر';
            
            const idEl = document.getElementById('user-id');
            if(idEl) idEl.textContent = `ID: ${user.id}`;
            
            const balEl = document.getElementById('user-balance');
            if(balEl) balEl.textContent = user.balance || '0';
            
            // Update Stats
            const activeEl = document.getElementById('active-services');
            if(activeEl) activeEl.textContent = stats.active_services || '0';
            
            const totalEl = document.getElementById('total-spent');
            if(totalEl) totalEl.textContent = stats.total_spent || '0';

            // Avatar
            if (user.photo_url) {
                 const avatarEl = document.getElementById('user-avatar');
                 if(avatarEl) avatarEl.src = user.photo_url;
            }
            
            // Render Products
            const productsList = document.getElementById('products-list');
            if (productsList) {
                if (data.products && data.products.length > 0) {
                    productsList.innerHTML = data.products.map(product => `
                        <div class="glass-panel p-4 rounded-xl flex items-center justify-between ripple-btn">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm">${product.name_product}</h4>
                                    <p class="text-xs text-gray-400">تحویل آنی</p>
                                </div>
                            </div>
                            <button class="px-4 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs font-bold transition-colors border border-white/10">
                                خرید
                            </button>
                        </div>
                    `).join('');
                    
                    // Re-attach ripple to new elements
                    const newButtons = productsList.getElementsByClassName("ripple-btn");
                    for (const button of newButtons) {
                        button.addEventListener("click", (e) => {
                            // Ripple logic duplicated or extracted
                             const btn = e.currentTarget;
                             const circle = document.createElement("span");
                             const d = Math.max(btn.clientWidth, btn.clientHeight);
                             const r = d / 2;
                             circle.style.width = circle.style.height = `${d}px`;
                             circle.style.left = `${e.clientX - btn.getBoundingClientRect().left - r}px`;
                             circle.style.top = `${e.clientY - btn.getBoundingClientRect().top - r}px`;
                             circle.classList.add("ripple");
                             const old = btn.getElementsByClassName("ripple")[0];
                             if(old) old.remove();
                             btn.appendChild(circle);
                             if(tg.HapticFeedback) tg.HapticFeedback.impactOccurred('light');
                        });
                    }
                } else {
                    productsList.innerHTML = '<p class="text-center text-gray-500 text-sm">محصولی یافت نشد</p>';
                }
            }

            // Hide Preloader
            setTimeout(() => {
                const preloader = document.getElementById('app-preloader');
                const main = document.getElementById('main-app');
                if(preloader) preloader.classList.add('hidden-preloader');
                if(main) main.classList.remove('opacity-0');
            }, 500);

        } else {
            console.error('Auth Error:', data.error);
            const nameEl = document.getElementById('user-name');
            if(nameEl) nameEl.textContent = 'خطا در احراز هویت';
            
            // Show anyway to debug
            setTimeout(() => {
                document.getElementById('app-preloader').classList.add('hidden-preloader');
                document.getElementById('main-app').classList.remove('opacity-0');
            }, 500);
        }

    } catch (e) {
        console.error('Network Error:', e);
        setTimeout(() => {
            document.getElementById('app-preloader').classList.add('hidden-preloader');
            document.getElementById('main-app').classList.remove('opacity-0');
        }, 500);
    }
};

// Main Entry
document.addEventListener('DOMContentLoaded', () => {
    tg.ready();
    initUI();
    loadData();
});
