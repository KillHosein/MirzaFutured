document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram.WebApp;
    
    // Initialize
    tg.ready();
    tg.expand();
    
    // Theme handling
    document.body.className = 'telegram-web-app'; 
    if (tg.themeParams) {
        const root = document.documentElement;
        if (tg.themeParams.bg_color) root.style.setProperty('--bg-color', tg.themeParams.bg_color);
        if (tg.themeParams.text_color) root.style.setProperty('--text-primary', tg.themeParams.text_color);
        if (tg.themeParams.button_color) root.style.setProperty('--accent-color', tg.themeParams.button_color);
        if (tg.themeParams.button_text_color) root.style.setProperty('--btn-text-color', tg.themeParams.button_text_color);
        if (tg.themeParams.secondary_bg_color) root.style.setProperty('--card-bg', tg.themeParams.secondary_bg_color);
    }

    // Navigation Logic
    const navItems = document.querySelectorAll('.nav-item');
    const sections = document.querySelectorAll('.page-section');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = item.getAttribute('href').substring(1);
            
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
            
            sections.forEach(sec => {
                if(sec.id === targetId) {
                    sec.style.display = 'block';
                    // Simple Fade In
                    sec.style.opacity = 0;
                    setTimeout(() => sec.style.opacity = 1, 50);
                } else {
                    sec.style.display = 'none';
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

        // Mock data for development if no initData (browser testing)
        if (!initData) {
            console.warn('No initData found. Using Mock Data.');
            setTimeout(() => {
                renderData({
                    user: { id: 123456, name: 'کاربر تستی', username: 'test_user', balance: '۵۰,۰۰۰ تومان' },
                    transactions: [
                        { id: 1, amount: 50000, date: '1402/10/01', description: 'شارژ حساب' },
                        { id: 2, amount: 100000, date: '1402/09/15', description: 'شارژ حساب' }
                    ],
                    services: [
                        { name: 'سرویس یک ماهه', expire_date: '1402/11/01' }
                    ]
                });
                loader.classList.add('hidden');
            }, 1000);
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
        document.getElementById('active-services-count').textContent = services.length;
        
        // Avatar
        if (user.username) {
            // Placeholder for avatar logic
        }

        // Services List
        const servicesContainer = document.getElementById('my-services-list');
        servicesContainer.innerHTML = '';
        if (services.length === 0) {
            servicesContainer.innerHTML = '<div class="empty-state">سرویس فعالی ندارید</div>';
        } else {
            services.forEach(srv => {
                const el = document.createElement('div');
                el.className = 'list-item service-item';
                el.innerHTML = `
                    <div class="item-icon bg-green"><i class="fas fa-rocket"></i></div>
                    <div class="item-content">
                        <span class="item-title">${srv.name}</span>
                        <span class="item-subtitle">انقضا: ${srv.expire_date || 'نامحدود'}</span>
                    </div>
                    <div class="item-action">
                        <span class="badge badge-success">فعال</span>
                    </div>
                `;
                servicesContainer.appendChild(el);
            });
        }

        // Transactions List
        const txContainer = document.getElementById('transactions-list');
        txContainer.innerHTML = '';
        if (transactions.length === 0) {
            txContainer.innerHTML = '<div class="empty-state">تراکنشی یافت نشد</div>';
        } else {
            transactions.forEach(tx => {
                const isPositive = true; // Simplified for now
                const el = document.createElement('div');
                el.className = 'list-item';
                el.innerHTML = `
                    <div class="item-icon ${isPositive ? 'bg-blue' : 'bg-red'}">
                        <i class="fas ${isPositive ? 'fa-wallet' : 'fa-arrow-up'}"></i>
                    </div>
                    <div class="item-content">
                        <span class="item-title">${tx.description || 'تراکنش'}</span>
                        <span class="item-subtitle">${tx.date}</span>
                    </div>
                    <div class="item-action">
                        <span class="tx-amount text-primary">
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
        
        // Simple logic: last 7 transactions amounts
        const lastTxs = transactions.slice(0, 7).reverse();
        const labels = lastTxs.map(t => 'Tx'); // Simplified labels
        const dataPoints = lastTxs.map(t => t.amount);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'تراکنش‌ها',
                    data: dataPoints,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#2563eb'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { display: false },
                    y: { 
                        display: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#71717a' }
                    }
                }
            }
        });
    }

    function showToast(msg, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.className = `toast show ${type}`;
        setTimeout(() => {
            toast.className = 'toast hidden';
        }, 3000);
    }
});
