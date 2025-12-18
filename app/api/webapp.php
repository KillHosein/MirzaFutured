<?php
require_once '../../config.php';
require_once '../../function.php';

header('Content-Type: application/json');

function sendJson($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function validateWebAppData($initData, $botToken)
{
    if (!is_string($initData) || trim($initData) === '') {
        return false;
    }

    parse_str($initData, $data);
    if (!is_array($data) || !isset($data['hash']) || !is_string($data['hash'])) {
        return false;
    }

    $hash = $data['hash'];
    unset($data['hash']);

    $dataCheckArr = [];
    foreach ($data as $key => $value) {
        if (!is_string($key) || (!is_string($value) && !is_numeric($value))) {
            continue;
        }
        $dataCheckArr[] = $key . '=' . $value;
    }
    sort($dataCheckArr, SORT_STRING);
    $dataCheckString = implode("\n", $dataCheckArr);

    $secretKey = hash_hmac('sha256', 'WebAppData', $botToken, true);
    $hashCheck = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($hashCheck, $hash)) {
        return false;
    }

    if (isset($data['auth_date']) && is_numeric($data['auth_date'])) {
        if (time() - (int) $data['auth_date'] > 3600) {
            return false;
        }
    }

    return $data;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJson(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$initData = $input['initData'] ?? '';

if (!$initData) {
    sendJson(['ok' => false, 'error' => 'No authentication data provided'], 401);
}

$validatedData = validateWebAppData($initData, $APIKEY);

if (!$validatedData) {
    sendJson(['ok' => false, 'error' => 'Invalid authentication data'], 401);
}

$userJson = $validatedData['user'] ?? '{}';
$tgUser = json_decode($userJson, true);
$userId = $tgUser['id'] ?? 0;

if ($userId == 0) {
    sendJson(['ok' => false, 'error' => 'Invalid user ID'], 400);
}

// Fetch User Data from DB
$dbUser = select("user", "*", "id", $userId, "select");

if (!$dbUser) {
    sendJson(['ok' => false, 'error' => 'User not found'], 404);
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
            'description' => $tx['description'] ?? 'شارژ حساب'
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
        $status = $inv['status'] ?? 'active';
        $services[] = [
            'id' => $inv['id_invoice'] ?? $inv['id'] ?? uniqid(),
            'name' => $inv['service_name'] ?? $inv['name_product'] ?? 'سرویس VPN',
            'expire_date' => $inv['expire_date'] ?? $inv['expire'] ?? '',
            'status' => $status,
            'traffic_usage' => $inv['traffic'] ?? '0'
        ];
    }
}

$response = [
    'ok' => true,
    'user' => [
        'id' => $dbUser['id'],
        'username' => $dbUser['username'] ?? '',
        'balance' => number_format((int) ($dbUser['Balance'] ?? 0)) . ' تومان',
        'raw_balance' => (int) ($dbUser['Balance'] ?? 0),
        'name' => (isset($dbUser['namecustom']) && $dbUser['namecustom'] !== 'none' && $dbUser['namecustom'] !== '') ? $dbUser['namecustom'] : trim(($tgUser['first_name'] ?? '') . ' ' . ($tgUser['last_name'] ?? '')),
    ],
    'transactions' => array_slice($transactions, 0, 20),
    'services' => array_slice($services, 0, 10)
];

sendJson($response);
