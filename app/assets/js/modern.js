import { API } from './modules/api.js';
import { Router } from './modules/router.js';
import { UI } from './modules/ui.js';

document.addEventListener('DOMContentLoaded', async () => {
    // Theme Init
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark'); // Default to dark anyway via body class
    }

    // Toggle Theme
    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        });
    }

    // Telegram Init
    if (window.Telegram && window.Telegram.WebApp) {
        window.Telegram.WebApp.ready();
        window.Telegram.WebApp.expand();
        
        // Style adjustments for Telegram
        document.body.style.backgroundColor = window.Telegram.WebApp.backgroundColor;
    }

    // Initial Auth Check
    // If no token, we can't do anything.
    if (!API.token) {
        // Try to get from URL (API module does this lazily, but let's check now)
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        if (token) {
            API.setToken(token);
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        } else {
             // Show Auth Error if we really can't find it
             // But Router.init() will trigger Pages.home() which calls API.request()
             // API.request() will try to find token again or fail.
        }
    }

    // Initialize Router
    Router.init();
});
