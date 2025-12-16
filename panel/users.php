<?php
session_start();
require_once '../config.php';
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    $where = [];$params = [];
    if(!empty($_GET['status'])){
        $where[] = "LOWER(User_Status) = :st";
        $params[':st'] = strtolower($_GET['status']);
    }
    if(!empty($_GET['agent'])){
        $where[] = "agent = :ag";
        $params[':ag'] = $_GET['agent'];
    }
    if(!empty($_GET['q'])){
        $search = '%' . $_GET['q'] . '%';
        $where[] = "(CAST(id AS CHAR) LIKE :q OR username LIKE :q OR number LIKE :q)";
        $params[':q'] = $search;
    }
    $sql = "SELECT * FROM user";
    if(!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY id DESC";
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $listusers = $query->fetchAll();
    if(isset($_GET['export']) && $_GET['export'] === 'csv'){
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users-'.date('Y-m-d').'.csv');
        $out = fopen('php://output','w');
        fputcsv($out, ['ID','Username','Number','Balance','Affiliates','Status','Agent']);
        foreach($listusers as $u){
            $status = strtolower($u['User_Status']);
            fputcsv($out, [
                $u['id'],
                $u['username'],
                $u['number'],
                $u['Balance'],
                $u['affiliatescount'],
                $status,
                $u['agent']
            ]);
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
                            <header class="panel-heading panel-heading--modern">
                                <div class="panel-heading-main">
                                    <span class="panel-heading-icon">
                                        <i class="icon-user"></i>
                                    </span>
                                    <div class="panel-heading-text">
                                        <div class="panel-heading-title">لیست کاربران</div>
                                        <div class="panel-heading-subtitle">مدیریت کاربران، وضعیت حساب و عملیات گروهی</div>
                                    </div>
                                </div>
                            </header>
                            <div class="panel-body">
                                <form class="form-inline filter-bar" method="get" id="usersFilterForm">
                                    <div class="form-group">
                                        <label for="filterSearch" class="control-label">جستجو</label>
                                        <input type="text" id="filterSearch" name="q" class="form-control" placeholder="نام کاربری، آیدی یا شماره" value="<?php echo isset($_GET['q'])?htmlspecialchars($_GET['q']):''; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="filterStatus" class="control-label">وضعیت</label>
                                        <select id="filterStatus" name="status" class="form-control">
                                            <option value="">همه وضعیت‌ها</option>
                                            <option value="active" <?php echo (isset($_GET['status']) && $_GET['status']==='active')?'selected':''; ?>>فعال</option>
                                            <option value="block" <?php echo (isset($_GET['status']) && $_GET['status']==='block')?'selected':''; ?>>مسدود</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="filterAgent" class="control-label">نوع کاربر</label>
                                        <select id="filterAgent" name="agent" class="form-control">
                                            <option value="">همه گروه‌ها</option>
                                            <option value="f" <?php echo (isset($_GET['agent']) && $_GET['agent']==='f')?'selected':''; ?>>کاربر عادی</option>
                                            <option value="n" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n')?'selected':''; ?>>نماینده معمولی</option>
                                            <option value="n2" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n2')?'selected':''; ?>>نماینده پیشرفته</option>
                                        </select>
                                    </div>
                                    <div class="filter-actions">
                                        <button type="submit" class="btn btn-primary" id="usersFilterSubmit">اعمال فیلتر</button>
                                        <a href="users.php" class="btn btn-default">پاک کردن</a>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn btn-success">خروجی CSV</a>
                                        <a href="#" class="btn btn-default" id="usersSaveFilter"><i class="icon-save"></i> ذخیره فیلتر</a>
                                        <a href="#" class="btn btn-default" id="usersLoadFilter"><i class="icon-repeat"></i> بارگذاری فیلتر</a>
                                    </div>
                                    <div class="filter-chips">
                                        <?php if(!empty($_GET['q'])){ ?>
                                            <span class="filter-chip">جستجو: <?php echo htmlspecialchars($_GET['q']); ?></span>
                                        <?php } ?>
                                        <?php if(!empty($_GET['status'])){ ?>
                                            <span class="filter-chip">وضعیت: <?php echo $_GET['status']==='active'?'فعال':'مسدود'; ?></span>
                                        <?php } ?>
                                        <?php if(!empty($_GET['agent'])){ ?>
                                            <span class="filter-chip">نوع: <?php echo $_GET['agent']==='f'?'کاربر عادی':($_GET['agent']==='n'?'نماینده معمولی':'نماینده پیشرفته'); ?></span>
                                        <?php } ?>
                                        <?php if(empty($_GET['q']) && empty($_GET['status']) && empty($_GET['agent'])){ ?>
                                            <span class="filter-chip muted">هیچ فیلتری اعمال نشده است</span>
                                        <?php } ?>
                                    </div>
                                </form>
                                <div class="action-toolbar sticky">
                                    <a href="users.php" class="btn btn-default" id="usersRefresh"><i class="icon-refresh"></i> بروزرسانی</a>
                                    <input type="text" id="usersQuickSearch" class="form-control" placeholder="جستجوی سریع در جدول" style="max-width:220px;">
                                    <a href="#" class="btn btn-default tooltips" id="usersSelectVisible" data-original-title="انتخاب همه ردیف‌های قابل‌مشاهده" aria-label="انتخاب همه"><i class="icon-check"></i> انتخاب همه نمایش‌داده‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="usersInvertSelection" data-original-title="معکوس کردن وضعیت انتخاب ردیف‌ها" aria-label="معکوس انتخاب"><i class="icon-retweet"></i> معکوس انتخاب‌ها</a>
                                    <a href="#" class="btn btn-default tooltips" id="usersClearSelection" data-original-title="لغو انتخاب همه ردیف‌ها" aria-label="لغو انتخاب"><i class="icon-remove"></i> لغو انتخاب</a>
                                    <span id="usersSelCount" class="sel-count">انتخاب‌ها: 0</span>
                                    <a href="#" class="btn btn-danger" id="usersBlockSel"><i class="icon-ban-circle"></i> مسدود گروهی</a>
                                    <a href="#" class="btn btn-success" id="usersUnblockSel"><i class="icon-ok-circle"></i> رفع مسدودی گروهی</a>
                                    <div class="btn-group" style="margin-right:8px;">
                                      <a href="#" class="btn btn-success" id="usersPresetActive">نمایش فعال</a>
                                      <a href="#" class="btn btn-danger" id="usersPresetBlock">نمایش مسدود</a>
                                    </div>
                                    <a href="#" class="btn btn-info" id="usersCompact"><i class="icon-resize-small"></i> حالت فشرده</a>
                                    <a href="#" class="btn btn-primary" id="usersCopy"><i class="icon-copy"></i> کپی آیدی‌های انتخاب‌شده</a>
                                    <input type="text" id="usersMessage" class="form-control" placeholder="پیام گروهی" style="max-width:240px;">
                                    <a href="#" class="btn btn-info" id="usersSendMsg"><i class="icon-envelope"></i> ارسال پیام</a>
                                    <input type="number" id="usersAmount" class="form-control" placeholder="مبلغ (تومان)" style="max-width:160px;">
                                    <a href="#" class="btn btn-success" id="usersAddBalance"><i class="icon-plus"></i> افزایش موجودی</a>
                                    <a href="#" class="btn btn-warning" id="usersLowBalance"><i class="icon-minus"></i> کسر موجودی</a>
                                    <select id="usersAgentSelect" class="form-control" style="max-width:180px;">
                                      <option value="">تغییر نوع کاربر…</option>
                                      <option value="f">عادی</option>
                                      <option value="n">نماینده</option>
                                      <option value="n2">نماینده پیشرفته</option>
                                    </select>
                                    <a href="#" class="btn btn-primary" id="usersApplyAgent"><i class="icon-user"></i> اعمال نوع کاربر</a>
                                    <a href="#" class="btn btn-success" id="usersExportVisible"><i class="icon-download"></i> خروجی CSV نمایش‌داده‌ها</a>
                                    <a href="#" class="btn btn-success" id="usersExportSelected"><i class="icon-download"></i> خروجی CSV انتخاب‌شده‌ها</a>
                                    <a href="#" class="btn btn-default" id="usersPrint"><i class="icon-print"></i> چاپ</a>
                                    <a href="#" class="btn btn-info tooltips" id="usersColumnsBtn" data-original-title="نمایش/پنهان‌کردن ستون‌های جدول" aria-label="ستون‌ها"><i class="icon-th"></i> ستون‌ها</a>
                                </div>
                            </div>
                            <?php
                            $total = count($listusers); $activeCount = 0; $blockCount = 0;
                            foreach($listusers as $u){ $s = strtolower($u['User_Status']); if($s==='active') $activeCount++; else if($s==='block') $blockCount++; }
                            ?>
                            <div class="stat-grid">
                                <div class="stat-card"><div class="stat-title">تعداد نتایج</div><div class="stat-value"><?php echo number_format($total); ?></div></div>
                                <div class="stat-card"><div class="stat-title">فعال</div><div class="stat-value"><?php echo number_format($activeCount); ?></div></div>
                                <div class="stat-card"><div class="stat-title">مسدود</div><div class="stat-value"><?php echo number_format($blockCount); ?></div></div>
                            </div>
                            <?php if(!$total){ ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="icon-user"></i>
                                    </div>
                                    <h3>کاربری یافت نشد</h3>
                                    <p>می‌توانید فیلترها را تغییر دهید یا جستجو را خالی کنید و دوباره تلاش کنید.</p>
                                    <div class="empty-actions">
                                        <a href="users.php" class="btn btn-default">نمایش همه کاربران</a>
                                    </div>
                                </div>
                            <?php } ?>
                            <table class="table table-striped border-top" id="sample_1">
                                <thead>
                                    <tr>
                                        <th style="width: 8px;">
                                            <input type="checkbox" class="group-checkable" data-set="#sample_1 .checkboxes" /></th>
                                        <th class="hidden-phone">آیدی عددی</th>
                                        <th>نام کاربری</th>
                                        <th class="hidden-phone">شماره تلفن</th>
                                        <th class="hidden-phone">موجودی کاربر</th>
                                        <th class="hidden-phone">تعداد زیرمجموعه های کاربر</th>
                                        <th class="hidden-phone">وضعیت کاربر</th>
                                        <th class="hidden-phone">مدیریت کاربر</th>
                                    </tr>
                                </thead>
                                <tbody> <?php
                                if($total){
                                foreach($listusers as $list){
                                    $statusKey = strtolower($list['User_Status']);
                                    $status_user = [
                                        'active' => "فعال",
                                        'block' => "مسدود",
                                    ][$statusKey] ?? $list['User_Status'];
                                    $status_color = $statusKey==='active' ? '#10b981' : ($statusKey==='block' ? '#ef4444' : '#6b7280');
                                    if($list['number'] == "none")$list['number'] ="بدون شماره ";
                                   $statusClass = 'status-'.strtolower($statusKey);
                                   echo "<tr class=\"odd gradeX\">\n                                        <td>\n                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"1\" /></td>\n                                        <td>{$list['id']}</td>\n                                        <td class=\"hidden-phone\">{$list['username']}</td>\n                                        <td class=\"hidden-phone\">{$list['number']}</td>\n                                        <td class=\"hidden-phone\">".number_format($list['Balance'])."</td>\n                                        <td class=\"hidden-phone\">{$list['affiliatescount']}</td>\n                                        <td class=\"hidden-phone\"><span class=\"status-badge {$statusClass}\">{$status_user}</span></td>\n                                        <td class=\"hidden-phone\">\n                                        <a class = \"btn btn-success\" href= \"user.php?id={$list['id']}\">مدیریت کاربر </a></td>\n                                    </tr>";
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
    <script>
      (function(){
        $('#usersCompact').on('click', function(e){ e.preventDefault(); $('#sample_1').toggleClass('compact'); });
        attachTableQuickSearch('#sample_1','#usersQuickSearch');
        $('#usersCopy').on('click', function(e){
          e.preventDefault();
          var ids = [];
          $('#sample_1 tbody tr').each(function(){
            var $row = $(this);
            var checked = $row.find('.checkboxes').prop('checked');
            if(checked){ ids.push($row.find('td').eq(1).text().trim()); }
          });
          if(ids.length){ navigator.clipboard.writeText(ids.join(', ')); showToast('آیدی‌ها کپی شد'); }
          else{ showToast('هیچ ردیفی انتخاب نشده است'); }
        });
        function bulkUserStatus(status){
          var ids = [];
          $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); });
          if(!ids.length){ showToast('هیچ کاربری انتخاب نشده است'); return; }
          var done=0; ids.forEach(function(id){ $.get('user.php',{id:id,status:status}).always(function(){ done++; if(done===ids.length){ showToast('عملیات انجام شد'); setTimeout(function(){ location.reload(); }, 600); } }); });
        }
        $('#usersBlockSel').on('click', function(e){ e.preventDefault(); bulkUserStatus('block'); });
        $('#usersUnblockSel').on('click', function(e){ e.preventDefault(); bulkUserStatus('active'); });
        $('#usersPresetActive').on('click', function(e){ e.preventDefault(); var $f=$('#usersFilterForm'); $f.find('select[name="status"]').val('active'); $f.submit(); });
        $('#usersPresetBlock').on('click', function(e){ e.preventDefault(); var $f=$('#usersFilterForm'); $f.find('select[name="status"]').val('block'); $f.submit(); });
        $('#usersSendMsg').on('click', function(e){ e.preventDefault(); var txt=$('#usersMessage').val(); if(!txt){ showToast('متن پیام را وارد کنید'); return; } var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); }); if(!ids.length){ showToast('هیچ کاربری انتخاب نشده است'); return; } var done=0; ids.forEach(function(id){ $.get('user.php',{id:id,textmessage:txt}).always(function(){ done++; if(done===ids.length){ showToast('پیام‌ها ارسال شد'); setTimeout(function(){ location.reload(); }, 600); } }); }); });
        function bulkBalance(param){ var amt=parseInt($('#usersAmount').val(),10); if(!(amt>0)){ showToast('مبلغ معتبر وارد کنید'); return; } var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); }); if(!ids.length){ showToast('هیچ کاربری انتخاب نشده است'); return; } var done=0; var key = param==='add' ? 'priceadd' : 'pricelow'; ids.forEach(function(id){ var args={id:id}; args[key]=amt; $.get('user.php',args).always(function(){ done++; if(done===ids.length){ showToast('عملیات موجودی انجام شد'); setTimeout(function(){ location.reload(); }, 600); } }); }); }
        $('#usersAddBalance').on('click', function(e){ e.preventDefault(); bulkBalance('add'); });
        $('#usersLowBalance').on('click', function(e){ e.preventDefault(); bulkBalance('low'); });
        $('#usersApplyAgent').on('click', function(e){ e.preventDefault(); var ag=$('#usersAgentSelect').val(); if(!ag){ showToast('نوع کاربر را انتخاب کنید'); return; } var ids=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); }); if(!ids.length){ showToast('هیچ کاربری انتخاب نشده است'); return; } var done=0; ids.forEach(function(id){ $.get('user.php',{id:id,agent:ag}).always(function(){ done++; if(done===ids.length){ showToast('نوع کاربر اعمال شد'); setTimeout(function(){ location.reload(); }, 600); } }); }); });
        $('#usersSelectVisible').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr:visible').each(function(){ $(this).find('.checkboxes').prop('checked', true); }); });
        $('#usersInvertSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody tr').each(function(){ var $cb=$(this).find('.checkboxes'); $cb.prop('checked', !$cb.prop('checked')); }); });
        $('#usersClearSelection').on('click', function(e){ e.preventDefault(); $('#sample_1 tbody .checkboxes').prop('checked', false); });
        $('#usersPrint').on('click', function(e){ e.preventDefault(); window.print(); });
        $('#usersExportVisible').on('click', function(e){ e.preventDefault(); var rows=[]; $('#sample_1 tbody tr:visible').each(function(){ var $td=$(this).find('td'); rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim(), $td.eq(4).text().trim(), $td.eq(5).text().trim(), $td.eq(6).text().trim()]); }); var csv='ID,Username,Number,Balance,Affiliates,Status\n'; rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; }); var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}); var url = URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url; a.download = 'users-visible-'+(new Date().toISOString().slice(0,10))+'.csv'; document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0); });
        $('#usersExportSelected').on('click', function(e){ e.preventDefault(); var rows=[]; $('#sample_1 tbody tr').each(function(){ var $r=$(this); if($r.find('.checkboxes').prop('checked')){ var $td=$r.find('td'); rows.push([$td.eq(1).text().trim(), $td.eq(2).text().trim(), $td.eq(3).text().trim(), $td.eq(4).text().trim(), $td.eq(5).text().trim(), $td.eq(6).text().trim()]); } }); if(!rows.length){ showToast('هیچ ردیفی انتخاب نشده است'); return; } var csv='ID,Username,Number,Balance,Affiliates,Status\n'; rows.forEach(function(r){ csv += r.map(function(x){ return '"'+x.replace(/"/g,'""')+'"'; }).join(',')+'\n'; }); var blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}); var url = URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url; a.download = 'users-selected-'+(new Date().toISOString().slice(0,10))+'.csv'; document.body.appendChild(a); a.click(); setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 0); });
        attachSelectionCounter('#sample_1','#usersSelCount');
        setupSavedFilter('#usersFilterForm','#usersSaveFilter','#usersLoadFilter','users');
        attachColumnToggles('#sample_1','#usersColumnsBtn');
      })();
    </script>


</body>
</html>
