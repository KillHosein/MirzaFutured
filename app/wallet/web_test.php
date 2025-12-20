<?php
/**
 * Wallet System Web Test Interface
 * Web-based testing for card-to-card wallet functionality
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/card_to_card_manager.php';

// Initialize managers
$walletDatabase = new WalletDatabase();
$cardToCardManager = new CardToCardManager();

// Handle test requests
$testResults = [];
$action = $_GET['action'] ?? '';

if ($action === 'initialize') {
    try {
        $result = $walletDatabase->initializeTables();
        $testResults[] = [
            'name' => 'Database Initialization',
            'status' => $result ? 'passed' : 'failed',
            'message' => $result ? 'Database tables created successfully' : 'Failed to create database tables'
        ];
    } catch (Exception $e) {
        $testResults[] = [
            'name' => 'Database Initialization',
            'status' => 'failed',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

if ($action === 'test_card_validation') {
    try {
        // Test card validation
        $validCards = ['6037991234567890'];
        $invalidCards = ['6037991234567891', '1234567890123456', '603799123456789', '60379912345678901'];
        
        $allPassed = true;
        $messages = [];
        
        // Test valid cards
        foreach ($validCards as $card) {
            $result = validateCardNumber($card);
            if (!$result) {
                $allPassed = false;
                $messages[] = "Valid card failed validation: $card";
            }
        }
        
        // Test invalid cards
        foreach ($invalidCards as $card) {
            $result = validateCardNumber($card);
            if ($result) {
                $allPassed = false;
                $messages[] = "Invalid card passed validation: $card";
            }
        }
        
        $testResults[] = [
            'name' => 'Card Validation',
            'status' => $allPassed ? 'passed' : 'failed',
            'message' => $allPassed ? 'All card validation tests passed' : implode(', ', $messages)
        ];
    } catch (Exception $e) {
        $testResults[] = [
            'name' => 'Card Validation',
            'status' => 'failed',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

if ($action === 'test_amount_validation') {
    try {
        $validAmounts = ['50000', '100,000', '۱۰۰۰۰۰', '1,000,000'];
        $invalidAmounts = ['0', '-1000', 'abc', '1000.5', '9999'];
        
        $allPassed = true;
        $messages = [];
        
        // Test valid amounts
        foreach ($validAmounts as $amount) {
            $result = validateAmount($amount);
            if (!$result) {
                $allPassed = false;
                $messages[] = "Valid amount failed validation: $amount";
            }
        }
        
        // Test invalid amounts
        foreach ($invalidAmounts as $amount) {
            $result = validateAmount($amount);
            if ($result) {
                $allPassed = false;
                $messages[] = "Invalid amount passed validation: $amount";
            }
        }
        
        $testResults[] = [
            'name' => 'Amount Validation',
            'status' => $allPassed ? 'passed' : 'failed',
            'message' => $allPassed ? 'All amount validation tests passed' : implode(', ', $messages)
        ];
    } catch (Exception $e) {
        $testResults[] = [
            'name' => 'Amount Validation',
            'status' => 'failed',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

if ($action === 'test_transaction_creation') {
    try {
        $testUserId = 'test_user_' . time();
        $transactionData = [
            'source_card_number' => '6037991234567890',
            'destination_card_number' => '6037991234567890',
            'amount' => '50000',
            'bank_name' => 'بانک ملی ایران',
            'transaction_date' => date('Y-m-d H:i:s')
        ];
        
        $result = $cardToCardManager->processTransaction($testUserId, $transactionData);
        
        if ($result['success']) {
            $_SESSION['test_transaction_id'] = $result['transaction_id'];
            $testResults[] = [
                'name' => 'Transaction Creation',
                'status' => 'passed',
                'message' => 'Transaction created successfully. ID: ' . $result['transaction_id']
            ];
        } else {
            $testResults[] = [
                'name' => 'Transaction Creation',
                'status' => 'failed',
                'message' => 'Failed to create transaction: ' . $result['message']
            ];
        }
    } catch (Exception $e) {
        $testResults[] = [
            'name' => 'Transaction Creation',
            'status' => 'failed',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

if ($action === 'test_transaction_confirmation') {
    try {
        $transactionId = $_SESSION['test_transaction_id'] ?? null;
        
        if (!$transactionId) {
            $testResults[] = [
                'name' => 'Transaction Confirmation',
                'status' => 'failed',
                'message' => 'No test transaction ID available'
            ];
        } else {
            $result = $cardToCardManager->confirmTransaction($transactionId, 'test_admin', [
                'tracking_code' => 'TEST123456',
                'reference_number' => 'REF123456',
                'admin_notes' => 'Test confirmation'
            ]);
            
            if ($result['success']) {
                $testResults[] = [
                    'name' => 'Transaction Confirmation',
                    'status' => 'passed',
                    'message' => 'Transaction confirmed successfully'
                ];
            } else {
                $testResults[] = [
                    'name' => 'Transaction Confirmation',
                    'status' => 'failed',
                    'message' => 'Failed to confirm transaction: ' . $result['message']
                ];
            }
        }
    } catch (Exception $e) {
        $testResults[] = [
            'name' => 'Transaction Confirmation',
            'status' => 'failed',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

if ($action === 'test_balance_update') {
    try {
        $testUserId = 'test_user_balance_' . time();
        
        // Get initial balance
        $initialBalance = $walletDatabase->getUserBalance($testUserId);
        
        // Create and confirm a transaction
        $transactionData = [
            'source_card_number' => '6037991234567890',
            'destination_card_number' => '6037991234567890',
            'amount' => '200000',
            'bank_name' => 'بانک ملی ایران',
            'transaction_date' => date('Y-m-d H:i:s')
        ];
        
        $createResult = $cardToCardManager->processTransaction($testUserId, $transactionData);
        if (!$createResult['success']) {
            throw new Exception("Failed to create transaction for balance test");
        }
        
        $confirmResult = $cardToCardManager->confirmTransaction($createResult['transaction_id'], 'test_admin');
        if (!$confirmResult['success']) {
            throw new Exception("Failed to confirm transaction for balance test");
        }
        
        // Check new balance
        $newBalance = $walletDatabase->getUserBalance($testUserId);
        $expectedBalance = 20000; // 200,000 Rial = 20,000 Toman
        
        if ($newBalance == $expectedBalance) {
            $testResults[] = [
                'name' => 'Balance Update',
                'status' => 'passed',
                'message' => 'Balance updated correctly. New balance: ' . $newBalance . ' Toman'
            ];
        } else {
            $testResults[] = [
                'name' => 'Balance Update',
                'status' => 'failed',
                'message' => 'Balance not updated correctly. Expected: ' . $expectedBalance . ', Got: ' . $newBalance
            ];
        }
    } catch (Exception $e) {
        $testResults[] = [
            'name' => 'Balance Update',
            'status' => 'failed',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

if ($action === 'cleanup') {
    try {
        // Clean up test data
        global $pdo;
        
        $stmt = $pdo->prepare("DELETE FROM card_to_card_transactions WHERE user_id LIKE 'test_user_%'");
        $stmt->execute();
        
        $stmt = $pdo->prepare("DELETE FROM wallet_transactions WHERE user_id LIKE 'test_user_%'");
        $stmt->execute();
        
        $stmt = $pdo->prepare("DELETE FROM bank_cards WHERE user_id LIKE 'test_user_%'");
        $stmt->execute();
        
        unset($_SESSION['test_transaction_id']);
        
        $testResults[] = [
            'name' => 'Cleanup',
            'status' => 'passed',
            'message' => 'Test data cleaned up successfully'
        ];
    } catch (Exception $e) {
        $testResults[] = [
            'name' => 'Cleanup',
            'status' => 'failed',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Helper functions
function validateCardNumber($cardNumber) {
    $cardNumber = preg_replace('/[^0-9]/', '', $cardNumber);
    
    if (strlen($cardNumber) !== 16) {
        return false;
    }
    
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

function validateAmount($amount) {
    $amount = str_replace([',', '،', ' '], '', $amount);
    $amount = intval($amount);
    
    return $amount >= 10000 && $amount <= 50000000;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست سیستم کیف پول - میرزا</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Vazir', Tahoma, Arial, sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        
        .test-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .test-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .test-result {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .result-passed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .result-failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .result-icon {
            font-size: 24px;
        }
        
        .result-content {
            flex: 1;
        }
        
        .result-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .result-message {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .test-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-test {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary-test {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success-test {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        
        .btn-warning-test {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            color: white;
        }
        
        .btn-danger-test {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #333;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-header">
            <h1><i class="fas fa-vial"></i> تست سیستم کیف پول</h1>
            <p class="text-muted">تست جامع عملکرد سیستم کیف پول و تراکنشهای کارت به کارت</p>
        </div>
        
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo count($testResults); ?></div>
                    <div class="stats-label">تستهای اجرا شده</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo count(array_filter($testResults, fn($r) => $r['status'] === 'passed')); ?></div>
                    <div class="stats-label">تستهای موفق</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo count(array_filter($testResults, fn($r) => $r['status'] === 'failed')); ?></div>
                    <div class="stats-label">تستهای ناموفق</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo round((count(array_filter($testResults, fn($r) => $r['status'] === 'passed')) / max(count($testResults), 1)) * 100); ?>%</div>
                    <div class="stats-label">نرخ موفقیت</div>
                </div>
            </div>
        </div>
        
        <!-- Test Actions -->
        <div class="test-card">
            <h4 class="mb-4"><i class="fas fa-cogs"></i> عملیات تست</h4>
            <div class="test-actions">
                <a href="?action=initialize" class="btn-test btn-primary-test">
                    <i class="fas fa-database"></i>
                    راهاندازی پایگاه داده
                </a>
                <a href="?action=test_card_validation" class="btn-test btn-success-test">
                    <i class="fas fa-credit-card"></i>
                    تست اعتبارسنجی کارت
                </a>
                <a href="?action=test_amount_validation" class="btn-test btn-success-test">
                    <i class="fas fa-money-bill-wave"></i>
                    تست اعتبارسنجی مبلغ
                </a>
                <a href="?action=test_transaction_creation" class="btn-test btn-warning-test">
                    <i class="fas fa-plus-circle"></i>
                    تست ایجاد تراکنش
                </a>
                <a href="?action=test_transaction_confirmation" class="btn-test btn-warning-test">
                    <i class="fas fa-check-circle"></i>
                    تست تایید تراکنش
                </a>
                <a href="?action=test_balance_update" class="btn-test btn-warning-test">
                    <i class="fas fa-wallet"></i>
                    تست بروزرسانی موجودی
                </a>
                <a href="?action=cleanup" class="btn-test btn-danger-test">
                    <i class="fas fa-trash"></i>
                    حذف دادههای تست
                </a>
            </div>
        </div>
        
        <!-- Test Results -->
        <?php if (!empty($testResults)): ?>
            <div class="test-card">
                <h4 class="mb-4"><i class="fas fa-chart-bar"></i> نتایج تست</h4>
                <?php foreach ($testResults as $result): ?>
                    <div class="test-result result-<?php echo $result['status']; ?>">
                        <div class="result-icon">
                            <?php echo $result['status'] === 'passed' ? '✅' : '❌'; ?>
                        </div>
                        <div class="result-content">
                            <div class="result-title"><?php echo htmlspecialchars($result['name']); ?></div>
                            <div class="result-message"><?php echo htmlspecialchars($result['message']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- System Status -->
        <div class="test-card">
            <h4 class="mb-4"><i class="fas fa-info-circle"></i> وضعیت سیستم</h4>
            <div class="row">
                <div class="col-md-6">
                    <h6>پایگاه داده</h6>
                    <?php
                    try {
                        global $pdo;
                        $stmt = $pdo->query("SELECT 1");
                        echo '<span class="text-success">✅ متصل</span>';
                    } catch (Exception $e) {
                        echo '<span class="text-danger">❌ غیرفعال - ' . $e->getMessage() . '</span>';
                    }
                    ?>
                </div>
                <div class="col-md-6">
                    <h6>تابلهای موجود</h6>
                    <?php
                    try {
                        global $pdo;
                        $tables = ['card_to_card_transactions', 'wallet_transactions', 'bank_cards'];
                        $existingTables = [];
                        
                        foreach ($tables as $table) {
                            $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = '$table'");
                            if ($stmt->fetch()) {
                                $existingTables[] = $table;
                            }
                        }
                        
                        if (count($existingTables) === count($tables)) {
                            echo '<span class="text-success">✅ تمام تابلها موجود هستند</span>';
                        } else {
                            echo '<span class="text-warning">⚠️ برخی تابلها موجود نیستند: ' . implode(', ', array_diff($tables, $existingTables)) . '</span>';
                        }
                    } catch (Exception $e) {
                        echo '<span class="text-danger">❌ خطا در بررسی تابلها</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="test-card">
            <h4 class="mb-4"><i class="fas fa-link"></i> لینکهای مفید</h4>
            <div class="row">
                <div class="col-md-6">
                    <a href="../wallet.php?user_id=test_user&username=testuser&first_name=تست&last_name=کاربر" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-wallet"></i>
                        مشاهده کیف پول
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="card_to_card_admin.php" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-cog"></i>
                        پنل ادمین
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh page after actions
        <?php if (!empty($action)): ?>
            setTimeout(function() {
                window.location.href = window.location.pathname;
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>