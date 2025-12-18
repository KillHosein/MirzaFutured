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
            --accent-primary: #8b5cf6;
            --accent-glow: rgba(139, 92, 246, 0.5);
            --accent-secondary: #06b6d4;
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
            grid-template-columns: 420px 1fr 340px; 
            height: calc(100vh - 80px);
        }

        /* --- PANELS --- */
        .panel { display: flex; flex-direction: column; position: relative; transition: 0.3s; }
        
        .panel-preview {
            background: rgba(2, 6, 23, 0.5);
            border-left: 1px solid var(--glass-border);
            align-items: center; justify-content: center;
            backdrop-filter: blur(12px);
            padding: 20px;
            overflow: hidden;
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
        }
        
        .key-btn:hover {
            background: rgba(139, 92, 246, 0.08);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.15), inset 0 0 0 1px rgba(139, 92, 246, 0.3);
            transform: translateY(-3px) scale(1.02);
            z-index: 10;
        }
        
        .key-code { 
            font-family: 'JetBrains Mono', monospace; 
            font-size: 11px; color: #a78bfa; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 5px; 
            direction: ltr; text-align: center;
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
        .act-edit { color: #60a5fa; border-color: #60a5fa; }
        .act-edit:hover { background: #60a5fa; color: white; }
        .act-del { color: #f87171; border-color: #f87171; }
        .act-del:hover { background: #f87171; color: white; }

        /* --- STASH AREA --- */
        .stash-container {
            flex: 1; overflow-y: auto;
            border: 2px dashed rgba(255,255,255,0.08);
            border-radius: 24px; padding: 20px;
            margin-top: 24px;
            display: flex; flex-direction: column; gap: 12px;
        }
        
        .stash-item {
            background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px; padding: 14px 18px; cursor: grab;
            display: flex; justify-content: space-between; align-items: center;
            transition: 0.2s;
        }
        .stash-item:hover { border-color: var(--accent-primary); background: rgba(30, 41, 59, 1); transform: translateX(-5px); }

        /* --- PHONE MOCKUP (iPhone 14 Pro Style) --- */
        .mockup-wrapper {
            position: relative;
            transform-origin: center center;
            transition: transform 0.3s ease;
        }
        
        .mockup {
            width: 380px; height: 780px;
            background: #000; border-radius: 54px;
            padding: 12px;
            box-shadow: 
                0 0 0 4px #1a1a1a,
                0 0 0 8px #222,
                0 20px 80px rgba(0,0,0,0.8);
            position: relative;
            user-select: none;
        }
        
        .screen {
            width: 100%; height: 100%;
            background: #000;
            border-radius: 42px;
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
        }
        
        .status-bar {
            height: 44px; display: flex; justify-content: space-between; align-items: center;
            padding: 0 32px; color: white; font-size: 12px; font-weight: bold;
            position: absolute; top: 0; left: 0; right: 0; z-index: 100;
        }

        .dynamic-island {
            width: 110px; height: 32px; background: #000;
            border-radius: 20px; position: absolute; top: 11px; left: 50%; transform: translateX(-50%);
            z-index: 110;
        }

        .tg-header {
            background: #212121; padding: 50px 20px 12px; color: white;
            display: flex; align-items: center; border-bottom: 1px solid #111;
        }
        .tg-body {
            flex: 1; background: #0f0f0f;
            background-image: url("https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png");
            background-size: cover;
            display: flex; flex-direction: column; justify-content: flex-end; padding: 15px;
        }
        .tg-msg {
            background: #2b5278; color: white; padding: 10px 15px; border-radius: 18px 18px 2px 18px;
            font-size: 13px; max-width: 85%; align-self: flex-start; margin-bottom: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .tg-kb-area {
            background: #1c1c1e; padding: 12px 10px 30px; min-height: 220px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .tg-btn {
            background: linear-gradient(180deg, #3a3a3c 0%, #2c2c2e 100%);
            color: #fff; border-radius: 8px; padding: 12px 4px; margin: 3px;
            font-size: 13px; text-align: center; flex: 1;
            box-shadow: 0 1px 1px rgba(0,0,0,0.4);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
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
        .btn-primary-glow:hover:not(:disabled) { transform: translateY(-2px); filter: brightness(1.1); }
        .btn-primary-glow:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-glass { background: rgba(255,255,255,0.03); color: var(--text-muted); border: 1px solid rgba(255,255,255,0.05); }
        .btn-glass:hover { background: rgba(255,255,255,0.08); color: white; }

        @media (max-width: 1400px) {
            .app-layout { grid-template-columns: 0 1fr 340px; }
            .panel-preview { display: none; }
        }
    </style>
</head>
<body>

    <div class="cosmic-bg"></div>
    <div class="star-field"></div>

    <header class="h-[80px] px-8 flex items-center justify-between z-50 border-b border-white/5 bg-[#020617]/80 backdrop-blur-xl sticky top-0">
        <div class="flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500/20 to-fuchsia-500/20 flex items-center justify-center border border-white/10 shadow-[0_0_25px_rgba(139,92,246,0.25)]">
                <i class="fa-solid fa-layer-group text-violet-400 text-xl"></i>
            </div>
            <div>
                <h1 class="font-bold text-white text-xl tracking-tight">طراحی <span class="text-violet-400">کیبورد</span></h1>
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
            <a href="keyboard.php?action=reset" onclick="return confirm('آیا مطمئن هستید؟ همه تغییرات حذف خواهند شد.')" class="w-12 h-[46px] rounded-xl btn-glass flex items-center justify-center text-rose-400 hover:bg-rose-500/10 transition" title="بازنشانی">
                <i class="fa-solid fa-rotate-left text-lg"></i>
            </a>
            <div class="w-px h-10 bg-white/10 mx-2"></div>
            <button onclick="App.exportJson()" class="btn-header btn-glass" title="Export JSON">
                <i class="fa-solid fa-file-code"></i>
            </button>
            <div class="w-px h-10 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="btn-header btn-primary-glow" disabled>
                <i class="fa-regular fa-floppy-disk text-lg"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <div class="app-layout">
        
        <!-- Right: Preview (Modified for scaling) -->
        <div class="panel panel-preview" id="preview-panel">
            <div class="mockup-wrapper" id="mockup-container">
                <div class="mockup">
                    <div class="status-bar">
                        <span>9:41</span>
                        <div class="flex gap-1.5 items-center">
                            <i class="fa-solid fa-signal text-[10px]"></i>
                            <i class="fa-solid fa-wifi text-[10px]"></i>
                            <i class="fa-solid fa-battery-full text-xs"></i>
                        </div>
                    </div>
                    <div class="dynamic-island"></div>
                    <div class="screen">
                        <div class="tg-header">
                            <i class="fa-solid fa-chevron-right text-blue-400 ml-3"></i>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-500 to-cyan-400 flex items-center justify-center text-white font-bold ml-3">B</div>
                            <div class="flex-1">
                                <div class="text-sm font-bold">ربات مدیریت پنل</div>
                                <div class="text-[10px] text-slate-400">bot</div>
                            </div>
                            <i class="fa-solid fa-ellipsis-vertical text-slate-500"></i>
                        </div>
                        <div class="tg-body">
                            <div class="tg-msg">
                                به پنل خوش آمدید! لطفاً از منوی زیر انتخاب کنید:
                            </div>
                        </div>
                        <div id="preview-render" class="tg-kb-area flex flex-col justify-end" dir="ltr"></div>
                        <div class="h-1 bg-white/30 w-1/3 mx-auto mb-2 rounded-full"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Center: Editor -->
        <div class="panel panel-editor custom-scrollbar">
            <div class="max-w-4xl mx-auto pb-48">
                <div class="flex justify-between items-end mb-10 sticky top-0 z-40 py-4 -mt-4 bg-[#020617]/90 backdrop-blur-md">
                    <div>
                        <h2 class="text-3xl font-bold text-white mb-2">چیدمان دکمه‌ها</h2>
                        <p class="text-sm text-slate-400">دکمه‌ها را بکشید و رها کنید.</p>
                    </div>
                    <button onclick="App.addRow()" class="h-11 px-6 rounded-xl border border-dashed border-violet-500/40 text-violet-300 text-sm font-bold hover:bg-violet-500/10 transition flex items-center gap-2">
                        <i class="fa-solid fa-plus text-xs"></i> سطر جدید
                    </button>
                </div>
                <div id="editor-render" class="flex flex-col gap-6"></div>
            </div>
        </div>

        <!-- Left: Stash -->
        <div class="panel panel-stash">
            <div class="flex items-center gap-4 mb-8 text-slate-200">
                <div class="w-14 h-14 rounded-2xl bg-slate-800/80 flex items-center justify-center text-violet-400 border border-slate-700">
                    <i class="fa-solid fa-box-archive text-2xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg">انبار دکمه‌ها</h3>
                    <p class="text-xs text-slate-400">محل نگهداری موقت</p>
                </div>
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
                    'text_sell': '🛍 خرید سرویس', 'text_extend': '🔄 تمدید سرویس', 'text_usertest': '🔥 تست رایگان',
                    'text_wheel_luck': '🎰 گردونه شانس', 'text_Purchased_services': '🛒 سرویس‌های من',
                    'accountwallet': '💳 کیف پول', 'text_affiliates': '🤝 همکاری', 'text_Tariff_list': '📋 تعرفه‌ها',
                    'text_support': '🎧 پشتیبانی', 'text_help': '📚 راهنما', 'text_start': '🏠 شروع'
                }
            },

            dom: {
                editor: document.getElementById('editor-render'),
                preview: document.getElementById('preview-render'),
                stash: document.getElementById('stash-render'),
                saveBtn: document.getElementById('btn-save'),
                mockup: document.getElementById('mockup-container'),
                previewPanel: document.getElementById('preview-panel')
            },

            init() {
                if (!Array.isArray(this.data.keyboard)) this.data.keyboard = [];
                if (!Array.isArray(this.data.stash)) this.data.stash = [];
                this.data.snapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                
                this.swal = Swal.mixin({
                    background: '#0f172a', color: '#f8fafc',
                    confirmButtonColor: '#8b5cf6', cancelButtonColor: '#ef4444'
                });

                this.render();
                window.addEventListener('resize', () => this.handleScale());
                this.handleScale();
            },

            handleScale() {
                const container = this.dom.previewPanel;
                const mockup = this.dom.mockup;
                const padding = 60; 
                const availableHeight = container.clientHeight - padding;
                const mockupHeight = 780;
                
                if (availableHeight < mockupHeight) {
                    const scale = availableHeight / mockupHeight;
                    mockup.style.transform = `scale(${scale})`;
                } else {
                    mockup.style.transform = `scale(1)`;
                }
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

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-wrapper group';
                    rowEl.dataset.rowIdx = rIdx;
                    rowEl.innerHTML = `<div class="drag-handle row-handle"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        rowEl.appendChild(this.createKeyElement(btn, rIdx, bIdx, 'main'));
                    });

                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-14 h-auto min-h-[60px] rounded-2xl border-2 border-dashed border-slate-600 flex items-center justify-center text-slate-500 hover:text-violet-400 hover:border-violet-400 cursor-pointer transition';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-xl"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    if (row.length === 0) {
                        const delBtn = document.createElement('div');
                        delBtn.className = 'w-full text-center text-xs text-rose-400 py-4 cursor-pointer border border-dashed border-rose-500/20 rounded-2xl bg-rose-500/5 hover:bg-rose-500/10 transition';
                        delBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> حذف سطر';
                        delBtn.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delBtn);
                    }
                    editor.appendChild(rowEl);
                });

                new Sortable(editor, { animation: 250, handle: '.row-handle', onEnd: (evt) => {
                    const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                    this.data.keyboard.splice(evt.newIndex, 0, item);
                    this.render();
                }});

                document.querySelectorAll('.row-wrapper').forEach(el => {
                    new Sortable(el, { group: 'shared-keys', animation: 200, draggable: '.key-btn', onEnd: () => this.rebuildData() });
                });
            },

            renderStash() {
                const { stash } = this.dom;
                stash.innerHTML = '';
                this.data.stash.forEach((btn, idx) => {
                    stash.appendChild(this.createKeyElement(btn, null, idx, 'stash'));
                });
                new Sortable(stash, { group: 'shared-keys', animation: 200, onEnd: () => this.rebuildData() });
            },

            createKeyElement(btn, rIdx, bIdx, type) {
                const label = this.data.labels[btn.text] || btn.text;
                const el = document.createElement('div');
                if (type === 'stash') {
                    el.className = 'stash-item';
                    el.dataset.text = btn.text;
                    el.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="text-xs text-slate-200 font-bold">${label}</div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="App.deleteKey('stash', null, ${bIdx})" class="text-rose-400 hover:text-rose-300"><i class="fa-solid fa-xmark"></i></button>
                        </div>`;
                } else {
                    el.className = 'key-btn';
                    el.dataset.text = btn.text;
                    el.innerHTML = `
                        <div class="key-code">${btn.text}</div>
                        <div class="key-label truncate">${label}</div>
                        <div class="btn-actions">
                            <div class="action-icon act-edit" onclick="App.editKey('main', ${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="action-icon act-del" onclick="App.deleteKey('main', ${rIdx}, ${bIdx})"><i class="fa-solid fa-trash"></i></div>
                        </div>`;
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
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-wrapper').forEach(rowEl => {
                    const btns = [];
                    rowEl.querySelectorAll('[data-text]').forEach(item => btns.push({ text: item.dataset.text }));
                    newRows.push(btns);
                });
                this.data.keyboard = newRows;

                const newStash = [];
                this.dom.stash.querySelectorAll('[data-text]').forEach(item => newStash.push({ text: item.dataset.text }));
                this.data.stash = newStash;
                this.render();
            },

            checkChanges() {
                const current = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                const isDirty = current !== this.data.snapshot;
                this.dom.saveBtn.disabled = !isDirty;
            },

            addRow() { this.data.keyboard.push([]); this.render(); },
            deleteRow(idx) { this.data.keyboard.splice(idx, 1); this.render(); },
            
            async addKeyToRow(rIdx) {
                const { value: text } = await this.swal.fire({
                    title: 'کد دکمه جدید', input: 'text', inputValue: 'text_new',
                    showCancelButton: true, confirmButtonText: 'افزودن'
                });
                if (text) { this.data.keyboard[rIdx].push({text}); this.render(); }
            },

            deleteKey(type, rIdx, bIdx) {
                if (type === 'stash') this.data.stash.splice(bIdx, 1);
                else this.data.keyboard[rIdx].splice(bIdx, 1);
                this.render();
            },

            async editKey(type, rIdx, bIdx) {
                let current = (type === 'stash') ? this.data.stash[bIdx].text : this.data.keyboard[rIdx][bIdx].text;
                const { value: text } = await this.swal.fire({
                    title: 'ویرایش کد دکمه', input: 'text', inputValue: current,
                    showCancelButton: true, confirmButtonText: 'ذخیره'
                });
                if (text) {
                    if (type === 'stash') this.data.stash[bIdx].text = text;
                    else this.data.keyboard[rIdx][bIdx].text = text;
                    this.render();
                }
            },

            exportJson() {
                const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify({ keyboard: this.data.keyboard, stash: this.data.stash }, null, 2));
                const downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute("href", dataStr);
                downloadAnchorNode.setAttribute("download", "keyboard_settings.json");
                document.body.appendChild(downloadAnchorNode);
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
            },

            save() {
                const { saveBtn } = this.dom;
                saveBtn.disabled = true;
                fetch('keyboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ keyboard: this.data.keyboard, stash: this.data.stash })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.data.snapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                        this.checkChanges();
                        Swal.fire({ icon: 'success', title: 'ذخیره شد', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
                    }
                });
            }
        };

        App.init();
    </script>
</body>
</html>