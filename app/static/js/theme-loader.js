/**
 * Theme Manager for Mirza Web App
 * Handles Dark/Light mode toggling, persistence, and UI rendering.
 */
(function() {
  'use strict';

  // Constants
  const STORAGE_KEY = 'theme';
  const DARK_CLASS = 'dark';
  const THEME_DARK = 'dark';
  const THEME_LIGHT = 'light';

  /**
   * Retrieves the preferred theme from storage or system settings.
   * @returns {string} 'dark' or 'light'
   */
  const getTheme = () => {
    if (typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY)) {
      return localStorage.getItem(STORAGE_KEY);
    }
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return THEME_DARK;
    }
    return THEME_LIGHT;
  };

  /**
   * Applies the theme to the document and updates storage.
   * @param {string} theme - 'dark' or 'light'
   */
  const setTheme = (theme) => {
    const root = document.documentElement;
    if (theme === THEME_DARK) {
      root.classList.add(DARK_CLASS);
    } else {
      root.classList.remove(DARK_CLASS);
    }
    localStorage.setItem(STORAGE_KEY, theme);
    updateButtonIcon(theme);
  };

  /**
   * Creates the floating toggle button and appends it to the DOM.
   * @returns {HTMLElement} The created button
   */
  const createToggleButton = () => {
    // Prevent duplicate buttons
    if (document.getElementById('theme-toggle')) return;

    const btn = document.createElement('button');
    btn.id = 'theme-toggle';
    btn.ariaLabel = 'تغییر حالت شب/روز'; // Persian label
    btn.title = 'تغییر پوسته';
    document.body.appendChild(btn);
    
    btn.addEventListener('click', () => {
      const current = document.documentElement.classList.contains(DARK_CLASS) ? THEME_DARK : THEME_LIGHT;
      setTheme(current === THEME_DARK ? THEME_LIGHT : THEME_DARK);
    });

    return btn;
  };

  /**
   * Updates the button icon based on the current theme.
   * @param {string} theme 
   */
  const updateButtonIcon = (theme) => {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    
    const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2"/><path d="M12 21v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg>`;
    const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
    
    btn.innerHTML = theme === THEME_DARK ? sunIcon : moonIcon;
  };

  /**
   * Initialize the Theme Manager
   */
  const init = () => {
    const initialTheme = getTheme();
    setTheme(initialTheme);
    
    const onReady = () => {
      createToggleButton();
      updateButtonIcon(initialTheme);
    };

    if (document.body) {
      onReady();
    } else {
      window.addEventListener('DOMContentLoaded', onReady);
    }
  };

  init();
})();
