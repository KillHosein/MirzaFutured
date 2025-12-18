# Mirza Pro Web App

## Overview
This is a modern, responsive web application for the Mirza Pro VPN panel. It uses a modular JavaScript architecture and Tailwind CSS for styling.

## Directory Structure
- `index.php`: Main entry point. Handles configuration and loads assets.
- `assets/`: Contains static assets.
  - `css/`: Stylesheets (Tailwind output/custom CSS).
  - `js/`: JavaScript files.
    - `modules/`: ES6 modules (API, Router, UI, Pages).
    - `modern.js`: Main application logic.
  - `fonts/`: Local fonts (Vazir).

## Features
- **Modern UI**: Glass-morphism design, dark mode support.
- **SPA Architecture**: Client-side routing for smooth navigation.
- **Telegram WebApp Integration**: Automatically detects and adapts to Telegram environment.
- **Performance**: API caching, lazy loading, optimized assets.
- **Security**: Token-based authentication, input validation.

## Development
1. **Setup**: Ensure PHP is running. Point your web server to `app/`.
2. **Configuration**: `config.php` in the parent directory handles database connections.
3. **API**: The frontend communicates with `../api/miniapp.php`.

## JavaScript Modules
- **api.js**: Handles API requests, token management, and caching.
- **router.js**: Manages URL hash-based routing.
- **ui.js**: UI utilities (loading, toast, formatting).
- **pages.js**: Renders page content (Home, Services, Products, Invoices).

## Key Functions
- `Pages.home()`: Renders dashboard with charts.
- `Pages.services()`: Lists countries and categories.
- `Pages.products()`: Lists available services.
- `handlePurchase()`: Manages purchase flow with validation.

## Customization
To change colors or fonts, edit `tailwind.config` in `index.php` or `assets/css/modern.css`.
