<?php
/**
 * Telegram Web Application - Main Integration File
 * Professional Telegram web application with comprehensive features
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/botapi.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/app/UserDataCollection.php';
require_once __DIR__ . '/app/FinancialSystem.php';
require_once __DIR__ . '/app/ServicePurchaseSystem.php';
require_once __DIR__ . '/app/UserPanel.php';
require_once __DIR__ . '/app/AdminDashboard.php';
require_once __DIR__ . '/app/NotificationSystem.php';

class TelegramWebApp {
    
    private $pdo;
    private $userDataCollection;
    private $financialSystem;
    private $servicePurchaseSystem;
    private $userPanel;
    private $adminDashboard;
    private $notificationSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userDataCollection = new UserDataCollection($pdo);
        $this->financialSystem = new FinancialSystem($pdo);
        $this->servicePurchaseSystem = new ServicePurchaseSystem($pdo);
        $this->userPanel = new UserPanel($pdo);
        $this->adminDashboard = new AdminDashboard($pdo);
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Process incoming Telegram updates
     */
    public function processUpdate($update) {
        try {
            if (!isset($update['message']) && !isset($update['callback_query'])) {
                return false;
            }
            
            // Handle callback queries
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }
            
            // Handle messages
            if (isset($update['message'])) {
                return $this->handleMessage($update['message']);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Update processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle callback queries
     */
    private function handleCallbackQuery($callbackQuery) {
        $userId = $callbackQuery['from']['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];
        $messageId = $callbackQuery['message']['message_id'];
        
        // Answer callback query
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callbackQuery['id'],
            'text' => 'در حال پردازش...'
        ]);
        
        // Handle different callback data
        $this->handleCallbackData($userId, $chatId, $data, $messageId);
        
        return true;
    }
    
    /**
     * Handle callback data
     */
    private function handleCallbackData($userId, $chatId, $data, $messageId = null) {
        $parts = explode(':', $data);
        $action = $parts[0];
        
        switch ($action) {
            // Main menu
            case 'main_menu':
                return $this->showMainMenu($userId, $chatId);
                
            // User registration
            case 'start_registration':
                return $this->userDataCollection->startRegistration($userId, $chatId);
                
            // Financial system
            case 'finance_menu':
                return $this->financialSystem->handleFinancialMenu($userId, $chatId);
            case 'finance_deposit':
                return $this->financialSystem->handleFinancialMenu($userId, $chatId, 'deposit');
            case 'finance_withdraw':
                return $this->financialSystem->handleFinancialMenu($userId, $chatId, 'withdraw');
            case 'finance_transactions':
                return $this->financialSystem->handleFinancialMenu($userId, $chatId, 'transactions');
                
            // Service purchase
            case 'service_menu':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId);
            case 'service_browse':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'browse');
            case 'service_cart':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'cart');
            case 'service_my_services':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'my_services');
                
            // User panel
            case 'user_dashboard':
                return $this->userPanel->handleUserPanel($userId, $chatId);
            case 'user_profile':
                return $this->userPanel->handleUserPanel($userId, $chatId, 'profile');
            case 'user_transactions':
                return $this->userPanel->handleUserPanel($userId, $chatId, 'transactions');
            case 'user_services':
                return $this->userPanel->handleUserPanel($userId, $chatId, 'services');
            case 'user_settings':
                return $this->userPanel->handleUserPanel($userId, $chatId, 'settings');
                
            // Admin panel
            case 'admin_main':
                return $this->adminDashboard->handleAdminDashboard($userId, $chatId);
            case 'admin_overview':
                return $this->adminDashboard->handleAdminDashboard($userId, $chatId, 'overview');
            case 'admin_users':
                return $this->adminDashboard->handleAdminDashboard($userId, $chatId, 'users');
            case 'admin_transactions':
                return $this->adminDashboard->handleAdminDashboard($userId, $chatId, 'transactions');
                
            // Transaction approval
            case 'approve_deposit':
                return $this->approveDeposit($userId, $chatId, $parts[1]);
            case 'reject_deposit':
                return $this->rejectDeposit($userId, $chatId, $parts[1]);
                
            // Service categories
            case 'service_category':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'category', $parts[1]);
            case 'service_detail':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'service', $parts[1]);
                
            // Add to cart
            case 'add_to_cart':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'add_to_cart', $parts[1]);
                
            // Payment
            case 'service_checkout':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'checkout');
            case 'pay_wallet':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'payment', 'wallet:' . $parts[1]);
            case 'pay_card':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'payment', 'card:' . $parts[1]);
                
            default:
                return $this->sendErrorMessage($chatId, "عملیات نامعتبر است.");
        }
    }
    
    /**
     * Handle messages
     */
    private function handleMessage($message) {
        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $contact = $message['contact'] ?? null;
        $photo = $message['photo'] ?? null;
        
        // Handle commands
        if (strpos($text, '/') === 0) {
            return $this->handleCommand($userId, $chatId, $text, $message);
        }
        
        // Handle registration process
        if ($this->isUserInRegistration($userId)) {
            return $this->userDataCollection->handleDataCollection($userId, $chatId, $text);
        }
        
        // Handle financial operations
        if ($this->isUserInFinancialOperation($userId)) {
            return $this->handleFinancialInput($userId, $chatId, $text, $photo);
        }
        
        // Handle service purchase
        if ($this->isUserInServicePurchase($userId)) {
            return $this->handleServicePurchaseInput($userId, $chatId, $text);
        }
        
        // Default message handler
        return $this->showMainMenu($userId, $chatId);
    }
    
    /**
     * Handle commands
     */
    private function handleCommand($userId, $chatId, $command, $message) {
        switch ($command) {
            case '/start':
                return $this->handleStartCommand($userId, $chatId, $message);
                
            case '/help':
                return $this->handleHelpCommand($userId, $chatId);
                
            case '/menu':
                return $this->showMainMenu($userId, $chatId);
                
            case '/profile':
                return $this->userPanel->handleUserPanel($userId, $chatId, 'profile');
                
            case '/balance':
                return $this->financialSystem->handleFinancialMenu($userId, $chatId, 'balance');
                
            case '/services':
                return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'browse');
                
            case '/transactions':
                return $this->userPanel->handleUserPanel($userId, $chatId, 'transactions');
                
            case '/admin':
                return $this->adminDashboard->handleAdminDashboard($userId, $chatId);
                
            default:
                return $this->sendErrorMessage($chatId, "دستور نامعتبر است. از /help برای راهنمایی استفاده کنید.");
        }
    }
    
    /**
     * Handle start command
     */
    private function handleStartCommand($userId, $chatId, $message) {
        // Check if user is registered
        if ($this->isUserRegistered($userId)) {
            return $this->showWelcomeBackMessage($userId, $chatId);
        }
        
        // Start registration process
        return $this->userDataCollection->startRegistration($userId, $chatId);
    }
    
    /**
     * Handle help command
     */
    private function handleHelpCommand($userId, $chatId) {
        $message = "🆘 <b>راهنمای استفاده از ربات</b>\n\n";
        $message .= "<b>دستورات اصلی:</b>\n";
        $message .= "• /start - شروع کار با ربات\n";
        $message .= "• /menu - نمایش منوی اصلی\n";
        $message .= "• /profile - مشاهده پروفایل کاربری\n";
        $message .= "• /balance - مشاهده موجودی حساب\n";
        $message .= "• /services - مشاهده سرویس‌ها\n";
        $message .= "• /transactions - مشاهده تراکنش‌ها\n";
        $message .= "• /help - نمایش این راهنما\n\n";
        
        $message .= "<b>امکانات ربات:</b>\n";
        $message .= "• 💳 مدیریت مالی و شارژ حساب\n";
        $message .= "• 🛍️ خرید سرویس‌های مختلف\n";
        $message .= "• 📊 مشاهده تاریخچه تراکنش‌ها\n";
        $message .= "• 👤 مدیریت پروفایل کاربری\n";
        $message .= "• 🔔 دریافت اعلان‌ها و نوتیفیکیشن‌ها\n\n";
        
        $message .= "برای شروع از دکمه زیر استفاده کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🚀 شروع', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        return telegram('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show main menu
     */
    private function showMainMenu($userId, $chatId) {
        $user = $this->getUserById($userId);
        
        if (!$user) {
            return $this->userDataCollection->startRegistration($userId, $chatId);
        }
        
        $balance = number_format($user['balance']);
        $message = "👋 <b>سلام {$user['first_name']}!</b>\n\n";
        $message .= "💰 موجودی شما: <code>{$balance}</code> ریال\n\n";
        $message .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👤 پروفایل کاربری', 'callback_data' => 'user_profile'],
                    ['text' => '💰 مدیریت مالی', 'callback_data' => 'finance_menu']
                ],
                [
                    ['text' => '🛍️ خرید سرویس', 'callback_data' => 'service_browse'],
                    ['text' => '📊 تراکنش‌ها', 'callback_data' => 'user_transactions']
                ],
                [
                    ['text' => '⚙️ تنظیمات', 'callback_data' => 'user_settings'],
                    ['text' => '🆘 پشتیبانی', 'callback_data' => 'user_support']
                ]
            ]
        ];
        
        // Add admin menu if user is admin
        if ($this->adminDashboard->isAdmin($userId)) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '🛠️ پنل مدیریت', 'callback_data' => 'admin_main']
            ];
        }
        
        return telegram('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show welcome back message
     */
    private function showWelcomeBackMessage($userId, $chatId) {
        $user = $this->getUserById($userId);
        $balance = number_format($user['balance']);
        
        $message = "👋 <b>خوش آمدید {$user['first_name']}!</b>\n\n";
        $message .= "💰 موجودی شما: <code>{$balance}</code> ریال\n\n";
        $message .= "شما قبلاً ثبت‌نام کرده‌اید. از منوی زیر استفاده کنید:";
        
        return telegram('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '📋 منوی اصلی', 'callback_data' => 'main_menu']
                    ]
                ]
            ]),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Handle financial input
     */
    private function handleFinancialInput($userId, $chatId, $text, $photo = null) {
        // This would handle financial operation inputs like amounts, card info, etc.
        // Implementation depends on the current financial operation state
        return $this->financialSystem->handleCardToCardDeposit($userId, $chatId, null, $text);
    }
    
    /**
     * Handle service purchase input
     */
    private function handleServicePurchaseInput($userId, $chatId, $text) {
        // This would handle service purchase inputs
        return $this->servicePurchaseSystem->handleServiceMenu($userId, $chatId, 'search', $text);
    }
    
    /**
     * Approve deposit (admin function)
     */
    private function approveDeposit($adminId, $chatId, $transactionId) {
        if (!$this->adminDashboard->isAdmin($adminId)) {
            return $this->sendErrorMessage($chatId, "شما مجوز لازم را ندارید.");
        }
        
        $result = $this->financialSystem->approveDeposit($transactionId, $adminId);
        
        if ($result) {
            return telegram('sendMessage', [
                'chat_id' => $chatId,
                'text' => "✅ تراکنش با موفقیت تأیید شد.",
                'parse_mode' => 'HTML'
            ]);
        } else {
            return $this->sendErrorMessage($chatId, "خطا در تأیید تراکنش.");
        }
    }
    
    /**
     * Reject deposit (admin function)
     */
    private function rejectDeposit($adminId, $chatId, $transactionId) {
        if (!$this->adminDashboard->isAdmin($adminId)) {
            return $this->sendErrorMessage($chatId, "شما مجوز لازم را ندارید.");
        }
        
        $result = $this->financialSystem->rejectDeposit($transactionId, $adminId);
        
        if ($result) {
            return telegram('sendMessage', [
                'chat_id' => $chatId,
                'text' => "❌ تراکنش رد شد.",
                'parse_mode' => 'HTML'
            ]);
        } else {
            return $this->sendErrorMessage($chatId, "خطا در رد تراکنش.");
        }
    }
    
    /**
     * Check if user is registered
     */
    private function isUserRegistered($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if user is in registration process
     */
    private function isUserInRegistration($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM registration_sessions WHERE user_id = ? AND completed = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if user is in financial operation
     */
    private function isUserInFinancialOperation($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM financial_sessions WHERE user_id = ? AND completed = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if user is in service purchase
     */
    private function isUserInServicePurchase($userId) {
        // This would check if user is in a service purchase flow
        // Implementation depends on the service purchase system state
        return false;
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Send error message
     */
    private function sendErrorMessage($chatId, $message) {
        return telegram('sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ خطا: " . $message,
            'parse_mode' => 'HTML'
        ]);
    }
}

/**
 * Telegram Webhook Handler
 */
class TelegramWebhook {
    
    private $telegramWebApp;
    
    public function __construct($pdo) {
        $this->telegramWebApp = new TelegramWebApp($pdo);
    }
    
    /**
     * Process incoming webhook update
     */
    public function processUpdate() {
        // Get update from input
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);
        
        if (!$update) {
            http_response_code(400);
            echo "Invalid update";
            return;
        }
        
        // Process the update
        $result = $this->telegramWebApp->processUpdate($update);
        
        if ($result) {
            http_response_code(200);
            echo "OK";
        } else {
            http_response_code(500);
            echo "Error processing update";
        }
    }
}

/**
 * Main execution
 */
try {
    // Initialize the webhook handler
    $webhook = new TelegramWebhook($pdo);
    
    // Process the update
    $webhook->processUpdate();
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo "Internal server error";
}

?>