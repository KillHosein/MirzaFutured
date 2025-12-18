document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram.WebApp;
    
    // Initialize
    tg.ready();
    tg.expand();
    
    // Theme handling
    document.body.className = 'telegram-web-app'; // trigger CSS vars
    
    // Mock Data
    const user = {
        name: tg.initDataUnsafe?.user?.first_name || 'کاربر مهمان',
        username: tg.initDataUnsafe?.user?.username || 'Guest',
        balance: '۵۰,۰۰۰ تومان',
        activeServices: 2
    };

    const services = [
        { id: 1, name: 'سرویس یک ماهه', speed: 'نامحدود', traffic: '۳۰ گیگ', price: '۵۰,۰۰۰ تومان' },
        { id: 2, name: 'سرویس سه ماهه', speed: 'نامحدود', traffic: '۹۰ گیگ', price: '۱۴۰,۰۰۰ تومان' },
        { id: 3, name: 'سرویس شش ماهه', speed: 'نامحدود', traffic: '۱۸۰ گیگ', price: '۲۵۰,۰۰۰ تومان' }
    ];

    const myServices = [
        { id: 101, name: 'vless-ws-tls', expire: '۱۴ روز مانده', status: 'active' },
        { id: 102, name: 'vmess-tcp', expire: 'منقضی شده', status: 'expired' }
    ];

    // Render User Info
    document.getElementById('user-name').textContent = user.name;
    document.getElementById('user-balance').textContent = user.balance;
    document.getElementById('active-services-count').textContent = user.activeServices;

    // Navigation
    const navItems = document.querySelectorAll('.nav-item');
    const sections = document.querySelectorAll('.page-section');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = item.getAttribute('href').substring(1);
            
            // Update Nav
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
            
            // Show Section
            sections.forEach(sec => {
                sec.style.display = 'none';
                if(sec.id === targetId) {
                    sec.style.display = 'block';
                    // Animation
                    sec.style.opacity = 0;
                    setTimeout(() => sec.style.opacity = 1, 50);
                }
            });
        });
    });

    // Render Shop
    const shopContainer = document.getElementById('shop-list');
    services.forEach(service => {
        const el = document.createElement('div');
        el.className = 'card service-card';
        el.innerHTML = `
            <div class="service-item">
                <div class="service-icon"><i class="fas fa-rocket"></i></div>
                <div class="service-details">
                    <span class="service-name">${service.name}</span>
                    <span class="service-desc">${service.traffic} | ${service.speed}</span>
                </div>
                <div class="service-price">${service.price}</div>
            </div>
            <button class="btn btn-primary" onclick="buyService(${service.id})">خرید سرویس</button>
        `;
        shopContainer.appendChild(el);
    });

    // Render My Services
    const myServicesContainer = document.getElementById('my-services-list');
    myServices.forEach(srv => {
        const statusColor = srv.status === 'active' ? 'text-accent' : 'text-danger';
        const statusText = srv.status === 'active' ? 'فعال' : 'منقضی';
        
        const el = document.createElement('div');
        el.className = 'service-item';
        el.innerHTML = `
            <div class="service-icon" style="background: ${srv.status === 'active' ? '' : 'rgba(239, 68, 68, 0.1)'}; color: ${srv.status === 'active' ? '' : '#ef4444'}">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="service-details">
                <span class="service-name">${srv.name}</span>
                <span class="service-desc ${statusColor}">${srv.expire} • ${statusText}</span>
            </div>
            <button class="btn btn-outline" style="width: auto; padding: 5px 10px; font-size: 12px;">مدیریت</button>
        `;
        myServicesContainer.appendChild(el);
    });

    // Hide Loader
    setTimeout(() => {
        document.getElementById('loader').classList.add('hidden');
    }, 500);
});

function buyService(id) {
    const tg = window.Telegram.WebApp;
    tg.showPopup({
        title: 'تایید خرید',
        message: 'آیا از خرید این سرویس اطمینان دارید؟',
        buttons: [
            {id: 'buy', type: 'ok', text: 'بله، خرید'},
            {id: 'cancel', type: 'cancel', text: 'انصراف'}
        ]
    }, (btnId) => {
        if (btnId === 'buy') {
            tg.sendData(JSON.stringify({action: 'buy_service', service_id: id}));
            tg.close();
        }
    });
}
