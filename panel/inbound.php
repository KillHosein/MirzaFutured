<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
// فراخوانی کتابخانه تاریخ شمسی
if (file_exists('../jdf.php')) require_once '../jdf.php';

// Authentication
if (!isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

$dbReady = isset($pdo) && ($pdo instanceof PDO);
if (!$dbReady) {
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>خطا</title></head><body style="font-family:tahoma,sans-serif;background:#0b0b10;color:#fff;padding:30px"><h2>خطا در اتصال به دیتابیس</h2><p>اتصال دیتابیس برقرار نیست یا تنظیمات `config.php` مشکل دارد.</p><a href="login.php" style="color:#22d3ee">رفتن به ورود</a></body></html>';
    exit;
}

try {
    $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Inbound auth error: ' . $e->getMessage());
    $result = false;
}

if (!$result) {
    header('Location: login.php');
    exit;
}

function csvSafeCell($value)
{
    $value = (string) $value;
    $firstChar = $value !== '' ? substr($value, 0, 1) : '';
    if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

$protocolInput = trim((string)($_GET['protocol'] ?? ''));
$locationInput = trim((string)($_GET['location'] ?? ''));
$qInput = trim((string)($_GET['q'] ?? ''));

$strlen = function ($v) { return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v); };
$substr = function ($v, $start, $len) { return function_exists('mb_substr') ? mb_substr($v, $start, $len, 'UTF-8') : substr($v, $start, $len); };

if ($strlen($protocolInput) > 60) $protocolInput = $substr($protocolInput, 0, 60);
if ($strlen($locationInput) > 120) $locationInput = $substr($locationInput, 0, 120);
if ($strlen($qInput) > 160) $qInput = $substr($qInput, 0, 160);

$dbError = null;
$totalRows = 0;
$listinvoice = [];
$protocolOptions = [];
$locationOptions = [];
$params = [];
$whereClause = '';
$tableSql = null;
$colLocationSql = null;
$colProtocolSql = null;
$colNameInboundSql = null;

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 25;
$offset = ($page - 1) * $perPage;
if ($offset < 0) $offset = 0;

try {
    $quoteIdent = function (string $name): string {
        return '`' . str_replace('`', '``', $name) . '`';
    };

    $tblStmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(TABLE_NAME) IN ('inbound','inbounds') ORDER BY CASE WHEN LOWER(TABLE_NAME)='inbound' THEN 0 ELSE 1 END LIMIT 1");
    $inboundTableName = $tblStmt ? $tblStmt->fetchColumn() : null;
    if (!$inboundTableName) {
        throw new RuntimeException('Inbound table not found');
    }

    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND TABLE_NAME = :t");
    $colsStmt->execute([':t' => $inboundTableName]);
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $colMap = [];
    foreach ($cols as $c) {
        $colMap[strtolower((string) $c)] = (string) $c;
    }

    $locationCol = $colMap['location'] ?? null;
    $protocolCol = $colMap['protocol'] ?? null;
    $nameInboundCol = $colMap['nameinbound'] ?? $colMap['name_inbound'] ?? $colMap['inboundname'] ?? $colMap['inbound_name'] ?? null;
    if (!$locationCol || !$protocolCol || !$nameInboundCol) {
        throw new RuntimeException('Inbound table columns missing');
    }

    $tableSql = $quoteIdent($inboundTableName);
    $colLocationSql = $quoteIdent($locationCol);
    $colProtocolSql = $quoteIdent($protocolCol);
    $colNameInboundSql = $quoteIdent($nameInboundCol);

    $where = [];
    $params = [];
    if ($protocolInput !== '') {
        $where[] = "{$colProtocolSql} = :protocol";
        $params[':protocol'] = $protocolInput;
    }
    if ($locationInput !== '') {
        $where[] = "{$colLocationSql} = :location";
        $params[':location'] = $locationInput;
    }
    if ($qInput !== '') {
        $search = '%' . $qInput . '%';
        $where[] = "({$colLocationSql} LIKE :q OR {$colProtocolSql} LIKE :q OR {$colNameInboundSql} LIKE :q)";
        $params[':q'] = $search;
    }
    $whereClause = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableSql}{$whereClause}");
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();

    $dataSql = "SELECT {$colLocationSql} AS location, {$colProtocolSql} AS protocol, {$colNameInboundSql} AS NameInbound FROM {$tableSql}{$whereClause} ORDER BY {$colLocationSql} ASC, {$colProtocolSql} ASC, {$colNameInboundSql} ASC LIMIT {$perPage} OFFSET {$offset}";
    $query = $pdo->prepare($dataSql);
    $query->execute($params);
    $listinvoice = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $protocolStmt = $pdo->query("SELECT DISTINCT {$colProtocolSql} AS protocol FROM {$tableSql} WHERE {$colProtocolSql} IS NOT NULL AND {$colProtocolSql} != '' ORDER BY {$colProtocolSql} ASC");
    $protocolOptions = ($protocolStmt ? $protocolStmt->fetchAll(PDO::FETCH_COLUMN) : []) ?: [];
    $locationStmt = $pdo->query("SELECT DISTINCT {$colLocationSql} AS location FROM {$tableSql} WHERE {$colLocationSql} IS NOT NULL AND {$colLocationSql} != '' ORDER BY {$colLocationSql} ASC");
    $locationOptions = ($locationStmt ? $locationStmt->fetchAll(PDO::FETCH_COLUMN) : []) ?: [];
} catch (Throwable $e) {
    error_log('Inbound page DB error: ' . $e->getMessage());
    $dbError = 'خطا در دریافت اطلاعات اینباندها. اگر جدول `Inbound` در دیتابیس وجود ندارد، ابتدا باید ساخته/همگام‌سازی شود.';
    $totalRows = 0;
    $listinvoice = [];
    $protocolOptions = [];
    $locationOptions = [];
    $params = [];
    $whereClause = '';
    $tableSql = null;
    $colLocationSql = null;
    $colProtocolSql = null;
    $colNameInboundSql = null;
}

// --- Export CSV ---
if(isset($_GET['export']) && $_GET['export']==='csv'){
    if ($dbError !== null || $tableSql === null || $colLocationSql === null || $colProtocolSql === null || $colNameInboundSql === null) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $dbError ?: 'امکان خروجی گرفتن وجود ندارد.';
        exit();
    }
    $exportSql = "SELECT {$colLocationSql} AS location, {$colProtocolSql} AS protocol, {$colNameInboundSql} AS NameInbound FROM {$tableSql}{$whereClause} ORDER BY {$colLocationSql} ASC, {$colProtocolSql} ASC, {$colNameInboundSql} ASC";
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute($params);
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inbounds-'.date('Y-m-d').'.csv');
    $out = fopen('php://output','w');
    fputs($out, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($out, ['Location','Protocol','Inbound Name']);
    foreach($exportRows as $row){
        fputcsv($out, [csvSafeCell($row['location'] ?? ''), csvSafeCell($row['protocol'] ?? ''), csvSafeCell($row['NameInbound'] ?? '')]);
    }
    fclose($out);
    exit();
}

// --- Stats Calculation ---
$totalInbounds = $totalRows;
$protocols = [];
$locations = [];
foreach($listinvoice as $row){
    if($row['protocol']) $protocols[$row['protocol']] = true;
    if($row['location']) $locations[$row['location']] = true;
}
$uniqueProtocols = count($protocols);
$uniqueLocations = count($locations);

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت اینباندها</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            /* Theme Core */
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.75);
            --bg-card-hover: rgba(35, 35, 45, 0.9);
            --bg-glass: rgba(20, 20, 25, 0.85);
            --bg-dock: rgba(10, 10, 15, 0.95);
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-amber: #fbbf24;
            --neon-cyan: #22d3ee;
            
            /* Text */
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            
            /* Borders */
            --border-subtle: 1px solid rgba(255, 255, 255, 0.08);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.2);
            --shadow-card: 0 15px 50px rgba(0,0,0,0.6);
            
            --radius-main: 28px;
            --radius-lg: 24px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        body {
            background-color: var(--bg-body);
            color: var(--text-pri);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(34, 211, 238, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 90% 80%, rgba(192, 38, 211, 0.08) 0%, transparent 45%);
            background-attachment: fixed;
            padding-bottom: 150px;
            display: flex; flex-direction: column;
        }

        /* --- Full Height Container --- */
        .container-fluid-custom {
            width: 100%; padding: 30px 4%; max-width: 1920px; margin: 0 auto;
            flex-grow: 1;
            display: flex; flex-direction: column; gap: 30px;
        }

        /* --- Header Bigger --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .page-title h1 {
            font-size: 3rem; font-weight: 900; margin: 0; color: #fff;
            text-shadow: 0 0 30px rgba(255,255,255,0.1);
        }
        .page-title p { color: var(--text-sec); font-size: 1.2rem; margin-top: 5px; font-weight: 400; }
        
        .info-pill {
            background: rgba(255,255,255,0.03); border: var(--border-subtle);
            padding: 12px 25px; border-radius: 18px;
            display: flex; align-items: center; gap: 10px; font-size: 1.1rem;
            backdrop-filter: blur(10px); color: var(--text-sec);
        }
        .alert-box {
            background: rgba(255, 42, 109, 0.08);
            border: 1px solid rgba(255, 42, 109, 0.25);
            border-radius: 18px;
            padding: 16px 18px;
            color: #ffd6e3;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-box code { color: #fff; }

        /* --- Stats Cards --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;
        }
        .stat-card {
            background: var(--bg-card); border: var(--border-subtle); border-radius: 24px;
            padding: 30px 35px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .stat-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.15); box-shadow: 0 15px 35px rgba(0,0,0,0.5); }
        
        .stat-info .val { font-size: 2.5rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 5px; }
        .stat-info .lbl { font-size: 1.1rem; color: var(--text-sec); font-weight: 500; }
        .stat-icon { font-size: 3rem; opacity: 0.9; }
        
        .c-inb { color: var(--neon-cyan); filter: drop-shadow(0 0 10px rgba(34, 211, 238, 0.3)); }
        .c-pro { color: var(--neon-purple); filter: drop-shadow(0 0 10px rgba(192, 38, 211, 0.3)); }
        .c-loc { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0, 255, 163, 0.3)); }

        /* --- Glass Panel --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 35px;
            flex-grow: 1;
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
            min-height: 500px;
        }

        /* --- Filters --- */
        .filters-row {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.06); margin-bottom: 25px;
        }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; color: var(--text-sec); font-size: 1.05rem; margin-bottom: 10px; font-weight: 600; }
        
        .input-readable {
            width: 100%; height: 55px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 20px; border-radius: 16px;
            font-family: inherit; font-size: 1.1rem; transition: 0.3s;
        }
        .input-readable:focus { background: rgba(0,0,0,0.5); border-color: var(--neon-blue); }
        
        .btn-filter {
            height: 55px; padding: 0 35px;
            background: var(--neon-blue); color: #000;
            border: none; border-radius: 16px;
            font-size: 1.1rem; font-weight: 700; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 20px var(--neon-blue); transform: translateY(-3px); }

        /* --- Actions Toolbar --- */
        .actions-row {
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 25px;
        }
        .btn-act {
            height: 50px; padding: 0 22px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px; color: var(--text-sec);
            font-size: 1.05rem; font-weight: 500; cursor: pointer;
            display: inline-flex; align-items: center; gap: 10px;
            transition: 0.3s; text-decoration: none; white-space: nowrap;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; color: #fff; transform: translateY(-3px); }
        
        .btn-green { color: var(--neon-green); border-color: rgba(0, 255, 163, 0.3); }
        .btn-green:hover { background: rgba(0, 255, 163, 0.1); box-shadow: 0 0 15px rgba(0, 255, 163, 0.2); border-color: var(--neon-green); }
        
        .btn-cyan { color: var(--neon-cyan); border-color: rgba(34, 211, 238, 0.3); }
        .btn-cyan:hover { background: rgba(34, 211, 238, 0.1); box-shadow: 0 0 15px rgba(34, 211, 238, 0.2); border-color: var(--neon-cyan); }

        /* --- Table --- */
        .table-container-flex {
            flex-grow: 1;
            overflow-y: auto; overflow-x: auto;
            border-radius: 18px;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.04);
        }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 1.1rem; }
        .glass-table th {
            text-align: right; padding: 22px 25px;
            color: var(--text-sec); font-weight: 600; background: rgba(255,255,255,0.03);
            position: sticky; top: 0; z-index: 10; backdrop-filter: blur(15px);
        }
        .glass-table tbody tr { transition: 0.15s; }
        .glass-table tbody tr:hover { background: rgba(255,255,255,0.05); }
        .glass-table td {
            padding: 22px 25px; color: #fff; vertical-align: middle;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        /* Checkbox */
        .custom-check {
            width: 24px; height: 24px; border: 2px solid #666; background: transparent; cursor: pointer;
            appearance: none; border-radius: 6px; position: relative; transition: 0.2s;
        }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after { content: '✔'; position: absolute; color: #000; top: -1px; left: 3px; font-size: 16px; font-weight: 800; }

        /* --- Floating Dock (Bigger & Labels Top) --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; z-index: 2000; pointer-events: none;
        }
        .dock {
            pointer-events: auto; display: flex; align-items: center; gap: 12px;
            background: rgba(15, 15, 20, 0.9); backdrop-filter: blur(35px);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 30px; padding: 15px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.9);
            max-width: 95vw; overflow-x: auto; scrollbar-width: none; /* Hide scrollbar */
        }
        .dock::-webkit-scrollbar { display: none; } /* Hide scrollbar Chrome/Safari */
        
        .dock-item {
            width: 60px; height: 60px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 20px;
            color: var(--text-sec); font-size: 1.6rem;
            text-decoration: none; position: relative; background: transparent;
            transition: all 0.25s cubic-bezier(0.3, 0.7, 0.4, 1.5);
        }
        .dock-item:hover {
            width: 75px; height: 75px; margin: 0 6px;
            background: rgba(255,255,255,0.1); color: #fff;
            transform: translateY(-12px);
        }
        .dock-item.active {
            color: var(--neon-blue); background: rgba(0, 242, 255, 0.1);
        }
        
        /* Unified Dock Labels (Labels Top) */
        .dock-label { 
            font-size: 0.9rem; font-weight: 600; opacity: 0; position: absolute; 
            bottom: 100%; /* Shows ABOVE */
            transition: 0.3s; white-space: nowrap; 
            background: rgba(0,0,0,0.9); padding: 4px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2);
            color: #fff; pointer-events: none; margin-bottom: 15px;
        }
        .dock-item:hover .dock-label { opacity: 1; bottom: 100%; transform: translateY(-5px); }
        .dock-item.active .dock-label { opacity: 1; bottom: 100%; color: var(--neon-blue); }

        .dock-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.1); margin: 0 6px; flex-shrink: 0; }

        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .dock { width: 95%; justify-content: flex-start; }
            .dock-item { width: 50px; height: 50px; font-size: 1.4rem; }
            .page-title h1 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>مدیریت اینباندها</h1>
                <p>
                    <i class="fa-solid fa-network-wired" style="color: var(--neon-cyan);"></i>
                    لیست کامل اینباندهای متصل به سیستم
                </p>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
            </div>
        </header>

        <?php if (!empty($dbError)): ?>
            <div class="alert-box anim d-1" style="margin-top: 20px;">
                <div style="font-size: 1.4rem; line-height: 1;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div style="font-size: 1.05rem; color: #ffe8ef;">
                    <?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid anim d-1">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($totalInbounds); ?></div>
                    <div class="lbl">تعداد اینباندها</div>
                </div>
                <i class="fa-solid fa-server stat-icon c-inb"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($uniqueProtocols); ?></div>
                    <div class="lbl">پروتکل‌های یکتا</div>
                </div>
                <i class="fa-solid fa-shield-halved stat-icon c-pro"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($uniqueLocations); ?></div>
                    <div class="lbl">لوکیشن‌های یکتا</div>
                </div>
                <i class="fa-solid fa-location-dot stat-icon c-loc"></i>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="glass-panel anim d-2">
            
            <!-- Filters -->
            <form method="get" class="filters-row">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="q" class="input-readable" placeholder="نام پنل، پروتکل یا اینباند..." value="<?php echo htmlspecialchars($qInput ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <div class="form-group">
                    <label>پروتکل</label>
                    <select name="protocol" class="input-readable">
                        <option value="">همه</option>
                        <?php foreach($protocolOptions as $p): $p = (string)$p; ?>
                            <option value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($protocolInput === $p ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>لوکیشن (نام پنل)</label>
                    <select name="location" class="input-readable">
                        <option value="">همه</option>
                        <?php foreach($locationOptions as $l): $l = (string)$l; ?>
                            <option value="<?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($locationInput === $l ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="max-width: 220px;">
                    <label>نمایش در هر صفحه</label>
                    <select name="per_page" class="input-readable">
                        <?php foreach([10,25,50,100] as $pp): ?>
                            <option value="<?php echo (int)$pp; ?>" <?php echo ($perPage === (int)$pp ? 'selected' : ''); ?>>
                                <?php echo (int)$pp; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-filter">
                    <i class="fa-solid fa-filter"></i> فیلتر
                </button>
                
                <?php if(!empty($_GET['q']) || !empty($_GET['protocol']) || !empty($_GET['location'])): ?>
                    <a href="inbound.php" class="btn-act" style="margin-top: 32px; height: 55px; justify-content: center;">
                        <i class="fa-solid fa-rotate-right" style="font-size: 1.3rem;"></i>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Action Toolbar -->
            <div class="actions-row">
                <span id="inbSelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.2rem; margin-left: 20px;">0 انتخاب</span>
                
                <button class="btn-act" id="inbSelectVisible"><i class="fa-solid fa-check-double"></i> انتخاب همه</button>
                <button class="btn-act" id="inbClearSelection"><i class="fa-solid fa-minus"></i> لغو</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act btn-cyan" id="inbCopyNames"><i class="fa-solid fa-copy"></i> کپی نام‌ها</button>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act btn-green"><i class="fa-solid fa-file-csv"></i> اکسل</a>
            </div>

            <!-- Table -->
            <?php if(empty($listinvoice)): ?>
                <div style="text-align: center; padding: 80px; color: var(--text-sec); flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                    <i class="fa-solid fa-magnifying-glass" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.3;"></i>
                    <h3 style="font-size: 2rem;">اینباندی یافت نشد</h3>
                    <p style="font-size: 1.2rem;">لطفاً فیلترها را بررسی کنید.</p>
                </div>
            <?php else: ?>
                <div class="table-container-flex">
                    <table class="glass-table" id="sample_1">
                        <thead>
                            <tr>
                                <th style="width: 60px;"><i class="fa-solid fa-check"></i></th>
                                <th>نام پنل (لوکیشن)</th>
                                <th>نام پروتکل</th>
                                <th>نام اینباند</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($listinvoice as $list): ?>
                            <tr>
                                <td><input type="checkbox" class="custom-check inb-check" value="1"></td>
                                <td style="font-weight: 800; font-size: 1.2rem; color: #fff;"><?php echo htmlspecialchars($list['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="font-family: monospace; color: var(--neon-purple); font-size: 1.2rem; font-weight: 700;"><?php echo htmlspecialchars($list['protocol'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="color: var(--neon-cyan); font-family: monospace; font-size: 1.1rem;"><?php echo htmlspecialchars($list['NameInbound'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                    $totalPages = (int) max(1, ceil($totalRows / max(1, $perPage)));
                    if ($page > $totalPages) $page = $totalPages;
                    $baseParams = $_GET;
                    unset($baseParams['page'], $baseParams['export']);
                ?>
                <div style="display:flex; gap:12px; align-items:center; justify-content:space-between; margin-top:20px; flex-wrap:wrap;">
                    <div style="color: var(--text-sec); font-size: 1.05rem;">
                        <?php echo number_format($totalRows); ?> نتیجه
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a class="btn-act" href="?<?php echo http_build_query(array_merge($baseParams, ['page'=>1])); ?>" <?php echo ($page<=1?'style="pointer-events:none;opacity:.5"':''); ?>>
                            <i class="fa-solid fa-angles-right"></i> اول
                        </a>
                        <a class="btn-act" href="?<?php echo http_build_query(array_merge($baseParams, ['page'=>max(1,$page-1)])); ?>" <?php echo ($page<=1?'style="pointer-events:none;opacity:.5"':''); ?>>
                            <i class="fa-solid fa-angle-right"></i> قبلی
                        </a>
                        <span class="btn-act" style="cursor:default; color:#fff;">
                            صفحه <?php echo (int)$page; ?> از <?php echo (int)$totalPages; ?>
                        </span>
                        <a class="btn-act" href="?<?php echo http_build_query(array_merge($baseParams, ['page'=>min($totalPages,$page+1)])); ?>" <?php echo ($page>=$totalPages?'style="pointer-events:none;opacity:.5"':''); ?>>
                            بعدی <i class="fa-solid fa-angle-left"></i>
                        </a>
                        <a class="btn-act" href="?<?php echo http_build_query(array_merge($baseParams, ['page'=>$totalPages])); ?>" <?php echo ($page>=$totalPages?'style="pointer-events:none;opacity:.5"':''); ?>>
                            آخر <i class="fa-solid fa-angles-left"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>

    </div>

    <!-- Floating Dock -->
    <div class="dock-container anim d-3">
        <div class="dock">
            <a href="index.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-house-chimney"></i></div>
                <span class="dock-label">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <span class="dock-label">سفارشات</span>
            </a>
            <a href="users.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-users"></i></div>
                <span class="dock-label">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-box-open"></i></div>
                <span class="dock-label">محصولات</span>
            </a>
            <a href="service.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-server"></i></div>
                <span class="dock-label">سرویس‌ها</span>
            </a>
            <div class="dock-divider"></div>
            <a href="cancelService.php" class="dock-item" style="color: var(--neon-red);">
                <div class="dock-icon"><i class="fa-solid fa-ban"></i></div>
                <span class="dock-label">مسدود</span>
            </a>
            <a href="payment.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-credit-card"></i></div>
                <span class="dock-label">مالی</span>
            </a>
            <a href="inbound.php" class="dock-item active">
                <div class="dock-icon"><i class="fa-solid fa-network-wired"></i></div>
                <span class="dock-label">کانفیگ</span>
            </a>
            <a href="setting_x_ui.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-tower-broadcast"></i></div>
                <span class="dock-label">پنل X-UI</span>
            </a>
            <div class="dock-divider"></div>
            <a href="settings.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-gear"></i></div>
                <span class="dock-label">تنظیمات</span>
            </a>
            <a href="login.php" class="dock-item" style="color: var(--neon-red);">
                <div class="dock-icon"><i class="fa-solid fa-power-off"></i></div>
                <span class="dock-label">خروج</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    
    <script>
      (function(){
        function showToast(msg) {
            var $t = $('#inbToast');
            if (!$t.length) { alert(msg); return; }
            $t.text(msg).addClass('show');
            clearTimeout(window.__inbToastTimer);
            window.__inbToastTimer = setTimeout(function(){ $t.removeClass('show'); }, 2500);
        }

        function updateSelCount() {
            var count = $('.inb-check:checked').length;
            $('#inbSelCount').text(count + ' انتخاب');
        }
        $(document).on('change', '.inb-check', updateSelCount);

        $('#inbSelectVisible').on('click', function(e){ 
            e.preventDefault(); 
            $('.inb-check:visible').prop('checked', true); 
            updateSelCount();
        });
        
        $('#inbClearSelection').on('click', function(e){ 
            e.preventDefault(); 
            $('.inb-check').prop('checked', false); 
            updateSelCount();
        });

        $('#inbCopyNames').on('click', function(e){
          e.preventDefault();
          var names = [];
          $('.inb-check:checked').each(function(){
            var row = $(this).closest('tr');
            names.push(row.find('td').eq(3).text().trim());
          });
          if(names.length){
            var text = names.join(', ');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function(){
                    showToast(names.length + ' نام کپی شد');
                }).catch(function(){
                    try{
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        showToast(names.length + ' نام کپی شد');
                    }catch(err){
                        showToast('امکان کپی وجود ندارد');
                    }
                });
            } else {
                try{
                    var ta2 = document.createElement('textarea');
                    ta2.value = text;
                    document.body.appendChild(ta2);
                    ta2.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta2);
                    showToast(names.length + ' نام کپی شد');
                }catch(err2){
                    showToast('امکان کپی وجود ندارد');
                }
            }
          }
          else{ showToast('هیچ ردیفی انتخاب نشده است'); }
        });
        updateSelCount();
      })();
    </script>

    <div id="inbToast" style="position:fixed; bottom:120px; left:20px; z-index:2500; background:rgba(0,0,0,0.85); color:#fff; border:1px solid rgba(255,255,255,0.15); padding:12px 16px; border-radius:14px; opacity:0; transform: translateY(10px); transition: .25s; pointer-events:none; max-width: 70vw; white-space: nowrap; overflow:hidden; text-overflow: ellipsis;"></div>
    <style>#inbToast.show{ opacity:1; transform: translateY(0); }</style>
</body>
</html>
