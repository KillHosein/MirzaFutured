<?php
/**
 * Keyboard Editor - Aurora Pro Edition (Refined Layout)
 * Wider Stash, Better Spacing, Cleaner UI
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
    // Full Default Keyboard Layout
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
    <title>ویرایشگر کیبورد | نسخه حرفه‌ای</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-deep: #020617;
            --bg-glass: rgba(15, 23, 42, 0.7);
            --border-glass: rgba(255, 255, 255, 0.08);
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.25);
            --danger: #ef4444;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: #f1f5f9;
            height: 100vh; overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* --- Ambient Background --- */
        .ambient-glow {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.1), transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(168, 85, 247, 0.1), transparent 50%);
            pointer-events: none;
        }
        
        /* --- Layout --- */
        .app-layout {
            display: grid; 
            /* Right (Preview) | Center (Editor) | Left (Stash) */
            /* Increased Stash width to 340px, Preview to 340px to balance */
            grid-template-columns: 340px 1fr 340px; 
            height: calc(100vh - 70px);
        }

        /* --- Panels --- */
        .panel { 
            position: relative; 
            display: flex; flex-direction: column;
        }
        .panel-preview { 
            background: rgba(15, 23, 42, 0.3); 
            border-left: 1px solid var(--border-glass);
            align-items: center; justify-content: center;
        }
        .panel-editor { 
            background: transparent; 
            overflow-y: auto; padding: 40px;
        }
        .panel-stash { 
            background: rgba(30, 41, 59, 0.25); 
            border-right: 1px solid var(--border-glass);
            backdrop-filter: blur(20px);
            padding: 30px; /* Increased padding */
        }

        /* --- Drag & Drop Elements --- */
        .row-wrapper {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 16px; /* Increased padding */
            margin-bottom: 24px; /* Increased margin */
            min-height: 90px;
            display: flex; flex-wrap: wrap; gap: 12px; /* Increased gap */
            position: relative; transition: all 0.2s;
        }
        .row-wrapper:hover {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(30, 41, 59, 0.7);
            box-shadow: 0 15px 40px -10px rgba(0,0,0,0.4);
        }
        
        .drag-handle {
            position: absolute; left: -32px; top: 50%; transform: translateY(-50%);
            padding: 8px; cursor: grab; color: #64748b; opacity: 0; transition: 0.2s; font-size: 1.2rem;
        }
        .row-wrapper:hover .drag-handle { opacity: 0.6; }
        .drag-handle:hover { opacity: 1 !important; color: white; }

        .key-btn {
            flex: 1 0 130px; /* Allow grow, min width 130 */
            background: linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.01));
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; padding: 14px 16px;
            cursor: grab; position: relative; overflow: hidden;
            transition: all 0.2s;
            display: flex; flex-direction: column; justify-content: center;
        }
        .key-btn:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
            box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.2);
            transform: translateY(-2px);
        }
        .key-btn:active { transform: scale(0.98); }
        
        .key-code { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #a5b4fc; opacity: 0.9; margin-bottom: 4px; }
        .key-label { font-size: 14px; font-weight: 500; color: #f1f5f9; }

        .btn-actions {
            position: absolute; inset: 0;
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center; gap: 10px;
            opacity: 0; transition: 0.2s;
        }
        .key-btn:hover .btn-actions { opacity: 1; }

        .action-circle {
            width: 32px; height: 32px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 13px; color: white;
            transition: 0.2s;
        }
        .action-circle:hover { transform: scale(1.1); }
        .act-edit { background: #3b82f6; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .act-del { background: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }

        /* --- Stash Area --- */
        .stash-container {
            flex: 1; overflow-y: auto; 
            border: 2px dashed rgba(255,255,255,0.1);
            border-radius: 18px; padding: 16px;
            background: rgba(0,0,0,0.15); margin-top: 24px;
            display: flex; flex-direction: column; gap: 10px;
            transition: 0.2s;
        }
        .stash-container:hover { border-color: rgba(255,255,255,0.2); background: rgba(0,0,0,0.2); }
        
        .stash-item {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 12px; padding: 12px 16px; cursor: grab;
            display: flex; justify-content: space-between; align-items: center;
        }
        .stash-item:hover { border-color: #64748b; }

        /* --- Phone Preview --- */
        .mockup {
            width: 320px; height: 700px;
            background: #000; border-radius: 50px;
            box-shadow: 0 0 0 8px #1e1e1e, 0 30px 60px rgba(0,0,0,0.6);
            display: flex; flex-direction: column; overflow: hidden; position: relative;
        }
        .dynamic-island {
            width: 110px; height: 30px; background: #000;
            border-radius: 20px; position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            z-index: 20;
        }
        .tg-header {
            background: #212121; padding: 48px 16px 12px; color: white;
            display: flex; align-items: center; border-bottom: 1px solid #111;
        }
        .tg-body {
            flex: 1; background: #0f0f0f; 
            background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 20px 20px;
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 12px;
        }
        .tg-kb-area {
            background: #1c1c1e; padding: 6px; min-height: 220px;
            border-top: 1px solid #000;
        }
        .tg-btn {
            background: #2c2c2e; color: white;
            border-radius: 6px; padding: 12px 4px; margin: 3px;
            font-size: 13px; text-align: center; flex: 1;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* --- Scrollbars --- */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* --- Responsive --- */
        @media (max-width: 1400px) {
            .app-layout { grid-template-columns: 0 1fr 320px; }
            .panel-preview { display: none; }
        }
        @media (max-width: 900px) {
            .app-layout { grid-template-columns: 1fr; grid-template-rows: 1fr auto; }
            .panel-stash { 
                height: 250px; border-right: none; border-top: 1px solid var(--border-glass); 
                order: 2; width: 100%;
            }
            .panel-editor { order: 1; padding: 20px; }
        }
    </style>
</head>
<body>

    <div class="ambient-glow"></div>

    <!-- Header -->
    <header class="h-[70px] bg-[#0f172a]/80 backdrop-blur-md border-b border-white/5 flex items-center justify-between px-8 z-50">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center border border-white/10 shadow-lg shadow-indigo-500/10">
                <i class="fa-solid fa-layer-group text-indigo-400"></i>
            </div>
            <div>
                <h1 class="font-bold text-white text-lg tracking-tight">Keyboard Studio</h1>
                <span class="text-[10px] text-indigo-300 uppercase tracking-widest opacity-60">Professional</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="h-10 px-4 rounded-xl bg-white/5 hover:bg-white/10 flex items-center gap-2 text-sm text-slate-300 transition">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:inline">خروج</span>
            </a>
            <a href="keyboard.php?action=reset" onclick="return confirm('همه تغییرات از بین می‌روند. ادامه می‌دهید؟')" class="h-10 w-10 rounded-xl bg-red-500/10 hover:bg-red-500/20 flex items-center justify-center text-red-400 transition" title="بازنشانی">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
            <div class="w-px h-6 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="h-10 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/20 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="app-layout">
        
        <!-- RIGHT: Preview -->
        <div class="panel panel-preview">
            <div class="mb-6 flex items-center gap-2 text-[10px] font-bold text-indigo-300 uppercase tracking-widest bg-indigo-500/5 px-4 py-1.5 rounded-full border border-indigo-500/10">
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span> Live Preview
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
                    <div class="bg-[#2b5278] text-white p-3 rounded-2xl rounded-tr-none text-sm max-w-[85%] shadow-md mr-auto ml-4 mb-2">
                        کیبورد شما به این صورت نمایش داده می‌شود 👇
                    </div>
                </div>
                <div id="preview-render" class="tg-kb-area flex flex-col justify-end" dir="ltr"></div>
            </div>
        </div>

        <!-- CENTER: Editor -->
        <div class="panel panel-editor custom-scrollbar">
            <div class="max-w-4xl mx-auto pb-40">
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-white mb-2">چیدمان دکمه‌ها</h2>
                        <p class="text-sm text-slate-400">سطرها را مدیریت کنید و دکمه‌ها را مرتب نمایید</p>
                    </div>
                    <button onclick="App.addRow()" class="h-10 px-5 rounded-xl border border-dashed border-indigo-500/30 text-indigo-300 text-xs font-bold hover:bg-indigo-500/10 hover:border-indigo-500/50 transition flex items-center gap-2">
                        <i class="fa-solid fa-plus text-sm"></i> سطر جدید
                    </button>
                </div>

                <div id="editor-render" class="flex flex-col gap-6"></div>
                
                <div class="mt-12 py-12 border-2 border-dashed border-white/5 rounded-3xl flex flex-col items-center justify-center text-slate-600">
                   <i class="fa-solid fa-arrow-up mb-2"></i>
                   <span class="text-sm">انتهای لیست کیبورد</span>
                </div>
            </div>
        </div>

        <!-- LEFT: Stash -->
        <div class="panel panel-stash">
            <div class="flex items-center gap-3 mb-6 text-slate-200">
                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-indigo-400 border border-slate-700">
                    <i class="fa-solid fa-box-archive text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base">انبار دکمه‌ها</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">فضای ذخیره موقت</p>
                </div>
            </div>
            
            <p class="text-xs text-slate-400/80 mb-2 leading-relaxed bg-white/5 p-3 rounded-lg border border-white/5">
                <i class="fa-regular fa-lightbulb ml-1 text-yellow-500/70"></i>
                دکمه‌هایی که موقتاً لازم ندارید را اینجا رها کنید تا بعداً استفاده کنید.
            </p>
            
            <div id="stash-render" class="stash-container custom-scrollbar"></div>
        </div>

    </div>

    <!-- Application Logic -->
    <script>
        const App = {
            data: {
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                stash: <?php echo $currentStashJSON ?: '[]'; ?>,
                snapshot: '',
                // Labels Dictionary
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
                // Ensure arrays
                if (!Array.isArray(this.data.keyboard)) this.data.keyboard = [];
                if (!Array.isArray(this.data.stash)) this.data.stash = [];
                
                // Store initial state for change detection
                this.data.snapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                
                // SweetAlert Config
                this.swal = Swal.mixin({
                    background: '#1e293b', color: '#f8fafc',
                    confirmButtonColor: '#6366f1', cancelButtonColor: '#ef4444',
                    customClass: { popup: 'rounded-2xl border border-white/10' }
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
                        <div class="flex flex-col items-center justify-center py-24 opacity-40 text-indigo-300 border-2 border-dashed border-indigo-500/20 rounded-3xl">
                            <i class="fa-solid fa-layer-group text-6xl mb-6"></i>
                            <p class="text-lg">هنوز سطری اضافه نکرده‌اید</p>
                            <button onclick="App.addRow()" class="mt-4 text-sm text-indigo-400 hover:underline">ایجاد اولین سطر</button>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-wrapper group';
                    rowEl.dataset.rowIdx = rIdx;
                    
                    // Drag Handle
                    rowEl.innerHTML = `<i class="fa-solid fa-grip-vertical drag-handle row-handle"></i>`;

                    // Render Keys in Row
                    row.forEach((btn, bIdx) => {
                        rowEl.appendChild(this.createKeyElement(btn, rIdx, bIdx, 'main'));
                    });

                    // Add Button Logic
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-10 h-auto min-h-[50px] rounded-xl border-2 border-dashed border-slate-600 flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:border-indigo-400 hover:bg-indigo-400/5 cursor-pointer transition opacity-60 hover:opacity-100';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Row Logic
                    if (row.length === 0) {
                        const delBtn = document.createElement('div');
                        delBtn.className = 'w-full text-center text-xs text-red-400 py-3 cursor-pointer border border-dashed border-red-500/20 rounded-xl bg-red-500/5 hover:bg-red-500/10 transition';
                        delBtn.innerHTML = '<i class="fa-solid fa-trash-can ml-1"></i> حذف سطر خالی';
                        delBtn.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delBtn);
                    }

                    editor.appendChild(rowEl);
                });

                // Init Sortable for Rows
                new Sortable(editor, {
                    animation: 250, handle: '.row-handle', ghostClass: 'opacity-50',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                // Init Sortable for Keys inside Rows
                document.querySelectorAll('.row-wrapper').forEach(el => {
                    new Sortable(el, {
                        group: 'shared-keys', // Connects with Stash
                        animation: 150, draggable: '.key-btn', ghostClass: 'opacity-40',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            renderStash() {
                const { stash } = this.dom;
                stash.innerHTML = '';
                
                if (this.data.stash.length === 0) {
                    stash.innerHTML = `
                        <div class="text-center py-12 opacity-30 text-xs text-slate-400 flex flex-col items-center">
                            <i class="fa-regular fa-folder-open text-2xl mb-2"></i>
                            انبار خالی است
                        </div>`;
                }

                this.data.stash.forEach((btn, idx) => {
                    stash.appendChild(this.createKeyElement(btn, null, idx, 'stash'));
                });

                new Sortable(stash, {
                    group: 'shared-keys', animation: 150, ghostClass: 'opacity-50',
                    onEnd: () => this.rebuildData()
                });
            },

            createKeyElement(btn, rIdx, bIdx, type) {
                const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                const el = document.createElement('div');
                
                if (type === 'stash') {
                    // Stash Item Style
                    el.className = 'stash-item';
                    el.dataset.text = btn.text;
                    el.innerHTML = `
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-indigo-400 text-xs font-mono border border-slate-700">
                                <i class="fa-solid fa-code"></i>
                            </div>
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-xs text-slate-200 truncate font-medium">${label}</span>
                                <span class="text-[10px] text-slate-500 truncate font-mono">${btn.text}</span>
                            </div>
                        </div>
                        <div class="flex gap-1 opacity-0 hover:opacity-100 transition-opacity">
                            <button onclick="App.editKey('${type}', ${rIdx}, ${bIdx})" class="w-6 h-6 rounded bg-blue-500 hover:bg-blue-400 flex items-center justify-center text-[10px] text-white"><i class="fa-solid fa-pen"></i></button>
                            <button onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})" class="w-6 h-6 rounded bg-red-500 hover:bg-red-400 flex items-center justify-center text-[10px] text-white"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    `;
                    // Add hover effect for showing buttons in stash
                    el.onmouseenter = () => el.querySelector('.flex.gap-1').classList.remove('opacity-0');
                    el.onmouseleave = () => el.querySelector('.flex.gap-1').classList.add('opacity-0');
                } else {
                    // Editor Item Style
                    el.className = 'key-btn';
                    el.dataset.text = btn.text;
                    el.innerHTML = `
                        <div class="key-code">${btn.text.substring(0, 14)}${btn.text.length>14?'..':''}</div>
                        <div class="key-label truncate">${label}</div>
                        
                        <div class="btn-actions">
                            <div class="action-circle act-edit" onclick="App.editKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="action-circle act-del" onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-trash"></i></div>
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
                    rowDiv.className = 'flex w-full gap-1 mb-1'; // Telegram gap style
                    
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
                // 1. Rebuild Keyboard
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-wrapper').forEach(rowEl => {
                    const btns = [];
                    // Select both key-btn (editor) and stash-item (if dropped from stash)
                    const items = rowEl.querySelectorAll('[data-text]');
                    items.forEach(item => {
                         // Only add if it's a direct child (handle nested sortables if any, though unlikely here)
                         btns.push({ text: item.dataset.text });
                    });
                    newRows.push(btns);
                });
                this.data.keyboard = newRows;

                // 2. Rebuild Stash
                const newStash = [];
                const stashItems = this.dom.stash.querySelectorAll('[data-text]');
                stashItems.forEach(item => {
                    newStash.push({ text: item.dataset.text });
                });
                this.data.stash = newStash;

                this.render();
            },

            checkChanges() {
                const current = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                const isDirty = current !== this.data.snapshot;
                const { saveBtn } = this.dom;
                
                if (isDirty) {
                    saveBtn.disabled = false;
                    saveBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                } else {
                    saveBtn.disabled = true;
                    saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شده';
                }
            },

            // --- Actions ---
            addRow() {
                this.data.keyboard.push([]); // Add empty row
                this.render();
                // Scroll to bottom
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
                        Toast.fire({icon: 'success', title: 'ذخیره شد'});
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