<?php
/**
 * Keyboard Editor - Aurora Pro Edition
 * Designed for maximum usability and beauty
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

// 3. API Handler (Save Logic)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $inputJSON = file_get_contents("php://input");
    $inputData = json_decode($inputJSON, true);

    if (is_array($inputData)) {
        if (isset($inputData['keyboard'])) {
            $saveData = $inputData; 
        } else {
            $saveData = ['keyboard' => $inputData, 'stash' => []];
        }
        
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

// 5. Fetch Current Data
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
    
    if ($currentKeyboardJSON == '[]' || $currentKeyboardJSON == 'null') {
         $def = [
            "keyboard" => [
                [["text" => "text_sell"], ["text" => "text_extend"]],
                [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
                [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
                [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
                [["text" => "text_support"], ["text" => "text_help"]]
            ]
         ];
         $currentKeyboardJSON = json_encode($def['keyboard']);
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
    <title>استودیو طراحی کیبورد | نسخه پرو</title>
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- DESIGN TOKENS --- */
        :root {
            --bg-void: #030712;
            --bg-panel: rgba(17, 24, 39, 0.7);
            --bg-card: rgba(31, 41, 55, 0.4);
            
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-active: rgba(99, 102, 241, 0.5);
            
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.3);
            --secondary: #a855f7;
            
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* --- ATMOSPHERE --- */
        .aurora-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2;
            background: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.15), transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(168, 85, 247, 0.1), transparent 50%);
            filter: blur(80px);
        }
        
        .noise-layer {
            position: fixed; inset: 0; z-index: -1; opacity: 0.04; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
        }

        /* --- HEADER --- */
        .studio-header {
            height: 72px; 
            background: rgba(3, 7, 18, 0.6);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-glass);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px; z-index: 50;
        }

        .logo-box {
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(168,85,247,0.1));
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 0 20px rgba(99,102,241,0.15);
        }

        /* --- LAYOUT --- */
        .studio-layout {
            display: grid; 
            /* Grid: Preview (Right/380px) | Editor (Center) | Stash (Left/260px) */
            grid-template-columns: 380px 1fr 260px; 
            height: calc(100vh - 72px);
        }

        /* --- PANELS --- */
        .panel-preview { 
            background: var(--bg-panel); 
            border-left: 1px solid var(--border-glass);
            backdrop-filter: blur(10px);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        
        .panel-editor { 
            flex: 1; overflow-y: auto; padding: 40px; position: relative; 
            background: radial-gradient(circle at 50% 30%, rgba(99,102,241,0.03), transparent 70%);
        }
        
        .panel-stash {
            background: rgba(2, 6, 23, 0.8);
            border-right: 1px solid var(--border-glass);
            backdrop-filter: blur(12px);
            display: flex; flex-direction: column; padding: 24px;
        }

        /* --- EDITOR CARDS --- */
        .row-container {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 16px; padding: 16px; margin-bottom: 20px;
            display: flex; flex-wrap: wrap; gap: 10px; position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .row-container:hover {
            background: rgba(31, 41, 55, 0.6);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        .key-chip {
            flex: 1; min-width: 130px;
            background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px; padding: 12px;
            position: relative; cursor: grab; overflow: hidden;
            transition: all 0.2s;
        }
        .key-chip::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.03), transparent);
            transform: translateX(-100%); transition: 0.5s;
        }
        .key-chip:hover {
            border-color: var(--primary);
            box-shadow: 0 0 15px var(--primary-glow), inset 0 0 10px rgba(99,102,241,0.1);
        }
        .key-chip:hover::before { transform: translateX(100%); }

        .key-code { font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; color: #818cf8; opacity: 0.9; }
        .key-title { font-size: 0.85rem; color: #f1f5f9; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .chip-actions {
            position: absolute; top: 6px; left: 6px; display: flex; gap: 4px; 
            opacity: 0; transform: translateY(5px); transition: 0.2s;
        }
        .key-chip:hover .chip-actions { opacity: 1; transform: translateY(0); }

        .action-icon {
            width: 20px; height: 20px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer; color: white;
            backdrop-filter: blur(4px);
        }

        /* --- STASH --- */
        .stash-zone {
            flex: 1; overflow-y: auto; 
            border: 2px dashed rgba(255,255,255,0.08);
            border-radius: 16px; padding: 12px; margin-top: 16px;
            background: rgba(0,0,0,0.2); transition: 0.3s;
            display: flex; flex-direction: column; gap: 8px;
        }
        .stash-zone:hover { border-color: rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); }
        .stash-zone.sortable-ghost { background: rgba(99,102,241,0.1); border-color: var(--primary); }
        
        .stash-item {
            background: #111827; border: 1px solid #1f2937;
            border-radius: 10px; padding: 10px; cursor: grab;
        }
        .stash-item:hover { border-color: #374151; }

        /* --- BUTTONS --- */
        .btn-modern {
            height: 40px; padding: 0 20px; border-radius: 12px;
            font-size: 0.9rem; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.3s; position: relative; overflow: hidden;
        }
        .btn-glow {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border: none;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        .btn-glow:hover { box-shadow: 0 6px 25px rgba(99, 102, 241, 0.6); transform: translateY(-1px); }
        .btn-glow:disabled { filter: grayscale(1); opacity: 0.5; cursor: not-allowed; box-shadow: none; }
        
        .btn-glass { background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid rgba(255,255,255,0.05); }
        .btn-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.1); }

        /* --- MOCKUP --- */
        .mockup-device {
            width: 330px; height: 700px;
            background: #000; border-radius: 50px;
            box-shadow: 
                0 0 0 6px #1f2024,
                0 0 0 10px #35363a,
                0 30px 60px -15px rgba(0,0,0,0.6);
            overflow: hidden; display: flex; flex-direction: column; position: relative;
        }
        .tg-container {
            background: #1c1c1e; padding: 6px; 
            border-top: 1px solid #111;
            min-height: 220px; display: flex; flex-col; justify-end;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
        
        @media (max-width: 1200px) {
            .studio-layout { grid-template-columns: 0 1fr 260px; }
            .panel-preview { display: none; }
        }
    </style>
</head>
<body>

    <div class="aurora-bg"></div>
    <div class="noise-layer"></div>

    <!-- HEADER -->
    <header class="studio-header">
        <div class="flex items-center gap-4">
            <div class="logo-box w-10 h-10 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-layer-group text-indigo-400 text-xl"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg tracking-tight text-white">Keyboard Studio</h1>
                <p class="text-[10px] text-indigo-300 uppercase tracking-widest opacity-70">Professional Editor</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-modern btn-glass text-xs">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
            <a href="keyboard.php?action=reset" onclick="return confirm('تنظیمات به حالت اولیه بازگردد؟')" class="btn-modern btn-glass text-red-400 hover:text-red-300">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
            <div class="w-px h-8 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="btn-modern btn-glow" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- MAIN LAYOUT -->
    <div class="studio-layout">
        
        <!-- RIGHT: PREVIEW (Column 1 in RTL) -->
        <div class="panel-preview">
            <div class="mb-6 flex items-center gap-2 px-4 py-1 rounded-full bg-white/5 border border-white/5 text-[10px] text-indigo-300 tracking-[0.2em] font-bold uppercase">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                Live Preview
            </div>
            
            <div class="mockup-device">
                <div class="dynamic-island absolute top-3 left-1/2 -translate-x-1/2 w-28 h-7 bg-black rounded-full z-20"></div>
                
                <!-- Telegram App Header -->
                <div class="bg-[#212121] pt-12 pb-3 px-4 flex items-center text-white border-b border-white/5 z-10">
                    <i class="fa-solid fa-arrow-right text-gray-400 ml-2 text-lg"></i>
                    <div class="flex-1 mr-3">
                        <div class="text-sm font-bold">ربات تلگرام</div>
                        <div class="text-[10px] text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <!-- Chat Body -->
                <div class="flex-1 bg-[#0f0f0f] relative overflow-hidden flex flex-col justify-end pb-4">
                    <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
                    
                    <div class="bg-[#2b5278] text-white p-3 rounded-2xl rounded-tr-none text-sm max-w-[85%] shadow-lg mr-auto ml-4 mb-2 relative z-0">
                        منوی ربات به صورت زیر نمایش داده می‌شود 👇
                    </div>
                </div>

                <!-- Keyboard Area (LTR Force) -->
                <div id="preview-render" class="tg-container" dir="ltr"></div>
            </div>
        </div>

        <!-- CENTER: EDITOR -->
        <div class="panel-editor custom-scrollbar">
            <div class="max-w-4xl mx-auto pb-32">
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-white mb-1">چیدمان کیبورد</h2>
                        <p class="text-sm text-slate-400">سطرها و دکمه‌ها را مدیریت کنید</p>
                    </div>
                    <div onclick="App.addRow()" class="px-4 py-2 rounded-lg bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs font-bold cursor-pointer hover:bg-indigo-500/20 transition flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> سطر جدید
                    </div>
                </div>

                <div id="editor-render">
                    <!-- Rows will be injected here -->
                </div>
            </div>
        </div>

        <!-- LEFT: STASH (Column 3 in RTL) -->
        <div class="panel-stash">
            <div class="flex items-center gap-3 mb-4 text-slate-200">
                <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-indigo-400">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm">انبار دکمه‌ها</h3>
                    <p class="text-[10px] text-slate-500">فضای ذخیره موقت</p>
                </div>
            </div>
            
            <p class="text-xs text-slate-500 mb-2 leading-relaxed opacity-70">
                دکمه‌های غیرفعال را اینجا رها کنید.
            </p>
            
            <div id="stash-render" class="stash-zone custom-scrollbar">
                <!-- Stashed Items -->
            </div>
        </div>

    </div>

    <!-- LOGIC -->
    <script>
        const App = {
            data: {
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                stash: <?php echo $currentStashJSON ?: '[]'; ?>,
                initialSnapshot: '',
                labels: {
                    'text_sell': '🛍 خرید سرویس',
                    'text_extend': '🔄 تمدید سرویس',
                    'text_usertest': '🔥 تست رایگان',
                    'text_wheel_luck': '🎰 گردونه شانس',
                    'text_Purchased_services': '👤 سرویس‌های من',
                    'accountwallet': '💳 کیف پول',
                    'text_affiliates': '🤝 همکاری در فروش',
                    'text_Tariff_list': '📋 لیست تعرفه‌ها',
                    'text_support': '🎧 پشتیبانی',
                    'text_help': '📚 راهنما'
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
                
                this.data.initialSnapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                
                // Enhanced SweetAlert Theme
                this.swal = Swal.mixin({
                    background: '#0f172a',
                    color: '#f8fafc',
                    confirmButtonColor: '#6366f1',
                    cancelButtonColor: '#ef4444',
                    customClass: { 
                        popup: 'border border-indigo-500/20 rounded-2xl shadow-2xl backdrop-blur-xl',
                        input: 'bg-slate-800 border-slate-700 text-white rounded-lg focus:ring-2 focus:ring-indigo-500'
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
                        <div class="flex flex-col items-center justify-center py-24 opacity-30 text-indigo-200">
                            <i class="fa-solid fa-layer-group text-6xl mb-4"></i>
                            <p class="tracking-wide">هیچ سطری وجود ندارد</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-container group';
                    rowEl.dataset.rowIdx = rIdx;
                    
                    // Row Handle
                    rowEl.innerHTML = `
                        <div class="absolute -left-7 top-1/2 -translate-y-1/2 text-slate-600 hover:text-indigo-400 cursor-grab px-2 row-handle opacity-0 group-hover:opacity-100 transition">
                            <i class="fa-solid fa-grip-vertical text-lg"></i>
                        </div>
                    `;

                    row.forEach((btn, bIdx) => {
                        rowEl.appendChild(this.createKeyElement(btn, rIdx, bIdx, 'main'));
                    });

                    // Inline Add Button
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-10 rounded-xl border border-dashed border-slate-600 flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:border-indigo-500 hover:bg-indigo-500/10 cursor-pointer transition opacity-40 hover:opacity-100';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Row if Empty
                    if (row.length === 0) {
                        const delRow = document.createElement('div');
                        delRow.className = 'w-full text-center text-[11px] text-red-400/80 py-3 cursor-pointer border border-dashed border-red-500/20 rounded-lg hover:bg-red-500/10 transition';
                        delRow.innerHTML = '<i class="fa-solid fa-trash-can mr-1"></i> حذف سطر خالی';
                        delRow.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delRow);
                    }

                    editor.appendChild(rowEl);
                });

                this.initSortable();
            },

            renderStash() {
                const { stash } = this.dom;
                stash.innerHTML = '';
                
                if (this.data.stash.length === 0) {
                    stash.innerHTML = `<div class="text-center py-12 opacity-20 text-xs text-white">خالی</div>`;
                }

                this.data.stash.forEach((btn, idx) => {
                    stash.appendChild(this.createKeyElement(btn, null, idx, 'stash'));
                });
                
                new Sortable(stash, {
                    group: 'shared-keys', animation: 200, ghostClass: 'sortable-ghost',
                    onEnd: () => this.rebuildData()
                });
            },

            createKeyElement(btn, rIdx, bIdx, type) {
                const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                const div = document.createElement('div');
                // Use different class for Stash vs Editor
                div.className = type === 'stash' ? 'stash-item group relative' : 'key-chip group';
                div.dataset.text = btn.text;
                
                const metaClass = type === 'stash' ? 'text-[10px] text-slate-500' : 'key-code';
                const titleClass = type === 'stash' ? 'text-xs text-slate-300' : 'key-title';
                
                div.innerHTML = `
                    <div class="flex justify-between items-start mb-1">
                        <span class="${metaClass}" title="${btn.text}">${btn.text.substring(0, 10)}${btn.text.length>10?'..':''}</span>
                    </div>
                    <div class="${titleClass}">${label}</div>
                    
                    <div class="chip-actions">
                        <div class="action-icon bg-blue-500 hover:bg-blue-400" onclick="App.editKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                        <div class="action-icon bg-red-500 hover:bg-red-400" onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                    </div>
                `;
                return div;
            },

            renderPreview() {
                const { preview } = this.dom;
                preview.innerHTML = '';
                
                this.data.keyboard.forEach(row => {
                    if (row.length === 0) return;
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'flex w-full gap-1 mb-1';
                    
                    row.forEach(btn => {
                        const btnDiv = document.createElement('div');
                        btnDiv.className = 'flex-1 bg-[#2c2c2e] text-white rounded-[5px] py-2 px-1 text-xs text-center truncate shadow-[0_1px_0_rgba(0,0,0,0.5)] cursor-default select-none';
                        btnDiv.innerText = this.data.labels[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    preview.appendChild(rowDiv);
                });
            },

            initSortable() {
                new Sortable(this.dom.editor, {
                    animation: 250, handle: '.row-handle', ghostClass: 'opacity-50',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                document.querySelectorAll('.row-container').forEach(el => {
                    new Sortable(el, {
                        group: 'shared-keys', animation: 200, draggable: '.key-chip', ghostClass: 'opacity-50',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            rebuildData() {
                // Rebuild Main
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-container').forEach(row => {
                    const btns = [];
                    row.querySelectorAll('.key-chip').forEach(el => btns.push({ text: el.dataset.text }));
                    newRows.push(btns);
                });
                this.data.keyboard = newRows;

                // Rebuild Stash
                const newStash = [];
                this.dom.stash.querySelectorAll('.stash-item').forEach(el => newStash.push({ text: el.dataset.text }));
                this.data.stash = newStash;

                this.render();
            },

            checkChanges() {
                const current = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                const isDirty = current !== this.data.initialSnapshot;
                const { saveBtn } = this.dom;
                
                if (isDirty) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                    saveBtn.classList.remove('grayscale', 'opacity-50');
                } else {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                    saveBtn.classList.add('grayscale', 'opacity-50');
                }
            },

            // Operations
            addRow() {
                this.data.keyboard.push([{text: 'text_new'}]);
                this.render();
                setTimeout(() => document.querySelector('.panel-editor').scrollTop = 99999, 50);
            },

            deleteRow(idx) {
                this.data.keyboard.splice(idx, 1);
                this.render();
            },

            async addKeyToRow(rIdx) {
                const { value: text } = await this.swal.fire({
                    title: 'افزودن دکمه جدید',
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

                const payload = { keyboard: this.data.keyboard, stash: this.data.stash };

                fetch('keyboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.data.initialSnapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                        this.checkChanges();
                        const Toast = Swal.mixin({
                            toast: true, position: 'top-end', showConfirmButton: false, 
                            timer: 3000, background: '#10b981', color: '#fff'
                        });
                        Toast.fire({icon: 'success', title: 'ذخیره شد'});
                    }
                })
                .catch(err => {
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