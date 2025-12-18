/**
 * ULTRA PRO UI Enhancer v3.0 - "Singularity"
 * 
 * Features:
 * - 🌌 Warp Speed Starfield (Canvas 2D with depth)
 * - 🔊 Audio Feedback Engine (Web Audio API - No assets needed)
 * - 📱 Gyroscope/Mouse Parallax 3D
 * - 🏝️ Dynamic Island Notifications
 * - 🔨 Brute-force Glassmorphism Injection
 * - ⚡ Performance Optimizer (FPS monitoring)
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // ==========================================
    // 0. CORE UTILITIES & STATE
    // ==========================================
    const state = {
        reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        lowPowerMode: false,
        fps: 60,
        audioEnabled: true
    };

    // Performance Monitor
    let lastTime = performance.now();
    let frameCount = 0;
    const checkPerformance = () => {
        const now = performance.now();
        frameCount++;
        if (now - lastTime >= 1000) {
            state.fps = frameCount;
            frameCount = 0;
            lastTime = now;
            if (state.fps < 30) state.lowPowerMode = true;
        }
        requestAnimationFrame(checkPerformance);
    };
    checkPerformance();

    // ==========================================
    // 1. AUDIO FEEDBACK ENGINE (Synthesizer)
    // ==========================================
    const AudioEngine = (() => {
        let ctx = null;
        
        const init = () => {
            if (!state.audioEnabled) return;
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) ctx = new AudioContext();
            } catch (e) { console.warn('Audio API not supported'); }
        };

        const playTone = (freq, type, duration, vol = 0.1) => {
            if (!ctx) init();
            if (!ctx || ctx.state === 'suspended') ctx?.resume();
            if (!ctx) return;

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.type = type;
            osc.frequency.setValueAtTime(freq, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(freq * 0.5, ctx.currentTime + duration);
            
            gain.gain.setValueAtTime(vol, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + duration);
        };

        return {
            click: () => playTone(600, 'sine', 0.15, 0.05),
            hover: () => playTone(300, 'triangle', 0.05, 0.02),
            success: () => {
                playTone(800, 'sine', 0.1, 0.05);
                setTimeout(() => playTone(1200, 'sine', 0.2, 0.05), 100);
            },
            error: () => playTone(150, 'sawtooth', 0.3, 0.05)
        };
    })();

    // Enable Audio on first interaction
    document.addEventListener('click', () => AudioEngine.click(), { once: true });

    // ==========================================
    // 2. WARP SPEED BACKGROUND
    // ==========================================
    const initBackground = () => {
        const canvas = document.getElementById('bg-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        let width, height, stars = [];
        const STAR_COUNT = state.lowPowerMode ? 50 : 150;
        
        // Mouse/Gyro influence
        let moveX = 0, moveY = 0;
        let targetX = 0, targetY = 0;

        const resize = () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
            stars = [];
            for (let i = 0; i < STAR_COUNT; i++) stars.push(new Star());
        };

        class Star {
            constructor() {
                this.reset(true);
            }
            reset(initial = false) {
                this.x = (Math.random() - 0.5) * width * 2; // Spread wider
                this.y = (Math.random() - 0.5) * height * 2;
                this.z = initial ? Math.random() * width : width;
                this.size = Math.random();
                this.color = Math.random() > 0.8 ? '#a855f7' : (Math.random() > 0.5 ? '#06b6d4' : '#ffffff');
            }
            update() {
                // Move towards camera
                this.z -= (state.lowPowerMode ? 5 : 10);
                
                // Parallax shift
                this.x -= (moveX * 0.05) * (width / this.z);
                this.y -= (moveY * 0.05) * (width / this.z);

                if (this.z <= 1) this.reset();
            }
            draw() {
                const x = (this.x / this.z) * width + width / 2;
                const y = (this.y / this.z) * height + height / 2;
                const r = (1 - this.z / width) * 3 * this.size; // Size based on depth
                const alpha = (1 - this.z / width);

                if (x < 0 || x > width || y < 0 || y > height) return;

                ctx.beginPath();
                ctx.arc(x, y, r, 0, Math.PI * 2);
                ctx.fillStyle = this.color;
                ctx.globalAlpha = alpha;
                ctx.fill();
                ctx.globalAlpha = 1;
            }
        }

        const animate = () => {
            // Smooth damping for mouse movement
            moveX += (targetX - moveX) * 0.05;
            moveY += (targetY - moveY) * 0.05;

            ctx.fillStyle = 'rgba(2, 6, 23, 0.3)'; // Trail effect
            ctx.fillRect(0, 0, width, height);

            stars.forEach(star => {
                star.update();
                star.draw();
            });
            
            if (!state.lowPowerMode) requestAnimationFrame(animate);
            else setTimeout(() => requestAnimationFrame(animate), 1000/30);
        };

        window.addEventListener('resize', resize);
        window.addEventListener('mousemove', e => {
            targetX = (e.clientX - width / 2) * 0.5;
            targetY = (e.clientY - height / 2) * 0.5;
        });
        window.addEventListener('deviceorientation', e => {
            if (e.beta && e.gamma) {
                targetX = e.gamma * 5; // Tilt L/R
                targetY = e.beta * 5;  // Tilt F/B
            }
        });

        resize();
        animate();
    };

    // ==========================================
    // 3. UI ENHANCEMENTS (Parallax & Glass)
    // ==========================================
    
    // Parallax Engine for Cards
    const ParallaxEngine = {
        apply: (el) => {
            el.addEventListener('mousemove', (e) => {
                if (state.lowPowerMode) return;
                const rect = el.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -8;
                const rotateY = ((x - centerX) / centerX) * 8;

                el.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
                
                // Spotlight
                el.style.setProperty('--mouse-x', `${x}px`);
                el.style.setProperty('--mouse-y', `${y}px`);
            });

            el.addEventListener('mouseleave', () => {
                el.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
            });
        }
    };

    // Dynamic Island Notification
    window.showNotification = (message, type = 'info') => {
        let island = document.getElementById('dynamic-island');
        if (!island) {
            island = document.createElement('div');
            island.id = 'dynamic-island';
            document.body.appendChild(island);
            // Styles injected below
        }
        
        island.innerHTML = `
            <div class="island-content ${type}">
                <div class="island-icon">${type === 'success' ? '✔' : 'ℹ'}</div>
                <span>${message}</span>
            </div>
        `;
        
        island.classList.add('active');
        AudioEngine.success();
        
        setTimeout(() => {
            island.classList.remove('active');
        }, 3000);
    };

    // ==========================================
    // 4. STYLE INJECTION & ORCHESTRATION
    // ==========================================
    
    // Brute-force Styles
    const style = document.createElement('style');
    style.innerHTML = `
        /* Dynamic Island */
        #dynamic-island {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) scale(0.8);
            width: auto;
            min-width: 120px;
            height: 40px;
            background: #000;
            border-radius: 25px;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 5px 20px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        #dynamic-island.active {
            transform: translateX(-50%) scale(1);
            width: auto;
            min-width: 200px;
            height: 50px;
            opacity: 1;
            padding: 0 20px;
        }
        .island-content { display: flex; align-items: center; gap: 10px; white-space: nowrap; }
        .island-icon { color: #10b981; }

        /* Spotlight Glass */
        .glass-spotlight {
            position: relative;
            background: rgba(20, 20, 35, 0.6) !important;
            backdrop-filter: blur(24px) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .glass-spotlight::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(600px circle at var(--mouse-x, -500px) var(--mouse-y, -500px), rgba(255,255,255,0.08), transparent 40%);
            z-index: 3;
            pointer-events: none;
        }

        /* Forced Dark Mode Overrides */
        .bg-zinc-900, .bg-slate-900, .bg-gray-900, .bg-black, .bg-\[\#18181b\] {
            background: transparent !important;
        }
        
        /* Smooth Scroll Reveal */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }
    `;
    document.head.appendChild(style);

    // Observer Logic
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    
                    // 1. Force Glass
                    if (node.classList.contains('bg-zinc-900') || node.classList.contains('bg-[#18181b]')) {
                        // Check if it looks like a card (has rounded corners)
                        if (node.className.includes('rounded')) {
                            node.classList.add('glass-spotlight');
                            ParallaxEngine.apply(node);
                        } else {
                            node.style.background = 'transparent';
                        }
                    }
                    node.querySelectorAll('.bg-zinc-900, .bg-[#18181b], .card').forEach(el => {
                        if (el.className.includes('rounded')) {
                            el.classList.add('glass-spotlight');
                            ParallaxEngine.apply(el);
                        } else {
                            el.style.background = 'transparent';
                        }
                    });

                    // 2. Buttons
                    const buttons = node.tagName === 'BUTTON' ? [node] : node.querySelectorAll('button, .btn');
                    buttons.forEach(btn => {
                        btn.addEventListener('mouseenter', AudioEngine.hover);
                        btn.addEventListener('click', (e) => {
                            AudioEngine.click();
                            // Ripple
                            const circle = document.createElement("span");
                            const d = Math.max(btn.clientWidth, btn.clientHeight);
                            circle.style.width = circle.style.height = `${d}px`;
                            circle.style.left = `${e.clientX - btn.getBoundingClientRect().left - d/2}px`;
                            circle.style.top = `${e.clientY - btn.getBoundingClientRect().top - d/2}px`;
                            circle.classList.add("ripple");
                            btn.appendChild(circle);
                            setTimeout(() => circle.remove(), 600);
                        });
                    });

                    // 3. Scroll Reveal
                    if (node.classList.contains('card') || node.tagName === 'LI') {
                        node.classList.add('reveal');
                        revealObserver.observe(node);
                    }
                }
            });
        });
    });

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    observer.observe(document.body, { childList: true, subtree: true });

    // --- 5. INITIALIZATION ---
    initBackground();
    
    // Splash Exit
    const splash = document.getElementById('splash-screen');
    if (splash) {
        setTimeout(() => {
            splash.style.opacity = 0;
            splash.style.transform = 'scale(1.1)';
            setTimeout(() => splash.remove(), 800);
        }, 2000);
    }
});
