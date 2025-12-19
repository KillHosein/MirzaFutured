// Initialize Telegram WebApp
const tg = window.Telegram.WebApp;

const WebApp = {
    init: () => {
        tg.ready();
        tg.expand();
        
        // Theme Integration
        if (tg.colorScheme === 'dark') document.documentElement.classList.add('dark');
        if (tg.headerColor) document.documentElement.style.setProperty('--tg-theme-header-bg-color', tg.headerColor);
        if (tg.backgroundColor) document.documentElement.style.setProperty('--tg-theme-bg-color', tg.backgroundColor);

        WebApp.attachEventListeners();
        WebApp.loadDashboard();
    },

    // --- API Wrapper ---
    callApi: async (action, payload = {}) => {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    initData: tg.initData,
                    action: action,
                    ...payload 
                })
            });
            return await response.json();
        } catch (e) {
            console.error('API Error:', e);
            WebApp.showToast('خطای شبکه رخ داد', 'error');
            return null;
        }
    },

    // --- UI Logic ---
    attachEventListeners: () => {
        // Global Ripple Effect
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.ripple-btn');
            if (btn) WebApp.createRipple(e, btn);
        });

        // Close modal on back button (Telegram)
        tg.BackButton.onClick(() => {
            WebApp.closeModal();
        });
    },

    createRipple: (e, button) => {
        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        
        // Handle touch vs click coordinates
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${clientX - button.getBoundingClientRect().left - radius}px`;
        circle.style.top = `${clientY - button.getBoundingClientRect().top - radius}px`;
        circle.classList.add("ripple");

        const existing = button.getElementsByClassName("ripple")[0];
        if (existing) existing.remove();

        button.appendChild(circle);
        if(tg.HapticFeedback) tg.HapticFeedback.impactOccurred('light');
    },

    showToast: (message, type = 'success') => {
        const toast = document.getElementById('toast');
        const msgEl = document.getElementById('toast-message');
        const iconEl = document.getElementById('toast-icon');

        msgEl.textContent = message;
        iconEl.innerHTML = type === 'success' 
            ? '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';

        toast.classList.remove('opacity-0', 'translate-y-[-20px]');
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-[-20px]');
        }, 3000);
    },

    // --- Modals ---
    openModal: (title, contentHtml) => {
        const overlay = document.getElementById('modal-overlay');
        const container = document.getElementById('modal-container');
        const content = document.getElementById('modal-content');

        content.innerHTML = `
            <h3 class="text-lg font-bold mb-4 text-center">${title}</h3>
            ${contentHtml}
        `;

        overlay.classList.remove('hidden');
        // Small delay to allow display:block to apply before opacity transition
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            container.classList.remove('translate-y-full');
        }, 10);
        
        tg.BackButton.show();
    },

    closeModal: () => {
        const overlay = document.getElementById('modal-overlay');
        const container = document.getElementById('modal-container');

        overlay.classList.add('opacity-0');
        container.classList.add('translate-y-full');

        setTimeout(() => {
            overlay.classList.add('hidden');
            document.getElementById('modal-content').innerHTML = '';
        }, 300);

        tg.BackButton.hide();
    },

    // --- Views & Actions ---
    loadDashboard: async () => {
        const data = await WebApp.callApi('dashboard');
        if (!data || !data.ok) return;

        const user = data.user;
        const stats = data.stats;

        // Populate User Info
        document.getElementById('user-name').textContent = user.username || user.first_name || 'کاربر';
        document.getElementById('user-id').textContent = `ID: ${user.id}`;
        document.getElementById('user-balance').textContent = user.balance;
        document.getElementById('active-services').textContent = stats.active_services;
        document.getElementById('total-spent').textContent = stats.total_spent;
        if (user.photo_url) document.getElementById('user-avatar').src = user.photo_url;

        // Render Dashboard Products
        WebApp.renderProductList(data.products, 'products-list');

        // Hide Preloader
        setTimeout(() => {
            document.getElementById('app-preloader').classList.add('hidden-preloader');
            document.getElementById('main-app').classList.remove('opacity-0');
        }, 500);

        // Bind Buttons
        WebApp.bindDashboardButtons();
    },

    bindDashboardButtons: () => {
        // Add Funds
        document.querySelector('button[onclick*="showAlert"]').onclick = null; // Remove inline
        const addFundsBtn = document.querySelector('.glass-panel .action-btn') || document.querySelector('.glass-panel button'); // Fallback selector
        if(addFundsBtn) {
            addFundsBtn.onclick = () => WebApp.openDepositModal();
        } else {
             // In case selector fails, try finding by text or specific parent
             const btns = document.querySelectorAll('button');
             btns.forEach(btn => {
                 if(btn.textContent.includes('افزایش موجودی')) btn.onclick = () => WebApp.openDepositModal();
             });
        }

        // Quick Actions
        const quickActions = document.querySelectorAll('.grid.grid-cols-3 a');
        if (quickActions.length >= 3) {
            quickActions[0].onclick = (e) => { e.preventDefault(); WebApp.openBuyServiceModal(); };
            quickActions[1].onclick = (e) => { e.preventDefault(); WebApp.openOrdersModal(); };
            quickActions[2].onclick = (e) => { e.preventDefault(); WebApp.openSupportModal(); };
        }
    },

    renderProductList: (products, containerId) => {
        const container = document.getElementById(containerId);
        if (!products || products.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-500 text-sm py-4">محصولی یافت نشد</p>';
            return;
        }

        container.innerHTML = products.map(p => `
            <div class="glass-panel p-4 rounded-xl flex items-center justify-between ripple-btn mb-3">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm">${p.name_product}</h4>
                        <p class="text-xs text-gray-400">${Number(p.price).toLocaleString()} تومان</p>
                    </div>
                </div>
                <button onclick="WebApp.confirmBuy(${p.id}, '${p.name_product}', ${p.price})" class="px-4 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs font-bold transition-colors border border-white/10 text-primary">
                    خرید
                </button>
            </div>
        `).join('');
    },

    // --- Specific Modals ---
    openDepositModal: () => {
        WebApp.openModal('افزایش موجودی', `
            <div class="space-y-4">
                <p class="text-sm text-gray-400 text-center">لطفا مبلغ مورد نظر را انتخاب یا وارد کنید</p>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="document.getElementById('amount-input').value=50000" class="p-3 rounded-lg bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-colors">50,000 ت</button>
                    <button onclick="document.getElementById('amount-input').value=100000" class="p-3 rounded-lg bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-colors">100,000 ت</button>
                    <button onclick="document.getElementById('amount-input').value=200000" class="p-3 rounded-lg bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-colors">200,000 ت</button>
                    <button onclick="document.getElementById('amount-input').value=500000" class="p-3 rounded-lg bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-colors">500,000 ت</button>
                </div>
                <div class="relative">
                    <input type="number" id="amount-input" placeholder="مبلغ دلخواه (تومان)" class="w-full bg-black/20 border border-white/10 rounded-xl p-3 text-center focus:border-primary focus:outline-none transition-colors">
                </div>
                <button onclick="WebApp.submitDeposit()" class="w-full py-3 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 font-bold shadow-lg shadow-blue-500/30 ripple-btn mt-4">
                    پرداخت آنلاین
                </button>
            </div>
        `);
    },

    submitDeposit: () => {
        const amount = document.getElementById('amount-input').value;
        if(!amount || amount < 1000) {
            WebApp.showToast('مبلغ نامعتبر است', 'error');
            return;
        }
        // Here we would normally redirect to payment gateway
        // Since we don't have gateway logic in this file, we simulate or show info
        WebApp.closeModal();
        WebApp.showToast('در حال انتقال به درگاه...', 'success');
        // window.location.href = 'payment_link...'; 
    },

    openBuyServiceModal: async () => {
        WebApp.openModal('خرید سرویس', `
            <div class="flex justify-center py-8">
                <div class="spinner"></div>
            </div>
        `);
        
        const data = await WebApp.callApi('get_products');
        if(data && data.products) {
            // Re-render modal content
            const content = document.getElementById('modal-content');
            content.innerHTML = '<div id="modal-products-list" class="space-y-3"></div>';
            WebApp.renderProductList(data.products, 'modal-products-list');
        } else {
            WebApp.closeModal();
        }
    },

    openOrdersModal: async () => {
        WebApp.openModal('سفارشات من', `
            <div class="flex justify-center py-8">
                <div class="spinner"></div>
            </div>
        `);

        const data = await WebApp.callApi('get_orders');
        const content = document.getElementById('modal-content');
        
        if (data && data.ok) {
            let html = '<div class="space-y-3">';
            
            // Merge invoices and services or show them separately. 
            // Let's show active services first.
            if (data.services && data.services.length > 0) {
                 html += '<h4 class="text-xs font-bold text-gray-400 mb-2">سرویس‌های فعال</h4>';
                 data.services.forEach(s => {
                     html += `
                        <div class="p-3 rounded-xl bg-white/5 border border-white/10 flex justify-between items-center">
                            <div>
                                <div class="text-sm font-bold text-white">سرویس #${s.id}</div>
                                <div class="text-xs text-gray-400">${new Date(s.date * 1000).toLocaleDateString('fa-IR')}</div>
                            </div>
                            <span class="px-2 py-1 rounded bg-green-500/20 text-green-400 text-xs">فعال</span>
                        </div>
                     `;
                 });
            }

            if (data.invoices && data.invoices.length > 0) {
                html += '<h4 class="text-xs font-bold text-gray-400 mt-4 mb-2">فاکتورها</h4>';
                data.invoices.forEach(inv => {
                    const statusClass = inv.Status === 'paid' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400';
                    const statusText = inv.Status === 'paid' ? 'پرداخت شده' : 'پرداخت نشده';
                    html += `
                       <div class="p-3 rounded-xl bg-white/5 border border-white/10 flex justify-between items-center">
                           <div>
                               <div class="text-sm font-bold text-white">فاکتور #${inv.id}</div>
                               <div class="text-xs text-gray-400">${Number(inv.price).toLocaleString()} تومان</div>
                           </div>
                           <span class="px-2 py-1 rounded ${statusClass} text-xs">${statusText}</span>
                       </div>
                    `;
                });
            }

            if ((!data.services || data.services.length === 0) && (!data.invoices || data.invoices.length === 0)) {
                html += '<p class="text-center text-gray-500 text-sm">هیچ سفارشی یافت نشد</p>';
            }

            html += '</div>';
            content.innerHTML = html;
        } else {
            WebApp.closeModal();
        }
    },

    openSupportModal: () => {
        WebApp.openModal('پشتیبانی', `
            <div class="text-center space-y-4">
                <div class="w-16 h-16 bg-pink-500/20 rounded-full flex items-center justify-center mx-auto text-pink-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <p class="text-gray-300 text-sm">برای ارتباط با پشتیبانی می‌توانید از دکمه زیر استفاده کنید</p>
                <button onclick="tg.openTelegramLink('https://t.me/SUPPORT_USERNAME')" class="w-full py-3 rounded-xl bg-pink-600 font-bold shadow-lg shadow-pink-500/30 ripple-btn">
                    ارسال پیام به پشتیبانی
                </button>
            </div>
        `);
    },

    confirmBuy: (id, name, price) => {
        if(confirm(`آیا از خرید "${name}" به مبلغ ${Number(price).toLocaleString()} تومان اطمینان دارید؟`)) {
            WebApp.buyProduct(id);
        }
    },

    buyProduct: async (id) => {
        WebApp.showToast('در حال پردازش خرید...', 'success');
        const data = await WebApp.callApi('buy_product', { product_id: id });
        
        if (data && data.ok !== false) { // Handles implicit success if API structure varies
             if(data.error) {
                 WebApp.showToast(data.error, 'error');
             } else {
                 WebApp.showToast('خرید با موفقیت انجام شد', 'success');
                 WebApp.closeModal();
                 // Update Balance UI
                 if(data.new_balance) {
                     document.getElementById('user-balance').textContent = data.new_balance;
                 }
                 // Refresh dashboard stats
                 WebApp.loadDashboard();
             }
        } else {
            WebApp.showToast(data ? data.error : 'خطا در خرید', 'error');
        }
    }
};

// Start App
window.WebApp = WebApp; // Expose globally
document.addEventListener('DOMContentLoaded', WebApp.init);
