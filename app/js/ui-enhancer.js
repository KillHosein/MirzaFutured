/**
 * UI Enhancer v2.0 - "Quantum Edition"
 * Features: Firefly particles, Haptic Feedback, Smooth Entrance
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 0. Cinematic Splash Screen Exit ---
    const splashScreen = document.getElementById('splash-screen');
    if (splashScreen) {
        setTimeout(() => {
            splashScreen.style.opacity = '0';
            splashScreen.style.transform = 'scale(1.1)'; // Slight zoom out effect
            setTimeout(() => {
                splashScreen.remove();
            }, 600);
        }, 2000); // Allow 2s for brand impression
    }

    // --- 1. Telegram WebApp Integration (Pro Features) ---
    const tg = window.Telegram?.WebApp;
    if (tg) {
        tg.ready();
        tg.expand();
        
        // Theming
        tg.setHeaderColor('#1e1b4b'); 
        tg.setBackgroundColor('#000000');
        
        // Haptic Feedback Helper
        window.triggerHaptic = (style = 'light') => {
            if (tg.HapticFeedback) {
                tg.HapticFeedback.impactOccurred(style);
            }
        };
    } else {
        window.triggerHaptic = () => {}; // Fallback
    }

    // --- 2. "Fireflies" Ambient Background (Optimized) ---
    const canvas = document.getElementById('bg-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let width, height;
        let particles = [];
        
        // Gentle, floating orbs instead of chaotic lines
        const particleCount = 40; 

        const resize = () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        };
        window.addEventListener('resize', resize);
        resize();

        class Firefly {
            constructor() {
                this.reset();
                this.y = Math.random() * height; // Start anywhere
                this.fadeDelay = Math.random() * 100;
            }

            reset() {
                this.x = Math.random() * width;
                this.y = height + 10; // Start from bottom when resetting
                this.speed = Math.random() * 0.5 + 0.2; // Very slow upward float
                this.size = Math.random() * 2 + 0.5;
                this.alpha = 0;
                this.phase = Math.random() * Math.PI * 2; // For pulsing
                this.hue = Math.random() > 0.5 ? 260 : 190; // Purple or Cyan
            }

            update() {
                this.y -= this.speed;
                this.phase += 0.05;
                
                // Horizontal drift
                this.x += Math.sin(this.phase) * 0.3;

                // Pulsing opacity
                this.alpha = (Math.sin(this.phase) + 1) / 2 * 0.8;

                if (this.y < -10) this.reset();
            }

            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                
                // Glow effect
                const gradient = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.size * 4);
                gradient.addColorStop(0, `hsla(${this.hue}, 100%, 70%, ${this.alpha})`);
                gradient.addColorStop(1, `hsla(${this.hue}, 100%, 70%, 0)`);
                
                ctx.fillStyle = gradient;
                ctx.fill();
            }
        }

        function initParticles() {
            particles = [];
            for (let i = 0; i < particleCount; i++) {
                particles.push(new Firefly());
            }
        }

        function animate() {
            ctx.clearRect(0, 0, width, height);
            // Composite operation for glowing effect
            ctx.globalCompositeOperation = 'screen';
            
            particles.forEach(p => {
                p.update();
                p.draw();
            });
            
            ctx.globalCompositeOperation = 'source-over';
            requestAnimationFrame(animate);
        }

        initParticles();
        animate();
    }

    // --- 3. Interaction & Sound Design ---
    const addRipple = (e) => {
        // Trigger Haptic
        window.triggerHaptic('light');

        const btn = e.currentTarget;
        const circle = document.createElement("span");
        const diameter = Math.max(btn.clientWidth, btn.clientHeight);
        const radius = diameter / 2;
        const rect = btn.getBoundingClientRect();

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${e.clientX - rect.left - radius}px`;
        circle.style.top = `${e.clientY - rect.top - radius}px`;
        circle.classList.add("ripple");

        const ripple = btn.querySelector(".ripple");
        if (ripple) ripple.remove();

        btn.appendChild(circle);
    };

    // --- 4. Observer for Animations & Haptics ---
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    
                    // Attach effects to buttons
                    const buttons = node.tagName === 'BUTTON' ? [node] : node.querySelectorAll('button, .btn, a[class*="btn"]');
                    buttons.forEach(btn => {
                        btn.addEventListener('click', addRipple);
                        // Add hover sound logic here if needed
                    });

                    // Staggered Entry Animation for Lists/Cards
                    if (node.classList.contains('card') || node.classList.contains('list-item')) {
                        node.style.opacity = '0';
                        node.style.animation = 'fadeInUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards';
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Inject Styles for JS-created elements
    const style = document.createElement('style');
    style.innerHTML = `
        span.ripple {
            position: absolute;
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            background-color: rgba(255, 255, 255, 0.4);
            pointer-events: none;
        }
        @keyframes ripple {
            to { transform: scale(4); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});