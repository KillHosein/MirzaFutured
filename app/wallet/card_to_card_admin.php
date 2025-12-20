<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/wallet/database.php';
require_once __DIR__ . '/wallet/card_to_card_manager.php';

// Initialize managers
$walletDatabase = new WalletDatabase();
$cardToCardManager = new CardToCardManager();

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../panel/login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'confirm_transaction':
                $transactionId = $_POST['transaction_id'] ?? '';
                $adminId = $_SESSION['admin_id'] ?? 'admin';
                $trackingCode = $_POST['tracking_code'] ?? '';
                $referenceNumber = $_POST['reference_number'] ?? '';
                $adminNotes = $_POST['admin_notes'] ?? '';
                
                $result = $cardToCardManager->confirmTransaction($transactionId, $adminId, [
                    'tracking_code' => $trackingCode,
                    'reference_number' => $referenceNumber,
                    'admin_notes' => $adminNotes
                ]);
                
                if ($result['success']) {
                    $successMessage = $result['message'];
                } else {
                    $errorMessage = $result['message'];
                }
                break;
                
            case 'reject_transaction':
                $transactionId = $_POST['transaction_id'] ?? '';
                $adminId = $_SESSION['admin_id'] ?? 'admin';
                $reason = $_POST['rejection_reason'] ?? '';
                
                $result = $cardToCardManager->rejectTransaction($transactionId, $adminId, $reason);
                
                if ($result['success']) {
                    $successMessage = $result['message'];
                } else {
                    $errorMessage = $result['message'];
                }
                break;
        }
    }
}

// Get pending transactions
$pendingTransactions = $cardToCardManager->getPendingTransactions(50, 0);

// Get statistics
$stats = [
    'total_pending' => 0,
    'total_confirmed' => 0,
    'total_rejected' => 0,
    'total_amount_pending' => 0,
    'total_amount_confirmed' => 0
];

try {
    global $pdo;
    
    // Get statistics
    $stmt = $pdo->query("SELECT 
        transaction_status,
        COUNT(*) as count,
        SUM(amount_toman) as total_amount
        FROM card_to_card_transactions 
        GROUP BY transaction_status");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        switch ($row['transaction_status']) {
            case 'pending':
                $stats['total_pending'] = $row['count'];
                $stats['total_amount_pending'] = $row['total_amount'];
                break;
            case 'confirmed':
                $stats['total_confirmed'] = $row['count'];
                $stats['total_amount_confirmed'] = $row['total_amount'];
                break;
            case 'rejected':
                $stats['total_rejected'] = $row['count'];
                break;
        }
    }
} catch (Exception $e) {
    error_log("Error getting statistics: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تراکنشهای کارت به کارت - پنل ادمین</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Vazir', Tahoma, Arial, sans-serif;
            background: #f8f9fa;
        }
        
        .sidebar {
            background: #343a40;
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .sidebar .nav-link {
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 15px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #495057;
            color: #ffffff;
        }
        
        .sidebar .nav-link i {
            margin-left: 10px;
            width: 20px;
        }
        
        .main-content {
            padding: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .stats-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .stats-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stats-card .label {
            color: #666;
            font-size: 14px;
        }
        
        .transaction-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .transaction-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .transaction-amount {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .transaction-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .transaction-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        
        .transaction-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-confirm {
            background: #28a745;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            background: #218838;
        }
        
        .btn-reject {
            background: #dc3545;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .modal-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-title {
            font-weight: 500;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .page-title {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
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
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .card-mask {
            font-family: 'Courier New', monospace;
            direction: ltr;
            text-align: right;
        }
        
        .amount-format {
            font-family: 'Vazir FD', Tahoma, Arial, sans-serif;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center mb-4">
                    <h4 class="text-white">پنل ادمین</h4>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="../panel/index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        داشبورد
                    </a>
                    <a class="nav-link" href="../panel/users.php">
                        <i class="fas fa-users"></i>
                        کاربران
                    </a>
                    <a class="nav-link" href="../panel/payment.php">
                        <i class="fas fa-credit-card"></i>
                        پرداختها
                    </a>
                    <a class="nav-link active" href="card_to_card_admin.php">
                        <i class="fas fa-exchange-alt"></i>
                        تراکنشهای کارت به کارت
                    </a>
                    <a class="nav-link" href="../panel/settings.php">
                        <i class="fas fa-cog"></i>
                        تنظیمات
                    </a>
                    <a class="nav-link" href="../panel/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        خروج
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="page-title">
                                <i class="fas fa-exchange-alt"></i>
                                مدیریت تراکنشهای کارت به کارت
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../panel/index.php">پنل ادمین</a></li>
                                    <li class="breadcrumb-item active">تراکنشهای کارت به کارت</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i>
                                بروزرسانی
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Alerts -->
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($successMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="number"><?php echo number_format($stats['total_pending']); ?></div>
                            <div class="label">تراکنشهای در انتظار</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="number"><?php echo number_format($stats['total_confirmed']); ?></div>
                            <div class="label">تراکنشهای تایید شده</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="number"><?php echo number_format($stats['total_rejected']); ?></div>
                            <div class="label">تراکنشهای رد شده</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="number amount-format"><?php echo number_format($stats['total_amount_pending']); ?></div>
                            <div class="label">مبلغ در انتظار (تومان)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Transactions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock"></i>
                                    تراکنشهای در انتظار بررسی
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($pendingTransactions)): ?>
                                    <?php foreach ($pendingTransactions as $transaction): ?>
                                        <div class="transaction-card">
                                            <div class="transaction-header">
                                                <div class="transaction-amount amount-format">
                                                    <?php echo number_format($transaction['amount_toman']); ?> تومان
                                                </div>
                                                <div class="transaction-status status-pending">
                                                    در انتظار بررسی
                                                </div>
                                            </div>
                                            
                                            <div class="transaction-details">
                                                <div class="detail-item">
                                                    <div class="detail-label">کاربر</div>
                                                    <div class="detail-value">
                                                        <?php echo htmlspecialchars($transaction['namecustom'] ?: $transaction['username'] ?: 'کاربر ناشناس'); ?>
                                                        <small class="text-muted">(ID: <?php echo htmlspecialchars($transaction['user_id']); ?>)</small>
                                                    </div>
                                                </div>
                                                
                                                <div class="detail-item">
                                                    <div class="detail-label">شماره کارت مبدا</div>
                                                    <div class="detail-value card-mask">
                                                        <?php echo htmlspecialchars($transaction['source_card_number']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="detail-item">
                                                    <div class="detail-label">بانک</div>
                                                    <div class="detail-value">
                                                        <?php echo htmlspecialchars($transaction['bank_name'] ?: 'نامشخص'); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="detail-item">
                                                    <div class="detail-label">تاریخ</div>
                                                    <div class="detail-value">
                                                        <?php echo jdate('Y/m/d H:i', strtotime($transaction['created_at'])); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="detail-item">
                                                    <div class="detail-label">شناسه تراکنش</div>
                                                    <div class="detail-value">
                                                        <code><?php echo htmlspecialchars($transaction['transaction_id']); ?></code>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($transaction['tracking_code'])): ?>
                                                    <div class="detail-item">
                                                        <div class="detail-label">شماره پیگیری</div>
                                                        <div class="detail-value">
                                                            <?php echo htmlspecialchars($transaction['tracking_code']); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($transaction['reference_number'])): ?>
                                                    <div class="detail-item">
                                                        <div class="detail-label">شماره مرجع</div>
                                                        <div class="detail-value">
                                                            <?php echo htmlspecialchars($transaction['reference_number']); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="transaction-actions">
                                                <button class="btn-confirm" 
                                                        onclick="confirmTransaction('<?php echo htmlspecialchars($transaction['transaction_id']); ?>')"
                                                        title="تایید تراکنش">
                                                    <i class="fas fa-check"></i>
                                                    تایید
                                                </button>
                                                <button class="btn-reject" 
                                                        onclick="rejectTransaction('<?php echo htmlspecialchars($transaction['transaction_id']); ?>')"
                                                        title="رد تراکنش">
                                                    <i class="fas fa-times"></i>
                                                    رد
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>تراکنشی برای بررسی وجود ندارد</h4>
                                        <p>در حال حاضر هیچ تراکنش کارت به کارت در انتظار بررسی نیست.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirm Transaction Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle text-success"></i>
                        تایید تراکنش کارت به کارت
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="confirmForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="confirm_transaction">
                        <input type="hidden" name="transaction_id" id="confirmTransactionId">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            پس از تایید این تراکنش، مبلغ مورد نظر به کیف پول کاربر افزوده خواهد شد.
                        </div>
                        
                        <div class="mb-3">
                            <label for="trackingCode" class="form-label">شماره پیگیری (اختیاری)</label>
                            <input type="text" class="form-control" id="trackingCode" name="tracking_code" 
                                   placeholder="در صورت داشتن شماره پیگیری وارد کنید">
                        </div>
                        
                        <div class="mb-3">
                            <label for="referenceNumber" class="form-label">شماره مرجع (اختیاری)</label>
                            <input type="text" class="form-control" id="referenceNumber" name="reference_number" 
                                   placeholder="در صورت داشتن شماره مرجع وارد کنید">
                        </div>
                        
                        <div class="mb-3">
                            <label for="adminNotes" class="form-label">یادداشت ادمین (اختیاری)</label>
                            <textarea class="form-control" id="adminNotes" name="admin_notes" rows="2" 
                                      placeholder="هرگونه یادداشت یا توضیح اضافی"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i>
                            تایید تراکنش
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Transaction Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle text-danger"></i>
                        رد تراکنش کارت به کارت
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_transaction">
                        <input type="hidden" name="transaction_id" id="rejectTransactionId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            پس از رد این تراکنش، دلیل رد شدن به کاربر اطلاع داده خواهد شد.
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label">
                                دلیل رد تراکنش <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="3" 
                                      placeholder="لطفاً دلیل رد این تراکنش را توضیح دهید" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i>
                            رد تراکنش
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmTransaction(transactionId) {
            document.getElementById('confirmTransactionId').value = transactionId;
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }
        
        function rejectTransaction(transactionId) {
            document.getElementById('rejectTransactionId').value = transactionId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
        
        // Auto refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Form validation
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('rejectionReason').value.trim();
            if (!reason) {
                e.preventDefault();
                alert('لطفاً دلیل رد تراکنش را وارد کنید.');
                return false;
            }
        });
    </script>
</body>
</html>

<?php
function jdate($format, $timestamp = null) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    // Simple Persian date function - you may want to use a proper library
    $date = date($format, $timestamp);
    
    // Replace English numbers with Persian numbers
    $persianNumbers = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'
    ];
    
    return strtr($date, $persianNumbers);
}
?>