/**
 * Custom JavaScript for Mirza Web App
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

// Telegram Web App integration
let tg = window.Telegram.WebApp;
let user = null;

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    loadUserData();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Check if running in Telegram Web App
    if (tg) {
        console.log('Telegram Web App detected');
        
        // Get user data
        user = tg.initDataUnsafe.user;
        if (user) {
            console.log('User data:', user);
            updateUIWithUserData(user);
        }
        
        // Expand Web App
        tg.expand();
        
        // Set theme
        applyTheme();
        
        // Enable closing confirmation
        tg.enableClosingConfirmation();
        
        // Set main button
        setupMainButton();
        
        // Set background color
        tg.setBackgroundColor(tg.themeParams.bg_color || '#ffffff');
        
        // Set header color
        tg.setHeaderColor(tg.themeParams.bg_color || '#0088cc');
    } else {
        console.log('Not running in Telegram Web App');
        showNotInTelegramMessage();
    }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Theme change event
    if (tg) {
        tg.onEvent('themeChanged', function() {
            applyTheme();
        });
        
        // Viewport changed event
        tg.onEvent('viewportChanged', function() {
            adjustLayout();
        });
        
        // Main button clicked event
        tg.onEvent('mainButtonClicked', function() {
            handleMainButtonClick();
        });
    }
    
    // Window resize
    window.addEventListener('resize', function() {
        adjustLayout();
    });
    
    // Form submissions
    document.addEventListener('submit', function(e) {
        handleFormSubmit(e);
    });
    
    // Button clicks
    document.addEventListener('click', function(e) {
        handleButtonClick(e);
    });
}

/**
 * Apply Telegram theme
 */
function applyTheme() {
    if (!tg || !tg.themeParams) return;
    
    const theme = tg.themeParams;
    const root = document.documentElement;
    
    // CSS custom properties
    root.style.setProperty('--tg-theme-bg-color', theme.bg_color || '#ffffff');
    root.style.setProperty('--tg-theme-text-color', theme.text_color || '#000000');
    root.style.setProperty('--tg-theme-button-color', theme.button_color || '#0088cc');
    root.style.setProperty('--tg-theme-button-text-color', theme.button_text_color || '#ffffff');
    root.style.setProperty('--tg-theme-secondary-bg-color', theme.secondary_bg_color || '#f1f1f1');
    root.style.setProperty('--tg-theme-hint-color', theme.hint_color || '#999999');
    root.style.setProperty('--tg-theme-link-color', theme.link_color || '#0088cc');
    root.style.setProperty('--tg-theme-accent-text-color', theme.accent_text_color || '#0088cc');
    
    // Apply background color
    document.body.style.backgroundColor = theme.bg_color || '#ffffff';
    document.body.style.color = theme.text_color || '#000000';
}

/**
 * Setup main button
 */
function setupMainButton() {
    if (!tg) return;
    
    tg.MainButton.setText('بستن اپلیکیشن');
    tg.MainButton.show();
    tg.MainButton.enable();
}

/**
 * Handle main button click
 */
function handleMainButtonClick() {
    if (tg) {
        showConfirm('آیا می‌خواهید اپلیکیشن را ببندید؟', function() {
            tg.close();
        });
    }
}

/**
 * Load user data from API
 */
function loadUserData() {
    if (!user) return;
    
    apiRequest('user')
        .then(data => {
            if (data.user) {
                updateUIWithUserData(data.user);
            }
        })
        .catch(error => {
            console.error('Error loading user data:', error);
        });
}

/**
 * Update UI with user data
 */
function updateUIWithUserData(userData) {
    // Update avatar
    const avatar = document.querySelector('.avatar');
    if (avatar) {
        avatar.textContent = userData.first_name.charAt(0).toUpperCase();
    }
    
    // Update user name
    const userNameElements = document.querySelectorAll('.user-name');
    userNameElements.forEach(element => {
        element.textContent = userData.first_name + (userData.last_name ? ' ' + userData.last_name : '');
    });
    
    // Update username
    const usernameElements = document.querySelectorAll('.user-username');
    usernameElements.forEach(element => {
        element.textContent = '@' + (userData.username || 'بدون نام کاربری');
    });
    
    // Update language
    const languageSelect = document.getElementById('language');
    if (languageSelect && userData.language_code) {
        languageSelect.value = userData.language_code;
    }
}

/**
 * Handle form submissions
 */
function handleFormSubmit(e) {
    const form = e.target;
    
    // Check if form has data-action attribute
    if (form.dataset.action) {
        e.preventDefault();
        
        switch (form.dataset.action) {
            case 'update-settings':
                handleSettingsUpdate(form);
                break;
            case 'send-message':
                handleSendMessage(form);
                break;
            default:
                console.log('Unknown form action:', form.dataset.action);
        }
    }
}

/**
 * Handle button clicks
 */
function handleButtonClick(e) {
    const button = e.target.closest('button');
    if (!button) return;
    
    const action = button.dataset.action;
    if (!action) return;
    
    e.preventDefault();
    
    switch (action) {
        case 'show-user-info':
            showUserInfo();
            break;
        case 'show-stats':
            showStats();
            break;
        case 'share-app':
            shareApp();
            break;
        case 'contact-support':
            contactSupport();
            break;
        case 'export-data':
            exportData();
            break;
        case 'clear-cache':
            clearCache();
            break;
        case 'delete-account':
            deleteAccount();
            break;
        case 'load-activity':
            loadActivity();
            break;
        default:
            console.log('Unknown button action:', action);
    }
}

/**
 * API request function
 */
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    const requestOptions = { ...defaultOptions, ...options };
    
    showLoading();
    
    try {
        const response = await fetch(`/app/index.php?action=api&endpoint=${endpoint}`, requestOptions);
        const data = await response.json();
        
        hideLoading();
        
        if (!response.ok) {
            throw new Error(data.error || 'API request failed');
        }
        
        return data;
    } catch (error) {
        hideLoading();
        showAlert('خطا: ' + error.message);
        throw error;
    }
}

/**
 * Show loading spinner
 */
function showLoading() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.style.display = 'block';
    }
}

/**
 * Hide loading spinner
 */
function hideLoading() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.style.display = 'none';
    }
}

/**
 * Show alert message
 */
function showAlert(message) {
    if (tg) {
        tg.showAlert(message);
    } else {
        alert(message);
    }
}

/**
 * Show confirm dialog
 */
function showConfirm(message, onConfirm, onCancel) {
    if (tg) {
        tg.showConfirm(message, function(result) {
            if (result && onConfirm) onConfirm();
            else if (!result && onCancel) onCancel();
        });
    } else {
        if (confirm(message) && onConfirm) onConfirm();
        else if (!result && onCancel) onCancel();
    }
}

/**
 * Show popup
 */
function showPopup(message, title = '') {
    if (tg) {
        tg.showPopup({
            title: title,
            message: message,
            buttons: [{ type: 'close' }]
        });
    } else {
        alert((title ? title + '\n\n' : '') + message);
    }
}

/**
 * Show user info
 */
function showUserInfo() {
    apiRequest('user')
        .then(data => {
            const user = data.user;
            showPopup(`
نام: ${user.first_name} ${user.last_name || ''}
نام کاربری: @${user.username || 'ندارد'}
زبان: ${user.language_code}
شناسه: ${user.telegram_id}
            `.trim(), 'اطلاعات کاربری');
        })
        .catch(error => {
            console.error('Error fetching user info:', error);
        });
}

/**
 * Show user stats
 */
function showStats() {
    apiRequest('stats')
        .then(data => {
            const stats = data.stats;
            showPopup(`
مجموع نشست‌ها: ${stats.total_sessions}
روزهای فعال: ${stats.days_active}
اولین بازدید: ${stats.first_seen}
آخرین بازدید: ${stats.last_seen}
            `.trim(), 'آمار استفاده');
        })
        .catch(error => {
            console.error('Error fetching stats:', error);
        });
}

/**
 * Share app
 */
function shareApp() {
    if (tg) {
        const shareUrl = window.location.origin + '/app/';
        const shareText = `با این اپلیکیشن عالی آشنا شوید! ${shareUrl}`;
        
        if (tg.shareMessage) {
            tg.shareMessage(shareText);
        } else {
            showAlert('لینک اشتراک‌گذاری کپی شد!');
            navigator.clipboard.writeText(shareText);
        }
    } else {
        showAlert('این قابلیت فقط در تلگرم کار می‌کند.');
    }
}

/**
 * Contact support
 */
function contactSupport() {
    showConfirm('آیا می‌خواهید با پشتیبانی تماس بگیرید؟', function() {
        // Replace with your support bot username
        window.open('https://t.me/your_support_bot', '_blank');
    });
}

/**
 * Export user data
 */
function exportData() {
    showLoading();
    
    setTimeout(function() {
        hideLoading();
        
        apiRequest('user')
            .then(data => {
                apiRequest('stats')
                    .then(statsData => {
                        const exportData = {
                            user: data.user,
                            stats: statsData.stats,
                            exportDate: new Date().toISOString(),
                            version: '1.0.0'
                        };
                        
                        const dataStr = JSON.stringify(exportData, null, 2);
                        const dataBlob = new Blob([dataStr], {type: 'application/json'});
                        const url = URL.createObjectURL(dataBlob);
                        
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `user-data-${Date.now()}.json`;
                        link.click();
                        
                        URL.revokeObjectURL(url);
                        
                        showAlert('داده‌ها با موفقیت خروجی گرفته شدند.');
                    });
            })
            .catch(error => {
                console.error('Error exporting data:', error);
            });
    }, 1500);
}

/**
 * Clear cache
 */
function clearCache() {
    showConfirm('آیا مطمئن هستید که می‌خواهید حافظه پنهان را پاک کنید؟', function() {
        showLoading();
        
        setTimeout(function() {
            hideLoading();
            
            // Clear localStorage and sessionStorage
            localStorage.clear();
            sessionStorage.clear();
            
            showAlert('حافظه پنهان با موفقیت پاک شد.');
        }, 1000);
    });
}

/**
 * Delete account
 */
function deleteAccount() {
    showConfirm('آیا مطمئن هستید که می‌خواهید حساب کاربری خود را حذف کنید؟ این عمل غیرقابل بازگشت است!', function() {
        showConfirm('برای تأیید حذف حساب، لطفاً دوباره تأیید کنید.', function() {
            showLoading();
            
            setTimeout(function() {
                hideLoading();
                
                // Logout user
                sessionStorage.clear();
                
                showAlert('حساب کاربری شما با موفقیت حذف شد.');
                
                // Redirect to home
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            }, 2000);
        });
    });
}

/**
 * Load activity
 */
function loadActivity() {
    showLoading();
    
    setTimeout(function() {
        hideLoading();
        
        // Replace with actual activity data
        const activityContainer = document.querySelector('.activity-container');
        if (activityContainer) {
            activityContainer.innerHTML = `
                <div class="timeline">
                    <div class="timeline-item mb-3">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">ورود به سیستم</h6>
                            <p class="small text-muted mb-0">ورود موفق از طریق تلگرم</p>
                            <small class="text-muted">همین حالا</small>
                        </div>
                    </div>
                    <div class="timeline-item mb-3">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">بازدید از پروفایل</h6>
                            <p class="small text-muted mb-0">مشاهده اطلاعات کاربری</p>
                            <small class="text-muted">چند دقیقه پیش</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">به‌روزرسانی تنظیمات</h6>
                            <p class="small text-muted mb-0">تغییر زبان به فارسی</p>
                            <small class="text-muted">دیروز</small>
                        </div>
                    </div>
                </div>
            `;
        }
    }, 1000);
}

/**
 * Handle settings update
 */
function handleSettingsUpdate(form) {
    showLoading();
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Simulate API call
    setTimeout(function() {
        hideLoading();
        
        // Show success message
        showAlert('تنظیمات با موفقیت ذخیره شدند.');
        
        // Reload page after 1 second
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    }, 1500);
}

/**
 * Handle send message
 */
function handleSendMessage(form) {
    showLoading();
    
    const formData = new FormData(form);
    const message = formData.get('message');
    
    // Simulate sending message
    setTimeout(function() {
        hideLoading();
        
        showAlert('پیام با موفقیت ارسال شد.');
        form.reset();
    }, 2000);
}

/**
 * Adjust layout
 */
function adjustLayout() {
    // Adjust layout based on viewport
    const viewportHeight = window.innerHeight;
    const viewportWidth = window.innerWidth;
    
    // Adjust main container height
    const mainContainer = document.querySelector('.main-container');
    if (mainContainer) {
        const headerHeight = document.querySelector('.navbar')?.offsetHeight || 0;
        const availableHeight = viewportHeight - headerHeight - 40; // 40px for padding
        mainContainer.style.minHeight = availableHeight + 'px';
    }
    
    // Adjust cards for mobile
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        if (viewportWidth < 576) {
            card.classList.add('mobile-card');
        } else {
            card.classList.remove('mobile-card');
        }
    });
}

/**
 * Show not in Telegram message
 */
function showNotInTelegramMessage() {
    const message = `
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i>
            <strong>توجه:</strong> این اپلیکیشن برای استفاده کامل باید از طریق تلگرم باز شود.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.main-container');
    if (container) {
        container.insertAdjacentHTML('afterbegin', message);
    }
}

/**
 * Utility functions
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Store session start time
if (!sessionStorage.getItem('sessionStart')) {
    sessionStorage.setItem('sessionStart', Date.now());
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Add fade-in animation to cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach(function(card, index) {
        setTimeout(function() {
            card.classList.add('fade-in');
        }, index * 100);
    });
});