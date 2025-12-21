<?php
/**
 * User Management Class
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

class UserManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create or update user from Telegram data
     */
    public function createOrUpdateUser($telegramUser) {
        $existingUser = $this->getUserByTelegramId($telegramUser['id']);
        
        if ($existingUser) {
            return $this->updateUser($existingUser['id'], [
                'first_name' => $telegramUser['first_name'] ?? $existingUser['first_name'],
                'last_name' => $telegramUser['last_name'] ?? $existingUser['last_name'],
                'username' => $telegramUser['username'] ?? $existingUser['username'],
                'language_code' => $telegramUser['language_code'] ?? $existingUser['language_code'],
                'last_seen' => date('Y-m-d H:i:s')
            ]);
        } else {
            return $this->createUser($telegramUser);
        }
    }
    
    /**
     * Create new user
     */
    public function createUser($telegramUser) {
        $userData = [
            'telegram_id' => $telegramUser['id'],
            'first_name' => $telegramUser['first_name'] ?? '',
            'last_name' => $telegramUser['last_name'] ?? '',
            'username' => $telegramUser['username'] ?? '',
            'language_code' => $telegramUser['language_code'] ?? 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s'),
            'is_active' => 1
        ];
        
        return $this->db->insert('users', $userData);
    }
    
    /**
     * Update user
     */
    public function updateUser($userId, $data) {
        return $this->db->update('users', $data, 'id = :id', ['id' => $userId]);
    }
    
    /**
     * Get user by Telegram ID
     */
    public function getUserByTelegramId($telegramId) {
        return $this->db->fetch('SELECT * FROM users WHERE telegram_id = :telegram_id', ['telegram_id' => $telegramId]);
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        return $this->db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
    }
    
    /**
     * Get all active users
     */
    public function getActiveUsers() {
        return $this->db->fetchAll('SELECT * FROM users WHERE is_active = 1 ORDER BY last_seen DESC');
    }
    
    /**
     * Update user last seen
     */
    public function updateLastSeen($userId) {
        return $this->updateUser($userId, ['last_seen' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Set user language
     */
    public function setUserLanguage($userId, $languageCode) {
        return $this->updateUser($userId, ['language_code' => $languageCode]);
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($userId) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return null;
        }
        
        return [
            'total_sessions' => $this->getUserSessionCount($userId),
            'first_seen' => $user['created_at'],
            'last_seen' => $user['last_seen'],
            'days_active' => $this->getDaysActive($userId)
        ];
    }
    
    /**
     * Get user session count
     */
    private function getUserSessionCount($userId) {
        $result = $this->db->fetch('SELECT COUNT(*) as count FROM user_sessions WHERE user_id = :user_id', ['user_id' => $userId]);
        return $result['count'] ?? 0;
    }
    
    /**
     * Get days user has been active
     */
    private function getDaysActive($userId) {
        $result = $this->db->fetch('
            SELECT DATEDIFF(MAX(created_at), MIN(created_at)) as days 
            FROM user_sessions 
            WHERE user_id = :user_id
        ', ['user_id' => $userId]);
        return ($result['days'] ?? 0) + 1;
    }
    
    /**
     * Log user session
     */
    public function logSession($userId, $sessionData = []) {
        $sessionData['user_id'] = $userId;
        $sessionData['created_at'] = date('Y-m-d H:i:s');
        $sessionData['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $sessionData['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        return $this->db->insert('user_sessions', $sessionData);
    }
}