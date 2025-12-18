<?php
/**
 * Keyboard Editor - Cosmic Glass Edition (Ultimate UI)
 * Features: Premium Glassmorphism, Neon Glows, Smooth Animations
 */

session_start();

// 1. Load Configurations & Auth
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../jdf.php';
    require_once __DIR__ . '/../function.php';
}

// 2. Authentication
if (isset($pdo) && !isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

// 3. API Handler
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $inputJSON = file_get_contents("php://input");
    $inputData = json_decode($inputJSON, true);

    if (is_array($inputData)) {
        $saveData = isset($inputData['keyboard']) ? $inputData : ['keyboard' => $inputData, 'stash' => []];
        
        if (function_exists('update')) {
            update("setting", "keyboardmain", json_encode($saveData, JSON_UNESCAPED_UNICODE), null, null);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'ts' => time()]);
        exit;
    }
}

// 4. Reset Logic
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $defaultData = json_encode([
        "keyboard" => [
            [["text" => "text_sell"], ["text" => "text_extend"]],
            [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
            [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
            [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
            [["text" => "text_support"], ["text" => "text_help"]]
        ],
        "stash" => []
    ], JSON_UNESCAPED_UNICODE);
    
    if (function_exists('update')) {
        update("setting", "keyboardmain", $defaultData, null, null);
    }
    header('Location: keyboard.php');
    exit;
}

// 5. Fetch Data
$currentKeyboardJSON = '[]';
$currentStashJSON = '[]';

try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT keyboardmain FROM setting LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && !empty($settings['keyboardmain'])) {
            $decoded = json_decode($settings['keyboardmain'], true);
            if (isset($decoded['keyboard'])) {
                $currentKeyboardJSON = json_encode($decoded['keyboard']);
            } elseif (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
                $currentKeyboardJSON = json_encode($decoded);
            }
            if (isset($decoded['stash'])) {
                $currentStashJSON = json_encode($decoded['stash']);
            }
        }
    }
    
    // Fallback if empty
    if ($currentKeyboardJSON == '[]' || $currentKeyboardJSON == 'null') {
         $def = [
            [["text" => "text_sell"], ["text" => "text_extend"]],
            [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
            [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
            [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
            [["text" => "text_support"], ["text" => "text_help"]]
         ];
         $currentKeyboardJSON = json_encode($def);
    }
} catch (Exception $e) { 
    $currentKeyboardJSON = '[]'; 
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طراحی کیبورد</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- THEME CONFIG --- */
        :root {
            --bg-deep: #020617;
            --accent-primary: #8b5cf6; /* Violet */
            --accent-glow: rgba(139, 92, 246, 0.5);
            --accent-secondary: #06b6d4; /* Cyan */
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-panel: rgba(15, 23, 42, 0.6);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh; overflow: hidden;
            display: flex; flex-direction: column;
            selection-background-color: var(--accent-primary);
            selection-color: white;
        }

        /* --- COSMIC BACKGROUND --- */
        .cosmic-bg {
            position: fixed; inset: 0; z-index: -2;
            background: radial-gradient(circle at 50% 120%, #2e1065, #020617 50%);
        }
        .star-field {
            position: fixed; inset: 0; z-index: -1; opacity: 0.4;
            background-image: 
                radial-gradient(1px 1px at 20px 30px, #fff, transparent),
                radial-gradient(1px 1px at 40px 70px, #fff, transparent),
                radial-gradient(1px 1px at 50px 160px, #fff, transparent),
                radial-gradient(2px 2px at 90px 40px, #fff, transparent),
                radial-gradient(2px 2px at 130px 80px, #fff, transparent);
            background-size: 200px 200px;
            animation: starMove 100s linear infinite;
        }
        @keyframes starMove { from { background-position: 0 0; } to { background-position: 0 1000px; } }

        /* --- LAYOUT GRID --- */
        .app-layout {
            display: grid; 
            grid-template-columns: 380px 1fr 340px; 
            height: calc(100vh - 80px);
        }

        /* --- PANELS --- */
        .panel { display: flex; flex-direction: column; position: relative; transition: 0.3s; }
        
        .panel-preview {
            background: rgba(2, 6, 23, 0.3);
            border-left: 1px solid var(--glass-border);
            align-items: center; justify-content: center;
            backdrop-filter: blur(12px);
        }
        
        .panel-editor {
            background: transparent;
            overflow-y: auto; padding: 40px;
        }
        
        .panel-stash {
            background: rgba(15, 23, 42, 0.4);
            border-right: 1px solid var(--glass-border);
            backdrop-filter: blur(20px);
            padding: 30px;
            box-shadow: -10px 0 30px rgba(0,0,0,0.2);
        }

        /* --- ROW WRAPPER --- */
        .row-wrapper {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 24px; padding: 20px; margin-bottom: 24px;
            min-height: 100px;
            display: flex; flex-wrap: wrap; gap: 14px;
            position: relative; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .row-wrapper:hover {
            border-color: rgba(139, 92, 246, 0.3);
            background: rgba(30, 41, 59, 0.6);
            box-shadow: 0 20px 50px -12px rgba(0,0,0,0.5);
            transform: translateY(-4px);
        }

        .drag-handle {
            position: absolute; left: -40px; top: 50%; transform: translateY(-50%);
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; background: rgba(255,255,255,0.05);
            color: #64748b; cursor: grab; opacity: 0; transition: 0.2s;
        }
        .row-wrapper:hover .drag-handle { opacity: 0.6; left: -35px; }
        .drag-handle:hover { opacity: 1 !important; background: var(--accent-primary); color: white; }

        /* --- NEON KEY BUTTONS --- */
        .key-btn {
            flex: 1 0 130px;
            background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.005) 100%);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px; padding: 14px 18px;
            cursor: grab; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        /* Gradient Border Trick */
        .key-btn::before {
            content: ''; position: absolute; inset: 0; padding: 1px; border-radius: 16px;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude;
            pointer-events: none; opacity: 0.5; transition: 0.3s;
        }
        
        .key-btn:hover {
            background: rgba(139, 92, 246, 0.08);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.15), inset 0 0 0 1px rgba(139, 92, 246, 0.3);
            transform: translateY(-3px) scale(1.02);
            z-index: 10;
        }
        .key-btn:hover::before { opacity: 1; background: linear-gradient(45deg, var(--accent-primary), var(--accent-secondary)); }
        
        .key-code { 
            font-family: 'JetBrains Mono', monospace; 
            font-size: 11px; 
            color: #a78bfa; 
            letter-spacing: 0.5px; 
            opacity: 0.9; 
            margin-bottom: 5px; 
            direction: ltr; /* Fix for code direction */
            text-align: center; /* Fix for code alignment */
        }
        .key-label { font-size: 14px; font-weight: 600; color: #f8fafc; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }

        .btn-actions {
            position: absolute; inset: 0;
            background: rgba(2, 6, 23, 0.85); backdrop-filter: blur(5px);
            display: flex; align-items: center; justify-content: center; gap: 12px;
            opacity: 0; transition: 0.2s; transform: translateY(10px);
        }
        .key-btn:hover .btn-actions { opacity: 1; transform: translateY(0); }

        .action-icon {
            width: 36px; height: 36px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 14px; color: white;
            transition: 0.2s; border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
        }
        .action-icon:hover { transform: scale(1.1); box-shadow: 0 0 15px currentColor; }
        .act-edit { color: #60a5fa; border-color: #60a5fa; }
        .act-edit:hover { background: #60a5fa; color: white; }
        .act-del { color: #f87171; border-color: #f87171; }
        .act-del:hover { background: #f87171; color: white; }

        /* --- STASH AREA --- */
        .stash-container {
            flex: 1; overflow-y: auto;
            border: 2px dashed rgba(255,255,255,0.08);
            background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 24px 24px;
            border-radius: 24px; padding: 20px;
            margin-top: 24px;
            display: flex; flex-direction: column; gap: 12px;
            transition: 0.2s;
        }
        .stash-container:hover { border-color: var(--accent-primary); background-color: rgba(0,0,0,0.2); }
        
        .stash-item {
            background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px; padding: 14px 18px; cursor: grab;
            display: flex; justify-content: space-between; align-items: center;
            transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stash-item:hover { 
            border-color: var(--accent-primary); background: rgba(30, 41, 59, 1); 
            transform: translateX(-5px);
        }

        /* --- PHONE MOCKUP --- */
        .mockup {
            width: 340px; height: 740px;
            background: #000; border-radius: 56px;
            box-shadow: 
                0 0 0 6px #333, /* Inner Bezel */
                0 0 0 10px #555, /* Outer Frame */
                0 40px 100px -20px rgba(0,0,0,0.8); /* Depth */
            display: flex; flex-direction: column; overflow: hidden; position: relative;
        }
        /* Glossy Reflection */
        .mockup::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0.05) 0%, transparent 40%, rgba(255,255,255,0.02) 100%);
            pointer-events: none; z-index: 50;
        }
        
        .dynamic-island {
            width: 126px; height: 34px; background: #000;
            border-radius: 20px; position: absolute; top: 16px; left: 50%; transform: translateX(-50%);
            z-index: 60;
        }

        .tg-header {
            background: #212121; padding: 56px 20px 16px; color: white;
            display: flex; align-items: center; border-bottom: 1px solid #111; z-index: 10;
        }
        .tg-body {
            flex: 1; background: #0f0f0f;
            background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1h2v2H1V1zm4 4h2v2H5V5zm4 4h2v2H9V9zm4 4h2v2h-2v-2zm4 4h2v2h-2v-2z' fill='%23222' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 12px;
        }
        .tg-kb-area {
            background: #1c1c1e; padding: 10px; min-height: 240px;
            border-top: 1px solid #000; z-index: 20;
        }
        .tg-btn {
            background: linear-gradient(180deg, #38383a 0%, #2c2c2e 100%);
            color: #fff; border-radius: 8px; padding: 12px 4px; margin: 3px;
            font-size: 13px; text-align: center; flex: 1;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.08);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            border: 1px solid transparent;
        }

        /* --- UI COMPONENTS --- */
        .btn-header {
            height: 46px; border-radius: 14px; padding: 0 24px;
            font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;
            transition: all 0.2s; cursor: pointer;
        }
        .btn-primary-glow {
            background: linear-gradient(135deg, var(--accent-primary), #6366f1);
            color: white; box-shadow: 0 0 25px rgba(139, 92, 246, 0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-primary-glow:hover { 
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.6); 
            transform: translateY(-2px); 
            filter: brightness(1.1);
        }
        .btn-primary-glow:disabled { filter: grayscale(1); opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        .btn-glass {
            background: rgba(255,255,255,0.03); color: var(--text-muted);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .btn-glass:hover { background: rgba(255,255,255,0.08); color: white; border-color: rgba(255,255,255,0.1); }

        /* --- Scrollbars --- */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

        @media (max-width: 1400px) {
            .app-layout { grid-template-columns: 0 1fr 340px; }
            .panel-preview { display: none; }
        }
        @media (max-width: 900px) {
            .app-layout { grid-template-columns: 1fr; grid-template-rows: 1fr auto; }
            .panel-stash { height: 280px; border-right: none; border-top: 1px solid var(--glass-border); order: 2; width: 100%; }
            .panel-editor { order: 1; padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Background Effects -->
    <div class="cosmic-bg"></div>
    <div class="star-field"></div>

    <!-- Header -->
    <header class="h-[80px] px-8 flex items-center justify-between z-50 border-b border-white/5 bg-[#020617]/80 backdrop-blur-xl sticky top-0">
        <div class="flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500/20 to-fuchsia-500/20 flex items-center justify-center border border-white/10 shadow-[0_0_25px_rgba(139,92,246,0.25)]">
                <i class="fa-solid fa-layer-group text-violet-400 text-xl"></i>
            </div>
            <div>
                <h1 class="font-bold text-white text-xl tracking-tight">Keyboard <span class="text-violet-400">Editor</span></h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[11px] text-slate-400 font-mono tracking-widest uppercase">System Ready</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <a href="index.php" class="btn-header btn-glass">
                <i class="fa-solid fa-arrow-right-from-bracket text-lg"></i>
                <span class="hidden sm:inline">خروج</span>
            </a>
            <a href="keyboard.php?action=reset" onclick="return confirm('آیا مطمئن هستید؟ همه تغییرات حذف خواهند شد.')" class="w-12 h-[46px] rounded-xl btn-glass flex items-center justify-center text-rose-400 hover:bg-rose-500/10 hover:border-rose-500/30 hover:text-rose-300 transition" title="بازنشانی">
                <i class="fa-solid fa-rotate-left text-lg"></i>
            </a>
            <div class="w-px h-10 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="btn-header btn-primary-glow" disabled>
                <i class="fa-regular fa-floppy-disk text-lg"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <div class="app-layout">
        
        <!-- Right: Preview -->
        <div class="panel panel-preview">
            <div class="mb-8 flex items-center gap-2 px-5 py-2 rounded-full bg-violet-500/10 border border-violet-500/20 text-[11px] font-bold text-violet-300 uppercase tracking-widest backdrop-blur-sm shadow-[0_0_15px_rgba(139,92,246,0.15)]">
                Live Preview
            </div>
            
            <div class="mockup">
                <div class="dynamic-island"></div>
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right text-gray-400 ml-2"></i>
                    <div class="flex-1 mr-3">
                        <div class="text-sm font-bold">ربات تلگرام</div>
                        <div class="text-[11px] text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>
                <div class="tg-body">
                    <div class="bg-[#2b5278] text-white p-3.5 rounded-2xl rounded-tr-none text-sm max-w-[85%] shadow-lg mr-auto ml-5 mb-3 relative">
                        منوی ربات شما به این شکل خواهد بود 👇
                    </div>
                </div>
                <div id="preview-render" class="tg-kb-area flex flex-col justify-end" dir="ltr"></div>
            </div>
        </div>

        <!-- Center: Editor -->
        <div class="panel panel-editor custom-scrollbar">
            <div class="max-w-4xl mx-auto pb-48">
                <div class="flex justify-between items-end mb-10 sticky top-0 z-40 py-4 -mt-4 bg-[#020617]/90 backdrop-blur-md border-b border-transparent">
                    <div>
                        <h2 class="text-3xl font-bold text-white mb-2">چیدمان دکمه‌ها</h2>
                        <p class="text-sm text-slate-400">برای تغییر ترتیب، دکمه‌ها را بکشید و رها کنید.</p>
                    </div>
                    <button onclick="App.addRow()" class="h-11 px-6 rounded-xl border border-dashed border-violet-500/40 text-violet-300 text-sm font-bold hover:bg-violet-500/10 hover:border-violet-400 hover:text-white transition flex items-center gap-2 group">
                        <span class="w-6 h-6 rounded-full bg-violet-500/20 flex items-center justify-center group-hover:bg-violet-500 group-hover:text-white transition"><i class="fa-solid fa-plus text-xs"></i></span>
                        سطر جدید
                    </button>
                </div>

                <div id="editor-render" class="flex flex-col gap-6"></div>
                
                <!-- Bottom Decoration -->
                <div class="mt-20 flex flex-col items-center justify-center text-slate-700 gap-3 opacity-60">
                   <div class="h-12 w-px bg-gradient-to-b from-transparent via-slate-700 to-transparent"></div>
                   <span class="text-xs font-mono tracking-widest uppercase">End of Keyboard</span>
                </div>
            </div>
        </div>

        <!-- Left: Stash -->
        <div class="panel panel-stash">
            <div class="flex items-center gap-4 mb-8 text-slate-200">
                <div class="w-14 h-14 rounded-2xl bg-slate-800/80 flex items-center justify-center text-violet-400 border border-slate-700 shadow-inner">
                    <i class="fa-solid fa-box-archive text-2xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg">انبار دکمه‌ها</h3>
                    <p class="text-xs text-slate-400 mt-1">محل نگهداری موقت</p>
                </div>
            </div>
            
            <div class="bg-violet-500/5 p-5 rounded-2xl border border-violet-500/10 mb-2 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-violet-500/10 blur-xl rounded-full -translate-y-1/2 translate-x-1/2"></div>
                <p class="text-xs text-violet-200/90 leading-relaxed relative z-10">
                    <i class="fa-regular fa-lightbulb ml-2 text-violet-400 text-sm"></i>
                    دکمه‌هایی که فعلاً لازم ندارید را اینجا بیندازید تا بعداً استفاده کنید.
                </p>
            </div>
            
            <div id="stash-render" class="stash-container custom-scrollbar"></div>
        </div>

    </div>

    <script>
        const App = {
            data: {
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                stash: <?php echo $currentStashJSON ?: '[]'; ?>,
                snapshot: '',
                labels: {
                    'text_sell': '🛍 خرید سرویس',
                    'text_extend': '🔄 تمدید سرویس',
                    'text_usertest': '🔥 تست رایگان',
                    'text_wheel_luck': '🎰 گردونه شانس',
                    'text_Purchased_services': '🛒 سرویس ها من',
                    'accountwallet': '💳 کیف پول',
                    'text_affiliates': '🤝 همکاری',
                    'text_Tariff_list': '📋 تعرفه‌ها',
                    'text_support': '🎧 پشتیبانی',
                    'text_help': '📚 راهنما',
                    'text_start': '🏠 شروع'
                }
            },

            dom: {
                editor: document.getElementById('editor-render'),
                preview: document.getElementById('preview-render'),
                stash: document.getElementById('stash-render'),
                saveBtn: document.getElementById('btn-save')
            },

            init() {
                if (!Array.isArray(this.data.keyboard)) this.data.keyboard = [];
                if (!Array.isArray(this.data.stash)) this.data.stash = [];
                
                this.data.snapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                
                this.swal = Swal.mixin({
                    background: '#0f172a', color: '#f8fafc',
                    confirmButtonColor: '#8b5cf6', cancelButtonColor: '#ef4444',
                    customClass: { 
                        popup: 'rounded-3xl border border-violet-500/20 shadow-2xl',
                        input: 'bg-slate-900 border-slate-700 text-white rounded-xl focus:ring-2 focus:ring-violet-500 px-4 py-2'
                    }
                });

                this.render();
            },

            render() {
                this.renderEditor();
                this.renderStash();
                this.renderPreview();
                this.checkChanges();
            },

            renderEditor() {
                const { editor } = this.dom;
                editor.innerHTML = '';

                if (this.data.keyboard.length === 0) {
                    editor.innerHTML = `
                        <div class="flex flex-col items-center justify-center py-32 opacity-60 text-violet-300 border-2 border-dashed border-violet-500/20 rounded-[30px] bg-violet-500/5">
                            <div class="w-20 h-20 rounded-full bg-violet-500/10 flex items-center justify-center mb-6">
                                <i class="fa-solid fa-layer-group text-4xl opacity-80"></i>
                            </div>
                            <p class="text-xl font-bold">هنوز سطری اضافه نکرده‌اید</p>
                            <button onclick="App.addRow()" class="mt-4 text-sm text-violet-400 hover:text-white transition flex items-center gap-2 group">
                                <span class="border-b border-dashed border-violet-400 group-hover:border-white pb-0.5">ایجاد اولین سطر</span>
                                <i class="fa-solid fa-arrow-left group-hover:-translate-x-1 transition"></i>
                            </button>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-wrapper group';
                    rowEl.dataset.rowIdx = rIdx;
                    
                    rowEl.innerHTML = `<div class="drag-handle row-handle"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        rowEl.appendChild(this.createKeyElement(btn, rIdx, bIdx, 'main'));
                    });

                    // Add Button Logic
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-14 h-auto min-h-[60px] rounded-2xl border-2 border-dashed border-slate-600 flex items-center justify-center text-slate-500 hover:text-violet-400 hover:border-violet-400 hover:bg-violet-400/5 cursor-pointer transition opacity-50 hover:opacity-100';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-xl"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Row Logic
                    if (row.length === 0) {
                        const delBtn = document.createElement('div');
                        delBtn.className = 'w-full text-center text-xs text-rose-400 py-5 cursor-pointer border border-dashed border-rose-500/20 rounded-2xl bg-rose-500/5 hover:bg-rose-500/10 transition flex items-center justify-center gap-2 font-bold';
                        delBtn.innerHTML = '<i class="fa-solid fa-trash-can text-sm"></i> حذف این سطر';
                        delBtn.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delBtn);
                    }

                    editor.appendChild(rowEl);
                });

                new Sortable(editor, {
                    animation: 250, handle: '.row-handle', ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                document.querySelectorAll('.row-wrapper').forEach(el => {
                    new Sortable(el, {
                        group: 'shared-keys', animation: 200, draggable: '.key-btn', ghostClass: 'opacity-40',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            renderStash() {
                const { stash } = this.dom;
                stash.innerHTML = '';
                
                if (this.data.stash.length === 0) {
                    stash.innerHTML = `
                        <div class="text-center py-16 opacity-30 text-xs text-slate-400 flex flex-col items-center select-none">
                            <i class="fa-solid fa-ghost text-4xl mb-4 text-violet-300"></i>
                            <span class="font-medium">انبار خالی است</span>
                        </div>`;
                }

                this.data.stash.forEach((btn, idx) => {
                    stash.appendChild(this.createKeyElement(btn, null, idx, 'stash'));
                });

                new Sortable(stash, {
                    group: 'shared-keys', animation: 200, ghostClass: 'opacity-50',
                    onEnd: () => this.rebuildData()
                });
            },

            createKeyElement(btn, rIdx, bIdx, type) {
                // If label doesn't exist, show the button text (code) itself, better than "Custom Button"
                const label = this.data.labels[btn.text] || btn.text;
                const el = document.createElement('div');
                
                if (type === 'stash') {
                    el.className = 'stash-item';
                    el.dataset.text = btn.text;
                    el.innerHTML = `
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 text-sm border border-violet-500/20 shadow-sm">
                                <i class="fa-solid fa-terminal"></i>
                            </div>
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-xs text-slate-200 truncate font-bold mb-0.5">${label}</span>
                                <span class="text-[10px] text-slate-500 truncate font-mono opacity-80" dir="ltr">${btn.text}</span>
                            </div>
                        </div>
                        <div class="flex gap-2 opacity-0 hover:opacity-100 transition-opacity">
                            <button onclick="App.editKey('${type}', ${rIdx}, ${bIdx})" class="w-8 h-8 rounded-lg bg-blue-600 hover:bg-blue-500 flex items-center justify-center text-xs text-white shadow-lg transition hover:scale-110"><i class="fa-solid fa-pen"></i></button>
                            <button onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})" class="w-8 h-8 rounded-lg bg-rose-600 hover:bg-rose-500 flex items-center justify-center text-xs text-white shadow-lg transition hover:scale-110"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    `;
                    el.onmouseenter = () => el.querySelector('.flex.gap-2').classList.remove('opacity-0');
                    el.onmouseleave = () => el.querySelector('.flex.gap-2').classList.add('opacity-0');
                } else {
                    el.className = 'key-btn';
                    el.dataset.text = btn.text;
                    el.innerHTML = `
                        <div class="key-code">${btn.text.substring(0, 14)}${btn.text.length>14?'..':''}</div>
                        <div class="key-label truncate">${label}</div>
                        
                        <div class="btn-actions">
                            <div class="action-icon act-edit" onclick="App.editKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="action-icon act-del" onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-trash"></i></div>
                        </div>
                    `;
                }
                return el;
            },

            renderPreview() {
                const { preview } = this.dom;
                preview.innerHTML = '';
                this.data.keyboard.forEach(row => {
                    if (row.length === 0) return;
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'flex w-full gap-1 mb-1';
                    row.forEach(btn => {
                        const btnEl = document.createElement('div');
                        btnEl.className = 'tg-btn';
                        btnEl.innerText = this.data.labels[btn.text] || btn.text; 
                        rowDiv.appendChild(btnEl);
                    });
                    preview.appendChild(rowDiv);
                });
            },

            rebuildData() {
                // Keyboard
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-wrapper').forEach(rowEl => {
                    const btns = [];
                    rowEl.querySelectorAll('[data-text]').forEach(item => btns.push({ text: item.dataset.text }));
                    newRows.push(btns);
                });
                this.data.keyboard = newRows;

                // Stash
                const newStash = [];
                this.dom.stash.querySelectorAll('[data-text]').forEach(item => newStash.push({ text: item.dataset.text }));
                this.data.stash = newStash;

                this.render();
            },

            checkChanges() {
                const current = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                const isDirty = current !== this.data.snapshot;
                const { saveBtn } = this.dom;
                
                if (isDirty) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa-solid fa-check text-lg"></i> <span class="text-sm">ذخیره تغییرات</span>';
                    saveBtn.classList.add('animate-pulse');
                    saveBtn.classList.remove('grayscale');
                } else {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk text-lg"></i> <span class="text-sm">ذخیره شد</span>';
                    saveBtn.classList.remove('animate-pulse');
                    saveBtn.classList.add('grayscale');
                }
            },

            addRow() {
                this.data.keyboard.push([]);
                this.render();
                setTimeout(() => {
                    const container = document.querySelector('.panel-editor');
                    container.scrollTop = container.scrollHeight;
                }, 50);
            },

            deleteRow(idx) {
                this.data.keyboard.splice(idx, 1);
                this.render();
            },

            async addKeyToRow(rIdx) {
                const { value: text } = await this.swal.fire({
                    title: 'کد دکمه جدید',
                    input: 'text', inputValue: 'text_new',
                    showCancelButton: true, confirmButtonText: 'افزودن'
                });
                if (text) {
                    this.data.keyboard[rIdx].push({text});
                    this.render();
                }
            },

            deleteKey(type, rIdx, bIdx) {
                if (type === 'stash') this.data.stash.splice(bIdx, 1);
                else this.data.keyboard[rIdx].splice(bIdx, 1);
                this.render();
            },

            async editKey(type, rIdx, bIdx) {
                let current = (type === 'stash') ? this.data.stash[bIdx].text : this.data.keyboard[rIdx][bIdx].text;
                const { value: text } = await this.swal.fire({
                    title: 'ویرایش کد دکمه',
                    input: 'text', inputValue: current,
                    showCancelButton: true, confirmButtonText: 'ذخیره'
                });
                if (text) {
                    if (type === 'stash') this.data.stash[bIdx].text = text;
                    else this.data.keyboard[rIdx][bIdx].text = text;
                    this.render();
                }
            },

            save() {
                const { saveBtn } = this.dom;
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-xl"></i>';
                saveBtn.disabled = true;

                fetch('keyboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        keyboard: this.data.keyboard,
                        stash: this.data.stash
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.data.snapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                        this.checkChanges();
                        const Toast = Swal.mixin({
                            toast: true, position: 'top-end', showConfirmButton: false, 
                            timer: 3000, background: '#020617', color: '#fff'
                        });
                        Toast.fire({icon: 'success', title: 'تنظیمات ذخیره شد'});
                    }
                })
                .catch(() => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    this.swal.fire({icon: 'error', title: 'خطا در ارتباط'});
                });
            }
        };

        App.init();
    </script>
</body>
</html>