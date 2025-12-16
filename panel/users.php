<?php
session_start();
require_once '../config.php';

// --- Logic Section ---
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

$where = [];
$params = [];
if (!empty($_GET['status'])) {
    $where[] = "LOWER(User_Status) = :st";
    $params[':st'] = strtolower($_GET['status']);
}
if (!empty($_GET['agent'])) {
    $where[] = "agent = :ag";
    $params[':ag'] = $_GET['agent'];
}
if (!empty($_GET['q'])) {
    $search = '%' . $_GET['q'] . '%';
    $where[] = "(CAST(id AS CHAR) LIKE :q OR username LIKE :q OR number LIKE :q)";
    $params[':q'] = $search;
}
$sql = "SELECT * FROM user";
if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY id DESC";
$query = $pdo->prepare($sql);
$query->execute($params);
$listusers = $query->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Username', 'Number', 'Balance', 'Affiliates', 'Status', 'Agent']);
    foreach ($listusers as $u) {
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
// --- End Logic ---
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران | ربات میرزا</title>

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-reset.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />

    <style>
        /* General Tweaks */
        body { background-color: #f1f2f7; font-family: 'Tahoma', sans-serif; }
        .panel { border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.05); border-radius: 8px; margin-bottom: 30px; }
        .panel-heading {
            background: #fff !important;
            border-bottom: 1px solid #eee;
            color: #333 !important;
            border-radius: 8px 8px 0 0;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 16px;
        }

        /* Filter Form */
        .filter-area {
            background: #f9fafc;
            padding: 15px;
            border-radius: 6px;
            border: 1px dashed #dce1e7;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
        }
        .filter-area .form-control { border-radius: 4px; border: 1px solid #e0e0e0; box-shadow: none; }
        
        /* Stats Cards */
        .stat-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .stat-card {
            flex: 1;
            min-width: 150px;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            text-align: center;
            border-bottom: 3px solid transparent;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.blue { border-bottom-color: #58c9f3; }
        .stat-card.green { border-bottom-color: #10b981; }
        .stat-card.red { border-bottom-color: #ef4444; }
        
        .stat-title { color: #888; font-size: 13px; margin-bottom: 5px; }
        .stat-value { font-size: 22px; font-weight: 700; color: #333; }

        /* Action Toolbar */
        .action-toolbar {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            border: 1px solid #eee;
        }
        .action-toolbar .btn { margin: 0 !important; border-radius: 4px; font-size: 12px; }
        .action-toolbar input, .action-toolbar select { font-size: 12px; height: 34px; }
        .sel-count { font-weight: bold; color: #666; margin: 0 10px; font-size: 12px; }

        /* Table Styling */
        .table { margin-bottom: 0; }
        .table thead th {
            background-color: #f4f6f9;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        .table tbody tr td { vertical-align: middle; padding: 12px 8px; }
        .table tbody tr:hover { background-color: #fcfcfc; }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: normal;
            color: #fff;
            min-width: 60px;
            text-align: center;
        }
        .status-active { background-color: #10b981; box-shadow: 0 2px 5px rgba(16, 185, 129, 0.3); }
        .status-block { background-color: #ef4444; box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3); }
        .status-unknown { background-color: #9ca3af; }

        /* Helpers */
        .w-auto { width: auto !important; display: inline-block; }
    </style>
</head>

<body>

<section id="container">
    <?php include("header.php"); ?>
    
    <section id="main-content">
        <section class="wrapper content-template">
            
            <div class="row">
                <div class="col-lg-12">
                    <section class="panel">
                        <header class="panel-heading">
                            <i class="fa fa-users"></i> لیست جامع کاربران
                        </header>
                        <div class="panel-body">
                            
                            <?php
                            $total = count($listusers); 
                            $activeCount = 0; 
                            $blockCount = 0;
                            foreach($listusers as $u){ 
                                $s = strtolower($u['User_Status']); 
                                if($s==='active') $activeCount++; 
                                else if($s==='block') $blockCount++; 
                            }
                            ?>
                            <div class="stat-grid">
                                <div class="stat-card blue">
                                    <div class="stat-title"><i class="fa fa-list"></i> کل کاربران</div>
                                    <div class="stat-value"><?php echo number_format($total); ?></div>
                                </div>
                                <div class="stat-card green">
                                    <div class="stat-title"><i class="fa fa-check-circle"></i> فعال</div>
                                    <div class="stat-value"><?php echo number_format($activeCount); ?></div>
                                </div>
                                <div class="stat-card red">
                                    <div class="stat-title"><i class="fa fa-ban"></i> مسدود</div>
                                    <div class="stat-value"><?php echo number_format($blockCount); ?></div>
                                </div>
                            </div>

                            <form class="filter-area" method="get">
                                <input type="text" name="q" class="form-control" style="width: 200px;" placeholder="جستجو (نام، آیدی، شماره)" value="<?php echo isset($_GET['q'])?htmlspecialchars($_GET['q']):''; ?>">
                                
                                <select name="status" class="form-control w-auto">
                                    <option value="">وضعیت: همه</option>
                                    <option value="active" <?php echo (isset($_GET['status']) && $_GET['status']==='active')?'selected':''; ?>>فعال</option>
                                    <option value="block" <?php echo (isset($_GET['status']) && $_GET['status']==='block')?'selected':''; ?>>مسدود</option>
                                </select>
                                
                                <select name="agent" class="form-control w-auto">
                                    <option value="">گروه کاربری: همه</option>
                                    <option value="f" <?php echo (isset($_GET['agent']) && $_GET['agent']==='f')?'selected':''; ?>>کاربر عادی</option>
                                    <option value="n" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n')?'selected':''; ?>>نماینده معمولی</option>
                                    <option value="n2" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n2')?'selected':''; ?>>نماینده پیشرفته</option>
                                </select>

                                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> فیلتر</button>
                                <a href="users.php" class="btn btn-default"><i class="fa fa-times"></i> پاک کردن</a>
                                <div style="margin-right: auto; display:flex; gap:5px;">
                                    <a href="#" class="btn btn-white tooltips" id="usersSaveFilter" data-original-title="ذخیره فیلتر فعلی"><i class="icon-save"></i></a>
                                    <a href="#" class="btn btn-white tooltips" id="usersLoadFilter" data-original-title="بارگذاری فیلتر ذخیره شده"><i class="icon-repeat"></i></a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> CSV</a>
                                </div>
                            </form>

                            <div class="action-toolbar sticky">
                                <a href="users.php" class="btn btn-info btn-sm" id="usersRefresh" title="بروزرسانی"><i class="icon-refresh"></i></a>
                                <input type="text" id="usersQuickSearch" class="form-control" placeholder="جستجوی سریع در صفحه..." style="max-width:180px;">
                                
                                <div class="btn-group">
                                    <a href="#" class="btn btn-default" id="usersSelectVisible" title="انتخاب همه"><i class="icon-check"></i></a>
                                    <a href="#" class="btn btn-default" id="usersInvertSelection" title="معکوس"><i class="icon-retweet"></i></a>
                                    <a href="#" class="btn btn-default" id="usersClearSelection" title="لغو"><i class="icon-remove"></i></a>
                                </div>
                                <span id="usersSelCount" class="sel-count">0 انتخاب</span>

                                <div class="btn-group">
                                    <a href="#" class="btn btn-danger" id="usersBlockSel">مسدود</a>
                                    <a href="#" class="btn btn-success" id="usersUnblockSel">رفع‌مسدودی</a>
                                </div>

                                <div class="input-group" style="width: 200px;">
                                    <input type="number" id="usersAmount" class="form-control" placeholder="مبلغ (تومان)">
                                    <div class="input-group-btn">
                                        <button type="button" class="btn btn-success" id="usersAddBalance" title="افزایش"><i class="icon-plus"></i></button>
                                        <button type="button" class="btn btn-warning" id="usersLowBalance" title="کسر"><i class="icon-minus"></i></button>
                                    </div>
                                </div>
                                
                                <div class="input-group" style="width: 250px;">
                                    <input type="text" id="usersMessage" class="form-control" placeholder="پیام گروهی...">
                                    <div class="input-group-btn">
                                        <button type="button" class="btn btn-info" id="usersSendMsg"><i class="icon-envelope"></i></button>
                                    </div>
                                </div>

                                <div class="btn-group">
                                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                        بیشتر <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu pull-right">
                                        <li><a href="#" id="usersCompact"><i class="icon-resize-small"></i> حالت فشرده</a></li>
                                        <li><a href="#" id="usersCopy"><i class="icon-copy"></i> کپی آیدی‌ها</a></li>
                                        <li><a href="#" id="usersPrint"><i class="icon-print"></i> چاپ</a></li>
                                        <li class="divider"></li>
                                        <li><a href="#" id="usersExportVisible">خروجی CSV (نمایش)</a></li>
                                        <li><a href="#" id="usersExportSelected">خروجی CSV (انتخاب)</a></li>
                                        <li class="divider"></li>
                                        <li><a href="#" id="usersPresetActive">فقط فعال‌ها</a></li>
                                        <li><a href="#" id="usersPresetBlock">فقط مسدودها</a></li>
                                    </ul>
                                </div>
                                
                                <select id="usersAgentSelect" class="form-control" style="width:120px;">
                                  <option value="">نوع کاربر...</option>
                                  <option value="f">عادی</option>
                                  <option value="n">نماینده</option>
                                  <option value="n2">ویژه</option>
                                </select>
                                <a href="#" class="btn btn-primary" id="usersApplyAgent"><i class="icon-ok"></i></a>
                                
                                <a href="#" class="btn btn-default tooltips" id="usersColumnsBtn" title="ستون‌ها"><i class="icon-th"></i></a>
                            </div>

                            <table class="table table-striped table-hover" id="sample_1">
                                <thead>
                                    <tr>
                                        <th style="width: 20px;"><input type="checkbox" class="group-checkable" data-set="#sample_1 .checkboxes" /></th>
                                        <th class="hidden-phone">شناسه (ID)</th>
                                        <th>نام کاربری</th>
                                        <th class="hidden-phone">شماره تماس</th>
                                        <th class="hidden-phone">موجودی (تومان)</th>
                                        <th class="hidden-phone">زیرمجموعه</th>
                                        <th class="hidden-phone">وضعیت</th>
                                        <th class="hidden-phone">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody> 
                                <?php foreach($listusers as $list): 
                                    $statusKey = strtolower($list['User_Status']);
                                    $statusText = ($statusKey === 'active') ? 'فعال' : (($statusKey === 'block') ? 'مسدود' : $list['User_Status']);
                                    $statusClass = 'status-' . (($statusKey === 'active' || $statusKey === 'block') ? $statusKey : 'unknown');
                                    
                                    $displayNumber = ($list['number'] == "none") ? '<span class="text-muted">---</span>' : $list['number'];
                                ?>
                                    <tr class="odd gradeX">
                                        <td><input type="checkbox" class="checkboxes" value="1" /></td>
                                        <td><?php echo $list['id']; ?></td>
                                        <td class="hidden-phone"><strong><?php echo $list['username']; ?></strong></td>
                                        <td class="hidden-phone"><?php echo $displayNumber; ?></td>
                                        <td class="hidden-phone text-success"><?php echo number_format($list['Balance']); ?></td>
                                        <td class="hidden-phone"><span class="badge"><?php echo $list['affiliatescount']; ?></span></td>
                                        <td class="hidden-phone">
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td class="hidden-phone">
                                            <a class="btn btn-xs btn-default" href="user.php?id=<?php echo $list['id']; ?>" title="مدیریت">
                                                <i class="fa fa-cog"></i> مدیریت
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
            </section>
    </section>
    </section>

<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.scrollTo.min.js"></script>
<script src="js/jquery.nicescroll.js" type="text/javascript"></script>
<script type="text/javascript" src="assets/data-tables/jquery.dataTables.js"></script>
<script type="text/javascript" src="assets/data-tables/DT_bootstrap.js"></script>

<script src="js/common-scripts.js"></script>

<script src="js/dynamic-table.js"></script>
<script>
    // JS Logic preserved exactly as original, just ensuring IDs match
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
    $('#usersPresetActive').on('click', function(e){ e.preventDefault(); var $f=$('form[method="get"]'); $f.find('select[name="status"]').val('active'); $f.submit(); });
    $('#usersPresetBlock').on('click', function(e){ e.preventDefault(); var $f=$('form[method="get"]'); $f.find('select[name="status"]').val('block'); $f.submit(); });
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
    setupSavedFilter('form[method="get"]','#usersSaveFilter','#usersLoadFilter','users');
    attachColumnToggles('#sample_1','#usersColumnsBtn');
    })();
</script>

</body>
</html>