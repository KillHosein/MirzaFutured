<?php
/**
 * Keyboard Editor Logic
 * Enterprise Grade Structure
 */

session_start();

// 1. Load Dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// 2. Constants & Configuration
const DEFAULT_KEYBOARD_CONFIG = [
    "keyboard" => [
        [["text" => "text_sell"], ["text" => "text_extend"]],
        [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
        [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
        [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
        [["text" => "text_support"], ["text" => "text_help"]]
    ]
];

// 3. Authentication Middleware
if (!isset($_SESSION["user"])) {
    header('Location: login.php');
    exit;
}

try {
    $authStmt = $pdo->prepare("SELECT id FROM admin WHERE username=:username LIMIT 1");
    $authStmt->execute([':username' => $_SESSION["user"]]);
    if (!$authStmt->fetch()) {
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    die("Database Connection Error.");
}

// 4. Request Controller
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

// -> API: Save Keyboard (AJAX)
if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (is_array($payload)) {
        $dataToSave = ['keyboard' => $payload];
        update("setting", "keyboardmain", json_encode($dataToSave, JSON_UNESCAPED_UNICODE), null, null);
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'timestamp' => time()]);
        exit;
    }
}

// -> Action: Reset Configuration
if ($method === 'GET' && $action === 'reaset') {
    $defaultJson = json_encode(DEFAULT_KEYBOARD_CONFIG, JSON_UNESCAPED_UNICODE);
    update("setting", "keyboardmain", $defaultJson, null, null);
    header('Location: keyboard.php');
    exit;
}

// 5. Data Fetching (View Model)
$viewData = '[]';
try {
    $stmt = $pdo->prepare("SELECT keyboardmain FROM setting LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && !empty($row['keyboardmain'])) {
        $decoded = json_decode($row['keyboardmain'], true);
        if (isset($decoded['keyboard'])) {
            $viewData = json_encode($decoded['keyboard']);
        }
    }
    
    // Fallback if empty
    if ($viewData === '[]' || empty($viewData)) {
        $viewData = json_encode(DEFAULT_KEYBOARD_CONFIG['keyboard']);
    }
} catch (Exception $e) {
    $viewData = '[]';
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استودیو طراحی کیبورد | MirzaBot</title>
    
    <!-- Libraries (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Design System: Developer Studio --- */
        :root {
            --bg-canvas: #09090b;
            --bg-sidebar: #121214;
            --bg-card: #1c1c1f;
            --border-subtle: #27272a;
            --primary: #6366f1; /* Indigo */
            --primary-dim: rgba(99, 102, 241, 0.15);
            --text-main: #e4e4e7;
            --text-muted: #a1a1aa;
            --danger: #ef4444;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .studio-header {
            height: 60px;
            background: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 50;
        }

        .btn-action {
            height: 34px;
            padding: 0 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-secondary { background: var(--bg-card); border-color: var(--border-subtle); color: var(--text-muted); }
        .btn-secondary:hover { background: #27272a; color: var(--text-main); }
        .btn-danger { color: var(--danger); background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.2); }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 12px var(--primary-dim); }
        .btn-primary:hover { filter: brightness(1.1); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(1); }

        /* Workspace */
        .workspace { display: flex; flex: 1; overflow: hidden; }

        /* Left: Preview */
        .preview-panel {
            width: 400px;
            background: #000;
            border-left: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            background-image: radial-gradient(#1c1c1f 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .mobile-viewport {
            width: 340px; height: 680px;
            background: #000;
            border-radius: 40px;
            border: 8px solid #1a1a1a;
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
        }

        .tg-top {
            padding: 40px 16px 12px; background: #1c1c1e;
            border-bottom: 1px solid #000; display: flex; align-items: center; gap: 12px; color: white;
        }
        .tg-content {
            flex: 1; background: #0e1621;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 8px;
        }
        .tg-keys-area {
            background: #1c1c1e; padding: 6px; min-height: 200px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }
        .tg-key {
            background: #2b5278; color: white; border-radius: 6px;
            padding: 10px 4px; margin: 2px; text-align: center; font-size: 12px;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: flex; align-items: center; justify-content: center;
        }

        /* Right: Editor */
        .editor-panel {
            flex: 1; background: transparent;
            display: flex; flex-direction: column; position: relative;
        }
        .editor-canvas {
            flex: 1; overflow-y: auto; padding: 40px;
        }

        /* Modules */
        .row-module {
            background: var(--bg-sidebar);
            border: 1px solid var(--border-subtle);
            border-radius: 12px; padding: 12px; margin-bottom: 16px;
            display: flex; flex-wrap: wrap; gap: 10px;
            position: relative; transition: all 0.2s;
        }
        .row-module:hover { border-color: #3f3f46; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }

        .drag-handle {
            position: absolute; left: -24px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); cursor: grab; padding: 6px; opacity: 0.5;
        }
        .row-module:hover .drag-handle { opacity: 1; }

        .key-block {
            flex: 1; min-width: 140px;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 8px; padding: 10px 14px;
            position: relative; cursor: grab;
            display: flex; flex-direction: column; gap: 4px;
            transition: all 0.2s;
        }
        .key-block:hover {
            border-color: var(--primary); background: #232326;
        }

        .key-code {
            font-family: 'JetBrains Mono', monospace; font-size: 12px;
            color: var(--primary); text-align: right; direction: ltr;
        }
        .key-label { font-size: 11px; color: var(--text-muted); }

        .key-actions {
            position: absolute; top: 6px; left: 6px; display: flex; gap: 4px; opacity: 0; transition: 0.2s;
        }
        .key-block:hover .key-actions { opacity: 1; }

        .mini-btn {
            width: 22px; height: 22px; border-radius: 4px;
            background: rgba(255,255,255,0.08); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer;
        }
        .mini-btn:hover { background: var(--primary); }
        .mini-btn.del:hover { background: var(--danger); }

        .add-placeholder {
            width: 100%; padding: 16px; margin-top: 24px;
            border: 2px dashed var(--border-subtle); border-radius: 12px;
            color: var(--text-muted); font-weight: 500; font-size: 14px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            cursor: pointer; transition: 0.2s;
        }
        .add-placeholder:hover {
            border-color: var(--primary); color: var(--primary); background: var(--primary-dim);
        }

        /* Responsive */
        @media (max-width: 1024px) { .preview-panel { display: none; } }
    </style>
</head>
<body>

    <!-- App Header -->
    <header class="studio-header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                <i class="fa-solid fa-code text-white text-xs"></i>
            </div>
            <div>
                <h1 class="font-bold text-sm text-white tracking-wide">MIRZABOT STUDIO</h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-action btn-secondary">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:block">خروج</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('تنظیمات به حالت اولیه بازگردد؟')" class="btn-action btn-danger">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <button onclick="App.save()" id="btn-save" class="btn-action btn-primary" disabled>
                <i class="fa-solid fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <div class="workspace">
        
        <!-- Live Preview (Left) -->
        <div class="preview-panel">
            <div class="absolute top-6 left-6 text-[10px] font-bold text-gray-500 uppercase tracking-widest">REALTIME PREVIEW</div>
            
            <div class="mobile-viewport animate__animated animate__fadeInLeft">
                <div class="tg-top">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1 font-bold text-sm">Mirza Bot <span class="text-xs text-blue-400 font-normal">bot</span></div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>
                <div class="tg-content">
                    <div class="bg-[#2b5278] text-white text-xs px-3 py-2 rounded-xl rounded-tl-sm mx-3 mb-2 max-w-[85%] shadow">
                        پیش‌نمایش زنده منوی ربات 👇
                    </div>
                </div>
                <div id="preview-render" class="tg-keys-area flex flex-col justify-end">
                    <!-- Buttons Render Here -->
                </div>
            </div>
        </div>

        <!-- Editor Canvas (Right) -->
        <div class="editor-panel">
            <div class="editor-canvas">
                <div id="editor-render" class="max-w-4xl mx-auto pb-8">
                    <!-- Rows Render Here -->
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

    <!-- Frontend Application -->
    <script>
        /**
         * App Logic - Self Contained
         */
        const App = {
            data: {
                keyboard: <?php echo $viewData; ?>,
                initialSnapshot: '',
                // Human-readable labels for technical variable names
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
                
                // Initialize SweetAlert with theme
                this.swal = Swal.mixin({
                    background: '#121214',
                    color: '#e4e4e7',
                    confirmButtonColor: '#6366f1',
                    cancelButtonColor: '#ef4444',
                    customClass: { popup: 'border border-[#27272a] rounded-xl' }
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
                        <div class="flex flex-col items-center justify-center py-20 opacity-30 select-none text-gray-500">
                            <i class="fa-solid fa-layer-group text-5xl mb-4"></i>
                            <p>هیچ دکمه‌ای تعریف نشده است</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'row-module animate__animated animate__fadeIn';
                    rowEl.innerHTML = `<div class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        const label = this.data.labels[btn.text] || 'دکمه سفارشی';
                        const keyEl = document.createElement('div');
                        keyEl.className = 'key-block';
                        keyEl.innerHTML = `
                            <div class="key-code" title="${btn.text}">${btn.text}</div>
                            <div class="key-label">${label}</div>
                            <div class="key-actions">
                                <div class="mini-btn" onclick="App.editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                                <div class="mini-btn del" onclick="App.deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                            </div>
                        `;
                        rowEl.appendChild(keyEl);
                    });

                    // Inline Add Button
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-[40px] border border-dashed border-[#3f3f46] rounded-lg flex items-center justify-center cursor-pointer hover:border-[#6366f1] hover:text-[#6366f1] transition text-[#52525b]';
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
                        // Show Persian label in preview
                        btnDiv.innerText = this.data.labels[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    
                    if (row.length > 0) preview.appendChild(rowDiv);
                });
            },

            initSortable() {
                new Sortable(this.dom.editor, {
                    animation: 200, handle: '.drag-handle', ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                document.querySelectorAll('.row-module').forEach(el => {
                    new Sortable(el, {
                        group: 'shared', animation: 200, draggable: '.key-block', ghostClass: 'opacity-40',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            rebuildData() {
                const newRows = [];
                this.dom.editor.querySelectorAll('.row-module').forEach(row => {
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
                setTimeout(() => document.querySelector('.editor-canvas').scrollTop = 99999, 50);
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
                saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';
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
                            timer: 3000, background: '#121214', color: '#fff'
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