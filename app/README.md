# Mirza Web App (Optimized)

A professional, modern, and optimized web application interface for Mirza Pro.

## 📁 Project Structure

The project has been reorganized for better maintainability and performance:

```
app/
├── static/              # Static Assets (Optimized)
│   ├── css/             # Stylesheets
│   │   ├── style.css    # Core Application Styles
│   │   └── theme.css    # Modern Theme & Dark Mode Variables
│   ├── js/              # JavaScript Modules
│   │   ├── main.js      # Application Entry Point
│   │   ├── vendor.js    # Third-party Dependencies
│   │   ├── theme-loader.js # Theme Management (Dark/Light)
│   │   └── ...          # Other Feature Modules
│   └── fonts/           # Typography (Vazir Font)
├── index.php            # Main Entry Point (SPA)
├── .htaccess            # Server Configuration (Caching & Routing)
└── README.md            # Documentation
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

### 3. Clean Code
- **Standard Naming**: File names are descriptive (e.g., `account.js` instead of hashed names).
- **Modular Structure**: Features are separated into distinct modules.

## 🛠 Installation & Usage

1. **Deploy**: Upload the `app` directory to your web server.
2. **Server Requirements**: Apache with `mod_rewrite` and `mod_expires` enabled.
3. **Access**: Navigate to `https://yourdomain.com/app`.

## 🎨 Theme Customization

To modify the color palette, edit `app/static/css/theme.css`:

```css
:root {
  /* Light Mode Colors */
  --primary: hsl(250, 95%, 60%);
  --background: hsl(0, 0%, 98%);
}

.dark {
  /* Dark Mode Colors */
  --primary: hsl(250, 95%, 65%);
  --background: hsl(222, 47%, 4%);
}
```

## 📄 License
Proprietary - Mirza Pro
