export class PhysicsSystem {
    constructor() {
        this.activeElements = new Map();
    }

    update(deltaTime) {
        // Future: Implement spring physics
    }

    applyTilt(element, mouseX, mouseY) {
        const rect = element.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        
        const rotateX = ((mouseY - centerY) / (rect.height / 2)) * -5;
        const rotateY = ((mouseX - centerX) / (rect.width / 2)) * 5;

        element.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
        element.style.setProperty('--mouse-x', `${mouseX - rect.left}px`);
        element.style.setProperty('--mouse-y', `${mouseY - rect.top}px`);
        
        if (!element.dataset.tiltInit) {
            element.dataset.tiltInit = "true";
            element.addEventListener('mouseleave', () => {
                element.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
            });
        }
    }
}
