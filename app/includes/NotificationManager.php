<?php
/**
 * Notification System for Mirza Web App
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

class NotificationManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create notification
     */
    public function createNotification($userId, $title, $message, $type = 'info', $priority = 'normal', $actionUrl = null, $actionText = null, $icon = null) {
        $notificationData = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'action_url' => $actionUrl,
            'action_text' => $actionText,
            'icon' => $icon,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ];
        
        return $this->db->insert('notifications', $notificationData);
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $limit = 50, $offset = 0, $unreadOnly = false) {
        $where = 'user_id = :user_id';
        $params = ['user_id' => $userId];
        
        if ($unreadOnly) {
            $where .= ' AND is_read = 0';
        }
        
        $where .= ' AND (expires_at IS NULL OR expires_at > NOW())';
        
        return $this->db->fetchAll("
            SELECT id, title, message, type, priority, is_read, action_url, action_text, icon, created_at, read_at 
            FROM notifications 
            WHERE $where 
            ORDER BY priority DESC, created_at DESC 
            LIMIT :limit OFFSET :offset
        ", array_merge($params, ['limit' => $limit, 'offset' => $offset]));
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($userId) {
        $result = $this->db->fetch('
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = :user_id AND is_read = 0 
            AND (expires_at IS NULL OR expires_at > NOW())
        ', ['user_id' => $userId]);
        
        return $result['count'] ?? 0;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        return $this->db->update('notifications',
            [
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s')
            ],
            'id = :id AND user_id = :user_id',
            ['id' => $notificationId, 'user_id' => $userId]
        );
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($userId) {
        return $this->db->update('notifications',
            [
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s')
            ],
            'user_id = :user_id AND is_read = 0',
            ['user_id' => $userId]
        );
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($notificationId, $userId) {
        return $this->db->delete('notifications',
            'id = :id AND user_id = :user_id',
            ['id' => $notificationId, 'user_id' => $userId]
        );
    }
    
    /**
     * Delete old notifications
     */
    public function deleteOldNotifications($days = 30) {
        return $this->db->query('
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ', ['days' => $days]);
    }
    
    /**
     * Create welcome notification
     */
    public function createWelcomeNotification($userId) {
        return $this->createNotification(
            $userId,
            'Welcome!',
            'Welcome to our application. Check out your profile and settings to get started.',
            'success',
            'high',
            '/app/index.php?action=profile',
            'View Profile',
            'bi-person-circle'
        );
    }
    
    /**
     * Create security notification
     */
    public function createSecurityNotification($userId, $action, $details = '') {
        $title = 'Security Alert';
        $message = "A security-related action has been detected: $action";
        
        if ($details) {
            $message .= "\n\nDetails: $details";
        }
        
        return $this->createNotification(
            $userId,
            $title,
            $message,
            'warning',
            'high',
            null,
            null,
            'bi-shield-exclamation'
        );
    }
    
    /**
     * Create achievement notification
     */
    public function createAchievementNotification($userId, $achievement, $description = '') {
        return $this->createNotification(
            $userId,
            '🎉 Achievement Unlocked!',
            "Congratulations! You've unlocked: $achievement" . ($description ? "\n\n$description" : ''),
            'success',
            'medium',
            '/app/index.php?action=profile',
            'View Profile',
            'bi-trophy'
        );
    }
    
    /**
     * Create system notification
     */
    public function createSystemNotification($userId, $title, $message, $type = 'info') {
        return $this->createNotification(
            $userId,
            $title,
            $message,
            $type,
            'normal',
            null,
            null,
            'bi-info-circle'
        );
    }
    
    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications($userIds, $title, $message, $type = 'info', $priority = 'normal') {
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($userIds as $userId) {
            try {
                $this->createNotification($userId, $title, $message, $type, $priority);
                $successCount++;
            } catch (Exception $e) {
                $failedCount++;
                error_log("Failed to send notification to user $userId: " . $e->getMessage());
            }
        }
        
        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => count($userIds)
        ];
    }
    
    /**
     * Get notification by ID
     */
    public function getNotificationById($notificationId, $userId) {
        return $this->db->fetch('
            SELECT * FROM notifications 
            WHERE id = :id AND user_id = :user_id
        ', ['id' => $notificationId, 'user_id' => $userId]);
    }
    
    /**
     * Update notification
     */
    public function updateNotification($notificationId, $userId, $data) {
        $allowedFields = ['title', 'message', 'type', 'priority', 'action_url', 'action_text', 'icon'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            return false;
        }
        
        return $this->db->update('notifications',
            $updateData,
            'id = :id AND user_id = :user_id',
            ['id' => $notificationId, 'user_id' => $userId]
        );
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStats($userId) {
        $stats = $this->db->fetch('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read,
                COUNT(CASE WHEN type = "info" THEN 1 END) as info_count,
                COUNT(CASE WHEN type = "success" THEN 1 END) as success_count,
                COUNT(CASE WHEN type = "warning" THEN 1 END) as warning_count,
                COUNT(CASE WHEN type = "error" THEN 1 END) as error_count
            FROM notifications 
            WHERE user_id = :user_id
        ', ['user_id' => $userId]);
        
        return $stats;
    }
    
    /**
     * Create notification preferences
     */
    public function createNotificationPreferences($userId, $preferences) {
        $defaults = [
            'email_notifications' => true,
            'push_notifications' => true,
            'sms_notifications' => false,
            'notification_sound' => true,
            'notification_vibration' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
            'quiet_hours_enabled' => true,
            'types' => ['info', 'success', 'warning', 'error']
        ];
        
        $preferences = array_merge($defaults, $preferences);
        
        return $this->db->insert('notification_preferences', [
            'user_id' => $userId,
            'preferences' => json_encode($preferences),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get notification preferences
     */
    public function getNotificationPreferences($userId) {
        $preferences = $this->db->fetch('
            SELECT preferences FROM notification_preferences 
            WHERE user_id = :user_id
        ', ['user_id' => $userId]);
        
        if ($preferences) {
            return json_decode($preferences['preferences'], true);
        }
        
        return null;
    }
    
    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences($userId, $preferences) {
        $existing = $this->getNotificationPreferences($userId);
        
        if ($existing) {
            $preferences = array_merge($existing, $preferences);
            
            return $this->db->update('notification_preferences',
                [
                    'preferences' => json_encode($preferences),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'user_id = :user_id',
                ['user_id' => $userId]
            );
        } else {
            return $this->createNotificationPreferences($userId, $preferences);
        }
    }
    
    /**
     * Check if notifications are allowed for user at current time
     */
    public function areNotificationsAllowed($userId) {
        $preferences = $this->getNotificationPreferences($userId);
        
        if (!$preferences || !$preferences['quiet_hours_enabled']) {
            return true;
        }
        
        $currentTime = date('H:i');
        $startTime = $preferences['quiet_hours_start'];
        $endTime = $preferences['quiet_hours_end'];
        
        // Handle overnight quiet hours
        if ($startTime > $endTime) {
            return !($currentTime >= $startTime || $currentTime <= $endTime);
        } else {
            return !($currentTime >= $startTime && $currentTime <= $endTime);
        }
    }
}