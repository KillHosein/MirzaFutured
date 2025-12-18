/**
 * UI Enhancer for Mirza Pro Web App
 * Adds smooth animations, ripple effects, and modern interactions.
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Initialize Telegram WebApp Configuration
    if (window.Telegram && window.Telegram.WebApp) {
        const webApp = window.Telegram.WebApp;
        webApp.ready();
        
        // Set Header Color to match our transparent/dark theme
        webApp.setHeaderColor('#0f172a'); // --bg-dark
        webApp.setBackgroundColor('#0f172a');
        webApp.expand(); // Auto expand
    }

    // 2. Ripple Effect for Buttons
    const addRippleEffect = (e) => {
        const button = e.currentTarget;
        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${e.clientX - button.getBoundingClientRect().left - radius}px`;
        circle.style.top = `${e.clientY - button.getBoundingClientRect().top - radius}px`;
        circle.classList.add("ripple");

        const ripple = button.getElementsByClassName("ripple")[0];
        if (ripple) {
            ripple.remove();
        }

        button.appendChild(circle);
    };

    // 3. Mutation Observer to animate new elements
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) { // Element node
                    // Add ripple to new buttons
                    if (node.tagName === 'BUTTON' || node.classList.contains('btn')) {
                        node.addEventListener('click', addRippleEffect);
                        node.classList.add('fade-in-up'); // Animation class
                    }
                    
                    // Search inside the node for buttons
                    const buttons = node.querySelectorAll('button, .btn');
                    buttons.forEach(btn => {
                        btn.addEventListener('click', addRippleEffect);
                    });

                    // Animate cards and list items
                    if (node.classList.contains('card') || 
                        node.classList.contains('list-item') || 
                        node.tagName === 'LI') {
                        node.classList.add('fade-in-up');
                    }
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // 4. Custom CSS for Animations injected by JS
    const style = document.createElement('style');
    style.innerHTML = `
        /* Ripple Effect */
        span.ripple {
            position: absolute;
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            background-color: rgba(255, 255, 255, 0.3);
            pointer-events: none;
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        button, .btn {
            position: relative;
            overflow: hidden;
        }

        /* Fade In Up Animation */
        .fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Smooth Page Transition */
        body {
            animation: fadeInPage 0.8s ease-out;
        }
        @keyframes fadeInPage {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
});
