<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    session_start();
    if (!isset($_SESSION["user"])) {
        http_response_code(403);
        die("دسترسی غیرمجاز");
    }
}

cronEnsureRunsTable();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $msg = "اتصال به دیتابیس برقرار نیست.";
    if ($isCli) {
        echo $msg . PHP_EOL;
        exit(1);
    }
    echo "<pre>" . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    exit;
}

$limit = 200;
try {
    $stmt = $pdo->prepare("SELECT id, job_name, started_at, ended_at, status, duration_ms, message FROM cron_runs ORDER BY started_at DESC, id DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $msg = "خطا در خواندن وضعیت کورن‌جاب‌ها: " . $e->getMessage();
    if ($isCli) {
        echo $msg . PHP_EOL;
        exit(1);
    }
    echo "<pre>" . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    exit;
}

$byJob = [];
foreach ($rows as $row) {
    $job = (string) ($row['job_name'] ?? 'unknown');
    if (!isset($byJob[$job])) {
        $byJob[$job] = [];
    }
    $byJob[$job][] = $row;
}
ksort($byJob);

if ($isCli) {
    foreach ($byJob as $job => $items) {
        $last = $items[0] ?? null;
        if (!$last) continue;
        $status = $last['status'] ?? '';
        $started = $last['started_at'] ?? '';
        $ended = $last['ended_at'] ?? '';
        $dur = $last['duration_ms'] ?? '';
        echo $job . " | " . $status . " | " . $started . " -> " . $ended . " | " . $dur . "ms" . PHP_EOL;
        if (!empty($last['message'])) {
            echo "  " . trim((string) $last['message']) . PHP_EOL;
        }
    }
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo "<!doctype html><html lang='fa' dir='rtl'><head><meta charset='utf-8'><title>وضعیت کورن‌جاب‌ها</title>";
echo "<style>
body{font-family:Tahoma,Arial,sans-serif;background:#0b1220;color:#e5e7eb;margin:0;padding:24px}
h1{margin:0 0 16px 0;font-size:20px}
.job{margin:14px 0;padding:14px;border:1px solid rgba(255,255,255,0.08);border-radius:12px;background:rgba(255,255,255,0.03)}
.job h2{margin:0 0 10px 0;font-size:16px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.08);vertical-align:top}
th{text-align:right;color:#9ca3af;font-weight:600}
.status-success{color:#34d399}
.status-failed{color:#f87171}
.status-running{color:#60a5fa}
.status-skipped{color:#fbbf24}
.msg{color:#cbd5e1;white-space:pre-wrap}
</style></head><body>";
echo "<h1>وضعیت کورن‌جاب‌ها (آخرین {$limit} اجرا)</h1>";

foreach ($byJob as $job => $items) {
    $last = $items[0] ?? null;
    $lastStatus = (string) ($last['status'] ?? '');
    $statusClass = 'status-' . preg_replace('/[^a-zA-Z0-9_-]+/', '', strtolower($lastStatus));
    echo "<div class='job'>";
    echo "<h2>" . htmlspecialchars($job, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " — <span class='{$statusClass}'>" . htmlspecialchars($lastStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</span></h2>";
    echo "<table><thead><tr><th>شروع</th><th>پایان</th><th>مدت</th><th>وضعیت</th><th>پیام</th></tr></thead><tbody>";
    $show = array_slice($items, 0, 10);
    foreach ($show as $row) {
        $s = htmlspecialchars((string) ($row['started_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $e = htmlspecialchars((string) ($row['ended_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $d = htmlspecialchars((string) ($row['duration_ms'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $st = (string) ($row['status'] ?? '');
        $stClass = 'status-' . preg_replace('/[^a-zA-Z0-9_-]+/', '', strtolower($st));
        $stHtml = "<span class='{$stClass}'>" . htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</span>";
        $m = htmlspecialchars((string) ($row['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<tr><td>{$s}</td><td>{$e}</td><td>{$d}</td><td>{$stHtml}</td><td class='msg'>{$m}</td></tr>";
    }
    echo "</tbody></table>";
    echo "</div>";
}

echo "</body></html>";

