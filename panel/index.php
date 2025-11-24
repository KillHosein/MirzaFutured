<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';
$datefirstday = time() - 86400;
$fromDate = isset($_GET['from']) ? $_GET['from'] : null;
$toDate = isset($_GET['to']) ? $_GET['to'] : null;
$selectedStatuses = isset($_GET['status']) ? $_GET['status'] : [];
if(!is_array($selectedStatuses) && !empty($selectedStatuses)) $selectedStatuses = [$selectedStatuses];
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    return;
}
    $invoiceWhere = ["name_product != 'سرویس تست'"];
    $invoiceParams = [];
    if($fromDate && strtotime($fromDate)){
        $invoiceWhere[] = "time_sell >= :fromTs";
        $invoiceParams[':fromTs'] = strtotime($fromDate);
    }
    if($toDate && strtotime($toDate)){
        $invoiceWhere[] = "time_sell <= :toTs";
        $invoiceParams[':toTs'] = strtotime($toDate.' 23:59:59');
    }
    if(!empty($selectedStatuses)){
        $ph = [];
        foreach($selectedStatuses as $i => $st){
            $k = ":st$i";
            $ph[] = $k;
            $invoiceParams[$k] = $st;
        }
        $invoiceWhere[] = "status IN (".implode(',', $ph).")";
    }else{
        $invoiceWhere[] = "(status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold')";
    }
    $invoiceWhereSql = implode(' AND ', $invoiceWhere);
    $query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE $invoiceWhereSql");
    $query->execute($invoiceParams);
    $subinvoice = $query->fetch(PDO::FETCH_ASSOC);
    $query = $pdo->prepare("SELECT * FROM user");
    $query->execute();
    $resultcount = $query->rowCount();
    $time = strtotime(date('Y/m/d'));
    $stmt = $pdo->prepare("SELECT * FROM user WHERE register > :time_register AND register != 'none'");
    $stmt->bindParam(':time_register', $datefirstday);
    $stmt->execute();
    $resultcountday = $stmt->rowCount();
    $query = $pdo->prepare("SELECT  * FROM invoice WHERE $invoiceWhereSql");
    $query->execute($invoiceParams);
    $resultcontsell = $query->rowCount();
    $subinvoice['SUM(price_product)'] = number_format($subinvoice['SUM(price_product)']);
    if($resultcontsell != 0){
    $query = $pdo->prepare("SELECT time_sell,price_product FROM invoice WHERE $invoiceWhereSql ORDER BY time_sell DESC;");
    $query->execute($invoiceParams);
    $salesData = $query->fetchAll();
    $grouped_data = [];
    foreach ($salesData as $sell){
        if(count($grouped_data) > 15)break;
        if(!is_numeric($sell['time_sell']))continue;
        $time = date('Y/m/d',$sell['time_sell']);
        $price = (int)$sell['price_product'];
        if (!isset($grouped_data[$time])) {
        $grouped_data[$time] = ['total_amount' => 0, 'order_count' => 0];
        }
        $grouped_data[$time]['total_amount'] += $price;
        $grouped_data[$time]['order_count'] += 1;
    }
    $max_amount = max(array_map(function($info) { return $info['total_amount']; }, $grouped_data)) ?: 1;
    }
    $statusLabels = [];
    $statusData = [];
    $statusColors = [];
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM invoice WHERE $invoiceWhereSql GROUP BY status");
    $stmt->execute($invoiceParams);
    $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statusMapFa = [
        'unpaid' => 'در انتظار پرداخت',
        'active' => 'فعال',
        'disabledn' => 'ناموجود',
        'end_of_time' => 'پایان زمان',
        'end_of_volume' => 'پایان حجم',
        'sendedwarn' => 'هشدار',
        'send_on_hold' => 'در انتظار اتصال',
        'removebyuser' => 'حذف توسط کاربر'
    ];
    $colorMap = [
        'unpaid' => '#f59e0b',
        'active' => '#10b981',
        'disabledn' => '#6b7280',
        'end_of_time' => '#ef4444',
        'end_of_volume' => '#3b82f6',
        'sendedwarn' => '#8b5cf6',
        'send_on_hold' => '#f97316',
        'removebyuser' => '#9ca3af'
    ];
    foreach($statusRows as $r){
        $k = $r['status'];
        $statusLabels[] = isset($statusMapFa[$k]) ? $statusMapFa[$k] : $k;
        $statusData[] = (int)$r['cnt'];
        $statusColors[] = isset($colorMap[$k]) ? $colorMap[$k] : '#999999';
    }
    $daysBack = 14;
    $userStart = ($fromDate && strtotime($fromDate)) ? strtotime($fromDate) : (strtotime(date('Y/m/d')) - ($daysBack-1)*86400);
    $userEnd = ($toDate && strtotime($toDate)) ? strtotime($toDate.' 23:59:59') : strtotime(date('Y/m/d'));
    $daysBack = max(1, floor(($userEnd - $userStart)/86400)+1);
    $stmt = $pdo->prepare("SELECT register FROM user WHERE register != 'none' AND register BETWEEN :ustart AND :uend");
    $stmt->bindParam(':ustart',$userStart,PDO::PARAM_INT);
    $stmt->bindParam(':uend',$userEnd,PDO::PARAM_INT);
    $stmt->execute();
    $regRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userLabels = [];
    $userCounts = [];
    $indexByDate = [];
    for($i=0;$i<$daysBack;$i++){
        $d = strtotime(date('Y/m/d', $userStart + $i*86400));
        $key = date('Y/m/d',$d);
        $indexByDate[$key] = count($userLabels);
        $userLabels[] = jdate('Y/m/d',$d);
        $userCounts[] = 0;
    }
    foreach($regRows as $row){
        if(!is_numeric($row['register'])) continue;
        $key = date('Y/m/d', (int)$row['register']);
        if(isset($indexByDate[$key])){
            $userCounts[$indexByDate[$key]]++;
        }
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
    <link rel="stylesheet" href="css/owl.carousel.css" type="text/css">
    <!-- Custom styles for this template -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />

  </head>

  <body>

  <section id="container" class="">
  <?php include("header.php");
?>
      <!--main content start-->
      <section id="main-content">
          <section class="wrapper">
              <div class="row">
                  <div class="col-lg-12">
                      <section class="panel">
                          <header class="panel-heading">فیلتر داشبورد</header>
                          <div class="panel-body">
                              <form class="form-inline" method="get">
                                  <div class="form-group" style="margin-left:8px;">
                                      <label style="margin-left:6px;">از تاریخ</label>
                                      <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars(isset($_GET['from'])?$_GET['from']:'',ENT_QUOTES,'UTF-8'); ?>">
                                  </div>
                                  <div class="form-group" style="margin-left:8px;">
                                      <label style="margin-left:6px;">تا تاریخ</label>
                                      <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars(isset($_GET['to'])?$_GET['to']:'',ENT_QUOTES,'UTF-8'); ?>">
                                  </div>
                                  <div class="form-group" style="margin-left:8px;">
                                      <label style="margin-left:6px;">وضعیت سفارش</label>
                                      <select name="status[]" multiple class="form-control" style="min-width:220px;">
                                          <?php foreach(['unpaid'=>'در انتظار پرداخت','active'=>'فعال','disabledn'=>'ناموجود','end_of_time'=>'پایان زمان','end_of_volume'=>'پایان حجم','sendedwarn'=>'هشدار','send_on_hold'=>'در انتظار اتصال','removebyuser'=>'حذف توسط کاربر'] as $sk=>$sl): ?>
                                              <option value="<?php echo $sk; ?>" <?php echo in_array($sk,$selectedStatuses)?'selected':''; ?>><?php echo $sl; ?></option>
                                          <?php endforeach; ?>
                                      </select>
                                  </div>
                                  <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                                  <a href="index.php" class="btn btn-default">پاک کردن</a>
                              </form>
                          </div>
                      </section>
                  </div>
              </div>
              <!--state overview start-->
              <div class="row state-overview">
                  <div class="col-lg-3 col-sm-6">
                      <section class="panel">
                          <div class="symbol terques">
                              <i class="icon-user"></i>
                          </div>
                          <div class="value">
                              <h1><?php echo $resultcount; ?></h1>
                              <p>تعداد کاربران</p>
                          </div>
                      </section>
                  </div>
                  <div class="col-lg-3 col-sm-6">
                      <section class="panel">
                          <div class="symbol red">
                              <i class="icon-tags"></i>
                          </div>
                          <div class="value">
                              <h1><?php echo $resultcontsell; ?></h1>
                              <p>تعداد فروش کل</p>
                          </div>
                      </section>
                  </div>
                  <div class="col-lg-3 col-sm-6">
                      <section class="panel">
                          <div class="symbol blue">
                              <i class="icon-bar-chart"></i>
                          </div>
                          <div class="value">
                              <h1 style = "font-size:19px"><?php echo $subinvoice['SUM(price_product)']; ?> تومان </h1>
                              <p>جمغ کل فروش</p>
                          </div>
                      </section>
                  </div>
                  <div class="col-lg-3 col-sm-6">
                      <section class="panel">
                          <div class="symbol yellow">
                              <i class="icon-user"></i>
                          </div>
                          <div class="value">
                              <h1><?php echo $resultcountday; ?></h1>
                              <p>کاربران جدید امروز</p>
                          </div>
                      </section>
                  </div>

              </div>
              <?php if($resultcontsell != 0 ){?>
              <div class="titlechart">
                  <h3 class = "title">چارت فروش</h3>
              </div>
              <?php
              $labels = [];
              $amounts = [];
              $counts = [];
              foreach ($grouped_data as $date => $info) {
                  $labels[] = jdate('Y/m/d', strtotime($date));
                  $amounts[] = (int)$info['total_amount'];
                  $counts[] = (int)$info['order_count'];
              }
              ?>
              <div style="background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);padding:16px">
                  <canvas id="salesChart" height="120"></canvas>
              </div>
              <script>
              (function(){
                var labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
                var data = <?php echo json_encode($amounts); ?>;
                var counts = <?php echo json_encode($counts); ?>;
                var ctx = document.getElementById('salesChart').getContext('2d');
                new Chart(ctx, {
                  type: 'bar',
                  data: {
                    labels: labels,
                    datasets: [
                      {
                        label: 'مبلغ فروش',
                        data: data,
                        backgroundColor: 'rgba(255,108,96,0.35)',
                        borderColor: '#ff6c60',
                        borderWidth: 2,
                        borderRadius: 8,
                        maxBarThickness: 32
                      },
                      {
                        type: 'line',
                        label: 'تعداد سفارش',
                        data: counts,
                        borderColor: '#57c8f2',
                        backgroundColor: 'rgba(87,200,242,0.2)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false,
                        yAxisID: 'y'
                      }
                    ]
                  },
                  options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                      legend: { display: false },
                      tooltip: {
                        callbacks: {
                          label: function(context){
                            var v = context.parsed.y || 0;
                            return v.toLocaleString('fa-IR') + ' تومان';
                          }
                        }
                      }
                    },
                    scales: {
                      x: {
                        grid: { display: false },
                        ticks: { color: '#6b7280', font: { family: 'Vazirmatn' } }
                      },
                      y: {
                        grid: { color: 'rgba(0,0,0,0.06)' },
                        ticks: {
                          color: '#6b7280',
                          font: { family: 'Vazirmatn' },
                          callback: function(value){
                            return value.toLocaleString('fa-IR');
                          }
                        }
                      }
                    }
                  }
                });
                var statusCtx = document.getElementById('statusChart').getContext('2d');
                new Chart(statusCtx, {
                  type: 'doughnut',
                  data: {
                    labels: <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                      data: <?php echo json_encode($statusData); ?>,
                      backgroundColor: <?php echo json_encode($statusColors); ?>,
                      borderColor: '#fff',
                      borderWidth: 2
                    }]
                  },
                  options: {
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '60%'
                  }
                });
                var usersCtx = document.getElementById('usersChart').getContext('2d');
                new Chart(usersCtx, {
                  type: 'line',
                  data: {
                    labels: <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                      label: 'کاربران جدید',
                      data: <?php echo json_encode($userCounts); ?>,
                      borderColor: '#10b981',
                      backgroundColor: 'rgba(16,185,129,0.2)',
                      tension: 0.3,
                      fill: true,
                      pointRadius: 3,
                      pointHoverRadius: 4
                    }]
                  },
                  options: {
                    plugins: { legend: { display: false } },
                    scales: {
                      x: { grid: { display: false } },
                      y: { grid: { color: 'rgba(0,0,0,0.06)' }, beginAtZero: true }
                    }
                  }
                });
              })();
              </script>
              <?php  } ?>
              <div class="charts-grid">
                  <div class="chart-card">
                      <canvas id="statusChart" height="140"></canvas>
                  </div>
                  <div class="chart-card">
                      <canvas id="usersChart" height="140"></canvas>
                  </div>
              </div>
              <div class="action-grid">
                  <a class="action-card" href="invoice.php">
                      <i class="icon-shopping-cart"></i>
                      <div class="action-title">سفارشات</div>
                      <div class="action-desc">مدیریت و بررسی فاکتورها</div>
                  </a>
                  <a class="action-card" href="inbound.php">
                      <i class="icon-exchange"></i>
                      <div class="action-title">ورودی‌ها</div>
                      <div class="action-desc">کنترل ورودی‌ها و جریان‌ها</div>
                  </a>
                  <a class="action-card" href="cancelService.php">
                      <i class="icon-trash"></i>
                      <div class="action-title">حذف سرویس</div>
                      <div class="action-desc">لغو و پاک‌سازی سرویس‌ها</div>
                  </a>
                  <a class="action-card" href="keyboard.php">
                      <i class="icon-th"></i>
                      <div class="action-title">چیدمان کیبورد</div>
                      <div class="action-desc">بهینه‌سازی چینش دکمه‌ها</div>
                  </a>
                  <a class="action-card" href="payment.php">
                      <i class="icon-credit-card"></i>
                      <div class="action-title">پرداخت‌ها</div>
                      <div class="action-desc">پیگیری تراکنش‌ها</div>
                  </a>
                  <a class="action-card" href="product.php">
                      <i class="icon-archive"></i>
                      <div class="action-title">مدیریت محصولات</div>
                      <div class="action-desc">ساخت و تغییر محصولات</div>
                  </a>
                  <a class="action-card" href="productedit.php">
                      <i class="icon-edit"></i>
                      <div class="action-title">ویرایش محصول</div>
                      <div class="action-desc">به‌روزرسانی جزئیات</div>
                  </a>
                  <a class="action-card" href="user.php">
                      <i class="icon-user"></i>
                      <div class="action-title">مدیریت کاربر</div>
                      <div class="action-desc">تنظیمات و وضعیت</div>
                  </a>
              </div>
          </section>
      </section>
      <!--main content end-->
  </section>

    <!-- js placed at the end of the document so the pages load faster -->
    <script src="js/jquery.js"></script>
    <script src="js/jquery-1.8.3.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.scrollTo.min.js"></script>
    <script src="js/jquery.nicescroll.js" type="text/javascript"></script>
    <script src="js/jquery.sparkline.js" type="text/javascript"></script>
    <script src="js/owl.carousel.js" ></script>
    <script src="js/jquery.customSelect.min.js" ></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    

    <!--common script for all pages-->
    <script src="js/common-scripts.js"></script>
  </body>
</html>
