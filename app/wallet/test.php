<?php
/**
 * Wallet System Test Suite
 * Comprehensive testing for card-to-card wallet functionality
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/card_to_card_manager.php';
require_once __DIR__ . '/../botapi.php';

class WalletTestSuite {
    private $walletDatabase;
    private $cardToCardManager;
    private $testUserId = 'test_user_123456';
    private $testResults = [];
    
    public function __construct() {
        $this->walletDatabase = new WalletDatabase();
        $this->cardToCardManager = new CardToCardManager();
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "🧪 Starting Wallet System Test Suite...\n\n";
        
        $tests = [
            'testDatabaseInitialization',
            'testCardValidation',
            'testAmountValidation',
            'testTransactionCreation',
            'testTransactionConfirmation',
            'testTransactionRejection',
            'testBalanceUpdate',
            'testWalletTransactions',
            'testUserNotifications',
            'testAdminNotifications',
            'testErrorHandling',
            'testSecurityFeatures',
            'testPerformance',
            'testEdgeCases'
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            echo "Running: $test... ";
            try {
                $result = $this->$test();
                if ($result) {
                    echo "✅ PASSED\n";
                    $passed++;
                } else {
                    echo "❌ FAILED\n";
                    $failed++;
                }
            } catch (Exception $e) {
                echo "❌ ERROR: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Test Results:\n";
        echo "✅ Passed: $passed\n";
        echo "❌ Failed: $failed\n";
        echo "📊 Success Rate: " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";
        echo str_repeat("=", 50) . "\n";
        
        return $failed === 0;
    }
    
    /**
     * Test database initialization
     */
    private function testDatabaseInitialization() {
        try {
            // Test table creation
            $result = $this->walletDatabase->initializeTables();
            if (!$result) {
                throw new Exception("Failed to initialize database tables");
            }
            
            // Test table existence
            global $pdo;
            $tables = ['card_to_card_transactions', 'wallet_transactions', 'bank_cards'];
            
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = '$table'");
                if (!$stmt->fetch()) {
                    throw new Exception("Table $table does not exist");
                }
            }
            
            return true;
        } catch (Exception $e) {
            echo "Database initialization error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test card validation
     */
    private function testCardValidation() {
        try {
            $validCards = [
                '6037991234567890', // Valid card
                '6037991234567891', // Invalid card (Luhn check fails)
                '1234567890123456', // Invalid card
                '603799123456789',  // Too short
                '60379912345678901' // Too long
            ];
            
            // Test valid card
            $reflection = new ReflectionClass($this->cardToCardManager);
            $method = $reflection->getMethod('isValidCardNumber');
            $method->setAccessible(true);
            
            $result = $method->invoke($this->cardToCardManager, $validCards[0]);
            if (!$result) {
                throw new Exception("Valid card failed validation");
            }
            
            // Test invalid cards
            $invalidCards = [$validCards[1], $validCards[2], $validCards[3], $validCards[4]];
            foreach ($invalidCards as $card) {
                $result = $method->invoke($this->cardToCardManager, $card);
                if ($result) {
                    throw new Exception("Invalid card passed validation: $card");
                }
            }
            
            return true;
        } catch (Exception $e) {
            echo "Card validation error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test amount validation
     */
    private function testAmountValidation() {
        try {
            $validAmounts = [
                '50000',
                '100,000',
                '۱۰۰۰۰۰',
                '1,000,000'
            ];
            
            $invalidAmounts = [
                '0',
                '-1000',
                'abc',
                '1000.5',
                '9999' // Below minimum
            ];
            
            // Test valid amounts
            foreach ($validAmounts as $amount) {
                $result = $this->validateAmount($amount);
                if (!$result) {
                    throw new Exception("Valid amount failed validation: $amount");
                }
            }
            
            // Test invalid amounts
            foreach ($invalidAmounts as $amount) {
                $result = $this->validateAmount($amount);
                if ($result) {
                    throw new Exception("Invalid amount passed validation: $amount");
                }
            }
            
            return true;
        } catch (Exception $e) {
            echo "Amount validation error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test transaction creation
     */
    private function testTransactionCreation() {
        try {
            $transactionData = [
                'source_card_number' => '6037991234567890',
                'destination_card_number' => '6037991234567890',
                'amount' => '50000',
                'bank_name' => 'بانک ملی ایران',
                'transaction_date' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->cardToCardManager->processTransaction($this->testUserId, $transactionData);
            
            if (!$result['success']) {
                throw new Exception("Transaction creation failed: " . $result['message']);
            }
            
            if (empty($result['transaction_id'])) {
                throw new Exception("No transaction ID returned");
            }
            
            // Store transaction ID for later tests
            $this->testTransactionId = $result['transaction_id'];
            
            return true;
        } catch (Exception $e) {
            echo "Transaction creation error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test transaction confirmation
     */
    private function testTransactionConfirmation() {
        try {
            if (empty($this->testTransactionId)) {
                throw new Exception("No test transaction ID available");
            }
            
            $result = $this->cardToCardManager->confirmTransaction($this->testTransactionId, 'test_admin', [
                'tracking_code' => 'TEST123456',
                'reference_number' => 'REF123456',
                'admin_notes' => 'Test confirmation'
            ]);
            
            if (!$result['success']) {
                throw new Exception("Transaction confirmation failed: " . $result['message']);
            }
            
            // Verify transaction status
            $transaction = $this->walletDatabase->getCardToCardTransaction($this->testTransactionId);
            if ($transaction['transaction_status'] !== 'confirmed') {
                throw new Exception("Transaction status not updated to confirmed");
            }
            
            return true;
        } catch (Exception $e) {
            echo "Transaction confirmation error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test transaction rejection
     */
    private function testTransactionRejection() {
        try {
            // Create a new transaction to reject
            $transactionData = [
                'source_card_number' => '6037991234567890',
                'destination_card_number' => '6037991234567890',
                'amount' => '100000',
                'bank_name' => 'بانک ملی ایران',
                'transaction_date' => date('Y-m-d H:i:s')
            ];
            
            $createResult = $this->cardToCardManager->processTransaction($this->testUserId, $transactionData);
            if (!$createResult['success']) {
                throw new Exception("Failed to create transaction for rejection test");
            }
            
            $rejectTransactionId = $createResult['transaction_id'];
            
            $result = $this->cardToCardManager->rejectTransaction($rejectTransactionId, 'test_admin', 'Test rejection reason');
            
            if (!$result['success']) {
                throw new Exception("Transaction rejection failed: " . $result['message']);
            }
            
            // Verify transaction status
            $transaction = $this->walletDatabase->getCardToCardTransaction($rejectTransactionId);
            if ($transaction['transaction_status'] !== 'rejected') {
                throw new Exception("Transaction status not updated to rejected");
            }
            
            return true;
        } catch (Exception $e) {
            echo "Transaction rejection error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test balance update
     */
    private function testBalanceUpdate() {
        try {
            // Get initial balance
            $initialBalance = $this->walletDatabase->getUserBalance($this->testUserId);
            
            // Create and confirm a transaction
            $transactionData = [
                'source_card_number' => '6037991234567890',
                'destination_card_number' => '6037991234567890',
                'amount' => '200000',
                'bank_name' => 'بانک ملی ایران',
                'transaction_date' => date('Y-m-d H:i:s')
            ];
            
            $createResult = $this->cardToCardManager->processTransaction($this->testUserId, $transactionData);
            if (!$createResult['success']) {
                throw new Exception("Failed to create transaction for balance test");
            }
            
            $balanceTestTransactionId = $createResult['transaction_id'];
            
            $confirmResult = $this->cardToCardManager->confirmTransaction($balanceTestTransactionId, 'test_admin');
            if (!$confirmResult['success']) {
                throw new Exception("Failed to confirm transaction for balance test");
            }
            
            // Check new balance
            $newBalance = $this->walletDatabase->getUserBalance($this->testUserId);
            $expectedBalance = $initialBalance + 20000; // 200,000 Rial = 20,000 Toman
            
            if ($newBalance != $expectedBalance) {
                throw new Exception("Balance not updated correctly. Expected: $expectedBalance, Got: $newBalance");
            }
            
            return true;
        } catch (Exception $e) {
            echo "Balance update error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test wallet transactions
     */
    private function testWalletTransactions() {
        try {
            // Get wallet transactions
            $transactions = $this->walletDatabase->getUserWalletTransactions($this->testUserId, 10, 0);
            
            if ($transactions === false) {
                throw new Exception("Failed to get wallet transactions");
            }
            
            if (empty($transactions)) {
                throw new Exception("No wallet transactions found");
            }
            
            // Verify transaction structure
            $requiredFields = ['id', 'user_id', 'transaction_type', 'amount', 'balance_before', 'balance_after', 'created_at'];
            foreach ($transactions as $transaction) {
                foreach ($requiredFields as $field) {
                    if (!isset($transaction[$field])) {
                        throw new Exception("Missing field in transaction: $field");
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            echo "Wallet transactions error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test user notifications
     */
    private function testUserNotifications() {
        try {
            // This would normally test actual notification sending
            // For now, we'll just verify the notification methods exist
            
            $methods = [
                'notifyUserTransactionCreated',
                'notifyUserTransactionConfirmed',
                'notifyUserTransactionRejected'
            ];
            
            $reflection = new ReflectionClass($this->cardToCardManager);
            foreach ($methods as $method) {
                if (!$reflection->hasMethod($method)) {
                    throw new Exception("Missing notification method: $method");
                }
            }
            
            return true;
        } catch (Exception $e) {
            echo "User notifications error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test admin notifications
     */
    private function testAdminNotifications() {
        try {
            // Verify admin notification method exists
            $reflection = new ReflectionClass($this->cardToCardManager);
            if (!$reflection->hasMethod('notifyAdminNewTransaction')) {
                throw new Exception("Missing admin notification method");
            }
            
            return true;
        } catch (Exception $e) {
            echo "Admin notifications error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test error handling
     */
    private function testErrorHandling() {
        try {
            // Test with invalid data
            $invalidData = [
                'source_card_number' => 'invalid',
                'amount' => '-1000',
                'bank_name' => ''
            ];
            
            $result = $this->cardToCardManager->processTransaction($this->testUserId, $invalidData);
            
            if ($result['success']) {
                throw new Exception("Invalid data was accepted");
            }
            
            // Test with missing required fields
            $incompleteData = [
                'source_card_number' => '6037991234567890'
                // Missing amount and other required fields
            ];
            
            $result = $this->cardToCardManager->processTransaction($this->testUserId, $incompleteData);
            
            if ($result['success']) {
                throw new Exception("Incomplete data was accepted");
            }
            
            return true;
        } catch (Exception $e) {
            echo "Error handling error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test security features
     */
    private function testSecurityFeatures() {
        try {
            // Test card number masking
            $reflection = new ReflectionClass($this->cardToCardManager);
            $method = $reflection->getMethod('maskCardNumber');
            $method->setAccessible(true);
            
            $cardNumber = '6037991234567890';
            $masked = $method->invoke($this->cardToCardManager, $cardNumber);
            
            if ($masked === $cardNumber) {
                throw new Exception("Card number not properly masked");
            }
            
            if (strpos($masked, '****') === false) {
                throw new Exception("Card number masking format incorrect");
            }
            
            // Test transaction ID generation
            $method = $reflection->getMethod('generateTransactionId');
            $method->setAccessible(true);
            
            $transactionId = $method->invoke($this->cardToCardManager, $this->testUserId);
            
            if (empty($transactionId)) {
                throw new Exception("Transaction ID generation failed");
            }
            
            if (strlen($transactionId) < 10) {
                throw new Exception("Transaction ID too short");
            }
            
            return true;
        } catch (Exception $e) {
            echo "Security features error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test performance
     */
    private function testPerformance() {
        try {
            $startTime = microtime(true);
            
            // Test transaction creation performance
            for ($i = 0; $i < 10; $i++) {
                $transactionData = [
                    'source_card_number' => '6037991234567890',
                    'destination_card_number' => '6037991234567890',
                    'amount' => '100000',
                    'bank_name' => 'بانک ملی ایران',
                    'transaction_date' => date('Y-m-d H:i:s')
                ];
                
                $result = $this->cardToCardManager->processTransaction($this->testUserId, $transactionData);
                if (!$result['success']) {
                    throw new Exception("Performance test transaction failed");
                }
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            if ($executionTime > 10) { // Should complete in less than 10 seconds
                throw new Exception("Performance test too slow: {$executionTime}s");
            }
            
            echo " (10 transactions in " . round($executionTime, 2) . "s) ";
            
            return true;
        } catch (Exception $e) {
            echo "Performance error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test edge cases
     */
    private function testEdgeCases() {
        try {
            // Test very large amount
            $largeAmountData = [
                'source_card_number' => '6037991234567890',
                'destination_card_number' => '6037991234567890',
                'amount' => '49999999', // Just under max
                'bank_name' => 'بانک ملی ایران',
                'transaction_date' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->cardToCardManager->processTransaction($this->testUserId, $largeAmountData);
            if (!$result['success']) {
                throw new Exception("Large amount transaction failed");
            }
            
            // Test minimum amount
            $minAmountData = [
                'source_card_number' => '6037991234567890',
                'destination_card_number' => '6037991234567890',
                'amount' => '10000', // Minimum amount
                'bank_name' => 'بانک ملی ایران',
                'transaction_date' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->cardToCardManager->processTransaction($this->testUserId, $minAmountData);
            if (!$result['success']) {
                throw new Exception("Minimum amount transaction failed");
            }
            
            // Test Persian numbers
            $persianAmountData = [
                'source_card_number' => '6037991234567890',
                'destination_card_number' => '6037991234567890',
                'amount' => '۵۰۰۰۰', // Persian numbers
                'bank_name' => 'بانک ملی ایران',
                'transaction_date' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->cardToCardManager->processTransaction($this->testUserId, $persianAmountData);
            if (!$result['success']) {
                throw new Exception("Persian numbers transaction failed");
            }
            
            return true;
        } catch (Exception $e) {
            echo "Edge cases error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Helper method to validate amount
     */
    private function validateAmount($amount) {
        $amount = str_replace([',', '،', ' '], '', $amount);
        $amount = intval($amount);
        
        return $amount >= 10000 && $amount <= 50000000;
    }
    
    /**
     * Clean up test data
     */
    public function cleanup() {
        echo "\n🧹 Cleaning up test data...\n";
        
        try {
            global $pdo;
            
            // Delete test transactions
            $stmt = $pdo->prepare("DELETE FROM card_to_card_transactions WHERE user_id = ?");
            $stmt->execute([$this->testUserId]);
            
            $stmt = $pdo->prepare("DELETE FROM wallet_transactions WHERE user_id = ?");
            $stmt->execute([$this->testUserId]);
            
            $stmt = $pdo->prepare("DELETE FROM bank_cards WHERE user_id = ?");
            $stmt->execute([$this->testUserId]);
            
            echo "✅ Test data cleaned up successfully\n";
        } catch (Exception $e) {
            echo "❌ Cleanup error: " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    echo "🏦 Wallet System Test Suite\n";
    echo "============================\n\n";
    
    $tester = new WalletTestSuite();
    $success = $tester->runAllTests();
    
    if ($success) {
        echo "\n🎉 All tests passed! The wallet system is working correctly.\n";
    } else {
        echo "\n⚠️  Some tests failed. Please review the errors above.\n";
    }
    
    $tester->cleanup();
    
    exit($success ? 0 : 1);
}

?>