<?php
/**
 * Enhanced Financial System - Card-to-Card Transfer and Balance Management
 * Professional financial management system for Telegram web application
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

class FinancialSystem {
    
    private $pdo;
    private $telegram;
    private $notificationSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Handle financial operations menu
     */
    public function handleFinancialMenu($userId, $chatId, $action = null) {
        try {
            if (!$this->isUserRegistered($userId)) {
                return $this->sendRegistrationRequired($chatId);
            }
            
            switch ($action) {
                case 'deposit':
                    return $this->showDepositOptions($userId, $chatId);
                    
                case 'withdraw':
                    return $this->showWithdrawOptions($userId, $chatId);
                    
                case 'balance':
                    return $this->showBalanceInfo($userId, $chatId);
                    
                case 'transactions':
                    return $this->showTransactionHistory($userId, $chatId);
                    
                case 'transfer':
                    return $this->showTransferOptions($userId, $chatId);
                    
                default:
                    return $this->showFinancialMenu($userId, $chatId);
            }
            
        } catch (Exception $e) {
            error_log("Financial menu error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Show financial main menu
     */
    private function showFinancialMenu($userId, $chatId) {
        $user = $this->getUserById($userId);
        $balance = number_format($user['balance']);
        
        $message = "💰 <b>مدیریت مالی</b>\n\n";
        $message .= "💳 موجودی فعلی: <code>{$balance}</code> ریال\n\n";
        $message .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💳 شارژ حساب', 'callback_data' => 'finance_deposit'],
                    ['text' => '💸 برداشت وجه', 'callback_data' => 'finance_withdraw']
                ],
                [
                    ['text' => '🔄 انتقال وجه', 'callback_data' => 'finance_transfer'],
                    ['text' => '📊 تراکنش‌ها', 'callback_data' => 'finance_transactions']
                ],
                [
                    ['text' => '💎 کیف پول دیجیتال', 'callback_data' => 'finance_wallet'],
                    ['text' => '📈 گزارش مالی', 'callback_data' => 'finance_report']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show deposit options
     */
    private function showDepositOptions($userId, $chatId) {
        $message = "💳 <b>شارژ حساب</b>\n\n";
        $message .= "روش مورد نظر برای شارژ حساب را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💳 کارت به کارت', 'callback_data' => 'deposit_card_to_card'],
                    ['text' => '🌐 درگاه پرداخت', 'callback_data' => 'deposit_online']
                ],
                [
                    ['text' => '💎 کیف پول دیجیتال', 'callback_data' => 'deposit_crypto'],
                    ['text' => '🏦 انتقال بانکی', 'callback_data' => 'deposit_bank']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'finance_menu']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Handle card-to-card deposit
     */
    public function handleCardToCardDeposit($userId, $chatId, $step = null, $data = null) {
        try {
            $session = $this->getFinancialSession($userId, 'deposit_card_to_card');
            
            if (!$session) {
                $this->createFinancialSession($userId, 'deposit_card_to_card');
                return $this->showCardToCardInstructions($userId, $chatId);
            }
            
            $currentStep = $session['current_step'] ?? 'instructions';
            
            switch ($currentStep) {
                case 'instructions':
                    return $this->requestDepositAmount($userId, $chatId);
                    
                case 'amount':
                    return $this->processDepositAmount($userId, $chatId, $data);
                    
                case 'card_info':
                    return $this->processCardInfo($userId, $chatId, $data);
                    
                case 'receipt':
                    return $this->processReceipt($userId, $chatId, $data);
                    
                case 'confirm':
                    return $this->confirmCardToCardDeposit($userId, $chatId, $data);
                    
                default:
                    return $this->showCardToCardInstructions($userId, $chatId);
            }
            
        } catch (Exception $e) {
            error_log("Card-to-card deposit error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Show card-to-card instructions
     */
    private function showCardToCardInstructions($userId, $chatId) {
        $bankCards = $this->getActiveBankCards();
        
        $message = "💳 <b>کارت به کارت</b>\n\n";
        $message .= "برای شارژ حساب از طریق کارت به کارت:\n\n";
        
        if (!empty($bankCards)) {
            $message .= "🔢 شماره کارت‌های فعال:\n";
            foreach ($bankCards as $card) {
                $message .= "\n🏦 {$card['bank_name']}\n";
                $message .= "💳 {$card['card_number']}\n";
                $message .= "👤 {$card['account_holder']}\n";
            }
        }
        
        $message .= "\n⚠️ <b>نکات مهم:</b>\n";
        $message .= "• حداقل مبلغ شارژ: ۱۰,۰۰۰ ریال\n";
        $message .= "• پس از انتقال، تصویر رسید را ارسال کنید\n";
        $message .= "• پردازش تا ۳۰ دقیقه زمان می‌برد\n";
        $message .= "• کارمزد: رایگان\n\n";
        $message .= "آیا مایل به ادامه هستید؟";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ ادامه', 'callback_data' => 'card_to_card_continue']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'deposit_menu']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Request deposit amount
     */
    private function requestDepositAmount($userId, $chatId) {
        $message = "💰 <b>مبلغ شارژ</b>\n\n";
        $message .= "مبلغ مورد نظر برای شارژ حساب را وارد کنید:\n";
        $message .= "<i>حداقل: ۱۰,۰۰۰ ریال</i>\n";
        $message .= "<i>حداکثر: ۱۰۰,۰۰۰,۰۰۰ ریال</i>";
        
        $this->updateFinancialStep($userId, 'deposit_card_to_card', 'amount');
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process deposit amount
     */
    private function processDepositAmount($userId, $chatId, $amount) {
        $amount = $this->parseAmount($amount);
        
        if (!$this->validateDepositAmount($amount)) {
            return $this->sendErrorMessage($chatId, "مبلغ وارد شده نامعتبر است. لطفاً دوباره وارد کنید.");
        }
        
        $this->updateFinancialData($userId, 'deposit_card_to_card', 'amount', $amount);
        
        // Request card information
        $this->requestCardInfo($userId, $chatId);
        $this->updateFinancialStep($userId, 'deposit_card_to_card', 'card_info');
        
        return true;
    }
    
    /**
     * Request card information
     */
    private function requestCardInfo($userId, $chatId) {
        $message = "💳 <b>اطلاعات کارت</b>\n\n";
        $message .= "لطفاً اطلاعات کارت خود را وارد کنید:\n";
        $message .= "<i>فرمت: شماره کارت - نام بانک - نام صاحب کارت</i>\n";
        $message .= "<i>مثال: 6037991234567890 - بانک ملی - علی احمدی</i>";
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process card information
     */
    private function processCardInfo($userId, $chatId, $cardInfo) {
        $parsedCard = $this->parseCardInfo($cardInfo);
        
        if (!$parsedCard) {
            return $this->sendErrorMessage($chatId, "اطلاعات کارت نامعتبر است. لطفاً دوباره وارد کنید.");
        }
        
        $this->updateFinancialData($userId, 'deposit_card_to_card', 'card_info', $parsedCard);
        
        // Request receipt
        $this->requestReceipt($userId, $chatId);
        $this->updateFinancialStep($userId, 'deposit_card_to_card', 'receipt');
        
        return true;
    }
    
    /**
     * Request receipt upload
     */
    private function requestReceipt($userId, $chatId) {
        $message = "📸 <b>تصویر رسید</b>\n\n";
        $message .= "لطفاً تصویر رسید کارت به کارت را ارسال کنید:\n";
        $message .= "<i>تصویر باید واضح و خوانا باشد</i>\n";
        $message .= "<i>اطلاعات انتقال در تصویر قابل مشاهده باشد</i>";
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process receipt upload
     */
    private function processReceipt($userId, $chatId, $photoData) {
        if (empty($photoData)) {
            return $this->sendErrorMessage($chatId, "لطفاً تصویر رسید را ارسال کنید.");
        }
        
        // Get the highest quality photo
        $photo = end($photoData);
        $fileId = $photo['file_id'];
        
        $this->updateFinancialData($userId, 'deposit_card_to_card', 'receipt_file_id', $fileId);
        
        // Show confirmation
        $this->showDepositConfirmation($userId, $chatId);
        $this->updateFinancialStep($userId, 'deposit_card_to_card', 'confirm');
        
        return true;
    }
    
    /**
     * Show deposit confirmation
     */
    private function showDepositConfirmation($userId, $chatId) {
        $data = $this->getFinancialData($userId, 'deposit_card_to_card');
        $amount = number_format($data['amount']);
        $cardInfo = $data['card_info'];
        
        $message = "🔍 <b>بررسی نهایی</b>\n\n";
        $message .= "💰 مبلغ شارژ: <code>{$amount}</code> ریال\n";
        $message .= "💳 شماره کارت: <code>{$cardInfo['card_number']}</code>\n";
        $message .= "🏦 بانک: {$cardInfo['bank_name']}\n";
        $message .= "👤 صاحب کارت: {$cardInfo['holder_name']}\n\n";
        $message .= "آیا اطلاعات بالا صحیح است؟";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید و ارسال درخواست', 'callback_data' => 'confirm_deposit']
                ],
                [
                    ['text' => '✏️ ویرایش', 'callback_data' => 'edit_deposit'],
                    ['text' => '❌ انصراف', 'callback_data' => 'cancel_deposit']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Confirm and create card-to-card deposit transaction
     */
    private function confirmCardToCardDeposit($userId, $chatId, $confirm) {
        if (!$confirm) {
            $this->cleanupFinancialSession($userId, 'deposit_card_to_card');
            return $this->showDepositOptions($userId, $chatId);
        }
        
        try {
            $data = $this->getFinancialData($userId, 'deposit_card_to_card');
            
            // Create transaction
            $transaction = $this->createTransaction([
                'user_id' => $userId,
                'transaction_id' => $this->generateTransactionId(),
                'type' => 'deposit',
                'amount' => $data['amount'],
                'payment_method' => 'card_to_card',
                'source_card_number' => $data['card_info']['card_number'],
                'destination_card_number' => $this->getDestinationCard(),
                'card_holder_name' => $data['card_info']['holder_name'],
                'bank_name' => $data['card_info']['bank_name'],
                'status' => 'pending',
                'balance_before' => $this->getUserBalance($userId),
                'balance_after' => $this->getUserBalance($userId), // Will be updated after approval
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Store receipt file
            $this->storeTransactionReceipt($transaction['id'], $data['receipt_file_id']);
            
            // Notify admin for approval
            $this->notifyAdminDepositRequest($transaction, $data);
            
            // Send confirmation to user
            $this->sendDepositPendingMessage($userId, $chatId, $transaction);
            
            // Clean up session
            $this->cleanupFinancialSession($userId, 'deposit_card_to_card');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Deposit confirmation error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "متأسفانه در ایجاد تراکنش مشکلی پیش آمده. لطفاً دوباره تلاش کنید.");
        }
    }
    
    /**
     * Handle balance transfer between users
     */
    public function handleTransfer($userId, $chatId, $step = null, $data = null) {
        try {
            $session = $this->getFinancialSession($userId, 'transfer');
            
            if (!$session) {
                $this->createFinancialSession($userId, 'transfer');
                return $this->showTransferInstructions($userId, $chatId);
            }
            
            $currentStep = $session['current_step'] ?? 'instructions';
            
            switch ($currentStep) {
                case 'instructions':
                    return $this->requestTransferRecipient($userId, $chatId);
                    
                case 'recipient':
                    return $this->processTransferRecipient($userId, $chatId, $data);
                    
                case 'amount':
                    return $this->processTransferAmount($userId, $chatId, $data);
                    
                case 'confirm':
                    return $this->confirmTransfer($userId, $chatId, $data);
                    
                default:
                    return $this->showTransferInstructions($userId, $chatId);
            }
            
        } catch (Exception $e) {
            error_log("Transfer error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Show transfer instructions
     */
    private function showTransferInstructions($userId, $chatId) {
        $message = "🔄 <b>انتقال وجه</b>\n\n";
        $message .= "با استفاده از این قسمت می‌توانید وجه را به دیگر کاربران منتقل کنید.\n\n";
        $message .= "⚠️ <b>نکات مهم:</b>\n";
        $message .= "• حداقل مبلغ انتقال: ۱۰,۰۰۰ ریال\n";
        $message .= "• کارمزد انتقال: رایگان\n";
        $message .= "• انتقال فقط به کاربران تأیید‌شده امکان‌پذیر است\n\n";
        $message .= "برای ادامه، شناسه کاربری یا نام کاربری مقصد را وارد کنید:";
        
        $this->updateFinancialStep($userId, 'transfer', 'instructions');
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Request transfer recipient
     */
    private function requestTransferRecipient($userId, $chatId) {
        $message = "👤 <b>مقصد انتقال</b>\n\n";
        $message .= "شناسه کاربری یا نام کاربری فرد مقصد را وارد کنید:\n";
        $message .= "<i>مثال: 123456789 یا @username</i>";
        
        $this->updateFinancialStep($userId, 'transfer', 'recipient');
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process transfer recipient
     */
    private function processTransferRecipient($userId, $chatId, $recipient) {
        $recipientUser = $this->findUserByIdentifier($recipient);
        
        if (!$recipientUser) {
            return $this->sendErrorMessage($chatId, "کاربر مورد نظر یافت نشد. لطفاً شناسه صحیح وارد کنید.");
        }
        
        if ($recipientUser['user_id'] == $userId) {
            return $this->sendErrorMessage($chatId, "امکان انتقال به خودتان وجود ندارد.");
        }
        
        if ($recipientUser['status'] != 'active') {
            return $this->sendErrorMessage($chatId, "کاربر مقصد غیرفعال است.");
        }
        
        $this->updateFinancialData($userId, 'transfer', 'recipient', $recipientUser);
        
        // Request transfer amount
        $this->requestTransferAmount($userId, $chatId);
        $this->updateFinancialStep($userId, 'transfer', 'amount');
        
        return true;
    }
    
    /**
     * Request transfer amount
     */
    private function requestTransferAmount($userId, $chatId) {
        $userBalance = $this->getUserBalance($userId);
        $formattedBalance = number_format($userBalance);
        
        $message = "💰 <b>مبلغ انتقال</b>\n\n";
        $message .= "موجودی فعلی شما: <code>{$formattedBalance}</code> ریال\n\n";
        $message .= "مبلغ مورد نظر برای انتقال را وارد کنید:\n";
        $message .= "<i>حداقل: ۱۰,۰۰۰ ریال</i>";
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process transfer amount
     */
    private function processTransferAmount($userId, $chatId, $amount) {
        $amount = $this->parseAmount($amount);
        $userBalance = $this->getUserBalance($userId);
        
        if (!$this->validateTransferAmount($amount)) {
            return $this->sendErrorMessage($chatId, "مبلغ وارد شده نامعتبر است.");
        }
        
        if ($amount > $userBalance) {
            return $this->sendErrorMessage($chatId, "موجودی کافی نیست. موجودی شما: " . number_format($userBalance) . " ریال");
        }
        
        $this->updateFinancialData($userId, 'transfer', 'amount', $amount);
        
        // Show transfer confirmation
        $this->showTransferConfirmation($userId, $chatId);
        $this->updateFinancialStep($userId, 'transfer', 'confirm');
        
        return true;
    }
    
    /**
     * Show transfer confirmation
     */
    private function showTransferConfirmation($userId, $chatId) {
        $data = $this->getFinancialData($userId, 'transfer');
        $recipient = $data['recipient'];
        $amount = number_format($data['amount']);
        
        $recipientName = $recipient['first_name'] . ' ' . $recipient['last_name'];
        $recipientInfo = $recipient['username'] ? "@{$recipient['username']}" : "{$recipient['user_id']}";
        
        $message = "🔍 <b>تأیید انتقال</b>\n\n";
        $message .= "👤 مقصد: {$recipientName} ({$recipientInfo})\n";
        $message .= "💰 مبلغ: <code>{$amount}</code> ریال\n\n";
        $message .= "آیا از انجام این انتقال اطمینان دارید؟";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید و ارسال', 'callback_data' => 'confirm_transfer']
                ],
                [
                    ['text' => '✏️ ویرایش', 'callback_data' => 'edit_transfer'],
                    ['text' => '❌ انصراف', 'callback_data' => 'cancel_transfer']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Confirm and process transfer
     */
    private function confirmTransfer($userId, $chatId, $confirm) {
        if (!$confirm) {
            $this->cleanupFinancialSession($userId, 'transfer');
            return $this->showFinancialMenu($userId, $chatId);
        }
        
        try {
            $data = $this->getFinancialData($userId, 'transfer');
            $recipient = $data['recipient'];
            $amount = $data['amount'];
            
            // Check balance again
            if ($this->getUserBalance($userId) < $amount) {
                return $this->sendErrorMessage($chatId, "موجودی کافی نیست.");
            }
            
            // Create transactions
            $transactionId = $this->generateTransactionId();
            
            // Debit from sender
            $this->createTransaction([
                'user_id' => $userId,
                'transaction_id' => $transactionId . '_SEND',
                'type' => 'transfer',
                'amount' => -$amount,
                'payment_method' => 'internal_transfer',
                'status' => 'completed',
                'balance_before' => $this->getUserBalance($userId),
                'balance_after' => $this->getUserBalance($userId) - $amount,
                'related_transaction_id' => $transactionId . '_RECV',
                'created_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Credit to recipient
            $this->createTransaction([
                'user_id' => $recipient['user_id'],
                'transaction_id' => $transactionId . '_RECV',
                'type' => 'transfer',
                'amount' => $amount,
                'payment_method' => 'internal_transfer',
                'status' => 'completed',
                'balance_before' => $this->getUserBalance($recipient['user_id']),
                'balance_after' => $this->getUserBalance($recipient['user_id']) + $amount,
                'related_transaction_id' => $transactionId . '_SEND',
                'created_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update balances
            $this->updateUserBalance($userId, -$amount);
            $this->updateUserBalance($recipient['user_id'], $amount);
            
            // Send notifications
            $this->notificationSystem->sendTransferNotification($userId, $recipient['user_id'], $amount);
            
            // Send success message
            $this->sendTransferSuccessMessage($userId, $chatId, $recipient, $amount);
            
            // Clean up session
            $this->cleanupFinancialSession($userId, 'transfer');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Transfer confirmation error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "متأسفانه در انجام انتقال مشکلی پیش آمده.");
        }
    }
    
    /**
     * Show transaction history
     */
    private function showTransactionHistory($userId, $chatId, $page = 1) {
        $transactions = $this->getUserTransactions($userId, $page);
        
        if (empty($transactions)) {
            $message = "📊 <b>تراکنش‌ها</b>\n\n";
            $message .= "هیچ تراکنشی یافت نشد.";
        } else {
            $message = "📊 <b>تراکنش‌های اخیر</b>\n\n";
            
            foreach ($transactions as $transaction) {
                $amount = number_format(abs($transaction['amount']));
                $date = jdate('Y/m/d H:i', strtotime($transaction['created_at']));
                $status = $this->getTransactionStatusText($transaction['status']);
                $type = $this->getTransactionTypeText($transaction['type']);
                
                $icon = $transaction['amount'] > 0 ? '➕' : '➖';
                $message .= "{$icon} {$type}: <code>{$amount}</code> ریال\n";
                $message .= "📅 {$date} - {$status}\n";
                $message .= "🆔 {$transaction['transaction_id']}\n\n";
            }
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 بروزرسانی', 'callback_data' => 'refresh_transactions'],
                    ['text' => '📈 گزارش کامل', 'callback_data' => 'full_report']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'finance_menu']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Admin functions for approving deposits
     */
    public function approveDeposit($transactionId, $adminId) {
        try {
            $transaction = $this->getTransactionById($transactionId);
            
            if (!$transaction || $transaction['type'] !== 'deposit' || $transaction['status'] !== 'pending') {
                return false;
            }
            
            // Update transaction status
            $this->updateTransactionStatus($transactionId, 'completed', $adminId);
            
            // Update user balance
            $this->updateUserBalance($transaction['user_id'], $transaction['amount']);
            
            // Update balance after in transaction
            $this->updateTransactionBalance($transactionId, $this->getUserBalance($transaction['user_id']));
            
            // Send notification to user
            $this->notificationSystem->sendDepositApprovedNotification($transaction['user_id'], $transaction['amount']);
            
            // Log admin action
            $this->logAdminAction($adminId, 'approve_deposit', 'transactions', $transactionId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Approve deposit error: " . $e->getMessage());
            return false;
        }
    }
    
    public function rejectDeposit($transactionId, $adminId, $reason = null) {
        try {
            $transaction = $this->getTransactionById($transactionId);
            
            if (!$transaction || $transaction['type'] !== 'deposit' || $transaction['status'] !== 'pending') {
                return false;
            }
            
            // Update transaction status
            $this->updateTransactionStatus($transactionId, 'failed', $adminId, $reason);
            
            // Send notification to user
            $this->notificationSystem->sendDepositRejectedNotification($transaction['user_id'], $transaction['amount'], $reason);
            
            // Log admin action
            $this->logAdminAction($adminId, 'reject_deposit', 'transactions', $transactionId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Reject deposit error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper methods
     */
    private function isUserRegistered($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user WHERE id = ? AND LOWER(User_Status) = 'active'");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getUserBalance($userId) {
        $stmt = $this->pdo->prepare("SELECT Balance FROM user WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function updateUserBalance($userId, $amount) {
        $stmt = $this->pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }
    
    private function createTransaction($data) {
        $sql = "INSERT INTO Payment_report (id_user, id_order, time, price, dec_not_confirmed, Payment_Method, payment_Status, bottype, id_invoice) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['user_id'],
            $data['transaction_id'],
            date('Y-m-d H:i:s'),
            $data['amount'],
            $data['admin_notes'] ?? null,
            $data['payment_method'],
            $data['status'],
            $data['bottype'] ?? null,
            $data['id_invoice'] ?? $data['transaction_id'],
        ]);
        
        return ['id' => $this->pdo->lastInsertId(), 'transaction_id' => $data['transaction_id']];
    }
    
    private function generateTransactionId() {
        return 'TRX' . date('YmdHis') . rand(1000, 9999);
    }
    
    private function validateDepositAmount($amount) {
        $minAmount = $this->getSystemSetting('min_deposit_amount', 10000);
        $maxAmount = $this->getSystemSetting('max_deposit_amount', 100000000);
        
        return $amount >= $minAmount && $amount <= $maxAmount;
    }
    
    private function validateTransferAmount($amount) {
        $minAmount = 10000; // 10,000 Rials minimum
        return $amount >= $minAmount;
    }
    
    private function parseAmount($amount) {
        $amount = preg_replace('/[^0-9]/', '', $amount);
        return intval($amount);
    }
    
    private function parseCardInfo($cardInfo) {
        $parts = explode('-', $cardInfo);
        if (count($parts) < 3) return false;
        
        $cardNumber = trim(preg_replace('/[^0-9]/', '', $parts[0]));
        $bankName = trim($parts[1]);
        $holderName = trim($parts[2]);
        
        if (strlen($cardNumber) < 10) return false;
        
        return [
            'card_number' => $cardNumber,
            'bank_name' => $bankName,
            'holder_name' => $holderName
        ];
    }
    
    private function getActiveBankCards() {
        $cards = select("card_number","*",null,null,"fetchAll");
        $result = [];
        foreach ($cards as $c) {
            $result[] = [
                'bank_name' => '',
                'card_number' => $c['cardnumber'] ?? '',
                'account_holder' => $c['namecard'] ?? '',
            ];
        }
        return $result;
    }
    
    private function getDestinationCard() {
        $cards = $this->getActiveBankCards();
        return $cards[0]['card_number'] ?? '';
    }
    
    private function storeTransactionReceipt($transactionId, $fileId) {
        $stmt = $this->pdo->prepare("UPDATE Payment_report SET dec_not_confirmed = ? WHERE id = ?");
        return $stmt->execute([$fileId, $transactionId]);
    }
    
    private function notifyAdminDepositRequest($transaction, $data) {
        global $adminnumber;
        
        $amount = number_format($transaction['amount']);
        $user = $this->getUserById($transaction['user_id']);
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        
        $message = "🆕 <b>درخواست شارژ جدید</b>\n\n";
        $message .= "👤 کاربر: {$userName}\n";
        $message .= "🆔 شناسه: {$user['user_id']}\n";
        $message .= "💰 مبلغ: <code>{$amount}</code> ریال\n";
        $message .= "💳 شماره کارت: {$data['card_info']['card_number']}\n";
        $message .= "🏦 بانک: {$data['card_info']['bank_name']}\n";
        $message .= "📅 تاریخ: " . jdate('Y/m/d H:i:s') . "\n\n";
        $message .= "برای بررسی و تأیید کلیک کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأیید', 'callback_data' => "approve_deposit:{$transaction['id']}"],
                    ['text' => '❌ رد', 'callback_data' => "reject_deposit:{$transaction['id']}"]
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $adminnumber,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    private function sendDepositPendingMessage($userId, $chatId, $transaction) {
        $amount = number_format($transaction['amount']);
        
        $message = "⏳ <b>درخواست شارژ در انتظار تأیید</b>\n\n";
        $message .= "💰 مبلغ: <code>{$amount}</code> ریال\n";
        $message .= "🆔 شماره تراکنش: {$transaction['transaction_id']}\n";
        $message .= "📅 تاریخ: " . jdate('Y/m/d H:i:s') . "\n\n";
        $message .= "درخواست شما در صف بررسی قرار گرفت.\n";
        $message .= "پس از تأیید، مبلغ به حساب شما افزوده خواهد شد.";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 بررسی وضعیت', 'callback_data' => 'check_deposit_status'],
                    ['text' => '📊 تراکنش‌ها', 'callback_data' => 'transactions']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    private function sendTransferSuccessMessage($userId, $chatId, $recipient, $amount) {
        $formattedAmount = number_format($amount);
        $recipientName = ($recipient['namecustom'] ?? '') !== '' ? $recipient['namecustom'] : ($recipient['username'] ?? $recipient['id'] ?? '');
        
        $message = "✅ <b>انتقال موفق</b>\n\n";
        $message .= "💰 مبلغ <code>{$formattedAmount}</code> ریال\n";
        $message .= "به {$recipientName} منتقل شد.\n\n";
        $message .= "موجودی فعلی شما: <code>" . number_format($this->getUserBalance($userId)) . "</code> ریال";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 تراکنش‌ها', 'callback_data' => 'transactions'],
                    ['text' => '🔙 بازگشت', 'callback_data' => 'finance_menu']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    private function findUserByIdentifier($identifier) {
        if (strpos($identifier, '@') === 0) {
            // Search by username
            $username = substr($identifier, 1);
            $stmt = $this->pdo->prepare("SELECT * FROM user WHERE username = ? AND LOWER(User_Status) = 'active'");
            $stmt->execute([$username]);
        } else {
            // Search by user_id
            $stmt = $this->pdo->prepare("SELECT * FROM user WHERE id = ? AND LOWER(User_Status) = 'active'");
            $stmt->execute([$identifier]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getUserTransactions($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("SELECT * FROM Payment_report WHERE id_user = ? ORDER BY time DESC LIMIT ? OFFSET ?");
        $stmt->execute([$userId, $limit, $offset]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTransactionById($transactionId) {
        $stmt = $this->pdo->prepare("SELECT * FROM Payment_report WHERE id = ?");
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateTransactionStatus($transactionId, $status, $adminId = null, $reason = null) {
        $sql = "UPDATE Payment_report SET payment_Status = ?, at_updated = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, date('Y-m-d H:i:s'), $transactionId]);
    }
    
    private function updateTransactionBalance($transactionId, $balance) {
        $stmt = $this->pdo->prepare("UPDATE transactions SET balance_after = ? WHERE id = ?");
        return $stmt->execute([$balance, $transactionId]);
    }
    
    private function getTransactionStatusText($status) {
        $statuses = [
            'pending' => '⏳ در انتظار',
            'completed' => '✅ تکمیل شده',
            'failed' => '❌ ناموفق',
            'cancelled' => '❌ لغو شده',
            'refunded' => '🔄 بازگشت وجه',
            'disputed' => '⚠️ مورد اختلاف'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function getTransactionTypeText($type) {
        $types = [
            'deposit' => 'شارژ حساب',
            'withdrawal' => 'برداشت وجه',
            'purchase' => 'خرید',
            'refund' => 'بازگشت وجه',
            'transfer' => 'انتقال وجه',
            'commission' => 'کارمزد',
            'bonus' => 'پاداش'
        ];
        
        return $types[$type] ?? $type;
    }
    
    private function getSystemSetting($key, $default = null) {
        $setting = select("setting","*",null,null,"select");
        if (!is_array($setting)) {
            return $default;
        }
        if (array_key_exists($key, $setting)) {
            return $setting[$key];
        }
        return $default;
    }
    
    private function logAdminAction($adminId, $action, $resourceType, $resourceId) {
        return true;
    }
    
    /**
     * Financial session management
     */
    private function createFinancialSession($userId, $type) {
        $stmt = $this->pdo->prepare("INSERT INTO financial_sessions (user_id, session_type, current_step, data, created_at) VALUES (?, ?, 'start', ?, NOW())");
        return $stmt->execute([$userId, $type, json_encode([])]);
    }
    
    private function getFinancialSession($userId, $type) {
        $stmt = $this->pdo->prepare("SELECT * FROM financial_sessions WHERE user_id = ? AND session_type = ? AND completed = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId, $type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateFinancialStep($userId, $type, $step) {
        $stmt = $this->pdo->prepare("UPDATE financial_sessions SET current_step = ? WHERE user_id = ? AND session_type = ? AND completed = 0");
        return $stmt->execute([$step, $userId, $type]);
    }
    
    private function updateFinancialData($userId, $type, $key, $value) {
        $session = $this->getFinancialSession($userId, $type);
        if (!$session) return false;
        
        $data = json_decode($session['data'], true);
        $data[$key] = $value;
        
        $stmt = $this->pdo->prepare("UPDATE financial_sessions SET data = ? WHERE user_id = ? AND session_type = ? AND completed = 0");
        return $stmt->execute([json_encode($data), $userId, $type]);
    }
    
    private function getFinancialData($userId, $type) {
        $session = $this->getFinancialSession($userId, $type);
        return $session ? json_decode($session['data'], true) : [];
    }
    
    private function cleanupFinancialSession($userId, $type) {
        $stmt = $this->pdo->prepare("UPDATE financial_sessions SET completed = 1 WHERE user_id = ? AND session_type = ?");
        return $stmt->execute([$userId, $type]);
    }
    
    private function sendRegistrationRequired($chatId) {
        return $this->sendErrorMessage($chatId, "ابتدا باید ثبت‌نام کنید. لطفاً از دستور /start استفاده کنید.");
    }
}

/**
 * Notification System Class
 */
class NotificationSystem {
    
    private $pdo;
    private $telegram;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
    }
    
    public function sendTransferNotification($senderId, $recipientId, $amount) {
        // Notify sender
        $this->createNotification($senderId, 'تراکنش انتقال', "مبلغ " . number_format($amount) . " ریال به کاربر دیگر منتقل شد.", 'transaction');
        
        // Notify recipient
        $this->createNotification($recipientId, 'دریافت وجه', "مبلغ " . number_format($amount) . " ریال از کاربر دیگر دریافت شد.", 'transaction');
        
        return true;
    }
    
    public function sendDepositApprovedNotification($userId, $amount) {
        return $this->createNotification($userId, 'شارژ حساب', "شارژ حساب شما به مبلغ " . number_format($amount) . " ریال تأیید و انجام شد.", 'transaction');
    }
    
    public function sendDepositRejectedNotification($userId, $amount, $reason = null) {
        $message = "درخواست شارژ حساب شما به مبلغ " . number_format($amount) . " ریال رد شد.";
        if ($reason) {
            $message .= "\nدلیل: " . $reason;
        }
        
        return $this->createNotification($userId, 'رد شارژ', $message, 'error');
    }
    
    private function createNotification($userId, $title, $message, $type = 'info') {
        $stmt = $this->pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $type, date('Y-m-d H:i:s')]);
    }
}

/**
 * Telegram API Wrapper Class
 */
class TelegramAPI {
    
    public function sendMessage($params) {
        return telegram('sendMessage', $params);
    }
    
    public function answerCallbackQuery($params) {
        return telegram('answerCallbackQuery', $params);
    }
}

/**
 * Create financial_sessions table
 */
function createFinancialSessionsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS financial_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        session_type VARCHAR(50) NOT NULL,
        current_step VARCHAR(50),
        data TEXT,
        completed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_type (user_id, session_type),
        INDEX idx_completed (completed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    return $pdo->exec($sql);
}

// Initialize the financial sessions table
try {
    createFinancialSessionsTable($pdo);
} catch (Exception $e) {
    error_log("Failed to create financial sessions table: " . $e->getMessage());
}

?>
