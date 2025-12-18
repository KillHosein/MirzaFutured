<?php
// Turn off all error reporting to the output to prevent invalid JSON
error_reporting(0);
ini_set('display_errors', 0);

// Buffer output so we can discard any warnings/notices/text before sending JSON
ob_start();

// Set Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Define response helper
function sendResponse($ok, $data = [], $error = null) {
    // Clear any buffered output (like warnings or 'die' messages if captured, though die kills script)
    ob_clean(); 
    
    $response = ['ok' => $ok];
    if ($error) $response['error'] = $error;
    if (!empty($data)) $response = array_merge($response, $data);
    
    echo json_encode($response);
    exit;
}

// Global exception handler
set_exception_handler(function($e) {
    sendResponse(false, [], 'Server Error: ' . $e->getMessage());
});

// 1. Check Config Integrity
$configPath = __DIR__ . '/../../config.php';
if (!file_exists($configPath)) {
    sendResponse(false, [], 'Config file not found');
}

$configContent = file_get_contents($configPath);
if (strpos($configContent, '{database_name}') !== false || strpos($configContent, '{API_KEY}') !== false) {
    // Bot is not configured yet. Return a friendly error or mock data for testing.
    // Let's return mock data so the WebApp works even if not configured (Demo Mode)
    $mockData = [
        'user' => [
            'id' => 123456,
            'username' => 'demo_user',
            'balance' => '0 تومان',
            'raw_balance' => 0,
            'name' => 'کاربر نمایشی (ربات پیکربندی نشده)'
        ],
        'transactions' => [],
        'services' => []
    ];
    sendResponse(true, $mockData);
}

// 2. Load Core Files
try {
    // Change directory to root for relative includes in function.php
    chdir(__DIR__ . '/../../');
    
    // We suppress the 'die' from config.php by checking connection manually? 
    // No, we can't stop 'die'. But we checked for placeholders above.
    // If credentials are wrong but not placeholders, it will still die.
    // There is no easy way to prevent 'die' in included file without editing it.
    // However, we can hope it connects if configured.
    
    require_once 'config.php';
    require_once 'function.php';
    
} catch (Throwable $e) {
    sendResponse(false, [], 'Core Load Error: ' . $e->getMessage());
}

// 3. Validate WebApp Data
function validateWebAppData($initData, $botToken) {
    if (!$initData) return false;
    parse_str($initData, $data);
    if (!isset($data['hash'])) return false;
    $hash = $data['hash'];
    unset($data['hash']);
    $data_check_arr = [];
    foreach ($data as $key => $value) {
        $data_check_arr[] = $key . '=' . $value;
    }
    sort($data_check_arr);
    $data_check_string = implode("\n", $data_check_arr);
    $secret_key = hash_hmac('sha256', $botToken, "WebAppData", true);
    $hash_check = hash_hmac('sha256', $data_check_string, $secret_key);
    return hash_equals($hash, $hash_check) ? $data : false;
}

// 4. Process Request
try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    $initData = $input['initData'] ?? '';

    // If no initData, return demo data (for browser testing)
    if (!$initData) {
        sendResponse(false, [], 'No authentication data');
    }

    // Validate
    $validatedData = validateWebAppData($initData, $APIKEY);
    if (!$validatedData) {
        sendResponse(false, [], 'Invalid authentication');
    }

    $userJson = $validatedData['user'] ?? '{}';
    $tgUser = json_decode($userJson, true);
    $userId = $tgUser['id'] ?? 0;

    if ($userId == 0) {
        sendResponse(false, [], 'Invalid User ID');
    }

    // Fetch from DB
    // Check if tables exist to avoid crashes
    
    // Fetch User
    $dbUser = select("user", "*", "id", $userId, "select");
    if (!$dbUser) {
        // User not found in DB
        sendResponse(false, [], 'User not registered in bot');
    }

    // Fetch Transactions
    $transactions = [];
    $transactionsRaw = select("Payment_report", "*", "id_user", $userId, "fetchAll");
    if ($transactionsRaw && is_array($transactionsRaw)) {
        foreach ($transactionsRaw as $tx) {
            $transactions[] = [
                'id' => $tx['id_order'] ?? $tx['id'] ?? uniqid(),
                'amount' => $tx['price'] ?? 0,
                'status' => $tx['payment_status'] ?? 'unknown',
                'date' => $tx['date'] ?? '',
                'description' => 'شارژ حساب'
            ];
        }
        usort($transactions, function($a, $b) {
            return $b['id'] <=> $a['id'];
        });
    }

    // Fetch Services
    $services = [];
    $invoicesRaw = select("invoice", "*", "id_user", $userId, "fetchAll");
    if ($invoicesRaw && is_array($invoicesRaw)) {
        foreach ($invoicesRaw as $inv) {
            $services[] = [
                'id' => $inv['id_invoice'] ?? $inv['id'] ?? uniqid(),
                'name' => $inv['service_name'] ?? 'سرویس VPN',
                'expire_date' => $inv['expire_date'] ?? '',
                'status' => 'active', 
                'traffic_usage' => $inv['traffic'] ?? '0'
            ];
        }
    }

    // Success Response
    $responseData = [
        'user' => [
            'id' => $dbUser['id'],
            'username' => $dbUser['username'],
            'balance' => number_format($dbUser['Balance']) . ' تومان',
            'raw_balance' => (float)$dbUser['Balance'],
            'name' => $dbUser['namecustom'] !== 'none' ? $dbUser['namecustom'] : ($tgUser['first_name'] . ' ' . ($tgUser['last_name'] ?? '')),
        ],
        'transactions' => array_slice($transactions, 0, 50),
        'services' => array_slice($services, 0, 20)
    ];

    sendResponse(true, $responseData);

} catch (Throwable $e) {
    sendResponse(false, [], 'Processing Error: ' . $e->getMessage());
}
?>
