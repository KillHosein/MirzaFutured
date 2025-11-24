<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';


$q = $pdo->prepare("SELECT * FROM admin WHERE username=:u");
$q->bindParam(':u', $_SESSION['user'], PDO::PARAM_STR);
$q->execute();
$adminRow = $q->fetch(PDO::FETCH_ASSOC);
if( !isset($_SESSION["user"]) || !$adminRow ){
    header('Location: login.php');
    return;
}

$statuses = [
    'unpaid' => ['label' => 'در انتظار پرداخت', 'color' => '#f59e0b'],
    'active' => ['label' => 'فعال', 'color' => '#10b981'],
    'disabledn' => ['label' => 'ناموجود در پنل', 'color' => '#6b7280'],
    'end_of_time' => ['label' => 'پایان زمان', 'color' => '#ef4444'],
    'end_of_volume' => ['label' => 'پایان حجم', 'color' => '#3b82f6'],
    'sendedwarn' => ['label' => 'هشدار حجم و زمان', 'color' => '#8b5cf6'],
    'send_on_hold' => ['label' => 'در انتظار اتصال', 'color' => '#f97316'],
    'removebyuser' => ['label' => 'حذف شده توسط کاربر', 'color' => '#9ca3af']
];

$where = [];
$params = [];
if(!empty($_GET['status'])){
    $where[] = "Status = :status";
    $params[':status'] = $_GET['status'];
}
if(!empty($_GET['product'])){
    $where[] = "name_product = :product";
    $params[':product'] = $_GET['product'];
}
if(!empty($_GET['location'])){
    $where[] = "Service_location = :loc";
    $params[':loc'] = $_GET['location'];
}
if(!empty($_GET['q'])){
    $searchTerm = '%' . $_GET['q'] . '%';
    $where[] = "(id_user LIKE :q OR id_invoice LIKE :q OR username LIKE :q)";
    $params[':q'] = $searchTerm;
}
if(!empty($_GET['from']) && strtotime($_GET['from'])){
    $where[] = "time_sell >= :fromTs";
    $params[':fromTs'] = strtotime($_GET['from']);
}
if(!empty($_GET['to']) && strtotime($_GET['to'])){
    $where[] = "time_sell <= :toTs";
    $params[':toTs'] = strtotime($_GET['to'].' 23:59:59');
}

$sql = "SELECT * FROM invoice";
if(!empty($where)){
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY time_sell DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listinvoice = $stmt->fetchAll();

if(isset($_GET['export']) && $_GET['export'] === 'csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=invoices-' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID User','Invoice ID','Username','Location','Product','Time','Price','Status']);
    foreach($listinvoice as $row){
        $time = is_numeric($row['time_sell']) ? jdate('Y/m/d H:i:s', $row['time_sell']) : $row['time_sell'];
        $price = $row['price_product'] == 0 ? 'رایگان' : number_format($row['price_product']);
        $status = isset($statuses[$row['Status']]) ? $statuses[$row['Status']]['label'] : $row['Status'];
        fputcsv($output, [
            $row['id_user'],
            $row['id_invoice'],
            $row['username'],
            $row['Service_location'],
            $row['name_product'],
            $time,
            $price,
            $status
        ]);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="Mosaddek">
    <meta name="keyword" content="FlatLab, Dashboard, Bootstrap, Admin, Template, Theme, Responsive, Fluid, Retina">
    <link rel="shortcut icon" href="img/favicon.html">

    <title>پنل مدیریت ربات میرزا</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-reset.css" rel="stylesheet">
    <!--external css-->
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    
    <!-- Custom styles for this template -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 tooltipss and media queries -->
    <!--[if lt IE 9]>
      <script src="js/html5shiv.js"></script>
      <script src="js/respond.min.js"></script>
    <![endif]-->
  </head>


<body>

    <section id="container" class="">
<?php include("header.php");
?>
        <!--main content start-->
        <section id="main-content">
            <section class="wrapper">
                <!-- page start-->
                <div class="row">
                    <div class="col-lg-12">
                        <section class="panel">
                            <header class="panel-heading">جستجوی پیشرفته</header>
                            <div class="panel-body">
                                <form class="form-inline" role="form" method="get">
                                    <div class="form-group" style="margin-left:8px;">
                                        <input type="text" class="form-control" name="q" placeholder="آیدی کاربر/سفارش یا نام کاربری" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <label style="margin-left:6px;">از تاریخ</label>
                                        <input type="date" class="form-control" name="from" value="<?php echo isset($_GET['from']) ? htmlspecialchars($_GET['from']) : ''; ?>">
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <label style="margin-left:6px;">تا تاریخ</label>
                                        <input type="date" class="form-control" name="to" value="<?php echo isset($_GET['to']) ? htmlspecialchars($_GET['to']) : ''; ?>">
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <select name="status" class="form-control">
                                            <option value="">همه وضعیت‌ها</option>
                                            <?php foreach($statuses as $key => $val): ?>
                                                <option value="<?php echo $key; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] === $key) ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <input type="text" class="form-control" name="product" placeholder="نام محصول" value="<?php echo isset($_GET['product']) ? htmlspecialchars($_GET['product']) : ''; ?>">
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <input type="text" class="form-control" name="location" placeholder="لوکیشن سرویس" value="<?php echo isset($_GET['location']) ? htmlspecialchars($_GET['location']) : ''; ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">فیلتر</button>
                                    <a href="invoice.php" class="btn btn-default">پاک کردن</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">خروجی CSV</a>
                                </form>
                            </div>
                        </section>
                        <section class="panel">
                            <header class="panel-heading">لیست سفارشات</header>
                            <table class="table table-striped border-top" id="sample_1">
                                <thead>
                                    <tr>
                                        <th style="width: 8px;">
                                            <input type="checkbox" class="group-checkable" data-set="#sample_1 .checkboxes" /></th>
                                        <th class="hidden-phone">آیدی عددی</th>
                                        <th class="hidden-phone">شناسه سفارش</th>
                                        <th>نام کاربری کانفیگ</th>
                                        <th class="hidden-phone">لوکیشن سرویس</th>
                                        <th class="hidden-phone">نام محصول</th>
                                        <th class="hidden-phone">تاریخ سفارش</th>
                                        <th class="hidden-phone">قیمت سفارش</th>
                                        <th class="hidden-phone">وضعیت سفارش</th>
                                    </tr>
                                </thead>
                                <tbody> <?php
                                foreach($listinvoice as $list){
                                    $timeFmt = intval($list['time_sell']) ? jdate('Y/m/d |  H:i:s',$list['time_sell']) : $list['time_sell'];
                                    $priceFmt = ($list['price_product'] == 0) ? "رایگان" : number_format($list['price_product']);
                                    $status_label = isset($statuses[$list['Status']]) ? $statuses[$list['Status']]['label'] : $list['Status'];
                                    $status_color = isset($statuses[$list['Status']]) ? $statuses[$list['Status']]['color'] : '#999';
                                    echo "<tr class=\"odd gradeX\">\n                                        <td>\n                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"1\" /></td>\n                                        <td>{$list['id_user']}</td>\n                                        <td class=\"hidden-phone\">{$list['id_invoice']}</td>\n                                        <td class=\"hidden-phone\">{$list['username']}</td>\n                                        <td class=\"hidden-phone\">{$list['Service_location']}</td>\n                                        <td class=\"hidden-phone\">{$list['name_product']}</td>\n                                        <td class=\"hidden-phone time_Sell\">{$timeFmt}</td>\n                                        <td class=\"hidden-phone\">{$priceFmt}</td>\n                                        <td class=\"hidden-phone\"><span class=\"badge\" style=\"background-color:{$status_color}\">{$status_label}</span></td>\n                                    </tr>";
                                }
                                    ?>
                                </tbody>
                            </table>
                        </section>
                    </div>
                </div>
                <!-- page end-->
            </section>
        </section>
        <!--main content end-->
    </section>

    <!-- js placed at the end of the document so the pages load faster -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.scrollTo.min.js"></script>
    <script src="js/jquery.nicescroll.js" type="text/javascript"></script>
    <script type="text/javascript" src="assets/data-tables/jquery.dataTables.js"></script>
    <script type="text/javascript" src="assets/data-tables/DT_bootstrap.js"></script>


    <!--common script for all pages-->
    <script src="js/common-scripts.js"></script>

    <!--script for this page only-->
    <script src="js/dynamic-table.js"></script>


</body>
</html>
