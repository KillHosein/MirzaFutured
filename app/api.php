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

function getPaySetting($name) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? $res['ValuePay'] : null;
    } catch (PDOException $e) {
        return null;
    }
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

// Extract User Info
$telegramUser = isset($validatedData['user']) ? json_decode($validatedData['user'], true) : null;
$userId = $telegramUser['id'] ?? 0;

if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid User ID']);
    exit;
}

// 4. Handle Actions
$action = $input['action'] ?? 'dashboard';

// Connect to DB using $pdo from config.php
try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
         echo json_encode([
            'ok' => true,
            'is_new' => true,
            'user' => $telegramUser
        ]);
        exit;
    }

    $response = ['ok' => true];

    switch ($action) {
        case 'dashboard':
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

            $response['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'balance' => number_format($user['Balance']),
                'raw_balance' => $user['Balance'],
                'joined_at' => date('Y/m/d', $user['register']),
                'score' => $user['score'],
                'code_invitation' => $user['codeInvitation'],
            ];
            $response['stats'] = [
                'active_services' => $serviceStats['count'] ?? 0,
                'total_spent' => number_format($serviceStats['total_spent'] ?? 0),
                'unpaid_invoices' => $unpaidInvoices['count'] ?? 0
            ];
            $response['products'] = $products;
            break;

        case 'get_products':
            $stmtProducts = $pdo->query("SELECT * FROM product ORDER BY id DESC");
            $response['products'] = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'get_orders':
            // Fetch invoices
            $stmtInvoices = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id ORDER BY id DESC LIMIT 50");
            $stmtInvoices->execute([':id' => $userId]);
            $invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch Services
            $stmtServices = $pdo->prepare("SELECT * FROM service_other WHERE id_user = :id ORDER BY id DESC LIMIT 50");
            $stmtServices->execute([':id' => $userId]);
            $services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

            $response['invoices'] = $invoices;
            $response['services'] = $services;
            break;

        case 'buy_product':
            $productId = $input['product_id'] ?? 0;
            if (!$productId) {
                $response['ok'] = false;
                $response['error'] = 'Invalid Product ID';
                break;
            }

            // Check Product
            $stmtP = $pdo->prepare("SELECT * FROM product WHERE id = :id");
            $stmtP->execute([':id' => $productId]);
            $product = $stmtP->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $response['ok'] = false;
                $response['error'] = 'Product not found';
                break;
            }

            $price = $product['price'];
            
            // Check Balance
            if ($user['Balance'] >= $price) {
                // Deduct Balance
                $newBalance = $user['Balance'] - $price;
                $stmtUpd = $pdo->prepare("UPDATE user SET Balance = :bal WHERE id = :id");
                $stmtUpd->execute([':bal' => $newBalance, ':id' => $userId]);

                // Create Order (service_other) - Simplified logic
                // NOTE: In a real app, this should call the bot's order creation function to handle delivery.
                // For now, we'll create a record to simulate "Working Button"
                $stmtIns = $pdo->prepare("INSERT INTO service_other (id_user, id_product, price, status, date) VALUES (:uid, :pid, :price, 'paid', :date)");
                $stmtIns->execute([
                    ':uid' => $userId,
                    ':pid' => $productId,
                    ':price' => $price,
                    ':date' => time()
                ]);

                $response['message'] = 'خرید با موفقیت انجام شد';
                $response['new_balance'] = number_format($newBalance);
            } else {
                $response['ok'] = false;
                $response['error'] = 'موجودی کافی نیست';
            }
            break;

        case 'deposit':
            $amount = intval($input['amount'] ?? 0);
            if ($amount < 1000) {
                $response['ok'] = false;
                $response['error'] = 'حداقل مبلغ ۱۰۰۰ تومان است';
                break;
            }

            // Check PaySetting for Card
            $cardNum = getPaySetting('cardnumber');
            $cardName = getPaySetting('namecard');
            
            if ($cardNum && $cardNum !== '0') {
                 $response['card_number'] = $cardNum;
                 $response['card_name'] = $cardName;
                 
                 // Record the request
                 $randomString = bin2hex(random_bytes(5));
                 $dateacc = date('Y/m/d H:i:s');
                 
                 // Ensure table Payment_report exists (should exist)
                 $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,bottype) VALUES (:uid,:oid,:time,:price,'Unpaid','cart to cart','0 | 0', 'webapp')");
                 $stmt->execute([
                     ':uid' => $userId,
                     ':oid' => $randomString,
                     ':time' => $dateacc,
                     ':price' => $amount
                 ]);
            } else {
                 $response['ok'] = false;
                 $response['error'] = 'درگاه پرداخت تنظیم نشده است. لطفا با پشتیبانی تماس بگیرید.';
            }
            break;


        default:
            $response['ok'] = false;
            $response['error'] = 'Invalid Action';
    }

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
