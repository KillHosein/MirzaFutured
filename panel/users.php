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
            --bg-body: #050505;
            --bg-glass: rgba(20, 20, 25, 0.7);
            --bg-dock: rgba(10, 10, 15, 0.85);
            
            --neon-blue: #00f3ff;
            --neon-purple: #bc13fe;
            --neon-green: #0aff68;
            --neon-red: #ff073a;
            --neon-amber: #ffaa00;
            
            --text-main: #ffffff;
            --text-muted: #a0a0a0;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.1);
            --shadow-float: 0 10px 40px rgba(0,0,0,0.8);
            
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
            /* پس‌زمینه کهکشانی */
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(0, 243, 255, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(188, 19, 254, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 140px; /* فضای خالی برای داک */
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
            width: 100%; padding: 40px 5%; max-width: 1800px; margin: 0 auto;
        }

        /* --- Header --- */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px;
        }
        .page-title h1 {
            font-size: 3rem; font-weight: 900; margin: 0; color: #fff;
            text-shadow: 0 0 20px rgba(255,255,255,0.1);
        }
        .page-title p {
            color: var(--text-muted); font-size: 1.2rem; margin-top: 10px;
        }

        /* --- Stats Cards --- */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px; margin-bottom: 50px;
        }
        .stat-card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px); border: var(--border-glass);
            border-radius: var(--radius-lg); padding: 25px;
            display: flex; align-items: center; justify-content: space-between;
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.3); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        
        .stat-info .val { font-size: 2.5rem; font-weight: 800; color: #fff; line-height: 1.1; }
        .stat-info .lbl { font-size: 1.1rem; color: var(--text-muted); margin-top: 5px; }
        .stat-icon { font-size: 3rem; opacity: 0.8; }
        
        .c-users { color: var(--neon-blue); }
        .c-active { color: var(--neon-green); }
        .c-block { color: var(--neon-red); }

        /* --- Control Panel (Filters & Actions) --- */
        .control-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(30px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 30px; margin-bottom: 40px;
            box-shadow: var(--shadow-float);
        }

        /* Filters Styling (Improved Readability) */
        .filters-row {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
            padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 25px;
        }
        
        .form-group { flex: 1; min-width: 250px; }
        .form-group label {
            display: block; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 8px; font-weight: 700;
        }
        
        .input-high-contrast {
            width: 100%; height: 55px;
            background: #1a1a20; /* پس‌زمینه تیره‌تر برای کنتراست */
            border: 2px solid #333;
            color: #fff; /* متن کاملاً سفید */
            padding: 0 20px; border-radius: 12px;
            font-family: inherit; font-size: 1.1rem; font-weight: 500;
            transition: 0.3s;
        }
        .input-high-contrast:focus {
            border-color: var(--neon-blue);
            background: #000;
            box-shadow: 0 0 15px rgba(0, 243, 255, 0.2);
        }
        .input-high-contrast::placeholder { color: #666; }
        
        .btn-filter {
            height: 55px; padding: 0 40px; margin-top: 28px; /* Align with inputs */
            background: var(--neon-blue); color: #000;
            border: none; border-radius: 12px;
            font-size: 1.2rem; font-weight: 800; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-filter:hover { box-shadow: 0 0 25px var(--neon-blue); transform: translateY(-2px); }

        /* Action Buttons */
        .actions-row {
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center;
        }
        .btn-act {
            height: 50px; padding: 0 25px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; color: #fff;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.1); border-color: #fff; transform: translateY(-2px); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255, 7, 58, 0.3); }
        .btn-red:hover { background: rgba(255, 7, 58, 0.1); box-shadow: 0 0 15px var(--neon-red); }
        
        .btn-green { color: var(--neon-green); border-color: rgba(10, 255, 104, 0.3); }
        .btn-green:hover { background: rgba(10, 255, 104, 0.1); box-shadow: 0 0 15px var(--neon-green); }

        /* --- Table Styling (Big & Readable) --- */
        .table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
        .glass-table {
            width: 100%; border-collapse: separate; border-spacing: 0 12px;
            font-size: 1.2rem; /* فونت درشت */
        }
        .glass-table th {
            text-align: right; padding: 20px;
            color: var(--text-muted); font-size: 1rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .glass-table tbody tr {
            background: rgba(255,255,255,0.02);
            transition: 0.3s;
        }
        .glass-table tbody tr:hover {
            background: rgba(255,255,255,0.07);
            transform: scale(1.01);
            box-shadow: 0 5px 20px rgba(0,0,0,0.5);
        }
        .glass-table td {
            padding: 25px 20px; color: #fff; vertical-align: middle;
            border-top: 1px solid rgba(255,255,255,0.03);
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .glass-table td:first-child { border-radius: 0 16px 16px 0; border-right: 1px solid rgba(255,255,255,0.03); }
        .glass-table td:last-child { border-radius: 16px 0 0 16px; border-left: 1px solid rgba(255,255,255,0.03); }

        /* Badges */
        .badge {
            padding: 8px 16px; border-radius: 50px; font-size: 1rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .badge.active { background: rgba(10, 255, 104, 0.15); color: var(--neon-green); border: 1px solid rgba(10, 255, 104, 0.3); }
        .badge.block { background: rgba(255, 7, 58, 0.15); color: var(--neon-red); border: 1px solid rgba(255, 7, 58, 0.3); }
        .badge.other { background: rgba(255, 255, 255, 0.1); color: #ccc; border: 1px solid rgba(255, 255, 255, 0.2); }

        /* Checkbox */
        .custom-check {
            width: 24px; height: 24px; border: 2px solid #666; background: transparent; cursor: pointer;
            appearance: none; border-radius: 6px; position: relative;
        }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after {
            content: '✔'; position: absolute; color: #000; top: -2px; left: 4px; font-size: 16px; font-weight: 900;
        }

        /* --- Floating Dock (Bottom Center) --- */
        .dock-container {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            z-index: 2000; width: auto;
        }
        .dock {
            display: flex; align-items: center; gap: 15px;
            background: var(--bg-dock);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 35px; padding: 15px 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.8);
        }
        .dock-item {
            display: flex; flex-direction: column; align-items: center; gap: 5px;
            color: var(--text-muted); text-decoration: none;
            transition: 0.3s; position: relative; padding: 0 10px;
        }
        .dock-icon {
            font-size: 1.8rem; transition: 0.3s;
            width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;
            border-radius: 18px;
        }
        .dock-label { font-size: 0.9rem; font-weight: 500; opacity: 0; position: absolute; bottom: -25px; transition: 0.3s; white-space: nowrap; }
        
        .dock-item:hover .dock-icon {
            transform: translateY(-15px) scale(1.2);
            background: rgba(255,255,255,0.1);
            color: #fff; box-shadow: 0 10px 20px rgba(0,0,0,0.5);
        }
        .dock-item:hover .dock-label { opacity: 1; bottom: -35px; color: #fff; }
        
        .dock-item.active .dock-icon {
            color: var(--neon-blue); background: rgba(0, 243, 255, 0.15);
            box-shadow: 0 0 20px rgba(0, 243, 255, 0.3); transform: translateY(-10px);
        }
        .dock-item.active .dock-label { opacity: 1; bottom: -35px; color: var(--neon-blue); }

        /* Responsive */
        @media (max-width: 1200px) {
            .filters-row { flex-direction: column; align-items: stretch; }
            .btn-filter { margin-top: 10px; }
        }
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px 140px 15px; }
            .glass-table { font-size: 1rem; }
            .dock { padding: 10px 15px; gap: 5px; width: 95vw; justify-content: space-around; }
            .dock-icon { font-size: 1.5rem; width: 40px; height: 40px; }
            .stat-val { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="page-header anim">
            <div class="page-title">
                <h1>کاربران</h1>
                <p>
                    <i class="fa-solid fa-users-gear" style="color: var(--neon-purple);"></i>
                    مدیریت مشترکین و نظارت بر حساب‌ها
                    <span style="opacity:0.3; margin:0 10px;">|</span>
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
                    <label>جستجو</label>
                    <input type="text" name="q" class="input-high-contrast" placeholder="نام، آیدی یا شماره تلفن..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>وضعیت حساب</label>
                    <select name="status" class="input-high-contrast">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="active" <?php echo (isset($_GET['status']) && $_GET['status']==='active')?'selected':''; ?>>فعال</option>
                        <option value="block" <?php echo (isset($_GET['status']) && $_GET['status']==='block')?'selected':''; ?>>مسدود</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>نوع کاربر</label>
                    <select name="agent" class="input-high-contrast">
                        <option value="">همه کاربران</option>
                        <option value="f" <?php echo (isset($_GET['agent']) && $_GET['agent']==='f')?'selected':''; ?>>عادی</option>
                        <option value="n" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n')?'selected':''; ?>>نماینده</option>
                        <option value="n2" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n2')?'selected':''; ?>>نماینده ارشد</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-filter">
                    <i class="fa-solid fa-search"></i> جستجو
                </button>
                
                <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['agent'])): ?>
                    <a href="users.php" class="btn-act" style="margin-top: 28px;">
                        <i class="fa-solid fa-rotate-right"></i>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Actions Toolbar -->
            <div class="actions-row">
                <span id="usersSelCount" style="color: var(--neon-blue); font-weight: bold; font-size: 1.1rem; margin-left: 15px;">0 انتخاب</span>
                
                <button class="btn-act" id="usersSelectVisible"><i class="fa-solid fa-check-double"></i> همه</button>
                <button class="btn-act" id="usersClearSelection"><i class="fa-solid fa-minus"></i> هیچ</button>
                
                <div style="flex:1"></div>
                
                <button class="btn-act" id="usersCopy"><i class="fa-solid fa-copy"></i> کپی ID</button>
                <button class="btn-act btn-green" id="usersUnblockSel"><i class="fa-solid fa-unlock"></i> فعال‌سازی</button>
                <button class="btn-act btn-red" id="usersBlockSel"><i class="fa-solid fa-ban"></i> مسدودسازی</button>
                
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i> اکسل</a>
            </div>
            
            <div class="actions-row" style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px;">
                <input type="number" id="usersAmount" class="input-high-contrast" placeholder="مبلغ (تومان)" style="max-width: 180px; height: 45px;">
                <button class="btn-act" id="usersAddBalance"><i class="fa-solid fa-plus"></i> شارژ</button>
                <button class="btn-act" id="usersLowBalance"><i class="fa-solid fa-minus"></i> کسر</button>
                
                <input type="text" id="usersMessage" class="input-high-contrast" placeholder="پیام گروهی..." style="max-width: 300px; height: 45px;">
                <button class="btn-act" id="usersSendMsg"><i class="fa-solid fa-paper-plane"></i> ارسال پیام</button>
            </div>

            <!-- Table -->
            <div style="margin-top: 30px;">
                <?php if(!$totalUsers): ?>
                    <div style="text-align: center; padding: 60px; color: var(--text-dim);">
                        <i class="fa-solid fa-folder-open" style="font-size: 5rem; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3>نتیجه‌ای یافت نشد</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="glass-table" id="sample_1">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"><i class="fa-solid fa-check"></i></th>
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
                                    $phone = ($list['number'] == "none") ? '<span style="opacity:0.3">---</span>' : $list['number'];
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="checkboxes custom-check" value="1"></td>
                                    <td style="font-family: monospace; color: var(--neon-blue); letter-spacing: 1px;"><?php echo $list['id']; ?></td>
                                    <td style="font-weight: 800; color: #fff;"><?php echo htmlspecialchars($list['username']); ?></td>
                                    <td><?php echo $phone; ?></td>
                                    <td style="color: var(--neon-amber); font-weight: 800;"><?php echo number_format($list['Balance']); ?></td>
                                    <td style="font-weight: bold;"><?php echo number_format($list['affiliatescount']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                    <td>
                                        <a href="user.php?id=<?php echo $list['id']; ?>" class="btn-act" style="height: 40px; font-size: 0.9rem; background: rgba(255,255,255,0.08);">
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

    <!-- Floating Dock (Bottom Center) -->
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
            <div style="width: 2px; height: 30px; background: rgba(255,255,255,0.1);"></div>
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
            if(!confirm('آیا مطمئن هستید؟')) return;

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
                    if(done === total){ showToast('انجام شد'); setTimeout(function(){ location.reload(); }, 800); } 
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