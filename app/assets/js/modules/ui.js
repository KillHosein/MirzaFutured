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
    }
};
