<?php
/**
 * API Handler for Mirza Web App
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/SecurityManager.php';

class APIHandler {
    private $db;
    private $userManager;
    
    public function __construct($database, $userManager) {
        $this->db = $database;
        $this->userManager = $userManager;
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest($endpoint, $method, $data = []) {
        header('Content-Type: application/json');
        
        try {
            switch ($endpoint) {
                case 'user':
                    return $this->handleUser($method, $data);
                case 'users':
                    return $this->handleUsers($method, $data);
                case 'stats':
                    return $this->handleStats($method, $data);
                case 'settings':
                    return $this->handleSettings($method, $data);
                case 'activity':
                    return $this->handleActivity($method, $data);
                case 'notifications':
                    return $this->handleNotifications($method, $data);
                case 'export':
                    return $this->handleExport($method, $data);
                case 'health':
                    return $this->handleHealth($method, $data);
                default:
                    return $this->errorResponse('Invalid endpoint', 404);
            }
        } catch (Exception $e) {
            error_log('API Error: ' . $e->getMessage());
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    /**
     * Handle user endpoints
     */
    private function handleUser($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        $userId = $_SESSION['user_id'];
        
        switch ($method) {
            case 'GET':
                $user = $this->userManager->getUserById($userId);
                if (!$user) {
                    return $this->errorResponse('User not found', 404);
                }
                
                return $this->successResponse([
                    'user' => [
                        'id' => $user['id'],
                        'telegram_id' => $user['telegram_id'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'username' => $user['username'],
                        'language_code' => $user['language_code'],
                        'created_at' => $user['created_at'],
                        'last_seen' => $user['last_seen']
                    ]
                ]);
                
            case 'PUT':
                // Update user data
                $allowedFields = ['first_name', 'last_name', 'language_code'];
                $updateData = [];
                
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $updateData[$field] = SecurityManager::sanitize($data[$field]);
                    }
                }
                
                if (empty($updateData)) {
                    return $this->errorResponse('No valid fields to update', 400);
                }
                
                $result = $this->userManager->updateUser($userId, $updateData);
                
                if ($result) {
                    return $this->successResponse(['message' => 'User updated successfully']);
                } else {
                    return $this->errorResponse('Failed to update user', 500);
                }
                
            case 'DELETE':
                // Delete user account
                $this->userManager->deleteUser($userId);
                session_destroy();
                return $this->successResponse(['message' => 'Account deleted successfully']);
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    /**
     * Handle users list endpoint (admin only)
     */
    private function handleUsers($method, $data) {
        if (!isset($_SESSION['user_id']) || !$this->isAdmin($_SESSION['user_id'])) {
            return $this->errorResponse('Forbidden', 403);
        }
        
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $page = max(1, intval($data['page'] ?? 1));
        $limit = max(1, min(100, intval($data['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $users = $this->db->fetchAll('
            SELECT id, telegram_id, first_name, last_name, username, language_code, created_at, last_seen, is_active 
            FROM users 
            ORDER BY last_seen DESC 
            LIMIT :limit OFFSET :offset
        ', ['limit' => $limit, 'offset' => $offset]);
        
        $totalUsers = $this->db->fetch('SELECT COUNT(*) as count FROM users')['count'];
        
        return $this->successResponse([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalUsers,
                'pages' => ceil($totalUsers / $limit)
            ]
        ]);
    }
    
    /**
     * Handle stats endpoint
     */
    private function handleStats($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $userId = $_SESSION['user_id'];
        $stats = $this->userManager->getUserStats($userId);
        
        if (!$stats) {
            return $this->errorResponse('Stats not found', 404);
        }
        
        return $this->successResponse(['stats' => $stats]);
    }
    
    /**
     * Handle settings endpoint
     */
    private function handleSettings($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        $userId = $_SESSION['user_id'];
        
        switch ($method) {
            case 'GET':
                $settings = $this->db->fetchAll('
                    SELECT setting_key, setting_value 
                    FROM user_settings 
                    WHERE user_id = :user_id
                ', ['user_id' => $userId]);
                
                $settingsArray = [];
                foreach ($settings as $setting) {
                    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
                }
                
                return $this->successResponse(['settings' => $settingsArray]);
                
            case 'POST':
            case 'PUT':
                if (!isset($data['key']) || !isset($data['value'])) {
                    return $this->errorResponse('Key and value are required', 400);
                }
                
                $key = SecurityManager::sanitize($data['key']);
                $value = SecurityManager::sanitize($data['value']);
                
                // Check if setting exists
                $existing = $this->db->fetch('
                    SELECT id FROM user_settings 
                    WHERE user_id = :user_id AND setting_key = :key
                ', ['user_id' => $userId, 'key' => $key]);
                
                if ($existing) {
                    $this->db->update('user_settings', 
                        ['setting_value' => $value], 
                        'user_id = :user_id AND setting_key = :key',
                        ['user_id' => $userId, 'key' => $key]
                    );
                } else {
                    $this->db->insert('user_settings', [
                        'user_id' => $userId,
                        'setting_key' => $key,
                        'setting_value' => $value
                    ]);
                }
                
                return $this->successResponse(['message' => 'Setting updated successfully']);
                
            case 'DELETE':
                if (!isset($data['key'])) {
                    return $this->errorResponse('Key is required', 400);
                }
                
                $key = SecurityManager::sanitize($data['key']);
                
                $this->db->delete('user_settings', 
                    'user_id = :user_id AND setting_key = :key',
                    ['user_id' => $userId, 'key' => $key]
                );
                
                return $this->successResponse(['message' => 'Setting deleted successfully']);
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    /**
     * Handle activity endpoint
     */
    private function handleActivity($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $userId = $_SESSION['user_id'];
        $limit = max(1, min(100, intval($data['limit'] ?? 20)));
        
        $activity = $this->db->fetchAll('
            SELECT action, platform, created_at 
            FROM user_sessions 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ', ['user_id' => $userId, 'limit' => $limit]);
        
        return $this->successResponse(['activity' => $activity]);
    }
    
    /**
     * Handle notifications endpoint
     */
    private function handleNotifications($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        $userId = $_SESSION['user_id'];
        
        switch ($method) {
            case 'GET':
                $notifications = $this->db->fetchAll('
                    SELECT id, title, message, is_read, created_at 
                    FROM notifications 
                    WHERE user_id = :user_id 
                    ORDER BY created_at DESC 
                    LIMIT 50
                ', ['user_id' => $userId]);
                
                return $this->successResponse(['notifications' => $notifications]);
                
            case 'POST':
                if (!isset($data['title']) || !isset($data['message'])) {
                    return $this->errorResponse('Title and message are required', 400);
                }
                
                $notificationId = $this->db->insert('notifications', [
                    'user_id' => $userId,
                    'title' => SecurityManager::sanitize($data['title']),
                    'message' => SecurityManager::sanitize($data['message']),
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                return $this->successResponse(['id' => $notificationId, 'message' => 'Notification created']);
                
            case 'PUT':
                if (!isset($data['id'])) {
                    return $this->errorResponse('Notification ID is required', 400);
                }
                
                $notificationId = intval($data['id']);
                
                $this->db->update('notifications',
                    ['is_read' => 1],
                    'id = :id AND user_id = :user_id',
                    ['id' => $notificationId, 'user_id' => $userId]
                );
                
                return $this->successResponse(['message' => 'Notification marked as read']);
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    /**
     * Handle export endpoint
     */
    private function handleExport($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $userId = $_SESSION['user_id'];
        $format = $data['format'] ?? 'json';
        
        $user = $this->userManager->getUserById($userId);
        $stats = $this->userManager->getUserStats($userId);
        $settings = $this->db->fetchAll('
            SELECT setting_key, setting_value 
            FROM user_settings 
            WHERE user_id = :user_id
        ', ['user_id' => $userId]);
        
        $exportData = [
            'user' => [
                'id' => $user['id'],
                'telegram_id' => $user['telegram_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'username' => $user['username'],
                'language_code' => $user['language_code'],
                'created_at' => $user['created_at'],
                'last_seen' => $user['last_seen']
            ],
            'stats' => $stats,
            'settings' => array_column($settings, 'setting_value', 'setting_key'),
            'export_date' => date('Y-m-d H:i:s'),
            'version' => APP_VERSION
        ];
        
        if ($format === 'csv') {
            return $this->exportCSV($exportData);
        } else {
            return $this->successResponse($exportData);
        }
    }
    
    /**
     * Handle health check endpoint
     */
    private function handleHealth($method, $data) {
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => APP_VERSION,
            'environment' => ENVIRONMENT,
            'checks' => [
                'database' => $this->checkDatabaseConnection(),
                'session' => isset($_SESSION) ? 'ok' : 'failed',
                'telegram' => $this->checkTelegramConnection()
            ]
        ];
        
        return $this->successResponse($health);
    }
    
    /**
     * Check database connection
     */
    private function checkDatabaseConnection() {
        try {
            $this->db->query('SELECT 1');
            return 'ok';
        } catch (Exception $e) {
            return 'failed';
        }
    }
    
    /**
     * Check Telegram connection
     */
    private function checkTelegramConnection() {
        // Simple check - in real implementation, you might want to test the bot API
        return defined('BOT_TOKEN') && !empty(BOT_TOKEN) ? 'ok' : 'failed';
    }
    
    /**
     * Check if user is admin
     */
    private function isAdmin($userId) {
        // In a real implementation, you would check user roles
        // For now, we'll assume the first user is admin
        $user = $this->userManager->getUserById($userId);
        return $user && $user['telegram_id'] == '123456789'; // Replace with your admin Telegram ID
    }
    
    /**
     * Export data as CSV
     */
    private function exportCSV($data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="user-data-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // User data
        fputcsv($output, ['User Data']);
        fputcsv($output, ['Field', 'Value']);
        foreach ($data['user'] as $key => $value) {
            fputcsv($output, [$key, $value]);
        }
        
        // Stats
        fputcsv($output, []);
        fputcsv($output, ['Statistics']);
        fputcsv($output, ['Field', 'Value']);
        foreach ($data['stats'] as $key => $value) {
            fputcsv($output, [$key, $value]);
        }
        
        // Settings
        fputcsv($output, []);
        fputcsv($output, ['Settings']);
        fputcsv($output, ['Setting', 'Value']);
        foreach ($data['settings'] as $key => $value) {
            fputcsv($output, [$key, $value]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Success response
     */
    private function successResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        return json_encode([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Error response
     */
    private function errorResponse($message, $statusCode = 400) {
        http_response_code($statusCode);
        return json_encode([
            'success' => false,
            'error' => $message
        ]);
    }
}