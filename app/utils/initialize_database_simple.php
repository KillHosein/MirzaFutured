<?php
/**
 * Database initialization script for Mirza Web App
 * Uses existing database connection from main project
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

// Include main project config to get database connection
require_once dirname(__DIR__, 2) . '/config.php';
require_once __DIR__ . '/config/config.php';

try {
    // Use the existing PDO connection from main config
    global $pdo;
    
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("Database connection not found in main config");
    }
    
    echo "Using existing database connection...\n\n";
    
    // Check if tables exist, if not create them
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Users table
    if (!in_array('users', $tables)) {
        echo "Creating users table...\n";
        $pdo->exec("
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
    } else {
        echo "Users table already exists.\n";
    }
    
    // User sessions table
    if (!in_array('user_sessions', $tables)) {
        echo "Creating user_sessions table...\n";
        $pdo->exec("
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
    } else {
        echo "User sessions table already exists.\n";
    }
    
    // User settings table
    if (!in_array('user_settings', $tables)) {
        echo "Creating user_settings table...\n";
        $pdo->exec("
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
    } else {
        echo "User settings table already exists.\n";
    }
    
    // Notifications table
    if (!in_array('notifications', $tables)) {
        echo "Creating notifications table...\n";
        $pdo->exec("
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
    } else {
        echo "Notifications table already exists.\n";
    }
    
    // Activity log table
    if (!in_array('activity_log', $tables)) {
        echo "Creating activity_log table...\n";
        $pdo->exec("
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
    } else {
        echo "Activity log table already exists.\n";
    }
    
    // System settings table
    if (!in_array('system_settings', $tables)) {
        echo "Creating system_settings table...\n";
        $pdo->exec("
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
        
        // Insert default system settings
        $defaultSettings = [
            ['app_name', 'Mirza Web App', 'string', 'Application name'],
            ['app_version', '1.0.0', 'string', 'Application version'],
            ['maintenance_mode', 'false', 'boolean', 'Maintenance mode status'],
            ['max_file_size', '10485760', 'integer', 'Maximum file upload size in bytes'],
            ['allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx', 'string', 'Allowed file types for upload'],
            ['rate_limit_enabled', 'true', 'boolean', 'Enable rate limiting'],
            ['rate_limit_max_attempts', '5', 'integer', 'Maximum attempts for rate limiting'],
            ['rate_limit_time_window', '3600', 'integer', 'Rate limiting time window in seconds'],
            ['session_lifetime', '7200', 'integer', 'Session lifetime in seconds'],
            ['cache_enabled', 'true', 'boolean', 'Enable caching'],
            ['cache_duration', '3600', 'integer', 'Cache duration in seconds']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
        echo "Default system settings inserted.\n";
    } else {
        echo "System settings table already exists.\n";
    }
    
    // Backup logs table
    if (!in_array('backup_logs', $tables)) {
        echo "Creating backup_logs table...\n";
        $pdo->exec("
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
    } else {
        echo "Backup logs table already exists.\n";
    }
    
    // File uploads table
    if (!in_array('file_uploads', $tables)) {
        echo "Creating file_uploads table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS file_uploads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                file_size BIGINT NOT NULL,
                file_type VARCHAR(20),
                mime_type VARCHAR(100),
                upload_path TEXT NOT NULL,
                download_count INT DEFAULT 0,
                is_public BOOLEAN DEFAULT 0,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_stored_name (stored_name),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } else {
        echo "File uploads table already exists.\n";
    }
    
    echo "\nDatabase initialization completed successfully!\n";
    echo "\nTables created/verified:\n";
    echo "- users\n";
    echo "- user_sessions\n";
    echo "- user_settings\n";
    echo "- notifications\n";
    echo "- activity_log\n";
    echo "- system_settings\n";
    echo "- backup_logs\n";
    echo "- file_uploads\n";
    echo "\nWeb app is ready to use with your existing database!\n";
    
} catch (Exception $e) {
    echo "Error initializing database: " . $e->getMessage() . "\n";
    exit(1);
}