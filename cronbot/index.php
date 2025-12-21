<?php
header('Content-Type: text/plain; charset=utf-8');

$statusFile = __DIR__ . '/cron_runner_status.json';
if (!is_file($statusFile)) {
    echo "CRON_STATUS=missing\n";
    exit;
}

$statusRaw = file_get_contents($statusFile);
$status = json_decode($statusRaw, true);
if (!is_array($status)) {
    echo "CRON_STATUS=invalid\n";
    exit;
}

$finishedAt = isset($status['finished_at']) ? (int)$status['finished_at'] : 0;
$ageSeconds = $finishedAt > 0 ? (time() - $finishedAt) : -1;

$scripts = isset($status['scripts']) && is_array($status['scripts']) ? $status['scripts'] : [];
$okCount = 0;
$failCount = 0;
foreach ($scripts as $info) {
    if (is_array($info) && !empty($info['ok'])) $okCount++;
    else $failCount++;
}

echo "CRON_RUN_ID=" . ($status['run_id'] ?? '') . "\n";
echo "CRON_FINISHED_AT=" . ($status['finished_at_iso'] ?? '') . "\n";
echo "CRON_AGE_SECONDS=$ageSeconds\n";
echo "SCRIPTS_OK=$okCount\n";
echo "SCRIPTS_FAILED=$failCount\n";

if ($failCount > 0) {
    foreach ($scripts as $name => $info) {
        if (!is_array($info) || !empty($info['ok'])) continue;
        $err = isset($info['error']) ? (string)$info['error'] : '';
        if (strlen($err) > 300) $err = substr($err, 0, 300) . '...';
        echo "FAILED_SCRIPT=$name\t$err\n";
    }
}
