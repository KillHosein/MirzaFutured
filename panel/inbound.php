<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';


$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    $where = [];
    $params = [];
    if(!empty($_GET['protocol'])){
        $where[] = "protocol = :protocol";
        $params[':protocol'] = $_GET['protocol'];
    }
    if(!empty($_GET['location'])){
        $where[] = "location = :location";
        $params[':location'] = $_GET['location'];
    }
    if(!empty($_GET['q'])){
        $search = '%' . $_GET['q'] . '%';
        $where[] = "(location LIKE :q OR protocol LIKE :q OR NameInbound LIKE :q)";
        $params[':q'] = $search;
    }
    $sql = "SELECT * FROM Inbound";
    if(!empty($where)){
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $listinvoice = $query->fetchAll();
    if(isset($_GET['export']) && $_GET['export']==='csv'){
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=inbounds-'.date('Y-m-d').'.csv');
        $out = fopen('php://output','w');
        fputcsv($out, ['Location','Protocol','Inbound Name']);
        foreach($listinvoice as $row){
            fputcsv($out, [$row['location'], $row['protocol'], $row['NameInbound']]);
        }
        fclose($out);
        exit();
    }
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
                            <header class="panel-heading">لیست اینباند های مرزبان</header>
                            <div class="panel-body">
                                <form class="form-inline" method="get">
                                    <div class="form-group" style="margin-left:8px;">
                                        <input type="text" name="q" class="form-control" placeholder="نام پنل/پروتکل/اینباند" value="<?php echo isset($_GET['q'])?htmlspecialchars($_GET['q']):''; ?>">
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <input type="text" name="protocol" class="form-control" placeholder="نام پروتکل" value="<?php echo isset($_GET['protocol'])?htmlspecialchars($_GET['protocol']):''; ?>">
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <input type="text" name="location" class="form-control" placeholder="نام پنل/لوکیشن" value="<?php echo isset($_GET['location'])?htmlspecialchars($_GET['location']):''; ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">فیلتر</button>
                                    <a href="inbound.php" class="btn btn-default">پاک کردن</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn btn-success">خروجی CSV</a>
                                    <a href="#" class="btn btn-default" id="inbSaveFilter"><i class="icon-save"></i> ذخیره فیلتر</a>
                                    <a href="#" class="btn btn-default" id="inbLoadFilter"><i class="icon-repeat"></i> بارگذاری فیلتر</a>
                                </form>
                                <div class="action-toolbar sticky">
                                    <a href="inbound.php" class="btn btn-default" id="inbRefresh"><i class="icon-refresh"></i> بروزرسانی</a>
                                    <input type="text" id="inbQuickSearch" class="form-control" placeholder="جستجوی سریع در جدول" style="max-width:220px;">
                                    <a href="#" class="btn btn-default tooltips" id="inbSelectVisible" data-original-title="انتخاب همه ردیف‌های قابل‌مشاهده" aria-label="انتخاب همه"><i class="icon-check"></i> انتخاب همه نمایش‌داده‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="inbInvertSelection" data-original-title="معکوس کردن وضعیت انتخاب ردیف‌ها" aria-label="معکوس انتخاب"><i class="icon-retweet"></i> معکوس انتخاب‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="inbClearSelection" data-original-title="لغو انتخاب همه ردیف‌ها" aria-label="لغو انتخاب"><i class="icon-remove"></i> لغو انتخاب</a>
                                    <span id="inbSelCount" class="sel-count">انتخاب‌ها: 0</span>
                                    <a href="#" class="btn btn-info" id="inbCompact"><i class="icon-resize-small"></i> حالت فشرده</a>
                                    <a href="#" class="btn btn-primary" id="inbCopyNames"><i class="icon-copy"></i> کپی نام اینباندها</a>
                                    <a href="#" class="btn btn-success" id="inbExportVisible"><i class="icon-download"></i> خروجی CSV نمایش‌داده‌ها</a>
                                    <a href="#" class="btn btn-success" id="inbExportSelected"><i class="icon-download"></i> خروجی CSV انتخاب‌شده‌ها</a>
                                    <a href="#" class="btn btn-info tooltips" id="inbColumnsBtn" data-original-title="نمایش/پنهان‌کردن ستون‌های جدول" aria-label="ستون‌ها"><i class="icon-th"></i> ستون‌ها</a>
                                    <a href="#" class="btn btn-default" id="inbPrint"><i class="icon-print"></i> چاپ</a>
                                </div>
                            </div>
                            <table class="table table-striped border-top" id="sample_1">
                                <thead>
                                    <tr>
                                        <th style="width: 8px;">
                                            <input type="checkbox" class="group-checkable" data-set="#sample_1 .checkboxes" /></th>
                                        <th class="hidden-phone">نام پنل</th>
                                        <th class="hidden-phone">نام پروتکل</th>
                                        <th class="hidden-phone">نام اینباند</th>
                                    </tr>
                                </thead>
                                <tbody> <?php
                                foreach($listinvoice as $list){
                                   echo "<tr class=\"odd gradeX\">
                                        <td>
                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"1\" /></td>
                                        <td>{$list['location']}</td>
                                        <td class=\"hidden-phone\">{$list['protocol']}</td>
                                        <td class=\"hidden-phone\">{$list['NameInbound']}</td>
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
        $('#inbCompact').on('click', function(e){ e.preventDefault(); $('#sample_1').toggleClass('compact'); });
        attachTableQuickSearch('#sample_1','#inbQuickSearch');
        $('#inbCopyNames').on('click', function(e){
          e.preventDefault();
          var names = [];
          $('#sample_1 tbody tr').each(function(){
            var $r=$(this);
            if($r.find('.checkboxes').prop('checked')) names.push($r.find('td').eq(3).text().trim());
          });
          if(names.length){ navigator.clipboard.writeText(names.join(', ')); showToast('نام اینباندها کپی شد'); }
          else{ showToast('هیچ ردیفی انتخاب نشده است'); }
        });
        $('#inbSelectVisible').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr:visible').each(function(){ $(this).find('.checkboxes').prop('checked', true); }); });
        $('#inbInvertSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr').each(function(){ var $cb=$(this).find('.checkboxes'); $cb.prop('checked', !$cb.prop('checked')); }); });
        $('#inbClearSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody .checkboxes').prop('checked', false); });
        $('#inbPrint').on('click', function(e){ e.preventDefault(); window.print(); });
        $('#inbExportVisible').on('click', function(e){
          e.preventDefault();
          var rows=[];
          $('#sample_1 tbody tr:visible').each(function(){
            var $td=$(this).find('td');
            rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim()]);
          });
          if(!rows.length){ showToast('ردیفی برای خروجی وجود ندارد'); return; }
          var csv='Location,Protocol,Inbound Name\n';
          rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; });
          var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a'); a.href=url; a.download='inbounds-visible-'+(new Date().toISOString().slice(0,10))+'.csv';
          document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0);
        });
        $('#inbExportSelected').on('click', function(e){
          e.preventDefault();
          var rows=[];
          $('#sample_1 tbody tr').each(function(){
            var $r=$(this);
            if($r.find('.checkboxes').prop('checked')){
              var $td=$r.find('td');
              rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim()]);
            }
          });
          if(!rows.length){ showToast('هیچ ردیفی انتخاب نشده است'); return; }
          var csv='Location,Protocol,Inbound Name\n';
          rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; });
          var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a'); a.href=url; a.download='inbounds-selected-'+(new Date().toISOString().slice(0,10))+'.csv';
          document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0);
        });
        attachSelectionCounter('#sample_1','#inbSelCount');
        setupSavedFilter('form[method="get"]','#inbSaveFilter','#inbLoadFilter','inbound');
        attachColumnToggles('#sample_1','#inbColumnsBtn');
      })();
    </script>


</body>
</html>
