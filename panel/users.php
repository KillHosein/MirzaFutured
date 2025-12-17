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
    <title>مدیریت کاربران | پنل حرفه‌ای</title>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <!-- Core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            /* Palette: Midnight Neon */
            --bg-body: #020204;
            --bg-glass: rgba(20, 20, 25, 0.85); /* تیره‌تر برای خوانایی بیشتر */
            --bg-dock: rgba(10, 10, 10, 0.95);
            
            --neon-blue: #00f3ff;
            --neon-purple: #bc13fe;
            --neon-green: #00ff88;
            --neon-red: #ff004c;
            --neon-amber: #ffb700;
            
            --text-main: #ffffff;
            --text-muted: #b0b0b0;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.12);
            --shadow-float: 0 15px 50px rgba(0,0,0,0.9);
            
            --radius-lg: 24px;
            --radius-md: 16px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            /* پس‌زمینه کهکشانی تاریک */
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(0, 243, 255, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(188, 19, 254, 0.04) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 160px; /* فضای بیشتر برای داک */
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Animations --- */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.98) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .anim { animation: fadeInScale 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; }

        /* --- Layout --- */
        .container-fluid-custom {
            width: 100%; padding: 40px 4%; max-width: 1900px; margin: 0 auto;
        }

        /* --- Header --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 25px;
        }
        .page-title h1 {
            font-size: 3.5rem; font-weight: 900; margin: 0; color: #fff;
            text-shadow: 0 0 25px rgba(255,255,255,0.15); letter-spacing: -1px;
        }
        .page-title p {
            color: var(--text-muted); font-size: 1.3rem; margin-top: 10px; font-weight: 400;
        }

        /* --- Stats Cards --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px; margin-bottom: 50px;
        }
        .stat-card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px); border: var(--border-glass);
            border-radius: var(--radius-lg); padding: 30px;
            display: flex; align-items: center; justify-content: space-between;
            transition: 0.3s;
        }
        .stat-card:hover { 
            transform: translateY(-8px); 
            border-color: rgba(255,255,255,0.3); 
            box-shadow: 0 15px 40px rgba(0,0,0,0.6); 
        }
        
        .stat-info .val { font-size: 3rem; font-weight: 800; color: #fff; line-height: 1; }
        .stat-info .lbl { font-size: 1.2rem; color: var(--text-muted); margin-top: 8px; font-weight: 500; }
        .stat-icon { font-size: 3.5rem; opacity: 0.9; }
        
        .c-users { color: var(--neon-blue); filter: drop-shadow(0 0 10px rgba(0,243,255,0.3)); }
        .c-active { color: var(--neon-green); filter: drop-shadow(0 0 10px rgba(0,255,136,0.3)); }
        .c-block { color: var(--neon-red); filter: drop-shadow(0 0 10px rgba(255,0,76,0.3)); }

        /* --- Control Panel (Filters & Actions) --- */
        .control-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(30px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px; margin-bottom: 40px;
            box-shadow: var(--shadow-float);
        }

        /* Filters Styling (IMPROVED READABILITY) */
        .filters-row {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
            padding-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 30px;
        }
        
        .form-group { flex: 1; min-width: 250px; }
        .form-group label {
            display: block; color: #fff; font-size: 1rem; margin-bottom: 10px; font-weight: 700;
        }
        
        .input-readable {
            width: 100%; height: 60px; /* باکس بزرگتر */
            background: #000000; /* پس‌زمینه کاملا مشکی */
            border: 2px solid #444;
            color: #ffffff; /* متن سفید خالص */
            padding: 0 20px; border-radius: 14px;
            font-family: inherit; font-size: 1.2rem; font-weight: 500;
            transition: 0.3s;
        }
        .input-readable:focus {
            border-color: var(--neon-blue);
            box-shadow: 0 0 20px rgba(0, 243, 255, 0.25);
        }
        .input-readable::placeholder { color: #888; font-size: 1.1rem; }
        select.input-readable { cursor: pointer; }
        
        .btn-filter {
            height: 60px; padding: 0 45px; margin-top: 32px;
            background: var(--neon-blue); color: #000;
            border: none; border-radius: 14px;
            font-size: 1.3rem; font-weight: 800; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 30px var(--neon-blue); transform: translateY(-3px); }

        /* Action Buttons */
        .actions-row {
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center;
        }
        .btn-act {
            height: 55px; padding: 0 30px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 14px; color: #fff;
            font-size: 1.1rem; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 10px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.15); border-color: #fff; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255, 7, 58, 0.4); }
        .btn-red:hover { background: rgba(255, 7, 58, 0.15); box-shadow: 0 0 20px var(--neon-red); color: #fff; border-color: var(--neon-red); }
        
        .btn-green { color: var(--neon-green); border-color: rgba(10, 255, 104, 0.4); }
        .btn-green:hover { background: rgba(10, 255, 104, 0.15); box-shadow: 0 0 20px var(--neon-green); color: #fff; border-color: var(--neon-green); }

        /* --- Table Styling (Big & Clear) --- */
        .table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
        .glass-table {
            width: 100%; border-collapse: separate; border-spacing: 0 15px;
            font-size: 1.3rem; /* فونت بسیار درشت */
        }
        .glass-table th {
            text-align: right; padding: 20px 25px;
            color: var(--text-muted); font-size: 1.1rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .glass-table tbody tr {
            background: rgba(255,255,255,0.03);
            transition: 0.3s;
        }
        .glass-table tbody tr:hover {
            background: rgba(255,255,255,0.08);
            transform: scale(1.005);
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .glass-table td {
            padding: 30px 25px; color: #fff; vertical-align: middle;
            border-top: 1px solid rgba(255,255,255,0.04);
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .glass-table td:first-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(255,255,255,0.04); }
        .glass-table td:last-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(255,255,255,0.04); }

        /* Badges */
        .badge {
            padding: 10px 20px; border-radius: 50px; font-size: 1.1rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .badge.active { background: rgba(10, 255, 104, 0.15); color: var(--neon-green); border: 1px solid rgba(10, 255, 104, 0.3); }
        .badge.block { background: rgba(255, 7, 58, 0.15); color: var(--neon-red); border: 1px solid rgba(255, 7, 58, 0.3); }
        .badge.other { background: rgba(255, 255, 255, 0.1); color: #ccc; border: 1px solid rgba(255, 255, 255, 0.2); }

        /* Checkbox */
        .custom-check {
            width: 28px; height: 28px; border: 2px solid #666; background: transparent; cursor: pointer;
            appearance: none; border-radius: 8px; position: relative; transition: 0.2s;
        }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after {
            content: '✔'; position: absolute; color: #000; top: 0px; left: 5px; font-size: 18px; font-weight: 900;
        }

        /* --- Floating Dock (FIXED CENTER) --- */
        .dock-container {
            position: fixed;
            bottom: 30px;
            left: 0; 
            right: 0;
            display: flex;
            justify-content: center;
            z-index: 2000;
            pointer-events: none; /* اجازه کلیک در اطراف داک */
        }
        .dock {
            pointer-events: auto; /* فعال کردن کلیک روی خود داک */
            display: flex; align-items: center; gap: 20px;
            background: var(--bg-dock);
            backdrop-filter: blur(35px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 40px; padding: 15px 35px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.9);
        }
        .dock-item {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            color: var(--text-muted); text-decoration: none;
            transition: 0.3s; position: relative; padding: 0 5px;
        }
        .dock-icon {
            font-size: 2rem; transition: 0.3s;
            width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;
            border-radius: 20px;
        }
        .dock-label { 
            font-size: 1rem; font-weight: 600; opacity: 0; position: absolute; 
            bottom: -35px; transition: 0.3s; white-space: nowrap; 
            background: #000; padding: 4px 10px; border-radius: 6px; border: 1px solid #333;
        }
        
        .dock-item:hover .dock-icon {
            transform: translateY(-20px) scale(1.2);
            background: rgba(255,255,255,0.15);
            color: #fff; box-shadow: 0 15px 30px rgba(0,0,0,0.6);
        }
        .dock-item:hover .dock-label { opacity: 1; bottom: -45px; color: #fff; }
        
        .dock-item.active .dock-icon {
            color: var(--neon-blue); background: rgba(0, 243, 255, 0.15);
            box-shadow: 0 0 25px rgba(0, 243, 255, 0.4); transform: translateY(-10px);
        }
        .dock-item.active .dock-label { opacity: 1; bottom: -45px; color: var(--neon-blue); }

        /* Responsive */
        @media (max-width: 1200px) {
            .filters-row { flex-direction: column; align-items: stretch; }
            .btn-filter { margin-top: 10px; }
        }
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 160px 15px; }
            .glass-table { font-size: 1.1rem; }
            .dock { padding: 12px 20px; gap: 10px; width: 95%; justify-content: space-between; border-radius: 25px; }
            .dock-icon { font-size: 1.6rem; width: 45px; height: 45px; }
            .stat-val { font-size: 2.8rem; }
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>مدیریت کاربران</h1>
                <p>
                    <i class="fa-solid fa-users-gear" style="color: var(--neon-purple);"></i>
                    لیست کامل مشترکین و ابزارهای مدیریتی
                    <span style="opacity:0.3; margin:0 15px;">|</span>
                    <?php echo $todayDate; ?>
                </p>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid anim d-1">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($totalUsers); ?></div>
                    <div class="lbl">کل کاربران</div>
                </div>
                <i class="fa-solid fa-users stat-icon c-users"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($activeUsers); ?></div>
                    <div class="lbl">کاربران فعال</div>
                </div>
                <i class="fa-solid fa-user-check stat-icon c-active"></i>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="val"><?php echo number_format($blockedUsers); ?></div>
                    <div class="lbl">کاربران مسدود</div>
                </div>
                <i class="fa-solid fa-user-lock stat-icon c-block"></i>
            </div>
        </div>

        <!-- Main Glass Panel -->
        <div class="glass-panel anim d-2">
            
            <!-- Filter Section (High Readability) -->
            <form method="get" class="filters-row">
                <div class="form-group">
                    <label>جستجو در کاربران</label>
                    <input type="text" name="q" class="input-readable" placeholder="نام، آیدی عددی یا شماره تلفن..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
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
                
                <button type="submit" class="btn-filter">
                    <i class="fa-solid fa-search"></i> جستجو
                </button>
                
                <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['agent'])): ?>
                    <a href="users.php" class="btn-act" style="margin-top: 32px; height: 60px;" title="پاک کردن فیلترها">
                        <i class="fa-solid fa-rotate-right" style="font-size: 1.4rem;"></i>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Actions Toolbar -->
            <div class="actions-row">
                <span id="usersSelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1.3rem; margin-left: 20px;">0 انتخاب</span>
                
                <button class="btn-act" id="usersSelectVisible"><i class="fa-solid fa-check-double"></i> انتخاب همه</button>
                <button class="btn-act" id="usersClearSelection"><i class="fa-solid fa-minus"></i> لغو</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act" id="usersCopy"><i class="fa-solid fa-copy"></i> کپی ID</button>
                <button class="btn-act btn-green" id="usersUnblockSel"><i class="fa-solid fa-unlock"></i> فعال‌سازی</button>
                <button class="btn-act btn-red" id="usersBlockSel"><i class="fa-solid fa-ban"></i> مسدودسازی</button>
                
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i> خروجی اکسل</a>
            </div>
            
            <div class="actions-row" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px;">
                <input type="number" id="usersAmount" class="input-readable" placeholder="مبلغ (تومان)" style="max-width: 220px; height: 55px; font-size: 1.1rem;">
                <button class="btn-act" id="usersAddBalance"><i class="fa-solid fa-plus"></i> شارژ</button>
                <button class="btn-act" id="usersLowBalance"><i class="fa-solid fa-minus"></i> کسر</button>
                
                <input type="text" id="usersMessage" class="input-readable" placeholder="متن پیام گروهی..." style="max-width: 350px; height: 55px; font-size: 1.1rem;">
                <button class="btn-act" id="usersSendMsg"><i class="fa-solid fa-paper-plane"></i> ارسال پیام</button>
            </div>

            <!-- Table -->
            <div style="margin-top: 40px;">
                <?php if(!$totalUsers): ?>
                    <div style="text-align: center; padding: 80px; color: var(--text-dim);">
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 6rem; margin-bottom: 25px; opacity: 0.4;"></i>
                        <h3 style="font-size: 2rem;">کاربری با این مشخصات یافت نشد</h3>
                        <p style="font-size: 1.2rem;">لطفاً فیلترها را تغییر دهید.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="glass-table" id="sample_1">
                            <thead>
                                <tr>
                                    <th style="width: 60px;"><i class="fa-solid fa-check"></i></th>
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
                                    $phone = ($list['number'] == "none") ? '<span style="opacity:0.3; font-size: 0.9rem;">---</span>' : $list['number'];
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="checkboxes custom-check" value="1"></td>
                                    <td style="font-family: monospace; color: var(--neon-blue); letter-spacing: 1.5px; font-weight: 700;"><?php echo $list['id']; ?></td>
                                    <td style="font-weight: 800; color: #fff; font-size: 1.25rem;"><?php echo htmlspecialchars($list['username']); ?></td>
                                    <td style="font-size: 1.2rem;"><?php echo $phone; ?></td>
                                    <td style="color: var(--neon-amber); font-weight: 800; font-size: 1.25rem;"><?php echo number_format($list['Balance']); ?></td>
                                    <td style="font-weight: bold; font-size: 1.2rem;"><?php echo number_format($list['affiliatescount']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                    <td>
                                        <a href="user.php?id=<?php echo $list['id']; ?>" class="btn-act" style="height: 45px; font-size: 1rem; background: rgba(255,255,255,0.08);">
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

    <!-- Floating Dock (Fixed Center) -->
    <div class="dock-container anim d-3">
        <div class="dock">
            <a href="index.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-house-chimney"></i></div>
                <span class="dock-label">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-file-invoice"></i></div>
                <span class="dock-label">سفارشات</span>
            </a>
            <a href="user.php" class="dock-item active">
                <div class="dock-icon"><i class="fa-solid fa-users"></i></div>
                <span class="dock-label">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-box-open"></i></div>
                <span class="dock-label">محصولات</span>
            </a>
            <a href="server_status.php" class="dock-item">
                <div class="dock-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <span class="dock-label">ادمین</span>
            </a>
            <div style="width: 2px; height: 35px; background: rgba(255,255,255,0.15);"></div>
            <a href="logout.php" class="dock-item" style="color: var(--neon-red);">
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