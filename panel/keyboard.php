<?php
/**
 * Keyboard Editor - Aurora Pro Edition (Ultimate UI)
 * Features: Advanced Glassmorphism, Animated Backgrounds, Neon Accents
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
    <title>استودیو طراحی کیبورد | فوق حرفه‌ای</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- THEME VARIABLES --- */
        :root {
            --bg-void: #030712;
            --primary: #6366f1;
            --primary-light: #818cf8;
            --accent: #d946ef; /* Fuchsia */
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-surface: rgba(17, 24, 39, 0.6);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            color: var(--text-main);
            height: 100vh; overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* --- ANIMATED BACKGROUND --- */
        .aurora-container {
            position: fixed; inset: 0; z-index: -2; overflow: hidden;
            background: radial-gradient(circle at 50% 0%, #1e1b4b 0%, #030712 60%);
        }
        .orb {
            position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.4;
            animation: float 20s infinite ease-in-out;
        }
        .orb-1 { width: 400px; height: 400px; background: var(--primary); top: -100px; left: 20%; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; background: var(--accent); bottom: -50px; right: 10%; animation-delay: -5s; }
        .grid-overlay {
            position: fixed; inset: 0; z-index: -1; opacity: 0.15;
            background-image: linear-gradient(rgba(99, 102, 241, 0.1) 1px, transparent 1px),
            linear-gradient(90deg, rgba(99, 102, 241, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: linear-gradient(to bottom, black 40%, transparent 100%);
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, 40px); }
        }

        /* --- LAYOUT --- */
        .app-layout {
            display: grid; 
            grid-template-columns: 360px 1fr 320px; 
            height: calc(100vh - 76px);
        }

        /* --- PANELS --- */
        .panel { display: flex; flex-direction: column; position: relative; }
        
        .panel-preview {
            background: rgba(3, 7, 18, 0.4);
            border-left: 1px solid var(--glass-border);
            align-items: center; justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .panel-editor {
            background: transparent;
            overflow-y: auto; padding: 40px;
        }
        
        .panel-stash {
            background: rgba(15, 23, 42, 0.5);
            border-right: 1px solid var(--glass-border);
            backdrop-filter: blur(20px);
            padding: 30px;
        }

        /* --- GLASSMOPHISM CARDS --- */
        .glass-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.4), rgba(15, 23, 42, 0.4));
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(5px);
        }

        /* --- ROW WRAPPER --- */
        .row-wrapper {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 20px; padding: 16px; margin-bottom: 20px;
            min-height: 90px;
            display: flex; flex-wrap: wrap; gap: 12px;
            position: relative; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .row-wrapper:hover {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(30, 41, 59, 0.6);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5);
            transform: translateY(-2px);
        }

        .drag-handle {
            position: absolute; left: -36px; top: 50%; transform: translateY(-50%);
            padding: 8px; cursor: grab; color: #475569; font-size: 1.2rem;
            opacity: 0; transition: 0.2s;
        }
        .row-wrapper:hover .drag-handle { opacity: 0.6; left: -32px; }
        .drag-handle:hover { opacity: 1 !important; color: #a5b4fc; }

        /* --- KEY BUTTONS (NEON CHIPS) --- */
        .key-btn {
            flex: 1 0 130px;
            background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
            border: 1px solid rgba(255,255,255,0.06);
            border-top: 1px solid rgba(255,255,255,0.1); /* Highlight top */
            border-radius: 14px; padding: 14px 16px;
            cursor: grab; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .key-btn:hover {
            border-color: var(--primary-light);
            background: rgba(99, 102, 241, 0.08);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.15), inset 0 0 0 1px rgba(99, 102, 241, 0.2);
            transform: translateY(-2px) scale(1.02);
            z-index: 10;
        }
        
        .key-code { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: #818cf8; letter-spacing: 0.5px; opacity: 0.8; margin-bottom: 4px; }
        .key-label { font-size: 14px; font-weight: 600; color: #f1f5f9; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }

        .btn-actions {
            position: absolute; inset: 0;
            background: rgba(3, 7, 18, 0.8); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center; gap: 12px;
            opacity: 0; transition: 0.2s;
        }
        .key-btn:hover .btn-actions { opacity: 1; }

        .action-icon {
            width: 34px; height: 34px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 13px; color: white;
            transition: 0.2s; border: 1px solid rgba(255,255,255,0.1);
        }
        .action-icon:hover { transform: scale(1.1); }
        .act-edit { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .act-del { background: linear-gradient(135deg, #ef4444, #dc2626); }

        /* --- STASH AREA --- */
        .stash-container {
            flex: 1; overflow-y: auto;
            border: 2px dashed rgba(255,255,255,0.1);
            background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            border-radius: 20px; padding: 16px;
            margin-top: 20px;
            display: flex; flex-direction: column; gap: 10px;
            transition: 0.2s;
        }
        .stash-container:hover { border-color: rgba(99,102,241,0.4); background-color: rgba(0,0,0,0.2); }
        
        .stash-item {
            background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px; padding: 12px 16px; cursor: grab;
            display: flex; justify-content: space-between; align-items: center;
            transition: 0.2s;
        }
        .stash-item:hover { border-color: var(--primary-light); background: rgba(30, 41, 59, 1); }

        /* --- PHONE MOCKUP --- */
        .mockup {
            width: 320px; height: 720px;
            background: #000; border-radius: 54px;
            box-shadow: 
                0 0 0 6px #27272a, /* Inner Frame */
                0 0 0 10px #52525b, /* Outer Frame */
                0 50px 100px -20px rgba(0,0,0,0.7); /* Deep Shadow */
            display: flex; flex-direction: column; overflow: hidden; position: relative;
        }
        .mockup::before { /* Reflection */
            content: ''; position: absolute; top: 0; right: 0; width: 50%; height: 100%;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.05));
            pointer-events: none; z-index: 50;
        }
        .dynamic-island {
            width: 120px; height: 32px; background: #000;
            border-radius: 20px; position: absolute; top: 14px; left: 50%; transform: translateX(-50%);
            z-index: 60;
        }

        .tg-header {
            background: #212121; padding: 52px 16px 14px; color: white;
            display: flex; align-items: center; border-bottom: 1px solid #000; z-index: 10;
        }
        .tg-body {
            flex: 1; background: #0f0f0f;
            background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1h2v2H1V1zm4 4h2v2H5V5zm4 4h2v2H9V9zm4 4h2v2h-2v-2zm4 4h2v2h-2v-2z' fill='%231a1a1a' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 12px;
        }
        .tg-kb-area {
            background: #1c1c1e; padding: 8px; min-height: 230px;
            border-top: 1px solid #000; z-index: 20;
        }
        .tg-btn {
            background: linear-gradient(180deg, #363638 0%, #2c2c2e 100%);
            color: #fff; border-radius: 8px; padding: 12px 4px; margin: 3px;
            font-size: 13px; text-align: center; flex: 1;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            border: 1px solid transparent;
        }

        /* --- UI COMPONENTS --- */
        .btn-header {
            height: 42px; border-radius: 12px; padding: 0 20px;
            font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px;
            transition: all 0.2s; cursor: pointer;
        }
        .btn-primary-glow {
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            color: white; box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-primary-glow:hover { box-shadow: 0 0 30px rgba(99, 102, 241, 0.6); transform: translateY(-1px); }
        .btn-primary-glow:disabled { filter: grayscale(1); opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        .btn-glass {
            background: rgba(255,255,255,0.05); color: var(--text-muted);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .btn-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.1); }

        /* --- Scrollbars --- */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

        @media (max-width: 1400px) {
            .app-layout { grid-template-columns: 0 1fr 320px; }
            .panel-preview { display: none; }
        }
        @media (max-width: 900px) {
            .app-layout { grid-template-columns: 1fr; grid-template-rows: 1fr auto; }
            .panel-stash { height: 260px; border-right: none; border-top: 1px solid var(--glass-border); order: 2; width: 100%; }
            .panel-editor { order: 1; padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Background Effects -->
    <div class="aurora-container">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>
    <div class="grid-overlay"></div>

    <!-- Header -->
    <header class="h-[76px] px-8 flex items-center justify-between z-50 border-b border-white/5 bg-[#030712]/70 backdrop-blur-xl">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center border border-white/10 shadow-[0_0_20px_rgba(99,102,241,0.2)]">
                <i class="fa-solid fa-cube text-indigo-400 text-xl"></i>
            </div>
            <div>
                <h1 class="font-bold text-white text-lg tracking-tight">Keyboard <span class="text-indigo-400">Studio</span></h1>
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                    <span class="text-[10px] text-slate-400 font-mono tracking-widest uppercase">Online Mode</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-header btn-glass">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:inline">خروج</span>
            </a>
            <a href="keyboard.php?action=reset" onclick="return confirm('تنظیمات به حالت پیش‌فرض برگردد؟')" class="w-11 h-[42px] rounded-xl btn-glass flex items-center justify-center text-red-400 hover:bg-red-500/10 hover:border-red-500/30 hover:text-red-300" title="بازنشانی">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
            <div class="w-px h-8 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="btn-header btn-primary-glow" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <div class="app-layout">
        
        <!-- Right: Preview -->
        <div class="panel panel-preview">
            <div class="mb-6 flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-[10px] font-bold text-indigo-300 uppercase tracking-widest backdrop-blur-sm">
                Live Preview
            </div>
            
            <div class="mockup">
                <div class="dynamic-island"></div>
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right text-gray-400 ml-2"></i>
                    <div class="flex-1 mr-2">
                        <div class="text-sm font-bold">ربات تلگرام</div>
                        <div class="text-[10px] text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>
                <div class="tg-body">
                    <div class="bg-[#2b5278] text-white p-3 rounded-2xl rounded-tr-none text-sm max-w-[85%] shadow-lg mr-auto ml-4 mb-2 relative">
                        کیبورد شما به این صورت نمایش داده می‌شود 👇
                    </div>
                </div>
                <div id="preview-render" class="tg-kb-area flex flex-col justify-end" dir="ltr"></div>
            </div>
        </div>

        <!-- Center: Editor -->
        <div class="panel panel-editor custom-scrollbar">
            <div class="max-w-4xl mx-auto pb-48">
                <div class="flex justify-between items-end mb-8 sticky top-0 z-40 py-4 -mt-4 bg-[#030712]/80 backdrop-blur-md">
                    <div>
                        <h2 class="text-2xl font-bold text-white mb-2">چیدمان دکمه‌ها</h2>
                        <p class="text-sm text-slate-400">برای تغییر ترتیب، دکمه‌ها را بکشید و رها کنید.</p>
                    </div>
                    <button onclick="App.addRow()" class="h-10 px-5 rounded-xl border border-dashed border-indigo-500/40 text-indigo-300 text-xs font-bold hover:bg-indigo-500/10 hover:border-indigo-400 transition flex items-center gap-2">
                        <i class="fa-solid fa-plus text-sm"></i> سطر جدید
                    </button>
                </div>

                <div id="editor-render" class="flex flex-col gap-6"></div>
                
                <!-- Bottom Decoration -->
                <div class="mt-16 flex flex-col items-center justify-center text-slate-700 gap-2">
                   <i class="fa-solid fa-grip-lines text-2xl opacity-50"></i>
                   <span class="text-xs font-mono opacity-50">End of Keyboard</span>
                </div>
            </div>
        </div>

        <!-- Left: Stash -->
        <div class="panel panel-stash">
            <div class="flex items-center gap-4 mb-6 text-slate-200">
                <div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center text-indigo-400 border border-slate-700 shadow-inner">
                    <i class="fa-solid fa-box-archive text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base">انبار دکمه‌ها</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">محل نگهداری موقت</p>
                </div>
            </div>
            
            <div class="bg-indigo-500/5 p-4 rounded-xl border border-indigo-500/10 mb-2">
                <p class="text-xs text-indigo-200/80 leading-relaxed">
                    <i class="fa-regular fa-lightbulb ml-1 text-indigo-400"></i>
                    دکمه‌هایی که لازم ندارید را اینجا بیندازید تا حذف نشوند.
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
                    confirmButtonColor: '#6366f1', cancelButtonColor: '#ef4444',
                    customClass: { 
                        popup: 'rounded-2xl border border-indigo-500/20 shadow-2xl',
                        input: 'bg-slate-900 border-slate-700 text-white rounded-lg focus:ring-2 focus:ring-indigo-500'
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
                        <div class="flex flex-col items-center justify-center py-24 opacity-50 text-indigo-300 border-2 border-dashed border-indigo-500/20 rounded-3xl bg-indigo-500/5">
                            <i class="fa-solid fa-layer-group text-6xl mb-6 opacity-80"></i>
                            <p class="text-lg font-bold">هنوز سطری اضافه نکرده‌اید</p>
                            <button onclick="App.addRow()" class="mt-4 text-sm text-indigo-400 hover:text-white transition underline decoration-dashed">ایجاد اولین سطر</button>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-wrapper group';
                    rowEl.dataset.rowIdx = rIdx;
                    
                    rowEl.innerHTML = `<i class="fa-solid fa-grip-vertical drag-handle row-handle"></i>`;

                    row.forEach((btn, bIdx) => {
                        rowEl.appendChild(this.createKeyElement(btn, rIdx, bIdx, 'main'));
                    });

                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-12 h-auto min-h-[50px] rounded-xl border-2 border-dashed border-slate-600 flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:border-indigo-400 hover:bg-indigo-400/5 cursor-pointer transition opacity-50 hover:opacity-100';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-lg"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    if (row.length === 0) {
                        const delBtn = document.createElement('div');
                        delBtn.className = 'w-full text-center text-xs text-red-400 py-4 cursor-pointer border border-dashed border-red-500/20 rounded-xl bg-red-500/5 hover:bg-red-500/10 transition flex items-center justify-center gap-2';
                        delBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> حذف سطر خالی';
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
                        <div class="text-center py-12 opacity-30 text-xs text-slate-400 flex flex-col items-center select-none">
                            <i class="fa-solid fa-ghost text-3xl mb-3"></i>
                            انبار خالی است
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
                const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                const el = document.createElement('div');
                
                if (type === 'stash') {
                    el.className = 'stash-item';
                    el.dataset.text = btn.text;
                    el.innerHTML = `
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-9 h-9 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 text-sm border border-indigo-500/20 shadow-sm">
                                <i class="fa-solid fa-terminal"></i>
                            </div>
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-xs text-slate-200 truncate font-bold">${label}</span>
                                <span class="text-[10px] text-slate-500 truncate font-mono opacity-70">${btn.text}</span>
                            </div>
                        </div>
                        <div class="flex gap-1.5 opacity-0 hover:opacity-100 transition-opacity">
                            <button onclick="App.editKey('${type}', ${rIdx}, ${bIdx})" class="w-7 h-7 rounded-lg bg-blue-600 hover:bg-blue-500 flex items-center justify-center text-xs text-white shadow-lg"><i class="fa-solid fa-pen"></i></button>
                            <button onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})" class="w-7 h-7 rounded-lg bg-red-600 hover:bg-red-500 flex items-center justify-center text-xs text-white shadow-lg"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    `;
                    el.onmouseenter = () => el.querySelector('.flex.gap-1\\.5').classList.remove('opacity-0');
                    el.onmouseleave = () => el.querySelector('.flex.gap-1\\.5').classList.add('opacity-0');
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
                    saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                    saveBtn.classList.add('animate-pulse');
                } else {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                    saveBtn.classList.remove('animate-pulse');
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
                saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ...';
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
                            timer: 3000, background: '#10b981', color: '#fff'
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