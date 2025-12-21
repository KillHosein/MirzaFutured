<?php
/**
 * Database initialization script
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    echo "Starting database initialization...\n\n";
    
    // Users table
    echo "Creating users table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telegram_id BIGINT UNIQUE NOT NULL,
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            username VARCHAR(255),
            language_code VARCHAR(10) DEFAULT 'en',
            is_premium BOOLEAN DEFAULT 0,
            allows_write_to_pm BOOLEAN DEFAULT 1,
            photo_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT 1,
            is_banned BOOLEAN DEFAULT 0,
            ban_reason TEXT,
            banned_at DATETIME,
            INDEX idx_telegram_id (telegram_id),
            INDEX idx_username (username),
            INDEX idx_last_seen (last_seen),
            INDEX idx_created_at (created_at),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // User sessions table
    echo "Creating user_sessions table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            action VARCHAR(50),
            platform VARCHAR(50),
            device_info TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            country VARCHAR(2),
            city VARCHAR(100),
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME,
            is_active BOOLEAN DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_session_id (session_id),
            INDEX idx_created_at (created_at),
            INDEX idx_ip_address (ip_address),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // User settings table
    echo "Creating user_settings table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            setting_type VARCHAR(20) DEFAULT 'string',
            is_public BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_setting (user_id, setting_key),
            INDEX idx_user_id (user_id),
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Notifications table
    echo "Creating notifications table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            priority VARCHAR(20) DEFAULT 'normal',
            is_read BOOLEAN DEFAULT 0,
            read_at DATETIME,
            action_url VARCHAR(500),
            action_text VARCHAR(100),
            icon VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at),
            INDEX idx_type (type),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Activity log table
    echo "Creating activity_log table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            old_values TEXT,
            new_values TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Bot commands table
    echo "Creating bot_commands table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS bot_commands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            command VARCHAR(50) NOT NULL,
            description TEXT,
            usage_example TEXT,
            is_active BOOLEAN DEFAULT 1,
            requires_admin BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_command (command),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Bot messages table
    echo "Creating bot_messages table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS bot_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_key VARCHAR(100) NOT NULL,
            message_text TEXT NOT NULL,
            language_code VARCHAR(10) DEFAULT 'en',
            message_type VARCHAR(50) DEFAULT 'text',
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_message_key (message_key, language_code),
            INDEX idx_language (language_code),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Rate limiting table
    echo "Creating rate_limiting table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS rate_limiting (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            action VARCHAR(100) NOT NULL,
            request_count INT DEFAULT 1,
            window_start DATETIME NOT NULL,
            window_end DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_identifier_action_window (identifier, action, window_start),
            INDEX idx_identifier (identifier),
            INDEX idx_action (action),
            INDEX idx_window_end (window_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // System settings table
    echo "Creating system_settings table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            setting_type VARCHAR(20) DEFAULT 'string',
            description TEXT,
            is_public BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_setting_key (setting_key),
            INDEX idx_is_public (is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Backup logs table
    echo "Creating backup_logs table...\n";
    $connection->exec("
        CREATE TABLE IF NOT EXISTS backup_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_type VARCHAR(50) NOT NULL,
            file_path VARCHAR(500),
            file_size BIGINT,
            checksum VARCHAR(64),
            status VARCHAR(20) NOT NULL,
            error_message TEXT,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            INDEX idx_backup_type (backup_type),
            INDEX idx_status (status),
            INDEX idx_started_at (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert default bot commands
    echo "Inserting default bot commands...\n";
    $connection->exec("
        INSERT IGNORE INTO bot_commands (command, description, usage_example, is_active) VALUES
        ('/start', 'Start the bot and show welcome message', '/start', 1),
        ('/help', 'Show available commands', '/help', 1),
        ('/profile', 'Show user profile information', '/profile', 1),
        ('/settings', 'Show user settings', '/settings', 1),
        ('/stats', 'Show user statistics', '/stats', 1),
        ('/language', 'Change language settings', '/language fa', 1),
        ('/about', 'Show information about the bot', '/about', 1),
        ('/contact', 'Show contact information', '/contact', 1),
        ('/admin', 'Admin panel (admin only)', '/admin', 1),
        ('/broadcast', 'Send message to all users (admin only)', '/broadcast Hello everyone!', 1)
    ");
    
    // Insert default bot messages
    echo "Inserting default bot messages...\n";
    $messages = [
        ['welcome_message', 'fa', 'به ربات ما خوش آمدید! برای شروع از دستور /help استفاده کنید.'],
        ['welcome_message', 'en', 'Welcome to our bot! Use /help to get started.'],
        ['help_message', 'fa', 'دستورات موجود:\n/start - شروع\n/help - راهنما\n/profile - پروفایل\n/settings - تنظیمات'],
        ['help_message', 'en', 'Available commands:\n/start - Start\n/help - Help\n/profile - Profile\n/settings - Settings'],
        ['error_message', 'fa', 'متأسفانه خطایی رخ داده است. لطفاً دوباره امتحان کنید.'],
        ['error_message', 'en', 'Sorry, an error occurred. Please try again.'],
        ['success_message', 'fa', 'عملیات با موفقیت انجام شد.'],
        ['success_message', 'en', 'Operation completed successfully.']
    ];
    
    foreach ($messages as $message) {
        $stmt = $connection->prepare("
            INSERT IGNORE INTO bot_messages (message_key, language_code, message_text) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$message[0], $message[1], $message[2]]);
    }
    
    // Insert default system settings
    echo "Inserting default system settings...\n";
    $settings = [
        ['app_name', APP_NAME, 'string', 'Application name'],
        ['app_version', APP_VERSION, 'string', 'Application version'],
        ['maintenance_mode', 'false', 'boolean', 'Maintenance mode status'],
        ['max_file_size', strval(MAX_FILE_SIZE), 'integer', 'Maximum file upload size in bytes'],
        ['allowed_file_types', implode(',', ALLOWED_FILE_TYPES), 'string', 'Allowed file types for upload'],
        ['rate_limit_enabled', 'true', 'boolean', 'Enable rate limiting'],
        ['rate_limit_max_attempts', '5', 'integer', 'Maximum attempts for rate limiting'],
        ['rate_limit_time_window', '3600', 'integer', 'Rate limiting time window in seconds'],
        ['session_lifetime', '7200', 'integer', 'Session lifetime in seconds'],
        ['cache_enabled', CACHE_ENABLED ? 'true' : 'false', 'boolean', 'Enable caching'],
        ['cache_duration', strval(CACHE_DURATION), 'integer', 'Cache duration in seconds']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $connection->prepare("
            INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$setting[0], $setting[1], $setting[2], $setting[3]]);
    }
    
    echo "\nDatabase initialization completed successfully!\n";
    echo "\nTables created:\n";
    echo "- users\n";
    echo "- user_sessions\n";
    echo "- user_settings\n";
    echo "- notifications\n";
    echo "- activity_log\n";
    echo "- bot_commands\n";
    echo "- bot_messages\n";
    echo "- rate_limiting\n";
    echo "- system_settings\n";
    echo "- backup_logs\n";
    echo "\nDefault data inserted successfully!\n";
    
} catch (Exception $e) {
    echo "Error initializing database: " . $e->getMessage() . "\n";
    exit(1);
}