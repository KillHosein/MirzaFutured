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

try {
    $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    if (!$result) {
        header('Location: login.php');
        exit;
    }
} catch (Throwable $e) {
    header('Location: login.php');
    exit;
}

$dbReady = isset($pdo) && ($pdo instanceof PDO);
if (!$dbReady) {
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>خطا</title></head><body style="font-family:tahoma,sans-serif;background:#0b0b10;color:#fff;padding:30px"><h2>خطا در اتصال به دیتابیس</h2><p>اتصال دیتابیس برقرار نیست یا تنظیمات `config.php` مشکل دارد.</p><a href="login.php" style="color:#22d3ee">رفتن به ورود</a></body></html>';
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_token'];

/**
 * @param string $token
 * @return bool
 */
function isValidCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals((string) $_SESSION['csrf_token'], (string) $token);
}

/**
 * @param string $codepanel
 * @return array|null
 */
function fetchXuiPanelRow($codepanel)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT x_ui.codepanel, x_ui.setting, marzban_panel.name_panel 
            FROM x_ui 
            LEFT JOIN marzban_panel ON marzban_panel.code_panel = x_ui.codepanel
            WHERE x_ui.codepanel = :codepanel
            LIMIT 1");
        $stmt->bindParam(':codepanel', $codepanel, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('XUI fetch row error: ' . $e->getMessage());
        return null;
    }
}

/**
 * @param string $codepanel
 * @param string $normalizedJson
 * @return void
 */
function saveXuiPanelSetting($codepanel, $normalizedJson)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE x_ui SET setting = :setting WHERE codepanel = :codepanel");
        $stmt->bindParam(':setting', $normalizedJson, PDO::PARAM_STR);
        $stmt->bindParam(':codepanel', $codepanel, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    } catch (Throwable $e) {
        error_log('XUI save setting error: ' . $e->getMessage());
        return false;
    }
}

// --- Fetch X-UI Panels ---
try {
    $panelsStmt = $pdo->query("SELECT x_ui.codepanel, marzban_panel.name_panel
        FROM x_ui
        LEFT JOIN marzban_panel ON marzban_panel.code_panel = x_ui.codepanel
        ORDER BY COALESCE(marzban_panel.name_panel, x_ui.codepanel) ASC");
    $resultpanel = $panelsStmt ? ($panelsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
    error_log('XUI list panels error: ' . $e->getMessage());
    $resultpanel = [];
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$alert = null;

$selectedPanel = '';
if ($action === 'change' || $action === 'export') {
    $selectedPanel = trim((string)($_GET['namepanel'] ?? ''));
}
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPanel = trim((string)($_POST['namepanel'] ?? ''));
}

if (($action === 'change' || $action === 'export') && $selectedPanel === '') {
    $action = '';
}

if (strlen($selectedPanel) > 100) {
    $selectedPanel = substr($selectedPanel, 0, 100);
}

$panelRow = $selectedPanel !== '' ? fetchXuiPanelRow($selectedPanel) : null;
if ($selectedPanel !== '' && !$panelRow && ($action === 'change' || $action === 'export' || $action === 'save')) {
    $alert = ['type' => 'danger', 'message' => 'پنل انتخاب‌شده معتبر نیست یا در دیتابیس یافت نشد.'];
    $action = '';
    $selectedPanel = '';
}

// --- Action Handler ---
if ($action === 'export' && $selectedPanel !== '' && $panelRow) {
    $dataToExport = $panelRow['setting'] ?? '';
    $decoded = json_decode((string) $dataToExport, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        $decoded = $dataToExport;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=xui-setting-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $selectedPanel) . '-' . date('Y-m-d') . '.json');
    echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $alert = ['type' => 'danger', 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید و دوباره تلاش کنید.'];
        $action = 'change';
    } elseif ($selectedPanel === '' || !$panelRow) {
        $alert = ['type' => 'danger', 'message' => 'پنل انتخاب‌شده معتبر نیست.'];
        $action = '';
    } else {
        $rawSettings = (string)($_POST['settings'] ?? '');
        if (strlen($rawSettings) > 200000) {
            $alert = ['type' => 'danger', 'message' => 'حجم JSON خیلی بزرگ است.'];
            $action = 'change';
        } else {
            $decoded = json_decode($rawSettings, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $alert = ['type' => 'danger', 'message' => 'JSON نامعتبر است: ' . json_last_error_msg()];
                $action = 'change';
            } elseif (!is_array($decoded)) {
                $alert = ['type' => 'danger', 'message' => 'JSON باید یک شیء یا آرایه باشد.'];
                $action = 'change';
            } else {
                $normalized = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($normalized === false) {
                    $alert = ['type' => 'danger', 'message' => 'ذخیره‌سازی JSON با خطا مواجه شد.'];
                    $action = 'change';
                } else {
                    if (saveXuiPanelSetting($selectedPanel, $normalized)) {
                        header('Location: setting_x_ui.php?action=change&namepanel=' . rawurlencode($selectedPanel) . '&saved=1');
                        exit;
                    }
                    $alert = ['type' => 'danger', 'message' => 'ذخیره‌سازی با خطا مواجه شد.'];
                    $action = 'change';
                }
            }
        }
    }
}

$namepanel = $selectedPanel;
$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تنظیمات X-UI</title>
    
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
            width: 100%; padding: 30px 4%; max-width: 1400px; margin: 0 auto;
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

        /* --- Glass Panel --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 40px;
            flex-grow: 1;
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
            min-height: 500px;
        }

        /* --- Inputs & Editors --- */
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; color: var(--text-sec); font-size: 1.1rem; margin-bottom: 10px; font-weight: 600; }
        
        .input-readable {
            width: 100%; height: 60px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 20px; border-radius: 16px;
            font-family: inherit; font-size: 1.2rem; transition: 0.3s;
        }
        .input-readable:focus { background: rgba(0,0,0,0.5); border-color: var(--neon-cyan); box-shadow: 0 0 20px rgba(34, 211, 238, 0.2); }
        select.input-readable { cursor: pointer; }
        select.input-readable option { background: #111; color: #fff; }

        .json-editor {
            width: 100%; min-height: 500px;
            background: #0d0d10; border: 1px solid rgba(255,255,255,0.1);
            color: #a6accd; padding: 20px; border-radius: 16px;
            font-family: 'Consolas', 'Monaco', monospace; font-size: 1rem;
            line-height: 1.6; resize: vertical; transition: 0.3s;
            direction: ltr; text-align: left;
        }
        .json-editor:focus { border-color: var(--neon-purple); outline: none; box-shadow: 0 0 20px rgba(192, 38, 211, 0.2); }

        .btn-act {
            height: 60px; padding: 0 30px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; color: var(--text-sec); font-size: 1.2rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 12px;
            transition: 0.3s; text-decoration: none; justify-content: center;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; transform: translateY(-3px); color: #fff; }
        
        .btn-primary-glow { background: var(--neon-cyan); color: #000; border: none; font-weight: 800; }
        .btn-primary-glow:hover { background: #fff; box-shadow: 0 0 30px var(--neon-cyan); color: #000; }
        
        .btn-green-glow { background: var(--neon-green); color: #000; border: none; font-weight: 800; }
        .btn-green-glow:hover { background: #fff; box-shadow: 0 0 30px var(--neon-green); color: #000; }

        .alert-box {
            width: 100%;
            padding: 14px 16px;
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(0,0,0,0.25);
            color: #fff;
            font-size: 1.05rem;
        }
        .alert-box.success { border-color: rgba(0,255,163,0.35); }
        .alert-box.danger { border-color: rgba(255,42,109,0.35); }
        .editor-toolbar {
            display:flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .editor-toolbar .tools {
            display:flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-small {
            height: 46px;
            padding: 0 16px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
        }
        .file-input {
            display:none;
        }

        /* --- Step Layout --- */
        .step-container {
            max-width: 600px; margin: 0 auto; text-align: center; padding: 40px 0;
        }
        .step-icon { font-size: 6rem; color: var(--neon-cyan); margin-bottom: 30px; opacity: 0.9; filter: drop-shadow(0 0 20px rgba(34,211,238,0.3)); }

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
                <h1>تنظیمات X-UI</h1>
                <p>
                    <i class="fa-solid fa-sliders" style="color: var(--neon-cyan);"></i>
                    پیکربندی و مدیریت تنظیمات کانفیگ‌ها
                </p>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar"></i>
                <span><?php echo $todayDate; ?></span>
            </div>
        </header>

        <!-- Main Panel -->
        <div class="glass-panel anim d-1">
            
            <?php if($action !== 'change'): ?>
                <!-- Step 1: Select Panel -->
                <div class="step-container">
                    <i class="fa-solid fa-server step-icon"></i>
                    <h2 style="margin-bottom: 10px; font-weight: 800; font-size: 2rem;">انتخاب پنل</h2>
                    <p style="color: var(--text-sec); margin-bottom: 40px; font-size: 1.1rem;">
                        لطفاً پنل مورد نظر برای ویرایش تنظیمات JSON را انتخاب کنید.
                    </p>
                    
                    <?php if(isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
                        <div class="alert-box success"><i class="fa-solid fa-check"></i> تنظیمات با موفقیت ذخیره شد.</div>
                    <?php endif; ?>
                    <?php if($alert): ?>
                        <div class="alert-box <?php echo htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="GET" action="setting_x_ui.php">
                        <input type="hidden" name="action" value="change">
                        <div class="form-group">
                            <select name="namepanel" class="input-readable" required>
                                <option value="">پنل را انتخاب کنید...</option>
                                <?php
                                if(count($resultpanel) > 0){
                                    foreach($resultpanel as $panel){
                                        $code = (string)($panel['codepanel'] ?? '');
                                        $nm = (string)($panel['name_panel'] ?? $code);
                                        echo "<option value=\"" . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-act btn-primary-glow" style="width: 100%; margin-top: 20px;">
                            مرحله بعد <i class="fa-solid fa-arrow-left" style="margin-right: 10px;"></i>
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- Step 2: Edit Settings -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px;">
                    <h3 style="margin:0; font-weight: 800; font-size: 1.8rem; color: var(--neon-cyan);">
                        <i class="fa-solid fa-code" style="margin-left: 10px;"></i> ویرایش کانفیگ JSON
                    </h3>
                    <a href="setting_x_ui.php" class="btn-act" style="height: 45px; padding: 0 20px; font-size: 1rem;">
                        <i class="fa-solid fa-rotate-right"></i> بازگشت
                    </a>
                </div>

                <?php if(isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
                    <div class="alert-box success"><i class="fa-solid fa-check"></i> تنظیمات با موفقیت ذخیره شد.</div>
                <?php endif; ?>
                <?php if($alert): ?>
                    <div class="alert-box <?php echo htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>قالب‌های آماده (اختیاری)</label>
                    <select id="mySelect" onchange="updateTextarea()" class="input-readable">
                        <option value="">یک قالب انتخاب کنید...</option>
                        <option value="tcp_http">TCP + HTTP</option>
                        <option value="ws_tls">WS + TLS</option>
                    </select>
                </div>

                <div class="editor-toolbar">
                    <div style="color: var(--text-sec); font-weight: 700;">
                        <?php echo htmlspecialchars((string)($panelRow['name_panel'] ?? $namepanel), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="tools">
                        <button type="button" class="btn-act btn-small" id="btnValidate"><i class="fa-solid fa-circle-check"></i> اعتبارسنجی</button>
                        <button type="button" class="btn-act btn-small" id="btnFormat"><i class="fa-solid fa-wand-magic-sparkles"></i> فرمت</button>
                        <button type="button" class="btn-act btn-small" id="btnCopy"><i class="fa-solid fa-copy"></i> کپی</button>
                        <a class="btn-act btn-small" href="setting_x_ui.php?<?php echo http_build_query(['action'=>'export','namepanel'=>$namepanel]); ?>"><i class="fa-solid fa-download"></i> دانلود</a>
                        <label class="btn-act btn-small" for="jsonFile" style="margin:0;"><i class="fa-solid fa-file-arrow-up"></i> بارگذاری</label>
                        <input class="file-input" id="jsonFile" type="file" accept="application/json">
                    </div>
                </div>

                <form method="POST" action="setting_x_ui.php?action=save" id="xuiForm">
                    <div class="form-group">
                        <label>ویرایشگر تنظیمات</label>
                        <textarea id="settings" name="settings" class="json-editor"><?php
                            $displayText = '';
                            if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                                $displayText = (string)($_POST['settings'] ?? '');
                            } elseif ($panelRow && isset($panelRow['setting']) && $panelRow['setting'] !== null && $panelRow['setting'] !== '') {
                                $data = json_decode((string)$panelRow['setting']);
                                $displayText = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            }
                            echo htmlspecialchars($displayText, ENT_NOQUOTES, 'UTF-8');
                        ?></textarea>
                        <input name="namepanel" type="hidden" value="<?php echo htmlspecialchars($namepanel, ENT_QUOTES, 'UTF-8'); ?>">
                        <input name="csrf_token" type="hidden" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    
                    <button type="submit" class="btn-act btn-green-glow" style="width: 100%; margin-top: 10px;">
                        <i class="fa-solid fa-floppy-disk"></i> ذخیره تنظیمات
                    </button>
                </form>
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
            <a href="inbound.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-network-wired"></i></div>
                <span class="dock-label">کانفیگ</span>
            </a>
            <a href="setting_x_ui.php" class="dock-item active">
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
        function showInlineMessage(type, message) {
            var el = document.getElementById('inlineMsg');
            if (!el) return;
            el.className = 'alert-box ' + (type === 'success' ? 'success' : 'danger');
            el.textContent = message;
            el.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function parseJsonEditor() {
            var textarea = document.getElementById('settings');
            if (!textarea) return null;
            var raw = textarea.value || '';
            return JSON.parse(raw);
        }

        function formatJsonEditor() {
            var textarea = document.getElementById('settings');
            var obj = parseJsonEditor();
            textarea.value = JSON.stringify(obj, null, 2);
        }

        function copyToClipboard(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function(resolve, reject){
                try{
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    resolve();
                }catch(e){
                    reject(e);
                }
            });
        }

        function updateTextarea() {
            var selectElement = document.getElementById("mySelect");
            var textareaElement = document.getElementById("settings");
            var selectedOption = selectElement.options[selectElement.selectedIndex].value;
            
            if (selectedOption === "tcp_http") {
                textareaElement.value = JSON.stringify({
                    "network": "tcp",
                    "security": "none",
                    "externalProxy": [],
                    "tcpSettings": {
                        "acceptProxyProtocol": false,
                        "header": {
                            "type": "http",
                            "request": {
                                "version": "1.1",
                                "method": "GET",
                                "path": ["/"],
                                "headers": { "host": ["zula.ir"] }
                            },
                            "response": {
                                "version": "1.1",
                                "status": "200",
                                "reason": "OK",
                                "headers": {}
                            }
                        }
                    }
                }, null, 2);
            } else if (selectedOption === "ws_tls") {
                textareaElement.value = JSON.stringify({
                    "network": "ws",
                    "security": "tls",
                    "externalProxy": [],
                    "tlsSettings": {
                        "serverName": "sni.com",
                        "minVersion": "1.2",
                        "maxVersion": "1.3",
                        "cipherSuites": "",
                        "rejectUnknownSni": true,
                        "certificates": [{
                            "certificateFile": "",
                            "keyFile": "",
                            "ocspStapling": 3600
                        }],
                        "alpn": ["h2", "http/1.1"],
                        "settings": {
                            "allowInsecure": true,
                            "fingerprint": ""
                        }
                    },
                    "wsSettings": {
                        "acceptProxyProtocol": false,
                        "path": "/",
                        "headers": {}
                    }
                }, null, 2);
            }
        }

        (function(){
            var validateBtn = document.getElementById('btnValidate');
            var formatBtn = document.getElementById('btnFormat');
            var copyBtn = document.getElementById('btnCopy');
            var form = document.getElementById('xuiForm');
            var fileInput = document.getElementById('jsonFile');
            var textarea = document.getElementById('settings');

            if (validateBtn) {
                validateBtn.addEventListener('click', function(){
                    try{
                        parseJsonEditor();
                        showInlineMessage('success', 'JSON معتبر است.');
                    }catch(e){
                        showInlineMessage('danger', 'JSON نامعتبر است: ' + (e && e.message ? e.message : 'خطای نامشخص'));
                    }
                });
            }
            if (formatBtn) {
                formatBtn.addEventListener('click', function(){
                    try{
                        formatJsonEditor();
                        showInlineMessage('success', 'JSON با موفقیت فرمت شد.');
                    }catch(e){
                        showInlineMessage('danger', 'امکان فرمت وجود ندارد: ' + (e && e.message ? e.message : 'خطای نامشخص'));
                    }
                });
            }
            if (copyBtn) {
                copyBtn.addEventListener('click', function(){
                    if (!textarea) return;
                    copyToClipboard(textarea.value || '').then(function(){
                        showInlineMessage('success', 'کپی شد.');
                    }).catch(function(){
                        showInlineMessage('danger', 'امکان کپی وجود ندارد.');
                    });
                });
            }
            if (fileInput && textarea) {
                fileInput.addEventListener('change', function(){
                    var f = fileInput.files && fileInput.files[0];
                    if (!f) return;
                    var reader = new FileReader();
                    reader.onload = function(){
                        textarea.value = reader.result || '';
                        try{
                            formatJsonEditor();
                            showInlineMessage('success', 'فایل بارگذاری شد.');
                        }catch(e){
                            showInlineMessage('danger', 'فایل بارگذاری شد ولی JSON معتبر نیست.');
                        }
                        fileInput.value = '';
                    };
                    reader.readAsText(f);
                });
            }
            if (form) {
                form.addEventListener('submit', function(e){
                    try{
                        parseJsonEditor();
                    }catch(err){
                        e.preventDefault();
                        showInlineMessage('danger', 'JSON نامعتبر است و ذخیره نشد.');
                    }
                });
            }
        })();
    </script>

    <div id="inlineMsg" class="alert-box" style="display:none; position:fixed; top:20px; left:20px; z-index:2500; max-width: 420px; box-shadow: 0 10px 35px rgba(0,0,0,0.6);"></div>
</body>
</html>
