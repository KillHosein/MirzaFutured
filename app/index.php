<?php
/**
 * Main Application Class
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/SecurityManager.php';
require_once __DIR__ . '/includes/TelegramWebApp.php';
require_once __DIR__ . '/includes/UserManager.php';
require_once __DIR__ . '/includes/NotificationManager.php';
require_once __DIR__ . '/includes/AdminManager.php';
require_once __DIR__ . '/api/APIHandler.php';

class MirzaWebApp {
    private $db;
    private $telegram;
    private $userManager;
    private $notificationManager;
    private $adminManager;
    private $apiHandler;
    private $currentUser = null;
    
    public function __construct() {
        session_start();
        $this->initializeDatabase();
        $this->initializeTelegram();
        $this->initializeUserManager();
        $this->initializeNotificationManager();
        $this->initializeAdminManager();
        $this->initializeAPIHandler();
        $this->authenticateUser();
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase() {
        try {
            $this->db = Database::getInstance();
        } catch (Exception $e) {
            $this->handleError('Database connection failed', $e->getMessage());
        }
    }
    
    /**
     * Initialize Telegram Web App
     */
    private function initializeTelegram() {
        $this->telegram = new TelegramWebApp(BOT_TOKEN);
    }
    
    /**
     * Initialize user manager
     */
    private function initializeUserManager() {
        $this->userManager = new UserManager($this->db);
    }
    
    /**
     * Initialize notification manager
     */
    private function initializeNotificationManager() {
        $this->notificationManager = new NotificationManager($this->db);
    }
    
    /**
     * Initialize admin manager
     */
    private function initializeAdminManager() {
        $this->adminManager = new AdminManager();
    }
    
    /**
     * Initialize API handler
     */
    private function initializeAPIHandler() {
        $this->apiHandler = new APIHandler($this->db, $this->userManager);
    }
    
    /**
     * Authenticate user from Telegram Web App data
     */
    private function authenticateUser() {
        if (isset($_GET['initData'])) {
            $initData = $_GET['initData'];
            
            if ($this->telegram->validateInitData($initData)) {
                $userData = $this->telegram->getUserData($initData);
                
                if ($userData) {
                    $userId = $this->userManager->createOrUpdateUser($userData);
                    $this->currentUser = $this->userManager->getUserById($userId);
                    
                    // Log session
                    $this->userManager->logSession($userId, [
                        'action' => 'login',
                        'platform' => 'webapp'
                    ]);
                    
                    // Store user in session
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['telegram_user'] = $userData;
                }
            }
        } elseif (isset($_SESSION['user_id'])) {
            $this->currentUser = $this->userManager->getUserById($_SESSION['user_id']);
        }
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        return $this->currentUser;
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return $this->currentUser !== null;
    }
    
    /**
     * Handle main application logic
     */
    public function handleRequest() {
        try {
            // Handle different actions
            $action = $_GET['action'] ?? 'home';
            
            // Check admin access for admin actions
            if (in_array($action, ['admin', 'admin_api']) && (!$this->isAuthenticated() || !$this->adminManager->isAdmin($this->currentUser['id']))) {
                $this->redirect('index.php');
                return;
            }
            
            switch ($action) {
                case 'profile':
                    $this->handleProfile();
                    break;
                case 'settings':
                    $this->handleSettings();
                    break;
                case 'admin':
                    $this->handleAdmin();
                    break;
                case 'api':
                    $this->handleAPI();
                    break;
                case 'admin_api':
                    $this->handleAdminAPI();
                    break;
                default:
                    $this->handleHome();
            }
        } catch (Exception $e) {
            $this->handleError('Request handling failed', $e->getMessage());
        }
    }
    
    /**
     * Handle home page
     */
    private function handleHome() {
        $this->render('home', [
            'user' => $this->currentUser,
            'authenticated' => $this->isAuthenticated()
        ]);
    }
    
    /**
     * Handle profile page
     */
    private function handleProfile() {
        if (!$this->isAuthenticated()) {
            $this->redirect('index.php');
            return;
        }
        
        $stats = $this->userManager->getUserStats($this->currentUser['id']);
        
        $this->render('profile', [
            'user' => $this->currentUser,
            'stats' => $stats
        ]);
    }
    
    /**
     * Handle settings page
     */
    private function handleSettings() {
        if (!$this->isAuthenticated()) {
            $this->redirect('index.php');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleSettingsUpdate();
            return;
        }
        
        $this->render('settings', [
            'user' => $this->currentUser
        ]);
    }
    
    /**
     * Handle settings update
     */
    private function handleSettingsUpdate() {
        if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->addError('Invalid request');
            $this->redirect('index.php?action=settings');
            return;
        }
        
        $language = SecurityManager::sanitize($_POST['language'] ?? 'en');
        
        $this->userManager->setUserLanguage($this->currentUser['id'], $language);
        
        $this->addSuccess('Settings updated successfully');
        $this->redirect('index.php?action=settings');
    }
    
    /**
     * Handle admin API requests
     */
    private function handleAdminAPI() {
        header('Content-Type: application/json');
        
        if (!$this->isAuthenticated() || !$this->adminManager->isAdmin($this->currentUser['id'])) {
            $this->jsonResponse(['error' => 'Forbidden'], 403);
            return;
        }
        
        $endpoint = $_GET['endpoint'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        try {
            switch ($endpoint) {
                case 'stats':
                    $this->jsonResponse($this->adminManager->getDashboardStats());
                    break;
                    
                case 'users':
                    $page = intval($_GET['page'] ?? 1);
                    $limit = intval($_GET['limit'] ?? 20);
                    $filters = $_GET['filters'] ?? [];
                    $result = $this->adminManager->getUsers($page, $limit, $filters);
                    $this->jsonResponse($result);
                    break;
                    
                case 'users/ban':
                    if ($method === 'POST') {
                        $userId = intval($_GET['user_id'] ?? 0);
                        $reason = $data['reason'] ?? '';
                        $success = $this->adminManager->banUser($userId, $reason);
                        $this->jsonResponse(['success' => $success]);
                    }
                    break;
                    
                case 'users/unban':
                    if ($method === 'POST') {
                        $userId = intval($_GET['user_id'] ?? 0);
                        $success = $this->adminManager->unbanUser($userId);
                        $this->jsonResponse(['success' => $success]);
                    }
                    break;
                    
                case 'broadcast':
                    if ($method === 'POST') {
                        $title = $data['title'] ?? '';
                        $message = $data['message'] ?? '';
                        $type = $data['type'] ?? 'info';
                        $priority = $data['priority'] ?? 'normal';
                        $sendNotification = $data['send_notification'] ?? false;
                        
                        $result = $this->adminManager->sendBroadcast($title, $message, $type, $priority, $sendNotification);
                        $this->jsonResponse($result);
                    }
                    break;
                    
                case 'settings':
                    if ($method === 'GET') {
                        $settings = $this->adminManager->getSystemSettings();
                        $this->jsonResponse(['settings' => $settings]);
                    } elseif ($method === 'POST') {
                        $key = $data['key'] ?? '';
                        $value = $data['value'] ?? '';
                        $success = $this->adminManager->updateSystemSetting($key, $value);
                        $this->jsonResponse(['success' => $success]);
                    }
                    break;
                    
                case 'maintenance/toggle':
                    if ($method === 'POST') {
                        $enabled = $this->adminManager->toggleMaintenanceMode();
                        $this->jsonResponse(['enabled' => $enabled]);
                    }
                    break;
                    
                case 'backup':
                    if ($method === 'POST') {
                        $result = $this->adminManager->createBackup();
                        $this->jsonResponse($result);
                    }
                    break;
                    
                case 'logs':
                    $level = $_GET['level'] ?? null;
                    $limit = intval($_GET['limit'] ?? 100);
                    $logs = $this->adminManager->getSystemLogs($level, $limit);
                    $this->jsonResponse(['logs' => $logs]);
                    break;
                    
                case 'logs/clear':
                    if ($method === 'POST') {
                        $days = intval($data['days'] ?? 30);
                        $deleted = $this->adminManager->clearSystemLogs($days);
                        $this->jsonResponse(['deleted' => $deleted]);
                    }
                    break;
                    
                default:
                    $this->jsonResponse(['error' => 'Invalid admin endpoint'], 404);
            }
        } catch (Exception $e) {
            error_log('Admin API error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }
    private function handleAdmin() {
        if (!$this->isAuthenticated() || !$this->adminManager->isAdmin($this->currentUser['id'])) {
            $this->redirect('index.php');
            return;
        }
        
        $this->render('admin', [
            'user' => $this->currentUser,
            'stats' => $this->adminManager->getDashboardStats()
        ]);
    }
    
    private function handleAPI() {
        header('Content-Type: application/json');
        
        $endpoint = $_GET['endpoint'] ?? '';
        
        switch ($endpoint) {
            case 'user':
                $this->handleAPIUser();
                break;
            case 'stats':
                $this->handleAPIStats();
                break;
            default:
                $this->jsonResponse(['error' => 'Invalid endpoint'], 404);
        }
    }
    
    /**
     * Handle user API
     */
    private function handleAPIUser() {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        
        $this->jsonResponse([
            'user' => [
                'id' => $this->currentUser['id'],
                'telegram_id' => $this->currentUser['telegram_id'],
                'first_name' => $this->currentUser['first_name'],
                'last_name' => $this->currentUser['last_name'],
                'username' => $this->currentUser['username'],
                'language_code' => $this->currentUser['language_code']
            ]
        ]);
    }
    
    /**
     * Handle stats API
     */
    private function handleAPIStats() {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        
        $stats = $this->userManager->getUserStats($this->currentUser['id']);
        $this->jsonResponse(['stats' => $stats]);
    }
    
    /**
     * Render template
     */
    private function render($template, $data = []) {
        extract($data);
        
        // Set CSRF token
        $csrfToken = SecurityManager::generateCSRFToken();
        
        include __DIR__ . '/components/header.php';
        include __DIR__ . '/components/' . $template . '.php';
        include __DIR__ . '/components/footer.php';
    }
    
    /**
     * JSON response
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
    
    /**
     * Redirect to URL
     */
    private function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Add success message
     */
    private function addSuccess($message) {
        $_SESSION['success'] = $message;
    }
    
    /**
     * Add error message
     */
    private function addError($message) {
        $_SESSION['error'] = $message;
    }
    
    /**
     * Handle errors
     */
    private function handleError($title, $message) {
        error_log("[$title] $message");
        
        if (ENVIRONMENT === 'development') {
            $this->render('error', [
                'title' => $title,
                'message' => $message
            ]);
        } else {
            $this->render('error', [
                'title' => 'Application Error',
                'message' => 'An unexpected error occurred. Please try again later.'
            ]);
        }
        exit;
    }
    
    /**
     * Initialize database tables
     */
    public function initializeDatabaseTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telegram_id BIGINT UNIQUE NOT NULL,
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            username VARCHAR(255),
            language_code VARCHAR(10) DEFAULT 'en',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT 1,
            INDEX idx_telegram_id (telegram_id),
            INDEX idx_last_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50),
            platform VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            setting_key VARCHAR(100),
            setting_value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_setting (user_id, setting_key),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->db->getConnection()->exec($sql);
        } catch (Exception $e) {
            $this->handleError('Database initialization failed', $e->getMessage());
        }
    }
}

// Initialize and run application
try {
    $app = new MirzaWebApp();
    $app->handleRequest();
} catch (Exception $e) {
    error_log('Application error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Application error occurred';
}
?>