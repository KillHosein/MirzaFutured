import { Pages } from './pages.js';

export const Router = {
    init() {
        window.addEventListener('hashchange', () => this.handleRoute());
        this.handleRoute();
    },

    handleRoute() {
        let hash = window.location.hash.slice(1);
        if (!hash) hash = 'home';
        
        // Handle params e.g. services/123
        const parts = hash.split('/');
        const route = parts[0];
        
        const container = document.getElementById('main-content');
        
        // Update nav active state
        document.querySelectorAll('.nav-item').forEach(el => {
            const href = el.getAttribute('href').slice(1); // remove #
            if (href === route) {
                el.classList.add('active');
                // Animate icon
                const icon = el.querySelector('i');
                icon.classList.add('animate-bounce');
                setTimeout(() => icon.classList.remove('animate-bounce'), 1000);
            } else {
                el.classList.remove('active');
            }
        });

        // Clear container content before render? Or let page handle it?
        // Let's clear to avoid duplication
        container.innerHTML = '';

        if (route === 'home') Pages.home(container);
        else if (route === 'services') Pages.services(container, parts[1]); // Pass ID if any
        else if (route === 'products') Pages.products(container, parts[1], parts[2]); // countryId, catId
        else if (route === 'invoices') Pages.invoices(container);
        else if (route === 'profile') Pages.profile(container);
        else Pages.home(container);
    }
};
