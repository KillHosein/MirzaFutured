<?php
require_once '../config.php';
require_once '../function.php';
require_once '../panels.php';
require_once '../jdf.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');

$ManagePanel = new ManagePanel();

function extJsonResponse(bool $status, string $msg, $obj = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'msg' => $msg,
        'obj' => $obj,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function extGetBearerToken(array $headers): ?string
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

if ($method !== 'GET') {
    extJsonResponse(false, 'Method invalid; must be GET', [], 405);
}

$data = [
    'actions' => $_GET['actions'] ?? null,
    'user_id' => isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0,
    'username' => isset($_GET['username']) ? (string)$_GET['username'] : null,
];

$data = sanitize_recursive($data);

$token = extGetBearerToken($headers);
if ($token === null) {
    extJsonResponse(false, 'Token invalid', [], 403);
}

$userId = $data['user_id'];
if ($userId <= 0) {
    extJsonResponse(false, 'user_id invalid', [], 400);
}

$user = select('user', '*', 'id', $userId, 'select');
if (!$user || !isset($user['token']) || $user['token'] !== $token) {
    extJsonResponse(false, 'Token invalid', [], 403);
}

if (isset($user['User_Status']) && $user['User_Status'] === 'block') {
    extJsonResponse(false, 'user blocked', [], 402);
}

$action = $data['actions'];
if (!is_string($action) || $action === '') {
    extJsonResponse(false, 'Action invalid', [], 400);
}

switch ($action) {
    case 'service_detail':
        $username = isset($data['username']) ? trim((string)$data['username']) : '';
        if ($username === '') {
            extJsonResponse(false, 'username invalid', [], 400);
        }

        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :user_id AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND username = :username LIMIT 1");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            extJsonResponse(false, 'Service Not Found', [], 404);
        }

        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);

        $data_limit = isset($DataUserOut['data_limit']) ? ($DataUserOut['data_limit'] / pow(1024, 3)) : 0;
        $used_Traffic = isset($DataUserOut['used_traffic']) ? ($DataUserOut['used_traffic'] / pow(1024, 3)) : 0;
        $remaining_traffic = isset($DataUserOut['data_limit'], $DataUserOut['used_traffic']) ? (($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) / pow(1024, 3)) : 0;

        $config = [];
        if ($panel && isset($panel['type'])) {
            if (in_array($panel['type'], ['marzban', 'marzneshin', 'alireza_single', 'x-ui_single', 'hiddify', 'eylanpanel'], true)) {
                if (($panel['sublink'] ?? '') === 'onsublink' && isset($DataUserOut['subscription_url'])) {
                    $config[] = ['type' => 'link', 'value' => $DataUserOut['subscription_url']];
                }
                if (($panel['config'] ?? '') === 'onconfig' && isset($DataUserOut['links'])) {
                    $config[] = ['type' => 'config', 'value' => $DataUserOut['links']];
                }
            } elseif ($panel['type'] === 'WGDashboard' && isset($DataUserOut['subscription_url'])) {
                $config[] = [
                    'type' => 'file',
                    'value' => $DataUserOut['subscription_url'],
                    'filename' => ($panel['inboundid'] ?? 'wg') . "_" . $invoice['id_user'] . "_" . $invoice['id_invoice'] . ".config",
                ];
            } elseif (in_array($panel['type'], ['mikrotik', 'ibsng'], true) && isset($DataUserOut['password'])) {
                $config[] = ['type' => 'password', 'value' => $DataUserOut['password']];
            }
        }

        $lastupdate = null;
        if (isset($DataUserOut['sub_updated_at']) && $DataUserOut['sub_updated_at'] !== null) {
            try {
                $dateTime = new DateTime((string)$DataUserOut['sub_updated_at'], new DateTimeZone('UTC'));
                $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
                $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
            } catch (Exception $e) {
                $lastupdate = null;
            }
        }

        $online = $DataUserOut['online_at'] ?? null;
        if ($online === 'online') {
            $online = 'آنلاین';
        } elseif ($online === 'offline') {
            $online = 'آفلاین';
        } elseif ($online !== null) {
            $online = jdate('Y/m/d H:i:s', strtotime((string)$online));
        } else {
            $online = 'متصل نشده';
        }

        $expirationDate = isset($DataUserOut['expire']) && $DataUserOut['expire'] ? jdate('Y/m/d', (int)$DataUserOut['expire']) : 'نامحدود';

        extJsonResponse(true, 'Successful', [
            'status' => $DataUserOut['status'] ?? null,
            'username' => $DataUserOut['username'] ?? $invoice['username'],
            'product_name' => $invoice['name_product'] ?? null,
            'total_traffic_gb' => round($data_limit, 2),
            'used_traffic_gb' => round($used_Traffic, 2),
            'remaining_traffic_gb' => round($remaining_traffic, 2),
            'expiration_time' => $expirationDate,
            'last_subscription_update' => $lastupdate,
            'online_at' => $online,
            'service_output' => $config,
        ]);
        break;

    default:
        extJsonResponse(false, 'Action Invalid', [], 400);
        break;
}

