export class DOMManager {
    constructor(engine) {
        this.engine = engine;
        this.injectStyles();
        this.startObserver();
        this.handleSplashScreen();
    }

    handleSplashScreen() {
        const splash = document.getElementById('splash-screen');
        if (splash) {
            setTimeout(() => {
                splash.style.opacity = '0';
                splash.style.transform = 'scale(1.1)';
                setTimeout(() => splash.remove(), 800);
            }, 1500);
        }
    }

    startObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        this.enhanceElement(node);
                        node.querySelectorAll('*').forEach(child => this.enhanceElement(child));
                    }
                });
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
        
        // Initial pass
        document.querySelectorAll('*').forEach(node => this.enhanceElement(node));
    }

    enhanceElement(node) {
        if (this.isDarkElement(node)) {
            this.applyGlass(node);
        }
        if (node.classList.contains('card') || node.tagName === 'LI') {
            node.classList.add('animate-entry');
        }
    }

    isDarkElement(node) {
        if (!node.classList) return false;
        const darkClasses = ['bg-zinc-900', 'bg-gray-900', 'bg-slate-900', 'bg-[#18181b]', 'bg-black'];
        return darkClasses.some(cls => node.classList.contains(cls));
    }

    applyGlass(node) {
        if (node.classList.contains('rounded-2xl') || node.classList.contains('p-4') || node.className.includes('card')) {
            node.classList.add('glass-forced');
        } else {
            node.style.background = 'transparent';
        }
    }

    injectStyles() {
        const style = document.createElement('style');
        style.innerHTML = `
            .ripple-effect {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: ripple-anim 0.6s linear;
                pointer-events: none;
            }
            @keyframes ripple-anim {
                to { transform: scale(4); opacity: 0; }
            }
            .glass-forced {
                background: rgba(20, 20, 35, 0.65) !important;
                backdrop-filter: blur(24px) !important;
                -webkit-backdrop-filter: blur(24px) !important;
                border: 1px solid rgba(255, 255, 255, 0.08) !important;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
            }
            .animate-entry {
                animation: fadeInUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
                opacity: 0;
            }
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    }
}
