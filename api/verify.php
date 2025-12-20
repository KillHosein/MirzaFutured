<?php

require_once '../config.php';
require_once '../function.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');


$method = $_SERVER['REQUEST_METHOD'];

function datevalid($data_unsafe)
{
    global $APIKEY;
    if (!isset($data_unsafe) || !is_string($data_unsafe) || trim($data_unsafe) === '') {
        return [false, null, null];
    }
    $decoded_query = html_entity_decode($data_unsafe);
    parse_str($decoded_query, $initData);
    if (!isset($initData['hash']) || !isset($initData['user'])) {
        return [false, null, null];
    }
    $userObj = json_decode($initData['user'], true);
    if (!is_array($userObj) || !isset($userObj['id'])) {
        return [false, null, null];
    }
    $userId = (int) $userObj['id'];
    $receivedHash = $initData['hash'];
    unset($initData['hash']);
    $dataCheckArray = [];
    foreach ($initData as $key => $value) {
        if (!empty($value)) {
            $dataCheckArray[] = "$key=$value";
        }
    }
    sort($dataCheckArray);
    $dataCheckString = implode("\n", $dataCheckArray);
    $secretKey = hash_hmac('sha256', $APIKEY, 'WebAppData', true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
    $valid_check = hash_equals($calculatedHash, $receivedHash);

    $authDate = isset($initData['auth_date']) ? (int) $initData['auth_date'] : 0;
    if ($authDate > 0 && (time() - $authDate) > 86400) {
        return [false, null, $userId];
    }

    if (!$valid_check) {
        return [false, null, $userId];
    }

    $randomString = bin2hex(random_bytes(20));
    $user = select("user", "*", "id", $userId, "select");
    if (!$user) {
        $setting = select("setting", "*");
        $valueverify = ($setting && isset($setting['verifystart']) && $setting['verifystart'] != "onverify") ? 1 : 0;
        $codeInvitation = bin2hex(random_bytes(6));
        $date = time();
        $username = isset($userObj['username']) ? $userObj['username'] : '';
        $showcard = $setting && isset($setting['showcard']) ? $setting['showcard'] : 0;
        $limit_usertest_all = $setting && isset($setting['limit_usertest_all']) ? $setting['limit_usertest_all'] : 0;
        $stmt = $GLOBALS['pdo']->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron,token) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','0','1',:token)");
        $stmt->bindValue(':from_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_usertest_all', $limit_usertest_all);
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':showcard', $showcard);
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':verifycode', $valueverify);
        $stmt->bindValue(':codeInvitation', $codeInvitation);
        $stmt->bindValue(':token', $randomString);
        $stmt->execute();
    } else {
        if (isset($userObj['username']) && is_string($userObj['username']) && $userObj['username'] !== '' && $user['username'] !== $userObj['username']) {
            update("user", "username", $userObj['username'], "id", $userId);
        }
        update("user", "token", $randomString, "id", $userId);
    }

    return [true, $randomString, $userId];
}

$payload = json_decode(file_get_contents("php://input"), true);
if (!isset($payload)) {
    echo json_encode([
        'status' => false,
        'msg' => "data invalid",
        'obj' => []
    ]);
    return;
}
$initData = null;
if (is_string($payload)) {
    $initData = $payload;
} elseif (is_array($payload) && isset($payload['initData']) && is_string($payload['initData'])) {
    $initData = $payload['initData'];
}

$initData = is_string($initData) ? htmlspecialchars($initData, ENT_QUOTES, 'UTF-8') : null;
[$ok, $token, $userId] = datevalid($initData);
if (!$ok) {
    http_response_code(403);
    echo json_encode([
        'status' => false,
        'msg' => "initData invalid",
        'obj' => [
            'token' => null,
            'user_id' => $userId
        ]
    ]);
    return;
}

echo json_encode([
    'status' => true,
    'msg' => "ok",
    'obj' => [
        'token' => $token,
        'user_id' => $userId
    ]
]);
