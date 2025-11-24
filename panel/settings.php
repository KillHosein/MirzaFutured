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
if(isset($_GET['export']) && $_GET['export']==='settings'){
    $generalAll = $pdo->query("SELECT * FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $shopRowsAll = $pdo->query("SELECT * FROM shopSetting")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $shopAll = [];
    foreach($shopRowsAll as $r){ $shopAll[$r['Namevalue']] = $r['value']; }
    $payload = [
        'general' => $generalAll,
        'shop' => $shopAll,
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=panel-settings-'.date('Y-m-d').'.json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
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
    if(isset($_POST['action']) && $_POST['action']==='import_settings'){
        $raw = $_POST['import_json'] ?? '';
        $data = json_decode($raw, true);
        if(is_array($data)){
            if(isset($data['general']) && is_array($data['general'])){
                foreach($data['general'] as $k=>$v){
                    $stmt = $pdo->prepare("UPDATE setting SET `$k` = :v");
                    $stmt->bindParam(':v',$v);
                    $stmt->execute();
                }
            }
            if(isset($data['shop']) && is_array($data['shop'])){
                foreach($data['shop'] as $k=>$v){
                    $stmt = $pdo->prepare("UPDATE shopSetting SET value = :v WHERE Namevalue = :n");
                    $stmt->bindParam(':v',$v);
                    $stmt->bindParam(':n',$k);
                    $stmt->execute();
                }
            }
            $saved = true;
        }
    }
}
$general = $pdo->query("SELECT * FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$shopRows = $pdo->query("SELECT * FROM shopSetting")->fetchAll(PDO::FETCH_ASSOC);
$shop = [];
foreach($shopRows as $r){ $shop[$r['Namevalue']] = $r['value']; }
$keyboardmain = $general['keyboardmain'] ?? '';
// Persian label mapping
$labelMapGeneral = [
  'iplogin' => 'آی‌پی مجاز ورود',
  'Channel_Support' => 'آی‌دی یا لینک کانال پشتیبانی',
  'Channel_Report' => 'کانال گزارشات سیستم',
  'domainhosts' => 'دامنه وب‌سایت',
];
$labelMapShop = [
  'products_per_page' => 'تعداد نمایش محصول در هر صفحه',
  'currency' => 'واحد پول',
  'gateway' => 'درگاه پرداخت پیش‌فرض',
];
?>
<!DOCTYPE html>
<html lang="fa">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات ادمین</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
                  <div class="form-grid">
                    <?php foreach($general as $k=>$v){ if($k==='keyboardmain') continue; $label = isset($labelMapGeneral[$k])?$labelMapGeneral[$k]:$k; ?>
                    <div class="form-field">
                      <label><?php echo htmlspecialchars($label); ?></label>
                      <input name="general[<?php echo htmlspecialchars($k); ?>]" class="form-control" value="<?php echo htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>" />
                    </div>
                    <?php } ?>
                  </div>
                  <div style="margin-top:12px">
                    <button type="submit" class="btn btn-success btn-space tooltips" data-original-title="ذخیره تنظیمات عمومی" aria-label="ذخیره تنظیمات عمومی"><i class="icon-save"></i> ذخیره</button>
                  </div>
                </form>
              </div>
            </section>
            <section class="panel setting-card">
              <header class="panel-heading">دسترسی با آی‌پی</header>
              <div class="panel-body">
                <form method="post">
                  <input type="hidden" name="action" value="save_general" />
                  <div class="row">
                    <div class="col-lg-6">
                      <label>آی‌پی مجاز ورود (iplogin)</label>
                      <input id="iploginField" name="general[iplogin]" class="form-control" value="<?php echo htmlspecialchars((string)($general['iplogin'] ?? ''),ENT_QUOTES,'UTF-8'); ?>" />
                    </div>
                    <div class="col-lg-6">
                      <label>آی‌پی فعلی شما</label>
                      <div class="ip-address" id="currentIp" style="display:inline-block; margin-left:10px;"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '',ENT_QUOTES,'UTF-8'); ?></div>
                      <button type="button" id="useMyIp" class="btn btn-info btn-space tooltips" data-original-title="قرار دادن آی‌پی فعلی" aria-label="استفاده از آی‌پی من"><i class="icon-location-arrow"></i> استفاده از آی‌پی من</button>
                    </div>
                  </div>
                  <div style="margin-top:12px">
                    <button type="submit" class="btn btn-success btn-space tooltips" data-original-title="ذخیره آی‌پی مجاز" aria-label="ذخیره آی‌پی"><i class="icon-save"></i> ذخیره</button>
                  </div>
                </form>
              </div>
            </section>
            <section class="panel setting-card">
              <header class="panel-heading">تنظیمات فروشگاه</header>
              <div class="panel-body">
                <form method="post">
                  <input type="hidden" name="action" value="save_shop" />
                  <div class="form-grid">
                    <?php foreach($shop as $k=>$v){ $label = isset($labelMapShop[$k])?$labelMapShop[$k]:$k; ?>
                    <div class="form-field">
                      <label><?php echo htmlspecialchars($label); ?></label>
                      <input name="shop[<?php echo htmlspecialchars($k); ?>]" class="form-control" value="<?php echo htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>" />
                    </div>
                    <?php } ?>
                  </div>
                  <div style="margin-top:12px">
                    <button type="submit" class="btn btn-success btn-space tooltips" data-original-title="ذخیره تنظیمات فروشگاه" aria-label="ذخیره فروشگاه"><i class="icon-save"></i> ذخیره</button>
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
                    <textarea name="keyboard_json" class="form-control" rows="10" style="direction:ltr;" placeholder='{"keyboard":[[{"text":"text_sell"},...]]}'><?php echo htmlspecialchars($keyboardmain,ENT_QUOTES,'UTF-8'); ?></textarea>
                  </div>
                  <div class="form-group">
                    <label><input type="checkbox" name="keyboard_reset" value="1" /> بازنشانی به پیش‌فرض</label>
                  </div>
                  <button type="submit" class="btn btn-success btn-space tooltips" data-original-title="ذخیره چیدمان کیبورد" aria-label="ذخیره کیبورد"><i class="icon-save"></i> ذخیره</button>
                </form>
              </div>
            </section>
          </div>
            <section class="panel setting-card">
              <header class="panel-heading">پشتیبان‌گیری و بازگردانی</header>
              <div class="panel-body">
                <div style="margin-bottom:10px;">
                  <a class="btn btn-primary btn-space tooltips" href="settings.php?export=settings" data-original-title="دانلود نسخه پشتیبان" aria-label="دانلود تنظیمات"><i class="icon-download"></i> دریافت فایل تنظیمات</a>
                </div>
                <form method="post">
                  <input type="hidden" name="action" value="import_settings" />
                  <div class="form-group">
                    <label>قرار دادن JSON تنظیمات برای بازگردانی</label>
                    <textarea name="import_json" class="form-control" rows="8" style="direction:ltr;" placeholder='{"general":{...},"shop":{...}}'></textarea>
                  </div>
                  <button type="submit" class="btn btn-warning btn-space tooltips" data-original-title="بازگردانی از JSON" aria-label="بازگردانی تنظیمات"><i class="icon-upload"></i> بازگردانی تنظیمات</button>
                </form>
              </div>
            </section>
        </section>
      </section>
    </section>
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/common-scripts.js"></script>
    <script>
      (function(){
        var btn = document.getElementById('useMyIp');
        var field = document.getElementById('iploginField');
        var ipEl = document.getElementById('currentIp');
        if(!btn || !field || !ipEl) return;
        btn.addEventListener('click', function(e){
          e.preventDefault();
          field.value = ipEl.textContent.trim();
        });
      })();
      (function(){
        var kbTextarea = document.querySelector('textarea[name="keyboard_json"]');
        if(kbTextarea){
          kbTextarea.addEventListener('blur', function(){
            var v = kbTextarea.value.trim();
            if(v.length === 0) return;
            try{ JSON.parse(v); kbTextarea.style.borderColor = '#10b981'; }
            catch(e){ kbTextarea.style.borderColor = '#ef4444'; }
          });
        }
      })();
    </script>
  </body>
</html>

