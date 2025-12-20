<?php
/**
 * Wallet System Integration
 * Integration script for connecting wallet system with the main bot
 */

require_once __DIR__ . '/wallet/database.php';
require_once __DIR__ . '/wallet/card_to_card_manager.php';
require_once __DIR__ . '/wallet/bot_interface.php';

/**
 * Wallet Integration Class
 * Handles integration between wallet system and main bot
 */
class WalletIntegration {
    private $walletDatabase;
    private $cardToCardManager;
    private $botInterface;
    
    public function __construct() {
        $this->walletDatabase = new WalletDatabase();
        $this->cardToCardManager = new CardToCardManager();
        $this->botInterface = new WalletBotInterface();
    }
    
    /**
     * Initialize wallet system
     */
    public function initialize() {
        try {
            // Initialize database tables
            $result = $this->walletDatabase->initializeTables();
            if (!$result) {
                throw new Exception("Failed to initialize wallet database");
            }
            
            // Add wallet menu to main bot keyboard
            $this->addWalletMenuToKeyboard();
            
            return [
                'success' => true,
                'message' => 'Wallet system initialized successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to initialize wallet system: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Add wallet menu to main bot keyboard
     */
    private function addWalletMenuToKeyboard() {
        global $connect;
        
        // Check if wallet menu already exists
        $result = mysqli_query($connect, "SELECT * FROM textbot WHERE id_text = 'wallet_menu'");
        if (mysqli_num_rows($result) == 0) {
            // Add wallet menu text
            mysqli_query($connect, "INSERT INTO textbot (id_text, text) VALUES ('wallet_menu', '💎 کیف پول')");
        }
        
        // Update main keyboard to include wallet option
        $keyboardMain = json_encode([
            'keyboard' => [
                [
                    ['text' => 'text_sell'],
                    ['text' => 'text_extend']
                ],
                [
                    ['text' => 'text_usertest'],
                    ['text' => 'text_wheel_luck']
                ],
                [
                    ['text' => 'text_Purchased_services'],
                    ['text' => 'wallet_menu'] // Add wallet menu
                ],
                [
                    ['text' => 'text_affiliates'],
                    ['text' => 'text_Tariff_list']
                ],
                [
                    ['text' => 'text_support'],
                    ['text' => 'text_help']
                ]
            ]
        ]);
        
        mysqli_query($connect, "UPDATE setting SET keyboardmain = '$keyboardMain'");
    }
    
    /**
     * Handle wallet-related bot commands
     */
    public function handleBotCommand($userId, $command, $message = null) {
        try {
            // Handle different wallet commands
            switch ($command) {
                case 'wallet':
                case 'accountwallet':
                    return $this->showWalletMenu($userId);
                    
                case 'wallet_balance':
                    return $this->showWalletBalance($userId);
                    
                case 'wallet_deposit':
                    return $this->showDepositOptions($userId);
                    
                case 'wallet_transactions':
                    return $this->showWalletTransactions($userId);
                    
                case 'card_to_card_deposit':
                    return $this->startCardToCardDeposit($userId);
                    
                default:
                    // Handle step-based interactions
                    return $this->handleStepInteraction($userId, $command, $message);
            }
        } catch (Exception $e) {
            error_log("Wallet bot command error: " . $e->getMessage());
            return [
                'text' => '❌ خطا در پردازش درخواست شما. لطفاً دوباره تلاش کنید.',
                'keyboard' => $this->getMainKeyboard()
            ];
        }
    }
    
    /**
     * Handle step-based interactions for card-to-card deposit
     */
    private function handleStepInteraction($userId, $step, $message) {
        global $connect;
        
        // Get current user step
        $userData = mysqli_fetch_assoc(mysqli_query($connect, "SELECT step FROM user WHERE id = '$userId'"));
        $currentStep = $userData['step'] ?? '';
        
        // Handle card-to-card deposit steps
        if (strpos($currentStep, 'card_to_card_') === 0) {
            return $this->botInterface->handleCardToCardForm($userId, str_replace('card_to_card_', '', $currentStep), $message);
        }
        
        return false;
    }
    
    /**
     * Show wallet menu
     */
    private function showWalletMenu($userId) {
        $balance = $this->walletDatabase->getUserBalance($userId);
        if ($balance === false) {
            $balance = 0;
        }
        
        $text = "💎 <b>کیف پول شما</b>\n\n";
        $text .= "💰 موجودی فعلی: <code>" . number_format($balance) . "</code> تومان\n\n";
        $text .= "لطفاً یکی از گزینههای زیر را انتخاب کنید:";
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '💳 افزایش موجودی', 'callback_data' => 'wallet_deposit'],
                    ['text' => '📋 تراکنشها', 'callback_data' => 'wallet_transactions']
                ],
                [
                    ['text' => '💎 موجودی کیف پول', 'callback_data' => 'wallet_balance']
                ],
                [
                    ['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']
                ]
            ]
        ]);
        
        return [
            'text' => $text,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML'
        ];
    }
    
    /**
     * Show wallet balance
     */
    private function showWalletBalance($userId) {
        $balance = $this->walletDatabase->getUserBalance($userId);
        if ($balance === false) {
            $balance = 0;
        }
        
        $text = "💰 <b>موجودی کیف پول شما</b>\n\n";
        $text .= "💎 موجودی فعلی: <code>" . number_format($balance) . "</code> تومان\n\n";
        
        if ($balance > 0) {
            $text .= "✅ شما میتوانید از این موجودی برای خرید سرویسها استفاده کنید.\n";
        } else {
            $text .= "⚠️ موجودی شما صفر است. برای خرید سرویس، ابتدا باید کیف پول خود را شارژ کنید.\n";
        }
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '💳 افزایش موجودی', 'callback_data' => 'wallet_deposit']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'wallet']
                ]
            ]
        ]);
        
        return [
            'text' => $text,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML'
        ];
    }
    
    /**
     * Show deposit options
     */
    private function showDepositOptions($userId) {
        $text = "💳 <b>افزایش موجودی کیف پول</b>\n\n";
        $text .= "لطفاً روش مورد نظر برای شارژ کیف پول را انتخاب کنید:";
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '💳 کارت به کارت', 'callback_data' => 'card_to_card_deposit']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'wallet']
                ]
            ]
        ]);
        
        return [
            'text' => $text,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML'
        ];
    }
    
    /**
     * Show wallet transactions
     */
    private function showWalletTransactions($userId) {
        $transactions = $this->walletDatabase->getUserWalletTransactions($userId, 10, 0);
        
        $text = "📋 <b>تراکنشهای کیف پول شما</b>\n\n";
        
        if (empty($transactions)) {
            $text .= "شما هنوز هیچ تراکنشی ندارید.";
        } else {
            foreach ($transactions as $transaction) {
                $typeIcon = $this->getTransactionTypeIcon($transaction['transaction_type']);
                $amountColor = $transaction['amount'] > 0 ? '+' : '';
                
                $text .= $typeIcon . " ";
                $text .= "<code>" . $amountColor . number_format($transaction['amount']) . "</code> تومان\n";
                $text .= "💬 " . ($transaction['description'] ?? 'بدون توضیح') . "\n";
                $text .= "📅 " . jdate('Y/m/d H:i', strtotime($transaction['created_at'])) . "\n";
                $text .= "💰 موجودی: " . number_format($transaction['balance_after']) . " تومان\n\n";
            }
        }
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '💎 کیف پول من', 'callback_data' => 'wallet']
                ]
            ]
        ]);
        
        return [
            'text' => $text,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML'
        ];
    }
    
    /**
     * Start card-to-card deposit process
     */
    private function startCardToCardDeposit($userId) {
        // Get destination card information from settings
        global $connect;
        $destinationCard = mysqli_fetch_assoc(mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'destination_card_number'"));
        $destinationCardNumber = $destinationCard['ValuePay'] ?? '6037991234567890';
        
        $bankInfo = mysqli_fetch_assoc(mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'destination_bank_name'"));
        $bankName = $bankInfo['ValuePay'] ?? 'بانک ملی ایران';
        
        $text = "💳 <b>افزایش موجودی از طریق کارت به کارت</b>\n\n";
        $text .= "📋 <b>دستورالعمل:</b>\n";
        $text .= "1️⃣ از کارت بانکی خود به شماره کارت زیر انتقال وجه انجام دهید\n";
        $text .= "2️⃣ پس از انتقال، اطلاعات تراکنش را در ادامه وارد کنید\n\n";
        
        $text .= "💳 <b>شماره کارت مقصد:</b>\n";
        $text .= "<code>$destinationCardNumber</code>\n";
        $text .= "🏦 <b>بانک:</b> $bankName\n\n";
        
        $text .= "⚠️ <b>نکات مهم:</b>\n";
        $text .= "• حداقل مبلغ: ۱۰٬۰۰۰ تومان\n";
        $text .= "• پس از انتقال، حتماً رسید یا شماره پیگیری را نگه دارید\n";
        $text .= "• پردازش تراکنش ممکن است تا چند دقیقه زمان ببرد\n\n";
        
        $text .= "برای ادامه، دکمه زیر را فشار دهید:";
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ انجام دادم، ادامه میدهم', 'callback_data' => 'card_to_card_form']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'wallet_deposit']
                ]
            ]
        ]);
        
        return [
            'text' => $text,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML'
        ];
    }
    
    /**
     * Get transaction type icon
     */
    private function getTransactionTypeIcon($type) {
        $icons = [
            'deposit' => '💰',
            'withdrawal' => '💸',
            'refund' => '🔄',
            'purchase' => '🛒',
            'commission' => '💎'
        ];
        return $icons[$type] ?? '📊';
    }
    
    /**
     * Get main keyboard
     */
    private function getMainKeyboard() {
        return json_encode([
            'keyboard' => [
                [
                    ['text' => 'text_sell'],
                    ['text' => 'text_extend']
                ],
                [
                    ['text' => 'text_usertest'],
                    ['text' => 'text_wheel_luck']
                ],
                [
                    ['text' => 'text_Purchased_services'],
                    ['text' => 'wallet_menu']
                ],
                [
                    ['text' => 'text_affiliates'],
                    ['text' => 'text_Tariff_list']
                ],
                [
                    ['text' => 'text_support'],
                    ['text' => 'text_help']
                ]
            ],
            'resize_keyboard' => true
        ]);
    }
    
    /**
     * Handle callback queries
     */
    public function handleCallbackQuery($userId, $callbackData) {
        try {
            // Handle wallet-related callback queries
            if (strpos($callbackData, 'wallet') === 0 || strpos($callbackData, 'card_to_card') === 0) {
                return $this->botInterface->handleCallbackQuery($userId, $callbackData);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Wallet callback query error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process payment using wallet balance
     */
    public function processWalletPayment($userId, $amount, $description, $referenceType = null, $referenceId = null) {
        try {
            // Get current balance
            $currentBalance = $this->walletDatabase->getUserBalance($userId);
            if ($currentBalance === false) {
                throw new Exception("Failed to get user balance");
            }
            
            // Check if user has sufficient balance
            if ($currentBalance < $amount) {
                return [
                    'success' => false,
                    'message' => 'موجودی کیف پول کافی نیست'
                ];
            }
            
            // Calculate new balance
            $newBalance = $currentBalance - $amount;
            
            // Update user balance
            $balanceResult = $this->walletDatabase->updateUserBalance($userId, $newBalance);
            if (!$balanceResult) {
                throw new Exception("Failed to update user balance");
            }
            
            // Record wallet transaction
            $walletTransaction = [
                'user_id' => $userId,
                'transaction_type' => 'purchase',
                'amount' => -$amount, // Negative for withdrawal
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId
            ];
            
            $this->walletDatabase->insertWalletTransaction($walletTransaction);
            
            return [
                'success' => true,
                'message' => 'پرداخت از کیف پول با موفقیت انجام شد',
                'new_balance' => $newBalance
            ];
            
        } catch (Exception $e) {
            error_log("Wallet payment error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در پردازش پرداخت از کیف پول'
            ];
        }
    }
    
    /**
     * Refund to wallet
     */
    public function refundToWallet($userId, $amount, $description, $referenceType = null, $referenceId = null) {
        try {
            // Get current balance
            $currentBalance = $this->walletDatabase->getUserBalance($userId);
            if ($currentBalance === false) {
                throw new Exception("Failed to get user balance");
            }
            
            // Calculate new balance
            $newBalance = $currentBalance + $amount;
            
            // Update user balance
            $balanceResult = $this->walletDatabase->updateUserBalance($userId, $newBalance);
            if (!$balanceResult) {
                throw new Exception("Failed to update user balance");
            }
            
            // Record wallet transaction
            $walletTransaction = [
                'user_id' => $userId,
                'transaction_type' => 'refund',
                'amount' => $amount,
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId
            ];
            
            $this->walletDatabase->insertWalletTransaction($walletTransaction);
            
            return [
                'success' => true,
                'message' => 'بازگشت وجه به کیف پول با موفقیت انجام شد',
                'new_balance' => $newBalance
            ];
            
        } catch (Exception $e) {
            error_log("Wallet refund error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در پردازش بازگشت وجه به کیف پول'
            ];
        }
    }
    
    /**
     * Get wallet statistics
     */
    public function getWalletStatistics($userId = null) {
        try {
            global $pdo;
            
            if ($userId) {
                // Get statistics for specific user
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_transactions,
                        SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
                        SUM(CASE WHEN transaction_type = 'withdrawal' OR transaction_type = 'purchase' THEN amount ELSE 0 END) as total_withdrawals,
                        SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END) as total_refunds,
                        MAX(created_at) as last_transaction_date
                    FROM wallet_transactions 
                    WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get current balance
                $balance = $this->walletDatabase->getUserBalance($userId);
                $stats['current_balance'] = $balance;
                
                return $stats;
            } else {
                // Get global statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_transactions,
                        COUNT(DISTINCT user_id) as total_users,
                        SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
                        SUM(CASE WHEN transaction_type = 'withdrawal' OR transaction_type = 'purchase' THEN amount ELSE 0 END) as total_withdrawals,
                        SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END) as total_refunds,
                        MAX(created_at) as last_transaction_date
                    FROM wallet_transactions
                ");
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Wallet statistics error: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Helper function to format Persian date
 */
function jdate($format, $timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    $date = date($format, $timestamp);
    
    // Replace English numbers with Persian numbers
    $persianNumbers = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'
    ];
    
    return strtr($date, $persianNumbers);
}

?>