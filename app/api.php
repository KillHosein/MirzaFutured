<?php
require_once '../config.php';

header('Content-Type: application/json');

// 1. Helper to validate Telegram Web App Data
function validateTelegramAuth($initData, $botToken) {
    if (!$initData) return false;
    
    $data = [];
    parse_str($initData, $data);
    
    if (!isset($data['hash'])) return false;
    
    $hash = $data['hash'];
    unset($data['hash']);
    
    $dataCheckArr = [];
    foreach ($data as $key => $value) {
        $dataCheckArr[] = $key . '=' . $value;
    }
    sort($dataCheckArr);
    $dataCheckString = implode("\n", $dataCheckArr);
    
    $secretKey = hash_hmac('sha256', $botToken, "WebAppData", true);
    $computedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));
    
    if (strcmp($hash, $computedHash) !== 0) {
        return false;
    }
    
    // Check for data freshness (optional, e.g. 24h)
    if (isset($data['auth_date']) && (time() - $data['auth_date'] > 86400)) {
        return false;
    }
    
    return $data;
}

// 2. Get initData from POST
$input = json_decode(file_get_contents('php://input'), true);
$initData = $input['initData'] ?? '';

if (!$initData) {
    echo json_encode(['ok' => false, 'error' => 'No initData provided']);
    exit;
}

// 3. Validate
// $APIKEY comes from ../config.php
$validatedData = validateTelegramAuth($initData, $APIKEY);

if (!$validatedData) {
    echo json_encode(['ok' => false, 'error' => 'Invalid authentication']);
    exit;
}

// 4. Fetch User Data from DB
$userJson = $validatedData['user'] ?? '{}';
$telegramUser = json_decode($userJson, true);
$userId = $telegramUser['id'] ?? 0;

try {
    // Connect to DB using $pdo from config.php
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Fetch Service Counts/Stats (Similar to api/users.php logic)
        
        // Active Services
        $stmtService = $pdo->prepare("SELECT COUNT(*) as count, SUM(price) as total_spent FROM service_other WHERE id_user = :id AND status = 'paid'");
        $stmtService->execute([':id' => $userId]);
        $serviceStats = $stmtService->fetch(PDO::FETCH_ASSOC);
        
        // Invoices
        $stmtInvoice = $pdo->prepare("SELECT COUNT(*) as count FROM invoice WHERE id_user = :id AND Status = 'unpaid'");
        $stmtInvoice->execute([':id' => $userId]);
        $unpaidInvoices = $stmtInvoice->fetch(PDO::FETCH_ASSOC);

        // Fetch Products (limit 5 for dashboard)
        $stmtProducts = $pdo->query("SELECT * FROM product ORDER BY id DESC LIMIT 5");
        $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'balance' => number_format($user['Balance']),
                'raw_balance' => $user['Balance'],
                'joined_at' => date('Y/m/d', $user['register']),
                'score' => $user['score'],
                'code_invitation' => $user['codeInvitation'],
            ],
            'stats' => [
                'active_services' => $serviceStats['count'] ?? 0,
                'total_spent' => number_format($serviceStats['total_spent'] ?? 0),
                'unpaid_invoices' => $unpaidInvoices['count'] ?? 0
            ],
            'products' => $products
        ]);
    } else {
        // User not found in bot DB
        echo json_encode([
            'ok' => true,
            'is_new' => true,
            'user' => $telegramUser // Return basic telegram info
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
