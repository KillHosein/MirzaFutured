<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/wallet/database.php';
require_once __DIR__ . '/wallet/bot_interface.php';

// Initialize wallet services
$walletDatabase = new WalletDatabase();
$walletBotInterface = new WalletBotInterface();

// Initialize database tables if they don't exist
$walletDatabase->initializeTables();

// Get user information from Telegram Web App
$userId = $_GET['user_id'] ?? null;
$username = $_GET['username'] ?? null;
$firstName = $_GET['first_name'] ?? null;
$lastName = $_GET['last_name'] ?? null;

// If user ID is not provided, show error
if (!$userId) {
    die('User ID is required');
}

// Get user's wallet balance
$balance = $walletDatabase->getUserBalance($userId);
if ($balance === false) {
    $balance = 0;
}

// Get recent transactions
$recentTransactions = $walletDatabase->getUserWalletTransactions($userId, 5, 0);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کیف پول - Mirza Web App</title>
    
    <!-- Telegram Web App Script -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazir:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="wallet/wallet-styles.css">
    
    <!-- Custom Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Vazir', Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        
        .header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .user-info {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        
        .user-details h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        
        .user-details p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #666;
        }
        
        .main-content {
            min-height: calc(100vh - 200px);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
        
        .telegram-web-app {
            background: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
        }
        
        .telegram-web-app .balance-card {
            background: linear-gradient(135deg, var(--tg-theme-button-color, #667eea) 0%, var(--tg-theme-button-text-color, #764ba2) 100%);
        }
        
        .telegram-web-app .recent-transactions,
        .telegram-web-app .user-info {
            background: var(--tg-theme-secondary-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
        }
        
        .telegram-web-app .transaction-item {
            background: var(--tg-theme-bg-color, #f9f9f9);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>💎 کیف پول من</h1>
            <p class="subtitle">مدیریت موجودی و تراکنشهای شما</p>
        </div>
        
        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar">
                <?php echo substr($firstName ?: $username ?: 'U', 0, 1); ?>
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></h3>
                <p>@<?php echo htmlspecialchars($username ?: 'کاربر'); ?></p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content" id="wallet-container">
            <!-- Wallet content will be loaded here by JavaScript -->
            <div class="wallet-dashboard">
                <div class="balance-card">
                    <div class="balance-header">
                        <h3>موجودی کیف پول</h3>
                        <span class="balance-icon">💰</span>
                    </div>
                    <div class="balance-amount">
                        <span class="amount"><?php echo number_format($balance); ?></span>
                        <span class="currency">تومان</span>
                    </div>
                </div>
                
                <div class="wallet-actions">
                    <button class="wallet-btn wallet-btn-primary" onclick="walletService.showDepositForm()">
                        💳 افزایش موجودی
                    </button>
                    <button class="wallet-btn wallet-btn-secondary" onclick="walletService.showTransactions()">
                        📋 تراکنشها
                    </button>
                </div>
                
                <div class="recent-transactions">
                    <h3>تراکنشهای اخیر</h3>
                    <div class="transactions-list">
                        <?php if (!empty($recentTransactions)): ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <div class="transaction-type">
                                            <?php echo getTransactionTypeIcon($transaction['transaction_type']); ?>
                                            <?php echo getTransactionTypeLabel($transaction['transaction_type']); ?>
                                        </div>
                                        <div class="transaction-description">
                                            <?php echo htmlspecialchars($transaction['description'] ?: 'بدون توضیح'); ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?php echo jdate('Y/m/d H:i', strtotime($transaction['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="transaction-amount <?php echo $transaction['amount'] > 0 ? 'positive' : 'negative'; ?>">
                                        <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['amount']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-transactions">شما هنوز هیچ تراکنشی ندارید</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>💎 کیف پول هوشمند میرزا</p>
            <p>پشتیبانی ۲۴ ساعته | امن و سریع</p>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="wallet/wallet-service.js"></script>
    <script>
        // Initialize Telegram Web App styling if available
        if (window.Telegram?.WebApp) {
            document.body.classList.add('telegram-web-app');
            
            // Set theme colors
            const theme = window.Telegram.WebApp.themeParams;
            if (theme.bg_color) {
                document.body.style.background = theme.bg_color;
                document.body.style.color = theme.text_color;
            }
        }
        
        // Initialize wallet service
        document.addEventListener('DOMContentLoaded', function() {
            // Set user data from PHP
            walletService.user = {
                id: '<?php echo $userId; ?>',
                username: '<?php echo $username; ?>',
                first_name: '<?php echo $firstName; ?>',
                last_name: '<?php echo $lastName; ?>'
            };
            
            // Initialize wallet UI
            console.log('Wallet initialized for user:', walletService.user.id);
        });
        
        // Handle Telegram Web App back button
        if (window.Telegram?.WebApp?.BackButton) {
            window.Telegram.WebApp.BackButton.onClick(function() {
                // Go back to the main dashboard
                window.location.href = 'index.php';
            });
            window.Telegram.WebApp.BackButton.show();
        }
        
        // Handle Telegram Web App main button
        if (window.Telegram?.WebApp?.MainButton) {
            window.Telegram.WebApp.MainButton.setText('بازگشت به ربات');
            window.Telegram.WebApp.MainButton.onClick(function() {
                window.Telegram.WebApp.close();
            });
            window.Telegram.WebApp.MainButton.show();
        }
    </script>
</body>
</html>

<?php
/**
 * Helper functions
 */
function getTransactionTypeIcon($type) {
    $icons = [
        'deposit' => '💰',
        'withdrawal' => '💸',
        'refund' => '🔄',
        'purchase' => '🛒',
        'commission' => '💎'
    ];
    return $icons[$type] ?? '📊';
}

function getTransactionTypeLabel($type) {
    $labels = [
        'deposit' => 'واریز',
        'withdrawal' => 'برداشت',
        'refund' => 'بازگشت وجه',
        'purchase' => 'خرید',
        'commission' => 'کمیسیون'
    ];
    return $labels[$type] ?? 'تراکنش';
}
?>