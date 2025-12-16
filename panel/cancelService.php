<?php
session_start();
require_once '../config.php';
require_once '../function.php';
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    return;
    }
    $query = $pdo->prepare("SELECT * FROM cancel_service");
    $query->execute();
    $listcencel = $query->fetchAll();
if($_GET['removeid'] && $_GET['removeid']){
    $stmt = $connect->prepare("DELETE FROM cancel_service WHERE id = ?");
    $stmt->bind_param("s", $_GET['removeid']);
    $stmt->execute();
    header("Location: cancelService.php");
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
                            <header class="panel-heading">لیست درخواست های حذف</header>
                                <section class="panel">
                                <div class="action-toolbar sticky" style="margin-top:8px;">
                                    <a href="cancelService.php" class="btn btn-default" id="cancelRefresh"><i class="icon-refresh"></i> بروزرسانی</a>
                                    <input type="text" id="cancelQuickSearch" class="form-control" placeholder="جستجوی سریع در جدول" style="max-width:220px;">
                                    <a href="#" class="btn btn-default tooltips" id="cancelSelectVisible" data-original-title="انتخاب همه ردیف‌های قابل‌مشاهده" aria-label="انتخاب همه"><i class="icon-check"></i> انتخاب همه نمایش‌داده‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="cancelInvertSelection" data-original-title="معکوس کردن وضعیت انتخاب ردیف‌ها" aria-label="معکوس انتخاب"><i class="icon-retweet"></i> معکوس انتخاب‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="cancelClearSelection" data-original-title="لغو انتخاب همه ردیف‌ها" aria-label="لغو انتخاب"><i class="icon-remove"></i> لغو انتخاب</a>
                                    <span id="cancelSelCount" class="sel-count">انتخاب‌ها: 0</span>
                                    <a href="#" class="btn btn-danger" id="cancelRemoveBulk"><i class="icon-trash"></i> حذف گروهی</a>
                                    <a href="#" class="btn btn-default" id="cancelPrint"><i class="icon-print"></i> چاپ</a>
                                </div>
                        </section>
                            <table class="table table-striped border-top" id="sample_1">
                                <thead>
                                    <tr>
                                        <th style="width: 8px;">
                                            <input type="checkbox" class="group-checkable" data-set="#sample_1 .checkboxes" /></th>
                                        <th class="hidden-phone">شناسه</th>
                                        <th class="hidden-phone">آیدی عددی کاربر</th>
                                        <th class="hidden-phone">نام کاربری سرویس</th>
                                        <th>توضیحات</th>
                                        <th class="hidden-phone">وضعیت</th>
                                        <th class="hidden-phone">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody> <?php
                                foreach($listcencel as $list){
                                    if($list['category'] == null){
                                        $list['category'] = "ندارد";
                                    }
                                   echo "<tr class=\"odd gradeX\">
                                        <td>
                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"1\" /></td>
                                        <td>{$list['id']}</td>
                                        <td class=\"hidden-phone\">{$list['id_user']}</td>
                                        <td class=\"hidden-phone\">{$list['username']}</td>
                                        <td class=\"hidden-phone\">{$list['description']}</td>
                                        <td class=\"hidden-phone\">{$list['status']}</td>
                                        <td  class="hidden-phone"><a class = "btn btn-danger" href= "cancelService.php?removeid={$list['id']}" data-confirm="آیا از حذف درخواست مطمئن هستید؟">حذف درخواست</a></td>
                                    </tr>";
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

    <script>
      (function(){
        attachTableQuickSearch('#sample_1','#cancelQuickSearch');
        attachSelectionCounter('#sample_1','#cancelSelCount');
        $('#cancelSelectVisible').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr:visible').each(function(){ $(this).find('.checkboxes').prop('checked', true).trigger('change'); }); });
        $('#cancelInvertSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr').each(function(){ var $cb=$(this).find('.checkboxes'); $cb.prop('checked', !$cb.prop('checked')).trigger('change'); }); });
        $('#cancelClearSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody .checkboxes').prop('checked', false).trigger('change'); });
        $('#cancelRemoveBulk').on('click', function(e){
          e.preventDefault(); var ids=[];
          $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); });
          if(!ids.length){ showToast('هیچ موردی انتخاب نشده است'); return; }
          if(!confirm('حذف گروهی درخواست‌های حذف انتخاب‌شده انجام شود؟')) return;
          var done=0; ids.forEach(function(id){ $.get('cancelService.php',{removeid:id}).always(function(){ done++; if(done===ids.length){ showToast('حذف انجام شد'); setTimeout(function(){ location.reload(); }, 600); } }); });
        });
        $('#cancelPrint').on('click', function(e){ e.preventDefault(); window.print(); });
      })();
    </script>


</body>
</html>
    
