# Mirza Web App (Optimized)

A professional, modern, and optimized web application interface for Mirza Pro.

## 📁 Project Structure

The project has been reorganized for better maintainability and performance:

```
app/
├── static/              # Static Assets (Optimized)
│   ├── css/             # Stylesheets
│   │   ├── style.css    # Core Application Styles
│   │   ├── theme.css    # Modern Theme & Dark Mode Variables
│   │   └── custom.css   # User-specific Style Overrides
│   ├── js/              # JavaScript Modules
│   │   ├── main.js      # Application Entry Point
│   │   ├── vendor.js    # Third-party Dependencies
│   │   ├── theme-loader.js # Theme Management (Dark/Light)
│   │   └── ...          # Other Feature Modules
│   └── fonts/           # Typography (Vazir Font)
├── index.php            # Main Entry Point (SPA)
├── .htaccess            # Server Configuration (Caching & Routing)
└── README.md            # Documentation

tests/                   # Automated Tests
├── SmokeTest.php        # PHP Integrity Tests
└── run_tests.ps1        # PowerShell Test Runner
```

## 🚀 Key Features

### 1. Modern UI/UX
- **Dark Mode Support**: Automatically detects system preference and includes a toggle.
- **Vazir Font**: Optimized for Persian/Farsi typography.
- **Pre-loader**: Improved perceived performance with an initial loading spinner.
- **Glassmorphism**: Modern visual effects in Dark Mode.

### 2. Performance
- **Asset Organization**: Clean separation of concerns (CSS, JS, Fonts).
- **Caching**: `.htaccess` configured for aggressive caching of static assets (1 Year).
- **Minified Assets**: JavaScript and CSS files are optimized for production.

### 3. Clean Code & Architecture
- **Standard Naming**: File names are descriptive (e.g., `account.js` instead of hashed names).
- **Modular Structure**: Features are separated into distinct modules.
- **Automated Testing**: Scripts to verify deployment integrity.

## 🛠 Installation & Usage

1. **Deploy**: Upload the `app` directory to your web server.
2. **Server Requirements**: Apache with `mod_rewrite` and `mod_expires` enabled.
3. **Access**: Navigate to `https://yourdomain.com/app`.

## 🧪 Testing

To ensure the integrity of the installation, run the provided test scripts:

**PowerShell (Windows):**
```powershell
.\tests\run_tests.ps1
```

**PHP (Linux/Mac/Windows):**
```bash
php tests/SmokeTest.php
```

## 🎨 Theme Customization

1. **Colors**: Edit `app/static/css/theme.css` to change the main color palette.
2. **Overrides**: Add custom CSS to `app/static/css/custom.css` (create this file if needed) to override specific styles without touching the core files.

```css
/* app/static/css/theme.css */
:root {
  --primary: hsl(250, 95%, 60%);
  --background: hsl(0, 0%, 98%);
}
```

## 📄 License
Proprietary - Mirza Pro
