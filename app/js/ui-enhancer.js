/**
 * UI Enhancer for Mirza Pro Web App
 * Adds smooth animations, ripple effects, particle background, and modern interactions.
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 0. Splash Screen Management ---
    const splashScreen = document.getElementById('splash-screen');
    if (splashScreen) {
        // Ensure splash screen stays for at least 1.5 seconds for branding impact
        setTimeout(() => {
            splashScreen.style.opacity = '0';
            splashScreen.style.visibility = 'hidden';
            setTimeout(() => {
                splashScreen.remove(); // Remove from DOM for performance
            }, 600);
        }, 1500);
    }

    // --- 1. Initialize Telegram WebApp Configuration ---
    if (window.Telegram && window.Telegram.WebApp) {
        const webApp = window.Telegram.WebApp;
        webApp.ready();
        
        // Set Header Color to match our transparent/dark theme
        webApp.setHeaderColor('#1e1b4b'); // Match gradient top color
        webApp.setBackgroundColor('#0f172a');
        webApp.expand(); // Auto expand
    }

    // --- 2. Interactive Particle Background ---
    const canvas = document.getElementById('bg-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let width, height;
        let particles = [];
        
        // Configuration
        const particleCount = 60; // Lightweight count
        const connectionDistance = 100;
        const mouseDistance = 150;

        let mouse = { x: null, y: null };

        // Resize Handler
        const resize = () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        };
        window.addEventListener('resize', resize);
        resize();

        // Mouse Move
        window.addEventListener('mousemove', (e) => {
            mouse.x = e.x;
            mouse.y = e.y;
        });
        
        // Touch Move (Mobile support)
        window.addEventListener('touchmove', (e) => {
            mouse.x = e.touches[0].clientX;
            mouse.y = e.touches[0].clientY;
        });

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.vx = (Math.random() - 0.5) * 0.5; // Slow movement
                this.vy = (Math.random() - 0.5) * 0.5;
                this.size = Math.random() * 2 + 0.5;
                this.baseColor = 'rgba(99, 102, 241, '; // Indigo base
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;

                // Bounce off edges
                if (this.x < 0 || this.x > width) this.vx *= -1;
                if (this.y < 0 || this.y > height) this.vy *= -1;

                // Mouse interaction
                if (mouse.x != null) {
                    let dx = mouse.x - this.x;
                    let dy = mouse.y - this.y;
                    let distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < mouseDistance) {
                        const forceDirectionX = dx / distance;
                        const forceDirectionY = dy / distance;
                        const force = (mouseDistance - distance) / mouseDistance;
                        const directionX = forceDirectionX * force * 2; // Repel force
                        const directionY = forceDirectionY * force * 2;
                        this.vx -= directionX * 0.05;
                        this.vy -= directionY * 0.05;
                    }
                }
            }

            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = this.baseColor + '0.5)';
                ctx.fill();
            }
        }

        function initParticles() {
            particles = [];
            for (let i = 0; i < particleCount; i++) {
                particles.push(new Particle());
            }
        }

        function animateParticles() {
            ctx.clearRect(0, 0, width, height);
            
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();

                // Draw connections
                for (let j = i; j < particles.length; j++) {
                    let dx = particles[i].x - particles[j].x;
                    let dy = particles[i].y - particles[j].y;
                    let distance = Math.sqrt(dx * dx + dy * dy);

                    if (distance < connectionDistance) {
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(139, 92, 246, ${0.15 - distance/connectionDistance * 0.15})`; // Purple tint
                        ctx.lineWidth = 1;
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(animateParticles);
        }

        initParticles();
        animateParticles();
    }

    // --- 3. Ripple Effect for Buttons ---
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

    // --- 4. Mutation Observer to animate new elements ---
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

    // --- 5. Custom CSS for Animations injected by JS ---
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
