<?php
require_once '../config.php';
require_once '../function.php';
require_once '../botapi.php';

$rbRow = select("topicid","idreport","report","backupfile","select");
$reportbackup = is_array($rbRow) && isset($rbRow['idreport']) ? $rbRow['idreport'] : null;
$destination = getcwd();
$setting = select("setting", "*");
$sourcefir = dirname($destination);
// Auto-backup gating per bot
$botlist = select("botsaz","*",null,null,"fetchAll");
$autoTriggered = false;
if($botlist){
    foreach ($botlist as $bot){
        $botSetting = json_decode($bot['setting'] ?? '{}', true);
        $enabled = !empty($botSetting['auto_backup_enabled']);
        $minutes = isset($botSetting['auto_backup_minutes']) ? (int)$botSetting['auto_backup_minutes'] : 0;
        $lastTs = isset($botSetting['auto_backup_last_ts']) ? (int)$botSetting['auto_backup_last_ts'] : 0;
        $now = time();
        $isDue = $enabled && $minutes > 0 && ($now - $lastTs) >= ($minutes * 60);
        $force = defined('FORCE_BACKUP');
        if(!$force && !$isDue){
            continue;
        }
        $autoTriggered = $autoTriggered || $isDue || $force;
        // Prepare zip of bot data
        $folderName = $bot['id_user'].$bot['username'];
        $zipName = 'file.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $baseDir = $sourcefir.'/vpnbot/'.$folderName;
            $dir = $baseDir.'/data';
            if (is_dir($dir)){
                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $file){
                    $filePath = $file->getPathname();
                    $localName = substr($filePath, strlen($baseDir)+1);
                    $zip->addFile($filePath, $localName);
                }
            }
            $p1 = $baseDir.'/product.json';
            if (is_file($p1)) $zip->addFile($p1, 'product.json');
            $p2 = $baseDir.'/product_name.json';
            if (is_file($p2)) $zip->addFile($p2, 'product_name.json');
            $zip->close();
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'document' => new CURLFile($zipName),
                'caption' => "@{$bot['username']} | {$bot['id_user']}",
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
            telegram('sendDocument',$payload);
            unlink($zipName);
        }
        // update last run timestamp when auto
        if($isDue){
            $botSetting['auto_backup_last_ts'] = $now;
            update('botsaz','setting', json_encode($botSetting, JSON_UNESCAPED_UNICODE), 'id_user', $bot['id_user']);
        }
    }
}




$backup_file_name = 'backup_' . date("Y-m-d") . '.sql';
$zip_file_name = 'backup_' . date("Y-m-d") . '.zip';

$command = "mysqldump -h localhost -u $usernamedb -p'$passworddb' --no-tablespaces $dbname > $backup_file_name";
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
            file_put_contents($tmpDir.'/'.$t.'.json', json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        $zip = new ZipArchive();
        if ($zip->open($zip_file_name, ZipArchive::CREATE|ZipArchive::OVERWRITE) === TRUE){
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
            foreach($rii as $file){
                $path = $file->getPathname();
                $local = substr($path, strlen($tmpDir)+1);
                $zip->addFile($path, $local);
            }
            $zip->setEncryptionName(reset($tables).'.json', ZipArchive::EM_AES_256, "MirzaBackup2025#@$");
            $zip->close();
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'document' => new CURLFile($zip_file_name),
                'caption' => "📌 بکاپ JSON دیتابیس",
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
            telegram('sendDocument', $payload);
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
    if ($zip->open($zip_file_name, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($backup_file_name, basename($backup_file_name));
        $zip->setEncryptionName(basename($backup_file_name), ZipArchive::EM_AES_256, "MirzaBackup2025#@$");
        $zip->close();
        if($autoTriggered || defined('FORCE_BACKUP')){
            $payload = [
                'chat_id' => $setting['Channel_Report'],
                'document' => new CURLFile($zip_file_name),
                'caption' => "📌 خروجی دیتابیس ربات اصلی \nبرای دریافت پسورد به اکانت پشتیبانی پیام دهید.",
            ];
            if ($reportbackup) $payload['message_thread_id'] = $reportbackup;
            telegram('sendDocument', $payload);
        }
        unlink($zip_file_name);
        unlink($backup_file_name);
    }
}
