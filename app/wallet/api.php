<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/card_to_card_manager.php';

header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');

// Initialize managers
$walletDatabase = new WalletDatabase();
$cardToCardManager = new CardToCardManager();

/**
 * Utility Functions
 */
function sendJsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'msg' => $message,
        'obj' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function validateToken($headers) {
    global $APIKEY;
    if (!isset($headers['Token'])) {
        return false;
    }
    if (is_file('hash.txt')) {
        $token = file_get_contents('hash.txt');
    } else {
        $token = "";
    }
    $validTokens = [$token, $APIKEY];
    return in_array($headers['Token'], $validTokens, true);
}

function sanitizeRecursive($data) {
    if (is_array($data)) {
        return array_map('sanitizeRecursive', $data);
    }
    return is_string($data) ? htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8') : $data;
}

function logApiRequest($headers, $data, $action) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO logs_api (header, data, time, ip, actions) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            json_encode($headers),
            json_encode($data),
            date('Y/m/d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $action
        ]);
    } catch (Exception $e) {
        error_log("API logging error: " . $e->getMessage());
    }
}

// Token validation
$headers = getallheaders();
if (!validateToken($headers)) {
    sendJsonResponse(false, "توکن نامعتبر است", [], 403);
}

// Get and sanitize input
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    sendJsonResponse(false, "دادههای ورودی نامعتبر است");
}

$data = sanitizeRecursive($data);
$action = $data['action'] ?? null;

// Log the API request
logApiRequest($headers, $data, $action);

// Handle API actions
switch ($action) {
    case 'create_card_to_card_transaction':
        if ($method !== 'POST') {
            sendJsonResponse(false, "متد نامعتبر است؛ باید POST باشد");
        }
        
        // Validate required fields
        $requiredFields = ['user_id', 'source_card_number', 'destination_card_number', 'amount'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                sendJsonResponse(false, "فیلد اجباری خالی است: $field");
            }
        }
        
        // Prepare transaction data
        $transactionData = [
            'source_card_number' => $data['source_card_number'],
            'destination_card_number' => $data['destination_card_number'],
            'amount' => $data['amount'],
            'bank_name' => $data['bank_name'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? date('Y-m-d H:i:s')
        ];
        
        // Process transaction
        $result = $cardToCardManager->processTransaction($data['user_id'], $transactionData);
        
        if ($result['success']) {
            sendJsonResponse(true, $result['message'], [
                'transaction_id' => $result['transaction_id']
            ]);
        } else {
            sendJsonResponse(false, $result['message']);
        }
        break;
        
    case 'get_user_wallet_balance':
        if ($method !== 'GET') {
            sendJsonResponse(false, "متد نامعتبر است؛ باید GET باشد");
        }
        
        if (empty($data['user_id'])) {
            sendJsonResponse(false, "فیلد اجباری: user_id");
        }
        
        $balance = $walletDatabase->getUserBalance($data['user_id']);
        if ($balance === false) {
            sendJsonResponse(false, "خطا در دریافت موجودی کیف پول");
        }
        
        sendJsonResponse(true, "موجودی کیف پول", [
            'balance' => $balance,
            'formatted_balance' => number_format($balance) . ' تومان'
        ]);
        break;
        
    case 'get_user_wallet_transactions':
        if ($method !== 'GET') {
            sendJsonResponse(false, "متد نامعتبر است؛ باید GET باشد");
        }
        
        if (empty($data['user_id'])) {
            sendJsonResponse(false, "فیلد اجباری: user_id");
        }
        
        $limit = isset($data['limit']) && is_numeric($data['limit']) ? min(max((int)$data['limit'], 1), 100) : 50;
        $offset = isset($data['offset']) && is_numeric($data['offset']) ? max((int)$data['offset'], 0) : 0;
        
        $transactions = $walletDatabase->getUserWalletTransactions($data['user_id'], $limit, $offset);
        if ($transactions === false) {
            sendJsonResponse(false, "خطا در دریافت تراکنشهای کیف پول");
        }
        
        // Format transactions for response
        $formattedTransactions = array_map(function($transaction) {
            return [
                'id' => $transaction['id'],
                'transaction_type' => $transaction['transaction_type'],
                'amount' => $transaction['amount'],
                'formatted_amount' => number_format($transaction['amount']) . ' تومان',
                'balance_before' => $transaction['balance_before'],
                'balance_after' => $transaction['balance_after'],
                'description' => $transaction['description'],
                'created_at' => jdate('Y/m/d H:i', strtotime($transaction['created_at']))
            ];
        }, $transactions);
        
        sendJsonResponse(true, "تراکنشهای کیف پول", [
            'transactions' => $formattedTransactions,
            'count' => count($formattedTransactions)
        ]);
        break;
        
    case 'get_user_card_to_card_transactions':
        if ($method !== 'GET') {
            sendJsonResponse(false, "متد نامعتبر است؛ باید GET باشد");
        }
        
        if (empty($data['user_id'])) {
            sendJsonResponse(false, "فیلد اجباری: user_id");
        }
        
        $limit = isset($data['limit']) && is_numeric($data['limit']) ? min(max((int)$data['limit'], 1), 100) : 50;
        $offset = isset($data['offset']) && is_numeric($data['offset']) ? max((int)$data['offset'], 0) : 0;
        
        $transactions = $walletDatabase->getUserCardToCardTransactions($data['user_id'], $limit, $offset);
        if ($transactions === false) {
            sendJsonResponse(false, "خطا در دریافت تراکنشهای کارت به کارت");
        }
        
        // Format transactions for response
        $formattedTransactions = array_map(function($transaction) {
            return [
                'id' => $transaction['id'],
                'transaction_id' => $transaction['transaction_id'],
                'source_card_number' => $transaction['source_card_number'],
                'destination_card_number' => $transaction['destination_card_number'],
                'amount' => $transaction['amount_toman'],
                'formatted_amount' => number_format($transaction['amount_toman']) . ' تومان',
                'transaction_status' => $transaction['transaction_status'],
                'tracking_code' => $transaction['tracking_code'],
                'reference_number' => $transaction['reference_number'],
                'bank_name' => $transaction['bank_name'],
                'created_at' => jdate('Y/m/d H:i', strtotime($transaction['created_at']))
            ];
        }, $transactions);
        
        sendJsonResponse(true, "تراکنشهای کارت به کارت", [
            'transactions' => $formattedTransactions,
            'count' => count($formattedTransactions)
        ]);
        break;
        
    case 'confirm_card_to_card_transaction':
        if ($method !== 'POST') {
            sendJsonResponse(false, "متد نامعتبر است؛ باید POST باشد");
        }
        
        if (empty($data['transaction_id'])) {
            sendJsonResponse(false, "فیلد اجباری: transaction_id");
        }
        
        // Note: This should be called by admin only, so we need admin authentication
        // For now, we'll assume the admin is authenticated via the token
        
        $result = $cardToCardManager->confirmTransaction($data['transaction_id'], $data['admin_id'] ?? 'admin', $data);
        
        if ($result['success']) {
            sendJsonResponse(true, $result['message']);
        } else {
            sendJsonResponse(false, $result['message']);
        }
        break;
        
    case 'reject_card_to_card_transaction':
        if ($method !== 'POST') {
            sendJsonResponse(false, "متد نامعتبر است؛ باید POST باشد");
        }
        
        if (empty($data['transaction_id']) || empty($data['reason'])) {
            sendJsonResponse(false, "فیلدهای اجباری: transaction_id, reason");
        }
        
        // Note: This should be called by admin only
        $result = $cardToCardManager->rejectTransaction($data['transaction_id'], $data['admin_id'] ?? 'admin', $data['reason']);
        
        if ($result['success']) {
            sendJsonResponse(true, $result['message']);
        } else {
            sendJsonResponse(false, $result['message']);
        }
        break;
        
    default:
        sendJsonResponse(false, "عملیات نامعتبر است");
}

sendJsonResponse(false, "عملیات نامعتبر است");
?>