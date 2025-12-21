    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="/app/assets/js/app.js"></script>
    
    <script>
        // Telegram Web App integration
        let tg = window.Telegram.WebApp;
        
        if (tg) {
            // Set theme colors
            document.documentElement.style.setProperty('--tg-theme-bg-color', tg.themeParams.bg_color);
            document.documentElement.style.setProperty('--tg-theme-text-color', tg.themeParams.text_color);
            document.documentElement.style.setProperty('--tg-theme-button-color', tg.themeParams.button_color);
            document.documentElement.style.setProperty('--tg-theme-button-text-color', tg.themeParams.button_text_color);
            document.documentElement.style.setProperty('--tg-theme-secondary-bg-color', tg.themeParams.secondary_bg_color);
            document.documentElement.style.setProperty('--tg-theme-header-bg-color', tg.themeParams.bg_color);
            document.documentElement.style.setProperty('--tg-theme-header-text-color', tg.themeParams.text_color);
            document.documentElement.style.setProperty('--tg-theme-hint-color', tg.themeParams.hint_color);
            
            // Expand Web App
            tg.expand();
            
            // Enable closing confirmation
            tg.enableClosingConfirmation();
            
            // Set main button
            tg.MainButton.setText('بستن اپلیکیشن');
            tg.MainButton.onClick(function() {
                tg.close();
            });
            tg.MainButton.show();
        }
        
        // Utility functions
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        function showAlert(message) {
            if (tg) {
                tg.showAlert(message);
            } else {
                alert(message);
            }
        }
        
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
        
        // API functions
        async function apiRequest(endpoint, options = {}) {
            try {
                showLoading();
                const response = await fetch(`/app/index.php?action=api&endpoint=${endpoint}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        ...options.headers
                    },
                    ...options
                });
                
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
    </script>
</body>
</html>