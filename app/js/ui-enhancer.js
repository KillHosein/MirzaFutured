/**
 * ULTRA PRO UI Enhancer for Mirza Pro Web App
 * Features:
 * - Interactive Particle Background
 * - 3D Tilt & Spotlight Effects (Apple TV Style)
 * - Scroll Reveal Animations
 * - Magnetic Buttons
 * - Haptic Feedback
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 0. Splash Screen Management ---
    const splashScreen = document.getElementById('splash-screen');
    if (splashScreen) {
        setTimeout(() => {
            splashScreen.style.opacity = '0';
            splashScreen.style.visibility = 'hidden';
            setTimeout(() => {
                splashScreen.remove();
            }, 600);
        }, 1500);
    }

    // --- 1. Telegram WebApp Integration ---
    if (window.Telegram && window.Telegram.WebApp) {
        const webApp = window.Telegram.WebApp;
        webApp.ready();
        webApp.setHeaderColor('#1e1b4b'); 
        webApp.setBackgroundColor('#0f172a');
        webApp.expand();
    }

    // --- 2. Advanced Particle System ---
    const canvas = document.getElementById('bg-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let width, height;
        let particles = [];
        const particleCount = 50; // Optimized count
        const connectionDistance = 120;
        const mouseDistance = 180;
        let mouse = { x: null, y: null };

        const resize = () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        };
        window.addEventListener('resize', resize);
        resize();

        window.addEventListener('mousemove', (e) => { mouse.x = e.clientX; mouse.y = e.clientY; });
        window.addEventListener('touchmove', (e) => { mouse.x = e.touches[0].clientX; mouse.y = e.touches[0].clientY; });

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.vx = (Math.random() - 0.5) * 0.3;
                this.vy = (Math.random() - 0.5) * 0.3;
                this.size = Math.random() * 2 + 0.5;
                this.baseColor = 'rgba(99, 102, 241, ';
            }
            update() {
                this.x += this.vx;
                this.y += this.vy;
                if (this.x < 0 || this.x > width) this.vx *= -1;
                if (this.y < 0 || this.y > height) this.vy *= -1;

                if (mouse.x != null) {
                    let dx = mouse.x - this.x;
                    let dy = mouse.y - this.y;
                    let distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < mouseDistance) {
                        const forceDirectionX = dx / distance;
                        const forceDirectionY = dy / distance;
                        const force = (mouseDistance - distance) / mouseDistance;
                        const directionX = forceDirectionX * force * 1.5;
                        const directionY = forceDirectionY * force * 1.5;
                        this.vx -= directionX * 0.05;
                        this.vy -= directionY * 0.05;
                    }
                }
            }
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = this.baseColor + '0.4)';
                ctx.fill();
            }
        }

        function initParticles() {
            particles = [];
            for (let i = 0; i < particleCount; i++) particles.push(new Particle());
        }

        function animateParticles() {
            ctx.clearRect(0, 0, width, height);
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
                for (let j = i; j < particles.length; j++) {
                    let dx = particles[i].x - particles[j].x;
                    let dy = particles[i].y - particles[j].y;
                    let distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < connectionDistance) {
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(139, 92, 246, ${0.1 - distance/connectionDistance * 0.1})`;
                        ctx.lineWidth = 0.5;
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

    // --- 3. 3D Tilt & Spotlight Effect (Apple Style) ---
    const applyTiltEffect = (element) => {
        element.addEventListener('mousemove', (e) => {
            const rect = element.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Spotlight
            element.style.setProperty('--mouse-x', `${x}px`);
            element.style.setProperty('--mouse-y', `${y}px`);
            
            // Tilt
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const rotateX = ((y - centerY) / centerY) * -5; // Max 5 deg
            const rotateY = ((x - centerX) / centerX) * 5;
            
            element.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
        });

        element.addEventListener('mouseleave', () => {
            element.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
            element.style.setProperty('--mouse-x', `-999px`);
            element.style.setProperty('--mouse-y', `-999px`);
        });
    };

    // --- 4. Magnetic Button Effect ---
    const applyMagneticEffect = (element) => {
        element.addEventListener('mousemove', (e) => {
            const rect = element.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            
            // Move button slightly towards cursor
            element.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
        });

        element.addEventListener('mouseleave', () => {
            element.style.transform = 'translate(0px, 0px)';
        });
    };

    // --- 5. Scroll Reveal Observer ---
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target); // Reveal once
            }
        });
    }, { threshold: 0.1 });

    // --- 6. Mutation Observer (The Orchestrator) ---
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) { // Element node
                    
                    // Button Ripple & Magnetic
                    if (node.tagName === 'BUTTON' || node.classList.contains('btn')) {
                        node.addEventListener('click', addRippleEffect);
                        applyMagneticEffect(node);
                    }
                    node.querySelectorAll('button, .btn').forEach(btn => {
                        btn.addEventListener('click', addRippleEffect);
                        applyMagneticEffect(btn);
                    });

                    // Card Tilt & Spotlight
                    if (node.classList.contains('card') || node.classList.contains('list-item')) {
                        node.classList.add('glass-spotlight'); // Add class for CSS
                        applyTiltEffect(node);
                        revealObserver.observe(node);
                        node.classList.add('reveal-on-scroll');
                    }
                    
                    // List Items
                    if (node.tagName === 'LI') {
                        revealObserver.observe(node);
                        node.classList.add('reveal-on-scroll');
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Helper: Ripple Effect
    function addRippleEffect(e) {
        const button = e.currentTarget;
        
        // Haptic Feedback
        if (window.Telegram?.WebApp?.HapticFeedback) {
            window.Telegram.WebApp.HapticFeedback.impactOccurred('light');
        }

        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${e.clientX - button.getBoundingClientRect().left - radius}px`;
        circle.style.top = `${e.clientY - button.getBoundingClientRect().top - radius}px`;
        circle.classList.add("ripple");
        const ripple = button.getElementsByClassName("ripple")[0];
        if (ripple) ripple.remove();
        button.appendChild(circle);
    }

    // --- 7. Inject Advanced Styles ---
    const style = document.createElement('style');
    style.innerHTML = `
        /* Ripple */
        span.ripple {
            position: absolute;
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            background-color: rgba(255, 255, 255, 0.3);
            pointer-events: none;
        }
        @keyframes ripple { to { transform: scale(4); opacity: 0; } }

        /* Scroll Reveal */
        .reveal-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s cubic-bezier(0.2, 0.8, 0.2, 1), transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .reveal-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Spotlight Glass Effect */
        .glass-spotlight {
            position: relative;
            overflow: hidden;
        }
        .glass-spotlight::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(
                600px circle at var(--mouse-x, -999px) var(--mouse-y, -999px),
                rgba(255, 255, 255, 0.1),
                transparent 40%
            );
            z-index: 3;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        /* Smooth Page Entry */
        body { animation: fadeInPage 0.8s ease-out; }
        @keyframes fadeInPage { from { opacity: 0; } to { opacity: 1; } }
    `;
    document.head.appendChild(style);
});
