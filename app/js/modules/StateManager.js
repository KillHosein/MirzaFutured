export class StateManager {
    constructor() {
        this.state = {};
        this.listeners = new Map();
    }

    set(key, value) {
        this.state[key] = value;
        this.notify(key, value);
    }

    get(key) {
        return this.state[key];
    }

    subscribe(key, callback) {
        if (!this.listeners.has(key)) {
            this.listeners.set(key, new Set());
        }
        this.listeners.get(key).add(callback);
    }

    notify(key, value) {
        if (this.listeners.has(key)) {
            this.listeners.get(key).forEach(cb => cb(value));
        }
    }
}
