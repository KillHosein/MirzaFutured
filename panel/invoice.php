<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';
require_once '../function.php';
require_once '../botapi.php';
require_once '../panels.php';


$q = $pdo->prepare("SELECT * FROM admin WHERE username=:u");
$q->bindParam(':u', $_SESSION['user'], PDO::PARAM_STR);
$q->execute();
$adminRow = $q->fetch(PDO::FETCH_ASSOC);
if( !isset($_SESSION["user"]) || !$adminRow ){
    header('Location: login.php');
    return;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_status'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $newStatus = $_POST['bulk_status'];
    if(!empty($ids) && in_array($newStatus,['active','disablebyadmin','unpaid'])){
        $stmtBulk = $pdo->prepare("UPDATE invoice SET Status = :st WHERE id_invoice = :id");
        foreach($ids as $id){ $stmtBulk->execute([':st'=>$newStatus, ':id'=>$id]); }
    }
    header('Location: invoice.php');
    exit;
}

$ManagePanel = new ManagePanel();
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_remove_type'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $type = $_POST['bulk_remove_type'];
    $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
    if(!empty($ids) && in_array($type,['one','tow','three'])){
        $stmtInv = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice = :id');
        foreach($ids as $id){
            $stmtInv->execute([':id'=>$id]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            if(!$inv) continue;
            if($type==='one'){
                $pdo->prepare('UPDATE invoice SET Status = "removebyadmin" WHERE id_invoice = :id')->execute([':id'=>$id]);
                $ManagePanel->RemoveUser($inv['Service_location'], $inv['username']);
            } elseif($type==='tow'){
                if($amount>0){ $pdo->prepare('UPDATE user SET Balance = Balance + :b WHERE id = :uid')->execute([':b'=>$amount, ':uid'=>$inv['id_user']]); }
                $pdo->prepare('UPDATE invoice SET Status = "removebyadmin" WHERE id_invoice = :id')->execute([':id'=>$id]);
                $ManagePanel->RemoveUser($inv['Service_location'], $inv['username']);
            } else {
                $pdo->prepare('DELETE FROM invoice WHERE id_invoice = :id')->execute([':id'=>$id]);
            }
        }
    }
    header('Location: invoice.php');
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_extend'])){
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $vol = isset($_POST['volume_service']) ? (int)$_POST['volume_service'] : 0;
    $days = isset($_POST['time_service']) ? (int)$_POST['time_service'] : 0;
    if(!empty($ids) && $vol>0 && $days>=0){
        $stmtInv = $pdo->prepare('SELECT * FROM invoice WHERE id_invoice = :id');
        $stmtPanel = $pdo->prepare('SELECT * FROM marzban_panel WHERE name_panel = :np');
        $stmtInsert = $pdo->prepare('INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)');
        foreach($ids as $id){
            $stmtInv->execute([':id'=>$id]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            if(!$inv) continue;
            $stmtPanel->execute([':np'=>$inv['Service_location']]);
            $panel = $stmtPanel->fetch(PDO::FETCH_ASSOC);
            if(!$panel) continue;
            $ext = $ManagePanel->extend($panel['Methodextend'], $vol, $days, $inv['username'], 'custom_volume', $panel['code_panel']);
            if(isset($ext['status']) && $ext['status']!==false){
                $val = $vol.'_'+$days;
                $stmtInsert->execute([':id_user'=>$inv['id_user'], ':username'=>$inv['username'], ':value'=>$val, ':type'=>'extend_user_by_admin', ':time'=>date('Y/m/d H:i:s'), ':price'=>0, ':output'=>json_encode($ext)]);
                $pdo->prepare('UPDATE invoice SET Status = "active" WHERE id_invoice = :id')->execute([':id'=>$id]);
            }
        }
    }
    header('Location: invoice.php');
    exit;
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
                                    <div class="btn-group" style="margin-right:8px;">
                                        <a href="#" class="btn btn-info" id="preset7dInv">۷ روز اخیر</a>
                                        <a href="#" class="btn btn-info" id="presetMonthInv">ماه جاری</a>
                                        <a href="#" class="btn btn-info" id="presetYearInv">سال جاری</a>
                                    </div>
                                    <div class="action-toolbar sticky">
                                        <a href="invoice.php" class="btn btn-default" id="invRefresh"><i class="icon-refresh"></i> بروزرسانی</a>
                                        <input type="text" id="invQuickSearch" class="form-control" placeholder="جستجوی سریع در جدول" style="max-width:220px;">
                                        <a href="#" class="btn btn-default tooltips" id="invSelectVisible" data-original-title="انتخاب همه ردیف‌های قابل‌مشاهده" aria-label="انتخاب همه"><i class="icon-check"></i> انتخاب همه نمایش‌داده‌ها</a>
                                        <a href="#" class="btn btn-default tooltips" id="invInvertSelection" data-original-title="معکوس کردن وضعیت انتخاب ردیف‌ها" aria-label="معکوس انتخاب"><i class="icon-retweet"></i> معکوس انتخاب‌ها</a>
                                        <a href="#" class="btn btn-default tooltips" id="invClearSelection" data-original-title="لغو انتخاب همه ردیف‌ها" aria-label="لغو انتخاب"><i class="icon-remove"></i> لغو انتخاب</a>
                                        <span id="invSelCount" class="sel-count">انتخاب‌ها: 0</span>
                                        <select id="invBulkStatus" class="form-control" style="max-width:200px;">
                                            <option value="">تغییر وضعیت گروهی…</option>
                                            <option value="active">فعال</option>
                                            <option value="disablebyadmin">غیرفعال توسط ادمین</option>
                                            <option value="unpaid">در انتظار پرداخت</option>
                                        </select>
                                        <a href="#" class="btn btn-warning" id="invApplyBulk"><i class="icon-ok"></i> اعمال وضعیت</a>
                                        <input type="number" id="extVolGB" class="form-control" placeholder="حجم (GB)" style="max-width:140px;">
                                        <input type="number" id="extTimeDays" class="form-control" placeholder="زمان (روز)" style="max-width:140px;">
                                        <a href="#" class="btn btn-success" id="invExtendBulk"><i class="icon-plus"></i> تمدید گروهی</a>
                                        <select id="invRemoveType" class="form-control" style="max-width:180px;">
                                            <option value="">حذف سرویس گروهی…</option>
                                            <option value="one">حذف بدون برگشت وجه</option>
                                            <option value="tow">حذف با برگشت وجه</option>
                                            <option value="three">حذف فاکتور</option>
                                        </select>
                                        <input type="number" id="invRemoveAmount" class="form-control" placeholder="مبلغ برگشتی" style="max-width:160px; display:none;">
                                        <a href="#" class="btn btn-danger" id="invRemoveBulk"><i class="icon-trash"></i> حذف گروهی</a>
                                        <a href="#" class="btn btn-success" id="invExportVisible"><i class="icon-download"></i> خروجی CSV نمایش‌داده‌ها</a>
                                        <a href="#" class="btn btn-success" id="invExportSelected"><i class="icon-download"></i> خروجی CSV انتخاب‌شده‌ها</a>
                                        <div class="btn-group" style="margin-right:8px;">
                                          <a href="#" class="btn btn-success" id="presetActiveInv">فعال</a>
                                          <a href="#" class="btn btn-warning" id="presetUnpaidInv">در انتظار پرداخت</a>
                                        </div>
                                        <a href="#" class="btn btn-info tooltips" id="invColumnsBtn" data-original-title="نمایش/پنهان‌کردن ستون‌های جدول" aria-label="ستون‌ها"><i class="icon-th"></i> ستون‌ها</a>
                                        <a href="#" class="btn btn-info" id="invCompact"><i class="icon-resize-small"></i> حالت فشرده</a>
                                        <a href="#" class="btn btn-primary tooltips" id="invCopy" data-original-title="کپی شناسه‌های انتخاب‌شده" aria-label="کپی شناسه‌ها"><i class="icon-copy"></i> کپی شناسه‌های انتخاب‌شده</a>
                                        <a href="#" class="btn btn-primary tooltips" id="invCopyUsernames" data-original-title="کپی نام‌های کاربری انتخاب‌شده" aria-label="کپی نام‌های کاربری"><i class="icon-copy"></i> کپی نام‌های کاربری</a>
                                        <a href="#" class="btn btn-default" id="invSaveFilter"><i class="icon-save"></i> ذخیره فیلتر</a>
                                        <a href="#" class="btn btn-default" id="invLoadFilter"><i class="icon-repeat"></i> بارگذاری فیلتر</a>
                                        <a href="#" class="btn btn-default" id="invPrint"><i class="icon-print"></i> چاپ</a>
                                    </div>
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
                                    $statusClass = 'status-'.strtolower($list['Status']);
                                    echo "<tr class=\"odd gradeX\">\n                                        <td>\n                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"{$list['id_invoice']}\" /></td>\n                                        <td>{$list['id_user']}</td>\n                                        <td class=\"hidden-phone\">{$list['id_invoice']}</td>\n                                        <td class=\"hidden-phone\">{$list['username']}</td>\n                                        <td class=\"hidden-phone\">{$list['Service_location']}</td>\n                                        <td class=\"hidden-phone\">{$list['name_product']}</td>\n                                        <td class=\"hidden-phone time_Sell\">{$timeFmt}</td>\n                                        <td class=\"hidden-phone\">{$priceFmt}</td>\n                                        <td class=\"hidden-phone\"><span class=\"status-badge {$statusClass}\">{$status_label}</span></td>\n                                    </tr>";
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
        function fmt(d){ var yyyy=d.getFullYear(); var mm=('0'+(d.getMonth()+1)).slice(-2); var dd=('0'+d.getDate()).slice(-2); return yyyy+'-'+mm+'-'+dd; }
        var $form = $('form[method="get"]');
        $('#preset7dInv').on('click',function(e){ e.preventDefault(); var end=new Date(); var start=new Date(); start.setDate(end.getDate()-6); $form.find('input[name="from"]').val(fmt(start)); $form.find('input[name="to"]').val(fmt(end)); $form.submit(); });
        $('#presetMonthInv').on('click',function(e){ e.preventDefault(); var end=new Date(); var start=new Date(end.getFullYear(), end.getMonth(), 1); $form.find('input[name="from"]').val(fmt(start)); $form.find('input[name="to"]').val(fmt(end)); $form.submit(); });
        $('#presetYearInv').on('click',function(e){ e.preventDefault(); var end=new Date(); var start=new Date(end.getFullYear(), 0, 1); $form.find('input[name="from"]').val(fmt(start)); $form.find('input[name="to"]').val(fmt(end)); $form.submit(); });
        $('#invCompact').on('click', function(e){ e.preventDefault(); $('#sample_1').toggleClass('compact'); });
        $('#invCopy').on('click', function(e){ e.preventDefault(); var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(2).text().trim()); }); if(ids.length){ navigator.clipboard.writeText(ids.join(', ')); showToast('شناسه‌ها کپی شد'); } else { showToast('هیچ سفارشی انتخاب نشده است'); } });
        $('#invCopyUsernames').on('click', function(e){ e.preventDefault(); var names=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) names.push($r.find('td').eq(3).text().trim()); }); if(names.length){ navigator.clipboard.writeText(names.join(', ')); showToast('نام‌های کاربری کپی شد'); } else { showToast('هیچ سفارشی انتخاب نشده است'); } });
        $('#invApplyBulk').on('click', function(e){ e.preventDefault(); var status=$('#invBulkStatus').val(); if(!status){ showToast('وضعیت را انتخاب کنید'); return; } var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(2).text().trim()); }); if(!ids.length){ showToast('هیچ سفارشی انتخاب نشده است'); return; } var $f=$('<form method="post"></form>').append($('<input name="bulk_status">').val(status)); ids.forEach(function(id){ $f.append($('<input name="ids[]">').val(id)); }); $('body').append($f); $f.submit(); });
        attachTableQuickSearch('#sample_1','#invQuickSearch');
        $('#invRemoveType').on('change', function(){ var v=$(this).val(); $('#invRemoveAmount').toggle(v==='tow'); });
        $('#invRemoveBulk').on('click', function(e){ e.preventDefault(); var type=$('#invRemoveType').val(); if(!type){ showToast('نوع حذف را انتخاب کنید'); return; } var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(2).text().trim()); }); if(!ids.length){ showToast('هیچ سفارشی انتخاب نشده است'); return; } var $f=$('<form method="post"></form>').append($('<input name="bulk_remove_type">').val(type)); if(type==='tow'){ var amt=$('#invRemoveAmount').val(); if(!amt || amt<=0){ showToast('مبلغ برگشتی نامعتبر است'); return; } $f.append($('<input name="amount">').val(amt)); } ids.forEach(function(id){ $f.append($('<input name="ids[]">').val(id)); }); $('body').append($f); $f.submit(); });
        $('#invExtendBulk').on('click', function(e){ e.preventDefault(); var vol=parseInt($('#extVolGB').val(),10); var days=parseInt($('#extTimeDays').val(),10); if(!(vol>0)){ showToast('حجم معتبر وارد کنید'); return; } if(days<0){ showToast('زمان معتبر وارد کنید'); return; } var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(2).text().trim()); }); if(!ids.length){ showToast('هیچ سفارشی انتخاب نشده است'); return; } var $f=$('<form method="post"></form>').append($('<input name="bulk_extend">').val(1)).append($('<input name="volume_service">').val(vol)).append($('<input name="time_service">').val(days)); ids.forEach(function(id){ $f.append($('<input name="ids[]">').val(id)); }); $('body').append($f); $f.submit(); });
        $('#invSelectVisible').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr:visible').each(function(){ $(this).find('.checkboxes').prop('checked', true); }); });
        $('#invInvertSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr').each(function(){ var $cb=$(this).find('.checkboxes'); $cb.prop('checked', !$cb.prop('checked')); }); });
        $('#invClearSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody .checkboxes').prop('checked', false); });
        $('#invExportVisible').on('click', function(e){ e.preventDefault(); var rows=[]; $('#sample_1 tbody tr:visible').each(function(){ var $td=$(this).find('td'); rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim(), $td.eq(4).text().trim(), $td.eq(5).text().trim(), $td.eq(6).text().trim(), $td.eq(7).text().trim(), $td.eq(8).text().trim()]); }); if(!rows.length){ showToast('ردیفی برای خروجی وجود ندارد'); return; } var csv='ID User,Invoice ID,Username,Location,Product,Time,Price,Status\n'; rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; }); var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}); var url = URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url; a.download = 'invoices-visible-'+(new Date().toISOString().slice(0,10))+'.csv'; document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0); });
        $('#invExportSelected').on('click', function(e){ e.preventDefault(); var rows=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')){ var $td=$r.find('td'); rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim(), $td.eq(4).text().trim(), $td.eq(5).text().trim(), $td.eq(6).text().trim(), $td.eq(7).text().trim(), $td.eq(8).text().trim()]); } }); if(!rows.length){ showToast('هیچ سفارشی انتخاب نشده است'); return; } var csv='ID User,Invoice ID,Username,Location,Product,Time,Price,Status\n'; rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; }); var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}); var url = URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url; a.download = 'invoices-selected-'+(new Date().toISOString().slice(0,10))+'.csv'; document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0); });
        function setStatusAndSubmit(val){ var $form = $('form[method="get"]').first(); $form.find('select[name="status"]').val(val); $form.submit(); }
        $('#presetActiveInv').on('click', function(e){ e.preventDefault(); setStatusAndSubmit('active'); });
        $('#presetUnpaidInv').on('click', function(e){ e.preventDefault(); setStatusAndSubmit('unpaid'); });
        $('#invPrint').on('click', function(e){ e.preventDefault(); window.print(); });
        attachSelectionCounter('#sample_1','#invSelCount');
        setupSavedFilter('form[method="get"]','#invSaveFilter','#invLoadFilter','invoice');
        attachColumnToggles('#sample_1','#invColumnsBtn');
      })();
    </script>


</body>
</html>
