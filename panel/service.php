<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';


$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    $query = $pdo->prepare("SELECT * FROM service_other");
    $query->execute();
    $listinvoice = $query->fetchAll();
if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    return;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
    <link href="assets/jquery-easy-pie-chart/jquery.easy-pie-chart.css" rel="stylesheet" type="text/css" media="screen"/>
    <link rel="stylesheet" href="css/owl.carousel.css" type="text/css">
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
        <section class="wrapper content-template">
                <!-- page start-->
                <div class="row">
                    <div class="col-lg-12">
                        <section class="panel">
                            <header class="panel-heading panel-heading--modern">
                                <div class="panel-heading-main">
                                    <span class="panel-heading-icon">
                                        <i class="icon-wrench"></i>
                                    </span>
                                    <div class="panel-heading-text">
                                        <div class="panel-heading-title">لیست خدمات های انجام شده</div>
                                        <div class="panel-heading-subtitle">پیگیری عملیات تمدید، حجم اضافه و تغییرات روی سرویس‌ها</div>
                                    </div>
                                </div>
                            </header>
                            <?php $total = count($listinvoice); ?>
                            <?php if(!$total){ ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="icon-wrench"></i>
                                    </div>
                                    <h3>خدمتی یافت نشد</h3>
                                    <p>هنوز خدمتی ثبت نشده است یا نتایج با فیلترهای فعلی منطبق نیستند.</p>
                                </div>
                            <?php } ?>
                            <table class="table table-striped border-top" id="sample_1">
                                <thead>
                                    <tr>
                                        <th style="width: 8px;">
                                            <input type="checkbox" class="group-checkable" data-set="#sample_1 .checkboxes" /></th>
                                        <th class="hidden-phone">شناسه</th>
                                        <th class="hidden-phone">آیدی عددی</th>
                                        <th>نام کاربری کانفیگ</th>
                                        <th class="hidden-phone">تاریخ سفارش</th>
                                        <th class="hidden-phone">قیمت خدمت </th>
                                        <th class="hidden-phone">خدمت انجام شده</th>
                                    </tr>
                                </thead>
                                <tbody> <?php
                                if($total){
                                foreach($listinvoice as $list){
                                    $list['time'] = jdate('Y/m/d |  H:i:s',$list['time']);
                                    $list['type'] = [
                                        'extend_user' => "تمدید",
                                        'extend_user_by_admin' => 'تمدید شده توسط ادمین',
                                        'extra_user' => "حجم اضافه",
                                        "extra_time_user" => "زمان اضافه",
                                        "transfertouser" => "انتقال به حساب دیگر",
                                        "extends_not_user" => "تمدید از نوع نبودن یوزر در لیست",
                                        "change_location" => "تغییر لوکیشن"
                                        
                                        ][$list['type']];
                                   echo "<tr class=\"odd gradeX\">
                                        <td>
                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"1\" /></td>
                                        <td>{$list['id']}</td>
                                        <td>{$list['id_user']}</td>
                                        <td class=\"hidden-phone\">{$list['username']}</td>
                                        <td class=\"hidden-phone time_Sell\">{$list['time']}</td>
                                        <td class=\"hidden-phone\">{$list['price']}</td>
                                        <td class=\"hidden-phone\">{$list['type']}</td>
                                    </tr>";
                                }
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
