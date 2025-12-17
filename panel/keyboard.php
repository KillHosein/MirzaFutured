<?php
/**
 * Keyboard Editor - Aurora Pro Edition (Refined)
 * Fixed UX issues, Improved Drag & Drop, Better Responsive Design
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
            [["text" => "accountwallet"]],
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
    
    // Fallback
    if ($currentKeyboardJSON == '[]' || $currentKeyboardJSON == 'null') {
         $def = [[["text" => "text_start"]]];
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
            --border-glass: rgba(255, 255, 255, 0.06);
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
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.08), transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.08), transparent 40%);
            pointer-events: none;
        }
        
        /* --- Layout --- */
        .app-layout {
            display: grid; 
            /* Right (Preview) | Center (Editor) | Left (Stash) */
            grid-template-columns: 360px 1fr 280px; 
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
            background: rgba(30, 41, 59, 0.2); 
            border-right: 1px solid var(--border-glass);
            backdrop-filter: blur(20px);
            padding: 24px;
        }

        /* --- Drag & Drop Elements --- */
        .row-wrapper {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            padding: 12px; margin-bottom: 16px;
            min-height: 80px; /* Important for empty dropzones */
            display: flex; flex-wrap: wrap; gap: 8px;
            position: relative; transition: all 0.2s;
        }
        .row-wrapper:hover {
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(30, 41, 59, 0.6);
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.3);
        }
        
        .drag-handle {
            position: absolute; left: -28px; top: 50%; transform: translateY(-50%);
            padding: 8px; cursor: grab; color: #64748b; opacity: 0; transition: 0.2s;
        }
        .row-wrapper:hover .drag-handle { opacity: 1; }

        .key-btn {
            flex: 1; min-width: 140px;
            background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01));
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 10px; padding: 10px 14px;
            cursor: grab; position: relative; overflow: hidden;
            transition: all 0.2s;
        }
        .key-btn:hover {
            border-color: var(--primary);
            box-shadow: inset 0 0 20px rgba(99, 102, 241, 0.1);
        }
        
        .key-code { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #818cf8; opacity: 0.8; }
        .key-label { font-size: 13px; font-weight: 500; color: #e2e8f0; margin-top: 2px; }

        .btn-actions {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(2px);
            display: flex; align-items: center; justify-content: center; gap: 8px;
            opacity: 0; transition: 0.2s;
        }
        .key-btn:hover .btn-actions { opacity: 1; }

        .action-circle {
            width: 28px; height: 28px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 12px; color: white;
            transform: scale(0.8); transition: 0.2s;
        }
        .action-circle:hover { transform: scale(1.1); }
        .act-edit { background: #3b82f6; }
        .act-del { background: #ef4444; }

        /* --- Stash Area --- */
        .stash-container {
            flex: 1; overflow-y: auto; 
            border: 2px dashed rgba(255,255,255,0.1);
            border-radius: 14px; padding: 12px;
            background: rgba(0,0,0,0.1); margin-top: 20px;
            display: flex; flex-direction: column; gap: 8px;
        }
        .stash-item {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 8px; padding: 10px; cursor: grab;
        }

        /* --- Phone Preview --- */
        .mockup {
            width: 320px; height: 680px;
            background: #000; border-radius: 45px;
            box-shadow: 0 0 0 8px #1e1e1e, 0 30px 60px rgba(0,0,0,0.5);
            display: flex; flex-direction: column; overflow: hidden; position: relative;
        }
        .dynamic-island {
            width: 100px; height: 26px; background: #000;
            border-radius: 100px; position: absolute; top: 10px; left: 50%; transform: translateX(-50%);
            z-index: 20;
        }
        .tg-header {
            background: #212121; padding: 40px 16px 10px; color: white;
            display: flex; align-items: center; border-bottom: 1px solid #111;
        }
        .tg-body {
            flex: 1; background: #0f0f0f; 
            background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 16px 16px;
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 10px;
        }
        .tg-kb-area {
            background: #1c1c1e; padding: 6px; min-height: 200px;
            border-top: 1px solid #000;
        }
        /* Telegram Button Style */
        .tg-btn {
            background: #2c2c2e; color: white;
            border-radius: 6px; padding: 10px 4px; margin: 3px;
            font-size: 13px; text-align: center; flex: 1;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* --- Scrollbars --- */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* --- Responsive --- */
        @media (max-width: 1280px) {
            .app-layout { grid-template-columns: 0 1fr 260px; }
            .panel-preview { display: none; } /* Hide Preview on Tablet */
        }
        @media (max-width: 768px) {
            .app-layout { grid-template-columns: 1fr; grid-template-rows: 1fr auto; }
            .panel-stash { 
                height: 200px; border-right: none; border-top: 1px solid var(--border-glass); 
                order: 2;
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
            <div class="w-9 h-9 rounded-lg bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20">
                <i class="fa-solid fa-keyboard text-indigo-400"></i>
            </div>
            <div>
                <h1 class="font-bold text-white text-lg tracking-tight">Keyboard Studio</h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="h-9 px-4 rounded-lg bg-white/5 hover:bg-white/10 flex items-center gap-2 text-sm text-slate-400 transition">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:inline">خروج</span>
            </a>
            <a href="keyboard.php?action=reset" onclick="return confirm('همه تغییرات از بین می‌روند. ادامه می‌دهید؟')" class="h-9 w-9 rounded-lg bg-red-500/10 hover:bg-red-500/20 flex items-center justify-center text-red-400 transition">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
            <div class="w-px h-6 bg-white/10 mx-1"></div>
            <button onclick="App.save()" id="btn-save" class="h-9 px-5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold flex items-center gap-2 shadow-lg shadow-indigo-500/20 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره</span>
            </button>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="app-layout">
        
        <!-- RIGHT: Preview (Desktop Only) -->
        <div class="panel panel-preview">
            <div class="mb-5 flex items-center gap-2 text-[10px] font-bold text-indigo-300 uppercase tracking-widest bg-indigo-500/5 px-3 py-1 rounded-full border border-indigo-500/10">
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span> Live Preview
            </div>
            
            <div class="mockup">
                <div class="dynamic-island"></div>
                <!-- Telegram Header -->
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right text-gray-400 ml-2"></i>
                    <div class="flex-1 mr-2">
                        <div class="text-sm font-bold">ربات تلگرام</div>
                        <div class="text-[10px] text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>
                <!-- Telegram Body -->
                <div class="tg-body">
                    <div class="bg-[#2b5278] text-white p-3 rounded-2xl rounded-tr-none text-sm max-w-[85%] shadow-md mr-auto ml-3 mb-2">
                        کیبورد شما به این صورت نمایش داده می‌شود 👇
                    </div>
                </div>
                <!-- Keyboard (Force LTR for correct Telegram Layout) -->
                <div id="preview-render" class="tg-kb-area flex flex-col justify-end" dir="ltr"></div>
            </div>
        </div>

        <!-- CENTER: Editor -->
        <div class="panel panel-editor custom-scrollbar">
            <div class="max-w-4xl mx-auto pb-32">
                <div class="flex justify-between items-end mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-white mb-1">چیدمان دکمه‌ها</h2>
                        <p class="text-xs text-slate-400">دکمه‌ها را مرتب کنید یا به انبار منتقل کنید</p>
                    </div>
                    <button onclick="App.addRow()" class="h-9 px-4 rounded-lg border border-dashed border-indigo-500/30 text-indigo-300 text-xs hover:bg-indigo-500/10 transition">
                        <i class="fa-solid fa-plus ml-1"></i> سطر جدید
                    </button>
                </div>

                <div id="editor-render" class="flex flex-col gap-4"></div>
            </div>
        </div>

        <!-- LEFT: Stash -->
        <div class="panel panel-stash">
            <div class="flex items-center gap-3 mb-4 text-slate-200">
                <div class="w-8 h-8 rounded bg-slate-800 flex items-center justify-center text-indigo-400">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <h3 class="font-bold text-sm">انبار دکمه‌ها</h3>
            </div>
            
            <p class="text-[11px] text-slate-500 mb-2 leading-5">
                دکمه‌هایی که موقتاً لازم ندارید را اینجا نگه دارید.
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
                    customClass: { popup: 'rounded-xl border border-white/10' }
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
                        <div class="flex flex-col items-center justify-center py-20 opacity-30 text-indigo-300">
                            <i class="fa-solid fa-layer-group text-5xl mb-4"></i>
                            <p>هیچ سطری وجود ندارد</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-wrapper group';
                    rowEl.dataset.rowIdx = rIdx;
                    
                    // Drag Handle for Row
                    rowEl.innerHTML = `<i class="fa-solid fa-grip-vertical drag-handle row-handle"></i>`;

                    // Render Keys in Row
                    row.forEach((btn, bIdx) => {
                        rowEl.appendChild(this.createKeyElement(btn, rIdx, bIdx, 'main'));
                    });

                    // Controls for Row
                    const controls = document.createElement('div');
                    controls.className = 'flex items-center gap-2 ml-auto pl-2 border-l border-white/5';
                    
                    // Add Button Logic (Max 8)
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-8 h-8 rounded-lg border border-dashed border-slate-500 flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:border-indigo-400 cursor-pointer transition';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Row Logic
                    if (row.length === 0) {
                        const delBtn = document.createElement('div');
                        delBtn.className = 'w-full text-center text-xs text-red-400 py-3 cursor-pointer border border-dashed border-red-500/20 rounded bg-red-500/5 hover:bg-red-500/10 transition';
                        delBtn.innerHTML = 'حذف سطر خالی';
                        delBtn.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delBtn);
                    }

                    editor.appendChild(rowEl);
                });

                // Init Sortable for Rows
                new Sortable(editor, {
                    animation: 200, handle: '.row-handle', ghostClass: 'opacity-50',
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
                        animation: 150, draggable: '.key-btn', ghostClass: 'opacity-50',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            renderStash() {
                const { stash } = this.dom;
                stash.innerHTML = '';
                
                if (this.data.stash.length === 0) {
                    stash.innerHTML = `<div class="text-center py-8 opacity-20 text-xs text-white">خالی</div>`;
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
                // Different styling for Stash vs Editor can be applied here
                el.className = type === 'stash' ? 'stash-item key-btn' : 'key-btn';
                el.dataset.text = btn.text; // Important for rebuilding
                
                el.innerHTML = `
                    <div class="key-code">${btn.text.substring(0, 12)}${btn.text.length>12?'...':''}</div>
                    <div class="key-label truncate">${label}</div>
                    
                    <div class="btn-actions">
                        <div class="action-circle act-edit" onclick="App.editKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                        <div class="action-circle act-del" onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-trash"></i></div>
                    </div>
                `;
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
                    rowEl.querySelectorAll('.key-btn').forEach(btnEl => {
                        btns.push({ text: btnEl.dataset.text });
                    });
                    newRows.push(btns);
                });
                this.data.keyboard = newRows;

                // 2. Rebuild Stash
                const newStash = [];
                this.dom.stash.querySelectorAll('.key-btn').forEach(btnEl => {
                    newStash.push({ text: btnEl.dataset.text });
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