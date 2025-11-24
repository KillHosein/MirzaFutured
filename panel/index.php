<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';
$datefirstday = time() - 86400;
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    return;
}
    $query = $pdo->prepare("SELECT SUM(price_product) FROM invoice  WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'");
    $query->execute();
    $subinvoice = $query->fetch(PDO::FETCH_ASSOC);
    $query = $pdo->prepare("SELECT * FROM user");
    $query->execute();
    $resultcount = $query->rowCount();
    $time = strtotime(date('Y/m/d'));
    $stmt = $pdo->prepare("SELECT * FROM user WHERE register > :time_register AND register != 'none'");
    $stmt->bindParam(':time_register', $datefirstday);
    $stmt->execute();
    $resultcountday = $stmt->rowCount();
    $query = $pdo->prepare("SELECT  * FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'");
    $query->execute();
    $resultcontsell = $query->rowCount();
    $subinvoice['SUM(price_product)'] = number_format($subinvoice['SUM(price_product)']);
    if($resultcontsell != 0){
    $query = $pdo->prepare("SELECT time_sell,price_product FROM invoice ORDER BY time_sell DESC;");
    $query->execute();
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
              })();
              </script>
              <?php  } ?>
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
