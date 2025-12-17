<?php
/**
 * Keyboard Editor - Ultimate Pro Version
 * Self Contained - No external local JS required.
 */

session_start();

// 1. Load Configurations
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// 2. Authentication Check
if (!isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

try {
    $authStmt = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $authStmt->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
    $authStmt->execute();
    $adminRow = $authStmt->fetch(PDO::FETCH_ASSOC);

    if (!$adminRow) {
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Keep invoice query to prevent header errors if used globally
try {
    $invoiceStmt = $pdo->prepare("SELECT * FROM invoice");
    $invoiceStmt->execute();
    $listinvoice = $invoiceStmt->fetchAll();
} catch (Exception $e) { /* Ignore */ }


// 3. API Handler (Save Logic)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $inputJSON = file_get_contents("php://input");
    $inputData = json_decode($inputJSON, true);

    if (is_array($inputData)) {
        $keyboardStruct = ['keyboard' => $inputData];
        update("setting", "keyboardmain", json_encode($keyboardStruct, JSON_UNESCAPED_UNICODE), null, null);
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'ts' => time()]);
        exit;
    }
}

// 4. Reset Logic
if (isset($_GET['action']) && $_GET['action'] === 'reaset') {
    $defaultKeyboard = json_encode([
        "keyboard" => [
            [["text" => "text_sell"], ["text" => "text_extend"]],
            [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
            [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
            [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
            [["text" => "text_support"], ["text" => "text_help"]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    update("setting", "keyboardmain", $defaultKeyboard, null, null);
    header('Location: keyboard.php');
    exit;
}

// 5. Fetch Current Data
$currentKeyboardJSON = '[]';
try {
    $stmt = $pdo->prepare("SELECT keyboardmain FROM setting LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($settings && !empty($settings['keyboardmain'])) {
        $decoded = json_decode($settings['keyboardmain'], true);
        if (isset($decoded['keyboard'])) {
            $currentKeyboardJSON = json_encode($decoded['keyboard']);
        }
    }
    
    // Fallback if DB is empty
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
    <title>استودیو طراحی کیبورد | MirzaBot</title>
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- DESIGN SYSTEM: LUMINOUS DARK --- */
        :root {
            /* Backgrounds */
            --bg-void: #030014;
            --bg-panel: #0b0b1e;
            --bg-surface: #13132b;
            --glass-surface: rgba(19, 19, 43, 0.6);
            
            /* Borders */
            --border-dim: rgba(255, 255, 255, 0.05);
            --border-light: rgba(255, 255, 255, 0.1);
            
            /* Accents */
            --primary: #6366f1; /* Indigo */
            --primary-glow: rgba(99, 102, 241, 0.4);
            --secondary: #a855f7; /* Purple */
            --danger: #ef4444;
            
            /* Text */
            --txt-main: #f8fafc;
            --txt-muted: #94a3b8;
            
            /* Effects */
            --blur: blur(24px);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-void);
            color: var(--txt-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            /* Enhanced Background */
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(99, 102, 241, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(168, 85, 247, 0.08), transparent 25%);
        }

        /* --- UI COMPONENTS --- */

        /* 1. Header */
        .glass-header {
            height: 70px;
            background: rgba(3, 0, 20, 0.7);
            backdrop-filter: var(--blur);
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 50;
        }

        .brand-badge {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1));
            border: 1px solid rgba(255,255,255,0.1);
            padding: 6px 14px;
            border-radius: 12px;
            display: flex; align-items: center; gap: 10px;
        }
        .brand-icon { color: #818cf8; filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.5)); }

        .btn-modern {
            height: 38px; padding: 0 18px; border-radius: 10px;
            font-size: 13px; font-weight: 500; cursor: pointer;
            display: flex; align-items: center; gap: 8px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        .btn-ghost { color: var(--txt-muted); background: transparent; border-color: var(--border-dim); }
        .btn-ghost:hover { background: rgba(255,255,255,0.05); color: white; border-color: var(--border-light); }
        
        .btn-danger { color: #f87171; background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.1); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); }

        .btn-glow {
            background: var(--primary); color: white; border: none;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
            position: relative; overflow: hidden;
        }
        .btn-glow::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%); transition: 0.5s;
        }
        .btn-glow:hover { transform: translateY(-1px); box-shadow: 0 0 25px rgba(99, 102, 241, 0.5); }
        .btn-glow:hover::after { transform: translateX(100%); }
        .btn-glow:disabled { background: #1e1e24; color: #52525b; box-shadow: none; cursor: not-allowed; }

        /* 2. Workspace */
        .workspace { display: flex; flex: 1; overflow: hidden; position: relative; }
        
        /* Grid Pattern Overlay */
        .workspace::before {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px; pointer-events: none; z-index: 0; opacity: 0.5;
        }

        /* 3. Preview Panel */
        .preview-sidebar {
            width: 440px;
            background: rgba(5, 5, 10, 0.5);
            border-left: 1px solid var(--border-light);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            position: relative; z-index: 10;
            backdrop-filter: blur(10px);
        }

        .phone-mockup {
            width: 350px; height: 700px;
            background: #000;
            border-radius: 48px;
            border: 6px solid #1f1f1f;
            box-shadow: 0 0 0 2px #333, 0 30px 80px rgba(0,0,0,0.8);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
        }
        
        .notch {
            position: absolute; top: 10px; left: 50%; transform: translateX(-50%);
            width: 90px; height: 26px; background: #000; border-radius: 14px; z-index: 20;
        }

        .tg-header {
            padding: 40px 16px 12px; background: #1c1c1e;
            border-bottom: 1px solid #000; display: flex; align-items: center; gap: 10px; color: white;
        }
        .tg-body {
            flex: 1; background: #0f0f0f;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231a1a1a' fill-opacity='0.5' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 8px;
        }
        .tg-bubble {
            background: #2b5278; color: white; padding: 8px 12px;
            border-radius: 14px; border-top-left-radius: 4px;
            max-width: 85%; margin: 0 12px 10px; font-size: 13px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .tg-keys-area {
            background: #1c1c1e; padding: 6px; min-height: 200px;
            border-top: 1px solid #000;
        }
        .tg-key {
            background: #2b5278; color: white; border-radius: 6px;
            padding: 10px 4px; margin: 2px; text-align: center; font-size: 12px;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: flex; align-items: center; justify-content: center;
        }

        /* 4. Editor Panel */
        .editor-container {
            flex: 1; display: flex; flex-direction: column; position: relative; z-index: 10;
        }
        .editor-content { flex: 1; overflow-y: auto; padding: 40px 60px; }

        /* Row Card */
        .row-card {
            background: var(--glass-surface);
            border: 1px solid var(--border-light);
            border-radius: 16px; padding: 14px; margin-bottom: 20px;
            display: flex; flex-wrap: wrap; gap: 10px;
            position: relative; transition: all 0.2s;
            backdrop-filter: blur(10px);
        }
        .row-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: rgba(19, 19, 43, 0.8);
        }

        .handle-grip {
            position: absolute; left: -28px; top: 50%; transform: translateY(-50%);
            color: var(--txt-muted); cursor: grab; padding: 6px; opacity: 0.5; font-size: 18px;
        }
        .row-card:hover .handle-grip { opacity: 1; color: white; }

        /* Key Unit */
        .key-unit {
            flex: 1; min-width: 140px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-light);
            border-radius: 10px; padding: 12px;
            position: relative; cursor: grab;
            display: flex; flex-direction: column; gap: 4px;
            transition: all 0.2s;
        }
        .key-unit:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--primary);
            box-shadow: inset 0 0 20px rgba(99, 102, 241, 0.05);
        }

        .key-code {
            font-family: 'JetBrains Mono', monospace; font-size: 12px;
            color: #a5b4fc; text-align: right; direction: ltr; font-weight: 500;
        }
        .key-label { font-size: 11px; color: var(--txt-muted); }

        .key-actions {
            position: absolute; top: 6px; left: 6px; display: flex; gap: 4px; opacity: 0; transition: 0.2s;
        }
        .key-unit:hover .key-actions { opacity: 1; }

        .icon-btn {
            width: 22px; height: 22px; border-radius: 6px;
            background: rgba(0,0,0,0.3); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer; backdrop-filter: blur(4px);
        }
        .icon-btn:hover { background: var(--primary); }
        .icon-btn.del:hover { background: var(--danger); }

        /* Add Button */
        .add-placeholder {
            width: 100%; padding: 18px; margin-top: 24px;
            border: 2px dashed var(--border-light); border-radius: 16px;
            color: var(--txt-muted); font-weight: 600; font-size: 14px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            cursor: pointer; transition: 0.2s;
        }
        .add-placeholder:hover {
            border-color: var(--primary); color: var(--primary);
            background: rgba(99, 102, 241, 0.08);
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        @media (max-width: 1024px) { .preview-sidebar { display: none; } }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="glass-header">
        <div class="brand-badge">
            <i class="fa-solid fa-layer-group brand-icon"></i>
            <span class="text-white font-bold text-sm tracking-wide">MIRZABOT <span class="font-light text-indigo-300">STUDIO</span></span>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-modern btn-ghost">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:block">خروج</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('تنظیمات ریست شود؟')" class="btn-modern btn-danger">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <div class="w-px h-6 bg-white/10 mx-1"></div>
            <button onclick="App.save()" id="btn-save" class="btn-modern btn-glow" disabled>
                <i class="fa-solid fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Workspace -->
    <div class="workspace">
        
        <!-- Preview Panel -->
        <div class="preview-sidebar">
            <div class="absolute top-6 left-6 text-[10px] font-bold text-indigo-400 uppercase tracking-widest opacity-80">REALTIME PREVIEW</div>
            
            <div class="phone-mockup animate__animated animate__fadeInLeft">
                <div class="notch"></div>
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1 font-bold text-sm text-white">Mirza Bot <span class="text-xs text-blue-400 font-normal">bot</span></div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>
                <div class="tg-body">
                    <div class="tg-bubble">
                        پیش‌نمایش زنده منوی ربات 👇
                    </div>
                </div>
                <div id="preview-render" class="tg-keys-area flex flex-col justify-end">
                    <!-- JS Renders Keys -->
                </div>
            </div>
        </div>

        <!-- Editor Panel -->
        <div class="editor-container">
            <div class="editor-content">
                <div id="editor-render" class="max-w-4xl mx-auto pb-8">
                    <!-- JS Renders Rows -->
                </div>
                
                <div class="max-w-4xl mx-auto pb-24">
                    <div onclick="App.addRow()" class="add-placeholder">
                        <i class="fa-solid fa-plus text-lg"></i>
                        افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Application Logic -->
    <script>
        const App = {
            data: {
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                initialSnapshot: '',
                // ترجمه کدهای فنی برای نمایش
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
                this.data.initialSnapshot = JSON.stringify(this.data.keyboard);
                
                // SweetAlert Theme
                this.swal = Swal.mixin({
                    background: '#0b0b1e',
                    color: '#e2e8f0',
                    confirmButtonColor: '#6366f1',
                    cancelButtonColor: '#ef4444',
                    customClass: { popup: 'border border-indigo-900/50 rounded-2xl shadow-2xl' }
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
                        <div class="flex flex-col items-center justify-center py-20 opacity-30 select-none text-indigo-200">
                            <i class="fa-solid fa-layer-group text-6xl mb-4"></i>
                            <p class="font-light tracking-wide">هیچ دکمه‌ای تعریف نشده است</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-card animate__animated animate__fadeIn';
                    rowEl.innerHTML = `<div class="handle-grip"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                        const keyEl = document.createElement('div');
                        keyEl.className = 'key-unit';
                        keyEl.innerHTML = `
                            <div class="key-code" title="${btn.text}">${btn.text}</div>
                            <div class="key-label">${label}</div>
                            <div class="key-actions">
                                <div class="icon-btn" onclick="App.editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                                <div class="icon-btn del" onclick="App.deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                            </div>
                        `;
                        rowEl.appendChild(keyEl);
                    });

                    // Add Button in Row
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-[40px] border border-dashed border-gray-600 rounded-lg flex items-center justify-center cursor-pointer hover:border-indigo-500 hover:text-indigo-500 transition text-gray-500 opacity-60 hover:opacity-100';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowEl.appendChild(addBtn);
                    }

                    // Delete Row
                    if (row.length === 0) {
                        const delRow = document.createElement('div');
                        delRow.className = 'w-full text-center text-xs text-red-400 py-2 border border-dashed border-red-900/30 rounded cursor-pointer hover:bg-red-900/10';
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
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'flex w-full gap-1 mb-1';
                    
                    row.forEach(btn => {
                        const btnDiv = document.createElement('div');
                        btnDiv.className = 'tg-key flex-1 truncate';
                        // Show Persian Label
                        btnDiv.innerText = this.data.labels[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    
                    if (row.length > 0) preview.appendChild(rowDiv);
                });
            },

            initSortable() {
                new Sortable(this.dom.editor, {
                    animation: 250, handle: '.handle-grip', ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                document.querySelectorAll('.row-card').forEach(el => {
                    new Sortable(el, {
                        group: 'shared', animation: 200, draggable: '.key-unit', ghostClass: 'opacity-40',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            rebuildData() {
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-card').forEach(row => {
                    const btns = [];
                    row.querySelectorAll('.key-code').forEach(el => btns.push({ text: el.innerText }));
                    if (btns.length > 0 || row.querySelector('.fa-plus')) newRows.push(btns);
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
                    saveBtn.classList.add('animate-pulse');
                } else {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ذخیره شد';
                    saveBtn.classList.remove('animate-pulse');
                }
            },

            addRow() {
                this.data.keyboard.push([{text: 'text_new'}]);
                this.render();
                setTimeout(() => document.querySelector('.editor-content').scrollTop = 99999, 50);
            },

            deleteRow(idx) {
                this.data.keyboard.splice(idx, 1);
                this.render();
            },

            async addKeyToRow(rIdx) {
                const { value: text } = await this.swal.fire({
                    title: 'افزودن دکمه',
                    input: 'text',
                    inputValue: 'text_new',
                    inputLabel: 'کد متغیر (انگلیسی)',
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
                    title: 'ویرایش کد',
                    input: 'text',
                    inputValue: current,
                    showCancelButton: true,
                    confirmButtonText: 'تایید'
                });
                if (text) {
                    this.data.keyboard[rIdx][bIdx].text = text;
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
                    body: JSON.stringify(this.data.keyboard)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.data.initialSnapshot = JSON.stringify(this.data.keyboard);
                        this.checkChanges();
                        const Toast = Swal.mixin({
                            toast: true, position: 'top-end', showConfirmButton: false, 
                            timer: 3000, background: '#0b0b1e', color: '#fff'
                        });
                        Toast.fire({icon: 'success', title: 'تغییرات ذخیره شد'});
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