<?php
session_start();
require_once '../config.php';
$q = $pdo->prepare("SELECT * FROM admin WHERE username=:u");
$q->bindParam(':u', $_SESSION['user'], PDO::PARAM_STR);
$q->execute();
$adminRow = $q->fetch(PDO::FETCH_ASSOC);
if( !isset($_SESSION["user"]) || !$adminRow ){
    header('Location: login.php');
    return;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_payment_status'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $st = $_POST['bulk_payment_status'];
    if(!empty($ids) && in_array($st,['paid','Unpaid','waiting','reject','expire'])){
        $stmt = $pdo->prepare('UPDATE Payment_report SET payment_Status = :st WHERE id_order = :id');
        foreach($ids as $id){ $stmt->execute([':st'=>$st, ':id'=>$id]); }
    }
    header('Location: payment.php');
    exit;
}

$statuses = [
    'paid' => ['label' => "پرداخت شده", 'color' => '#10b981'],
    'Unpaid' => ['label' => "پرداخت نشده", 'color' => '#ef4444'],
    'expire' => ['label' => "منقضی شده", 'color' => '#6b7280'],
    'reject' => ['label' => "رد شده", 'color' => '#f59e0b'],
    'waiting' => ['label' => "در انتظار تایید", 'color' => '#3b82f6']
];
$methods = [
    'cart to cart' => "کارت به کارت",
    'low balance by admin' => "کسر موجودی توسط ادمین",
    'add balance by admin' => "افزایش موجودی توسط ادمین",
    'Currency Rial 1' => "درگاه ارزی ریالی اول",
    'Currency Rial tow' => "درگاه ارزی ریالی دوم",
    'Currency Rial 3' => "درگاه ارزی ریالی سوم",
    'aqayepardakht' => "درگاه اقای پرداخت",
    'zarinpal' => "زرین پال",
    'plisio' => "درکاه ارزی plisio",
    'arze digital offline' => "درگاه ارزی آفلاین",
    'Star Telegram' => "استار تلگرام",
    'nowpayment' => 'NowPayment'
];

$where = [];
$params = [];
if(!empty($_GET['status'])){
    $where[] = "payment_Status = :status";
    $params[':status'] = $_GET['status'];
}
if(!empty($_GET['method'])){
    $where[] = "Payment_Method = :method";
    $params[':method'] = $_GET['method'];
}
if(!empty($_GET['q'])){
    $searchTerm = '%' . $_GET['q'] . '%';
    $where[] = "(id_user LIKE :q OR id_order LIKE :q)";
    $params[':q'] = $searchTerm;
}

$sql = "SELECT * FROM Payment_report";
if(!empty($where)){
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY time DESC";

$query = $pdo->prepare($sql);
$query->execute($params);
$listpayment = $query->fetchAll();

if(isset($_GET['export']) && $_GET['export'] === 'csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=payments-' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID User', 'Order ID', 'Price', 'Time', 'Method', 'Status']);
    foreach($listpayment as $row){
        fputcsv($output, [
            $row['id_user'],
            $row['id_order'],
            $row['price'],
            $row['time'],
            isset($methods[$row['Payment_Method']]) ? $methods[$row['Payment_Method']] : $row['Payment_Method'],
            isset($statuses[$row['payment_Status']]) ? $statuses[$row['payment_Status']]['label'] : $row['payment_Status']
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
                                        <input type="text" class="form-control" name="q" placeholder="آیدی کاربر یا سفارش" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
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
                                        <select name="method" class="form-control">
                                            <option value="">همه روش‌ها</option>
                                            <?php foreach($methods as $key => $val): ?>
                                                <option value="<?php echo $key; ?>" <?php echo (isset($_GET['method']) && $_GET['method'] === $key) ? 'selected' : ''; ?>><?php echo $val; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">فیلتر</button>
                                    <a href="payment.php" class="btn btn-default">پاک کردن</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">خروجی CSV</a>
                                    <a href="#" class="btn btn-default" id="paySaveFilter"><i class="icon-save"></i> ذخیره فیلتر</a>
                                    <a href="#" class="btn btn-default" id="payLoadFilter"><i class="icon-repeat"></i> بارگذاری فیلتر</a>
                                </form>
                                <div class="action-toolbar sticky">
                                    <a href="payment.php" class="btn btn-default" id="payRefresh"><i class="icon-refresh"></i> بروزرسانی</a>
                                    <a href="#" class="btn btn-info" id="payCompact"><i class="icon-resize-small"></i> حالت فشرده</a>
                                    <a href="#" class="btn btn-primary" id="payCopy"><i class="icon-copy"></i> کپی شماره تراکنش‌ها</a>
                                    <input type="text" id="payQuickSearch" class="form-control" placeholder="جستجوی سریع در جدول" style="max-width:220px;">
                                    <select id="payBulkStatus" class="form-control" style="max-width:200px;">
                                        <option value="">تغییر وضعیت گروهی…</option>
                                        <option value="paid">پرداخت شده</option>
                                        <option value="Unpaid">پرداخت نشده</option>
                                        <option value="waiting">در انتظار تایید</option>
                                        <option value="reject">رد شده</option>
                                        <option value="expire">منقضی</option>
                                    </select>
                                    <a href="#" class="btn btn-warning" id="payApplyBulk"><i class="icon-ok"></i> اعمال وضعیت</a>
                                    <div class="btn-group" style="margin-right:8px;">
                                        <a href="#" class="btn btn-success" id="presetPaid">پرداخت‌شده</a>
                                        <a href="#" class="btn btn-danger" id="presetUnpaid">پرداخت‌نشده</a>
                                        <a href="#" class="btn btn-info" id="presetWaiting">در انتظار</a>
                                    </div>
                                    <a href="#" class="btn btn-default tooltips" id="paySelectVisible" data-original-title="انتخاب همه ردیف‌های قابل‌مشاهده" aria-label="انتخاب همه"><i class="icon-check"></i> انتخاب همه نمایش‌داده‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="payInvertSelection" data-original-title="معکوس کردن وضعیت انتخاب ردیف‌ها" aria-label="معکوس انتخاب"><i class="icon-retweet"></i> معکوس انتخاب‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="payClearSelection" data-original-title="لغو انتخاب همه ردیف‌ها" aria-label="لغو انتخاب"><i class="icon-remove"></i> لغو انتخاب</a>
                                    <span id="paySelCount" class="sel-count">انتخاب‌ها: 0</span>
                                    <a href="#" class="btn btn-default" id="payPrint"><i class="icon-print"></i> چاپ</a>
                                    <a href="#" class="btn btn-success" id="payExportVisible"><i class="icon-download"></i> خروجی CSV نمایش‌داده‌ها</a>
                                    <a href="#" class="btn btn-success" id="payExportSelected"><i class="icon-download"></i> خروجی CSV انتخاب‌شده‌ها</a>
                                    <a href="#" class="btn btn-info tooltips" id="payColumnsBtn" data-original-title="نمایش/پنهان‌کردن ستون‌های جدول" aria-label="ستون‌ها"><i class="icon-th"></i> ستون‌ها</a>
                                </div>
                            </div>
                        </section>
                        <section class="panel">
                            <header class="panel-heading">لیست تراکنش ها</header>
                            <table class="table table-striped border-top" id="sample_1">
                                <thead>
                                    <tr>
                                        <th style="width: 8px;">
                                            <input type="checkbox" class="group-checkable" data-set="#sample_1 .checkboxes" /></th>
                                        <th class="hidden-phone">آیدی عددی</th>
                                        <th>شماره تراکنش</th>
                                        <th class="hidden-phone">مبلغ تراکنش</th>
                                        <th class="hidden-phone">زمان تراکنش</th>
                                        <th class="hidden-phone">روش پرداخت</th>
                                        <th class="hidden-phone">وضعیت تراکنش</th>
                                    </tr>
                                </thead>
                                <tbody> <?php
                                foreach($listpayment as $list){
                                    $status_label = isset($statuses[$list['payment_Status']]) ? $statuses[$list['payment_Status']]['label'] : $list['payment_Status'];
                                    $status_color = isset($statuses[$list['payment_Status']]) ? $statuses[$list['payment_Status']]['color'] : '#999';
                                    $method_label = isset($methods[$list['Payment_Method']]) ? $methods[$list['Payment_Method']] : $list['Payment_Method'];
                                    $statusClass = 'status-'.strtolower($list['payment_Status']);
                                    echo "<tr class=\"odd gradeX\">\n                                        <td>\n                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"1\" /></td>\n                                        <td>{$list['id_user']}</td>\n                                        <td class=\"hidden-phone\">{$list['id_order']}</td>\n                                        <td class=\"hidden-phone\">" . number_format($list['price']) . "</td>\n                                        <td class=\"hidden-phone\">{$list['time']}</td>\n                                        <td class=\"hidden-phone\">{$method_label}</td>\n                                        <td class=\"hidden-phone\"><span class=\"status-badge {$statusClass}\">{$status_label}</span></td>\n                                    </tr>";
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
        $('#payCompact').on('click', function(e){ e.preventDefault(); $('#sample_1').toggleClass('compact'); });
        attachTableQuickSearch('#sample_1','#payQuickSearch');
        $('#payCopy').on('click', function(e){ e.preventDefault(); var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(2).text().trim()); }); if(ids.length){ navigator.clipboard.writeText(ids.join(', ')); showToast('شماره‌ها کپی شد'); } else { showToast('هیچ ردیفی انتخاب نشده است'); } });
        $('#payApplyBulk').on('click', function(e){ e.preventDefault(); var st=$('#payBulkStatus').val(); if(!st){ showToast('وضعیت را انتخاب کنید'); return; } var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(2).text().trim()); }); if(!ids.length){ showToast('هیچ ردیفی انتخاب نشده است'); return; } var $f=$('<form method="post"></form>').append($('<input name="bulk_payment_status">').val(st)); ids.forEach(function(id){ $f.append($('<input name="ids[]">').val(id)); }); $('body').append($f); $f.submit(); });
        function setStatusAndSubmit(val){ var $form = $('form[method="get"]').first(); $form.find('select[name="status"]').val(val); $form.submit(); }
        $('#presetPaid').on('click', function(e){ e.preventDefault(); setStatusAndSubmit('paid'); });
        $('#presetUnpaid').on('click', function(e){ e.preventDefault(); setStatusAndSubmit('Unpaid'); });
        $('#presetWaiting').on('click', function(e){ e.preventDefault(); setStatusAndSubmit('waiting'); });
        $('#paySelectVisible').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr:visible').each(function(){ $(this).find('.checkboxes').prop('checked', true); }); });
        $('#payInvertSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr').each(function(){ var $cb=$(this).find('.checkboxes'); $cb.prop('checked', !$cb.prop('checked')); }); });
        $('#payClearSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody .checkboxes').prop('checked', false); });
        $('#payPrint').on('click', function(e){ e.preventDefault(); window.print(); });
        $('#payExportVisible').on('click', function(e){ e.preventDefault(); var rows=[]; $('#sample_1 tbody tr:visible').each(function(){ var $td=$(this).find('td'); rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim(), $td.eq(4).text().trim(), $td.eq(5).text().trim(), $td.eq(6).text().trim()]); }); var csv='ID User,Order ID,Price,Time,Method,Status\n'; rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; }); var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}); var url = URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url; a.download = 'payments-visible-'+(new Date().toISOString().slice(0,10))+'.csv'; document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0); });
        $('#payExportSelected').on('click', function(e){ e.preventDefault(); var rows=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')){ var $td=$r.find('td'); rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim(), $td.eq(4).text().trim(), $td.eq(5).text().trim(), $td.eq(6).text().trim()]); } }); if(!rows.length){ showToast('هیچ ردیفی انتخاب نشده است'); return; } var csv='ID User,Order ID,Price,Time,Method,Status\n'; rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; }); var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}); var url = URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url; a.download = 'payments-selected-'+(new Date().toISOString().slice(0,10))+'.csv'; document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0); });
        attachSelectionCounter('#sample_1','#paySelCount');
        setupSavedFilter('form[method="get"]','#paySaveFilter','#payLoadFilter','payment');
        attachColumnToggles('#sample_1','#payColumnsBtn');
      })();
    </script>


</body>
</html>
