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
            <section class="wrapper">
                <!-- page start-->
                <div class="row">
                    <div class="col-lg-12">
                        <section class="panel">
                            <header class="panel-heading">لیست کاربران</header>
                            <div class="panel-body">
                                <form class="form-inline" method="get">
                                    <div class="form-group" style="margin-left:8px;">
                                        <input type="text" name="q" class="form-control" placeholder="جستجو نام کاربری/آیدی/شماره" value="<?php echo isset($_GET['q'])?htmlspecialchars($_GET['q']):''; ?>">
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <select name="status" class="form-control">
                                            <option value="">همه وضعیت‌ها</option>
                                            <option value="active" <?php echo (isset($_GET['status']) && $_GET['status']==='active')?'selected':''; ?>>فعال</option>
                                            <option value="block" <?php echo (isset($_GET['status']) && $_GET['status']==='block')?'selected':''; ?>>مسدود</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-left:8px;">
                                        <select name="agent" class="form-control">
                                            <option value="">همه گروه‌ها</option>
                                            <option value="f" <?php echo (isset($_GET['agent']) && $_GET['agent']==='f')?'selected':''; ?>>کاربر عادی</option>
                                            <option value="n" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n')?'selected':''; ?>>نماینده معمولی</option>
                                            <option value="n2" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n2')?'selected':''; ?>>نماینده پیشرفته</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">فیلتر</button>
                                    <a href="users.php" class="btn btn-default">پاک کردن</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn btn-success">خروجی CSV</a>
                                </form>
                                <div class="action-toolbar">
                                    <a href="users.php" class="btn btn-default" id="usersRefresh"><i class="icon-refresh"></i> بروزرسانی</a>
                                    <a href="#" class="btn btn-info" id="usersCompact"><i class="icon-resize-small"></i> حالت فشرده</a>
                                    <a href="#" class="btn btn-primary" id="usersCopy"><i class="icon-copy"></i> کپی آیدی‌های انتخاب‌شده</a>
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
                                foreach($listusers as $list){
                                    $statusKey = strtolower($list['User_Status']);
                                    $status_user = [
                                        'active' => "فعال",
                                        'block' => "مسدود",
                                    ][$statusKey] ?? $list['User_Status'];
                                    $status_color = $statusKey==='active' ? '#10b981' : ($statusKey==='block' ? '#ef4444' : '#6b7280');
                                    if($list['number'] == "none")$list['number'] ="بدون شماره ";
                                   echo "<tr class=\"odd gradeX\">\n                                        <td>\n                                        <input type=\"checkbox\" class=\"checkboxes\" value=\"1\" /></td>\n                                        <td>{$list['id']}</td>\n                                        <td class=\"hidden-phone\">{$list['username']}</td>\n                                        <td class=\"hidden-phone\">{$list['number']}</td>\n                                        <td class=\"hidden-phone\">".number_format($list['Balance'])."</td>\n                                        <td class=\"hidden-phone\">{$list['affiliatescount']}</td>\n                                        <td class=\"hidden-phone\"><span class=\"badge\" style=\"background-color:{$status_color}\">{$status_user}</span></td>\n                                        <td class=\"hidden-phone\">\n                                        <a class = \"btn btn-success\" href= \"user.php?id={$list['id']}\">مدیریت کاربر </a></td>\n                                    </tr>";
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
      })();
    </script>


</body>
</html>
