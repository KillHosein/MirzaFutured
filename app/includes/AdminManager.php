<?php
/**
 * Admin Manager for Mirza Web App
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../includes/UserManager.php';
require_once __DIR__ . '/../includes/NotificationManager.php';

class AdminManager {
    private $db;
    private $userManager;
    private $notificationManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userManager = new UserManager($this->db);
        $this->notificationManager = new NotificationManager($this->db);
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin($userId) {
        $user = $this->userManager->getUserById($userId);
        return $user && $user['telegram_id'] == ADMIN_TELEGRAM_ID;
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        $stats = [];
        
        // User statistics
        $stats['total_users'] = $this->db->fetch('SELECT COUNT(*) as count FROM users')['count'];
        $stats['active_users'] = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE is_active = 1')['count'];
        $stats['banned_users'] = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE is_banned = 1')['count'];
        $stats['today_users'] = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()')['count'];
        $stats['week_users'] = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')['count'];
        $stats['month_users'] = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)')['count'];
        
        // Session statistics
        $stats['total_sessions'] = $this->db->fetch('SELECT COUNT(*) as count FROM user_sessions')['count'];
        $stats['today_sessions'] = $this->db->fetch('SELECT COUNT(*) as count FROM user_sessions WHERE DATE(created_at) = CURDATE()')['count'];
        $stats['active_sessions'] = $this->db->fetch('SELECT COUNT(*) as count FROM user_sessions WHERE is_active = 1')['count'];
        
        // Notification statistics
        $stats['total_notifications'] = $this->db->fetch('SELECT COUNT(*) as count FROM notifications')['count'];
        $stats['unread_notifications'] = $this->db->fetch('SELECT COUNT(*) as count FROM notifications WHERE is_read = 0')['count'];
        $stats['today_notifications'] = $this->db->fetch('SELECT COUNT(*) as count FROM notifications WHERE DATE(created_at) = CURDATE()')['count'];
        
        // Activity statistics
        $stats['total_activities'] = $this->db->fetch('SELECT COUNT(*) as count FROM activity_log')['count'];
        $stats['today_activities'] = $this->db->fetch('SELECT COUNT(*) as count FROM activity_log WHERE DATE(created_at) = CURDATE()')['count'];
        
        // System health
        $stats['database_size'] = $this->getDatabaseSize();
        $stats['last_backup'] = $this->getLastBackupDate();
        $stats['system_status'] = $this->getSystemStatus();
        
        return $stats;
    }
    
    /**
     * Get users with pagination and filtering
     */
    public function getUsers($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        
        // Apply filters
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(first_name LIKE :search OR last_name LIKE :search OR username LIKE :search OR telegram_id LIKE :search)';
            $params['search'] = $search;
        }
        
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = $filters['is_active'];
        }
        
        if (isset($filters['is_banned'])) {
            $where[] = 'is_banned = :is_banned';
            $params['is_banned'] = $filters['is_banned'];
        }
        
        if (isset($filters['language_code'])) {
            $where[] = 'language_code = :language_code';
            $params['language_code'] = $filters['language_code'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get users
        $users = $this->db->fetchAll("
            SELECT id, telegram_id, first_name, last_name, username, language_code, 
                   created_at, last_seen, is_active, is_banned, photo_url
            FROM users 
            $whereClause
            ORDER BY last_seen DESC 
            LIMIT :limit OFFSET :offset
        ", array_merge($params, ['limit' => $limit, 'offset' => $offset]));
        
        // Get total count
        $totalCount = $this->db->fetch("SELECT COUNT(*) as count FROM users $whereClause", $params)['count'];
        
        return [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ];
    }
    
    /**
     * Get user details
     */
    public function getUserDetails($userId) {
        $user = $this->userManager->getUserById($userId);
        if (!$user) {
            return null;
        }
        
        // Get additional user data
        $stats = $this->userManager->getUserStats($userId);
        $sessions = $this->db->fetchAll('
            SELECT * FROM user_sessions 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 10
        ', ['user_id' => $userId]);
        
        $notifications = $this->db->fetchAll('
            SELECT * FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 10
        ', ['user_id' => $userId]);
        
        $settings = $this->db->fetchAll('
            SELECT * FROM user_settings 
            WHERE user_id = :user_id
        ', ['user_id' => $userId]);
        
        return [
            'user' => $user,
            'stats' => $stats,
            'sessions' => $sessions,
            'notifications' => $notifications,
            'settings' => $settings
        ];
    }
    
    /**
     * Ban user
     */
    public function banUser($userId, $reason = '') {
        return $this->db->update('users',
            [
                'is_banned' => 1,
                'ban_reason' => $reason,
                'banned_at' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $userId]
        );
    }
    
    /**
     * Unban user
     */
    public function unbanUser($userId) {
        return $this->db->update('users',
            [
                'is_banned' => 0,
                'ban_reason' => null,
                'banned_at' => null
            ],
            'id = :id',
            ['id' => $userId]
        );
    }
    
    /**
     * Delete user
     */
    public function deleteUser($userId) {
        // Delete related data first (cascade delete)
        $this->db->delete('user_sessions', 'user_id = :user_id', ['user_id' => $userId]);
        $this->db->delete('user_settings', 'user_id = :user_id', ['user_id' => $userId]);
        $this->db->delete('notifications', 'user_id = :user_id', ['user_id' => $userId]);
        
        // Delete user
        return $this->db->delete('users', 'id = :id', ['id' => $userId]);
    }
    
    /**
     * Send broadcast message
     */
    public function sendBroadcast($title, $message, $type = 'info', $priority = 'normal', $sendNotification = false) {
        $users = $this->db->fetchAll('SELECT id FROM users WHERE is_active = 1 AND is_banned = 0');
        
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($users as $user) {
            try {
                if ($sendNotification) {
                    $this->notificationManager->createNotification(
                        $user['id'],
                        $title,
                        $message,
                        $type,
                        $priority
                    );
                }
                $successCount++;
            } catch (Exception $e) {
                $failedCount++;
                error_log("Failed to send broadcast to user {$user['id']}: " . $e->getMessage());
            }
        }
        
        // Log the broadcast
        $this->db->insert('broadcast_logs', [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'recipients_count' => count($users),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'recipients' => count($users),
            'success' => $successCount,
            'failed' => $failedCount
        ];
    }
    
    /**
     * Get system logs
     */
    public function getSystemLogs($level = null, $limit = 100) {
        $where = '';
        $params = [];
        
        if ($level) {
            $where = 'WHERE level = :level';
            $params['level'] = $level;
        }
        
        return $this->db->fetchAll("
            SELECT * FROM system_logs 
            $where 
            ORDER BY created_at DESC 
            LIMIT :limit
        ", array_merge($params, ['limit' => $limit]));
    }
    
    /**
     * Clear system logs
     */
    public function clearSystemLogs($days = 30) {
        return $this->db->query('
            DELETE FROM system_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ', ['days' => $days]);
    }
    
    /**
     * Get system settings
     */
    public function getSystemSettings() {
        $settings = $this->db->fetchAll('SELECT setting_key, setting_value, setting_type, description FROM system_settings ORDER BY setting_key');
        
        $settingsArray = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Convert based on type
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'float':
                    $value = (float) $value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $settingsArray[$setting['setting_key']] = $value;
        }
        
        return $settingsArray;
    }
    
    /**
     * Update system setting
     */
    public function updateSystemSetting($key, $value) {
        return $this->db->update('system_settings',
            ['setting_value' => $value],
            'setting_key = :key',
            ['key' => $key]
        );
    }
    
    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenanceMode() {
        $currentStatus = $this->db->fetch('SELECT setting_value FROM system_settings WHERE setting_key = "maintenance_mode"')['setting_value'];
        $newStatus = $currentStatus === 'true' ? 'false' : 'true';
        
        $this->updateSystemSetting('maintenance_mode', $newStatus);
        
        return $newStatus === 'true';
    }
    
    /**
     * Get database size
     */
    private function getDatabaseSize() {
        $result = $this->db->fetch('SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()');
        return $result['size'] ?? 0;
    }
    
    /**
     * Get last backup date
     */
    private function getLastBackupDate() {
        $result = $this->db->fetch('SELECT MAX(completed_at) as last_backup FROM backup_logs WHERE status = "completed"');
        return $result['last_backup'];
    }
    
    /**
     * Get system status
     */
    private function getSystemStatus() {
        $status = 'healthy';
        
        // Check database connection
        try {
            $this->db->query('SELECT 1');
        } catch (Exception $e) {
            $status = 'database_error';
        }
        
        // Check disk space (if possible)
        $diskFree = disk_free_space('.');
        $diskTotal = disk_total_space('.');
        $diskUsage = ($diskTotal - $diskFree) / $diskTotal * 100;
        
        if ($diskUsage > 90) {
            $status = 'disk_full';
        }
        
        return $status;
    }
    
    /**
     * Create backup
     */
    public function createBackup() {
        $backupDir = __DIR__ . '/../backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        try {
            // Start backup
            $this->db->insert('backup_logs', [
                'backup_type' => 'manual',
                'status' => 'started',
                'started_at' => date('Y-m-d H:i:s')
            ]);
            
            $backupId = $this->db->getConnection()->lastInsertId();
            
            // Create backup (simplified - in production use mysqldump)
            $tables = ['users', 'user_sessions', 'user_settings', 'notifications', 'activity_log', 'system_settings'];
            $backupContent = "-- Backup created at " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $backupContent .= "-- Table: $table\n";
                // Add table structure and data export logic here
                $backupContent .= "\n";
            }
            
            file_put_contents($backupFile, $backupContent);
            
            $fileSize = filesize($backupFile);
            $checksum = md5_file($backupFile);
            
            // Update backup log
            $this->db->update('backup_logs',
                [
                    'file_path' => $backupFile,
                    'file_size' => $fileSize,
                    'checksum' => $checksum,
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $backupId]
            );
            
            return [
                'success' => true,
                'backup_path' => $backupFile,
                'file_size' => $fileSize,
                'checksum' => $checksum
            ];
            
        } catch (Exception $e) {
            // Update backup log with error
            if (isset($backupId)) {
                $this->db->update('backup_logs',
                    [
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => date('Y-m-d H:i:s')
                    ],
                    'id = :id',
                    ['id' => $backupId]
                );
            }
            
            throw $e;
        }
    }
}