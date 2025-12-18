document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;

    const els = {
        loader: document.getElementById('loader'),
        toast: document.getElementById('toast'),
        navItems: Array.from(document.querySelectorAll('.nav-item')),
        sections: Array.from(document.querySelectorAll('.page-section')),
        reloadBtn: document.getElementById('reload-btn'),
        userName: document.getElementById('user-name'),
        userId: document.getElementById('user-id'),
        userBalance: document.getElementById('user-balance'),
        activeServicesCount: document.getElementById('active-services-count'),
        servicesList: document.getElementById('my-services-list'),
        transactionsList: document.getElementById('transactions-list'),
        privacyToggle: document.getElementById('privacy-toggle'),
        chargeBtn: document.getElementById('charge-btn'),
        supportItem: document.getElementById('support-item'),
        txSearch: document.getElementById('tx-search'),
        txClear: document.getElementById('tx-clear'),
        txChips: Array.from(document.querySelectorAll('.chip')),
        chartCanvas: document.getElementById('usageChart'),
    };

    const params = new URLSearchParams(window.location.search);
    const botParam = (params.get('bot') || '').trim().replace(/^@/, '');
    const supportParam = (params.get('support') || '').trim().replace(/^@/, '');
    const chargeUrl = botParam ? `https://t.me/${botParam}?start=charge` : 'https://t.me/Bot?start=charge';
    const supportUrl = supportParam ? `https://t.me/${supportParam}` : (botParam ? `https://t.me/${botParam}` : 'https://t.me/Support');

    const state = {
        privacyOn: !!tg,
        txQuery: '',
        txFilter: 'all',
        user: null,
        services: [],
        transactions: [],
        filteredTransactions: [],
        chart: null,
        inTelegram: !!tg && typeof tg.initData === 'string' && tg.initData.length > 0,
    };

    if (tg) {
        tg.ready();
        tg.expand();
        document.body.classList.add('telegram-web-app');
        applyTelegramTheme();
    }

    setPrivacy(state.privacyOn, false);
    bindEvents();
    setLoading(true);
    renderSkeletons();
    fetchAndRender();

    function bindEvents() {
        if (els.reloadBtn) {
            els.reloadBtn.addEventListener('click', () => {
                window.location.reload();
            });
        }

        els.navItems.forEach((item) => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = (item.getAttribute('href') || '').slice(1);
                if (!targetId) return;
                navigate(targetId);
                hapticImpact();
            });
        });

        if (els.privacyToggle) {
            els.privacyToggle.addEventListener('click', () => {
                setPrivacy(!state.privacyOn, true);
                hapticImpact();
            });
        }

        if (els.chargeBtn) {
            els.chargeBtn.addEventListener('click', () => {
                openLink(chargeUrl);
                hapticImpact();
            });
        }

        if (els.supportItem) {
            const handler = () => {
                openLink(supportUrl);
                hapticImpact();
            };
            els.supportItem.addEventListener('click', handler);
            els.supportItem.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') handler();
            });
        }

        if (els.txSearch) {
            els.txSearch.addEventListener('input', () => {
                state.txQuery = (els.txSearch.value || '').trim();
                applyTxFilter();
            });
        }

        if (els.txClear) {
            els.txClear.addEventListener('click', () => {
                if (els.txSearch) els.txSearch.value = '';
                state.txQuery = '';
                applyTxFilter();
                hapticImpact();
            });
        }

        els.txChips.forEach((chip) => {
            chip.addEventListener('click', () => {
                const filter = chip.getAttribute('data-filter') || 'all';
                setChipFilter(filter);
                hapticImpact();
            });
        });
    }

    function navigate(targetId) {
        els.navItems.forEach((nav) => nav.classList.toggle('active', (nav.getAttribute('href') || '') === `#${targetId}`));
        els.sections.forEach((sec) => {
            const isTarget = sec.id === targetId;
            sec.style.display = isTarget ? 'block' : 'none';
            sec.style.opacity = isTarget ? '1' : '0';
        });
    }

    function setChipFilter(filter) {
        state.txFilter = filter;
        els.txChips.forEach((chip) => {
            const isActive = (chip.getAttribute('data-filter') || 'all') === filter;
            chip.classList.toggle('active', isActive);
            chip.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        applyTxFilter();
    }

    function applyTelegramTheme() {
        if (!tg || !tg.themeParams) return;
        const root = document.documentElement;
        if (tg.themeParams.bg_color) root.style.setProperty('--bg-color', tg.themeParams.bg_color);
        if (tg.themeParams.text_color) root.style.setProperty('--text-primary', tg.themeParams.text_color);
        if (tg.themeParams.button_color) root.style.setProperty('--accent-color', tg.themeParams.button_color);
        if (tg.themeParams.secondary_bg_color) root.style.setProperty('--card-bg', tg.themeParams.secondary_bg_color);
    }

    function setPrivacy(on, showConfirm) {
        const next = !!on;
        const apply = () => {
            state.privacyOn = next;
            document.body.classList.toggle('privacy-on', state.privacyOn);
            if (els.privacyToggle) {
                const icon = els.privacyToggle.querySelector('i');
                if (icon) icon.className = state.privacyOn ? 'fas fa-eye-slash' : 'fas fa-eye';
            }
        };

        if (showConfirm && !next && tg && typeof tg.showPopup === 'function') {
            tg.showPopup(
                {
                    title: 'نمایش اطلاعات حساس',
                    message: 'آیا می‌خواهید اطلاعات حساس (مثل موجودی) نمایش داده شود؟',
                    buttons: [
                        { id: 'show', type: 'ok', text: 'نمایش' },
                        { id: 'cancel', type: 'cancel', text: 'انصراف' },
                    ],
                },
                (id) => {
                    if (id === 'show') apply();
                }
            );
            return;
        }

        apply();
    }

    function setLoading(on) {
        if (!els.loader) return;
        els.loader.classList.toggle('hidden', !on);
    }

    function renderSkeletons() {
        if (els.servicesList) els.servicesList.innerHTML = skeletonList(3);
        if (els.transactionsList) els.transactionsList.innerHTML = skeletonList(5);
    }

    function skeletonList(count) {
        const items = Array.from({ length: count }).map(
            () =>
                `<div class="skeleton-item" aria-hidden="true"><div class="skeleton-icon"></div><div class="skeleton-lines"><div class="skeleton-line long"></div><div class="skeleton-line short"></div></div></div>`
        );
        return items.join('');
    }

    async function fetchAndRender() {
        try {
            const data = await fetchData();
            hydrate(data);
            render();
            if (!state.inTelegram) showToast('حالت پیش‌نمایش فعال است', 'success');
        } catch (e) {
            showToast('خطا در دریافت اطلاعات', 'error');
        } finally {
            setLoading(false);
        }
    }

    async function fetchData() {
        if (!state.inTelegram) {
            return {
                ok: true,
                user: { id: 123456, name: 'کاربر تستی', username: 'test_user', balance: '۵۰,۰۰۰ تومان', raw_balance: 50000 },
                transactions: [
                    { id: 1, amount: 50000, status: 'success', date: '1402/10/01', description: 'شارژ حساب' },
                    { id: 2, amount: 100000, status: 'success', date: '1402/09/15', description: 'شارژ حساب' },
                    { id: 3, amount: 20000, status: 'success', date: '1402/09/10', description: 'خرید سرویس' },
                    { id: 4, amount: 50000, status: 'success', date: '1402/08/25', description: 'شارژ حساب' },
                ],
                services: [
                    { id: 'a', name: 'سرویس یک ماهه', expire_date: '1402/11/01', status: 'active' },
                    { id: 'b', name: 'سرویس سه ماهه', expire_date: '1402/08/01', status: 'expired' },
                ],
            };
        }

        const resp = await fetch('api/webapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ initData: tg.initData }),
        });
        const json = await resp.json();
        if (!json || !json.ok) {
            throw new Error((json && json.error) || 'API error');
        }
        return json;
    }

    function hydrate(data) {
        state.user = data.user || null;
        state.services = Array.isArray(data.services) ? data.services : [];
        state.transactions = Array.isArray(data.transactions) ? data.transactions.map(normalizeTx) : [];
        state.filteredTransactions = state.transactions.slice();
    }

    function normalizeTx(tx) {
        const amount = toNumber(tx.amount);
        const description = typeof tx.description === 'string' ? tx.description : '';
        const date = typeof tx.date === 'string' ? tx.date : '';
        const id = tx.id != null ? String(tx.id) : '';
        const status = typeof tx.status === 'string' ? tx.status : 'unknown';
        return { id, amount, status, description, date };
    }

    function toNumber(v) {
        if (typeof v === 'number' && Number.isFinite(v)) return v;
        const n = Number(String(v).replace(/[^\d.-]/g, ''));
        return Number.isFinite(n) ? n : 0;
    }

    function render() {
        renderUser();
        renderServices();
        applyTxFilter();
        renderChart();
    }

    function renderUser() {
        const user = state.user;
        if (!user) return;
        if (els.userName) els.userName.textContent = user.name || 'کاربر';
        if (els.userId) els.userId.textContent = `ID: ${user.id || '---'}`;
        if (els.userBalance) els.userBalance.textContent = user.balance || '---';
    }

    function renderServices() {
        if (!els.servicesList) return;
        const services = state.services;
        const activeCount = services.filter((s) => String(s.status || '').toLowerCase() !== 'expired').length;
        if (els.activeServicesCount) els.activeServicesCount.textContent = String(activeCount);

        if (!services.length) {
            els.servicesList.innerHTML = '<div class="empty-state">سرویس فعالی ندارید</div>';
            return;
        }

        els.servicesList.innerHTML = '';
        services.forEach((srv, index) => {
            const status = String(srv.status || '').toLowerCase();
            const isActive = status !== 'expired';
            const name = srv.name || 'سرویس';
            const expire = srv.expire_date ? String(srv.expire_date) : 'نامشخص';
            const item = document.createElement('div');
            item.className = 'list-item service-item';
            item.style.animationDelay = `${index * 0.08}s`;
            item.tabIndex = 0;
            item.innerHTML = `
                <div class="item-icon ${isActive ? 'bg-green' : 'bg-red'}"><i class="fas ${isActive ? 'fa-rocket' : 'fa-ban'}"></i></div>
                <div class="item-content">
                    <span class="item-title">${escapeHtml(name)}</span>
                    <span class="item-subtitle">${isActive ? `انقضا: ${escapeHtml(expire)}` : 'منقضی شده'}</span>
                </div>
                <div class="item-action">
                    <span class="badge ${isActive ? 'badge-success' : ''}" style="${!isActive ? 'background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2)' : ''}">${isActive ? 'فعال' : 'غیرفعال'}</span>
                </div>
            `;
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') item.click();
            });
            els.servicesList.appendChild(item);
        });
    }

    function applyTxFilter() {
        const q = state.txQuery.toLowerCase();
        const f = state.txFilter;
        const filtered = state.transactions.filter((t) => {
            const hay = `${t.description} ${t.date} ${t.id}`.toLowerCase();
            if (q && !hay.includes(q)) return false;
            if (f === 'charge') return t.description.includes('شارژ');
            if (f === 'buy') return t.description.includes('خرید');
            return true;
        });
        state.filteredTransactions = filtered;
        renderTransactions();
    }

    function renderTransactions() {
        if (!els.transactionsList) return;
        const txs = state.filteredTransactions;
        if (!txs.length) {
            els.transactionsList.innerHTML = '<div class="empty-state">نتیجه‌ای یافت نشد</div>';
            return;
        }
        els.transactionsList.innerHTML = '';
        txs.forEach((tx, index) => {
            const isPositive = tx.description.includes('شارژ');
            const item = document.createElement('div');
            item.className = 'list-item';
            item.style.animationDelay = `${index * 0.04}s`;
            item.tabIndex = 0;
            item.innerHTML = `
                <div class="item-icon ${isPositive ? 'bg-blue' : 'bg-orange'}"><i class="fas ${isPositive ? 'fa-wallet' : 'fa-shopping-bag'}"></i></div>
                <div class="item-content">
                    <span class="item-title">${escapeHtml(tx.description || 'تراکنش')}</span>
                    <span class="item-subtitle">${escapeHtml(tx.date || '')}</span>
                </div>
                <div class="item-action">
                    <span class="tx-amount text-primary font-bold sensitive">${formatAmount(tx.amount)}</span>
                </div>
            `;
            item.addEventListener('click', () => {
                if (!tg || typeof tg.showPopup !== 'function') return;
                tg.showPopup({
                    title: 'جزئیات تراکنش',
                    message: `مبلغ: ${formatAmount(tx.amount)}\nتاریخ: ${tx.date || '-'}\nشناسه: ${tx.id || '-'}`,
                    buttons: [{ id: 'ok', type: 'ok', text: 'بستن' }],
                });
            });
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') item.click();
            });
            els.transactionsList.appendChild(item);
        });
    }

    function renderChart() {
        if (!els.chartCanvas || !window.Chart) return;
        const txs = state.transactions.slice(0, 7).reverse();
        const ctx = els.chartCanvas.getContext('2d');
        const labels = txs.map((_, i) => i + 1);
        const dataPoints = txs.map((t) => t.amount);

        if (state.chart) {
            state.chart.destroy();
            state.chart = null;
        }

        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

        state.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        data: dataPoints,
                        borderColor: '#3b82f6',
                        backgroundColor: gradient,
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        titleColor: '#f3f4f6',
                        bodyColor: '#d1d5db',
                        borderColor: 'rgba(255,255,255,0.08)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: (context) => `${formatAmount(context.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: { display: false },
                    y: { display: false, min: 0 },
                },
                interaction: { intersect: false, mode: 'index' },
            },
        });
    }

    function formatAmount(amount) {
        const n = toNumber(amount);
        try {
            return `${n.toLocaleString('fa-IR')} تومان`;
        } catch {
            return `${n} تومان`;
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function openLink(url) {
        if (tg) {
            if (typeof tg.openTelegramLink === 'function' && /^https?:\\/\\/t\\.me\\//i.test(url)) {
                tg.openTelegramLink(url);
                return;
            }
            if (typeof tg.openLink === 'function') {
                tg.openLink(url);
                return;
            }
        }
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function hapticImpact() {
        if (!tg || !tg.HapticFeedback || typeof tg.HapticFeedback.impactOccurred !== 'function') return;
        tg.HapticFeedback.impactOccurred('light');
    }

    function showToast(msg, type) {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `toast show ${type || 'info'}`;
        if (tg && tg.HapticFeedback && typeof tg.HapticFeedback.notificationOccurred === 'function') {
            tg.HapticFeedback.notificationOccurred(type === 'error' ? 'error' : 'success');
        }
        setTimeout(() => {
            els.toast.className = 'toast hidden';
        }, 2800);
    }
});
