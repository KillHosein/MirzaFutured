<?php


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

echo "<pre>";
echo "🚀 Starting Wallet System Installation...\n\n";

// Initialize wallet database
$walletDatabase = new WalletDatabase();

// Create tables
echo "📋 Creating database tables...\n";
$success = $walletDatabase->initializeTables();

if ($success) {
    echo "✅ Database tables created successfully!\n\n";
} else {
    echo "❌ Error creating database tables!\n\n";
    exit(1);
}

// Insert default configuration
echo "⚙️ Inserting default configuration...\n";
try {
    global $pdo;
    
    // Check if configuration table exists, if not create it
    $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'wallet_config'");
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tableExists) {
        $sql = "CREATE TABLE wallet_config (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(255) UNIQUE NOT NULL,
            config_value TEXT NOT NULL,
            description TEXT DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_config_key (config_key),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Configuration table created!\n";
    }
    
    // Insert default settings
    $defaultConfigs = [
        [
            'config_key' => 'card_to_card.enabled',
            'config_value' => json_encode(true),
            'description' => 'Enable/disable card-to-card transactions'
        ],
        [
            'config_key' => 'card_to_card.min_amount',
            'config_value' => json_encode(10000),
            'description' => 'Minimum transaction amount in Toman'
        ],
        [
            'config_key' => 'card_to_card.max_amount',
            'config_value' => json_encode(50000000),
            'description' => 'Maximum transaction amount in Toman'
        ],
        [
            'config_key' => 'card_to_card.processing_time_minutes',
            'config_value' => json_encode(30),
            'description' => 'Expected processing time in minutes'
        ],
        [
            'config_key' => 'card_to_card.destination_card.card_number',
            'config_value' => json_encode('6037991234567890'),
            'description' => 'Destination card number for card-to-card transfers'
        ],
        [
            'config_key' => 'card_to_card.destination_card.bank_name',
            'config_value' => json_encode('بانک ملی ایران'),
            'description' => 'Destination bank name'
        ],
        [
            'config_key' => 'card_to_card.destination_card.card_owner',
            'config_value' => json_encode('شرکت فناوری اطلاعات میرزا'),
            'description' => 'Destination card owner name'
        ],
        [
            'config_key' => 'notifications.admin_notification_enabled',
            'config_value' => json_encode(true),
            'description' => 'Enable admin notifications'
        ],
        [
            'config_key' => 'notifications.user_notification_enabled',
            'config_value' => json_encode(true),
            'description' => 'Enable user notifications'
        ],
        [
            'config_key' => 'security.enable_rate_limiting',
            'config_value' => json_encode(true),
            'description' => 'Enable rate limiting for security'
        ],
        [
            'config_key' => 'security.max_attempts_per_hour',
            'config_value' => json_encode(10),
            'description' => 'Maximum attempts per hour per user'
        ],
        [
            'config_key' => 'security.enable_fraud_detection',
            'config_value' => json_encode(true),
            'description' => 'Enable fraud detection'
        ],
        [
            'config_key' => 'security.max_amount_per_day',
            'config_value' => json_encode(50000000),
            'description' => 'Maximum amount per day per user in Toman'
        ],
        [
            'config_key' => 'api.enabled',
            'config_value' => json_encode(true),
            'description' => 'Enable wallet API'
        ],
        [
            'config_key' => 'api.rate_limit_enabled',
            'config_value' => json_encode(true),
            'description' => 'Enable API rate limiting'
        ],
        [
            'config_key' => 'api.rate_limit_per_minute',
            'config_value' => json_encode(60),
            'description' => 'API rate limit per minute'
        ],
        [
            'config_key' => 'api.log_level',
            'config_value' => json_encode('info'),
            'description' => 'API logging level'
        ],
        [
            'config_key' => 'fees.deposit_fee_percentage',
            'config_value' => json_encode(0),
            'description' => 'Deposit fee percentage'
        ],
        [
            'config_key' => 'fees.withdrawal_fee_percentage',
            'config_value' => json_encode(0),
            'description' => 'Withdrawal fee percentage'
        ],
        [
            'config_key' => 'reports.enable_daily_reports',
            'config_value' => json_encode(true),
            'description' => 'Enable daily reports'
        ],
        [
            'config_key' => 'reports.enable_weekly_reports',
            'config_value' => json_encode(true),
            'description' => 'Enable weekly reports'
        ],
        [
            'config_key' => 'reports.enable_monthly_reports',
            'config_value' => json_encode(true),
            'description' => 'Enable monthly reports'
        ],
        [
            'config_key' => 'maintenance.enabled',
            'config_value' => json_encode(false),
            'description' => 'Enable maintenance mode'
        ],
        [
            'config_key' => 'error_handling.log_errors',
            'config_value' => json_encode(true),
            'description' => 'Enable error logging'
        ],
        [
            'config_key' => 'error_handling.error_log_file',
            'config_value' => json_encode('logs/wallet_errors.log'),
            'description' => 'Error log file path'
        ]
    ];
    
    // Insert configurations
    $stmt = $pdo->prepare("INSERT INTO wallet_config (config_key, config_value, description) VALUES (?, ?, ?) 
                          ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), description = VALUES(description)");
    
    foreach ($defaultConfigs as $config) {
        $stmt->execute([
            $config['config_key'],
            $config['config_value'],
            $config['description']
        ]);
    }
    
    echo "✅ Default configuration inserted successfully!\n\n";
    
} catch (Exception $e) {
    echo "❌ Error inserting default configuration: " . $e->getMessage() . "\n\n";
}

// Create necessary directories
echo "📁 Creating necessary directories...\n";
$directories = [
    'logs',
    'reports',
    'backups',
    'temp'
];

foreach ($directories as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            echo "✅ Created directory: $dir\n";
        } else {
            echo "❌ Failed to create directory: $dir\n";
        }
    } else {
        echo "ℹ️ Directory already exists: $dir\n";
    }
}

echo "\n";

// Set up cron jobs (optional)
echo "⏰ Setting up cron jobs...\n";
echo "ℹ️ Please add the following cron jobs to your system:\n\n";
echo "# Wallet system cron jobs\n";
echo "# Daily report generation (runs at 2 AM)\n";
echo "0 2 * * * php " . __DIR__ . "/cron/daily_report.php >> " . __DIR__ . "/logs/cron.log 2>&1\n";
echo "\n";
echo "# Weekly report generation (runs every Monday at 3 AM)\n";
echo "0 3 * * 1 php " . __DIR__ . "/cron/weekly_report.php >> " . __DIR__ . "/logs/cron.log 2>&1\n";
echo "\n";
echo "# Monthly report generation (runs on 1st of each month at 4 AM)\n";
echo "0 4 1 * * php " . __DIR__ . "/cron/monthly_report.php >> " . __DIR__ . "/logs/cron.log 2>&1\n";
echo "\n";
echo "# Cleanup old logs (runs daily at 5 AM)\n";
echo "0 5 * * * php " . __DIR__ . "/cron/cleanup_logs.php >> " . __DIR__ . "/logs/cron.log 2>&1\n";
echo "\n";

// Final instructions
echo "🎉 Wallet system installation completed successfully!\n\n";
echo "📋 Next steps:\n";
echo "1. Configure your destination card number in the admin panel\n";
echo "2. Set up admin notification channels\n";
echo "3. Test the card-to-card functionality\n";
echo "4. Set up the cron jobs mentioned above\n";
echo "5. Configure your bank card information\n";
echo "6. Test the entire workflow\n\n";
echo "🔧 Important notes:\n";
echo "- Make sure your destination card number is correct\n";
echo "- Set up proper admin notifications\n";
echo "- Monitor the logs regularly\n";
echo "- Keep your system updated\n";
echo "- Test all functionality before going live\n\n";
echo "📞 For support, please contact the development team.\n";
echo "</pre>";

?>