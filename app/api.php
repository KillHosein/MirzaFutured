<?php
require_once '../config.php';
require_once '../function.php';

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

    // Auto-Register if user doesn't exist
    if (!$user) {
        try {
            // Get Settings
            $stmtSetting = $pdo->query("SELECT * FROM setting LIMIT 1");
            $setting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
            
            // Default values
            $limit_usertest = $setting['limit_usertest_all'] ?? 0;
            $showcard = $setting['showcard'] ?? 'off';
            $verifystart = $setting['verifystart'] ?? 'off';
            $valueverify = ($verifystart != "onverify") ? 1 : 0;
            $date = time();
            $randomString = bin2hex(random_bytes(6));
            
            // Handle Referral
            $inviterId = 0;
            $startParam = $validatedData['start_param'] ?? '';
            
            if ($startParam) {
                $stmtInviter = $pdo->prepare("SELECT id FROM user WHERE codeInvitation = :code LIMIT 1");
                $stmtInviter->execute([':code' => $startParam]);
                $inviter = $stmtInviter->fetch(PDO::FETCH_ASSOC);
                if ($inviter) {
                    $inviterId = $inviter['id'];
                    // Update inviter stats
                    $pdo->prepare("UPDATE user SET affiliatescount = affiliatescount + 1 WHERE id = :id")->execute([':id' => $inviterId]);
                }
            }

            $sql = "INSERT IGNORE INTO user (id, step, limit_usertest, User_Status, number, Balance, pagenumber, username, agent, message_count, last_message_time, affiliates, affiliatescount, cardpayment, number_username, namecustom, register, verify, codeInvitation, pricediscount, maxbuyagent, joinchannel, score, status_cron) VALUES (:id, 'none', :limit, 'Active', 'none', '0', '1', :username, 'f', '0', '0', :affiliates, '0', :showcard, '100', 'none', :date, :verify, :code, '0', '0', '0', '0', '1')";
            
            $stmtIns = $pdo->prepare($sql);
            $stmtIns->execute([
                ':id' => $userId,
                ':limit' => $limit_usertest,
                ':username' => $telegramUser['username'] ?? '',
                ':affiliates' => $inviterId,
                ':showcard' => $showcard,
                ':date' => $date,
                ':verify' => $valueverify,
                ':code' => $randomString
            ]);
            
            // Fetch newly created user
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            // If registration fails, return error
             echo json_encode(['ok' => false, 'error' => 'Registration failed: ' . $e->getMessage()]);
             exit;
        }
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
                'unpaid_invoices' => $unpaidInvoices['count'] ?? 0,
                'referrals' => $user['affiliatescount'] ?? 0
            ];
            $response['bot_username'] = $usernamebot; // Send bot username for referral link
            $response['products'] = $products;

            // Determine active payment methods
            $methods = [];
            
            // Check Zarinpal
            $zarinpalMerchant = getPaySettingValue('merchant_zarinpal');
            if ($zarinpalMerchant && $zarinpalMerchant !== '0' && strlen($zarinpalMerchant) > 10) {
                $methods[] = ['id' => 'zarinpal', 'name' => 'پرداخت ریالی (زرین‌پال)'];
            }
            
            // Check NowPayments
            $nowPaymentsKey = getPaySettingValue('marchent_tronseller');
            if ($nowPaymentsKey && $nowPaymentsKey !== '0') {
                $methods[] = ['id' => 'nowpayments', 'name' => 'پرداخت ارزی (NowPayments)'];
            }
            
            // Check Card to Card
            $cardNum = getPaySettingValue('cardnumber');
            if ($cardNum && $cardNum !== '0') {
                $methods[] = ['id' => 'card', 'name' => 'کارت به کارت'];
            }
            
            $response['payment_methods'] = $methods;
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
            $method = $input['method'] ?? 'zarinpal';

            if ($amount < 1000) {
                $response['ok'] = false;
                $response['error'] = 'حداقل مبلغ ۱۰۰۰ تومان است';
                break;
            }

            if ($method === 'nowpayments') {
                // NowPayments
                $nowPaymentsKey = getPaySettingValue('marchent_tronseller');
                if (!$nowPaymentsKey || $nowPaymentsKey === '0') {
                     $response['ok'] = false;
                     $response['error'] = 'درگاه پرداخت ارزی فعال نیست';
                     break;
                }
            
                // Convert Amount
                $rates = tronratee();
                if (!$rates['ok']) {
                     $response['ok'] = false;
                     $response['error'] = 'خطا در دریافت نرخ ارز';
                     break;
                }
                $usdRate = $rates['result']['USD'];
                if (!$usdRate) {
                     $response['ok'] = false;
                     $response['error'] = 'خطا در محاسبه نرخ ارز';
                     break;
                }

                $usdAmount = round($amount / $usdRate, 2);
                if ($usdAmount < 1) {
                    // Force min 1 USD or handled by NP
                    // Let's just warn if very low
                }
            
                $orderId = bin2hex(random_bytes(10));
                $desc = "Charge Account: $userId";
                
                // Call nowPayments
                $npResult = nowPayments('invoice', $usdAmount, $orderId, $desc);
                
                if (isset($npResult['invoice_url'])) {
                    $paymentUrl = $npResult['invoice_url'];
                    $npId = $npResult['id'];
                    
                    $dateacc = date('Y/m/d H:i:s');
                    $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user, id_order, time, price, payment_Status, Payment_Method, id_invoice, bottype, dec_not_confirmed) VALUES (:uid, :oid, :time, :price, 'Unpaid', 'nowpayment', '0 | 0', 'webapp', :auth)");
                    $stmt->execute([
                        ':uid' => $userId,
                        ':oid' => $orderId,
                        ':time' => $dateacc,
                        ':price' => $amount, // Store Toman amount
                        ':auth' => $npId
                    ]);
                    
                    $response['ok'] = true;
                    $response['url'] = $paymentUrl;
                } else {
                    $response['ok'] = false;
                    $response['error'] = 'NowPayments Error: ' . ($npResult['message'] ?? 'Unknown');
                }
                break;
            }

            if ($method === 'card') {
                // Card to Card
                $cardNum = getPaySettingValue('cardnumber');
                $cardName = getPaySettingValue('namecard');
                
                if ($cardNum && $cardNum !== '0') {
                     $response['card_number'] = $cardNum;
                     $response['card_name'] = $cardName;
                     
                     $randomString = bin2hex(random_bytes(5));
                     $dateacc = date('Y/m/d H:i:s');
                     
                     $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,bottype) VALUES (:uid,:oid,:time,:price,'Unpaid','cart to cart','0 | 0', 'webapp')");
                     $stmt->execute([
                         ':uid' => $userId,
                         ':oid' => $randomString,
                         ':time' => $dateacc,
                         ':price' => $amount
                     ]);
                } else {
                     $response['ok'] = false;
                     $response['error'] = 'اطلاعات کارت تنظیم نشده است';
                }
                break;
            }

            // Default: Zarinpal
            $zarinpalMerchant = getPaySettingValue('merchant_zarinpal');
            
            if ($zarinpalMerchant && $zarinpalMerchant !== '0' && strlen($zarinpalMerchant) > 10) {
                // Initiate Zarinpal Payment
                $orderId = bin2hex(random_bytes(10));
                $callbackUrl = "https://" . $domainhosts . "/payment/zarinpal.php";
                
                $data = [
                    "merchant_id" => $zarinpalMerchant,
                    "currency" => "IRT",
                    "amount" => $amount,
                    "callback_url" => $callbackUrl,
                    "description" => "Charge Account: " . $userId,
                    "metadata" => [
                        "order_id" => $orderId
                    ]
                ];

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/request.json',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ),
                ));

                $result = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);

                if ($err) {
                    $response['ok'] = false;
                    $response['error'] = 'Curl Error: ' . $err;
                } else {
                    $json = json_decode($result, true);
                    if (isset($json['data']['code']) && $json['data']['code'] == 100) {
                        $authority = $json['data']['authority'];
                        $paymentUrl = 'https://www.zarinpal.com/pg/StartPay/' . $authority;
                        
                        // Insert into DB
                        $dateacc = date('Y/m/d H:i:s');
                        $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user, id_order, time, price, payment_Status, Payment_Method, id_invoice, bottype, dec_not_confirmed) VALUES (:uid, :oid, :time, :price, 'Unpaid', 'zarinpal', '0 | 0', 'webapp', :auth)");
                        $stmt->execute([
                            ':uid' => $userId,
                            ':oid' => $orderId,
                            ':time' => $dateacc,
                            ':price' => $amount,
                            ':auth' => $authority
                        ]);
                        
                        $response['ok'] = true;
                        $response['url'] = $paymentUrl;
                    } else {
                         // Fallback or Error
                         $response['ok'] = false;
                         $response['error'] = 'Zarinpal Error: ' . ($json['errors']['message'] ?? 'Unknown error');
                    }
                }
                break;
            }

            // Fallback to Card if Zarinpal failed or not set
            $cardNum = getPaySettingValue('cardnumber');
            $cardName = getPaySettingValue('namecard');
            
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
