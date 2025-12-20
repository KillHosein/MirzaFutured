<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/card_to_card_manager.php';

class WalletBotInterface {
    private $walletDatabase;
    private $cardToCardManager;
    
    public function __construct() {
        $this->walletDatabase = new WalletDatabase();
        $this->cardToCardManager = new CardToCardManager();
    }
    
    /**
     * Handle wallet-related bot commands
     */
    public function handleWalletCommand($userId, $command, $message = null) {
        switch ($command) {
            case 'wallet':
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
                return false;
        }
    }
    
    /**
     * Show wallet main menu
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
     * Start card-to-card deposit process
     */
    private function startCardToCardDeposit($userId) {
        // Get destination card information from settings
        global $connect;
        $destinationCard = mysqli_fetch_assoc(mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'destination_card_number'"));
        $destinationCardNumber = $destinationCard['ValuePay'] ?? '6037991234567890'; // Default card
        
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
     * Handle card-to-card form submission
     */
    public function handleCardToCardForm($userId, $step, $message = null) {
        global $connect;
        
        switch ($step) {
            case 'start':
                // Set user step in database
                mysqli_query($connect, "UPDATE user SET step = 'card_to_card_amount' WHERE id = '$userId'");
                
                $text = "💰 <b>مبلغ تراکنش</b>\n\n";
                $text .= "لطفاً مبلغی را که از کارت خود به کارت مقصد انتقال دادهاید، وارد کنید:\n\n";
                $text .= "💡 <b>مثال:</b> 50000 (برای ۵۰٬۰۰۰ تومان)";
                
                return [
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ];
                
            case 'amount':
                $amount = str_replace([',', '،', ' '], '', $message);
                
                if (!is_numeric($amount) || $amount <= 0) {
                    return [
                        'text' => "❌ مبلغ وارد شده نامعتبر است. لطفاً فقط عدد وارد کنید.",
                        'parse_mode' => 'HTML'
                    ];
                }
                
                if ($amount < 10000) {
                    return [
                        'text' => "❌ حداقل مبلغ تراکنش ۱۰٬۰۰۰ تومان است.",
                        'parse_mode' => 'HTML'
                    ];
                }
                
                // Store amount in user processing data
                mysqli_query($connect, "UPDATE user SET Processing_value = '$amount' WHERE id = '$userId'");
                mysqli_query($connect, "UPDATE user SET step = 'card_to_card_card_number' WHERE id = '$userId'");
                
                $text = "💳 <b>شماره کارت مبدا</b>\n\n";
                $text .= "لطفاً شماره کارت خود را که از آن انتقال وجه انجام دادهاید، وارد کنید:\n\n";
                $text .= "💡 <b>مثال:</b> 6037991234567890";
                
                return [
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ];
                
            case 'card_number':
                $cardNumber = preg_replace('/[^0-9]/', '', $message);
                
                if (strlen($cardNumber) !== 16) {
                    return [
                        'text' => "❌ شماره کارت باید ۱۶ رقم باشد.",
                        'parse_mode' => 'HTML'
                    ];
                }
                
                // Validate card number using Luhn algorithm
                if (!$this->isValidCardNumber($cardNumber)) {
                    return [
                        'text' => "❌ شماره کارت نامعتبر است.",
                        'parse_mode' => 'HTML'
                    ];
                }
                
                // Store card number in user processing data
                mysqli_query($connect, "UPDATE user SET Processing_value_one = '$cardNumber' WHERE id = '$userId'");
                mysqli_query($connect, "UPDATE user SET step = 'card_to_card_bank_name' WHERE id = '$userId'");
                
                $text = "🏦 <b>نام بانک</b>\n\n";
                $text .= "لطفاً نام بانک صادر کننده کارت خود را وارد کنید:\n\n";
                $text .= "💡 <b>مثال:</b> بانک ملی ایران";
                
                return [
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ];
                
            case 'bank_name':
                $bankName = trim($message);
                
                if (empty($bankName)) {
                    return [
                        'text' => "❌ نام بانک نمیتواند خالی باشد.",
                        'parse_mode' => 'HTML'
                    ];
                }
                
                // Store bank name in user processing data
                mysqli_query($connect, "UPDATE user SET Processing_value_tow = '$bankName' WHERE id = '$userId'");
                mysqli_query($connect, "UPDATE user SET step = 'card_to_card_tracking' WHERE id = '$userId'");
                
                $text = "📋 <b>شماره پیگیری یا رسید (اختیاری)</b>\n\n";
                $text .= "در صورت داشتن شماره پیگیری یا رسید، آن را وارد کنید.\n";
                $text .= "در غیر این صورت عدد 0 را ارسال کنید.\n\n";
                $text .= "💡 <b>مثال:</b> 1234567890 یا 0";
                
                return [
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ];
                
            case 'tracking':
                $trackingCode = trim($message);
                if ($trackingCode === '0') {
                    $trackingCode = '';
                }
                
                // Store tracking code in user processing data
                mysqli_query($connect, "UPDATE user SET Processing_value_four = '$trackingCode' WHERE id = '$userId'");
                
                // Get all stored data
                $userData = mysqli_fetch_assoc(mysqli_query($connect, "SELECT Processing_value, Processing_value_one, Processing_value_tow, Processing_value_four FROM user WHERE id = '$userId'"));
                
                // Get destination card information
                $destinationCard = mysqli_fetch_assoc(mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'destination_card_number'"));
                $destinationCardNumber = $destinationCard['ValuePay'] ?? '6037991234567890';
                
                // Show confirmation
                $text = "✅ <b>تایید اطلاعات تراکنش</b>\n\n";
                $text .= "💰 مبلغ: <code>" . number_format($userData['Processing_value']) . "</code> تومان\n";
                $text .= "💳 کارت مبدا: <code>" . substr($userData['Processing_value_one'], 0, 6) . "****" . substr($userData['Processing_value_one'], -4) . "</code>\n";
                $text .= "🏦 بانک: " . $userData['Processing_value_tow'] . "\n";
                $text .= "💳 کارت مقصد: <code>" . substr($destinationCardNumber, 0, 6) . "****" . substr($destinationCardNumber, -4) . "</code>\n";
                
                if (!empty($userData['Processing_value_four'])) {
                    $text .= "📋 شماره پیگیری: <code>" . $userData['Processing_value_four'] . "</code>\n";
                }
                
                $text .= "\nآیا اطلاعات بالا صحیح است؟";
                
                $keyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و ارسال', 'callback_data' => 'card_to_card_submit'],
                            ['text' => '❌ انصراف', 'callback_data' => 'wallet']
                        ]
                    ]
                ]);
                
                return [
                    'text' => $text,
                    'keyboard' => $keyboard,
                    'parse_mode' => 'HTML'
                ];
                
            case 'submit':
                // Get all stored data
                $userData = mysqli_fetch_assoc(mysqli_query($connect, "SELECT Processing_value, Processing_value_one, Processing_value_tow, Processing_value_four FROM user WHERE id = '$userId'"));
                $destinationCard = mysqli_fetch_assoc(mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'destination_card_number'"));
                $destinationCardNumber = $destinationCard['ValuePay'] ?? '6037991234567890';
                
                // Prepare transaction data
                $transactionData = [
                    'source_card_number' => $userData['Processing_value_one'],
                    'destination_card_number' => $destinationCardNumber,
                    'amount' => $userData['Processing_value'],
                    'bank_name' => $userData['Processing_value_tow'],
                    'tracking_code' => $userData['Processing_value_four']
                ];
                
                // Process transaction
                $result = $this->cardToCardManager->processTransaction($userId, $transactionData);
                
                // Clear user processing data
                mysqli_query($connect, "UPDATE user SET Processing_value = '', Processing_value_one = '', Processing_value_tow = '', Processing_value_four = '', step = '' WHERE id = '$userId'");
                
                if ($result['success']) {
                    $text = "✅ <b>تراکنش شما با موفقیت ثبت شد</b>\n\n";
                    $text .= "🆔 شناسه تراکنش: <code>" . $result['transaction_id'] . "</code>\n";
                    $text .= "⏳ تراکنش شما در انتظار بررسی و تایید توسط ادمین است.\n";
                    $text .= "پس از تایید، مبلغ به کیف پول شما افزوده خواهد شد.";
                } else {
                    $text = "❌ <b>خطا در ثبت تراکنش</b>\n\n";
                    $text .= $result['message'] . "\n";
                    $text .= "لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.";
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
                
            default:
                return false;
        }
    }
    
    /**
     * Validate card number using Luhn algorithm
     */
    private function isValidCardNumber($cardNumber) {
        $sum = 0;
        $alternate = false;
        
        for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
            $n = intval($cardNumber[$i]);
            
            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n = ($n % 10) + 1;
                }
            }
            
            $sum += $n;
            $alternate = !$alternate;
        }
        
        return ($sum % 10 == 0);
    }
    
    /**
     * Show wallet transactions
     */
    private function showWalletTransactions($userId) {
        $transactions = $this->walletDatabase->getUserWalletTransactions($userId, 10, 0);
        
        if (empty($transactions)) {
            $text = "📋 <b>تراکنشهای کیف پول</b>\n\n";
            $text .= "شما هنوز هیچ تراکنشی ندارید.";
        } else {
            $text = "📋 <b>تراکنشهای کیف پول شما</b>\n\n";
            
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
     * Get transaction type icon
     */
    private function getTransactionTypeIcon($type) {
        switch ($type) {
            case 'deposit':
                return '💰';
            case 'withdrawal':
                return '💸';
            case 'refund':
                return '🔄';
            case 'purchase':
                return '🛒';
            case 'commission':
                return '💎';
            default:
                return '📊';
        }
    }
    
    /**
     * Handle callback queries
     */
    public function handleCallbackQuery($userId, $callbackData) {
        switch ($callbackData) {
            case 'wallet':
                return $this->showWalletMenu($userId);
                
            case 'wallet_balance':
                return $this->showWalletBalance($userId);
                
            case 'wallet_deposit':
                return $this->showDepositOptions($userId);
                
            case 'wallet_transactions':
                return $this->showWalletTransactions($userId);
                
            case 'card_to_card_deposit':
                return $this->startCardToCardDeposit($userId);
                
            case 'card_to_card_form':
                return $this->handleCardToCardForm($userId, 'start');
                
            case 'card_to_card_submit':
                return $this->handleCardToCardForm($userId, 'submit');
                
            default:
                return false;
        }
    }
}