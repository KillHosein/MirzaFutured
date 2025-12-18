export const UI = {
    showLoading() {
        const el = document.getElementById('app-loading');
        el.classList.remove('hidden');
        el.classList.remove('opacity-0');
    },
    
    hideLoading() {
        const el = document.getElementById('app-loading');
        el.classList.add('opacity-0');
        setTimeout(() => {
            el.classList.add('hidden');
        }, 300);
    },

    toast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const el = document.createElement('div');
        
        let icon = 'info';
        let colorClass = 'border-blue-500';
        if (type === 'success') { icon = 'check-circle'; colorClass = 'border-green-500'; }
        if (type === 'error') { icon = 'warning-circle'; colorClass = 'border-red-500'; }

        el.className = `toast p-4 rounded-lg bg-gray-800 text-white shadow-xl flex items-center gap-3 border-r-4 ${colorClass} transform transition-all duration-300 translate-x-full`;
        el.innerHTML = `
            <i class="ph ph-${icon} text-xl"></i>
            <span class="text-sm font-medium">${message}</span>
        `;
        
        container.appendChild(el);
        
        // Animate in
        requestAnimationFrame(() => {
            el.classList.remove('translate-x-full');
        });

        // Remove
        setTimeout(() => {
            el.classList.add('translate-x-full');
            setTimeout(() => el.remove(), 300);
        }, 3000);
    },

    formatMoney(amount) {
        return new Intl.NumberFormat('fa-IR').format(amount) + ' تومان';
    },

    confirm(title, message, onConfirm) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm fade-in';
        modal.innerHTML = `
            <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform scale-100 transition-transform">
                <h3 class="text-xl font-bold text-white mb-2">${title}</h3>
                <p class="text-gray-300 mb-6">${message}</p>
                <div class="flex gap-3">
                    <button id="modal-cancel" class="flex-1 py-2.5 rounded-xl bg-gray-800 text-gray-300 font-medium hover:bg-gray-700 transition-colors">انصراف</button>
                    <button id="modal-confirm" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-500 transition-colors shadow-lg shadow-blue-500/20">تایید</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const close = () => {
            modal.classList.add('opacity-0');
            setTimeout(() => modal.remove(), 200);
        };

        modal.querySelector('#modal-cancel').onclick = close;
        modal.querySelector('#modal-confirm').onclick = () => {
            close();
            onConfirm();
        };
    },

    promptPurchase(title, serviceName, price, country, onConfirm) {
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
                <h3 class="text-xl font-bold text-white mb-4">${title}</h3>
                <p class="text-gray-300 text-sm mb-4">سرویس: ${serviceName}<br>قیمت: ${this.formatMoney(price)}</p>
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
        modal.querySelector('#modal-confirm').onclick = () => {
            const customUsername = document.getElementById('custom-username')?.value;
            const customNote = document.getElementById('custom-note')?.value;

            if (country.is_username) {
                if (!customUsername) {
                    this.toast('لطفا نام کاربری را وارد کنید', 'error');
                    return;
                }
                if (!/^[a-zA-Z0-9_]+$/.test(customUsername)) {
                    this.toast('نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد و زیرخط باشد', 'error');
                    return;
                }
            }
            if (country.is_note && customNote && customNote.length > 255) {
                this.toast('توضیحات نمی‌تواند بیشتر از 255 کاراکتر باشد', 'error');
                return;
            }

            close();
            onConfirm(customUsername, customNote);
        };
    },

    showLogin(onLogin) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/90 backdrop-blur-md fade-in';
        modal.innerHTML = `
            <div class="bg-gray-800 border border-gray-700 rounded-2xl p-8 w-full max-w-sm shadow-2xl">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 rounded-full bg-blue-600/20 text-blue-500 flex items-center justify-center mx-auto mb-4">
                        <i class="ph ph-lock-key text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">احراز هویت</h2>
                    <p class="text-gray-400 text-sm">لطفا توکن دسترسی خود را وارد کنید</p>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <input type="text" id="login-token" 
                            class="w-full bg-gray-900 text-white text-center tracking-wider font-mono rounded-xl py-3 px-4 border border-gray-700 focus:border-blue-500 focus:outline-none transition-colors" 
                            placeholder="Example: 123456789...">
                    </div>
                    <button id="login-submit" class="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-500 transition-colors shadow-lg shadow-blue-500/20">
                        ورود به پنل
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const submit = () => {
            const token = document.getElementById('login-token').value.trim();
            if (token) {
                onLogin(token);
                modal.remove();
            } else {
                this.toast('لطفا توکن را وارد کنید', 'error');
            }
        };

        document.getElementById('login-submit').onclick = submit;
        document.getElementById('login-token').onkeypress = (e) => {
            if (e.key === 'Enter') submit();
        };
    }
};
