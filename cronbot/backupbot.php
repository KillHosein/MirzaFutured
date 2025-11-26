<?php
if (PHP_SAPI === 'cli' && isset($argv)) {
    $GLOBAL_FORCE_BACKUP = false;
    foreach ($argv as $arg) {
        if ($arg === '--force' || $arg === '-f' || $arg === '--now') { $GLOBAL_FORCE_BACKUP = true; break; }
    }
    if ($GLOBAL_FORCE_BACKUP && !defined('FORCE_BACKUP')) {
        define('FORCE_BACKUP', true);
    }
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

$rbRow = select("topicid","idreport","report","general","select");
$reportbackup = is_array($rbRow) && isset($rbRow['idreport']) ? $rbRow['idreport'] : null;
$destination = __DIR__;
$setting = select("setting", "*");
$minFreeBytes = 30 * 1024 * 1024;
if (!is_writable($destination)) {
    echo date('Y-m-d H:i:s') . " destination not writable: $destination, switching to temp dir\n";
    $destination = sys_get_temp_dir();
}
if (function_exists('disk_free_space')) {
    $free = @disk_free_space($destination);
    if ($free !== false && $free < $minFreeBytes) {
        $payload = [
            'chat_id' => $setting['Channel_Report'],
            'text' => "❌ فضای ناکافی برای ایجاد فایل‌های بکاپ",
        ];
        if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
        telegram('sendmessage', $payload);
        echo date('Y-m-d H:i:s') . " insufficient disk space: " . intval($free) . " bytes\n";
        exit;
    }
}
try{
    $pdo->query('SELECT 1');
}catch(Throwable $e){
    $payload = [
        'chat_id' => $setting['Channel_Report'],
        'text' => "❌ عدم دسترسی به دیتابیس برای بکاپ",
    ];
    if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
    telegram('sendmessage', $payload);
    echo date('Y-m-d H:i:s') . " database connectivity check failed\n";
}
$sourcefir = dirname(__DIR__);
// Auto-backup gating per bot
function run_backup_cycle($destination, $sourcefir, $setting, $reportbackup, $forceArg = false){
    global $domainhosts, $dbname, $usernamedb, $passworddb, $pdo;
    $botlist = select("botsaz","*",null,null,"fetchAll", ['cache' => false]);
    $autoTriggered = false;
    foreach ($botlist ?: [] as $bot){
        $botSetting = json_decode($bot['setting'] ?? '{}', true);
        $enabled = !empty($botSetting['auto_backup_enabled']);
        $minutes = isset($botSetting['auto_backup_minutes']) ? (int)$botSetting['auto_backup_minutes'] : 0;
        $lastTs = isset($botSetting['auto_backup_last_ts']) ? (int)$botSetting['auto_backup_last_ts'] : 0;
        $now = time();
        $isDue = $enabled && $minutes > 0 && ($now - $lastTs) >= ($minutes * 60);
        $force = $forceArg || defined('FORCE_BACKUP');
        if(!$force && !$isDue){
            echo date('Y-m-d H:i:s') . " skip bot data backup for @{$bot['username']} due scheduling -> enabled=" . ($enabled?1:0) . ", minutes=" . $minutes . ", lastTs=" . $lastTs . "\n";
            continue;
        }
        $autoTriggered = $autoTriggered || $isDue || $force;
        // Prepare zip of bot data (per bot folder)
        $folderName = $bot['id_user'].$bot['username'];
        $zipName = $destination . DIRECTORY_SEPARATOR . 'file.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $baseDir = $sourcefir.'/vpnbot/'.$folderName;
            $dir = $baseDir.'/data';
            if (is_dir($dir)){
                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $file){
                    $filePath = $file->getPathname();
                    $localName = substr($filePath, strlen($baseDir)+1);
                    $content = @file_get_contents($filePath);
                    if ($content !== false) {
                        if (!empty($domainhosts)) {
                            $content = str_replace($domainhosts, '<redacted-domain>', $content);
                        }
                        $zip->addFromString($localName, $content);
                    } else {
                        $zip->addFile($filePath, $localName);
                    }
                }
            }
            $p1 = $baseDir.'/product.json';
            if (is_file($p1)) {
                $j = json_decode(@file_get_contents($p1), true);
                if (is_array($j)){
                    foreach (['domain','panel_url','url_panel','subscription_url'] as $k){ if (isset($j[$k])) $j[$k] = '<redacted-domain>'; }
                    $zip->addFromString('product.json', json_encode($j, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                } else {
                    $zip->addFile($p1, 'product.json');
                }
            }
            $p2 = $baseDir.'/product_name.json';
            if (is_file($p2)) {
                $j = json_decode(@file_get_contents($p2), true);
                if (is_array($j)){
                    foreach (['domain','panel_url','url_panel'] as $k){ if (isset($j[$k])) $j[$k] = '<redacted-domain>'; }
                    $zip->addFromString('product_name.json', json_encode($j, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                } else {
                    $zip->addFile($p2, 'product_name.json');
                }
            }
            $zip->close();
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'document' => new CURLFile(realpath($zipName)),
                'caption' => "@{$bot['username']} | {$bot['id_user']}",
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
            $resp = telegram('sendDocument',$payload);
            echo date('Y-m-d H:i:s') . " send bot data zip for @{$bot['username']} -> " . json_encode($resp) . "\n";
            unlink($zipName);
            if (!is_array($resp) || empty($resp['ok'])) {
                $payloadErr = [
                    'chat_id' => $setting['Channel_Report'],
                    'text' => "❌ ارسال بکاپ داده‌های بات ناموفق بود",
                ];
                if ($reportbackup) $payloadErr['message_thread_id'] = $reportbackup;
                telegram('sendmessage', $payloadErr);
            }
        }
        // update last run timestamp after a send (due or force)
        $botSetting['auto_backup_last_ts'] = $now;
        update('botsaz','setting', json_encode($botSetting, JSON_UNESCAPED_UNICODE), 'id_user', $bot['id_user']);
    }
    // Global data folder backup (vpnbot/update/data)
    $globalDataDir = $sourcefir.'/vpnbot/update/data';
    if (is_dir($globalDataDir)){
        $zipName = $destination . DIRECTORY_SEPARATOR . 'data_update.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($globalDataDir, FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $file){
                $path = $file->getPathname();
                $local = substr($path, strlen($globalDataDir)+1);
                $content = @file_get_contents($path);
                if ($content !== false && !empty($domainhosts)){
                    $content = str_replace($domainhosts, '<redacted-domain>', $content);
                    $zip->addFromString($local, $content);
                } else {
                    $zip->addFile($path, $local);
                }
            }
            foreach (['vpnbot/Default/product.json','vpnbot/Default/product_name.json','vpnbot/update/text.json'] as $cfg){
                $cfgPath = $sourcefir.'/'.$cfg;
                if (is_file($cfgPath)){
                    $content = @file_get_contents($cfgPath);
                    if ($content !== false && !empty($domainhosts)) $content = str_replace($domainhosts, '<redacted-domain>', $content);
                    $zip->addFromString(basename($cfg), $content !== false ? $content : '');
                }
            }
            $zip->close();
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'document' => new CURLFile(realpath($zipName)),
                'caption' => "📦 داده‌های عمومی ربات (update/data)",
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
            $resp = telegram('sendDocument',$payload);
            echo date('Y-m-d H:i:s') . " send update data zip -> " . json_encode($resp) . "\n";
            unlink($zipName);
            if (!is_array($resp) || empty($resp['ok'])) {
                $payloadErr = [
                    'chat_id' => $setting['Channel_Report'],
                    'text' => "❌ ارسال بکاپ داده‌های عمومی ناموفق بود",
                ];
                if ($reportbackup) $payloadErr['message_thread_id'] = $reportbackup;
                telegram('sendmessage', $payloadErr);
            }
        }
    }

    // Database backup section
    $backup_file_name = $destination . DIRECTORY_SEPARATOR . ('backup_' . date("Y-m-d") . '.sql');
    $zip_file_name = $destination . DIRECTORY_SEPARATOR . ('backup_' . date("Y-m-d") . '.zip');
    $zipEnc = isset($setting['zip_encryption']) ? strtolower(trim($setting['zip_encryption'])) : 'none';
    $zipPass = isset($setting['zip_password']) ? trim($setting['zip_password']) : '';

    $command = "mysqldump -h localhost -u $usernamedb -p'$passworddb' --no-tablespaces --single-transaction --quick --routines --events --triggers --default-character-set=utf8mb4 $dbname > $backup_file_name";
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        $tmpDir = 'db-json-backup-'.date('Y-m-d');
        if (!is_dir($tmpDir)) mkdir($tmpDir);
        try{
            $tables = [];
            $stmt = $pdo->query('SHOW TABLES');
            while($row = $stmt->fetch(PDO::FETCH_NUM)){$tables[] = $row[0];}
            foreach($tables as $t){
                $dataStmt = $pdo->query('SELECT * FROM `'.$t.'`');
                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($rows)){
                    foreach ($rows as &$row){
                        foreach ($row as $col => &$val){
                            if (preg_match('/^(domain|url_panel|panel_url|subscription_url)$/i', $col)){
                                $val = '<redacted-domain>';
                            } elseif (is_string($val) && !empty($domainhosts) && strpos($val, $domainhosts) !== false){
                                $val = str_replace($domainhosts, '<redacted-domain>', $val);
                            }
                        }
                        unset($val);
                    }
                    unset($row);
                }
                file_put_contents($tmpDir.'/'.$t.'.json', json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            }
            // Schema
            $schemaSql = '';
            foreach($tables as $t){
                try{ $createRow = $pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_ASSOC); }catch(Throwable $e){ $createRow = null; }
                if ($createRow){ $schemaSql .= $createRow['Create Table'] . ";\n\n"; }
            }
            try{
                $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);
                foreach($views as $v){
                    $vn = $v[0];
                    $cr = $pdo->query('SHOW CREATE VIEW `'.$vn.'`')->fetch(PDO::FETCH_ASSOC);
                    if ($cr && isset($cr['Create View'])) $schemaSql .= $cr['Create View'] . ";\n\n";
                }
            }catch(Throwable $e){ }
            try{
                $procs = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = '$dbname'")->fetchAll(PDO::FETCH_ASSOC);
                foreach($procs as $pr){
                    $nm = $pr['Name'];
                    $cr = $pdo->query('SHOW CREATE PROCEDURE `'.$nm.'`')->fetch(PDO::FETCH_ASSOC);
                    if ($cr && isset($cr['Create Procedure'])) $schemaSql .= $cr['Create Procedure'] . ";\n\n";
                }
                $funcs = $pdo->query("SHOW FUNCTION STATUS WHERE Db = '$dbname'")->fetchAll(PDO::FETCH_ASSOC);
                foreach($funcs as $fn){
                    $nm = $fn['Name'];
                    $cr = $pdo->query('SHOW CREATE FUNCTION `'.$nm.'`')->fetch(PDO::FETCH_ASSOC);
                    if ($cr && isset($cr['Create Function'])) $schemaSql .= $cr['Create Function'] . ";\n\n";
                }
            }catch(Throwable $e){ }
            if (!empty($domainhosts)) $schemaSql = str_replace($domainhosts, '<redacted-domain>', $schemaSql);
            file_put_contents($tmpDir.'/schema.sql', $schemaSql);

            $zip = new ZipArchive();
            if ($zip->open($zip_file_name, ZipArchive::CREATE|ZipArchive::OVERWRITE) === TRUE){
                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
                foreach($rii as $file){
                    $path = $file->getPathname();
                    $local = substr($path, strlen($tmpDir)+1);
                    $zip->addFile($path, $local);
                }
                if ($zipEnc === 'aes' && $zipPass !== '') {
                    $first = reset($tables);
                    if ($first) $zip->setEncryptionName($first.'.json', ZipArchive::EM_AES_256, $zipPass);
                } elseif ($zipEnc === 'pkware' && $zipPass !== '') {
                    $first = reset($tables);
                    if ($first) $zip->setEncryptionName($first.'.json', ZipArchive::EM_TRAD_PKWARE, $zipPass);
                }
                $zip->close();
                $payload = [
                    'chat_id' => $setting['Channel_Report'],
                    'document' => new CURLFile(realpath($zip_file_name)),
                    'caption' => ($zipEnc !== 'none' && $zipPass !== '' ? "📌 بکاپ JSON دیتابیس (رمز: $zipPass)" : "📌 بکاپ JSON دیتابیس"),
                ];
                if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
                $resp = telegram('sendDocument', $payload);
                if (!is_array($resp) || empty($resp['ok'])) {
                    $payloadErr = [
                        'chat_id' => $setting['Channel_Report'],
                        'text' => "❌ ارسال بکاپ JSON دیتابیس ناموفق بود",
                    ];
                    if ($reportbackup) $payloadErr['message_thread_id'] = $reportbackup;
                    telegram('sendmessage', $payloadErr);
                }
                unlink($zip_file_name);
            }
        } catch (Throwable $e){
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'text' => "❌ خطا در بکاپ دیتابیس",
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
                telegram('sendmessage', $payload);
        }
        if (is_dir($tmpDir)){
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach($rii as $file){ $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            rmdir($tmpDir);
        }
    } else {
        $zip = new ZipArchive();
        if ($zip->open($zip_file_name, ZipArchive::CREATE|ZipArchive::OVERWRITE) === TRUE) {
            $sqlContent = @file_get_contents($backup_file_name);
            if ($sqlContent !== false && !empty($domainhosts)){
                $sqlContent = str_replace($domainhosts, '<redacted-domain>', $sqlContent);
                $zip->addFromString(basename($backup_file_name), $sqlContent);
            } else {
                $zip->addFile($backup_file_name, basename($backup_file_name));
            }
            if ($zipEnc === 'aes' && $zipPass !== '') {
                $zip->setEncryptionName(basename($backup_file_name), ZipArchive::EM_AES_256, $zipPass);
            } elseif ($zipEnc === 'pkware' && $zipPass !== '') {
                $zip->setEncryptionName(basename($backup_file_name), ZipArchive::EM_TRAD_PKWARE, $zipPass);
            }
            $zip->close();
            if($autoTriggered || $forceArg || defined('FORCE_BACKUP')){
                $payload = [
                    'chat_id' => $setting['Channel_Report'],
                    'document' => new CURLFile(realpath($zip_file_name)),
                    'caption' => ($zipEnc !== 'none' && $zipPass !== '' ? "📌 خروجی دیتابیس (رمز: $zipPass)" : "📌 خروجی دیتابیس"),
                ];
                if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
                $resp = telegram('sendDocument', $payload);
                if (!is_array($resp) || empty($resp['ok'])) {
                    $payloadErr = [
                        'chat_id' => $setting['Channel_Report'],
                        'text' => "❌ ارسال بکاپ SQL دیتابیس ناموفق بود",
                    ];
                    if ($reportbackup) $payloadErr['message_thread_id'] = $reportbackup;
                    telegram('sendmessage', $payloadErr);
                }
            } else {
                echo date('Y-m-d H:i:s') . " skip sql backup send due scheduling -> autoTriggered=0, minutes aggregate per-bot" . "\n";
            }
            unlink($zip_file_name);
            unlink($backup_file_name);
        }
    }
}

if (PHP_SAPI === 'cli' && isset($argv) && in_array('--daemon', $argv, true)) {
    set_time_limit(0);
    while(true){
        run_backup_cycle($destination, $sourcefir, $setting, $reportbackup, defined('FORCE_BACKUP'));
        sleep(60);
    }
    exit;
}

echo date('Y-m-d H:i:s') . " run backup cycle force=" . (defined('FORCE_BACKUP')?1:0) . "\n";
run_backup_cycle($destination, $sourcefir, $setting, $reportbackup, defined('FORCE_BACKUP'));

// Global data folder backup (vpnbot/update/data)
$globalDataDir = $sourcefir.'/vpnbot/update/data';
if (is_dir($globalDataDir)){
    $zipName = $destination . DIRECTORY_SEPARATOR . 'data_update.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($globalDataDir, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file){
            $path = $file->getPathname();
            $local = substr($path, strlen($globalDataDir)+1);
            $content = @file_get_contents($path);
            if ($content !== false && !empty($domainhosts)){
                $content = str_replace($domainhosts, '<redacted-domain>', $content);
                $zip->addFromString($local, $content);
            } else {
                $zip->addFile($path, $local);
            }
        }
        // include Default/update product configs if present
        foreach (['vpnbot/Default/product.json','vpnbot/Default/product_name.json','vpnbot/update/text.json'] as $cfg){
            $cfgPath = $sourcefir.'/'.$cfg;
            if (is_file($cfgPath)){
                $content = @file_get_contents($cfgPath);
                if ($content !== false && !empty($domainhosts)) $content = str_replace($domainhosts, '<redacted-domain>', $content);
                $zip->addFromString(basename($cfg), $content !== false ? $content : '');
            }
        }
        $zip->close();
        $payload = [
            'chat_id' => $setting['Channel_Report'],
            'document' => new CURLFile(realpath($zipName)),
            'caption' => "📦 داده‌های عمومی ربات (update/data)",
        ];
        if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
        telegram('sendDocument',$payload);
        unlink($zipName);
    }
}




$backup_file_name = $destination . DIRECTORY_SEPARATOR . ('backup_' . date("Y-m-d") . '.sql');
$zip_file_name = $destination . DIRECTORY_SEPARATOR . ('backup_' . date("Y-m-d") . '.zip');
// Optional encryption configuration
$zipEnc = isset($setting['zip_encryption']) ? strtolower(trim($setting['zip_encryption'])) : 'none';
$zipPass = isset($setting['zip_password']) ? trim($setting['zip_password']) : '';

$command = "mysqldump -h localhost -u $usernamedb -p'$passworddb' --no-tablespaces --single-transaction --quick --routines --events --triggers --default-character-set=utf8mb4 $dbname > $backup_file_name";
$output = [];
$return_var = 0;
exec($command, $output, $return_var);
if ($return_var !== 0) {
    $tmpDir = 'db-json-backup-'.date('Y-m-d');
    if (!is_dir($tmpDir)) mkdir($tmpDir);
    try{
        $tables = [];
        $stmt = $pdo->query('SHOW TABLES');
        while($row = $stmt->fetch(PDO::FETCH_NUM)){$tables[] = $row[0];}
        foreach($tables as $t){
            $dataStmt = $pdo->query('SELECT * FROM `'.$t.'`');
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            if (is_array($rows)){
                foreach ($rows as &$row){
                    foreach ($row as $col => &$val){
                        if (preg_match('/^(domain|url_panel|panel_url|subscription_url)$/i', $col)){
                            $val = '<redacted-domain>';
                        } elseif (is_string($val) && !empty($domainhosts) && strpos($val, $domainhosts) !== false){
                            $val = str_replace($domainhosts, '<redacted-domain>', $val);
                        }
                    }
                    unset($val);
                }
                unset($row);
            }
            file_put_contents($tmpDir.'/'.$t.'.json', json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        // Schema and views/routines fallback
        $schemaSql = '';
        foreach($tables as $t){
            try{ $createRow = $pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_ASSOC); }catch(Throwable $e){ $createRow = null; }
            if ($createRow){ $schemaSql .= $createRow['Create Table'] . ";\n\n"; }
        }
        // Views
        try{
            $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);
            foreach($views as $v){
                $vn = $v[0];
                $cr = $pdo->query('SHOW CREATE VIEW `'.$vn.'`')->fetch(PDO::FETCH_ASSOC);
                if ($cr && isset($cr['Create View'])) $schemaSql .= $cr['Create View'] . ";\n\n";
            }
        }catch(Throwable $e){ }
        // Routines
        try{
            $procs = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = '$dbname'")->fetchAll(PDO::FETCH_ASSOC);
            foreach($procs as $pr){
                $nm = $pr['Name'];
                $cr = $pdo->query('SHOW CREATE PROCEDURE `'.$nm.'`')->fetch(PDO::FETCH_ASSOC);
                if ($cr && isset($cr['Create Procedure'])) $schemaSql .= $cr['Create Procedure'] . ";\n\n";
            }
            $funcs = $pdo->query("SHOW FUNCTION STATUS WHERE Db = '$dbname'")->fetchAll(PDO::FETCH_ASSOC);
            foreach($funcs as $fn){
                $nm = $fn['Name'];
                $cr = $pdo->query('SHOW CREATE FUNCTION `'.$nm.'`')->fetch(PDO::FETCH_ASSOC);
                if ($cr && isset($cr['Create Function'])) $schemaSql .= $cr['Create Function'] . ";\n\n";
            }
        }catch(Throwable $e){ }
        if (!empty($domainhosts)) $schemaSql = str_replace($domainhosts, '<redacted-domain>', $schemaSql);
        file_put_contents($tmpDir.'/schema.sql', $schemaSql);
        $zip = new ZipArchive();
        if ($zip->open($zip_file_name, ZipArchive::CREATE|ZipArchive::OVERWRITE) === TRUE){
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
            foreach($rii as $file){
                $path = $file->getPathname();
                $local = substr($path, strlen($tmpDir)+1);
                $zip->addFile($path, $local);
            }
            if ($zipEnc === 'aes' && $zipPass !== '') {
                // Encrypt first JSON entry as indicator
                $first = reset($tables);
                if ($first) $zip->setEncryptionName($first.'.json', ZipArchive::EM_AES_256, $zipPass);
            } elseif ($zipEnc === 'pkware' && $zipPass !== '') {
                $first = reset($tables);
                if ($first) $zip->setEncryptionName($first.'.json', ZipArchive::EM_TRAD_PKWARE, $zipPass);
            }
            $zip->close();
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'document' => new CURLFile(realpath($zip_file_name)),
                'caption' => ($zipEnc !== 'none' && $zipPass !== '' ? "📌 بکاپ JSON دیتابیس (رمز: $zipPass)" : "📌 بکاپ JSON دیتابیس"),
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
            $resp = telegram('sendDocument', $payload);
            echo date('Y-m-d H:i:s') . " send db json backup -> " . json_encode($resp) . "\n";
            unlink($zip_file_name);
        }
    } catch (Throwable $e){
        $payload = [
            'chat_id' => $setting['Channel_Report'],
            'text' => "❌ خطا در بکاپ دیتابیس",
        ];
        if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
        telegram('sendmessage', $payload);
    }
    if (is_dir($tmpDir)){
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach($rii as $file){ $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($tmpDir);
    }
} else {
    $zip = new ZipArchive();
    if ($zip->open($zip_file_name, ZipArchive::CREATE|ZipArchive::OVERWRITE) === TRUE) {
        $sqlContent = @file_get_contents($backup_file_name);
        if ($sqlContent !== false && !empty($domainhosts)){
            $sqlContent = str_replace($domainhosts, '<redacted-domain>', $sqlContent);
            $zip->addFromString(basename($backup_file_name), $sqlContent);
        } else {
            $zip->addFile($backup_file_name, basename($backup_file_name));
        }
        if ($zipEnc === 'aes' && $zipPass !== '') {
            $zip->setEncryptionName(basename($backup_file_name), ZipArchive::EM_AES_256, $zipPass);
        } elseif ($zipEnc === 'pkware' && $zipPass !== '') {
            $zip->setEncryptionName(basename($backup_file_name), ZipArchive::EM_TRAD_PKWARE, $zipPass);
        }
        $zip->close();
        if($autoTriggered || defined('FORCE_BACKUP')){
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'document' => new CURLFile(realpath($zip_file_name)),
                'caption' => ($zipEnc !== 'none' && $zipPass !== '' ? "📌 خروجی دیتابیس (رمز: $zipPass)" : "📌 خروجی دیتابیس"),
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
                $resp = telegram('sendDocument', $payload);
                echo date('Y-m-d H:i:s') . " send db sql backup -> " . json_encode($resp) . "\n";
            }
        unlink($zip_file_name);
        unlink($backup_file_name);
    }
}
