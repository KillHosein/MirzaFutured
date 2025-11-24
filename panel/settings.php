<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION["user"])){
    header('Location: login.php');
    return;
}
$q = $pdo->prepare("SELECT * FROM admin WHERE username=:u");
$q->bindParam(':u', $_SESSION['user'], PDO::PARAM_STR);
$q->execute();
$adminRow = $q->fetch(PDO::FETCH_ASSOC);
if(!$adminRow){
    header('Location: login.php');
    return;
}
$saved = false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['action']) && $_POST['action']==='save_general' && isset($_POST['general']) && is_array($_POST['general'])){
        foreach($_POST['general'] as $k=>$v){
            $stmt = $pdo->prepare("UPDATE setting SET `$k` = :v");
            $stmt->bindParam(':v',$v);
            $stmt->execute();
        }
        $saved = true;
    }
    if(isset($_POST['action']) && $_POST['action']==='save_shop' && isset($_POST['shop']) && is_array($_POST['shop'])){
        foreach($_POST['shop'] as $k=>$v){
            $stmt = $pdo->prepare("UPDATE shopSetting SET value = :v WHERE Namevalue = :n");
            $stmt->bindParam(':v',$v);
            $stmt->bindParam(':n',$k);
            $stmt->execute();
        }
        $saved = true;
    }
    if(isset($_POST['action']) && $_POST['action']==='save_keyboard'){
        if(isset($_POST['keyboard_reset']) && $_POST['keyboard_reset']==='1'){
            $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
            $pdo->exec("UPDATE setting SET keyboardmain = " . $pdo->quote($keyboardmain));
        } else if(isset($_POST['keyboard_json'])){
            $kb = trim($_POST['keyboard_json']);
            $pdo->exec("UPDATE setting SET keyboardmain = " . $pdo->quote($kb));
        }
        $saved = true;
    }
}
$general = $pdo->query("SELECT * FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$shopRows = $pdo->query("SELECT * FROM shopSetting")->fetchAll(PDO::FETCH_ASSOC);
$shop = [];
foreach($shopRows as $r){ $shop[$r['Namevalue']] = $r['value']; }
$keyboardmain = $general['keyboardmain'] ?? '';
?>
<!DOCTYPE html>
<html lang="fa">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات ادمین</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-reset.css" rel="stylesheet">
    <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />
  </head>
  <body>
    <section id="container" class="">
      <?php include('header.php'); ?>
      <section id="main-content">
        <section class="wrapper">
          <?php if($saved){ ?>
          <div class="alert alert-success">تنظیمات ذخیره شد</div>
          <?php } ?>
          <div class="settings-grid">
            <section class="panel setting-card">
              <header class="panel-heading">تنظیمات عمومی</header>
              <div class="panel-body">
                <form method="post">
                  <input type="hidden" name="action" value="save_general" />
                  <div class="row">
                    <?php foreach($general as $k=>$v){ if($k==='keyboardmain') continue; ?>
                    <div class="col-lg-6">
                      <label><?php echo htmlspecialchars($k); ?></label>
                      <input name="general[<?php echo htmlspecialchars($k); ?>]" class="form-control" value="<?php echo htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>" />
                    </div>
                    <?php } ?>
                  </div>
                  <div style="margin-top:12px">
                    <button type="submit" class="btn btn-success">ذخیره</button>
                  </div>
                </form>
              </div>
            </section>
            <section class="panel setting-card">
              <header class="panel-heading">تنظیمات فروشگاه</header>
              <div class="panel-body">
                <form method="post">
                  <input type="hidden" name="action" value="save_shop" />
                  <div class="row">
                    <?php foreach($shop as $k=>$v){ ?>
                    <div class="col-lg-6">
                      <label><?php echo htmlspecialchars($k); ?></label>
                      <input name="shop[<?php echo htmlspecialchars($k); ?>]" class="form-control" value="<?php echo htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>" />
                    </div>
                    <?php } ?>
                  </div>
                  <div style="margin-top:12px">
                    <button type="submit" class="btn btn-success">ذخیره</button>
                  </div>
                </form>
              </div>
            </section>
            <section class="panel setting-card">
              <header class="panel-heading">چیدمان کیبورد ربات</header>
              <div class="panel-body">
                <form method="post">
                  <input type="hidden" name="action" value="save_keyboard" />
                  <div class="form-group">
                    <label>JSON کیبورد</label>
                    <textarea name="keyboard_json" class="form-control" rows="10" style="direction:ltr;"><?php echo htmlspecialchars($keyboardmain,ENT_QUOTES,'UTF-8'); ?></textarea>
                  </div>
                  <div class="form-group">
                    <label><input type="checkbox" name="keyboard_reset" value="1" /> بازنشانی به پیش‌فرض</label>
                  </div>
                  <button type="submit" class="btn btn-success">ذخیره</button>
                </form>
              </div>
            </section>
          </div>
        </section>
      </section>
    </section>
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/common-scripts.js"></script>
  </body>
</html>

