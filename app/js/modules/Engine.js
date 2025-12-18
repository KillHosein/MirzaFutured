import { CONFIG } from './Config.js';
import { StateManager } from './StateManager.js';
import { AudioSystem } from './AudioSystem.js';
import { VisualEffects } from './VisualEffects.js';
import { PhysicsSystem } from './PhysicsSystem.js';
import { DOMManager } from './DOMManager.js';

export class Engine {
    constructor() {
        this.modules = {};
        this.isRunning = false;
        this.init();
    }

    init() {
        this.modules.state = new StateManager();
        this.modules.audio = new AudioSystem();
        this.modules.fx = new VisualEffects();
        this.modules.physics = new PhysicsSystem();
        this.modules.dom = new DOMManager(this);
        
        this.startLoop();
        this.setupEvents();
        
        console.log('%c MIRZA PRO ENGINE v4.0 (MODULES) INITIALIZED ', 'background: #4f46e5; color: #fff; padding: 4px; border-radius: 4px;');
    }

    startLoop() {
        this.isRunning = true;
        let lastTime = 0;
        
        const loop = (timestamp) => {
            if (!this.isRunning) return;
            const deltaTime = timestamp - lastTime;
            lastTime = timestamp;

            this.modules.fx.render(deltaTime);
            this.modules.physics.update(deltaTime);
            
            requestAnimationFrame(loop);
        };
        requestAnimationFrame(loop);
    }

    setupEvents() {
        document.addEventListener('click', (e) => this.handleInteraction(e, 'click'));
        document.addEventListener('mousemove', (e) => this.handleInteraction(e, 'hover'));
        
        if (window.Telegram?.WebApp) {
            const tg = window.Telegram.WebApp;
            tg.ready();
            tg.expand();
            tg.setHeaderColor(CONFIG.theme.colors.bg);
            tg.setBackgroundColor(CONFIG.theme.colors.bg);
        }
    }

    handleInteraction(e, type) {
        const target = e.target.closest('button, .btn, .card, .list-item, input');
        if (!target) return;

        if (type === 'click') {
            this.modules.audio.play('click');
            this.modules.fx.createRipple(e.clientX, e.clientY, target);
            if (window.Telegram?.WebApp?.HapticFeedback) {
                window.Telegram.WebApp.HapticFeedback.impactOccurred('light');
            }
        } else if (type === 'hover') {
            this.modules.physics.applyTilt(target, e.clientX, e.clientY);
        }
    }
}
