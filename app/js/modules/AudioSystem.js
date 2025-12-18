export class AudioSystem {
    constructor() {
        this.ctx = null;
        this.enabled = true;
        document.addEventListener('click', () => this.initContext(), { once: true });
    }

    initContext() {
        if (!this.enabled) return;
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) this.ctx = new AudioContext();
    }

    play(type) {
        if (!this.ctx) return;
        if (this.ctx.state === 'suspended') this.ctx.resume();

        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        
        const now = this.ctx.currentTime;
        
        if (type === 'click') {
            osc.frequency.setValueAtTime(600, now);
            osc.frequency.exponentialRampToValueAtTime(300, now + 0.1);
            gain.gain.setValueAtTime(0.1, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
            osc.start(now);
            osc.stop(now + 0.1);
        } else if (type === 'success') {
            // Success chord
            [440, 554, 659].forEach((freq, i) => {
                const o = this.ctx.createOscillator();
                const g = this.ctx.createGain();
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.05, now + i * 0.05);
                g.gain.exponentialRampToValueAtTime(0.001, now + 0.3 + i * 0.05);
                o.connect(g);
                g.connect(this.ctx.destination);
                o.start(now + i * 0.05);
                o.stop(now + 0.4);
            });
        }
        
        if (type !== 'success') {
            osc.connect(gain);
            gain.connect(this.ctx.destination);
        }
    }
}
