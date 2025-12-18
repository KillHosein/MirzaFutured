export class VisualEffects {
    constructor() {
        this.canvas = document.getElementById('bg-canvas');
        this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
        this.particles = [];
        this.resize();
        window.addEventListener('resize', () => this.resize());
        this.initParticles();
    }

    resize() {
        if (!this.canvas) return;
        this.width = this.canvas.width = window.innerWidth;
        this.height = this.canvas.height = window.innerHeight;
    }

    initParticles() {
        if (!this.ctx) return;
        for(let i = 0; i < 60; i++) {
            this.particles.push({
                x: Math.random() * this.width,
                y: Math.random() * this.height,
                vx: (Math.random() - 0.5) * 0.5,
                vy: (Math.random() - 0.5) * 0.5,
                size: Math.random() * 2,
                color: Math.random() > 0.5 ? '#4f46e5' : '#06b6d4'
            });
        }
    }

    render(deltaTime) {
        if (!this.ctx) return;
        
        this.ctx.fillStyle = 'rgba(2, 6, 23, 0.2)';
        this.ctx.fillRect(0, 0, this.width, this.height);
        
        this.ctx.globalCompositeOperation = 'screen';
        
        this.particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;
            
            if (p.x < 0) p.x = this.width;
            if (p.x > this.width) p.x = 0;
            if (p.y < 0) p.y = this.height;
            if (p.y > this.height) p.y = 0;
            
            this.ctx.beginPath();
            this.ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            this.ctx.fillStyle = p.color;
            this.ctx.shadowBlur = 10;
            this.ctx.shadowColor = p.color;
            this.ctx.fill();
        });
        
        this.ctx.globalCompositeOperation = 'source-over';
        this.ctx.shadowBlur = 0;
    }

    createRipple(x, y, element) {
        const circle = document.createElement("span");
        const rect = element.getBoundingClientRect();
        const diameter = Math.max(rect.width, rect.height);
        
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${x - rect.left - diameter/2}px`;
        circle.style.top = `${y - rect.top - diameter/2}px`;
        circle.classList.add("ripple-effect");
        
        const existing = element.querySelector('.ripple-effect');
        if (existing) existing.remove();
        
        element.appendChild(circle);
        
        setTimeout(() => circle.remove(), 600);
    }
}
