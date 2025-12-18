<?php
require_once '../../config.php';
require_once '../../function.php';

// Headers
header('Content-Type: application/json');
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
    
    if (strcmp($hash, $hash_check) !== 0) {
        return false;
    }
    
    // Check if data is outdated (optional but recommended)
    if (isset($data['auth_date'])) {
        if (time() - $data['auth_date'] > 86400) return false;
    }
    
    return $data;
}

// Main Logic
$input = json_decode(file_get_contents('php://input'), true);
$initData = $input['initData'] ?? '';

// Debug: If no initData provided (e.g. testing in browser), we might want to skip validation OR fail.
// For production, we must fail.
if (!$initData) {
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
    echo json_encode(['ok' => false, 'error' => 'User not found in database']);
    exit;
}

// Fetch Transactions (Payment_report)
$transactionsRaw = select("Payment_report", "*", "id_user", $userId, "fetchAll");
$transactions = [];
if ($transactionsRaw && is_array($transactionsRaw)) {
    // Normalize and sort
    foreach ($transactionsRaw as $tx) {
        $transactions[] = [
            'id' => $tx['id_order'] ?? $tx['id'] ?? uniqid(),
            'amount' => $tx['price'] ?? 0,
            'status' => $tx['payment_status'] ?? 'unknown',
            'date' => $tx['date'] ?? '',
            'description' => 'شارژ حساب' // Can be customized
        ];
    }
    // Sort by ID or Date (assuming higher ID is newer)
    usort($transactions, function($a, $b) {
        return $b['id'] <=> $a['id'];
    });
}

// Fetch Active Services (invoice)
// Assuming 'invoice' table holds purchased services
$invoicesRaw = select("invoice", "*", "id_user", $userId, "fetchAll");
$services = [];
if ($invoicesRaw && is_array($invoicesRaw)) {
    foreach ($invoicesRaw as $inv) {
        $services[] = [
            'id' => $inv['id_invoice'] ?? $inv['id'] ?? uniqid(),
            'name' => $inv['service_name'] ?? 'سرویس VPN', // Adjust column name if needed
            'expire_date' => $inv['expire_date'] ?? '',
            'status' => 'active', // You might need to check expiration
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
        'raw_balance' => $dbUser['Balance'],
        'name' => $dbUser['namecustom'] !== 'none' ? $dbUser['namecustom'] : ($tgUser['first_name'] . ' ' . ($tgUser['last_name'] ?? '')),
    ],
    'transactions' => array_slice($transactions, 0, 20),
    'services' => array_slice($services, 0, 10)
];

echo json_encode($response);
?>
