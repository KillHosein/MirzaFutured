import { API } from './api.js';
import { UI } from './ui.js';

window.handlePurchase = async (countryId, serviceId, name, price) => {
    try {
        UI.showLoading();
        // Check if panel needs custom info
        const countriesRes = await API.request('countries', 'GET', null, true);
        const country = countriesRes.obj.find(c => c.id == countryId);
        UI.hideLoading();

        if (country && (country.is_username || country.is_note)) {
            // Show custom input modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm fade-in';
            
            let inputsHtml = '';
            if (country.is_username) {
                inputsHtml += `
                    <div class="mb-4">
                        <label class="block text-gray-400 text-sm mb-2">نام کاربری دلخواه (انگلیسی)</label>
                        <input type="text" id="custom-username" class="w-full bg-gray-800 text-white rounded-xl py-3 px-4 border border-gray-700 focus:border-blue-500 focus:outline-none ltr" placeholder="username">
                    </div>
                `;
            }
            if (country.is_note) {
                inputsHtml += `
                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm mb-2">توضیحات (اختیاری)</label>
                        <input type="text" id="custom-note" class="w-full bg-gray-800 text-white rounded-xl py-3 px-4 border border-gray-700 focus:border-blue-500 focus:outline-none" placeholder="یادداشت...">
                    </div>
                `;
            }

            modal.innerHTML = `
                <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform scale-100 transition-transform">
                    <h3 class="text-xl font-bold text-white mb-4">تکمیل خرید</h3>
                    <p class="text-gray-300 text-sm mb-4">سرویس: ${name}<br>قیمت: ${UI.formatMoney(price)}</p>
                    ${inputsHtml}
                    <div class="flex gap-3">
                        <button id="modal-cancel" class="flex-1 py-2.5 rounded-xl bg-gray-800 text-gray-300 font-medium hover:bg-gray-700 transition-colors">انصراف</button>
                        <button id="modal-confirm" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-500 transition-colors shadow-lg shadow-blue-500/20">خرید نهایی</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            const close = () => {
                modal.classList.add('opacity-0');
                setTimeout(() => modal.remove(), 200);
            };

            modal.querySelector('#modal-cancel').onclick = close;
            modal.querySelector('#modal-confirm').onclick = async () => {
                const customUsername = document.getElementById('custom-username')?.value;
                const customNote = document.getElementById('custom-note')?.value;

                if (country.is_username) {
                    if (!customUsername) {
                        UI.toast('لطفا نام کاربری را وارد کنید', 'error');
                        return;
                    }
                    if (!/^[a-zA-Z0-9_]+$/.test(customUsername)) {
                        UI.toast('نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد و زیرخط باشد', 'error');
                        return;
                    }
                }

                close();
                await executePurchase(countryId, serviceId, customUsername, customNote);
            };

        } else {
            // Normal confirmation
            UI.confirm('تایید خرید', `آیا از خرید سرویس ${name} با قیمت ${UI.formatMoney(price)} مطمئن هستید؟`, async () => {
                await executePurchase(countryId, serviceId);
            });
        }

    } catch (err) {
        UI.hideLoading();
        UI.toast(err.message, 'error');
    }
};

async function executePurchase(countryId, serviceId, customUsername = null, customNote = null) {
    UI.showLoading();
    try {
        const data = {
            country_id: countryId,
            service_id: serviceId
        };
        if (customUsername) data.custom_username = customUsername;
        if (customNote) data.custom_note = customNote;

        const res = await API.request('purchase', 'POST', data);
        if (res.status) {
            UI.toast('خرید با موفقیت انجام شد', 'success');
            location.hash = '#invoices';
        } else {
            throw new Error(res.msg || 'خطا در خرید');
        }
    } catch (err) {
        UI.toast(err.message, 'error');
    } finally {
        UI.hideLoading();
    }
}

export const Pages = {
    async home(container) {
        UI.showLoading();
        try {
            const res = await API.request('user_info');
            if (!res.status) throw new Error(res.msg);
            const user = res.obj;

            // Low Balance Alert
            if (user.balance < 10000) {
                setTimeout(() => {
                    UI.toast('موجودی کیف پول شما کم است. لطفا شارژ کنید.', 'warning');
                }, 1000);
            }

            container.innerHTML = `
                <div class="space-y-4 fade-in">
                    <!-- Balance Card -->
                    <div class="glass-card p-6 rounded-2xl bg-gradient-to-br from-blue-600/20 to-purple-600/20 border border-white/10 relative overflow-hidden">
                        <div class="absolute -right-10 -top-10 w-32 h-32 bg-blue-500/30 rounded-full blur-3xl"></div>
                        <div class="relative z-10">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-gray-400 text-sm">موجودی کیف پول</span>
                                <span class="bg-green-500/20 text-green-400 text-xs px-2 py-1 rounded-full">فعال</span>
                            </div>
                            <div class="text-3xl font-bold text-white mb-4 tracking-tight">${UI.formatMoney(user.balance)}</div>
                            <div class="flex gap-2">
                                <button onclick="location.hash='#deposit'" class="flex-1 bg-white/10 hover:bg-white/20 text-white py-2 rounded-lg text-sm transition-colors backdrop-blur-sm">
                                    <i class="ph ph-plus mr-1"></i> افزایش
                                </button>
                                <button onclick="location.hash='#transactions'" class="flex-1 bg-white/5 hover:bg-white/10 text-gray-300 py-2 rounded-lg text-sm transition-colors">
                                    تراکنش‌ها
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="glass-card p-4 rounded-xl flex flex-col items-center justify-center text-center">
                            <div class="w-10 h-10 rounded-full bg-orange-500/20 text-orange-400 flex items-center justify-center mb-2">
                                <i class="ph ph-shopping-bag text-xl"></i>
                            </div>
                            <span class="text-gray-400 text-xs">سرویس‌های فعال</span>
                            <span class="text-lg font-bold text-white mt-1">${user.count_order}</span>
                        </div>
                        <div class="glass-card p-4 rounded-xl flex flex-col items-center justify-center text-center">
                            <div class="w-10 h-10 rounded-full bg-purple-500/20 text-purple-400 flex items-center justify-center mb-2">
                                <i class="ph ph-users text-xl"></i>
                            </div>
                            <span class="text-gray-400 text-xs">زیرمجموعه‌ها</span>
                            <span class="text-lg font-bold text-white mt-1">${user.affiliatescount || 0}</span>
                        </div>
                    </div>

                    <!-- Chart Section -->
                    <div class="glass-card p-5 rounded-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-gray-200">وضعیت مصرف (ماهانه)</h3>
                            <select class="bg-gray-800 text-xs text-gray-400 rounded px-2 py-1 border border-gray-700 outline-none">
                                <option>این ماه</option>
                                <option>ماه قبل</option>
                            </select>
                        </div>
                        <div class="h-48 w-full">
                            <canvas id="usageChart"></canvas>
                        </div>
                    </div>
                </div>
            `;

            // Init Chart
            setTimeout(() => {
                const ctx = document.getElementById('usageChart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['1', '5', '10', '15', '20', '25', '30'],
                            datasets: [{
                                label: 'مصرف (GB)',
                                data: [12, 19, 15, 25, 22, 30, 28],
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointRadius: 0,
                                pointHoverRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                    ticks: { color: '#9ca3af', font: { family: 'Vazir' } }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#9ca3af', font: { family: 'Vazir' } }
                                }
                            }
                        }
                    });
                }
            }, 100);

        } catch (err) {
            console.error(err);
            UI.toast('خطا در بارگذاری اطلاعات: ' + err.message, 'error');
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center h-64 text-center">
                    <i class="ph ph-warning-circle text-4xl text-red-500 mb-2"></i>
                    <p class="text-gray-400 mb-4">خطا در ارتباط با سرور</p>
                    <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 rounded-lg text-white text-sm">تلاش مجدد</button>
                </div>
            `;
        } finally {
            UI.hideLoading();
        }
    },

    async services(container, countryId = null) {
        UI.showLoading();
        try {
            if (countryId) {
                // Fetch Categories for Country (Cached)
                const res = await API.request('categories', 'GET', { id_panel: countryId }, true);
                if (!res.status) throw new Error(res.msg);
                const categories = res.obj;
                
                let html = `
                    <div class="space-y-4 fade-in">
                        <div class="flex items-center gap-2 mb-4">
                            <button onclick="location.hash='#services'" class="p-2 rounded-lg bg-gray-800 text-white"><i class="ph ph-arrow-right"></i></button>
                            <h2 class="text-lg font-bold text-white">انتخاب دسته‌بندی</h2>
                        </div>
                        <div class="grid grid-cols-1 gap-3">
                `;
                
                categories.forEach(cat => {
                     html += `
                        <div class="glass-card p-4 rounded-xl flex items-center justify-between hover:bg-white/5 transition-colors cursor-pointer" onclick="location.hash='#products/${countryId}/${cat.id}'">
                            <span class="font-bold text-white">${cat.name}</span>
                            <i class="ph ph-caret-left text-gray-500"></i>
                        </div>
                    `;
                });
                html += '</div></div>';
                container.innerHTML = html;
                
            } else {
                // Fetch Countries (Cached)
                const res = await API.request('countries', 'GET', null, true);
                if (!res.status) throw new Error(res.msg);
                const countries = res.obj;

                // Search Logic
                window.filterCountries = (val) => {
                    const term = val.toLowerCase();
                    document.querySelectorAll('.country-item').forEach(el => {
                        const name = el.getAttribute('data-name').toLowerCase();
                        if (name.includes(term)) el.classList.remove('hidden');
                        else el.classList.add('hidden');
                    });
                };

                let html = `
                    <div class="space-y-4 fade-in">
                        <h2 class="text-lg font-bold text-white px-2">انتخاب لوکیشن</h2>
                        
                        <!-- Search -->
                        <div class="relative mb-4">
                            <i class="ph ph-magnifying-glass absolute right-3 top-3 text-gray-400"></i>
                            <input type="text" placeholder="جستجو..." 
                                oninput="window.filterCountries(this.value)"
                                class="w-full bg-gray-800 text-white rounded-xl py-3 pr-10 pl-4 border border-gray-700 focus:border-blue-500 focus:outline-none transition-colors">
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                `;

                countries.forEach(c => {
                    html += `
                        <div class="country-item glass-card p-4 rounded-xl flex items-center justify-between hover:bg-white/5 transition-colors cursor-pointer" 
                             data-name="${c.name}"
                             onclick="location.hash='#services/${c.id}'">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-2xl">
                                    🌍
                                </div>
                                <div>
                                    <div class="font-bold text-white">${c.name}</div>
                                    <div class="text-xs text-gray-400">تحویل آنی</div>
                                </div>
                            </div>
                            <i class="ph ph-caret-left text-gray-500"></i>
                        </div>
                    `;
                });

                html += `</div></div>`;
                container.innerHTML = html;
            }

        } catch (err) {
            UI.toast(err.message, 'error');
            container.innerHTML = '<p class="text-center text-gray-400 mt-10">خطا در دریافت لیست</p>';
        } finally {
            UI.hideLoading();
        }
    },

    async products(container, countryId, catId) {
        UI.showLoading();
        try {
            const res = await API.request('services', 'GET', { id_panel: countryId, category_id: catId });
            if (!res.status) throw new Error(res.msg);
            const products = res.obj;

            let html = `
                <div class="space-y-4 fade-in">
                    <div class="flex items-center gap-2 mb-4">
                        <button onclick="location.hash='#services/${countryId}'" class="p-2 rounded-lg bg-gray-800 text-white"><i class="ph ph-arrow-right"></i></button>
                        <h2 class="text-lg font-bold text-white">انتخاب سرویس</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-3">
            `;

            products.forEach(p => {
                html += `
                    <div class="glass-card p-4 rounded-xl hover:bg-white/5 transition-colors cursor-pointer" onclick="handlePurchase('${countryId}', '${p.id}', '${p.name}', ${p.price})">
                        <div class="flex justify-between items-start mb-2">
                            <div class="font-bold text-white">${p.name}</div>
                            <div class="text-blue-400 font-bold">${UI.formatMoney(p.price)}</div>
                        </div>
                        <div class="text-xs text-gray-400">${p.description || ''}</div>
                        <div class="flex gap-2 mt-3">
                            <span class="text-[10px] bg-gray-700 px-2 py-1 rounded text-gray-300">${p.time_days} روزه</span>
                            <span class="text-[10px] bg-gray-700 px-2 py-1 rounded text-gray-300">${p.traffic_gb} گیگ</span>
                        </div>
                    </div>
                `;
            });
            html += '</div></div>';
            container.innerHTML = html;

        } catch (err) {
            UI.toast(err.message, 'error');
            container.innerHTML = '<p class="text-center text-gray-400 mt-10">خطا در دریافت لیست</p>';
        } finally {
            UI.hideLoading();
        }
    },

    async invoices(container) {
         UI.showLoading();
        try {
            const res = await API.request('invoices', 'GET', { limit: 50, page: 1 }); // Get more for client-side search
            if (!res.status) throw new Error(res.msg);
            
            const invoices = res.obj;
            if (invoices.length === 0) {
                 container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-64 text-center fade-in">
                        <i class="ph ph-receipt text-6xl text-gray-700 mb-4"></i>
                        <p class="text-gray-400">هیچ سرویس فعالی ندارید</p>
                    </div>
                 `;
                 return;
            }

            // Search Logic
            window.filterInvoices = (val) => {
                const term = val.toLowerCase();
                document.querySelectorAll('.invoice-item').forEach(el => {
                    const name = el.getAttribute('data-name').toLowerCase();
                    const note = el.getAttribute('data-note').toLowerCase();
                    if (name.includes(term) || note.includes(term)) el.classList.remove('hidden');
                    else el.classList.add('hidden');
                });
            };

            let html = `
                <div class="space-y-3 fade-in">
                    <!-- Search -->
                    <div class="relative mb-2">
                        <i class="ph ph-magnifying-glass absolute right-3 top-3 text-gray-400"></i>
                        <input type="text" placeholder="جستجو در سرویس‌ها..." 
                            oninput="window.filterInvoices(this.value)"
                            class="w-full bg-gray-800 text-white rounded-xl py-3 pr-10 pl-4 border border-gray-700 focus:border-blue-500 focus:outline-none transition-colors">
                    </div>
            `;

            invoices.forEach(inv => {
                const statusColor = inv.status === 'active' ? 'text-green-400' : 'text-yellow-400';
                const statusText = inv.status === 'active' ? 'فعال' : inv.status;
                
                html += `
                    <div class="invoice-item glass-card p-4 rounded-xl" data-name="${inv.username}" data-note="${inv.note || ''}">
                        <div class="flex justify-between items-start mb-2">
                            <div class="font-bold text-white">${inv.username}</div>
                            <span class="text-xs ${statusColor} bg-white/5 px-2 py-1 rounded">${statusText}</span>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400 mt-2">
                            <span>انقضا: ${inv.expire}</span>
                            <span>${inv.note || ''}</span>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
            container.innerHTML = html;

        } catch (err) {
            UI.toast(err.message, 'error');
             container.innerHTML = '<p class="text-center text-gray-400 mt-10">خطا در دریافت لیست</p>';
        } finally {
             UI.hideLoading();
        }
    },

    async profile(container) {
        // Just show user info
        this.home(container); // Re-use home for now or specific profile UI
    }
};
