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
        // بررسی ساختار جدید (شامل انبار) یا ساختار قدیم
        if (isset($inputData['keyboard'])) {
            $saveData = $inputData; // فرمت جدید: {keyboard: [], stash: []}
        } else {
            $saveData = ['keyboard' => $inputData, 'stash' => []]; // فرمت قدیم
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
            
            // Extract Keyboard
            if (isset($decoded['keyboard'])) {
                $currentKeyboardJSON = json_encode($decoded['keyboard']);
            } elseif (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
                // Legacy format support
                $currentKeyboardJSON = json_encode($decoded);
            }

            // Extract Stash
            if (isset($decoded['stash'])) {
                $currentStashJSON = json_encode($decoded['stash']);
            }
        }
    }
    
    // Fallback data
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
    <title>مدیریت کیبورد | پنل حرفه‌ای</title>
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- CORE VARIABLES --- */
        :root {
            --bg-deep: #0f172a;
            --accent-primary: #6366f1; /* Indigo 500 */
            --accent-secondary: #8b5cf6; /* Violet 500 */
            --accent-glow: rgba(99, 102, 241, 0.4);
            
            --glass-bg: rgba(30, 41, 59, 0.4);
            --glass-border: rgba(255, 255, 255, 0.08);
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* --- BACKGROUND --- */
        .ambient-light {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: radial-gradient(circle at 15% 50%, rgba(99, 102, 241, 0.15), transparent 25%);
            filter: blur(40px);
        }

        /* --- HEADER --- */
        .glass-header {
            height: 70px; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border); display: flex; align-items: center; 
            justify-content: space-between; padding: 0 32px; z-index: 50;
        }

        /* --- LAYOUT GRID --- */
        .main-layout {
            display: grid; 
            /* Grid: Editor (Right/1fr) | Preview (Center/360px) | Stash (Left/240px) */
            grid-template-columns: 1fr 380px 240px; 
            height: calc(100vh - 70px);
        }

        /* --- PANELS --- */
        .editor-panel { flex: 1; overflow-y: auto; padding: 40px; position: relative; }
        .preview-panel { 
            background: rgba(15, 23, 42, 0.4); border-right: 1px solid var(--glass-border);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .stash-panel {
            background: rgba(10, 15, 25, 0.6); border-right: 1px solid var(--glass-border);
            display: flex; flex-direction: column; padding: 20px;
        }

        /* --- EDITOR COMPONENTS --- */
        .row-card {
            background: var(--glass-bg); border: 1px solid var(--glass-border);
            border-radius: 16px; padding: 12px; margin-bottom: 16px;
            display: flex; flex-wrap: wrap; gap: 8px; position: relative;
            transition: all 0.2s;
        }
        .row-card:hover { border-color: rgba(99, 102, 241, 0.3); background: rgba(30, 41, 59, 0.6); }

        .key-item {
            flex: 1; min-width: 140px;
            background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 10px; padding: 10px 14px; position: relative; cursor: grab;
            transition: all 0.2s;
        }
        .key-item:hover { background: rgba(99, 102, 241, 0.08); border-color: rgba(99, 102, 241, 0.4); }
        
        .key-code { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #a5b4fc; }
        .key-title { font-size: 0.85rem; color: #e2e8f0; font-weight: 500; }

        .key-actions {
            position: absolute; top: 6px; left: 6px; display: flex; gap: 4px; opacity: 0; transition: 0.2s;
        }
        .key-item:hover .key-actions { opacity: 1; }

        /* --- STASH STYLES --- */
        .stash-dropzone {
            flex: 1; overflow-y: auto; border: 2px dashed rgba(255,255,255,0.1);
            border-radius: 12px; padding: 10px; margin-top: 15px;
            display: flex; flex-direction: column; gap: 8px;
            background: rgba(0,0,0,0.2); transition: 0.2s;
        }
        .stash-dropzone.sortable-ghost { background: rgba(99,102,241,0.1); border-color: var(--accent-primary); }
        
        /* Stashed Item Style (smaller/simpler) */
        .stash-dropzone .key-item {
            min-width: auto; width: 100%; flex: none;
            background: #1e1e24; border-color: #2d2d30;
        }

        /* --- BUTTONS --- */
        .btn { padding: 8px 20px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .btn-primary { background: linear-gradient(90deg, #6366f1, #8b5cf6); color: white; box-shadow: 0 0 15px rgba(99,102,241,0.4); }
        .btn-ghost { color: #94a3b8; background: rgba(255,255,255,0.05); }

        /* --- PHONE MOCKUP --- */
        .phone-frame {
            width: 320px; height: 680px; background: #000; border-radius: 40px; border: 6px solid #2d2d30;
            overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .tg-key { background: #2c2c2e; color: white; border-radius: 6px; padding: 10px 4px; font-size: 0.8rem; text-align: center; margin: 2px; flex: 1; }

        @media (max-width: 1200px) {
            .main-layout { grid-template-columns: 1fr 340px 0; }
            .stash-panel { display: none; } /* Hide stash on smaller screens or make it toggleable */
        }
    </style>
</head>
<body>

    <div class="ambient-light"></div>

    <!-- HEADER -->
    <header class="glass-header">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-keyboard text-indigo-400 text-2xl"></i>
            <span class="font-bold text-white text-lg">ویرایشگر کیبورد</span>
        </div>

        <div class="flex items-center gap-2">
            <a href="index.php" class="btn btn-ghost">خروج</a>
            <a href="keyboard.php?action=reset" onclick="return confirm('بازنشانی؟')" class="btn btn-ghost text-red-400"><i class="fa-solid fa-rotate-left"></i></a>
            <div class="w-px h-6 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="btn btn-primary" disabled>
                <i class="fa-regular fa-floppy-disk"></i> ذخیره تغییرات
            </button>
        </div>
    </header>

    <!-- GRID LAYOUT -->
    <div class="main-layout">
        
        <!-- COL 1: EDITOR (Right) -->
        <div class="editor-panel">
            <div class="max-w-4xl mx-auto pb-24">
                <div class="flex justify-between items-end mb-6">
                    <h2 class="text-xl font-bold text-white">چیدمان فعال</h2>
                    <span class="text-xs text-slate-400">کشیدن و رها کردن برای مرتب‌سازی</span>
                </div>

                <div id="editor-render"></div>
                
                <div onclick="App.addRow()" class="w-full py-5 border-2 border-dashed border-white/10 rounded-xl flex items-center justify-center text-slate-400 cursor-pointer hover:border-indigo-500 hover:text-indigo-400 hover:bg-indigo-500/5 transition mt-8">
                    <i class="fa-solid fa-plus text-lg ml-2"></i> افزودن سطر جدید
                </div>
            </div>
        </div>

        <!-- COL 2: PREVIEW (Center) -->
        <div class="preview-panel">
            <div class="mb-4 text-xs font-bold text-indigo-300 tracking-widest opacity-70">LIVE PREVIEW</div>
            <div class="phone-frame">
                <div class="bg-[#212121] p-3 flex items-center text-white border-b border-black">
                    <i class="fa-solid fa-arrow-right text-gray-400 ml-3"></i>
                    <div class="flex-1 text-sm font-bold">ربات تلگرام</div>
                </div>
                <div class="flex-1 bg-[#0f0f0f] relative overflow-hidden">
                    <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
                    <div class="absolute bottom-4 right-4 bg-[#2b5278] text-white p-3 rounded-xl rounded-tr-none text-sm max-w-[80%] shadow-lg">
                        منوی ربات به صورت زیر است 👇
                    </div>
                </div>
                <div id="preview-render" class="bg-[#1c1c1e] p-2 min-h-[200px] flex flex-col justify-end border-t border-black"></div>
            </div>
        </div>

        <!-- COL 3: STASH (Left) -->
        <div class="stash-panel">
            <div class="flex items-center gap-2 mb-2 text-slate-300">
                <i class="fa-solid fa-box-archive text-indigo-400"></i>
                <h3 class="font-bold text-sm">انبار دکمه‌ها</h3>
            </div>
            <p class="text-[11px] text-slate-500 mb-2 leading-5">
                دکمه‌هایی که نمی‌خواهید نمایش داده شوند را اینجا رها کنید. (حذف نمی‌شوند)
            </p>
            
            <div id="stash-render" class="stash-dropzone">
                <!-- Stashed Items -->
            </div>
        </div>

    </div>

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
                
                // Snapshot includes stash now
                this.data.initialSnapshot = JSON.stringify({k: this.data.keyboard, s: this.data.stash});
                
                this.swal = Swal.mixin({
                    background: '#1e293b', color: '#f8fafc',
                    confirmButtonColor: '#6366f1', cancelButtonColor: '#ef4444',
                    customClass: { popup: 'border border-slate-700 rounded-2xl' }
                });

                this.render();
            },

            render() {
                this.renderEditor();
                this.renderStash(); // New
                this.renderPreview();
                this.checkChanges();
            },

            // --- Editor Rendering ---
            renderEditor() {
                const { editor } = this.dom;
                editor.innerHTML = '';

                if (this.data.keyboard.length === 0) {
                    editor.innerHTML = `<div class="text-center py-10 opacity-30 text-white">سطری وجود ندارد</div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-card';
                    rowEl.dataset.rowIdx = rIdx;
                    
                    // Add Row Handle
                    rowEl.innerHTML = `<div class="absolute -left-6 top-1/2 -translate-y-1/2 text-slate-600 hover:text-white cursor-grab px-2 row-handle"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        rowEl.appendChild(this.createKeyElement(btn, rIdx, bIdx, 'main'));
                    });

                    // Inline Add
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-10 h-10 border border-dashed border-slate-600 rounded-lg flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:border-indigo-500 cursor-pointer opacity-50 hover:opacity-100 transition';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Empty Row
                    if (row.length === 0) {
                        const delRow = document.createElement('div');
                        delRow.className = 'w-full text-center text-xs text-red-400 py-2 cursor-pointer border border-dashed border-red-900/30 rounded bg-red-500/5 hover:bg-red-500/10';
                        delRow.innerHTML = 'حذف سطر خالی';
                        delRow.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delRow);
                    }

                    editor.appendChild(rowEl);
                });

                this.initSortable();
            },

            // --- Stash Rendering (New) ---
            renderStash() {
                const { stash } = this.dom;
                stash.innerHTML = '';
                
                if (this.data.stash.length === 0) {
                    stash.innerHTML = `<div class="text-center py-10 opacity-20 text-xs text-white">خالی</div>`;
                }

                this.data.stash.forEach((btn, idx) => {
                    // Pass 'stash' as type to handle edits differently if needed
                    stash.appendChild(this.createKeyElement(btn, null, idx, 'stash'));
                });
                
                // Initialize Sortable for Stash
                new Sortable(stash, {
                    group: 'shared-keys',
                    animation: 200,
                    ghostClass: 'opacity-50',
                    onEnd: () => this.rebuildData()
                });
            },

            createKeyElement(btn, rIdx, bIdx, type) {
                const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                const div = document.createElement('div');
                div.className = 'key-item';
                div.dataset.text = btn.text; // Store data for rebuilding
                
                div.innerHTML = `
                    <div class="key-meta flex justify-between">
                        <span class="key-code" title="${btn.text}">${btn.text.substring(0, 10)}${btn.text.length>10?'..':''}</span>
                    </div>
                    <div class="key-title truncate">${label}</div>
                    <div class="key-actions">
                        <div class="w-5 h-5 rounded bg-blue-500 flex items-center justify-center text-[10px] text-white cursor-pointer" onclick="App.editKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                        <div class="w-5 h-5 rounded bg-red-500 flex items-center justify-center text-[10px] text-white cursor-pointer" onclick="App.deleteKey('${type}', ${rIdx}, ${bIdx})"><i class="fa-solid fa-trash"></i></div>
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
                        btnDiv.className = 'tg-key';
                        btnDiv.innerText = this.data.labels[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    preview.appendChild(rowDiv);
                });
            },

            initSortable() {
                // Row Sorting
                new Sortable(this.dom.editor, {
                    animation: 250, handle: '.row-handle', ghostClass: 'opacity-50',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                // Key Sorting inside Rows
                document.querySelectorAll('.row-card').forEach(el => {
                    new Sortable(el, {
                        group: 'shared-keys', // Connects with Stash
                        animation: 200, draggable: '.key-item', ghostClass: 'opacity-50',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            rebuildData() {
                // 1. Rebuild Keyboard (Main)
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-card').forEach(row => {
                    const btns = [];
                    row.querySelectorAll('.key-item').forEach(el => {
                        btns.push({ text: el.dataset.text });
                    });
                    newRows.push(btns);
                });
                this.data.keyboard = newRows;

                // 2. Rebuild Stash
                const newStash = [];
                this.dom.stash.querySelectorAll('.key-item').forEach(el => {
                    newStash.push({ text: el.dataset.text });
                });
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
                    saveBtn.classList.remove('opacity-50');
                } else {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                    saveBtn.classList.add('opacity-50');
                }
            },

            // --- Actions ---
            addRow() {
                this.data.keyboard.push([{text: 'text_new'}]);
                this.render();
            },

            deleteRow(idx) {
                this.data.keyboard.splice(idx, 1);
                this.render();
            },

            async addKeyToRow(rIdx) {
                const { value: text } = await this.swal.fire({
                    title: 'افزودن دکمه',
                    input: 'text', inputValue: 'text_new',
                    showCancelButton: true, confirmButtonText: 'افزودن'
                });
                if (text) {
                    this.data.keyboard[rIdx].push({text});
                    this.render();
                }
            },

            deleteKey(type, rIdx, bIdx) {
                if (type === 'stash') {
                    this.data.stash.splice(bIdx, 1);
                } else {
                    this.data.keyboard[rIdx].splice(bIdx, 1);
                }
                this.render();
            },

            async editKey(type, rIdx, bIdx) {
                let current;
                if (type === 'stash') current = this.data.stash[bIdx].text;
                else current = this.data.keyboard[rIdx][bIdx].text;

                const { value: text } = await this.swal.fire({
                    title: 'ویرایش متن/کد',
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

                // Send both Keyboard and Stash
                const payload = {
                    keyboard: this.data.keyboard,
                    stash: this.data.stash
                };

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