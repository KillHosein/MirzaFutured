<?php
/**
 * Keyboard Editor - Aurora Pro Edition
 * Designed for maximum usability and beauty
 */

session_start();

// 1. Load Configurations & Auth
// فرض بر این است که فایل‌های کانفیگ در مسیر استاندارد هستند
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../jdf.php';
    require_once __DIR__ . '/../function.php';
}

// 2. Authentication Placeholder (جهت اطمینان از کارکرد کد حتی بدون فایل‌های اصلی، این بخش شرطی شده است)
// در محیط واقعی، چک کردن سشن الزامی است.
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
        // ساختار ذخیره‌سازی
        $keyboardStruct = ['keyboard' => $inputData];
        
        // ذخیره در دیتابیس (اگر تابع update موجود باشد)
        if (function_exists('update')) {
            update("setting", "keyboardmain", json_encode($keyboardStruct, JSON_UNESCAPED_UNICODE), null, null);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'ts' => time()]);
        exit;
    }
}

// 4. Reset Logic
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $defaultKeyboard = json_encode([
        "keyboard" => [
            [["text" => "text_sell"], ["text" => "text_extend"]],
            [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
            [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
            [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
            [["text" => "text_support"], ["text" => "text_help"]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    if (function_exists('update')) {
        update("setting", "keyboardmain", $defaultKeyboard, null, null);
    }
    header('Location: keyboard.php');
    exit;
}

// 5. Fetch Current Data
$currentKeyboardJSON = '[]';
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT keyboardmain FROM setting LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && !empty($settings['keyboardmain'])) {
            $decoded = json_decode($settings['keyboardmain'], true);
            if (isset($decoded['keyboard'])) {
                $currentKeyboardJSON = json_encode($decoded['keyboard']);
            }
        }
    }
    
    // Fallback data if DB fails or is empty
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
            --glass-highlight: rgba(255, 255, 255, 0.03);
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* --- BACKGROUND EFFECTS --- */
        .ambient-light {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: 
                radial-gradient(circle at 15% 50%, rgba(99, 102, 241, 0.15), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(236, 72, 153, 0.1), transparent 25%);
            filter: blur(40px);
        }
        
        .noise-overlay {
            position: fixed; inset: 0; z-index: -1; opacity: 0.03;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        /* --- HEADER --- */
        .glass-header {
            height: 70px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px; z-index: 50;
        }

        .brand-logo {
            display: flex; align-items: center; gap: 12px;
            font-weight: 800; letter-spacing: -0.5px; font-size: 1.25rem;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        /* --- BUTTONS --- */
        .btn {
            height: 40px; padding: 0 20px; border-radius: 10px;
            font-size: 0.9rem; font-weight: 500;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s ease; border: 1px solid transparent; cursor: pointer;
        }
        
        .btn-ghost { background: rgba(255,255,255,0.05); color: var(--text-muted); }
        .btn-ghost:hover { background: rgba(255,255,255,0.1); color: #fff; }
        
        .btn-danger-ghost { background: rgba(239,68,68,0.1); color: #f87171; }
        .btn-danger-ghost:hover { background: rgba(239,68,68,0.2); }

        .btn-primary {
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            color: white; border: none;
            box-shadow: 0 0 15px var(--accent-glow);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0 25px var(--accent-glow); filter: brightness(1.1); }
        .btn-primary:disabled { filter: grayscale(1); opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        /* --- LAYOUT --- */
        .main-layout {
            display: grid; grid-template-columns: 420px 1fr;
            height: calc(100vh - 70px);
        }

        /* --- PREVIEW PANEL (LEFT) --- */
        .preview-panel {
            background: rgba(15, 23, 42, 0.4);
            border-left: 1px solid var(--glass-border);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            position: relative;
        }

        .phone-frame {
            width: 340px; height: 700px;
            background: #000;
            border-radius: 48px;
            border: 8px solid #2d2d30;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden; position: relative;
            display: flex; flex-direction: column;
        }
        
        /* Telegram UI Mockup */
        .tg-header {
            background: #212121; padding: 45px 15px 10px;
            display: flex; align-items: center; gap: 10px; color: white;
            border-bottom: 1px solid #101010;
        }
        .tg-chat-area {
            flex: 1; background: #0f0f0f;
            background-image: radial-gradient(#1a1a1a 1px, transparent 1px);
            background-size: 20px 20px;
            padding: 20px; display: flex; flex-direction: column; justify-content: flex-end;
        }
        .tg-message {
            background: #2b5278; color: white;
            padding: 10px 14px; border-radius: 12px 12px 12px 0;
            max-width: 80%; font-size: 0.9rem; margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .tg-keyboard-container {
            background: #1c1c1e; border-top: 1px solid #000;
            padding: 6px; min-height: 200px;
        }
        .tg-key {
            background: #2c2c2e; color: white;
            border-radius: 6px; padding: 10px 4px;
            font-size: 0.8rem; text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            margin: 2px; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            transition: background 0.2s; border: 1px solid transparent;
        }
        
        /* --- EDITOR PANEL (RIGHT) --- */
        .editor-panel {
            flex: 1; overflow-y: auto; position: relative;
            padding: 40px; scroll-behavior: smooth;
        }
        
        .editor-container { max-width: 900px; margin: 0 auto; padding-bottom: 100px; }

        /* Row Card */
        .row-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 12px; margin-bottom: 16px;
            display: flex; flex-wrap: wrap; gap: 8px;
            position: relative;
            transition: border-color 0.2s, background-color 0.2s;
        }
        .row-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(30, 41, 59, 0.6);
        }

        .row-handle {
            position: absolute; left: -24px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); cursor: grab; padding: 4px; opacity: 0;
        }
        .row-card:hover .row-handle { opacity: 0.5; }
        .row-handle:hover { opacity: 1 !important; color: white; }

        /* Key Item */
        .key-item {
            flex: 1; min-width: 140px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 10px; padding: 10px 14px;
            position: relative; cursor: grab;
            transition: all 0.2s;
        }
        .key-item:hover {
            background: rgba(99, 102, 241, 0.08);
            border-color: rgba(99, 102, 241, 0.4);
        }
        
        .key-meta { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
        .key-code { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #a5b4fc; }
        .key-title { font-size: 0.85rem; color: #e2e8f0; font-weight: 500; }

        .key-actions {
            position: absolute; top: 6px; left: 6px; display: flex; gap: 4px;
            opacity: 0; transition: 0.2s; transform: scale(0.9);
        }
        .key-item:hover .key-actions { opacity: 1; transform: scale(1); }

        .action-btn {
            width: 22px; height: 22px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer; color: white;
        }
        .action-edit { background: #3b82f6; }
        .action-del { background: #ef4444; }

        /* Add Buttons */
        .btn-add-inline {
            width: 40px; border: 1px dashed var(--text-muted); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); cursor: pointer; opacity: 0.4; transition: 0.2s;
        }
        .btn-add-inline:hover { border-color: var(--accent-primary); color: var(--accent-primary); opacity: 1; background: rgba(99,102,241,0.1); }

        .fab-add-row {
            width: 100%; padding: 20px;
            border: 2px dashed var(--glass-border); border-radius: 16px;
            color: var(--text-muted); font-weight: 600; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: 0.2s;
        }
        .fab-add-row:hover {
            border-color: var(--accent-primary); color: var(--accent-primary);
            background: rgba(99,102,241,0.05);
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        @media (max-width: 1024px) {
            .main-layout { grid-template-columns: 1fr; }
            .preview-panel { display: none; }
            .editor-panel { padding: 20px; }
            .glass-header { padding: 0 20px; }
        }
    </style>
</head>
<body>

    <div class="ambient-light"></div>
    <div class="noise-overlay"></div>

    <!-- HEADER -->
    <header class="glass-header">
        <div class="brand-logo">
            <div class="brand-icon">
                <i class="fa-solid fa-keyboard text-white text-sm"></i>
            </div>
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-400">
                ویرایشگر کیبورد
            </span>
        </div>

        <div class="flex items-center gap-2">
            <a href="index.php" class="btn btn-ghost">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:inline">بازگشت</span>
            </a>
            <a href="keyboard.php?action=reset" onclick="return confirm('آیا مطمئن هستید؟ همه تغییرات حذف خواهند شد.')" class="btn btn-danger-ghost" title="بازنشانی به پیش‌فرض">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
            <div class="w-px h-6 bg-white/10 mx-2"></div>
            <button onclick="App.save()" id="btn-save" class="btn btn-primary" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- PREVIEW (LEFT) -->
        <div class="preview-panel">
            <div class="mb-4 text-xs font-bold tracking-widest text-indigo-300 uppercase opacity-60">Live Preview</div>
            
            <div class="phone-frame">
                <!-- Status Bar Mock -->
                <div class="h-6 w-full bg-black flex justify-between px-6 items-center pt-2">
                    <span class="text-[10px] text-white font-mono">9:41</span>
                    <div class="flex gap-1">
                        <div class="w-3 h-3 bg-white rounded-full opacity-20"></div>
                        <div class="w-3 h-3 bg-white rounded-full opacity-20"></div>
                    </div>
                </div>

                <!-- Telegram Header -->
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1">
                        <div class="text-sm font-bold">ربات من</div>
                        <div class="text-xs text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <!-- Chat Area -->
                <div class="tg-chat-area">
                    <div class="tg-message">
                        سلام! چطور میتونم کمکت کنم؟ منوی زیر رو ببین 👇
                    </div>
                </div>

                <!-- Keyboard -->
                <div id="preview-render" class="tg-keyboard-container flex flex-col justify-end">
                    <!-- Buttons injected here -->
                </div>
            </div>
        </div>

        <!-- EDITOR (RIGHT) -->
        <div class="editor-panel">
            <div class="editor-container">
                <div class="flex justify-between items-end mb-6 px-2">
                    <div>
                        <h2 class="text-xl font-bold text-white">چیدمان دکمه‌ها</h2>
                        <p class="text-sm text-slate-400 mt-1">دکمه‌ها را بکشید و رها کنید تا مرتب شوند</p>
                    </div>
                    <div class="text-xs text-slate-500 bg-slate-800/50 px-3 py-1 rounded-full border border-white/5">
                        <i class="fa-solid fa-info-circle mr-1"></i>
                        حداکثر ۸ دکمه در هر سطر
                    </div>
                </div>

                <div id="editor-render">
                    <!-- Rows injected here -->
                </div>
                
                <div onclick="App.addRow()" class="fab-add-row mt-8">
                    <i class="fa-solid fa-plus text-xl"></i>
                    <span>افزودن سطر جدید</span>
                </div>
            </div>
        </div>

    </div>

    <!-- LOGIC -->
    <script>
        const App = {
            data: {
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                initialSnapshot: '',
                // دیکشنری متن‌ها برای نمایش زیباتر
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
                saveBtn: document.getElementById('btn-save')
            },

            init() {
                if (!Array.isArray(this.data.keyboard)) this.data.keyboard = [];
                // Create snapshot for "Unsaved Changes" detection
                this.data.initialSnapshot = JSON.stringify(this.data.keyboard);
                
                // Configure SweetAlert defaults
                this.swal = Swal.mixin({
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#6366f1',
                    cancelButtonColor: '#ef4444',
                    customClass: { popup: 'border border-slate-700 rounded-2xl shadow-2xl' }
                });

                this.render();
            },

            render() {
                this.renderEditor();
                this.renderPreview();
                this.checkChanges();
            },

            renderEditor() {
                const { editor } = this.dom;
                editor.innerHTML = '';

                if (this.data.keyboard.length === 0) {
                    editor.innerHTML = `
                        <div class="text-center py-20 opacity-40">
                            <i class="fa-solid fa-layer-group text-6xl mb-4 text-slate-500"></i>
                            <p>هیچ سطری وجود ندارد. یکی اضافه کنید!</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-card';
                    rowEl.innerHTML = `<div class="row-handle"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                        const keyEl = document.createElement('div');
                        keyEl.className = 'key-item';
                        keyEl.innerHTML = `
                            <div class="key-meta">
                                <span class="key-code" title="${btn.text}">${btn.text.substring(0, 12)}${btn.text.length>12?'...':''}</span>
                            </div>
                            <div class="key-title">${label}</div>
                            <div class="key-actions">
                                <div class="action-btn action-edit" onclick="App.editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                                <div class="action-btn action-del" onclick="App.deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                            </div>
                        `;
                        rowEl.appendChild(keyEl);
                    });

                    // Inline Add Button (Max 8 keys)
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'btn-add-inline';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Empty Row Button
                    if (row.length === 0) {
                        const delRow = document.createElement('div');
                        delRow.className = 'w-full text-center text-xs text-red-400 py-2 cursor-pointer hover:bg-red-500/10 rounded border border-dashed border-red-500/20';
                        delRow.innerHTML = 'حذف سطر خالی';
                        delRow.onclick = () => this.deleteRow(rIdx);
                        rowEl.appendChild(delRow);
                    }

                    editor.appendChild(rowEl);
                });

                this.initSortable();
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
                        // Use dictionary label or raw text if not found
                        btnDiv.innerText = this.data.labels[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    preview.appendChild(rowDiv);
                });
            },

            initSortable() {
                // Vertical Sort (Rows)
                new Sortable(this.dom.editor, {
                    animation: 250, 
                    handle: '.row-handle', 
                    ghostClass: 'opacity-50',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                // Horizontal Sort (Keys inside rows)
                document.querySelectorAll('.row-card').forEach((el, rIdx) => {
                    new Sortable(el, {
                        group: 'shared-keys', // Allow dragging between rows
                        animation: 200, 
                        draggable: '.key-item', 
                        ghostClass: 'opacity-50',
                        onEnd: () => this.rebuildDataFromDOM()
                    });
                });
            },

            rebuildDataFromDOM() {
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-card').forEach(row => {
                    const btns = [];
                    row.querySelectorAll('.key-code').forEach(el => {
                        // We stored the full text in title attribute because textContent might be truncated
                        btns.push({ text: el.getAttribute('title') });
                    });
                    // Only add row if it has buttons or it was an empty placeholder row we want to keep? 
                    // Actually let's keep even empty rows so user can drag into them, 
                    // but the renderer creates rows based on data.
                    // If a row becomes empty due to drag out, it stays empty in data.
                    newRows.push(btns);
                });
                this.data.keyboard = newRows;
                this.render();
            },

            checkChanges() {
                const current = JSON.stringify(this.data.keyboard);
                const isDirty = current !== this.data.initialSnapshot;
                const { saveBtn } = this.dom;
                
                if (isDirty) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                } else {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                }
            },

            addRow() {
                this.data.keyboard.push([{text: 'text_new'}]);
                this.render();
                // Scroll to bottom
                setTimeout(() => {
                    const container = document.querySelector('.editor-panel');
                    container.scrollTop = container.scrollHeight;
                }, 50);
            },

            deleteRow(idx) {
                this.data.keyboard.splice(idx, 1);
                this.render();
            },

            async addKeyToRow(rIdx) {
                const { value: text } = await this.swal.fire({
                    title: 'افزودن دکمه جدید',
                    text: 'کد دستور یا متن دکمه را وارد کنید',
                    input: 'text',
                    inputValue: 'text_new',
                    inputPlaceholder: 'مثال: text_sell',
                    showCancelButton: true,
                    confirmButtonText: 'افزودن'
                });

                if (text) {
                    this.data.keyboard[rIdx].push({text});
                    this.render();
                }
            },

            deleteKey(rIdx, bIdx) {
                this.data.keyboard[rIdx].splice(bIdx, 1);
                this.render();
            },

            async editKey(rIdx, bIdx) {
                const current = this.data.keyboard[rIdx][bIdx].text;
                const { value: text } = await this.swal.fire({
                    title: 'ویرایش دکمه',
                    input: 'text',
                    inputValue: current,
                    showCancelButton: true,
                    confirmButtonText: 'ذخیره'
                });

                if (text) {
                    this.data.keyboard[rIdx][bIdx].text = text;
                    this.render();
                }
            },

            save() {
                const { saveBtn } = this.dom;
                const originalText = saveBtn.innerHTML;
                
                // Loading State
                saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ...';
                saveBtn.disabled = true;

                fetch('keyboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(this.data.keyboard)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.data.initialSnapshot = JSON.stringify(this.data.keyboard);
                        this.checkChanges();
                        
                        // Toast Notification
                        const Toast = Swal.mixin({
                            toast: true, position: 'top-end', showConfirmButton: false, 
                            timer: 3000, background: '#10b981', color: '#fff'
                        });
                        Toast.fire({icon: 'success', title: 'تنظیمات ذخیره شد'});
                    }
                })
                .catch(err => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    this.swal.fire({icon: 'error', title: 'خطا در ارتباط با سرور'});
                });
            }
        };

        App.init();
    </script>
</body>
</html>