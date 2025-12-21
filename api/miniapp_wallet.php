<?php
require_once '../config.php';
require_once '../function.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');

function miniappJsonResponse(bool $status, string $msg, $obj = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'msg' => $msg,
        'obj' => $obj,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function miniappGetBearerToken(array $headers): ?string
{
    if (!isset($headers['Authorization'])) {
        return null;
    }
    $raw = trim((string)$headers['Authorization']);
    if (stripos($raw, 'Bearer ') !== 0) {
        return null;
    }
    $token = trim(substr($raw, 7));
    return $token !== '' ? $token : null;
}

$headers = getallheaders();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $data = [
        'actions' => $_GET['actions'] ?? null,
        'user_id' => isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0,
        'order_id' => isset($_GET['order_id']) ? (string)$_GET['order_id'] : null,
    ];
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    miniappJsonResponse(false, 'Method invalid', [], 405);
}

if (!is_array($data)) {
    miniappJsonResponse(false, 'Data invalid', [], 400);
}

$data = sanitize_recursive($data);

$token = miniappGetBearerToken($headers);
if ($token === null) {
    miniappJsonResponse(false, 'Token invalid', [], 403);
}

$userId = isset($data['user_id']) && is_numeric($data['user_id']) ? (int)$data['user_id'] : 0;
if ($userId <= 0) {
    miniappJsonResponse(false, 'user_id invalid', [], 400);
}

$user = select('user', '*', 'id', $userId, 'select');
if (!$user || !isset($user['token']) || $user['token'] !== $token) {
    miniappJsonResponse(false, 'Token invalid', [], 403);
}

if (isset($user['User_Status']) && $user['User_Status'] === 'block') {
    miniappJsonResponse(false, 'user blocked', [], 402);
}

$action = $data['actions'] ?? null;
if (!is_string($action) || $action === '') {
    miniappJsonResponse(false, 'Action invalid', [], 400);
}

$setting = select('setting', '*', null, null, 'select');

function paySettingValue(string $name, $default = null)
{
    $row = select('PaySetting', 'ValuePay', 'NamePay', $name, 'select');
    if (!$row || !isset($row['ValuePay'])) {
        return $default;
    }
    return $row['ValuePay'];
}

function canUseGateway($value): bool
{
    if ($value === null) {
        return false;
    }
    $s = trim((string)$value);
    if ($s === '' || $s === '0' || $s === '{API_KEY}' || $s === '{domain_name}') {
        return false;
    }
    return true;
}

switch ($action) {
    case 'meta':
        if ($method !== 'GET') {
            miniappJsonResponse(false, 'Method invalid; must be GET', [], 405);
        }
        miniappJsonResponse(true, 'Successful', [
            'support_contact' => $setting['support_contact'] ?? null,
            'support_username' => $setting['support_username'] ?? null,
            'Channel_Support' => $setting['Channel_Support'] ?? null,
            'currency' => $setting['currency'] ?? 'تومان',
        ]);
        break;

    case 'methods':
        if ($method !== 'GET') {
            miniappJsonResponse(false, 'Method invalid; must be GET', [], 405);
        }

        $methods = [];

        $zarinpalMerchant = paySettingValue('merchant_zarinpal');
        if (canUseGateway($zarinpalMerchant)) {
            $methods[] = [
                'key' => 'zarinpal',
                'title' => 'زرین‌پال',
                'min' => (int)paySettingValue('minbalancezarinpal', 5000),
                'max' => (int)paySettingValue('maxbalancezarinpal', 0),
            ];
        }

        $aqayPin = paySettingValue('merchant_id_aqayepardakht');
        if (canUseGateway($aqayPin)) {
            $methods[] = [
                'key' => 'aqayepardakht',
                'title' => 'آقای‌پرداخت',
                'min' => (int)paySettingValue('minbalanceaqayepardakht', 5000),
                'max' => (int)paySettingValue('maxbalanceaqayepardakht', 0),
            ];
        }

        $plisioKey = paySettingValue('apinowpayment');
        if (canUseGateway($plisioKey)) {
            $methods[] = [
                'key' => 'plisio',
                'title' => 'ارز دیجیتال (Plisio)',
                'min' => (int)paySettingValue('minbalanceplisio', 0),
                'max' => (int)paySettingValue('maxbalanceplisio', 0),
            ];
        }

        $nowPaymentsKey = paySettingValue('marchent_tronseller');
        if (canUseGateway($nowPaymentsKey)) {
            $methods[] = [
                'key' => 'nowpayment',
                'title' => 'ارز دیجیتال (NowPayments)',
                'min' => (int)paySettingValue('minbalancenowpayment', 0),
                'max' => (int)paySettingValue('maxbalancenowpayment', 0),
            ];
        }

        $iranpayKey = paySettingValue('marchent_floypay');
        if (canUseGateway($iranpayKey)) {
            $methods[] = [
                'key' => 'iranpay1',
                'title' => 'درگاه ریالی ۱',
                'min' => (int)paySettingValue('minbalanceiranpay1', 0),
                'max' => (int)paySettingValue('maxbalanceiranpay1', 0),
            ];
        }

        miniappJsonResponse(true, 'Successful', $methods);
        break;

    case 'history':
        if ($method !== 'GET') {
            miniappJsonResponse(false, 'Method invalid; must be GET', [], 405);
        }
        global $pdo;
        $stmt = $pdo->prepare("SELECT id_order, time, price, payment_Status, Payment_Method FROM Payment_report WHERE id_user = :id_user ORDER BY time DESC LIMIT 20");
        $stmt->execute([':id_user' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        miniappJsonResponse(true, 'Successful', $rows ?: []);
        break;

    case 'status':
        if ($method !== 'GET') {
            miniappJsonResponse(false, 'Method invalid; must be GET', [], 405);
        }
        $orderId = isset($data['order_id']) ? (string)$data['order_id'] : '';
        if ($orderId === '') {
            miniappJsonResponse(false, 'order_id empty', [], 400);
        }
        $payment = select('Payment_report', '*', 'id_order', $orderId, 'select');
        if (!$payment || (string)$payment['id_user'] !== (string)$userId) {
            miniappJsonResponse(false, 'payment not found', [], 404);
        }
        miniappJsonResponse(true, 'Successful', [
            'order_id' => $payment['id_order'],
            'payment_status' => $payment['payment_Status'],
            'price' => $payment['price'],
            'method' => $payment['Payment_Method'],
            'time' => $payment['time'] ?? null,
        ]);
        break;

    case 'create':
        if ($method !== 'POST') {
            miniappJsonResponse(false, 'Method invalid; must be POST', [], 405);
        }
        $amount = isset($data['amount']) ? (int)$data['amount'] : 0;
        $methodKey = isset($data['method']) ? (string)$data['method'] : '';
        if ($amount <= 0) {
            miniappJsonResponse(false, 'amount invalid', [], 400);
        }
        if ($methodKey === '') {
            miniappJsonResponse(false, 'method invalid', [], 400);
        }

        $dateacc = date('Y/m/d H:i:s');
        $orderId = bin2hex(random_bytes(5));
        $invoice = "wallet_topup|miniapp";

        $insertPayment = function ($methodName, $decNotConfirmed, $paymentUrl) use ($pdo, $userId, $orderId, $dateacc, $amount, $invoice) {
            $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user, id_order, time, price, payment_Status, Payment_Method, id_invoice, dec_not_confirmed) VALUES (:id_user, :id_order, :time, :price, :payment_Status, :Payment_Method, :id_invoice, :dec_not_confirmed)");
            $stmt->execute([
                ':id_user' => $userId,
                ':id_order' => $orderId,
                ':time' => $dateacc,
                ':price' => $amount,
                ':payment_Status' => 'Unpaid',
                ':Payment_Method' => $methodName,
                ':id_invoice' => $invoice,
                ':dec_not_confirmed' => $decNotConfirmed,
            ]);
            miniappJsonResponse(true, 'Successful', [
                'order_id' => $orderId,
                'payment_url' => $paymentUrl,
                'amount' => $amount,
                'method' => $methodName,
            ]);
        };

        if ($methodKey === 'zarinpal') {
            $min = (int)paySettingValue('minbalancezarinpal', 5000);
            $max = (int)paySettingValue('maxbalancezarinpal', 0);
            if ($amount < $min || ($max > 0 && $amount > $max)) {
                miniappJsonResponse(false, 'amount out of range', [], 400);
            }
            $pay = createPayZarinpal($amount, $orderId);
            if (!isset($pay['data']['code']) || (int)$pay['data']['code'] !== 100) {
                $err = isset($pay['errors']) ? json_encode($pay['errors'], JSON_UNESCAPED_UNICODE) : 'gateway error';
                miniappJsonResponse(false, $err, [], 502);
            }
            $authority = (string)$pay['data']['authority'];
            $paymentUrl = "https://www.zarinpal.com/pg/StartPay/" . $authority;
            $insertPayment('zarinpal', $authority, $paymentUrl);
        }

        if ($methodKey === 'aqayepardakht') {
            $min = (int)paySettingValue('minbalanceaqayepardakht', 5000);
            $max = (int)paySettingValue('maxbalanceaqayepardakht', 0);
            if ($amount < $min || ($max > 0 && $amount > $max)) {
                miniappJsonResponse(false, 'amount out of range', [], 400);
            }
            $pay = createPayaqayepardakht($amount, $orderId);
            if (!isset($pay['status']) || $pay['status'] !== 'success' || !isset($pay['transid'])) {
                miniappJsonResponse(false, 'gateway error', [], 502);
            }
            $paymentUrl = "https://panel.aqayepardakht.ir/startpay/" . $pay['transid'];
            $insertPayment('aqayepardakht', '', $paymentUrl);
        }

        if ($methodKey === 'plisio') {
            $min = (int)paySettingValue('minbalanceplisio', 0);
            $max = (int)paySettingValue('maxbalanceplisio', 0);
            if (($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
                miniappJsonResponse(false, 'amount out of range', [], 400);
            }
            $rates = requireTronRates(['TRX', 'USD']);
            if ($rates === null) {
                miniappJsonResponse(false, 'rate unavailable', [], 503);
            }
            $trx = (float)$rates['TRX'];
            if ($trx <= 0) {
                miniappJsonResponse(false, 'rate invalid', [], 503);
            }
            $trxPrice = $amount / $trx;
            $pay = plisio($orderId, $trxPrice);
            if (isset($pay['message'])) {
                miniappJsonResponse(false, 'gateway error', [], 502);
            }
            if (!isset($pay['txn_id']) || !isset($pay['invoice_url'])) {
                miniappJsonResponse(false, 'gateway error', [], 502);
            }
            $insertPayment('plisio', (string)$pay['txn_id'], (string)$pay['invoice_url']);
        }

        if ($methodKey === 'nowpayment') {
            $min = (int)paySettingValue('minbalancenowpayment', 0);
            $max = (int)paySettingValue('maxbalancenowpayment', 0);
            if (($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
                miniappJsonResponse(false, 'amount out of range', [], 400);
            }
            $rates = requireTronRates(['USD']);
            if ($rates === null) {
                miniappJsonResponse(false, 'rate unavailable', [], 503);
            }
            $usd = (float)$rates['USD'];
            if ($usd <= 0) {
                miniappJsonResponse(false, 'rate invalid', [], 503);
            }
            $usdPrice = $amount / $usd;
            $pay = nowPayments('invoice', $usdPrice, $orderId, 'wallet_topup');
            if (!isset($pay['id']) || !isset($pay['invoice_url'])) {
                miniappJsonResponse(false, 'gateway error', [], 502);
            }
            $insertPayment('nowpayment', (string)$pay['id'], (string)$pay['invoice_url']);
        }

        if ($methodKey === 'iranpay1') {
            $min = (int)paySettingValue('minbalanceiranpay1', 0);
            $max = (int)paySettingValue('maxbalanceiranpay1', 0);
            if (($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
                miniappJsonResponse(false, 'amount out of range', [], 400);
            }
            $pay = createInvoiceiranpay1($amount, $orderId);
            if (!isset($pay['status']) || (string)$pay['status'] !== '100' || !isset($pay['payment_url_bot'])) {
                miniappJsonResponse(false, 'gateway error', [], 502);
            }
            $authority = isset($pay['Authority']) ? (string)$pay['Authority'] : '';
            $insertPayment('Currency Rial 1', $authority, (string)$pay['payment_url_bot']);
        }

        miniappJsonResponse(false, 'method invalid', [], 400);
        break;

    default:
        miniappJsonResponse(false, 'Action Invalid', [], 400);
        break;
}

