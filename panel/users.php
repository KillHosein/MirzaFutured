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
            /* Theme Core */
            --bg-body: #050509;
            --bg-card: rgba(23, 23, 30, 0.75);
            --bg-card-hover: rgba(35, 35, 45, 0.9);
            
            /* Neons */
            --neon-blue: #00f2ff;
            --neon-purple: #c026d3;
            --neon-green: #00ffa3;
            --neon-red: #ff2a6d;
            --neon-gold: #fbbf24;
            
            /* Text */
            --text-pri: #ffffff;
            --text-sec: #94a3b8;
            
            /* Borders */
            --border-subtle: 1px solid rgba(255, 255, 255, 0.08);
            --border-highlight: 1px solid rgba(255, 255, 255, 0.2);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.5);
            
            --radius-main: 24px;
        }

        /* --- Base --- */
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
            padding-bottom: 120px; /* Space for dock */
            overflow-x: hidden;
            display: flex; flex-direction: column;
        }

        /* --- Full Height Container --- */
        .dashboard-container {
            width: 100%; max-width: 1920px; margin: 0 auto;
            padding: 25px 3%; 
            flex-grow: 1; /* Fill vertical space */
            display: flex; flex-direction: column; gap: 20px;
        }

        /* --- 1. Header Compact --- */
        .header-section {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .header-titles { display: flex; flex-direction: column; gap: 2px; }
        .ht-main {
            font-size: 2.2rem; font-weight: 900; margin: 0; line-height: 1.1;
            background: linear-gradient(to right, #ffffff 40%, #94a3b8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .ht-sub { color: var(--text-sec); font-size: 0.95rem; font-weight: 400; display: flex; align-items: center; gap: 8px; }
        
        .info-pill {
            background: rgba(255,255,255,0.03); border: var(--border-subtle);
            padding: 8px 16px; border-radius: 14px;
            display: flex; align-items: center; gap: 8px; font-size: 0.9rem;
            backdrop-filter: blur(10px); color: var(--text-sec);
        }

        /* --- 2. Stats Row (Compact) --- */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;
        }
        .holo-card {
            background: var(--bg-card); border: var(--border-subtle); border-radius: 20px;
            padding: 20px 25px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .holo-card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,0.15); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .hc-val { font-size: 1.8rem; font-weight: 800; color: #fff; margin-bottom: 2px; }
        .hc-lbl { color: var(--text-sec); font-size: 0.85rem; font-weight: 500; }
        .hc-icon-box {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; background: rgba(255,255,255,0.03);
        }

        .hc-blue .hc-icon-box { color: var(--neon-blue); border: 1px solid rgba(0, 242, 255, 0.2); }
        .hc-green .hc-icon-box { color: var(--neon-green); border: 1px solid rgba(0, 255, 163, 0.2); }
        .hc-red .hc-icon-box { color: var(--neon-red); border: 1px solid rgba(255, 42, 109, 0.2); }

        /* --- 3. Main Glass Panel (Fills Remaining Space) --- */
        .glass-panel {
            background: var(--bg-card); border: var(--border-subtle); border-radius: var(--radius-main);
            padding: 25px; 
            flex-grow: 1; /* Key for filling space */
            display: flex; flex-direction: column;
            backdrop-filter: blur(20px); box-shadow: var(--shadow-card);
            min-height: 400px;
        }

        .filters-row {
            display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
            padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px;
        }
        .form-group { flex: 1; min-width: 180px; }
        .form-group label { display: block; color: var(--text-sec); font-size: 0.85rem; margin-bottom: 6px; font-weight: 600; }
        
        .input-readable {
            width: 100%; height: 45px;
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 0 15px; border-radius: 12px;
            font-family: inherit; font-size: 0.95rem; transition: 0.3s;
        }
        .input-readable:focus { background: rgba(0,0,0,0.5); border-color: var(--neon-blue); }
        select.input-readable option { background: #111; color: #fff; }

        .btn-filter {
            height: 45px; padding: 0 25px;
            background: var(--neon-blue); color: #000;
            border: none; border-radius: 12px;
            font-size: 0.95rem; font-weight: 700; cursor: pointer;
            transition: 0.3s; display: flex; align-items: center; gap: 8px;
        }
        .btn-filter:hover { box-shadow: 0 0 15px var(--neon-blue); transform: translateY(-2px); }

        /* Actions Toolbar */
        .actions-row {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px;
        }
        .btn-act {
            height: 40px; padding: 0 18px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; color: var(--text-sec);
            font-size: 0.9rem; font-weight: 500; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-act:hover { background: rgba(255,255,255,0.1); border-color: #fff; color: #fff; transform: translateY(-2px); }
        
        .btn-red { color: var(--neon-red); border-color: rgba(255, 42, 109, 0.3); }
        .btn-red:hover { background: rgba(255, 42, 109, 0.1); border-color: var(--neon-red); box-shadow: 0 0 10px rgba(255, 42, 109, 0.3); }
        .btn-green { color: var(--neon-green); border-color: rgba(0, 255, 163, 0.3); }
        .btn-green:hover { background: rgba(0, 255, 163, 0.1); border-color: var(--neon-green); box-shadow: 0 0 10px rgba(0, 255, 163, 0.3); }

        /* Table - Fills remaining space */
        .table-container-flex {
            flex-grow: 1; /* Fills vertical space in glass panel */
            overflow-y: auto; overflow-x: auto;
            border-radius: 16px;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.03);
        }
        .glass-table {
            width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.95rem;
        }
        .glass-table th {
            text-align: right; padding: 15px 20px;
            color: var(--text-sec); font-size: 0.8rem; font-weight: 600;
            background: rgba(255,255,255,0.03);
            position: sticky; top: 0; z-index: 10; backdrop-filter: blur(10px);
        }
        .glass-table tbody tr { transition: 0.15s; }
        .glass-table tbody tr:hover { background: rgba(255,255,255,0.04); }
        .glass-table td {
            padding: 15px 20px; color: #fff; vertical-align: middle;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        
        /* Badges & Checkbox */
        .badge {
            padding: 5px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 600;
            display: inline-flex; align-items: center;
        }
        .badge.active { background: rgba(0, 255, 163, 0.1); color: var(--neon-green); border: 1px solid rgba(0, 255, 163, 0.2); }
        .badge.block { background: rgba(255, 42, 109, 0.1); color: var(--neon-red); border: 1px solid rgba(255, 42, 109, 0.2); }
        .badge.other { background: rgba(255, 255, 255, 0.05); color: #ccc; border: 1px solid rgba(255, 255, 255, 0.1); }

        .custom-check {
            width: 18px; height: 18px; border: 2px solid #555; background: transparent; cursor: pointer;
            appearance: none; border-radius: 5px; position: relative; transition: 0.2s;
        }
        .custom-check:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        .custom-check:checked::after {
            content: '✔'; position: absolute; color: #000; top: -2px; left: 2px; font-size: 12px; font-weight: 800;
        }

        /* --- Super Dock --- */
        .dock-container {
            position: fixed; bottom: 20px; left: 0; right: 0;
            display: flex; justify-content: center; pointer-events: none; z-index: 9999;
        }
        .super-dock {
            pointer-events: auto;
            background: rgba(15, 15, 20, 0.9);
            backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 20px 50px rgba(0,0,0,0.7);
            padding: 10px; border-radius: 24px;
            display: flex; align-items: center; gap: 8px;
        }
        .dock-item {
            width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 16px;
            color: var(--text-sec); font-size: 1.3rem;
            text-decoration: none; position: relative;
            background: transparent;
            transition: all 0.25s cubic-bezier(0.3, 0.7, 0.4, 1.5);
        }
        .dock-item:hover {
            width: 58px; height: 58px; margin: 0 4px;
            background: rgba(255,255,255,0.1); color: #fff;
            transform: translateY(-10px);
        }
        .dock-item.active {
            color: var(--neon-blue); background: rgba(0, 242, 255, 0.1);
        }
        .dock-item::before {
            content: attr(data-tooltip);
            position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(10px) scale(0.8);
            background: rgba(0,0,0,0.9); border: 1px solid rgba(255,255,255,0.2);
            color: #fff; padding: 5px 10px; border-radius: 8px;
            font-size: 0.75rem; font-weight: 600; white-space: nowrap;
            opacity: 0; visibility: hidden; transition: 0.2s; pointer-events: none; margin-bottom: 12px;
        }
        .dock-item:hover::before { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0) scale(1); }
        .dock-divider { width: 1px; height: 30px; background: rgba(255,255,255,0.1); margin: 0 4px; }

        @media (max-width: 768px) {
            .dashboard-container { padding: 15px; gap: 15px; }
            .header-section { flex-direction: column; align-items: flex-start; gap: 15px; }
            .super-dock { max-width: 95vw; overflow-x: auto; justify-content: flex-start; }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- 1. Header -->
        <header class="header-section">
            <div class="header-titles">
                <h1 class="ht-main">مدیریت کاربران</h1>
                <div class="ht-sub">
                    <i class="fa-solid fa-users-gear" style="color: var(--neon-purple);"></i>
                    لیست مشترکین
                </div>
            </div>
            <div class="info-pill">
                <i class="fa-regular fa-calendar" style="color: var(--text-sec);"></i>
                <span><?php echo $todayDate; ?></span>
            </div>
        </header>

        <!-- 2. Stats (Filled Width) -->
        <section class="stats-row">
            <div class="holo-card hc-blue">
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($totalUsers); ?></div>
                    <div class="hc-lbl">کل کاربران</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="holo-card hc-green">
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($activeUsers); ?></div>
                    <div class="hc-lbl">کاربران فعال</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-user-check"></i></div>
            </div>
            <div class="holo-card hc-red">
                <div class="hc-info">
                    <div class="hc-val"><?php echo number_format($blockedUsers); ?></div>
                    <div class="hc-lbl">کاربران مسدود</div>
                </div>
                <div class="hc-icon-box"><i class="fa-solid fa-user-lock"></i></div>
            </div>
        </section>

        <!-- 3. Main Panel (Fills Space) -->
        <div class="glass-panel">
            
            <!-- Filters -->
            <form method="get" class="filters-row">
                <div class="form-group">
                    <label>جستجو</label>
                    <input type="text" name="q" class="input-readable" placeholder="نام، آیدی، شماره..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status" class="input-readable">
                        <option value="">همه</option>
                        <option value="active" <?php echo (isset($_GET['status']) && $_GET['status']==='active')?'selected':''; ?>>فعال</option>
                        <option value="block" <?php echo (isset($_GET['status']) && $_GET['status']==='block')?'selected':''; ?>>مسدود</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>سطح</label>
                    <select name="agent" class="input-readable">
                        <option value="">همه</option>
                        <option value="f" <?php echo (isset($_GET['agent']) && $_GET['agent']==='f')?'selected':''; ?>>کاربر</option>
                        <option value="n" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n')?'selected':''; ?>>نماینده</option>
                        <option value="n2" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n2')?'selected':''; ?>>نماینده ارشد</option>
                    </select>
                </div>
                <div style="padding-bottom: 0;">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-search"></i></button>
                </div>
                <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['agent'])): ?>
                    <div style="padding-bottom: 0;">
                        <a href="users.php" class="btn-act" style="height: 45px; justify-content: center;"><i class="fa-solid fa-rotate-right"></i></a>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Actions -->
            <div class="actions-row">
                <span id="usersSelCount" style="color: var(--neon-blue); font-weight: 800; font-size: 1rem; margin-left: 10px;">0</span>
                
                <button class="btn-act" id="usersSelectVisible"><i class="fa-solid fa-check-double"></i> همه</button>
                <button class="btn-act" id="usersClearSelection"><i class="fa-solid fa-minus"></i> لغو</button>
                <div style="flex:1"></div>
                <button class="btn-act" id="usersCopy" title="کپی آیدی"><i class="fa-solid fa-copy"></i></button>
                <button class="btn-act btn-green" id="usersUnblockSel" title="فعال سازی"><i class="fa-solid fa-unlock"></i></button>
                <button class="btn-act btn-red" id="usersBlockSel" title="مسدود سازی"><i class="fa-solid fa-ban"></i></button>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-act"><i class="fa-solid fa-file-csv"></i></a>
            </div>

            <div class="actions-row" style="background: rgba(255,255,255,0.02); padding: 10px; border-radius: 12px;">
                <input type="number" id="usersAmount" class="input-readable" placeholder="مبلغ (تومان)" style="max-width: 150px; height: 40px; font-size: 0.9rem;">
                <button class="btn-act" id="usersAddBalance"><i class="fa-solid fa-plus"></i></button>
                <button class="btn-act" id="usersLowBalance"><i class="fa-solid fa-minus"></i></button>
                <div style="width: 1px; height: 25px; background: rgba(255,255,255,0.1); margin: 0 8px;"></div>
                <input type="text" id="usersMessage" class="input-readable" placeholder="متن پیام..." style="flex-grow: 1; height: 40px; font-size: 0.9rem;">
                <button class="btn-act" id="usersSendMsg"><i class="fa-solid fa-paper-plane"></i></button>
            </div>

            <!-- Table (Scrollable & Fills Space) -->
            <?php if(!$totalUsers): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-sec); flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                    <i class="fa-solid fa-magnifying-glass" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <h3>کاربری یافت نشد</h3>
                </div>
            <?php else: ?>
                <div class="table-container-flex">
                    <table class="glass-table" id="sample_1">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>آیدی</th>
                                <th>نام کاربری</th>
                                <th>شماره</th>
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
                                <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($list['username']); ?></td>
                                <td><?php echo $phone; ?></td>
                                <td style="color: var(--neon-gold); font-weight: 700;"><?php echo number_format($list['Balance']); ?></td>
                                <td><?php echo number_format($list['affiliatescount']); ?></td>
                                <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                <td>
                                    <a href="user.php?id=<?php echo $list['id']; ?>" class="btn-act" style="height: 32px; font-size: 0.8rem; padding: 0 12px;">
                                        <i class="fa-solid fa-gear"></i>
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

    <!-- Dock -->
    <div class="dock-container">
        <nav class="super-dock">
            <a href="index.php" class="dock-item" data-tooltip="داشبورد"><i class="fa-solid fa-house"></i></a>
            <div class="dock-divider"></div>
            <a href="users.php" class="dock-item active" data-tooltip="کاربران"><i class="fa-solid fa-users"></i></a>
            <a href="invoice.php" class="dock-item" data-tooltip="سفارشات"><i class="fa-solid fa-file-contract"></i></a>
            <a href="product.php" class="dock-item" data-tooltip="محصولات"><i class="fa-solid fa-box"></i></a>
            <a href="service.php" class="dock-item" data-tooltip="سرویس‌ها"><i class="fa-solid fa-server"></i></a>
            <div class="dock-divider"></div>
            <a href="cancelService.php" class="dock-item" data-tooltip="مسدودها" style="color: var(--neon-red);"><i class="fa-solid fa-ban"></i></a>
            <a href="payment.php" class="dock-item" data-tooltip="مالی"><i class="fa-solid fa-wallet"></i></a>
            <a href="inbound.php" class="dock-item" data-tooltip="کانفیگ"><i class="fa-solid fa-network-wired"></i></a>
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
        function showToast(msg) { alert(msg); }

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

        function bulkAction(type, val) {
            var ids = [];
            $('#sample_1 tbody tr').each(function(){
                var $r=$(this); 
                if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); 
            });
            
            if(!ids.length){ showToast('کاربری انتخاب نشده است'); return; }
            if(!confirm('تغییرات روی ' + ids.length + ' کاربر اعمال شود؟')) return;

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