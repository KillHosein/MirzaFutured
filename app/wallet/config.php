<?php
/**
 * Wallet System Configuration
 * Configuration settings for the card-to-card wallet top-up system
 */

return [
    /**
     * General Settings
     */
    'general' => [
        'min_amount' => 10000, // Minimum transaction amount in Toman
        'max_amount' => 50000000, // Maximum transaction amount in Toman (50 million)
        'currency' => 'تومان',
        'decimal_places' => 0,
        'timezone' => 'Asia/Tehran',
        'language' => 'fa',
    ],
    
    /**
     * Card-to-Card Settings
     */
    'card_to_card' => [
        'enabled' => true,
        'auto_confirm' => false, // Whether to auto-confirm transactions (not recommended)
        'verification_required' => true, // Whether admin verification is required
        'max_daily_transactions' => 10, // Maximum transactions per user per day
        'max_monthly_transactions' => 50, // Maximum transactions per user per month
        'processing_time_minutes' => 30, // Expected processing time in minutes
        
        // Bank card settings
        'accepted_banks' => [
            'بانک ملی ایران',
            'بانک صادرات ایران',
            'بانک تجارت',
            'بانک ملت',
            'بانک سامان',
            'بانک پارسیان',
            'بانک اقتصاد نوین',
            'بانک پاسارگاد',
            'بانک شهر',
            'بانک مسکن',
            'بانک رفاه',
            'بانک کشاورزی',
            'بانک صنعت و معدن',
            'بانک توسعه صادرات',
            'بانک سپه',
            'بانک دی',
            'بانک انصار',
            'بانک قوامین',
            'بانک حکمت ایرانیان',
            'بانک گردشگری',
            'بانک ایران زمین',
            'بانک خاورمیانه',
            'بانک قرض الحسنه رسالت',
            'بانک قرض الحسنه مهر ایران'
        ],
        
        // Destination card settings (should be configured in database)
        'destination_card' => [
            'card_number' => '6037991234567890', // This should be set in database
            'bank_name' => 'بانک ملی ایران',
            'card_owner' => 'شرکت فناوری اطلاعات میرزا',
        ],
    ],
    
    /**
     * Notification Settings
     */
    'notifications' => [
        'admin_notification_enabled' => true,
        'user_notification_enabled' => true,
        'notification_channels' => ['telegram', 'email'],
        
        // Admin ID for Telegram notifications (Numeric User ID)
        'admin_id' => '8481984748', // Put your numeric admin ID here (e.g., 123456789)
        
        // Admin notification settings
        'admin' => [
            'notify_on_new_transaction' => true,
            'notify_on_large_transaction' => true,
            'large_transaction_threshold' => 1000000, // 1 million Toman
        ],
        
        // User notification settings
        'user' => [
            'notify_on_transaction_created' => true,
            'notify_on_transaction_confirmed' => true,
            'notify_on_transaction_rejected' => true,
        ],
    ],
    
    /**
     * Security Settings
     */
    'security' => [
        'enable_rate_limiting' => true,
        'max_attempts_per_hour' => 10,
        'lockout_duration_minutes' => 60,
        
        'enable_fraud_detection' => true,
        'max_amount_per_hour' => 10000000, // 10 million Toman per hour per user
        'max_amount_per_day' => 50000000, // 50 million Toman per day per user
        
        'card_validation_enabled' => true,
        'require_card_verification' => true,
        'max_cards_per_user' => 5,
        
        'ip_restriction_enabled' => false,
        'allowed_ips' => [], // Empty means all IPs are allowed
        'blocked_ips' => [],
    ],
    
    /**
     * API Settings
     */
    'api' => [
        'enabled' => true,
        'rate_limit_enabled' => true,
        'rate_limit_per_minute' => 60,
        'rate_limit_per_hour' => 1000,
        
        'authentication_required' => true,
        'token_expiry_hours' => 24,
        
        'enable_logging' => true,
        'log_level' => 'info', // debug, info, warning, error
        'log_retention_days' => 30,
    ],
    
    /**
     * Database Settings
     */
    'database' => [
        'table_prefix' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'engine' => 'InnoDB',
        
        'tables' => [
            'card_to_card_transactions' => 'card_to_card_transactions',
            'wallet_transactions' => 'wallet_transactions',
            'bank_cards' => 'bank_cards',
            'transaction_logs' => 'transaction_logs',
        ],
    ],
    
    /**
     * Fee Settings
     */
    'fees' => [
        'deposit_fee_percentage' => 0, // 0% fee for deposits
        'withdrawal_fee_percentage' => 0, // 0% fee for withdrawals
        'minimum_fee_amount' => 0, // Minimum fee amount
        'maximum_fee_amount' => 0, // Maximum fee amount
        
        'fee_calculation_method' => 'percentage', // percentage, fixed, or both
    ],
    
    /**
     * Report Settings
     */
    'reports' => [
        'enable_daily_reports' => true,
        'enable_weekly_reports' => true,
        'enable_monthly_reports' => true,
        
        'report_recipients' => [], // Admin IDs or email addresses
        
        'report_include_statistics' => true,
        'report_include_charts' => true,
        'report_format' => 'html', // html, pdf, csv
    ],
    
    /**
     * Integration Settings
     */
    'integrations' => [
        'telegram_bot_enabled' => true,
        'telegram_bot_username' => '@dgspksafbot', // Should be configured
        
        'payment_gateways' => [
            'zarinpal' => false,
            'idpay' => false,
            'nextpay' => false,
        ],
        
        'sms_provider' => [
            'enabled' => false,
            'provider' => 'kavenegar', // kavenegar, melipayamak, etc.
            'api_key' => '', // Should be configured
        ],
    ],
    
    /**
     * Maintenance Settings
     */
    'maintenance' => [
        'enabled' => false,
        'message' => 'سیستم کیف پول در حال بروزرسانی است. لطفاً بعداً تلاش کنید.',
        'allowed_ips' => ['127.0.0.1'], // IPs that can access during maintenance
    ],
    
    /**
     * Error Handling
     */
    'error_handling' => [
        'display_errors' => false,
        'log_errors' => true,
        'error_log_file' => 'logs/wallet_errors.log',
        'error_email_notifications' => true,
        'error_email_recipients' => [], // Email addresses for error notifications
    ],
];

/**
 * Helper function to get configuration value
 */
function getWalletConfig($key, $default = null) {
    global $walletConfig;
    
    $keys = explode('.', $key);
    $value = $walletConfig;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Helper function to update configuration value
 */
function setWalletConfig($key, $value) {
    global $walletConfig;
    
    $keys = explode('.', $key);
    $config = &$walletConfig;
    
    foreach ($keys as $k) {
        if (!isset($config[$k])) {
            $config[$k] = [];
        }
        $config = &$config[$k];
    }
    
    $config = $value;
    
    return true;
}

/**
 * Load configuration from database
 */
function loadWalletConfigFromDatabase() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM wallet_config WHERE is_active = 1");
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($configs as $config) {
            setWalletConfig($config['config_key'], json_decode($config['config_value'], true));
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error loading wallet config from database: " . $e->getMessage());
        return false;
    }
}

/**
 * Save configuration to database
 */
function saveWalletConfigToDatabase($key, $value) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO wallet_config (config_key, config_value, updated_at) 
                              VALUES (?, ?, NOW()) 
                              ON DUPLICATE KEY UPDATE 
                              config_value = VALUES(config_value), 
                              updated_at = VALUES(updated_at)");
        
        return $stmt->execute([$key, json_encode($value)]);
    } catch (Exception $e) {
        error_log("Error saving wallet config to database: " . $e->getMessage());
        return false;
    }
}

// Load configuration from database if available
loadWalletConfigFromDatabase();

return $walletConfig;