(function() {
  // 1. Setup State
  const getTheme = () => {
    if (typeof localStorage !== 'undefined' && localStorage.getItem('theme')) {
      return localStorage.getItem('theme');
    }
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    return 'light';
  };

  const setTheme = (theme) => {
    const root = document.documentElement;
    if (theme === 'dark') {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
    localStorage.setItem('theme', theme);
    updateButtonIcon(theme);
  };

  // 2. Create UI
  const createToggleButton = () => {
    const btn = document.createElement('button');
    btn.id = 'theme-toggle';
    btn.ariaLabel = 'Toggle Dark Mode';
    document.body.appendChild(btn);
    
    btn.addEventListener('click', () => {
      const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
      setTheme(current === 'dark' ? 'light' : 'dark');
    });

    return btn;
  };

  const updateButtonIcon = (theme) => {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    
    // Simple SVG icons
    const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2"/><path d="M12 21v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg>`;
    const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
    
    btn.innerHTML = theme === 'dark' ? sunIcon : moonIcon;
  };

  // 3. Initialize
  const init = () => {
    const initialTheme = getTheme();
    setTheme(initialTheme);
    
    // Wait for body to be ready to append button
    if (document.body) {
      createToggleButton();
      updateButtonIcon(initialTheme);
    } else {
      window.addEventListener('DOMContentLoaded', () => {
        createToggleButton();
        updateButtonIcon(initialTheme);
      });
    }
  };

  init();
})();

