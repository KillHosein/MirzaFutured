<?php
/**
 * Enhanced API Handler with additional endpoints
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../utils/Utils.php';

class EnhancedAPIHandler {
    private $db;
    private $userManager;
    private $notificationManager;
    
    public function __construct($database, $userManager, $notificationManager) {
        $this->db = $database;
        $this->userManager = $userManager;
        $this->notificationManager = $notificationManager;
    }
    
    /**
     * Handle enhanced API requests
     */
    public function handleEnhancedRequest($endpoint, $method, $data = []) {
        header('Content-Type: application/json');
        
        try {
            switch ($endpoint) {
                case 'notifications':
                    return $this->handleNotifications($method, $data);
                case 'activity':
                    return $this->handleActivity($method, $data);
                case 'export':
                    return $this->handleExport($method, $data);
                case 'settings':
                    return $this->handleSettings($method, $data);
                case 'upload':
                    return $this->handleUpload($method, $data);
                case 'analytics':
                    return $this->handleAnalytics($method, $data);
                case 'health':
                    return $this->handleHealth($method, $data);
                case 'feedback':
                    return $this->handleFeedback($method, $data);
                case 'support':
                    return $this->handleSupport($method, $data);
                default:
                    return $this->errorResponse('Invalid endpoint', 404);
            }
        } catch (Exception $e) {
            error_log('Enhanced API Error: ' . $e->getMessage());
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    /**
     * Handle notifications
     */
    private function handleNotifications($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        $userId = $_SESSION['user_id'];
        
        switch ($method) {
            case 'GET':
                $limit = max(1, min(100, intval($data['limit'] ?? 20)));
                $offset = intval($data['offset'] ?? 0);
                $unreadOnly = filter_var($data['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
                
                $notifications = $this->notificationManager->getUserNotifications($userId, $limit, $offset, $unreadOnly);
                $unreadCount = $this->notificationManager->getUnreadCount($userId);
                
                return $this->successResponse([
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount,
                    'total_count' => count($notifications)
                ]);
                
            case 'POST':
                if (!isset($data['title']) || !isset($data['message'])) {
                    return $this->errorResponse('Title and message are required', 400);
                }
                
                $notificationId = $this->notificationManager->createNotification(
                    $userId,
                    $data['title'],
                    $data['message'],
                    $data['type'] ?? 'info',
                    $data['priority'] ?? 'normal',
                    $data['action_url'] ?? null,
                    $data['action_text'] ?? null,
                    $data['icon'] ?? null
                );
                
                return $this->successResponse(['id' => $notificationId, 'message' => 'Notification created']);
                
            case 'PUT':
                if (!isset($data['id'])) {
                    return $this->errorResponse('Notification ID is required', 400);
                }
                
                $success = $this->notificationManager->markAsRead($data['id'], $userId);
                return $this->successResponse(['success' => $success]);
                
            case 'DELETE':
                if (!isset($data['id'])) {
                    return $this->errorResponse('Notification ID is required', 400);
                }
                
                $success = $this->notificationManager->deleteNotification($data['id'], $userId);
                return $this->successResponse(['success' => $success]);
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    /**
     * Handle activity tracking
     */
    private function handleActivity($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        $userId = $_SESSION['user_id'];
        
        switch ($method) {
            case 'GET':
                $limit = max(1, min(100, intval($data['limit'] ?? 50)));
                $action = $data['action'] ?? null;
                
                $where = 'user_id = :user_id';
                $params = ['user_id' => $userId];
                
                if ($action) {
                    $where .= ' AND action = :action';
                    $params['action'] = $action;
                }
                
                $activity = $this->db->fetchAll("
                    SELECT action, platform, ip_address, created_at 
                    FROM activity_log 
                    WHERE $where 
                    ORDER BY created_at DESC 
                    LIMIT :limit
                ", array_merge($params, ['limit' => $limit]));
                
                return $this->successResponse(['activity' => $activity]);
                
            case 'POST':
                if (!isset($data['action'])) {
                    return $this->errorResponse('Action is required', 400);
                }
                
                $this->db->insert('activity_log', [
                    'user_id' => $userId,
                    'action' => $data['action'],
                    'platform' => $data['platform'] ?? 'web',
                    'ip_address' => Utils::getClientIP(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                return $this->successResponse(['message' => 'Activity logged']);
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    /**
     * Handle data export
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
        
        // Get user data
        $user = $this->userManager->getUserById($userId);
        $stats = $this->userManager->getUserStats($userId);
        $settings = $this->db->fetchAll('
            SELECT setting_key, setting_value 
            FROM user_settings 
            WHERE user_id = :user_id
        ', ['user_id' => $userId]);
        
        $activity = $this->db->fetchAll('
            SELECT action, platform, created_at 
            FROM activity_log 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 100
        ', ['user_id' => $userId]);
        
        $notifications = $this->db->fetchAll('
            SELECT title, message, type, is_read, created_at 
            FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 100
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
            'activity' => $activity,
            'notifications' => $notifications,
            'export_date' => date('Y-m-d H:i:s'),
            'export_format' => $format,
            'app_version' => APP_VERSION
        ];
        
        if ($format === 'csv') {
            return $this->exportCSV($exportData);
        } else {
            return $this->successResponse($exportData);
        }
    }
    
    /**
     * Handle user settings
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
                
                $settingsArray = array_column($settings, 'setting_value', 'setting_key');
                
                // Add notification preferences
                $notificationPrefs = $this->notificationManager->getNotificationPreferences($userId);
                if ($notificationPrefs) {
                    $settingsArray['notifications'] = $notificationPrefs;
                }
                
                return $this->successResponse(['settings' => $settingsArray]);
                
            case 'POST':
            case 'PUT':
                if (!isset($data['key']) || !isset($data['value'])) {
                    return $this->errorResponse('Key and value are required', 400);
                }
                
                $key = SecurityManager::sanitize($data['key']);
                $value = SecurityManager::sanitize($data['value']);
                
                // Handle notification preferences separately
                if ($key === 'notifications') {
                    $this->notificationManager->updateNotificationPreferences($userId, $value);
                    return $this->successResponse(['message' => 'Notification preferences updated']);
                }
                
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
     * Handle file upload
     */
    private function handleUpload($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        if ($method !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        if (!isset($_FILES['file'])) {
            return $this->errorResponse('No file uploaded', 400);
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        if (!SecurityManager::validateFileUpload($file, ALLOWED_FILE_TYPES, MAX_FILE_SIZE)) {
            return $this->errorResponse('Invalid file', 400);
        }
        
        // Generate unique filename
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = Utils::generateRandomString(32) . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Log upload
            $this->db->insert('file_uploads', [
                'user_id' => $_SESSION['user_id'],
                'original_name' => $file['name'],
                'stored_name' => $filename,
                'file_size' => $file['size'],
                'file_type' => $extension,
                'mime_type' => $file['type'],
                'upload_path' => $filepath,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $this->successResponse([
                'filename' => $filename,
                'original_name' => $file['name'],
                'file_size' => $file['size'],
                'file_type' => $extension,
                'upload_url' => '/app/uploads/' . $filename
            ]);
        } else {
            return $this->errorResponse('Failed to upload file', 500);
        }
    }
    
    /**
     * Handle analytics
     */
    private function handleAnalytics($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        $userId = $_SESSION['user_id'];
        $type = $data['type'] ?? 'general';
        
        switch ($type) {
            case 'user':
                $analytics = $this->getUserAnalytics($userId);
                break;
            case 'system':
                $analytics = $this->getSystemAnalytics();
                break;
            case 'engagement':
                $analytics = $this->getEngagementAnalytics($userId);
                break;
            default:
                return $this->errorResponse('Invalid analytics type', 400);
        }
        
        return $this->successResponse(['analytics' => $analytics]);
    }
    
    /**
     * Handle feedback
     */
    private function handleFeedback($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        if ($method !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        if (!isset($data['feedback']) || !isset($data['rating'])) {
            return $this->errorResponse('Feedback and rating are required', 400);
        }
        
        $userId = $_SESSION['user_id'];
        $feedbackId = $this->db->insert('user_feedback', [
            'user_id' => $userId,
            'feedback' => SecurityManager::sanitize($data['feedback']),
            'rating' => intval($data['rating']),
            'category' => $data['category'] ?? 'general',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => Utils::getClientIP(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Send thank you notification
        $this->notificationManager->createNotification(
            $userId,
            'Thank you for your feedback!',
            'We appreciate your input and will use it to improve our service.',
            'success',
            'normal',
            null,
            null,
            'bi-heart'
        );
        
        return $this->successResponse(['id' => $feedbackId, 'message' => 'Feedback submitted successfully']);
    }
    
    /**
     * Handle support requests
     */
    private function handleSupport($method, $data) {
        if (!isset($_SESSION['user_id'])) {
            return $this->errorResponse('Unauthorized', 401);
        }
        
        switch ($method) {
            case 'GET':
                // Get support tickets for user
                $tickets = $this->db->fetchAll('
                    SELECT id, subject, status, priority, created_at, updated_at 
                    FROM support_tickets 
                    WHERE user_id = :user_id 
                    ORDER BY created_at DESC
                ', ['user_id' => $_SESSION['user_id']]);
                
                return $this->successResponse(['tickets' => $tickets]);
                
            case 'POST':
                if (!isset($data['subject']) || !isset($data['message'])) {
                    return $this->errorResponse('Subject and message are required', 400);
                }
                
                $ticketId = $this->db->insert('support_tickets', [
                    'user_id' => $_SESSION['user_id'],
                    'subject' => SecurityManager::sanitize($data['subject']),
                    'message' => SecurityManager::sanitize($data['message']),
                    'status' => 'open',
                    'priority' => $data['priority'] ?? 'normal',
                    'category' => $data['category'] ?? 'general',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Send confirmation notification
                $this->notificationManager->createNotification(
                    $_SESSION['user_id'],
                    'Support ticket created',
                    'Your support ticket has been created. We will respond as soon as possible.',
                    'info',
                    'normal',
                    null,
                    null,
                    'bi-headset'
                );
                
                return $this->successResponse(['id' => $ticketId, 'message' => 'Support ticket created successfully']);
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    /**
     * Get user analytics
     */
    private function getUserAnalytics($userId) {
        $user = $this->userManager->getUserById($userId);
        $stats = $this->userManager->getUserStats($userId);
        
        // Get activity trends
        $activityTrends = $this->db->fetchAll('
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM activity_log 
            WHERE user_id = :user_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ', ['user_id' => $userId]);
        
        // Get most common actions
        $topActions = $this->db->fetchAll('
            SELECT action, COUNT(*) as count 
            FROM activity_log 
            WHERE user_id = :user_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY action
            ORDER BY count DESC
            LIMIT 10
        ', ['user_id' => $userId]);
        
        return [
            'user' => $user,
            'stats' => $stats,
            'activity_trends' => $activityTrends,
            'top_actions' => $topActions,
            'engagement_score' => $this->calculateEngagementScore($userId)
        ];
    }
    
    /**
     * Get system analytics
     */
    private function getSystemAnalytics() {
        $totalUsers = $this->db->fetch('SELECT COUNT(*) as count FROM users')['count'];
        $activeUsers = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE is_active = 1')['count'];
        $todayUsers = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()')['count'];
        
        // Get user growth trend
        $userGrowth = $this->db->fetchAll('
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM users 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ');
        
        // Get activity distribution
        $activityDistribution = $this->db->fetchAll('
            SELECT HOUR(created_at) as hour, COUNT(*) as count 
            FROM activity_log 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ');
        
        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'today_users' => $todayUsers,
            'user_growth' => $userGrowth,
            'activity_distribution' => $activityDistribution,
            'system_health' => $this->getSystemHealth()
        ];
    }
    
    /**
     * Get engagement analytics
     */
    private function getEngagementAnalytics($userId) {
        $engagementScore = $this->calculateEngagementScore($userId);
        
        // Get session frequency
        $sessionFrequency = $this->db->fetch('
            SELECT 
                COUNT(*) as total_sessions,
                AVG(TIMESTAMPDIFF(HOUR, created_at, last_activity)) as avg_session_duration,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM user_sessions 
            WHERE user_id = :user_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ', ['user_id' => $userId]);
        
        // Get feature usage
        $featureUsage = $this->db->fetchAll('
            SELECT action, COUNT(*) as count 
            FROM activity_log 
            WHERE user_id = :user_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY action
            ORDER BY count DESC
        ', ['user_id' => $userId]);
        
        return [
            'engagement_score' => $engagementScore,
            'session_frequency' => $sessionFrequency,
            'feature_usage' => $featureUsage,
            'recommendations' => $this->getEngagementRecommendations($engagementScore)
        ];
    }
    
    /**
     * Calculate engagement score
     */
    private function calculateEngagementScore($userId) {
        $score = 0;
        
        // Activity in last 7 days
        $recentActivity = $this->db->fetch('
            SELECT COUNT(*) as count 
            FROM activity_log 
            WHERE user_id = :user_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ', ['user_id' => $userId])['count'];
        
        $score += min($recentActivity * 10, 30);
        
        // Profile completion
        $user = $this->userManager->getUserById($userId);
        if ($user['first_name']) $score += 10;
        if ($user['last_name']) $score += 5;
        if ($user['username']) $score += 10;
        if ($user['photo_url']) $score += 10;
        
        // Settings configured
        $settingsCount = $this->db->fetch('
            SELECT COUNT(*) as count 
            FROM user_settings 
            WHERE user_id = :user_id
        ', ['user_id' => $userId])['count'];
        
        $score += min($settingsCount * 5, 20);
        
        // Session frequency
        $sessionCount = $this->db->fetch('
            SELECT COUNT(*) as count 
            FROM user_sessions 
            WHERE user_id = :user_id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ', ['user_id' => $userId])['count'];
        
        if ($sessionCount >= 10) $score += 15;
        elseif ($sessionCount >= 5) $score += 10;
        elseif ($sessionCount >= 1) $score += 5;
        
        return min($score, 100);
    }
    
    /**
     * Get engagement recommendations
     */
    private function getEngagementRecommendations($score) {
        $recommendations = [];
        
        if ($score < 30) {
            $recommendations[] = 'Complete your profile to get started';
            $recommendations[] = 'Explore the settings to customize your experience';
        } elseif ($score < 60) {
            $recommendations[] = 'Try using the web app daily to build a habit';
            $recommendations[] = 'Check out the profile section for more features';
        } elseif ($score < 80) {
            $recommendations[] = 'Share the app with friends to get more value';
            $recommendations[] = 'Explore advanced settings and customization';
        } else {
            $recommendations[] = 'You are a power user! Keep exploring new features';
            $recommendations[] = 'Consider providing feedback to help improve the app';
        }
        
        return $recommendations;
    }
    
    /**
     * Get system health
     */
    private function getSystemHealth() {
        $health = [
            'database' => 'unknown',
            'disk_space' => 'unknown',
            'memory' => 'unknown',
            'uptime' => 'unknown'
        ];
        
        // Check database
        try {
            $this->db->query('SELECT 1');
            $health['database'] = 'healthy';
        } catch (Exception $e) {
            $health['database'] = 'error';
        }
        
        // Check disk space
        $diskFree = disk_free_space('.');
        $diskTotal = disk_total_space('.');
        $diskUsage = ($diskTotal - $diskFree) / $diskTotal * 100;
        
        if ($diskUsage < 80) {
            $health['disk_space'] = 'healthy';
        } elseif ($diskUsage < 90) {
            $health['disk_space'] = 'warning';
        } else {
            $health['disk_space'] = 'critical';
        }
        
        return $health;
    }
    
    /**
     * Export CSV
     */
    private function exportCSV($data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="user-data-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // User data
        fputcsv($output, ['User Information']);
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
            fputcsv($output, [$key, is_array($value) ? json_encode($value) : $value]);
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