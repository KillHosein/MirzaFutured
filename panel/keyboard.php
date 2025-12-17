<?php
/**
 * Keyboard Editor - Self Contained Version
 * No external JS files required.
 */

session_start();
// تنظیم مسیرهای فایل‌های کانفیگ با توجه به ساختار پوشه‌ها
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// 1. بررسی لاگین بودن ادمین
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// 2. مدیریت درخواست‌های ذخیره‌سازی (AJAX POST)
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

if($method == "POST" && is_array($inputData)){
    // ساختار استاندارد کیبورد تلگرام
    $keyboardStruct = ['keyboard' => $inputData];
    // ذخیره در دیتابیس
    update("setting", "keyboardmain", json_encode($keyboardStruct, JSON_UNESCAPED_UNICODE), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// 3. مدیریت درخواست ریست (GET)
if(isset($_GET['action']) && $_GET['action'] == "reaset"){
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

// 4. دریافت اطلاعات فعلی برای نمایش در ویرایشگر
$currentKeyboardJSON = '[]';
try {
    $stmt = $pdo->prepare("SELECT * FROM setting LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($settings && isset($settings['keyboardmain'])) {
        $decoded = json_decode($settings['keyboardmain'], true);
        if(isset($decoded['keyboard'])) {
            $currentKeyboardJSON = json_encode($decoded['keyboard']);
        }
    }
    
    // اگر دیتابیس خالی بود یا فرمت اشتباه بود، مقدار پیش‌فرض را لود کن
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
    <title>استودیو کیبورد | MirzaBot</title>
    
    <!-- CDN Libraries (No local files needed) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Studio Theme (Obsidian) --- */
        :root {
            --bg-deep: #020617;       
            --bg-panel: #0f172a;      
            --bg-surface: #1e293b;    
            --border-dim: #334155;    
            --border-light: #475569;  
            --accent-primary: #3b82f6; 
            --accent-hover: #2563eb;   
            --text-high: #f1f5f9;     
            --text-med: #94a3b8;      
            --danger: #ef4444;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-high);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background-image: 
                linear-gradient(var(--border-dim) 1px, transparent 1px),
                linear-gradient(90deg, var(--border-dim) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* --- Header --- */
        .studio-header {
            height: 64px;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-dim);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 50;
        }

        .action-button {
            height: 36px; padding: 0 16px; border-radius: 8px;
            font-size: 13px; font-weight: 500;
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; transition: 0.2s;
            border: 1px solid transparent;
        }
        .btn-ghost { color: var(--text-med); background: transparent; border: 1px solid var(--border-dim); }
        .btn-ghost:hover { background: var(--bg-surface); color: var(--text-high); }
        .btn-danger { color: var(--danger); border-color: rgba(239,68,68,0.3); }
        .btn-danger:hover { background: rgba(239,68,68,0.1); }
        .btn-solid { background: var(--accent-primary); color: white; box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        .btn-solid:hover { background: var(--accent-hover); transform: translateY(-1px); }
        .btn-solid:disabled { background: var(--bg-surface); color: var(--text-med); box-shadow: none; cursor: not-allowed; }

        /* --- Layout --- */
        .workspace-grid {
            display: grid;
            grid-template-columns: 420px 1fr;
            height: calc(100vh - 64px);
            overflow: hidden;
        }

        /* --- Left: Preview --- */
        .preview-sidebar {
            background: rgba(2, 6, 23, 0.6);
            border-left: 1px solid var(--border-dim);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            position: relative;
        }

        .device-bezel {
            width: 340px; height: 680px;
            background: #000;
            border-radius: 48px;
            box-shadow: 0 0 0 12px #1e1e1e, 0 40px 100px -20px rgba(0,0,0,0.8);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
            transform: scale(0.9);
        }
        
        .tg-app-header {
            background: #1c1c1e; padding: 45px 16px 12px;
            display: flex; align-items: center; gap: 10px;
            border-bottom: 1px solid #000; color: white;
        }
        
        .tg-chat-area {
            flex: 1; background: #0e1621;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 8px;
        }

        .tg-keyboard-panel {
            background: #1c1c1e; padding: 6px; min-height: 200px;
            border-top: 1px solid #000;
        }

        .tg-btn {
            background: #2b5278; color: #fff;
            border-radius: 6px; margin: 2px;
            padding: 10px 4px; font-size: 12px; text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: flex; align-items: center; justify-content: center;
        }

        /* --- Right: Editor --- */
        .editor-container {
            display: flex; flex-direction: column;
            background: transparent; position: relative;
        }

        .editor-scroll-area {
            flex: 1; overflow-y: auto; padding: 40px 60px;
        }

        .row-wrapper {
            background: var(--bg-surface);
            border: 1px solid var(--border-dim);
            border-radius: 12px;
            padding: 12px; margin-bottom: 16px;
            display: flex; flex-wrap: wrap; gap: 8px;
            position: relative; transition: 0.2s;
        }
        .row-wrapper:hover { border-color: var(--border-light); background: #252f42; }

        .handle-grip {
            position: absolute; left: -24px; top: 50%; transform: translateY(-50%);
            color: var(--text-med); cursor: grab; padding: 6px;
        }

        .key-module {
            flex: 1; min-width: 130px;
            background: var(--bg-panel);
            border: 1px solid var(--border-dim);
            border-radius: 8px;
            padding: 10px 14px;
            position: relative; cursor: grab;
            display: flex; flex-direction: column; gap: 4px;
            transition: 0.2s;
        }
        .key-module:hover { border-color: var(--accent-primary); background: #131c2e; }

        .key-var-name {
            font-family: 'JetBrains Mono', monospace; font-size: 12px;
            color: var(--accent-primary); text-align: right; direction: ltr;
        }
        .key-translation { font-size: 11px; color: var(--text-med); }

        .module-actions {
            position: absolute; top: 6px; left: 6px;
            display: flex; gap: 4px; opacity: 0; transition: 0.2s;
        }
        .key-module:hover .module-actions { opacity: 1; }

        .icon-btn {
            width: 20px; height: 20px; border-radius: 4px;
            background: rgba(255,255,255,0.1); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer;
        }
        .icon-btn:hover { background: var(--accent-primary); }
        .icon-btn.del:hover { background: var(--danger); }

        .fab-add {
            width: 100%; padding: 18px; margin-top: 20px;
            border: 2px dashed var(--border-dim); border-radius: 12px;
            color: var(--text-med); font-weight: 600; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: 0.2s;
        }
        .fab-add:hover { border-color: var(--accent-primary); color: var(--accent-primary); background: rgba(59, 130, 246, 0.05); }

        @media (max-width: 1024px) {
            .workspace-grid { grid-template-columns: 1fr; }
            .preview-sidebar { display: none; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="studio-header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white shadow-lg">
                <i class="fa-solid fa-code"></i>
            </div>
            <span class="text-white font-bold tracking-tight">MirzaBot Studio</span>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="action-button btn-ghost">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> خروج
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('تنظیمات ریست شود؟')" class="action-button btn-danger">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <button onclick="App.save()" id="btn-save" class="action-button btn-solid" disabled>
                <i class="fa-regular fa-floppy-disk"></i> ذخیره تغییرات
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="workspace-grid">
        
        <!-- Preview Sidebar -->
        <div class="preview-sidebar">
            <div class="absolute top-6 left-6 text-xs font-bold text-gray-500 uppercase tracking-widest">Live Preview</div>
            
            <div class="device-bezel animate__animated animate__fadeInLeft">
                <div class="tg-app-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1 text-sm font-bold text-white">Mirza Bot <span class="text-blue-400 font-normal text-xs">bot</span></div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <div class="tg-chat-area">
                    <div class="bg-[#2b5278] text-white text-xs px-3 py-2 rounded-xl rounded-tl-sm mx-4 mb-2 max-w-[85%] shadow">
                        سلام! منوی ربات به صورت زیر است 👇
                    </div>
                </div>

                <div id="preview-render" class="tg-keyboard-panel flex flex-col justify-end">
                    <!-- Buttons will render here -->
                </div>
            </div>
        </div>

        <!-- Editor Container -->
        <div class="editor-container">
            <div class="editor-scroll-area">
                <div id="editor-render" class="max-w-4xl mx-auto pb-8">
                    <!-- Rows will render here -->
                </div>
                
                <div class="max-w-4xl mx-auto pb-24">
                    <div onclick="App.addRow()" class="fab-add">
                        <i class="fa-solid fa-plus text-lg"></i>
                        افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Logic -->
    <script>
        const App = {
            data: {
                // دریافت دیتای PHP به صورت امن
                keyboard: <?php echo $currentKeyboardJSON ?: '[]'; ?>,
                initialSnapshot: '',
                // دیکشنری ترجمه
                translations: {
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
                
                // Initialize SweetAlert Theme
                this.swal = Swal.mixin({
                    background: '#0f0b29',
                    color: '#e2e8f0',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#ef4444',
                    customClass: { popup: 'border border-slate-700 rounded-2xl' }
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
                        <div class="flex flex-col items-center justify-center py-24 opacity-30 select-none text-slate-400">
                            <i class="fa-solid fa-layer-group text-5xl mb-4"></i>
                            <p>هیچ دکمه‌ای وجود ندارد</p>
                        </div>`;
                    return;
                }

                this.data.keyboard.forEach((row, rIdx) => {
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'row-wrapper animate__animated animate__fadeIn';
                    
                    // Drag Handle
                    rowDiv.innerHTML += `<div class="handle-grip"><i class="fa-solid fa-grip-vertical"></i></div>`;

                    row.forEach((btn, bIdx) => {
                        const label = this.data.translations[btn.text] || 'دکمه سفارشی';
                        const keyDiv = document.createElement('div');
                        keyDiv.className = 'key-module';
                        keyDiv.innerHTML = `
                            <div class="key-var-name" title="${btn.text}">${btn.text}</div>
                            <div class="key-translation">${label}</div>
                            <div class="module-actions">
                                <div class="icon-btn" onclick="App.editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                                <div class="icon-btn del" onclick="App.deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                            </div>
                        `;
                        rowDiv.appendChild(keyDiv);
                    });

                    // Add Button in Row
                    if (row.length < 8) {
                        const addBtn = document.createElement('div');
                        addBtn.className = 'w-[40px] border border-dashed border-slate-600 rounded-lg flex items-center justify-center cursor-pointer hover:border-blue-500 hover:text-blue-500 text-slate-500 transition';
                        addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                        addBtn.onclick = () => this.addKeyToRow(rIdx);
                        rowDiv.appendChild(addBtn);
                    }

                    // Delete Row
                    if (row.length === 0) {
                        const delRow = document.createElement('div');
                        delRow.className = 'w-full text-center text-red-400 text-xs py-2 cursor-pointer border border-dashed border-red-500/30 rounded hover:bg-red-500/10';
                        delRow.innerHTML = 'حذف سطر خالی';
                        delRow.onclick = () => this.deleteRow(rIdx);
                        rowDiv.appendChild(delRow);
                    }

                    editor.appendChild(rowDiv);
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
                        btnDiv.className = 'tg-btn flex-1 truncate';
                        // نمایش ترجمه در پیش‌نمایش
                        btnDiv.innerText = this.data.translations[btn.text] || btn.text; 
                        rowDiv.appendChild(btnDiv);
                    });
                    
                    if (row.length > 0) preview.appendChild(rowDiv);
                });
            },

            initSortable() {
                // Rows
                new Sortable(this.dom.editor, {
                    animation: 250, handle: '.handle-grip', ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const item = this.data.keyboard.splice(evt.oldIndex, 1)[0];
                        this.data.keyboard.splice(evt.newIndex, 0, item);
                        this.render();
                    }
                });

                // Keys
                document.querySelectorAll('.row-wrapper').forEach(el => {
                    new Sortable(el, {
                        group: 'shared', animation: 200, draggable: '.key-module', ghostClass: 'opacity-40',
                        onEnd: () => this.rebuildData()
                    });
                });
            },

            rebuildData() {
                const newRows = [];
                const domRows = this.dom.editor.querySelectorAll('.row-wrapper');
                domRows.forEach(row => {
                    const btns = [];
                    row.querySelectorAll('.key-var-name').forEach(el => {
                        btns.push({ text: el.innerText });
                    });
                    if (btns.length > 0 || row.querySelector('.fa-plus')) {
                        newRows.push(btns);
                    }
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
                    saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                    saveBtn.classList.remove('animate-pulse');
                }
            },

            addRow() {
                this.data.keyboard.push([{text: 'text_new'}]);
                this.render();
                setTimeout(() => document.querySelector('.editor-scroll-area').scrollTop = 99999, 50);
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
                    inputLabel: 'کد متغیر (مثال: text_sell)',
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
                            timer: 3000, background: '#0f0b29', color: '#fff'
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