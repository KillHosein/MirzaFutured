<?php
// Ensure we are in the API directory context but can access root files
chdir(__DIR__);

// Fix for relative paths in required files
// We need to move up to the root directory to include config and function properly
// Because function.php uses relative paths like 'vendor/autoload.php' assuming it's in root
chdir('../../');

// Now we are in the project root
require_once 'config.php';
require_once 'function.php';

// Return to API directory if needed (optional, but good practice if we output relative links)
// chdir('app/api'); 

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Helper to validate WebApp Data
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
    
    if (!hash_equals($hash, $hash_check)) {
        return false;
    }
    
    // Check if data is outdated (1 hour)
    if (isset($data['auth_date'])) {
        if (time() - $data['auth_date'] > 3600) return false;
    }
    
    return $data;
}

// Error Handling to prevent HTML output on fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Internal Server Error: ' . $error['message']]);
        exit;
    }
});

// Main Logic
try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    $initData = $input['initData'] ?? '';

    if (!$initData) {
        // If testing without initData, return mock error or handle gracefully
        echo json_encode(['ok' => false, 'error' => 'No authentication data provided']);
        exit;
    }

    $validatedData = validateWebAppData($initData, $APIKEY);

    if (!$validatedData) {
        echo json_encode(['ok' => false, 'error' => 'Invalid authentication data']);
        exit;
    }

    $userJson = $validatedData['user'] ?? '{}';
    $tgUser = json_decode($userJson, true);
    $userId = $tgUser['id'] ?? 0;

    if ($userId == 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid user ID']);
        exit;
    }

    // Fetch User Data from DB
    $dbUser = select("user", "*", "id", $userId, "select");

    if (!$dbUser) {
        // Optional: Auto-register user if not found?
        // For now, return error
        echo json_encode(['ok' => false, 'error' => 'User not found. Please start the bot first.']);
        exit;
    }

    // Fetch Transactions (Payment_report)
    $transactionsRaw = select("Payment_report", "*", "id_user", $userId, "fetchAll");
    $transactions = [];
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

    // Fetch Active Services
    $invoicesRaw = select("invoice", "*", "id_user", $userId, "fetchAll");
    $services = [];
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

    $response = [
        'ok' => true,
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

    echo json_encode($response);

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
