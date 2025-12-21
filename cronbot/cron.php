<?php
// Main Cron Runner
// Run this file every minute via Cron Job
// * * * * * php /path/to/cronbot/cron.php

// Increase execution time limit
ini_set('max_execution_time', 300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define a constant to signal we are in the runner
define('CRON_RUNNING', true);

// List of scripts to run
$scripts = [
    'activeconfig.php',
    'backupbot.php',
    'croncard.php',
    'disableconfig.php',
    'expireagent.php',
    'gift.php',
    'lottery.php',
    'on_hold.php',
    'payment_expire.php',
    'sendmessage.php',
    'statusday.php',
    'sync_inbounds.php',
    'uptime_node.php',
    'uptime_panel.php'
];

$baseDir = __DIR__;

echo "Starting Cron Runner at " . date('Y-m-d H:i:s') . "\n";

foreach ($scripts as $script) {
    $file = $baseDir . DIRECTORY_SEPARATOR . $script;
    if (file_exists($file)) {
        echo "Running $script...\n";
        try {
            // We include the file directly in the global scope.
            // This is necessary because the scripts rely on 'require_once' for config.php.
            // If we used a function scope, the first script would load config.php (creating local vars),
            // and subsequent scripts would skip loading config.php (due to require_once) 
            // and fail to see the variables.
            include $file;
        } catch (Throwable $e) {
            echo "Error running $script: " . $e->getMessage() . "\n";
        }
        echo "\nFinished $script.\n-----------------------------------\n";
    } else {
        echo "Script not found: $script\n";
    }
}

echo "Cron Runner Finished at " . date('Y-m-d H:i:s') . "\n";
