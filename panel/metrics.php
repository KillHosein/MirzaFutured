<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if(!isset($_SESSION['user'])){ echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }
try{
  $users = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
  $orders = (int)$pdo->query("SELECT COUNT(*) FROM invoice")->fetchColumn();
  $payments = (int)$pdo->query("SELECT COUNT(*) FROM Payment_report")->fetchColumn();
  $latestOrders = $pdo->query("SELECT id_invoice, username, Status, time_sell FROM invoice ORDER BY time_sell DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
  $latestPayments = $pdo->query("SELECT id_order, id_user, payment_Status, time FROM Payment_report ORDER BY time DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'counts'=>['users'=>$users,'orders'=>$orders,'payments'=>$payments],'latest'=>['orders'=>$latestOrders,'payments'=>$latestPayments]]);
}catch(Exception $e){ echo json_encode(['ok'=>false,'error'=>'db_error']); }
