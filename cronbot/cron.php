<?php
// Main Cron Runner
// Run this file every minute via Cron Job
// * * * * * php /path/to/cronbot/cron.php

// Increase execution time limit
ini_set('max_execution_time', 300);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Load config once to ensure global variables ($pdo, etc.) are available to all scripts
require_once __DIR__ . '/../config.php';

// Define a constant to signal we are in the runner
define('CRON_RUNNING', true);

$lockFile = __DIR__ . '/cron_runner.lock';
$fp = fopen($lockFile, 'c+');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "Cron Runner is already running. Exiting.\n";
    return;
}

$cronRunnerLogFile = __DIR__ . '/cron_runner.log';
$cronRunnerStatusFile = __DIR__ . '/cron_runner_status.json';
function cron_runner_log($msg) {
    global $cronRunnerLogFile;
    $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
    echo $line;
    @file_put_contents($cronRunnerLogFile, $line, FILE_APPEND);
}

$cronRunnerRunId = bin2hex(random_bytes(8));
$cronRunnerStatus = [
    'run_id' => $cronRunnerRunId,
    'started_at' => time(),
    'started_at_iso' => date('c'),
    'cwd' => getcwd(),
    'php_sapi' => PHP_SAPI,
    'php_version' => PHP_VERSION,
    'scripts' => [],
    'finished_at' => null,
    'finished_at_iso' => null,
];

register_shutdown_function(function () {
    global $cronRunnerStatus, $cronRunnerStatusFile;
    $lastError = error_get_last();
    if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $cronRunnerStatus['fatal_error'] = $lastError;
    }
    if ($cronRunnerStatus['finished_at'] === null) {
        $cronRunnerStatus['finished_at'] = time();
        $cronRunnerStatus['finished_at_iso'] = date('c');
    }
    $tmp = $cronRunnerStatusFile . '.tmp';
    @file_put_contents($tmp, json_encode($cronRunnerStatus, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if (is_file($cronRunnerStatusFile)) {
        @unlink($cronRunnerStatusFile);
    }
    @rename($tmp, $cronRunnerStatusFile);
});

$scripts = [
    'activeconfig.php',
    'backupbot.php',
    'configtest.php',
    'croncard.php',
    'disableconfig.php',
    'expireagent.php',
    'gift.php',
    'iranpay1.php',
    'lottery.php',
    'NoticationsService.php',
    'on_hold.php',
    'payment_expire.php',
    'plisio.php',
    'sendmessage.php',
    'statusday.php',
    'sync_inbounds.php',
    'uptime_node.php',
    'uptime_panel.php'
];

$baseDir = __DIR__;

cron_runner_log("Starting Cron Runner ($cronRunnerRunId)");

foreach ($scripts as $script) {
    $file = $baseDir . DIRECTORY_SEPARATOR . $script;
    if (file_exists($file)) {
        cron_runner_log("Running $script");
        $startedAt = microtime(true);
        $ok = true;
        $error = null;
        $output = '';
        try {
            unset($stmt, $user, $row, $result, $rows, $invoice, $setting, $ManagePanel, $volumeMonitor);
            ob_start();
            include $file;
            $output = ob_get_clean();
        } catch (Throwable $e) {
            $ok = false;
            $error = $e;
            $output = ob_get_level() ? ob_get_clean() : $output;
        }
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        if ($output !== '') {
            echo $output;
            $trimmed = $output;
            if (strlen($trimmed) > 20000) {
                $trimmed = substr($trimmed, 0, 20000) . "\n[output truncated]\n";
            }
            @file_put_contents($cronRunnerLogFile, $trimmed, FILE_APPEND);
        }
        if ($error) {
            cron_runner_log("Error in $script: " . $error->getMessage());
        }
        $cronRunnerStatus['scripts'][$script] = [
            'ok' => $ok,
            'duration_ms' => $durationMs,
            'output_bytes' => strlen($output),
            'error' => $error ? get_class($error) . ': ' . $error->getMessage() : null,
        ];
        cron_runner_log("Finished $script ($durationMs ms)");
    } else {
        cron_runner_log("Script not found: $script");
        $cronRunnerStatus['scripts'][$script] = [
            'ok' => false,
            'duration_ms' => 0,
            'output_bytes' => 0,
            'error' => 'Script not found',
        ];
    }
}

$cronRunnerStatus['finished_at'] = time();
$cronRunnerStatus['finished_at_iso'] = date('c');
cron_runner_log("Cron Runner Finished ($cronRunnerRunId)");
