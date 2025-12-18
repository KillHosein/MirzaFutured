document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram.WebApp;
    
    // Initialize
    tg.ready();
    tg.expand();
    
    // Theme handling with smooth transition
    document.body.className = 'telegram-web-app'; 
    if (tg.themeParams) {
        const root = document.documentElement;
        if (tg.themeParams.bg_color) root.style.setProperty('--bg-color', tg.themeParams.bg_color);
        if (tg.themeParams.text_color) root.style.setProperty('--text-primary', tg.themeParams.text_color);
        if (tg.themeParams.button_color) root.style.setProperty('--accent-color', tg.themeParams.button_color);
        if (tg.themeParams.button_text_color) root.style.setProperty('--btn-text-color', tg.themeParams.button_text_color);
        if (tg.themeParams.secondary_bg_color) root.style.setProperty('--card-bg', tg.themeParams.secondary_bg_color);
    }

    // Navigation Logic with Animation
    const navItems = document.querySelectorAll('.nav-item');
    const sections = document.querySelectorAll('.page-section');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = item.getAttribute('href').substring(1);
            
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
            
            // Haptic Feedback
            if(tg.HapticFeedback) {
                tg.HapticFeedback.impactOccurred('light');
            }
            
            sections.forEach(sec => {
                if(sec.id === targetId) {
                    sec.style.display = 'block';
                    // Trigger reflow for animation
                    void sec.offsetWidth;
                    sec.style.opacity = 1;
                } else {
                    sec.style.display = 'none';
                    sec.style.opacity = 0;
                }
            });
        });
    });

    // Fetch Data
    fetchData();

    function fetchData() {
        const loader = document.getElementById('loader');
        loader.classList.remove('hidden');

        const initData = tg.initData;

        // Mock data for development
        if (!initData) {
            console.warn('No initData found. Using Mock Data.');
            setTimeout(() => {
                renderData({
                    user: { id: 123456, name: 'کاربر تستی', username: 'test_user', balance: '۵۰,۰۰۰ تومان' },
                    transactions: [
                        { id: 1, amount: 50000, date: '1402/10/01', description: 'شارژ حساب' },
                        { id: 2, amount: 100000, date: '1402/09/15', description: 'شارژ حساب' },
                        { id: 3, amount: 20000, date: '1402/09/10', description: 'خرید سرویس' },
                        { id: 4, amount: 50000, date: '1402/08/25', description: 'شارژ حساب' }
                    ],
                    services: [
                        { name: 'سرویس یک ماهه', expire_date: '1402/11/01', status: 'active' },
                        { name: 'سرویس سه ماهه', expire_date: '1402/08/01', status: 'expired' }
                    ]
                });
                loader.classList.add('hidden');
            }, 800);
            return;
        }

        fetch('api/webapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ initData: initData })
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                renderData(data);
            } else {
                showToast(data.error || 'خطا در دریافت اطلاعات', 'error');
            }
        })
        .catch(err => {
            console.error('API Error:', err);
            showToast('خطا در ارتباط با سرور', 'error');
        })
        .finally(() => {
            loader.classList.add('hidden');
        });
    }

    function renderData(data) {
        const user = data.user;
        const transactions = data.transactions;
        const services = data.services;

        // User Info
        document.getElementById('user-name').textContent = user.name;
        document.getElementById('user-id').textContent = 'ID: ' + user.id;
        document.getElementById('user-balance').textContent = user.balance;
        document.getElementById('active-services-count').textContent = services.filter(s => s.status !== 'expired').length;
        
        // Avatar
        if (user.username) {
             const avatarImg = document.getElementById('user-avatar');
             // In a real app, you'd fetch the user profile photo via API
        }

        // Services List with Staggered Animation
        const servicesContainer = document.getElementById('my-services-list');
        servicesContainer.innerHTML = '';
        if (services.length === 0) {
            servicesContainer.innerHTML = '<div class="empty-state">سرویس فعالی ندارید</div>';
        } else {
            services.forEach((srv, index) => {
                const isActive = srv.status !== 'expired';
                const el = document.createElement('div');
                el.className = 'list-item service-item';
                el.style.animationDelay = `${index * 0.1}s`; // Stagger
                el.innerHTML = `
                    <div class="item-icon ${isActive ? 'bg-green' : 'bg-red'}">
                        <i class="fas ${isActive ? 'fa-rocket' : 'fa-ban'}"></i>
                    </div>
                    <div class="item-content">
                        <span class="item-title">${srv.name}</span>
                        <span class="item-subtitle">${isActive ? 'انقضا: ' + srv.expire_date : 'منقضی شده'}</span>
                    </div>
                    <div class="item-action">
                        <span class="badge ${isActive ? 'badge-success' : ''}" style="${!isActive ? 'background:rgba(239,68,68,0.1);color:#ef4444' : ''}">
                            ${isActive ? 'فعال' : 'غیرفعال'}
                        </span>
                    </div>
                `;
                servicesContainer.appendChild(el);
            });
        }

        // Transactions List with Staggered Animation
        const txContainer = document.getElementById('transactions-list');
        txContainer.innerHTML = '';
        if (transactions.length === 0) {
            txContainer.innerHTML = '<div class="empty-state">تراکنشی یافت نشد</div>';
        } else {
            transactions.forEach((tx, index) => {
                const isPositive = true; 
                const el = document.createElement('div');
                el.className = 'list-item';
                el.style.animationDelay = `${index * 0.05}s`; // Faster stagger
                el.innerHTML = `
                    <div class="item-icon ${isPositive ? 'bg-blue' : 'bg-red'}">
                        <i class="fas ${isPositive ? 'fa-wallet' : 'fa-arrow-up'}"></i>
                    </div>
                    <div class="item-content">
                        <span class="item-title">${tx.description || 'تراکنش'}</span>
                        <span class="item-subtitle">${tx.date}</span>
                    </div>
                    <div class="item-action">
                        <span class="tx-amount text-primary font-bold">
                            ${parseInt(tx.amount).toLocaleString()}
                        </span>
                    </div>
                `;
                txContainer.appendChild(el);
            });
        }

        // Chart
        if (transactions.length > 0) {
            renderChart(transactions);
        }
    }

    function renderChart(transactions) {
        const ctx = document.getElementById('usageChart').getContext('2d');
        
        // Prepare Data
        const lastTxs = transactions.slice(0, 7).reverse();
        const labels = lastTxs.map((t, i) => i + 1); 
        const dataPoints = lastTxs.map(t => t.amount);

        // Gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'تراکنش‌ها',
                    data: dataPoints,
                    borderColor: '#3b82f6',
                    backgroundColor: gradient,
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointRadius: 0,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleColor: '#f3f4f6',
                        bodyColor: '#d1d5db',
                        borderColor: '#374151',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString() + ' تومان';
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { 
                        display: false,
                        min: 0
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    }

    function showToast(msg, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.className = `toast show ${type}`;
        
        if(tg.HapticFeedback) {
             tg.HapticFeedback.notificationOccurred(type === 'error' ? 'error' : 'success');
        }

        setTimeout(() => {
            toast.className = 'toast hidden';
        }, 3000);
    }
});
