const CONFIG = window.__APP_CONFIG__;

export const API = {
    token: localStorage.getItem('app_token'),
    cache: {},
    cacheTTL: 5 * 60 * 1000, // 5 minutes

    setToken(token) {
        if (!token) return;
        this.token = token;
        localStorage.setItem('app_token', token);
    },

    logout() {
        this.token = null;
        localStorage.removeItem('app_token');
        window.location.reload();
    },

    async request(action, method = 'GET', data = null, useCache = false) {
        // Generate cache key
        const cacheKey = `${action}_${JSON.stringify(data)}`;
        
        if (method === 'GET' && useCache) {
            const cached = this.cache[cacheKey];
            if (cached && (Date.now() - cached.timestamp < this.cacheTTL)) {
                return cached.data;
            }
        }

        if (!this.token) {
            // Try to find token in URL
            const urlParams = new URLSearchParams(window.location.search);
            const tokenFromUrl = urlParams.get('token');
            if (tokenFromUrl) {
                this.setToken(tokenFromUrl);
            } else {
                console.warn('No token found');
            }
        }

        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.token}`
        };

        // Construct URL
        let url = `${CONFIG.apiUrl}/miniapp.php?actions=${action}`;
        
        // Append extra params for GET
        if (method === 'GET' && data) {
            Object.keys(data).forEach(key => {
                url += `&${key}=${encodeURIComponent(data[key])}`;
            });
        }

        const options = {
            method,
            headers,
        };

        if (method === 'POST' && data) {
            options.body = JSON.stringify(data);
        }

        try {
            const res = await fetch(url, options);
            // Handle 401/403 (Auth failed)
            if (res.status === 401 || res.status === 403) {
                console.error('Authentication failed');
                localStorage.removeItem('app_token');
                // We rely on modern.js init logic to show login on reload,
                // OR we can throw error and let UI handle it. 
                // But simple reload is safest to reset state.
                // However, reload might cause loop if server always returns 401.
                // So let's throw, and rely on caller to show error, or redirect.
                // Better: Just clear token and let user know.
                
                // If we are in the main app flow, we might want to trigger login.
                // For now, let's just throw, but ensure token is gone.
                throw new Error('Unauthorized');
            }
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            
            // Cache successful GET requests if requested
            if (method === 'GET' && useCache && json.status) {
                this.cache[cacheKey] = {
                    data: json,
                    timestamp: Date.now()
                };
            }

            return json;
        } catch (err) {
            console.error('API Error:', err);
            throw err;
        }
    }
};
