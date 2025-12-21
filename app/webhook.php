<?php
/**
 * Telegram Bot Webhook Handler
 * Uses existing database configuration from main project
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

// Include main project config to get database connection
require_once dirname(__DIR__, 2) . '/config.php';

// Now include web app files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/UserManager.php';
require_once __DIR__ . '/includes/SecurityManager.php';

class TelegramBot {
    private $db;
    private $userManager;
    private $botToken;
    private $apiUrl;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userManager = new UserManager($this->db);
        $this->botToken = BOT_TOKEN;
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
    }
    
    /**
     * Handle incoming webhook
     */
    public function handleWebhook() {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);
        
        if (!$update) {
            http_response_code(400);
            return;
        }
        
        try {
            // Log the update for debugging
            $this->logUpdate($update);
            
            // Handle different types of updates
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            } elseif (isset($update['inline_query'])) {
                $this->handleInlineQuery($update['inline_query']);
            } elseif (isset($update['web_app_data'])) {
                $this->handleWebAppData($update['web_app_data']);
            }
            
            http_response_code(200);
            
        } catch (Exception $e) {
            error_log('Webhook error: ' . $e->getMessage());
            $this->sendMessage($update['message']['chat']['id'] ?? 0, 'Sorry, an error occurred.');
            http_response_code(500);
        }
    }
    
    /**
     * Handle incoming messages
     */
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Create or update user
        $this->userManager->createOrUpdateUser($message['from']);
        
        // Log the message
        $this->logMessage($message);
        
        // Handle commands
        if (strpos($text, '/') === 0) {
            $this->handleCommand($chatId, $userId, $text, $message);
        } else {
            $this->handleTextMessage($chatId, $userId, $text, $message);
        }
    }
    
    /**
     * Handle bot commands
     */
    private function handleCommand($chatId, $userId, $command, $message) {
        $parts = explode(' ', $command);
        $cmd = strtolower($parts[0]);
        $args = array_slice($parts, 1);
        
        switch ($cmd) {
            case '/start':
                $this->handleStartCommand($chatId, $userId, $message);
                break;
                
            case '/help':
                $this->handleHelpCommand($chatId, $userId);
                break;
                
            case '/profile':
                $this->handleProfileCommand($chatId, $userId);
                break;
                
            case '/settings':
                $this->handleSettingsCommand($chatId, $userId);
                break;
                
            case '/stats':
                $this->handleStatsCommand($chatId, $userId);
                break;
                
            case '/language':
                $this->handleLanguageCommand($chatId, $userId, $args);
                break;
                
            case '/about':
                $this->handleAboutCommand($chatId, $userId);
                break;
                
            case '/contact':
                $this->handleContactCommand($chatId, $userId);
                break;
                
            case '/webapp':
                $this->handleWebAppCommand($chatId, $userId);
                break;
                
            case '/admin':
                $this->handleAdminCommand($chatId, $userId);
                break;
                
            case '/broadcast':
                $this->handleBroadcastCommand($chatId, $userId, $args);
                break;
                
            default:
                $this->sendMessage($chatId, "Unknown command: $cmd");
        }
    }
    
    /**
     * Handle /start command
     */
    private function handleStartCommand($chatId, $userId, $message) {
        $user = $this->userManager->getUserByTelegramId($userId);
        $languageCode = $user['language_code'] ?? 'en';
        
        $welcomeMessage = $this->getBotMessage('welcome_message', $languageCode);
        
        $keyboard = [
            [
                ['text' => '🏠 Home', 'web_app' => ['url' => APP_URL . '/app/index.php']],
                ['text' => '👤 Profile', 'callback_data' => 'profile']
            ],
            [
                ['text' => '⚙️ Settings', 'callback_data' => 'settings'],
                ['text' => '📊 Stats', 'callback_data' => 'stats']
            ],
            [
                ['text' => '❓ Help', 'callback_data' => 'help'],
                ['text' => '📞 Contact', 'callback_data' => 'contact']
            ]
        ];
        
        $this->sendMessage($chatId, $welcomeMessage, [
            'reply_markup' => ['inline_keyboard' => $keyboard]
        ]);
    }
    
    /**
     * Handle /help command
     */
    private function handleHelpCommand($chatId, $userId) {
        $user = $this->userManager->getUserByTelegramId($userId);
        $languageCode = $user['language_code'] ?? 'en';
        
        $helpMessage = $this->getBotMessage('help_message', $languageCode);
        
        $this->sendMessage($chatId, $helpMessage);
    }
    
    /**
     * Handle /profile command
     */
    private function handleProfileCommand($chatId, $userId) {
        $user = $this->userManager->getUserByTelegramId($userId);
        $stats = $this->userManager->getUserStats($user['id']);
        
        $profileText = "👤 *Profile Information*\n\n";
        $profileText .= "*Name:* {$user['first_name']}";
        if ($user['last_name']) {
            $profileText .= " {$user['last_name']}";
        }
        $profileText .= "\n";
        
        if ($user['username']) {
            $profileText .= "*Username:* @{$user['username']}\n";
        }
        
        $profileText .= "*Language:* {$user['language_code']}\n";
        $profileText .= "*Joined:* {$user['created_at']}\n";
        $profileText .= "*Last seen:* {$user['last_seen']}\n\n";
        
        $profileText .= "📊 *Statistics*\n";
        $profileText .= "Total sessions: {$stats['total_sessions']}\n";
        $profileText .= "Days active: {$stats['days_active']}\n";
        
        $keyboard = [
            [
                ['text' => '🌐 Open Web App', 'web_app' => ['url' => APP_URL . '/app/index.php?action=profile']]
            ],
            [
                ['text' => '⚙️ Settings', 'callback_data' => 'settings'],
                ['text' => '📊 Detailed Stats', 'callback_data' => 'detailed_stats']
            ]
        ];
        
        $this->sendMessage($chatId, $profileText, [
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'parse_mode' => 'Markdown'
        ]);
    }
    
    /**
     * Handle /settings command
     */
    private function handleSettingsCommand($chatId, $userId) {
        $user = $this->userManager->getUserByTelegramId($userId);
        
        $settingsText = "⚙️ *Settings*\n\n";
        $settingsText .= "Current language: {$user['language_code']}\n\n";
        $settingsText .= "Choose an option:";
        
        $keyboard = [
            [
                ['text' => '🌐 Language', 'callback_data' => 'change_language'],
                ['text' => '🔔 Notifications', 'callback_data' => 'notifications']
            ],
            [
                ['text' => '🌐 Open Settings', 'web_app' => ['url' => APP_URL . '/app/index.php?action=settings']]
            ],
            [
                ['text' => '🔙 Back', 'callback_data' => 'main_menu']
            ]
        ];
        
        $this->sendMessage($chatId, $settingsText, [
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'parse_mode' => 'Markdown'
        ]);
    }
    
    /**
     * Handle /stats command
     */
    private function handleStatsCommand($chatId, $userId) {
        $user = $this->userManager->getUserByTelegramId($userId);
        $stats = $this->userManager->getUserStats($user['id']);
        
        $statsText = "📊 *Your Statistics*\n\n";
        $statsText .= "Total sessions: {$stats['total_sessions']}\n";
        $statsText .= "Days active: {$stats['days_active']}\n";
        $statsText .= "First seen: {$stats['first_seen']}\n";
        $statsText .= "Last seen: {$stats['last_seen']}\n";
        
        $keyboard = [
            [
                ['text' => '📈 Detailed Stats', 'callback_data' => 'detailed_stats'],
                ['text' => '📊 Activity Log', 'callback_data' => 'activity_log']
            ],
            [
                ['text' => '🌐 View in Web App', 'web_app' => ['url' => APP_URL . '/app/index.php?action=profile']]
            ],
            [
                ['text' => '🔙 Back', 'callback_data' => 'main_menu']
            ]
        ];
        
        $this->sendMessage($chatId, $statsText, [
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'parse_mode' => 'Markdown'
        ]);
    }
    
    /**
     * Handle /webapp command
     */
    private function handleWebAppCommand($chatId, $userId) {
        $webAppUrl = APP_URL . '/app/index.php';
        
        $keyboard = [
            [
                ['text' => '🌐 Open Web App', 'web_app' => ['url' => $webAppUrl]]
            ]
        ];
        
        $this->sendMessage($chatId, "🌐 Click the button below to open the web app:", [
            'reply_markup' => ['inline_keyboard' => $keyboard]
        ]);
    }
    
    /**
     * Handle /admin command
     */
    private function handleAdminCommand($chatId, $userId) {
        if (!$this->isAdmin($userId)) {
            $this->sendMessage($chatId, "❌ You don't have admin privileges.");
            return;
        }
        
        $adminText = "🔧 *Admin Panel*\n\n";
        
        // Get system statistics
        $totalUsers = $this->db->fetch('SELECT COUNT(*) as count FROM users')['count'];
        $activeUsers = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE is_active = 1')['count'];
        $todayUsers = $this->db->fetch('SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()')['count'];
        
        $adminText .= "📊 *Statistics*\n";
        $adminText .= "Total users: $totalUsers\n";
        $adminText .= "Active users: $activeUsers\n";
        $adminText .= "New users today: $todayUsers\n\n";
        
        $adminText .= "🔧 *Admin Tools*\n";
        
        $keyboard = [
            [
                ['text' => '👥 Users', 'callback_data' => 'admin_users'],
                ['text' => '📊 Statistics', 'callback_data' => 'admin_stats']
            ],
            [
                ['text' => '📢 Broadcast', 'callback_data' => 'admin_broadcast'],
                ['text' => '⚙️ System', 'callback_data' => 'admin_system']
            ],
            [
                ['text' => '🔙 Back', 'callback_data' => 'main_menu']
            ]
        ];
        
        $this->sendMessage($chatId, $adminText, [
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'parse_mode' => 'Markdown'
        ]);
    }
    
    /**
     * Check if user is admin
     */
    private function isAdmin($telegramId) {
        return $telegramId == ADMIN_TELEGRAM_ID;
    }
    
    /**
     * Send message via Telegram API
     */
    private function sendMessage($chatId, $text, $options = []) {
        $url = $this->apiUrl . 'sendMessage';
        
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? '',
            'reply_markup' => isset($options['reply_markup']) ? json_encode($options['reply_markup']) : ''
        ];
        
        $this->makeApiRequest($url, $data);
    }
    
    /**
     * Make API request to Telegram
     */
    private function makeApiRequest($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
    
    /**
     * Log update for debugging
     */
    private function logUpdate($update) {
        $this->db->insert('webhook_logs', [
            'update_data' => json_encode($update),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log message
     */
    private function logMessage($message) {
        $this->db->insert('message_logs', [
            'telegram_id' => $message['from']['id'],
            'chat_id' => $message['chat']['id'],
            'message_text' => $message['text'] ?? '',
            'message_data' => json_encode($message),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Handle other callback queries and commands...
     * (Implementation continues with remaining methods)
     */
    private function handleCallbackQuery($callbackQuery) {
        // Implementation for callback queries
        $this->answerCallbackQuery($callbackQuery['id']);
    }
    
    private function handleInlineQuery($inlineQuery) {
        // Implementation for inline queries
    }
    
    private function handleWebAppData($webAppData) {
        // Implementation for web app data
    }
    
    private function handleTextMessage($chatId, $userId, $text, $message) {
        // Handle regular text messages
        $this->sendMessage($chatId, "You said: $text");
    }
    
    private function handleLanguageCommand($chatId, $userId, $args) {
        // Implementation for language command
        if (empty($args)) {
            $this->sendMessage($chatId, "Please specify a language: /language en|fa|ru|ar");
            return;
        }
        
        $languageCode = strtolower($args[0]);
        $validLanguages = ['en', 'fa', 'ru', 'ar'];
        
        if (!in_array($languageCode, $validLanguages)) {
            $this->sendMessage($chatId, "Invalid language code. Available languages: en, fa, ru, ar");
            return;
        }
        
        $user = $this->userManager->getUserByTelegramId($userId);
        $this->userManager->setUserLanguage($user['id'], $languageCode);
        
        $this->sendMessage($chatId, "Language updated to: $languageCode");
    }
    
    private function handleAboutCommand($chatId, $userId) {
        // Implementation for about command
        $aboutText = "🤖 *Mirza Web App Bot*\n\n";
        $aboutText .= "Version: 1.0.0\n";
        $aboutText .= "A professional Telegram Web App with user management, notifications, and admin features.\n\n";
        $aboutText .= "Developed with ❤️ for Telegram community.";
        
        $this->sendMessage($chatId, $aboutText, ['parse_mode' => 'Markdown']);
    }
    
    private function handleContactCommand($chatId, $userId) {
        // Implementation for contact command
        $contactText = "📞 *Contact Information*\n\n";
        $contactText .= "For support and questions, please contact:\n";
        $contactText .= "• Telegram: @your_support_username\n";
        $contactText .= "• Email: support@your-domain.com\n\n";
        $contactText .= "We'll respond as soon as possible!";
        
        $this->sendMessage($chatId, $contactText, ['parse_mode' => 'Markdown']);
    }
    
    private function handleBroadcastCommand($chatId, $userId, $args) {
        // Implementation for broadcast command
        if (!$this->isAdmin($userId)) {
            $this->sendMessage($chatId, "❌ You don't have admin privileges.");
            return;
        }
        
        if (empty($args)) {
            $this->sendMessage($chatId, "Usage: /broadcast <message>");
            return;
        }
        
        $message = implode(' ', $args);
        $this->broadcastMessage($message);
        
        $this->sendMessage($chatId, "✅ Message broadcasted to all users.");
    }
    
    private function answerCallbackQuery($callbackQueryId, $text = '') {
        $url = $this->apiUrl . 'answerCallbackQuery';
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text
        ];
        $this->makeApiRequest($url, $data);
    }
    
    private function broadcastMessage($message) {
        $users = $this->db->fetchAll('SELECT telegram_id FROM users WHERE is_active = 1');
        
        foreach ($users as $user) {
            $this->sendMessage($user['telegram_id'], $message);
            
            // Add delay to avoid rate limiting
            usleep(100000); // 100ms delay
        }
    }
    
    private function getBotMessage($key, $languageCode) {
        // Implementation to get bot messages from database
        $messages = [
            'welcome_message' => [
                'en' => "Welcome to Mirza Web App! 🎉\n\nUse the buttons below to get started or type /help for available commands.",
                'fa' => "به اپلیکیشن وب میرزا خوش آمدید! 🎉\n\nاز دکمه‌های زیر برای شروع استفاده کنید یا /help را تایپ کنید."
            ],
            'help_message' => [
                'en' => "🤖 *Available Commands:*\n\n/start - Start the bot\n/help - Show this help\n/profile - View your profile\n/settings - Settings\n/stats - Your statistics\n/webapp - Open web app\n/language - Change language",
                'fa' => "🤖 *دستورات موجود:*\n\n/start - شروع ربات\n/help - نمایش راهنما\n/profile - مشاهده پروفایل\n/settings - تنظیمات\n/stats - آمار شما\n/webapp - باز کردن وب اپ\n/language - تغییر زبان"
            ]
        ];
        
        return $messages[$key][$languageCode] ?? $messages[$key]['en'] ?? "Message not found: $key";
    }
}

// Handle the webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bot = new TelegramBot();
    $bot->handleWebhook();
} else {
    echo "This endpoint only accepts POST requests.";
}