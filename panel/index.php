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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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
    <link href="assets/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet" />

  
<style>

/* =========================================================
   MODERN INDEX OVERRIDES (Append-only)
   Injected into index.php
========================================================= */

html, body{
  -webkit-font-smoothing: antialiased;
  text-rendering: optimizeLegibility;
}

/* background polish if page doesn't already define one */
body{
  background:
    radial-gradient(1200px 700px at 10% -10%, rgba(124,92,255,0.18), transparent 60%),
    radial-gradient(1200px 700px at 90% -20%, rgba(34,211,238,0.16), transparent 60%),
    linear-gradient(180deg, #070b14 0%, #0b1220 65%, #0a1020 100%);
}

/* section headers */
.section-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin: 8px 0 14px;
}
.section-title{
  font-size: 18px;
  font-weight: 800;
  color: var(--text-strong, #f8fafc);
  position: relative;
  padding-left: 12px;
}
.section-title::before{
  content:"";
  position:absolute;
  left:0; top:50%;
  transform: translateY(-50%);
  width:4px; height:18px;
  border-radius: 99px;
  background: linear-gradient(180deg, var(--primary, #7c5cff), var(--primary-2, #22d3ee));
}

/* cards/panels */
.card, .panel, .action-card, .chart-card, .stat-card{
  background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 22px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.25), 0 1px 0 rgba(255,255,255,0.04) inset;
  backdrop-filter: blur(6px);
  padding: 16px;
  transition: transform .15s cubic-bezier(.2,.8,.2,1),
              box-shadow .35s cubic-bezier(.2,.8,.2,1),
              border-color .15s cubic-bezier(.2,.8,.2,1);
}
.card:hover, .panel:hover, .action-card:hover, .chart-card:hover, .stat-card:hover{
  transform: translateY(-4px);
  box-shadow: 0 22px 70px rgba(0,0,0,0.45);
  border-color: rgba(124,92,255,0.35);
}

/* grids */
.action-grid{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap:16px;
  margin-top:16px;
}
.charts-grid{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap:16px;
  margin-top:16px;
}

/* action card internals */
.action-card{
  position: relative;
  display:flex;
  flex-direction:column;
  gap:6px;
  min-height:120px;
}
.action-card::before{
  content:"";
  position:absolute;
  inset:0 0 auto 0;
  height:4px;
  border-radius:22px 22px 0 0;
  background: linear-gradient(90deg, var(--primary, #7c5cff), var(--primary-2, #22d3ee));
  opacity:.85;
}
.action-card::after{
  content: attr(data-cat);
  position:absolute;
  top:10px; right:10px;
  font-size:11px; font-weight:800;
  color:#fff;
  padding:4px 8px;
  border-radius:999px;
  background: rgba(124,92,255,0.9);
}
.action-card i{ font-size:22px; color: var(--danger, #ff6b6b); }
.action-card .action-title{ font-weight:800; color: var(--text-strong, #f8fafc); }
.action-card .action-desc{ font-size:12px; color: var(--text-muted, #9aa4b2); }

/* buttons */
.btn, button, .action-btn{
  border:0;
  border-radius:999px;
  padding:10px 16px;
  font-weight:800;
  color:#fff;
  background: linear-gradient(135deg, var(--primary, #7c5cff), var(--primary-2, #22d3ee));
  box-shadow: 0 10px 30px rgba(124,92,255,0.35);
  cursor:pointer;
  transition: transform .15s cubic-bezier(.2,.8,.2,1),
              filter .15s cubic-bezier(.2,.8,.2,1),
              box-shadow .15s cubic-bezier(.2,.8,.2,1);
}
.btn:hover, button:hover, .action-btn:hover{
  transform: translateY(-2px);
  filter: brightness(1.08);
  box-shadow: 0 14px 40px rgba(34,211,238,0.35);
}

/* focus ring */
:focus-visible{
  outline:none !important;
  box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary, #7c5cff) 55%, transparent);
  border-radius:10px;
}

/* tables hover polish */
.table-hover>tbody>tr:hover>td,
.table-hover>tbody>tr:hover>th{
  background: color-mix(in oklab, var(--primary, #7c5cff) 8%, transparent);
  transform: translateY(-1px);
}

/* responsive stacking */
@media (max-width: 768px){
  .action-grid, .charts-grid{ grid-template-columns: 1fr !important; }
  .btn, button{ width:100%; }
}

@media (prefers-reduced-motion: reduce){
  *{ transition:none !important; animation:none !important; }
}

</style>
</head>

  <body>

  <section id="container" class="">
  <?php include("header.php");
?>
      <!--main content start-->
      <section id="main-content">
          <section class="wrapper content-template">
              <div class="row">
                  <div class="col-lg-12">
                      <section class="panel">
                          <header class="panel-heading">فیلتر داشبورد</header>
                          <div class="panel-body">
                              <form class="form-inline" method="get" id="dashboardFilterForm">
                                  <div class="form-group" style="margin-left:8px;">
                                      <label style="margin-left:6px;">بازه تاریخ</label>
                                      <input type="text" id="rangePicker" class="form-control" placeholder="انتخاب بازه" style="min-width:220px;">
                                      <input type="hidden" name="from" id="rangeFrom" value="<?php echo htmlspecialchars(isset($_GET['from'])?$_GET['from']:'',ENT_QUOTES,'UTF-8'); ?>">
                                      <input type="hidden" name="to" id="rangeTo" value="<?php echo htmlspecialchars(isset($_GET['to'])?$_GET['to']:'',ENT_QUOTES,'UTF-8'); ?>">
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
                                  <div class="btn-group" style="margin-right:8px;">
                                    <a href="#" class="btn btn-info" id="preset7d">۷ روز اخیر</a>
                                    <a href="#" class="btn btn-info" id="presetMonth">ماه جاری</a>
                                    <a href="#" class="btn btn-info" id="presetYear">سال جاری</a>
                                  </div>
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
              <div class="chart-card" data-chart="sales">
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
                  <div class="chart-card" data-chart="status">
                      <canvas id="statusChart" height="140"></canvas>
                  </div>
                  <div class="chart-card" data-chart="users">
                      <canvas id="usersChart" height="140"></canvas>
                  </div>
              </div>
              <section class="panel" style="margin-top:12px;">
                <header class="panel-heading">سفارشی‌سازی داشبورد</header>
                <div class="panel-body">
                  <div id="dashPrefs">
                    <div class="row">
                      <div class="col-sm-4"><label><input type="checkbox" v-model="show.status"> نمایش نمودار وضعیت سفارشات</label></div>
                      <div class="col-sm-4"><label><input type="checkbox" v-model="show.users"> نمایش نمودار کاربران جدید</label></div>
                      <div class="col-sm-4"><label><input type="checkbox" v-model="show.sales"> نمایش نمودار فروش</label></div>
                    </div>
                  </div>
                </div>
              </section>
              <div class="action-toolbar sticky">
                  <div class="btn-group" role="group" aria-label="فیلتر اقدامات">
                      <button class="btn btn-default btn-sm" data-filter="all">همه</button>
                      <button class="btn btn-default btn-sm" data-filter="fav">پرکاربرد</button>
                      <button class="btn btn-default btn-sm" data-filter="group-finance">مالی</button>
                      <button class="btn btn-default btn-sm" data-filter="group-ops">عملیات</button>
                      <button class="btn btn-default btn-sm" data-filter="group-catalog">محصولات</button>
                      <button class="btn btn-default btn-sm" data-filter="group-settings">تنظیمات</button>
                      <button class="btn btn-default btn-sm" data-filter="group-users">کاربران</button>
                      <button class="btn btn-default btn-sm" data-filter="group-urgent">اضطراری</button>
                  </div>
                  <div style="flex:1"></div>
                  <button class="btn btn-outline-default btn-sm" id="toggleLayoutEdit"><i class="icon-edit"></i> ویرایش چیدمان</button>
                  <button class="btn btn-outline-warning btn-sm" id="resetLayout"><i class="icon-refresh"></i> بازنشانی</button>
                  <button class="btn btn-info btn-sm" id="openCmdPal"><i class="icon-search"></i> جستجو</button>
              </div>
              <div class="action-grid">
                  <a class="action-card group-finance" href="invoice.php" data-action-id="orders" data-cat="مالی" draggable="true" aria-label="مدیریت سفارشات">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
                      <i class="icon-shopping-cart"></i>
                      <div class="action-title">سفارشات</div>
                      <div class="action-desc">مدیریت و بررسی فاکتورها</div>
                  </a>
                  <a class="action-card group-ops" href="inbound.php" data-action-id="inbounds" data-cat="عملیات" draggable="true" aria-label="مدیریت ورودی‌ها">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
                      <i class="icon-exchange"></i>
                      <div class="action-title">ورودی‌ها</div>
                      <div class="action-desc">کنترل ورودی‌ها و جریان‌ها</div>
                  </a>
                  <a class="action-card group-urgent" href="cancelService.php" data-action-id="cancel" data-cat="اضطراری" draggable="true" aria-label="حذف سرویس">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
                      <i class="icon-trash"></i>
                      <div class="action-title">حذف سرویس</div>
                      <div class="action-desc">لغو و پاک‌سازی سرویس‌ها</div>
                  </a>
                  <a class="action-card group-settings" href="keyboard.php" data-action-id="keyboard" data-cat="تنظیمات" draggable="true" aria-label="چیدمان کیبورد">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
                      <i class="icon-th"></i>
                      <div class="action-title">چیدمان کیبورد</div>
                      <div class="action-desc">بهینه‌سازی چینش دکمه‌ها</div>
                  </a>
                  <a class="action-card group-finance" href="payment.php" data-action-id="payments" data-cat="مالی" draggable="true" aria-label="پرداخت‌ها">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
                      <i class="icon-credit-card"></i>
                      <div class="action-title">پرداخت‌ها</div>
                      <div class="action-desc">پیگیری تراکنش‌ها</div>
                  </a>
                  <a class="action-card group-catalog" href="product.php" data-action-id="products" data-cat="محصولات" draggable="true" aria-label="مدیریت محصولات">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
                      <i class="icon-archive"></i>
                      <div class="action-title">مدیریت محصولات</div>
                      <div class="action-desc">ساخت و تغییر محصولات</div>
                  </a>
                  <a class="action-card group-catalog" href="productedit.php" data-action-id="productedit" data-cat="محصولات" draggable="true" aria-label="ویرایش محصول">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
                      <i class="icon-edit"></i>
                      <div class="action-title">ویرایش محصول</div>
                      <div class="action-desc">به‌روزرسانی جزئیات</div>
                  </a>
                  <a class="action-card group-users" href="user.php" data-action-id="user" data-cat="کاربران" draggable="true" aria-label="مدیریت کاربر">
                      <div class="fav-toggle tooltips" data-original-title="نشانه‌گذاری به‌عنوان پرکاربرد"><i class="icon-star"></i></div>
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
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.scrollTo.min.js"></script>
    <script src="js/jquery.nicescroll.js" type="text/javascript"></script>
    <script src="js/jquery.sparkline.js" type="text/javascript"></script>
    <script src="js/owl.carousel.js" ></script>
    <script src="js/jquery.customSelect.min.js" ></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js" defer></script>
    <script src="assets/bootstrap-daterangepicker/date.js"></script>
    <script src="assets/bootstrap-daterangepicker/daterangepicker.js"></script>
    

    <!--common script for all pages-->
    <script src="js/common-scripts.js"></script>
    <script>
      (function(){
        var from = document.getElementById('rangeFrom').value;
        var to = document.getElementById('rangeTo').value;
        var $input = $('#rangePicker');
        if(!$input.length) return;
        var start = from ? new Date(from) : new Date();
        var end = to ? new Date(to) : new Date();
        var fmt = function(d){ var yyyy=d.getFullYear(); var mm=('0'+(d.getMonth()+1)).slice(-2); var dd=('0'+d.getDate()).slice(-2); return yyyy+'-'+mm+'-'+dd; };
        $input.daterangepicker({
          startDate: start,
          endDate: end,
          locale: { direction: 'rtl' }
        }, function(start, end){
          $('#rangeFrom').val(fmt(start.toDate()));
          $('#rangeTo').val(fmt(end.toDate()));
        });
        if(from && to){
          $input.val(from + ' - ' + to);
        }
        function fmt(d){ var yyyy=d.getFullYear(); var mm=('0'+(d.getMonth()+1)).slice(-2); var dd=('0'+d.getDate()).slice(-2); return yyyy+'-'+mm+'-'+dd; }
        $('#preset7d').on('click',function(e){ e.preventDefault(); var end=new Date(); var start=new Date(); start.setDate(end.getDate()-6); $('#rangeFrom').val(fmt(start)); $('#rangeTo').val(fmt(end)); $('#dashboardFilterForm')[0].submit(); });
        $('#presetMonth').on('click',function(e){ e.preventDefault(); var end=new Date(); var start=new Date(end.getFullYear(), end.getMonth(), 1); $('#rangeFrom').val(fmt(start)); $('#rangeTo').val(fmt(end)); $('#dashboardFilterForm')[0].submit(); });
        $('#presetYear').on('click',function(e){ e.preventDefault(); var end=new Date(); var start=new Date(end.getFullYear(), 0, 1); $('#rangeFrom').val(fmt(start)); $('#rangeTo').val(fmt(end)); $('#dashboardFilterForm')[0].submit(); });
      })();
    </script>
    <script>
      (function(){
        if(!window.Vue) return;
        var app = Vue.createApp({
          data(){ return { show: JSON.parse(localStorage.getItem('dash_show')||'{"status":true,"users":true,"sales":true}') } },
          watch:{ show:{ deep:true, handler:function(v){ localStorage.setItem('dash_show', JSON.stringify(v)); toggleCharts(v); } } },
          mounted(){ toggleCharts(this.show); lazyInitCharts(); }
        });
        app.mount('#dashPrefs');
        function toggleCharts(s){
          var statusEl = document.querySelector('[data-chart="status"]'); if(statusEl) statusEl.style.display = s.status ? '' : 'none';
          var usersEl = document.querySelector('[data-chart="users"]'); if(usersEl) usersEl.style.display = s.users ? '' : 'none';
          var salesCanvas = document.getElementById('salesChart'); if(salesCanvas){ var card = salesCanvas.parentElement; card.style.display = s.sales ? '' : 'none'; }
        }
        function lazyInitCharts(){
          if(!('IntersectionObserver' in window)) return;
          var io = new IntersectionObserver(function(entries){ entries.forEach(function(en){ if(en.isIntersecting){ /* charts already instantiate by original script */ } }); }, { threshold: 0.1 });
          document.querySelectorAll('.chart-card').forEach(function(el){ io.observe(el); });
          var salesCanvas = document.getElementById('salesChart'); if(salesCanvas) io.observe(salesCanvas);
        }
      })();
    </script>
  </body>
</html>
