<?php
// --- Logic & Config (Preserved) ---
session_start();
// تنظیمات گزارش خطا برای محیط پروداکشن (خاموش کردن نمایش خطا)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

// Authentication Check
if (!isset($_SESSION["user"])) { header('Location: login.php'); exit; }

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!$result) { header('Location: login.php'); exit; }

// --- Query Construction ---
$where = [];
$params = [];

// Filter: Status
if (!empty($_GET['status'])) {
    $where[] = "LOWER(User_Status) = :st";
    $params[':st'] = strtolower($_GET['status']);
}
// Filter: Agent
if (!empty($_GET['agent'])) {
    $where[] = "agent = :ag";
    $params[':ag'] = $_GET['agent'];
}
// Filter: Search
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

// --- Export Logic ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fputs($out, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    fputcsv($out, ['شناسه', 'نام کاربری', 'شماره', 'موجودی', 'زیرمجموعه', 'وضعیت', 'نماینده']);
    foreach ($listusers as $u) {
        $status = strtolower($u['User_Status']);
        fputcsv($out, [
            $u['id'],
            $u['username'],
            $u['number'],
            $u['Balance'],
            $u['affiliatescount'],
            $status,
            $u['agent']
        ]);
    }
    fclose($out);
    exit();
}

// Statistics Calculation
$totalUsers = count($listusers);
$activeUsers = 0;
$blockedUsers = 0;
foreach ($listusers as $u) {
    $s = strtolower($u['User_Status']);
    if ($s === 'active') $activeUsers++;
    else if ($s === 'block') $blockedUsers++;
}

// Date for Header
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
    <!-- DataTables CSS Override -->
    <style>
        :root {
            /* Palette: Deep Midnight & Neon Glow (Consistent with Index) */
            --bg-void: #000000;
            --bg-surface: rgba(15, 15, 20, 0.6);
            --bg-dock: rgba(5, 5, 10, 0.8);
            
            --neon-blue: #22d3ee;
            --neon-purple: #c084fc;
            --neon-teal: #2dd4bf;
            --neon-amber: #fbbf24;
            --neon-pink: #f472b6;
            --neon-green: #10b981;
            --neon-red: #ef4444;
            
            --text-main: #ffffff;
            --text-dim: #94a3b8;
            --text-highlight: #e2e8f0;
            
            --border-glass: 1px solid rgba(255, 255, 255, 0.08);
            --border-glow: 1px solid rgba(255, 255, 255, 0.15);
            
            --shadow-float: 0 20px 60px -10px rgba(0,0,0,0.9);
            
            --radius-lg: 30px;
            --radius-md: 16px;
            --radius-pill: 100px;
        }

        /* --- Global Reset --- */
        * { box-sizing: border-box; outline: none; }
        body {
            background-color: var(--bg-void);
            color: var(--text-main);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0; padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
            /* Starry Deep Background */
            background-image: 
                radial-gradient(circle at 15% 10%, rgba(34, 211, 238, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 85% 90%, rgba(192, 132, 252, 0.06) 0%, transparent 40%);
            background-attachment: fixed;
            padding-bottom: 140px; /* Space for dock */
        }

        a { text-decoration: none; color: inherit; transition: 0.3s; }

        /* --- Animations --- */
        @keyframes floatIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .anim { animation: floatIn 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; opacity: 0; }
        .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; }

        /* --- Layout --- */
        .container-fluid-custom {
            width: 100%; padding: 50px 6%; max-width: 1920px; margin: 0 auto;
        }

        /* --- Header --- */
        .header-top {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 50px; padding-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .header-title h1 {
            font-size: 3.5rem; font-weight: 900; color: #fff; margin: 0; line-height: 1.1;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: -1px;
        }
        .header-title p {
            color: var(--text-dim); margin-top: 10px; font-size: 1.2rem; font-weight: 300;
            display: flex; align-items: center; gap: 15px;
        }
        
        /* --- Stats Deck --- */
        .stats-deck {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px; margin-bottom: 50px;
        }
        .stat-card {
            background: var(--bg-surface);
            backdrop-filter: blur(30px); border: var(--border-glass);
            border-radius: var(--radius-lg); padding: 30px;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 180px; position: relative; overflow: hidden;
            transition: all 0.4s ease;
        }
        .stat-card:hover { transform: translateY(-10px); border-color: rgba(255,255,255,0.2); box-shadow: var(--shadow-float); }
        
        .stat-icon { font-size: 2.5rem; color: var(--text-dim); margin-bottom: 15px; }
        .stat-val { font-size: 3rem; font-weight: 800; color: #fff; line-height: 1; }
        .stat-lbl { font-size: 1.1rem; color: var(--text-dim); font-weight: 400; margin-top: 5px; }
        
        .s-users .stat-icon { color: var(--neon-blue); }
        .s-active .stat-icon { color: var(--neon-green); }
        .s-block .stat-icon { color: var(--neon-red); }

        /* --- Glass Panel (Table & Controls) --- */
        .glass-panel {
            background: var(--bg-surface);
            backdrop-filter: blur(40px);
            border: var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 40px;
            margin-bottom: 60px;
            box-shadow: var(--shadow-float);
        }

        /* --- Filter & Action Toolbar --- */
        .toolbar-section {
            display: flex; flex-direction: column; gap: 30px; margin-bottom: 40px;
        }
        
        .filter-row {
            display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
            background: rgba(0,0,0,0.2); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.03);
        }
        
        .input-glass {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #fff; padding: 12px 20px; border-radius: 12px; height: 55px;
            font-family: inherit; font-size: 1.1rem; flex: 1; min-width: 220px;
            transition: 0.3s;
        }
        .input-glass::placeholder { color: rgba(255,255,255,0.5); }
        .input-glass:focus { border-color: var(--neon-blue); box-shadow: 0 0 15px rgba(34, 211, 238, 0.2); background: rgba(0,0,0,0.4); }
        
        .btn-neon {
            background: transparent; border: 1px solid var(--neon-blue); color: var(--neon-blue);
            padding: 0 35px; height: 55px; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 1.1rem;
            transition: 0.3s; display: flex; align-items: center; gap: 10px;
        }
        .btn-neon:hover { background: var(--neon-blue); color: #000; box-shadow: 0 0 20px var(--neon-blue); }
        
        .btn-glass-action {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: var(--text-highlight); height: 50px; padding: 0 25px; border-radius: 12px;
            display: inline-flex; align-items: center; gap: 10px; cursor: pointer; transition: 0.3s; 
            text-decoration: none; font-size: 1rem; font-weight: 500;
        }
        .btn-glass-action:hover { background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.4); }
        
        .bulk-actions {
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center;
            border-top: 1px solid rgba(255,255,255,0.05); padding-top: 25px;
        }

        /* --- Table Styling --- */
        .table-responsive { overflow-x: auto; }
        .custom-table {
            width: 100%; border-collapse: separate; border-spacing: 0 15px;
            font-size: 1.15rem; /* Large text for readability */
        }
        .custom-table thead th {
            color: var(--text-dim); font-weight: 700; padding: 15px 25px;
            text-align: right; border-bottom: 2px solid rgba(255,255,255,0.05);
            font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;
        }
        .custom-table tbody tr {
            background: rgba(255,255,255,0.03); transition: 0.3s;
        }
        .custom-table tbody tr:hover {
            background: rgba(255,255,255,0.08); transform: scale(1.005);
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        }
        .custom-table td {
            padding: 25px; color: #fff; vertical-align: middle;
            border-top: 1px solid rgba(255,255,255,0.02);
            border-bottom: 1px solid rgba(255,255,255,0.02);
        }
        .custom-table td:first-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(255,255,255,0.02); }
        .custom-table td:last-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(255,255,255,0.02); }

        /* Badges */
        .badge-status {
            padding: 8px 16px; border-radius: 30px; font-size: 1rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .st-active { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); box-shadow: 0 0 10px rgba(16, 185, 129, 0.1); }
        .st-block { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); box-shadow: 0 0 10px rgba(239, 68, 68, 0.1); }
        .st-other { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); }

        /* Checkbox Custom */
        input[type="checkbox"] {
            appearance: none; width: 24px; height: 24px;
            border: 2px solid var(--text-dim); border-radius: 8px; background: transparent; cursor: pointer; position: relative;
        }
        input[type="checkbox"]:checked { background: var(--neon-blue); border-color: var(--neon-blue); }
        input[type="checkbox"]:checked::after {
            content: '✔'; position: absolute; color: #000; font-size: 16px; top: -1px; left: 4px; font-weight: bold;
        }

        /* --- Floating Dock --- */
        .dock-wrapper {
            position: fixed; bottom: 40px; left: 50%; transform: translateX(-50%);
            z-index: 2000; width: auto;
        }
        .dock {
            display: flex; align-items: center; gap: 10px;
            background: var(--bg-dock);
            backdrop-filter: blur(40px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 30px; padding: 15px 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6);
        }
        .dock-item {
            width: 65px; height: 65px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-dim); font-size: 1.8rem; text-decoration: none;
            transition: all 0.3s cubic-bezier(0.3, 0.7, 0.4, 1.5); position: relative;
        }
        .dock-item:hover {
            width: 85px; height: 85px; font-size: 2.4rem; color: #fff;
            background: rgba(255,255,255,0.1); margin: 0 12px; transform: translateY(-25px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
        }
        .dock-item.active { color: var(--neon-blue); background: rgba(34, 211, 238, 0.15); box-shadow: 0 0 20px rgba(34, 211, 238, 0.2); }
        .dock-tooltip {
            position: absolute; top: -50px; left: 50%; transform: translateX(-50%) scale(0.8);
            background: #000; color: #fff; padding: 5px 12px; border-radius: 8px; opacity: 0; pointer-events: none; transition: 0.2s; font-size: 0.9rem; white-space: nowrap; font-weight: bold;
        }
        .dock-item:hover .dock-tooltip { opacity: 1; transform: translateX(-50%) scale(1); top: -65px; }

        /* Responsive */
        @media (max-width: 768px) {
            .container-fluid-custom { padding: 30px 15px; padding-bottom: 120px; }
            .header-top { flex-direction: column; align-items: flex-start; gap: 15px; }
            .header-title h1 { font-size: 2.5rem; }
            .filter-row, .bulk-actions { flex-direction: column; align-items: stretch; }
            .input-glass, .btn-neon, .btn-glass-action { width: 100%; }
            .dock-wrapper { width: 95%; bottom: 20px; }
            .dock { justify-content: space-between; padding: 10px; }
            .dock-item:hover { transform: translateY(-10px); width: 55px; height: 55px; font-size: 1.5rem; margin: 0; }
        }
    </style>
</head>
<body>

    <div class="container-fluid-custom">
        
        <!-- Header -->
        <header class="header-top anim">
            <div class="header-title">
                <h1>لیست کاربران</h1>
                <p>
                    <i class="fa-solid fa-users-gear" style="color: var(--neon-purple);"></i>
                    مدیریت، ویرایش و نظارت بر مشترکین
                    <span style="margin: 0 15px; opacity: 0.3;">|</span>
                    <?php echo $todayDate; ?>
                </p>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-deck anim d-1">
            <div class="stat-card s-users">
                <i class="fa-solid fa-users stat-icon"></i>
                <div class="stat-val"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-lbl">کل کاربران ثبت شده</div>
            </div>
            <div class="stat-card s-active">
                <i class="fa-solid fa-user-check stat-icon"></i>
                <div class="stat-val"><?php echo number_format($activeUsers); ?></div>
                <div class="stat-lbl">کاربران فعال</div>
            </div>
            <div class="stat-card s-block">
                <i class="fa-solid fa-user-lock stat-icon"></i>
                <div class="stat-val"><?php echo number_format($blockedUsers); ?></div>
                <div class="stat-lbl">کاربران مسدود</div>
            </div>
        </div>

        <!-- Main Glass Panel -->
        <div class="glass-panel anim d-2">
            
            <!-- Filters -->
            <div class="toolbar-section">
                <form method="get" class="filter-row" id="usersFilterForm">
                    <input type="text" name="q" class="input-glass" placeholder="جستجو (نام، آیدی، شماره)..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    
                    <select name="status" class="input-glass" style="cursor: pointer;">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="active" <?php echo (isset($_GET['status']) && $_GET['status']==='active')?'selected':''; ?>>فعال</option>
                        <option value="block" <?php echo (isset($_GET['status']) && $_GET['status']==='block')?'selected':''; ?>>مسدود</option>
                    </select>
                    
                    <select name="agent" class="input-glass" style="cursor: pointer;">
                        <option value="">همه گروه‌ها</option>
                        <option value="f" <?php echo (isset($_GET['agent']) && $_GET['agent']==='f')?'selected':''; ?>>کاربر عادی</option>
                        <option value="n" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n')?'selected':''; ?>>نماینده</option>
                        <option value="n2" <?php echo (isset($_GET['agent']) && $_GET['agent']==='n2')?'selected':''; ?>>نماینده ارشد</option>
                    </select>
                    
                    <button type="submit" class="btn-neon" id="usersFilterSubmit">
                        <i class="fa-solid fa-filter"></i> اعمال فیلتر
                    </button>
                    
                    <?php if(!empty($_GET['q']) || !empty($_GET['status']) || !empty($_GET['agent'])): ?>
                        <a href="users.php" class="btn-glass-action" title="پاک کردن">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Action Toolbar -->
                <div class="bulk-actions">
                    <button class="btn-glass-action" id="usersSelectVisible"><i class="fa-solid fa-check-double"></i> انتخاب همه صفحه</button>
                    <button class="btn-glass-action" id="usersClearSelection"><i class="fa-solid fa-minus"></i> لغو انتخاب</button>
                    <span id="usersSelCount" style="color: var(--neon-blue); font-weight: bold; margin: 0 10px; font-size: 1.1rem;">0 انتخاب</span>
                    
                    <div style="flex:1;"></div> <!-- Spacer -->
                    
                    <button class="btn-glass-action" id="usersCopy"><i class="fa-solid fa-copy"></i> کپی آیدی</button>
                    <button class="btn-glass-action" style="color: var(--neon-red); border-color: rgba(239,68,68,0.3);" id="usersBlockSel"><i class="fa-solid fa-ban"></i> مسدود سازی</button>
                    <button class="btn-glass-action" style="color: var(--neon-green); border-color: rgba(16,185,129,0.3);" id="usersUnblockSel"><i class="fa-solid fa-unlock"></i> رفع مسدودی</button>
                    
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-glass-action"><i class="fa-solid fa-download"></i> خروجی CSV</a>
                </div>
                
                <!-- Additional Bulk Actions (Hidden by default or expandable, simplified here) -->
                <div class="bulk-actions" style="border-top: none; padding-top: 0;">
                    <input type="number" id="usersAmount" class="input-glass" placeholder="مبلغ (تومان)" style="max-width: 180px; height: 50px;">
                    <button class="btn-glass-action" id="usersAddBalance"><i class="fa-solid fa-plus"></i> شارژ</button>
                    <button class="btn-glass-action" id="usersLowBalance"><i class="fa-solid fa-minus"></i> کسر</button>
                    
                    <input type="text" id="usersMessage" class="input-glass" placeholder="پیام گروهی..." style="max-width: 300px; height: 50px;">
                    <button class="btn-glass-action" id="usersSendMsg"><i class="fa-solid fa-paper-plane"></i> ارسال</button>
                </div>
            </div>

            <!-- Data Table -->
            <?php if(!$totalUsers): ?>
                <div style="text-align: center; padding: 50px; color: var(--text-dim);">
                    <i class="fa-solid fa-folder-open" style="font-size: 5rem; margin-bottom: 25px; opacity: 0.5;"></i>
                    <h3>کاربری یافت نشد</h3>
                    <p style="font-size: 1.2rem;">فیلترها را تغییر دهید یا جستجو را پاک کنید.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table" id="sample_1">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><i class="fa-solid fa-check"></i></th>
                                <th>آیدی</th>
                                <th>کاربر</th>
                                <th>تلفن</th>
                                <th>موجودی (تومان)</th>
                                <th>زیرمجموعه</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($listusers as $list): 
                                $s = strtolower($list['User_Status']);
                                $statusLabel = ($s === 'active') ? 'فعال' : (($s === 'block') ? 'مسدود' : $list['User_Status']);
                                $statusClass = ($s === 'active') ? 'st-active' : (($s === 'block') ? 'st-block' : 'st-other');
                                $phone = ($list['number'] == "none") ? '<span style="opacity:0.5">---</span>' : $list['number'];
                            ?>
                            <tr>
                                <td><input type="checkbox" class="checkboxes" value="1"></td>
                                <td style="font-family: monospace; font-size: 1.2rem; color: var(--neon-blue); letter-spacing: 1px;"><?php echo $list['id']; ?></td>
                                <td style="font-weight: 800; font-size: 1.2rem;"><?php echo htmlspecialchars($list['username']); ?></td>
                                <td style="font-size: 1.1rem;"><?php echo $phone; ?></td>
                                <td style="color: var(--neon-amber); font-weight: 800; font-size: 1.2rem;"><?php echo number_format($list['Balance']); ?></td>
                                <td style="font-size: 1.2rem; font-weight: bold;"><?php echo number_format($list['affiliatescount']); ?></td>
                                <td><span class="badge-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                <td>
                                    <a href="user.php?id=<?php echo $list['id']; ?>" class="btn-glass-action" style="height: 40px; font-size: 0.9rem; padding: 0 15px;">
                                        <i class="fa-solid fa-pen-to-square"></i> مدیریت
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

    <!-- Floating Dock -->
    <div class="dock-wrapper anim d-3">
        <nav class="dock">
            <a href="index.php" class="dock-item">
                <i class="fa-solid fa-house-chimney"></i>
                <span class="dock-tooltip">داشبورد</span>
            </a>
            <a href="invoice.php" class="dock-item">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span class="dock-tooltip">سفارشات</span>
            </a>
            <a href="user.php" class="dock-item active">
                <i class="fa-solid fa-users"></i>
                <span class="dock-tooltip">کاربران</span>
            </a>
            <a href="product.php" class="dock-item">
                <i class="fa-solid fa-box-open"></i>
                <span class="dock-tooltip">محصولات</span>
            </a>
            <a href="server_status.php" class="dock-item">
                <i class="fa-solid fa-server"></i>
                <span class="dock-tooltip">ادمین</span>
            </a>
            <div style="width: 2px; height: 30px; background: rgba(255,255,255,0.1); margin: 0 8px;"></div>
            <a href="logout.php" class="dock-item" style="color: var(--neon-red);">
                <i class="fa-solid fa-power-off"></i>
                <span class="dock-tooltip">خروج</span>
            </a>
        </nav>
    </div>

    <!-- Scripts (Keeping Logic Intact) -->
    <script src="js/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    
    <!-- Custom JS Logic for Actions -->
    <script>
      (function(){
        // Toast Notification Helper
        function showToast(msg) {
            // Simple alert for now, can be upgraded to fancy toast
            alert(msg);
        }

        // Selection Logic
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

        // Copy IDs
        $('#usersCopy').on('click', function(e){
          e.preventDefault();
          var ids = [];
          $('#sample_1 tbody tr').each(function(){
            var $row = $(this);
            if($row.find('.checkboxes').prop('checked')){ ids.push($row.find('td').eq(1).text().trim()); }
          });
          if(ids.length){ navigator.clipboard.writeText(ids.join(', ')); showToast('آیدی‌ها کپی شد (' + ids.length + ' مورد)'); }
          else{ showToast('هیچ ردیفی انتخاب نشده است'); }
        });

        // Bulk Actions (AJAX)
        function bulkAction(actionType, value) {
            var ids = [];
            $('#sample_1 tbody tr').each(function(){
                var $r=$(this); 
                if($r.find('.checkboxes').prop('checked')) ids.push($r.find('td').eq(1).text().trim()); 
            });
            
            if(!ids.length){ showToast('کاربری انتخاب نشده است'); return; }
            
            if(!confirm('آیا از انجام عملیات روی ' + ids.length + ' کاربر اطمینان دارید؟')) return;

            var done=0; 
            var total=ids.length;
            
            ids.forEach(function(id){ 
                var data = {id: id};
                if(actionType === 'status') data.status = value;
                if(actionType === 'msg') data.textmessage = value;
                if(actionType === 'balance_add') data.priceadd = value;
                if(actionType === 'balance_low') data.pricelow = value;
                if(actionType === 'agent') data.agent = value;

                $.get('user.php', data).always(function(){ 
                    done++; 
                    if(done === total){ 
                        showToast('عملیات با موفقیت انجام شد'); 
                        setTimeout(function(){ location.reload(); }, 1000); 
                    } 
                }); 
            });
        }

        $('#usersBlockSel').on('click', function(e){ e.preventDefault(); bulkAction('status', 'block'); });
        $('#usersUnblockSel').on('click', function(e){ e.preventDefault(); bulkAction('status', 'active'); });
        
        $('#usersSendMsg').on('click', function(e){ 
            e.preventDefault(); 
            var txt=$('#usersMessage').val(); 
            if(!txt) return showToast('متن پیام خالی است');
            bulkAction('msg', txt); 
        });

        $('#usersAddBalance').on('click', function(e){
            e.preventDefault();
            var amt = parseInt($('#usersAmount').val(), 10);
            if(!amt) return showToast('مبلغ نامعتبر');
            bulkAction('balance_add', amt);
        });

        $('#usersLowBalance').on('click', function(e){
            e.preventDefault();
            var amt = parseInt($('#usersAmount').val(), 10);
            if(!amt) return showToast('مبلغ نامعتبر');
            bulkAction('balance_low', amt);
        });

      })();
    </script>
</body>
</html>