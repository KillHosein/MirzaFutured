<?php
session_start();
require_once '../config.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if(!isset($_SESSION["user"]) || !$result){
    header('Location: login.php');
    exit;
}

/* ===== Filters ===== */
$where = [];
$params = [];

if(!empty($_GET['status'])){
    $where[] = "LOWER(User_Status) = :st";
    $params[':st'] = strtolower($_GET['status']);
}
if(!empty($_GET['agent'])){
    $where[] = "agent = :ag";
    $params[':ag'] = $_GET['agent'];
}
if(!empty($_GET['q'])){
    $params[':q'] = "%{$_GET['q']}%";
    $where[] = "(CAST(id AS CHAR) LIKE :q OR username LIKE :q OR number LIKE :q)";
}

$sql = "SELECT * FROM user";
if($where) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " ORDER BY id DESC";

$q = $pdo->prepare($sql);
$q->execute($params);
$listusers = $q->fetchAll(PDO::FETCH_ASSOC);

/* ===== CSV Export ===== */
if(isset($_GET['export']) && $_GET['export']==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users-'.date('Y-m-d').'.csv');
    $out = fopen('php://output','w');
    fputcsv($out,['ID','Username','Number','Balance','Affiliates','Status','Agent']);
    foreach($listusers as $u){
        fputcsv($out,[
            $u['id'],$u['username'],$u['number'],$u['Balance'],
            $u['affiliatescount'],strtolower($u['User_Status']),$u['agent']
        ]);
    }
    fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>پنل مدیریت کاربران</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="css/bootstrap.min.css" rel="stylesheet">
<link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet">

<style>
body{background:#f4f6f9;font-family:Vazirmatn}
.panel{border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.06);border:none}
.panel-heading{background:linear-gradient(135deg,#2563eb,#1e40af);color:#fff;font-weight:700}
.form-control,.btn{border-radius:10px}
.table{background:#fff;border-radius:12px;overflow:hidden}
.table thead th{background:#f1f5f9}
.table tbody tr:hover{background:#f9fafb}
.action-toolbar{display:flex;flex-wrap:wrap;gap:6px;background:#fff;padding:12px;margin:12px;border-radius:12px}
.status-badge{padding:4px 12px;border-radius:999px;font-weight:600;font-size:12px}
.status-active{background:#dcfce7;color:#15803d}
.status-block{background:#fee2e2;color:#b91c1c}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:15px}
.stat-card{background:#fff;border-radius:14px;padding:14px;text-align:center}
.stat-value{font-size:22px;font-weight:700}
@media print{.action-toolbar,form,header{display:none}}
</style>
</head>

<body>
<section id="container">
<?php include "header.php"; ?>

<section id="main-content">
<section class="wrapper">

<section class="panel">
<header class="panel-heading">لیست کاربران</header>

<div class="panel-body">
<form class="form-inline" method="get">
<input class="form-control" name="q" placeholder="جستجو" value="<?=htmlspecialchars($_GET['q']??'')?>">
<select name="status" class="form-control">
<option value="">همه وضعیت‌ها</option>
<option value="active">فعال</option>
<option value="block">مسدود</option>
</select>
<button class="btn btn-primary">فیلتر</button>
<a href="users.php" class="btn btn-default">ریست</a>
<a href="?<?=http_build_query(array_merge($_GET,['export'=>'csv']))?>" class="btn btn-success">CSV</a>
</form>
</div>

<?php
$total=count($listusers);
$active=$block=0;
foreach($listusers as $u){
    strtolower($u['User_Status'])==='active'?$active++:$block++;
}
?>
<div class="stat-grid">
<div class="stat-card"><div>کل کاربران</div><div class="stat-value"><?=$total?></div></div>
<div class="stat-card"><div>فعال</div><div class="stat-value"><?=$active?></div></div>
<div class="stat-card"><div>مسدود</div><div class="stat-value"><?=$block?></div></div>
</div>

<table class="table">
<thead>
<tr>
<th>ID</th>
<th>نام کاربری</th>
<th>شماره</th>
<th>موجودی</th>
<th>زیرمجموعه</th>
<th>وضعیت</th>
<th>مدیریت</th>
</tr>
</thead>
<tbody>
<?php foreach($listusers as $u):
$status=strtolower($u['User_Status']);
?>
<tr>
<td><?=$u['id']?></td>
<td><?=$u['username']?></td>
<td><?=$u['number']=='none'?'—':$u['number']?></td>
<td><?=number_format($u['Balance'])?></td>
<td><?=$u['affiliatescount']?></td>
<td><span class="status-badge status-<?=$status?>"><?=$status=='active'?'فعال':'مسدود'?></span></td>
<td><a class="btn btn-sm btn-info" href="user.php?id=<?=$u['id']?>">مدیریت</a></td>
</tr>
<?php endforeach;?>
</tbody>
</table>

</section>
</section>
</section>
</section>

<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
