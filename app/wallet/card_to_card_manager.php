<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../../botapi.php';

class CardToCardManager {
    private $walletDatabase;
    private $adminGroupId;
    private $adminTopicId;
    
    public function __construct() {
        $this->walletDatabase = new WalletDatabase();
        
        // Get admin notification settings from existing system
        global $connect;
        $adminReport = mysqli_fetch_assoc(mysqli_query($connect, "SELECT idreport FROM topicid WHERE report = 'paymentreport'"));
        $this->adminGroupId = $adminReport['idreport'] ?? '';
        
        $topicReport = mysqli_fetch_assoc(mysqli_query($connect, "SELECT idreport FROM topicid WHERE report = 'otherservice'"));
        $this->adminTopicId = $topicReport['idreport'] ?? '';
    }
    
    /**
     * Process a new card-to-card transaction request
     */
    public function processTransaction($userId, $transactionData) {
        try {
            // Validate input data
            $validation = $this->validateTransactionData($transactionData);
            if (!$validation['success']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Generate unique transaction ID
            $transactionId = $this->generateTransactionId($userId);
            
            // Convert amount to Rial (if provided in Toman)
            $amount = $this->parseAmount($transactionData['amount']);
            $amountToman = $amount / 10; // Convert to Toman for display
            
            // Prepare transaction data
            $transaction = [
                'user_id' => $userId,
                'transaction_id' => $transactionId,
                'source_card_number' => $this->maskCardNumber($transactionData['source_card_number']),
                'destination_card_number' => $transactionData['destination_card_number'],
                'amount' => $amount,
                'amount_toman' => $amountToman,
                'bank_name' => $transactionData['bank_name'] ?? null,
                'transaction_date' => $transactionData['transaction_date'] ?? date('Y-m-d H:i:s'),
                'transaction_status' => 'pending'
            ];
            
            // Insert transaction into database
            $transactionDbId = $this->walletDatabase->insertCardToCardTransaction($transaction);
            if (!$transactionDbId) {
                throw new Exception("Failed to save transaction to database");
            }
            
            // Add bank card information
            $this->walletDatabase->addBankCard([
                'user_id' => $userId,
                'card_number' => $transactionData['source_card_number'],
                'bank_name' => $transactionData['bank_name'] ?? null
            ]);
            
            // Send notification to admin for manual verification
            $this->notifyAdminNewTransaction($transaction);
            
            // Send confirmation message to user
            $this->notifyUserTransactionCreated($userId, $transaction);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'تراکنش کارت به کارت با موفقیت ثبت شد. پس از بررسی و تایید توسط ادمین، مبلغ به کیف پول شما افزوده خواهد شد.'
            ];
            
        } catch (Throwable $e) {
            error_log("Card-to-card transaction processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در پردازش تراکنش: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate transaction data
     */
    private function validateTransactionData($data) {
        // Check required fields
        if (empty($data['source_card_number']) || empty($data['amount'])) {
            return [
                'success' => false,
                'message' => 'شماره کارت و مبلغ تراکنش اجباری هستند'
            ];
        }
        
        // Validate card number (Iranian bank cards are 16 digits)
        $cardNumber = preg_replace('/[^0-9]/', '', $data['source_card_number']);
        if (strlen($cardNumber) !== 16) {
            return [
                'success' => false,
                'message' => 'شماره کارت باید ۱۶ رقم باشد'
            ];
        }
        
        // Validate card number using Luhn algorithm
        if (!$this->isValidCardNumber($cardNumber)) {
            return [
                'success' => false,
                'message' => 'شماره کارت نامعتبر است'
            ];
        }
        
        // Validate amount (Input is in Toman)
        $amountToman = $this->parseAmount($data['amount']);
        if ($amountToman <= 0) {
            return [
                'success' => false,
                'message' => 'مبلغ تراکنش باید بیشتر از صفر باشد'
            ];
        }
        
        // Check minimum amount (10,000 Toman)
        if ($amountToman < 10000) { 
            return [
                'success' => false,
                'message' => 'حداقل مبلغ تراکنش ۱۰٬۰۰۰ تومان است'
            ];
        }
        
        // Check maximum amount (50,000,000 Toman)
        if ($amountToman > 50000000) { 
            return [
                'success' => false,
                'message' => 'حداکثر مبلغ تراکنش ۵۰٬۰۰۰٬۰۰۰ تومان است'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Parse and normalize amount
     */
    private function parseAmount($amount) {
        // Remove commas and convert to integer
        $amount = str_replace([',', '،', ' '], '', $amount);
        return intval($amount);
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
     * Generate unique transaction ID
     */
    private function generateTransactionId($userId) {
        $timestamp = time();
        $random = rand(1000, 9999);
        return 'C2C' . $userId . $timestamp . $random;
    }
    
    /**
     * Mask card number for security
     */
    private function maskCardNumber($cardNumber) {
        $cardNumber = preg_replace('/[^0-9]/', '', $cardNumber);
        return substr($cardNumber, 0, 6) . '****' . substr($cardNumber, -4);
    }
    
    /**
     * Confirm a card-to-card transaction
     */
    public function confirmTransaction($transactionId, $adminId, $data = []) {
        try {
            // Get transaction details
            $transaction = $this->walletDatabase->getCardToCardTransaction($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'تراکنش یافت نشد'
                ];
            }
            
            if ($transaction['transaction_status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این تراکنش قبلاً بررسی شده است'
                ];
            }
            
            // Update transaction status
            $updateData = [
                'tracking_code' => $data['tracking_code'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'admin_notes' => $data['admin_notes'] ?? null
            ];
            
            $result = $this->walletDatabase->updateCardToCardTransactionStatus($transactionId, 'confirmed', $updateData);
            if (!$result) {
                throw new Exception("Failed to update transaction status");
            }
            
            // Get current user balance
            $currentBalance = $this->walletDatabase->getUserBalance($transaction['user_id']);
            if ($currentBalance === false) {
                throw new Exception("Failed to get user balance");
            }
            
            // Calculate new balance (amount is in Rial, convert to Toman for wallet)
            $newBalance = $currentBalance + ($transaction['amount'] / 10);
            
            // Update user balance
            $balanceResult = $this->walletDatabase->updateUserBalance($transaction['user_id'], $newBalance);
            if (!$balanceResult) {
                throw new Exception("Failed to update user balance");
            }
            
            // Record wallet transaction
            $walletTransaction = [
                'user_id' => $transaction['user_id'],
                'transaction_type' => 'deposit',
                'amount' => $transaction['amount'] / 10, // Convert to Toman
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
                'related_transaction_id' => $transactionId,
                'description' => 'افزایش موجودی از طریق کارت به کارت',
                'reference_type' => 'card_to_card',
                'reference_id' => $transactionId
            ];
            
            $this->walletDatabase->insertWalletTransaction($walletTransaction);
            
            // Notify user about confirmation
            $this->notifyUserTransactionConfirmed($transaction['user_id'], $transaction);
            
            return [
                'success' => true,
                'message' => 'تراکنش با موفقیت تایید شد و مبلغ به کیف پول کاربر افزوده شد'
            ];
            
        } catch (Exception $e) {
            error_log("Transaction confirmation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تایید تراکنش'
            ];
        }
    }
    
    /**
     * Reject a card-to-card transaction
     */
    public function rejectTransaction($transactionId, $adminId, $reason) {
        try {
            // Get transaction details
            $transaction = $this->walletDatabase->getCardToCardTransaction($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'تراکنش یافت نشد'
                ];
            }
            
            if ($transaction['transaction_status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'این تراکنش قبلاً بررسی شده است'
                ];
            }
            
            // Update transaction status
            $result = $this->walletDatabase->updateCardToCardTransactionStatus($transactionId, 'rejected', [
                'admin_notes' => $reason
            ]);
            
            if (!$result) {
                throw new Exception("Failed to update transaction status");
            }
            
            // Notify user about rejection
            $this->notifyUserTransactionRejected($transaction['user_id'], $transaction, $reason);
            
            return [
                'success' => true,
                'message' => 'تراکنش رد شد و دلیل به کاربر اطلاع داده شد'
            ];
            
        } catch (Exception $e) {
            error_log("Transaction rejection error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در رد تراکنش'
            ];
        }
    }
    
    /**
     * Notify admin about new transaction
     */
    private function notifyAdminNewTransaction($transaction) {
        if (empty($this->adminGroupId)) {
            return;
        }
        
        $text = "🔄 <b>تراکنش کارت به کارت جدید</b>\n\n";
        $text .= "👤 کاربر: {$transaction['user_id']}\n";
        $text .= "💳 کارت مبدا: {$transaction['source_card_number']}\n";
        $text .= "🏦 بانک: " . ($transaction['bank_name'] ?? 'نامشخص') . "\n";
        $text .= "💰 مبلغ: " . number_format($transaction['amount_toman']) . " تومان\n";
        $text .= "📅 تاریخ: " . jdate('Y/m/d H:i', strtotime($transaction['transaction_date'])) . "\n";
        $text .= "🆔 شناسه تراکنش: <code>{$transaction['transaction_id']}</code>\n\n";
        $text .= "✅ برای تایید: /confirm_{$transaction['transaction_id']}\n";
        $text .= "❌ برای رد: /reject_{$transaction['transaction_id']}";
        
        sendmessage($this->adminGroupId, $text, null, 'HTML');
    }
    
    /**
     * Notify user about transaction creation
     */
    private function notifyUserTransactionCreated($userId, $transaction) {
        $text = "✅ <b>تراکنش شما ثبت شد</b>\n\n";
        $text .= "💳 کارت: {$transaction['source_card_number']}\n";
        $text .= "💰 مبلغ: " . number_format($transaction['amount_toman']) . " تومان\n";
        $text .= "🆔 شناسه: <code>{$transaction['transaction_id']}</code>\n\n";
        $text .= "⏳ تراکنش شما در انتظار بررسی و تایید توسط ادمین است.\n";
        $text .= "پس از تایید، مبلغ به کیف پول شما افزوده خواهد شد.";
        
        sendmessage($userId, $text, null, 'HTML');
    }
    
    /**
     * Notify user about transaction confirmation
     */
    private function notifyUserTransactionConfirmed($userId, $transaction) {
        $text = "✅ <b>تراکنش شما تایید شد</b>\n\n";
        $text .= "💳 کارت: {$transaction['source_card_number']}\n";
        $text .= "💰 مبلغ: " . number_format($transaction['amount_toman']) . " تومان\n";
        $text .= "🆔 شناسه: <code>{$transaction['transaction_id']}</code>\n\n";
        $text .= "💎 مبلغ مورد نظر به کیف پول شما افزوده شد.";
        
        sendmessage($userId, $text, null, 'HTML');
    }
    
    /**
     * Notify user about transaction rejection
     */
    private function notifyUserTransactionRejected($userId, $transaction, $reason) {
        $text = "❌ <b>تراکنش شما رد شد</b>\n\n";
        $text .= "💳 کارت: {$transaction['source_card_number']}\n";
        $text .= "💰 مبلغ: " . number_format($transaction['amount_toman']) . " تومان\n";
        $text .= "🆔 شناسه: <code>{$transaction['transaction_id']}</code>\n";
        $text .= "💬 دلیل: {$reason}\n\n";
        $text .= "در صورت نیاز به اطلاعات بیشتر با پشتیبانی تماس بگیرید.";
        
        sendmessage($userId, $text, null, 'HTML');
    }
    
    /**
     * Get pending transactions for admin review
     */
    public function getPendingTransactions($limit = 50, $offset = 0) {
        try {
            $sql = "SELECT c2c.*, u.username, u.namecustom 
                    FROM card_to_card_transactions c2c 
                    JOIN user u ON c2c.user_id = u.id 
                    WHERE c2c.transaction_status = 'pending' 
                    ORDER BY c2c.created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->walletDatabase->pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get pending transactions error: " . $e->getMessage());
            return false;
        }
    }
}