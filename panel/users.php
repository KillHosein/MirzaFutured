<?php
// --- Logic & Config ---
session_start();
// تنظیمات گزارش خطا
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

// بررسی احراز هویت
if (!isset($_SESSION["user"])) { header('Location: login.php'); exit; }

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!$result) { header('Location: login.php'); exit; }

// --- ساخت کوئری ---
$where = [];
$params = [];

// فیلتر وضعیت
if (!empty($_GET['status'])) {
    $where[] = "LOWER(User_Status) = :st";
    $params[':st'] = strtolower($_GET['status']);
}
// فیلتر نماینده
if (!empty($_GET['agent'])) {
    $where[] = "agent = :ag";
    $params[':ag'] = $_GET['agent'];
}
// جستجو
if (!empty($_GET['q'])) {
    $search = '%' . $_GET['q'] . '%';
    $where[] = "(CAST(id AS CHAR) LIKE :q OR username LIKE :q OR number LIKE :q)";
    $params[':q'] = $search;
}

$sql = "SELECT * FROM user";
if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY id DESC";

$query = $pdo->prepare($sql);
$query->execute($params);
$listusers = $query->fetchAll();

// --- خروجی CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF))); // BOM
    fputcsv($out, ['شناسه', 'نام کاربری', 'شماره', 'موجودی', 'زیرمجموعه', 'وضعیت', 'نماینده']);
    foreach ($listusers as $u) {
        $status = strtolower($u['User_Status']);
        fputcsv($out, [
            $u['id'], $u['username'], $u['number'], $u['Balance'],
            $u['affiliatescount'], $status, $u['agent']
        ]);
    }
    fclose($out);
    exit();
}

// آمارها
$totalUsers = count($listusers);
$activeUsers = 0;
$blockedUsers = 0;
foreach ($listusers as $u) {
    $s = strtolower($u['User_Status']);
    if ($s === 'active') $activeUsers++;
    else if ($s === 'block') $blockedUsers++;
}

$todayDate = function_exists('jdate') ? jdate('l، j F Y') : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت کاربران | نسخه الترا پلاس</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            /* Theme Core: Deep Void & Electric Accents */
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.65);
            --bg-card-hover: rgba(35, 35, 45, 0.8);
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-gold: #fbbf24;
            
            /* Text */
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            
            /* Borders & Shadows */
            --border-subtle: 1px solid rgba(255, 255, 255, 0.05);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.15);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            
            --radius-main: 24px;
        }

        /* --- Base & Scrollbar --- */
        * { box-sizing: border-box; outline: none; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(192, 38, 211, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(0, 242, 255, 0.08) 0%, transparent 45%);
            color: var(--text-pri);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            padding-bottom: 140px;
            overflow-x: hidden;
        }

        /* --- Container --- */
        .dashboard-container {
            width: 100%; max-width: 1920px; margin: 0 auto;
            padding: 35px 5%; 
            display: flex; flex-direction: column; gap: 35px;
        }

        /* --- 1. Header Enhanced --- */
        .header-section {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.03);
            position: relative;
        }
        .header-titles { display: flex; flex-direction: column; gap: 5px; }
        .ht-sup {
            font-size: 0.9rem; color: var(--neon-blue); font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; display: flex; align-items: center; gap: 8px;
        }
        .ht-sup::before { content: ''; width: 20px; height: 2px; background: var(--neon-blue); border-radius: 2px; }
        .ht-main {
            font-size: 3rem; font-weight: 900; margin: 0; line-height: 1.1;
            background: linear-gradient(to right, #ffffff 40%, #94a3b8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .ht-sub { color: var(--text-sec); font-size: 1.1rem; font-weight: 300; margin-top: 5px; }
        .header-actions { display: flex; gap: 15px; align-items: center; }
        .info-pill {
            background: rgba(255,255,255,0.03); border: var(--border-subtle);
            padding: 10px 20px; border-radius: 16px;
            display: flex; align-items: center; gap: 10px;
            backdrop-filter: blur(10px); transition: 0.3s;
        }
        .info-pill:hover { background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.2); transform: translateY(-2px); }

        /* --- 2. Stats Grid (Holo Cards) --- */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;
        }
        .holo-card {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 25px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .holo-card::after {
            content: ''; position: absolute; inset: 0; border-radius: var(--radius-main);
            padding: 1px; background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor; pointer-events: none;
        }
        .holo-card:hover {
            transform: translateY(-5px) scale(1.02);
            background: var(--bg-card-hover);
            box-shadow: 0 15px 40px -10px rgba(0,0,0,0.6);
            border-color: rgba(255,255,255,0.1);
        }
        .hc-info { z-index: 2; }
        .hc-val { font-size: 2.2rem; font-weight: 800; color: #fff; margin-bottom: 5px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .hc-lbl { color: var(--text-sec); font-size: 0.9rem; font-weight: 500; }
        .hc-icon-box {
            width: 60px; height: 60px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; z-index: 2;
            background: rgba(255,255,255,0.03);
            box-shadow: inset 0 0 20px rgba(255,255,255,0.02);
            backdrop-filter: blur(5px); transition: 0.4s;
        }
        .holo-card:hover .hc-icon-box { transform: rotate(-10deg) scale(1.1); }
        .hc-glow {
            position: absolute; width: 150px; height: 150px; border-radius: 50%;
            filter: blur(60px); opacity: 0.15; top: -50px; right: -50px; transition: 0.4s;
        }
        .holo-card:hover .hc-glow { opacity: 0.3; }

        .hc-blue .hc-icon-box { color: var(--neon-blue); border: 1px solid rgba(0, 242, 255, 0.2); }
        .hc-blue .hc-glow { background: var(--neon-blue); }
        .hc-green .hc-icon-box { color: var(--neon-green); border: 1px solid rgba(0, 255, 163, 0.2); }
        .hc-green .hc-glow { background: var(--neon-green); }
        .hc-red .hc-icon-box { color: var(--neon-red); border: 1px solid rgba(255, 42, 109, 0.2); }
        .hc-red .hc-glow { background: var(--neon-red); }

        /* --- 3. Glass Panel & Filters --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 30px; display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
        }

        .filters-row {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;
            padding-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 30px;
        }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; color: var(--text-sec); font-size: 0.9rem; margin-bottom: 8px; font-weight: 600; }
        
        .input-readable {
            width: 100%; height: 50px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 15px; border-radius: 14px;
            font-family: inherit; font-size: 1rem; transition: 0.3s;
        }
        .input-readable:focus {
            background: rgba(0,0,0,0.5); border-color: var(--neon-blue);
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.1);
        }
        select.input-readable option { background: #111; color: #fff; }

        .btn-filter {
            height: 50px; padding: 0 30px;
            background: var(--neon-blue); color: #000;
            border: none; border-radius: 14px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 20px var(--neon-blue); transform: translateY(-2px); }

        /* Actions Toolbar */
        .actions-row {
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 25px;
        }
        .btn-act {
            height: 45px; padding: 0 20px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; color: var(--text-sec);
            font-size: 0.95rem; font-weight: 500; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: #fff; transform: translateY(-2px); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255, 42, 109, 0.3); }
        .btn-red:hover { background: rgba(255, 42, 109, 0.1); box-shadow: 0 0 15px rgba(255, 42, 109, 0.2); border-color: var(--neon-red); }
        
        .btn-green { color: var(--neon-green); border-color: rgba(0, 255, 163, 0.3); }
        .btn-green:hover { background: rgba(0, 255, 163, 0.1); box-shadow: 0 0 15px rgba(0, 255, 163, 0.2); border-color: var(--neon-green); }

        /* Table */
        .table-wrap { overflow-x: auto; }
        .glass-table {
            width: 100%; border-collapse: separate; border-spacing: 0 8px; font-size: 1rem;
        }
        .glass-table th {
            text-align: right; padding: 15px 20px;
            color: var(--text-sec); font-size: 0.85rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .glass-table tbody tr {
            background: rgba(255,255,255,0.02); transition: 0.2s;
        }
        .glass-table tbody tr:hover {
            background: rgba(255,255,255,0.06); transform: scale(1.002);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .glass-table td {
            padding: 20px; color: #fff; vertical-align: middle;
            border-top: 1px solid rgba(255,255,255,0.03);
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .glass-table td:first-child { border-radius: 0 16px 16px 0; border-right: 1px solid rgba(255,255,255,0.03); }
        .glass-table td:last-child { border-radius: 16px 0 0 16px; border-left: 1px solid rgba(255,255,255,0.03); }

        /* Badges & Checkbox */
        .badge {
            padding: 6px 14px; border-radius: 50px; font-size: 0.85rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .badge.active { background: rgba(0, 255, 163, 0.1); color: var(--neon-green); border: 1px solid rgba(0, 255, 163, 0.2); }
        .badge.block { background: rgba(255, 42, 109, 0.1); color: var(--neon-red); border: 1px solid rgba(255, 42, 109, 0.2); }
        .badge.other { background: rgba(255, 255, 255, 0.05); color: #ccc; border: 1px solid rgba(255, 255, 255, 0.1); }

        .custom-check {
            width: 20px; height: 20px; border: 2px solid #555; background: transparent; cursor: pointer;
            appearance: none; border-radius: 6px; position: relative; transition: 0.2s;
        }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after {
            content: '✔'; position: absolute; color: #000; top: -2px; left: 3px; font-size: 14px; font-weight: 800;
        }

        /* --- Super Dock --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 0; right: 0;
            display: flex; justify-content: center; pointer-events: none; z-index: 9999;
        }
        .super-dock {
            pointer-events: auto;
            background: rgba(15, 15, 20, 0.85);
            backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.1);
            padding: 12px; border-radius: 28px;
            display: flex; align-items: center; gap: 8px;
            transition: 0.3s;
        }
        .dock-item {
            width: 54px; height: 54px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 18px;
            color: var(--text-sec); font-size: 1.5rem;
            text-decoration: none; position: relative;
            background: transparent;
            transition: all 0.25s cubic-bezier(0.3, 0.7, 0.4, 1.5);
            border: 1px solid transparent;
        }
        .dock-item:hover {
            width: 65px; height: 65px; margin: 0 5px;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
            border-color: rgba(255,255,255,0.3);
            color: #fff; box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            transform: translateY(-15px);
        }
        .dock-item.active {
            color: var(--neon-blue);
            background: rgba(0, 242, 255, 0.1);
            border-color: rgba(0, 242, 255, 0.3);
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.15);
        }
        .dock-item::before {
            content: attr(data-tooltip);
            position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(10px) scale(0.8);
            background: rgba(0,0,0,0.9); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; padding: 6px 14px; border-radius: 10px;
            font-size: 0.8rem; font-weight: 600; white-space: nowrap;
            opacity: 0; visibility: hidden; transition: 0.2s; pointer-events: none; margin-bottom: 15px;
        }
        .dock-item:hover::before { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0) scale(1); }
        .dock-divider { width: 1px; height: 35px; background: rgba(255,255,255,0.1); margin: 0 5px; }

        @media (max-width: 768px) {
            .dashboard-container { padding: 20px; }
            .header-section { flex-direction: column; align-items: flex-start; gap: 20px; }
            .header-actions { width: 100%; justify-content: space-between; }
            .super-dock { max-width: 95vw; overflow-x: auto; justify-content: flex-start; padding: 10px; border-radius: 20px; }
            .dock-item { flex-shrink: 0; width: 48px; height: 48px; }
            .dock-item:hover { width: 48px; height: 48px; margin: 0; transform: translateY(-5px); }
            .ht-main { font-size: 2rem; }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- 1. Cinematic Header -->
        <header class="header-section">
            <div class="header-titles">
                <div class="ht-sup">پنل مدیریت</div>
                <h1 class="ht-main">مدیریت کاربران</h1>
                <div class="ht-sub">
                    <i class="fa-solid fa-users-gear" style="color: var(--neon-purple);"></i>
                    لیست کامل مشترکین و ابزارهای مدیریتی
                </div>
            </div>
            
            <div class="header-actions">
                <div class="info-pill">
                    <i class="fa-regular fa-calendar" style="color: var(--text-sec);"></i>
                    <span style="font-weight: 500;"><?php echo $todayDate; ?></span>
                </div>
            </div>
        </header>

        <!-- 2. Stats Row (Holo Cards) -->
        <section class="stats-row">
            <div class="holo-card hc-blue">
                <div class="hc-glow"></div>
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($totalUsers); ?></div>
                    <div class="hc-lbl">کل کاربران</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="holo-card hc-green">
                <div class="hc-glow"></div>
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($activeUsers); ?></div>
                    <div class="hc-lbl">کاربران فعال</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-user-check"></i></div>
            </div>
            <div class="holo-card hc-red">
                <div class="hc-glow"></div>
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($blockedUsers); ?></div>
                    <div class="hc-lbl">کاربران مسدود</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-user-lock"></i></div>
            </div>
        </section>

        <!-- 3. Main Glass Panel -->
        <div class="glass-panel">
            
            <!-- Filter Section -->
            <form method="get" class="filters-row">
                <div class="form-group">
                    <label>جستجو در کاربران</label>
                    <input type="text" name="q" class="input-readable" placeholder="نام، آیدی عددی یا شماره..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>وضعیت حساب</label>
                    <select name="status" class="input-readable">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="active" <?php echo (isset($_GET['status']) && $_GET['status']==='active')?'selected':''; ?>>فعال</option>
                        <option value="block" <?php echo (isset($_GET['status']) && $_GET['status']==='block')?'selected':''; ?>>مسدود</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>سطح کاربری</label>
                    <select name="agent" class="input-readable">
                        <option value="">همه کاربران</option>
                        <option value="f" <?php echo (isset($_GET['agent']) && $_GET['agent']==='f')?'selected':''; ?>>کاربر عادی</option>
                        <option value="n" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n')?'selected':''; ?>>نماینده</option>
                        <option value="n2" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n2')?'selected':''; ?>>نماینده ارشد</option>
                    </select>
                </div>
                <div style="padding-bottom: 2px;">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-search"></i> جستجو</button>
                </div>
                <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['agent'])): ?>
                    <div style="padding-bottom: 2px;">
                        <a href="users.php" class="btn-act" style="height: 50px; justify-content: center;"><i class="fa-solid fa-rotate-right"></i></a>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Actions Toolbar -->
            <div class="actions-row">
                <span id="usersSelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.1rem; margin-left: 15px;">0 انتخاب</span>
                
                <button class="btn-act" id="usersSelectVisible"><i class="fa-solid fa-check-double"></i> انتخاب همه</button>
                <button class="btn-act" id="usersClearSelection"><i class="fa-solid fa-minus"></i> لغو</button>
                <div style="flex:1"></div>
                <button class="btn-act" id="usersCopy"><i class="fa-solid fa-copy"></i> کپی ID</button>
                <button class="btn-act btn-green" id="usersUnblockSel"><i class="fa-solid fa-unlock"></i> فعال‌سازی</button>
                <button class="btn-act btn-red" id="usersBlockSel"><i class="fa-solid fa-ban"></i> مسدودسازی</button>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i> اکسل</a>
            </div>

            <div class="actions-row" style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05);">
                <input type="number" id="usersAmount" class="input-readable" placeholder="مبلغ (تومان)" style="max-width: 180px; height: 45px;">
                <button class="btn-act" id="usersAddBalance"><i class="fa-solid fa-plus"></i> شارژ</button>
                <button class="btn-act" id="usersLowBalance"><i class="fa-solid fa-minus"></i> کسر</button>
                <div style="width: 1px; height: 30px; background: rgba(255,255,255,0.1); margin: 0 10px;"></div>
                <input type="text" id="usersMessage" class="input-readable" placeholder="متن پیام گروهی..." style="max-width: 300px; height: 45px; flex-grow: 1;">
                <button class="btn-act" id="usersSendMsg"><i class="fa-solid fa-paper-plane"></i> ارسال</button>
            </div>

            <!-- Table -->
            <div style="margin-top: 20px;">
                <?php if(!$totalUsers): ?>
                    <div style="text-align: center; padding: 60px; color: var(--text-sec);">
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
                        <h3>کاربری یافت نشد</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="glass-table" id="sample_1">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>آیدی عددی</th>
                                    <th>نام کاربری</th>
                                    <th>شماره تماس</th>
                                    <th>موجودی</th>
                                    <th>زیرمجموعه</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($listusers as $list): 
                                    $s = strtolower($list['User_Status']);
                                    $statusLabel = ($s === 'active') ? 'فعال' : (($s === 'block') ? 'مسدود' : $list['User_Status']);
                                    $statusClass = ($s === 'active') ? 'active' : (($s === 'block') ? 'block' : 'other');
                                    $phone = ($list['number'] == "none") ? '<span style="opacity:0.3;">---</span>' : $list['number'];
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="checkboxes custom-check" value="1"></td>
                                    <td style="font-family: monospace; color: var(--neon-blue); letter-spacing: 1px; font-weight: 600;"><?php echo $list['id']; ?></td>
                                    <td style="font-weight: 700; color: #fff; font-size: 1.1rem;"><?php echo htmlspecialchars($list['username']); ?></td>
                                    <td><?php echo $phone; ?></td>
                                    <td style="color: var(--neon-gold); font-weight: 700;"><?php echo number_format($list['Balance']); ?></td>
                                    <td><?php echo number_format($list['affiliatescount']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                    <td>
                                        <a href="user.php?id=<?php echo $list['id']; ?>" class="btn-act" style="height: 38px; font-size: 0.85rem; padding: 0 15px;">
                                            <i class="fa-solid fa-gear"></i> مدیریت
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- 4. Super Dock -->
    <div class="dock-container">
        <nav class="super-dock">
            <a href="index.php" class="dock-item" data-tooltip="داشبورد"><i class="fa-solid fa-house"></i></a>
            <div class="dock-divider"></div>
            <a href="users.php" class="dock-item active" data-tooltip="کاربران"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-item" data-tooltip="سفارشات"><i class="fa-solid fa-file-contract"></i></a>
            <a href="product.php" class="dock-item" data-tooltip="محصولات"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-item" data-tooltip="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
            <div class="dock-divider"></div>
            <a href="cancelService.php" class="dock-item" data-tooltip="لیست مسدود" style="color: var(--neon-red);"><i class="fa-solid fa-ban"></i></a>
            <a href="payment.php" class="dock-item" data-tooltip="امور مالی"><i class="fa-solid fa-wallet"></i></a>
            <a href="inbound.php" class="dock-item" data-tooltip="کانفیگ سرور"><i class="fa-solid fa-network-wired"></i></a>
            <a href="seeting_x_ui.php" class="dock-item" data-tooltip="پنل X-UI"><i class="fa-solid fa-tower-broadcast"></i></a>
            <div class="dock-divider"></div>
            <a href="settings.php" class="dock-item" data-tooltip="تنظیمات"><i class="fa-solid fa-gear"></i></a>
            <a href="logout.php" class="dock-item" data-tooltip="خروج" style="color: var(--neon-red);"><i class="fa-solid fa-power-off"></i></a>
        </nav>
    </div>

    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
      (function(){
        // Notification
        function showToast(msg) { alert(msg); }

        // Selection
        function updateSelCount() {
            var count = $('#sample_1 tbody .checkboxes:checked').length;
            $('#usersSelCount').text(count + ' انتخاب');
        }
        $(document).on('change', '.checkboxes', updateSelCount);

        $('#usersSelectVisible').on('click', function(e){ 
            e.preventDefault(); 
            $('#sample_1 tbody tr:visible').each(function(){ $(this).find('.checkboxes').prop('checked', true); }); 
            updateSelCount();
        });
        $('#usersClearSelection').on('click', function(e){ 
            e.preventDefault(); 
            $('#sample_1 tbody .checkboxes').prop('checked', false); 
            updateSelCount();
        });

        // Copy
        $('#usersCopy').on('click', function(e){
          e.preventDefault();
          var ids = [];
          $('#sample_1 tbody tr').each(function(){
            var $row = $(this);
            if($row.find('.checkboxes').prop('checked')){ ids.push($row.find('td').eq(1).text().trim()); }
          });
          if(ids.length){ navigator.clipboard.writeText(ids.join(', ')); showToast(ids.length + ' آیدی کپی شد'); }
          else{ showToast('کاربری انتخاب نشده است'); }
        });

        // Bulk Logic
        function bulkAction(type, val) {
            var ids = [];
            $('#sample_1 tbody tr').each(function(){
                var $r=$(this); 
                if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); 
            });
            
            if(!ids.length){ showToast('کاربری انتخاب نشده است'); return; }
            if(!confirm('آیا از اعمال تغییرات روی ' + ids.length + ' کاربر مطمئن هستید؟')) return;

            var done=0, total=ids.length;
            ids.forEach(function(id){ 
                var data = {id: id};
                if(type === 'status') data.status = val;
                if(type === 'msg') data.textmessage = val;
                if(type === 'add') data.priceadd = val;
                if(type === 'low') data.pricelow = val;
                if(type === 'agent') data.agent = val;

                $.get('user.php', data).always(function(){ 
                    done++; 
                    if(done === total){ showToast('عملیات با موفقیت انجام شد'); setTimeout(function(){ location.reload(); }, 800); } 
                }); 
            });
        }

        $('#usersBlockSel').on('click', function(e){ e.preventDefault(); bulkAction('status', 'block'); });
        $('#usersUnblockSel').on('click', function(e){ e.preventDefault(); bulkAction('status', 'active'); });
        
        $('#usersSendMsg').on('click', function(e){ 
            e.preventDefault(); var t=$('#usersMessage').val(); 
            if(t) bulkAction('msg', t); else showToast('متن پیام خالی است');
        });

        $('#usersAddBalance').on('click', function(e){
            e.preventDefault(); var a = parseInt($('#usersAmount').val());
            if(a) bulkAction('add', a); else showToast('مبلغ نامعتبر');
        });

        $('#usersLowBalance').on('click', function(e){
            e.preventDefault(); var a = parseInt($('#usersAmount').val());
            if(a) bulkAction('low', a); else showToast('مبلغ نامعتبر');
        });
      })();
    </script>
</body>
</html>