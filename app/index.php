<?php
require __DIR__ . '/lib/app_bootstrap.php';

$config = mirza_app_build_config($_SERVER);
$prefix = $config['prefix'];
$assetPrefix = $config['assetPrefix'];

$nonce = mirza_app_create_nonce();
$csp = mirza_app_build_csp($nonce);
mirza_app_send_headers($csp);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#ffffff" />
    <title>Mirza Web App</title>
    <base href="<?php echo htmlspecialchars($prefix, ENT_QUOTES); ?>" />
    <script src="<?php echo htmlspecialchars($assetPrefix . 'js/telegram-web-app.js', ENT_QUOTES); ?>"></script>
    <script nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES); ?>">
      window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script type="module" crossorigin src="<?php echo htmlspecialchars($assetPrefix . 'assets/runtime-ui.js', ENT_QUOTES); ?>"></script>
    <script type="module" crossorigin src="<?php echo htmlspecialchars($assetPrefix . 'assets/index-C-2a0Dur.js', ENT_QUOTES); ?>"></script>
    <link rel="modulepreload" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/vendor-CIGJ9g2q.js', ENT_QUOTES); ?>">
    <link rel="stylesheet" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/index-BoHBsj0Z.css', ENT_QUOTES); ?>">
    <link rel="stylesheet" crossorigin href="<?php echo htmlspecialchars($assetPrefix . 'assets/runtime-ui.css', ENT_QUOTES); ?>">
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>
