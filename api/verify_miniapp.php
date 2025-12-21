<?php
require_once '../config.php';
require_once '../function.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');

function verifyJsonResponse(bool $status, string $msg, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'status' => $status,
        'msg' => $msg,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function verifyTelegramInitData(string $initData): array
{
    global $APIKEY;

    $decoded_query = html_entity_decode($initData);
    parse_str($decoded_query, $parsed);

    if (!is_array($parsed) || !isset($parsed['hash']) || !isset($parsed['user'])) {
        return ['ok' => false, 'user_id' => null];
    }

    $receivedHash = (string)$parsed['hash'];
    unset($parsed['hash']);

    $dataCheckArray = [];
    foreach ($parsed as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $dataCheckArray[] = $key . '=' . $value;
    }
    sort($dataCheckArray);
    $dataCheckString = implode("\n", $dataCheckArray);

    $secretKey = hash_hmac('sha256', $APIKEY, 'WebAppData', true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
    $valid = hash_equals($calculatedHash, $receivedHash);

    $userJson = json_decode((string)$parsed['user'], true);
    $userId = is_array($userJson) && isset($userJson['id']) ? (int)$userJson['id'] : null;

    return ['ok' => $valid, 'user_id' => $userId];
}

$raw = file_get_contents('php://input');
if ($raw === false) {
    verifyJsonResponse(false, 'data invalid', [], 400);
}

$raw = trim($raw);
if ($raw === '') {
    verifyJsonResponse(false, 'data invalid', [], 400);
}

$initData = $raw;
if ((str_starts_with($raw, '"') && str_ends_with($raw, '"')) || (str_starts_with($raw, '{') && str_ends_with($raw, '}'))) {
    $decoded = json_decode($raw, true);
    if (is_string($decoded)) {
        $initData = $decoded;
    } elseif (is_array($decoded) && isset($decoded['initData']) && is_string($decoded['initData'])) {
        $initData = $decoded['initData'];
    }
}

$initData = (string)$initData;
if ($initData === '') {
    verifyJsonResponse(false, 'data invalid', [], 400);
}

$result = verifyTelegramInitData($initData);
if (!$result['ok'] || !$result['user_id']) {
    verifyJsonResponse(false, 'unauthorized', ['token' => null], 403);
}

$token = bin2hex(random_bytes(20));
update('user', 'token', $token, 'id', $result['user_id']);

verifyJsonResponse(true, 'Successful', ['token' => $token], 200);

