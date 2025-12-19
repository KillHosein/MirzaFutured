// Initialize Telegram WebApp
const tg = window.Telegram.WebApp;

const WebApp = {
    user: null,
    
    init: () => {
        tg.ready();
        tg.expand();
        
        // Theme Integration
        if (tg.colorScheme === 'dark') document.documentElement.classList.add('dark');
        if (tg.headerColor) document.documentElement.style.setProperty('--tg-theme-header-bg-color', tg.headerColor);
        if (tg.backgroundColor) document.documentElement.style.setProperty('--tg-theme-bg-color', tg.backgroundColor);

        // Prevent iOS scroll bounce
        document.body.style.overflowY = 'hidden'; 
        // Allow main app to scroll
        const main = document.getElementById('main-app');
        main.style.overflowY = 'auto';
        main.style.height = '100vh';

        WebApp.attachEventListeners();
        WebApp.loadDashboard();
        
        // Update Nav Indicator Position for initial active tab
        setTimeout(() => WebApp.updateNavIndicator(document.querySelector('.nav-item.active')), 100);
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
            if(!document.getElementById('modal-overlay').classList.contains('hidden')) {
                WebApp.closeModal();
            } else if (document.getElementById('view-dashboard').style.display === 'none') {
                WebApp.switchView('dashboard');
            } else {
                tg.close();
            }
        });
    },

    createRipple: (e, button) => {
        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        
        const rect = button.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${clientX - rect.left - radius}px`;
        circle.style.top = `${clientY - rect.top - radius}px`;
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

    // --- Navigation & Views ---
    switchView: (viewName) => {
        // Update Nav UI
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        const activeNav = document.querySelector(`.nav-item[data-target="${viewName}"]`);
        if(activeNav) {
            activeNav.classList.add('active');
            WebApp.updateNavIndicator(activeNav);
        }

        // Hide all views
        document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
        // Show target view
        document.getElementById(`view-${viewName}`).classList.add('active');

        // Handle specific view loading
        if (viewName === 'orders') {
            WebApp.loadOrdersView();
            tg.BackButton.show();
        } else if (viewName === 'profile') {
            WebApp.loadProfileView();
            tg.BackButton.show();
        } else {
            tg.BackButton.hide();
        }
        
        // Haptic
        if(tg.HapticFeedback) tg.HapticFeedback.selectionChanged();
    },

    updateNavIndicator: (element) => {
        const indicator = document.querySelector('.nav-indicator');
        const rect = element.getBoundingClientRect();
        // Since nav is fixed bottom, we calculate left relative to viewport width
        // But since we want it centered on the item:
        const width = 40; // indicator width
        const left = rect.left + (rect.width / 2) - (width / 2);
        indicator.style.left = `${left}px`;
    },

    // --- Data Loading ---
    loadDashboard: async () => {
        try {
            const data = await WebApp.callApi('dashboard');
            
            if (!data || !data.ok) {
                // Show error state inside preloader
                const errorMsg = data ? (data.error || 'خطای نامشخص') : 'خطای ارتباط با سرور';
                
                document.getElementById('app-preloader').innerHTML = `
                    <div class="text-center text-white px-6">
                        <div class="mb-4 text-red-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2">خطا در بارگذاری</h3>
                        <p class="text-sm text-gray-400 mb-6">${errorMsg}</p>
                        <button onclick="location.reload()" class="px-6 py-2 bg-white/10 rounded-xl hover:bg-white/20 transition-colors">تلاش مجدد</button>
                    </div>
                `;
                return;
            }

            WebApp.user = data.user;
            WebApp.botUsername = data.bot_username;
            WebApp.paymentMethods = data.payment_methods || [];
            const stats = data.stats;
            WebApp.user.referrals = stats.referrals;

            // Populate User Info
            document.getElementById('user-name').textContent = WebApp.user.username || WebApp.user.first_name || 'کاربر';
            document.getElementById('user-id').textContent = `ID: ${WebApp.user.id}`;
            document.getElementById('user-balance').textContent = WebApp.user.balance;
            document.getElementById('active-services').textContent = stats.active_services;
            document.getElementById('total-spent').textContent = stats.total_spent;
            if (WebApp.user.photo_url) document.getElementById('user-avatar').src = WebApp.user.photo_url;

            // Render Dashboard Products
            WebApp.renderProductList(data.products, 'products-list');

            // Hide Preloader
            setTimeout(() => {
                document.getElementById('app-preloader').classList.add('hidden-preloader');
                document.getElementById('main-app').classList.remove('opacity-0');
            }, 500);
            
        } catch (e) {
            console.error(e);
            document.getElementById('app-preloader').innerHTML = '<div class="text-white text-center p-4">خطای سیستمی رخ داد.<br><button onclick="location.reload()" class="mt-4 px-4 py-2 bg-white/10 rounded">تلاش مجدد</button></div>';
        }
    },

    loadOrdersView: async () => {
        const container = document.getElementById('orders-list-full');
        container.innerHTML = '<div class="flex justify-center py-10"><div class="spinner"></div></div>';
        
        const data = await WebApp.callApi('get_orders');
        
        if (data && data.ok !== false) {
            let html = '';
            
            // Services (Invoices)
            if (data.services && data.services.length > 0) {
                 data.services.forEach((s, index) => {
                     const delay = index * 0.05;
                     const statusColors = {
                         'active': 'bg-green-500/10 text-green-400 border-green-500/20',
                         'expired': 'bg-red-500/10 text-red-400 border-red-500/20',
                         'limited': 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                         'disabled': 'bg-gray-500/10 text-gray-400 border-gray-500/20'
                     };
                     const statusText = {
                         'active': 'فعال',
                         'expired': 'منقضی',
                         'limited': 'محدود',
                         'disabled': 'غیرفعال'
                     };
                     
                     const badgeClass = statusColors[s.Status] || statusColors['active'];
                     const badgeText = statusText[s.Status] || s.Status || 'فعال';
                     const date = s.time_sell ? new Date(s.time_sell * 1000).toLocaleDateString('fa-IR') : '-';

                     html += `
                        <div onclick="WebApp.openServiceDetails(${s.id})" class="glass-panel p-4 rounded-xl flex justify-between items-center mb-3 fade-in-up ripple-btn cursor-pointer" style="animation-delay: ${delay}s">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-white">${s.name_product || 'سرویس نامشخص'}</div>
                                    <div class="text-xs text-gray-400">${s.username || ''} | ${date}</div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="px-2 py-0.5 rounded ${badgeClass} text-[10px] border">${badgeText}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </div>
                        </div>
                     `;
                 });
            } else {
                html += '<div class="text-center text-gray-500 py-10 text-sm">سرویس فعالی ندارید</div>';
            }
            
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="text-center text-red-400 py-4">خطا در دریافت لیست سرویس‌ها</div>';
        }
    },

    openServiceDetails: async (id) => {
        WebApp.openModal('جزئیات سرویس', `
            <div class="flex justify-center py-10"><div class="spinner"></div></div>
        `);
        
        const data = await WebApp.callApi('get_service_details', { service_id: id });
        
        if(data && data.ok !== false && data.data) {
            const s = data.data;
            const total = s.total_traffic || 1;
            const used = s.used_traffic || 0;
            const remaining = total - used;
            const percent = Math.min(100, (used / total) * 100);
            
            const totalGB = (total / (1024*1024*1024)).toFixed(2);
            const usedGB = (used / (1024*1024*1024)).toFixed(2);
            const remainingGB = (remaining / (1024*1024*1024)).toFixed(2);
            
            const expireDate = s.expire_date ? new Date(s.expire_date * 1000).toLocaleDateString('fa-IR') : 'نامحدود';
            
            const content = `
                <div class="space-y-6">
                    <!-- Header -->
                    <div class="text-center">
                        <h4 class="text-xl font-bold text-white mb-1">${s.name_product}</h4>
                        <p class="text-sm text-gray-400 font-mono" dir="ltr">@${s.username}</p>
                    </div>

                    <!-- Usage Circle/Bar -->
                    <div class="glass-panel p-5 rounded-xl">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-400">مصرف شده</span>
                            <span class="text-white font-bold" dir="ltr">${usedGB} / ${totalGB} GB</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full transition-all duration-1000" style="width: ${percent}%"></div>
                        </div>
                        <div class="mt-4 flex justify-between items-center">
                            <div class="text-center">
                                <div class="text-xs text-gray-500">باقی‌مانده</div>
                                <div class="text-lg font-bold text-green-400" dir="ltr">${remainingGB} GB</div>
                            </div>
                             <div class="w-px h-8 bg-white/10"></div>
                            <div class="text-center">
                                <div class="text-xs text-gray-500">انقضا</div>
                                <div class="text-lg font-bold text-yellow-400">${expireDate}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="grid grid-cols-1 gap-3">
                        <div class="relative">
                            <input type="text" readonly value="${s.subscription_url}" class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-xs text-gray-400 font-mono text-left" id="sub-link-${s.id}">
                            <button onclick="WebApp.copyText('${s.subscription_url}')" class="absolute left-2 top-1/2 transform -translate-y-1/2 p-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-white transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                        
                        <button onclick="WebApp.openQRCode('${s.subscription_url}')" class="w-full py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 font-bold transition-colors flex items-center justify-center gap-2">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            نمایش QR Code
                        </button>
                    </div>
                </div>
            `;
            
            WebApp.openModal('جزئیات سرویس', content);
        } else {
             WebApp.showToast('خطا در دریافت اطلاعات سرویس', 'error');
             WebApp.closeModal();
        }
    },

    copyText: (text) => {
        navigator.clipboard.writeText(text).then(() => {
            WebApp.showToast('کپی شد');
        }).catch(() => {
             WebApp.showToast('خطا در کپی', 'error');
        });
    },

    openQRCode: (url) => {
         // Simple QR Code generation using API or library if available. 
         // Since we don't have a library, we can use a public API or just show the link.
         // Using goqr.me API for simplicity in this context
         const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`;
         
         WebApp.openModal('QR Code', `
            <div class="text-center py-4">
                <div class="bg-white p-4 rounded-xl inline-block mx-auto mb-4">
                    <img src="${qrUrl}" alt="QR Code" class="w-48 h-48">
                </div>
                <p class="text-sm text-gray-400 break-all px-4">${url.substring(0, 30)}...</p>
                <button onclick="WebApp.closeModal()" class="mt-6 w-full py-3 rounded-xl bg-white/5 hover:bg-white/10 font-bold">بستن</button>
            </div>
         `);
    },

    loadProfileView: () => {
        if (!WebApp.user) return;
        
        document.getElementById('profile-name').textContent = WebApp.user.username || WebApp.user.first_name || 'کاربر';
        document.getElementById('profile-username').textContent = WebApp.user.username ? `@${WebApp.user.username}` : '';
        document.getElementById('profile-id').textContent = WebApp.user.id;
        document.getElementById('profile-joined').textContent = WebApp.user.joined_at;
        if (WebApp.user.photo_url) document.getElementById('profile-avatar-large').src = WebApp.user.photo_url;

        // Referral Section
        const referralId = 'profile-referral-card';
        let referralCard = document.getElementById(referralId);
        
        if (!referralCard) {
            referralCard = document.createElement('div');
            referralCard.id = referralId;
            referralCard.className = 'glass-panel p-4 rounded-xl mb-4 fade-in-up';
            referralCard.innerHTML = `
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center text-orange-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm text-white">دعوت از دوستان</h4>
                        <p class="text-xs text-gray-400">با دعوت دوستان خود پاداش بگیرید</p>
                    </div>
                </div>
                <div class="bg-black/20 rounded-lg p-3 flex items-center justify-between gap-2 mb-3 border border-white/5">
                    <div class="text-xs font-mono text-gray-300 truncate" id="ref-link">...</div>
                    <button onclick="WebApp.copyReferralLink()" class="p-2 rounded bg-primary/20 text-primary hover:bg-primary/30 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
                <div class="flex justify-between items-center text-xs text-gray-400">
                    <span>تعداد دعوت شده‌ها:</span>
                    <span class="text-white font-bold" id="ref-count">0</span>
                </div>
            `;
            
            const profileView = document.getElementById('view-profile');
            const profileInfo = profileView.querySelector('.glass-panel'); 
            if (profileInfo && profileInfo.parentNode) {
                 profileInfo.parentNode.insertBefore(referralCard, profileInfo.nextSibling);
            }
        }
        
        const botName = WebApp.botUsername || 'YourBot';
        const refLink = `https://t.me/${botName}?start=${WebApp.user.code_invitation}`;
        const linkEl = document.getElementById('ref-link');
        if(linkEl) linkEl.textContent = refLink;
        
        const countEl = document.getElementById('ref-count');
        if(countEl) countEl.textContent = WebApp.user.referrals || 0;
        
        // History Button
        const historyBtnId = 'profile-history-btn';
        if (!document.getElementById(historyBtnId)) {
            const btn = document.createElement('button');
            btn.id = historyBtnId;
            btn.onclick = WebApp.openTransactionsHistory;
            btn.className = 'w-full py-4 rounded-xl bg-white/5 border border-white/10 font-bold hover:bg-white/10 transition-colors ripple-btn flex items-center justify-between px-4 mb-3';
            btn.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center text-blue-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-white text-sm">تاریخچه تراکنش‌ها</span>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            `;
            if (referralCard && referralCard.parentNode) {
                 referralCard.parentNode.insertBefore(btn, referralCard.nextSibling);
            }
        }
    },

    openTransactionsHistory: async () => {
        WebApp.openModal('تاریخچه تراکنش‌ها', `
            <div class="flex justify-center py-10"><div class="spinner"></div></div>
        `);
        
        const data = await WebApp.callApi('get_transactions');
        
        if (data && data.ok !== false && data.transactions) {
            let html = '<div class="space-y-3">';
            
            if (data.transactions.length > 0) {
                data.transactions.forEach(t => {
                     const isPaid = t.payment_Status === 'paid';
                     const statusColor = isPaid ? 'text-green-400' : 'text-red-400';
                     const iconColor = isPaid ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400';
                     const statusText = isPaid ? 'موفق' : 'ناموفق';
                     const method = t.Payment_Method || 'نامشخص';
                     const date = t.time ? t.time : '-';
                     
                     html += `
                        <div class="glass-panel p-3 rounded-xl flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg ${iconColor} flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isPaid ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'}" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-white">${Number(t.price).toLocaleString()} تومان</div>
                                    <div class="text-xs text-gray-400">${method} | ${date}</div>
                                </div>
                            </div>
                            <span class="text-xs ${statusColor}">${statusText}</span>
                        </div>
                     `;
                });
            } else {
                html += '<div class="text-center text-gray-500 py-6">تراکنشی یافت نشد</div>';
            }
            html += '</div>';
            
            WebApp.openModal('تاریخچه تراکنش‌ها', html);
        } else {
            WebApp.showToast('خطا در دریافت تاریخچه', 'error');
            WebApp.closeModal();
        }
    },

    copyReferralLink: () => {
        const link = document.getElementById('ref-link').textContent;
        navigator.clipboard.writeText(link).then(() => {
            WebApp.showToast('لینک دعوت کپی شد');
        }).catch(() => {
             WebApp.showToast('خطا در کپی لینک', 'error');
        });
    },

    renderProductList: (products, containerId) => {
        const container = document.getElementById(containerId);
        if (!products || products.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-500 text-sm py-4">محصولی یافت نشد</p>';
            return;
        }

        container.innerHTML = products.map((p, index) => `
            <div class="glass-panel p-4 rounded-xl flex items-center justify-between ripple-btn mb-3 fade-in-up" style="animation-delay: ${index * 0.1}s">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-500/20 flex items-center justify-center text-indigo-400 shadow-inner shadow-indigo-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm mb-1">${p.name_product}</h4>
                        <div class="flex items-center gap-2">
                             <span class="text-xs text-primary bg-primary/10 px-2 py-0.5 rounded border border-primary/20">${Number(p.price).toLocaleString()} ت</span>
                             <span class="text-[10px] text-gray-500 line-through">${Number(p.price * 1.2).toLocaleString()}</span>
                        </div>
                    </div>
                </div>
                <button onclick="WebApp.confirmBuy(${p.id}, '${p.name_product}', ${p.price})" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center transition-colors border border-white/10 text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
        `).join('');
    },

    // --- Modals ---
    openModal: (title, contentHtml) => {
        const overlay = document.getElementById('modal-overlay');
        const container = document.getElementById('modal-container');
        const content = document.getElementById('modal-content');

        content.innerHTML = `
            <h3 class="text-lg font-bold mb-6 text-center text-white sticky top-0 bg-[#1e293b] pb-2 z-10">${title}</h3>
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

        if (document.getElementById('view-dashboard').classList.contains('active')) {
             tg.BackButton.hide();
        }
    },

    // --- Specific Modals ---
    openDepositModal: () => {
        let methodsHtml = '';
        
        if (WebApp.paymentMethods && WebApp.paymentMethods.length > 0) {
            methodsHtml = '<div class="grid grid-cols-1 gap-2 mb-4">';
            WebApp.paymentMethods.forEach((m, index) => {
                const checked = index === 0 ? 'checked' : '';
                methodsHtml += `
                    <label class="relative flex items-center justify-between p-3 rounded-xl bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10 transition-colors">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="payment_method" value="${m.id}" class="w-4 h-4 text-blue-500 bg-gray-700 border-gray-600 focus:ring-blue-500" ${checked}>
                            <span class="text-sm font-bold text-white">${m.name}</span>
                        </div>
                        ${m.id === 'card' ? '<svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>' : ''}
                        ${m.id === 'zarinpal' ? '<svg class="w-5 h-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>' : ''}
                        ${m.id === 'nowpayments' ? '<svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' : ''}
                    </label>
                `;
            });
            methodsHtml += '</div>';
        } else {
             methodsHtml = '<p class="text-red-400 text-xs text-center mb-4">هیچ روش پرداختی فعال نیست</p>';
        }

        WebApp.openModal('افزایش موجودی', `
            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="WebApp.setAmount(50000)" class="p-4 rounded-xl bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-all ripple-btn font-bold">50,000 ت</button>
                    <button onclick="WebApp.setAmount(100000)" class="p-4 rounded-xl bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-all ripple-btn font-bold">100,000 ت</button>
                    <button onclick="WebApp.setAmount(200000)" class="p-4 rounded-xl bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-all ripple-btn font-bold">200,000 ت</button>
                    <button onclick="WebApp.setAmount(500000)" class="p-4 rounded-xl bg-white/5 border border-white/10 text-sm hover:bg-primary/20 transition-all ripple-btn font-bold">500,000 ت</button>
                </div>
                <div class="relative group">
                    <label class="text-xs text-gray-400 absolute -top-2 right-3 bg-[#1e293b] px-1">مبلغ دلخواه</label>
                    <input type="number" id="amount-input" placeholder="0" class="modern-input text-center text-2xl font-bold tracking-widest">
                    <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500 text-sm">تومان</span>
                </div>
                
                <div class="border-t border-white/10 pt-4">
                    <p class="text-xs text-gray-400 mb-2">انتخاب روش پرداخت:</p>
                    ${methodsHtml}
                </div>

                <button onclick="WebApp.submitDeposit()" class="w-full py-4 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 font-bold shadow-lg shadow-blue-500/30 ripple-btn text-lg">
                    پرداخت آنلاین
                </button>
            </div>
        `);
    },

    setAmount: (val) => {
        const input = document.getElementById('amount-input');
        input.value = val;
        // Visual feedback
        input.parentElement.classList.add('scale-[1.02]');
        setTimeout(() => input.parentElement.classList.remove('scale-[1.02]'), 150);
    },

    submitDeposit: async () => {
        const amount = document.getElementById('amount-input').value;
        if(!amount || amount < 1000) {
            WebApp.showToast('مبلغ باید حداقل ۱۰۰۰ تومان باشد', 'error');
            return;
        }

        const methodInput = document.querySelector('input[name="payment_method"]:checked');
        const method = methodInput ? methodInput.value : 'zarinpal';

        const btn = document.querySelector('#modal-content button.bg-gradient-to-r');
        if(btn) {
            btn.innerHTML = '<div class="spinner w-5 h-5 border-2 mx-auto"></div>';
            btn.disabled = true;
        }

        const data = await WebApp.callApi('deposit', { amount: amount, method: method });
        
        if (data && data.ok) {
            WebApp.closeModal();
            if (data.url) {
                WebApp.showToast('در حال انتقال به درگاه...', 'success');
                setTimeout(() => {
                    tg.openLink(data.url);
                }, 1000);
            } else if (data.card_number) {
                // Show Card Info
                WebApp.openModal('پرداخت کارت به کارت', `
                    <div class="text-center space-y-4">
                        <div class="p-4 bg-white/5 rounded-xl border border-white/10">
                            <p class="text-gray-400 text-xs mb-2">شماره کارت جهت واریز</p>
                            <div class="font-mono text-xl font-bold tracking-wider text-yellow-400 select-all" onclick="navigator.clipboard.writeText('${data.card_number}'); WebApp.showToast('کپی شد')">
                                ${data.card_number.match(/.{1,4}/g).join('-')}
                            </div>
                            <p class="text-gray-500 text-xs mt-2">${data.card_name || ''}</p>
                        </div>
                        
                        <div class="bg-blue-500/10 p-3 rounded-lg text-sm text-blue-300">
                            <p>لطفا مبلغ ${Number(amount).toLocaleString()} تومان را به کارت بالا واریز کرده و سپس در ربات اصلی، رسید پرداخت را ارسال کنید.</p>
                        </div>

                        <button onclick="tg.close()" class="w-full py-3 rounded-xl bg-white/5 hover:bg-white/10 transition-colors font-bold">
                            بازگشت به ربات
                        </button>
                    </div>
                `);
            }
        } else {
            WebApp.showToast(data ? data.error : 'خطا در ایجاد تراکنش', 'error');
            if(btn) {
                btn.innerHTML = 'پرداخت آنلاین';
                btn.disabled = false;
            }
        }
    },

    openSupportModal: () => {
        WebApp.openModal('پشتیبانی', `
            <div class="text-center space-y-6 py-4">
                <div class="relative w-24 h-24 mx-auto">
                    <div class="absolute inset-0 bg-pink-500/30 rounded-full blur-xl animate-pulse"></div>
                    <div class="relative w-full h-full bg-gradient-to-tr from-pink-500 to-purple-600 rounded-full flex items-center justify-center text-white shadow-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg text-white mb-2">چطور می‌توانیم کمک کنیم؟</h4>
                    <p class="text-gray-400 text-sm leading-relaxed">تیم پشتیبانی ما ۲۴ ساعته آماده پاسخگویی به سوالات و مشکلات شماست.</p>
                </div>

                <button onclick="tg.openTelegramLink('https://t.me/SUPPORT_USERNAME')" class="w-full py-4 rounded-xl bg-white/5 border border-white/10 font-bold hover:bg-white/10 transition-colors ripple-btn flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                    <span>ارسال پیام در تلگرام</span>
                </button>
            </div>
        `);
    },

    confirmBuy: (id, name, price) => {
        WebApp.openModal('تایید خرید', `
            <div class="text-center space-y-4">
                <div class="w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto text-blue-400 mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <h4 class="font-bold text-lg">${name}</h4>
                <div class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">
                    ${Number(price).toLocaleString()} <span class="text-sm text-gray-500 font-normal">تومان</span>
                </div>
                <p class="text-sm text-gray-400">آیا از خرید این سرویس اطمینان دارید؟ مبلغ از کیف پول شما کسر خواهد شد.</p>
                
                <div class="grid grid-cols-2 gap-3 mt-6">
                    <button onclick="WebApp.closeModal()" class="py-3 rounded-xl bg-white/5 text-gray-400 font-bold">انصراف</button>
                    <button onclick="WebApp.buyProduct(${id})" class="py-3 rounded-xl bg-primary text-white font-bold shadow-lg shadow-blue-500/30">تایید و خرید</button>
                </div>
            </div>
        `);
    },

    buyProduct: async (id) => {
        // Show loading state in button
        const btn = document.querySelector('#modal-content button.bg-primary');
        if(btn) {
            btn.innerHTML = '<div class="spinner w-5 h-5 border-2 mx-auto"></div>';
            btn.disabled = true;
        }

        const data = await WebApp.callApi('buy_product', { product_id: id });
        
        if (data && data.ok !== false) {
             if(data.error) {
                 WebApp.showToast(data.error, 'error');
                 WebApp.closeModal();
             } else {
                 WebApp.closeModal();
                 // Success Animation Modal
                 WebApp.openModal(' ', `
                    <div class="text-center py-6">
                        <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">خرید موفق!</h3>
                        <p class="text-gray-400 text-sm">سرویس شما با موفقیت فعال شد.</p>
                    </div>
                 `);
                 if(tg.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
                 
                 // Update Balance UI
                 if(data.new_balance) {
                     document.getElementById('user-balance').textContent = data.new_balance;
                 }
                 // Refresh dashboard stats
                 WebApp.loadDashboard();
             }
        } else {
            WebApp.showToast(data ? data.error : 'خطا در خرید', 'error');
            WebApp.closeModal();
        }
    }
};

// Start App
window.WebApp = WebApp; 
document.addEventListener('DOMContentLoaded', WebApp.init);
