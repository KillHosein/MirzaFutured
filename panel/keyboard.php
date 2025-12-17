<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// --- بررسی دسترسی ---
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

// جلوگیری از خطا در صورت استفاده در هدر
$query = $pdo->prepare("SELECT * FROM invoice");
$query->execute();
$listinvoice = $query->fetchAll();

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// --- مدیریت درخواست‌ها ---
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

// ذخیره‌سازی
if($method == "POST" && !empty($inputData)){
    $keyboardStruct = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStruct), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// ریست پیش‌فرض
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

// --- دریافت اطلاعات ---
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
    } else {
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
    
    <!-- کتابخانه‌ها -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Studio Theme Variables --- */
        :root {
            --bg-body: #020617;       /* Slate 950 */
            --bg-panel: #0f172a;      /* Slate 900 */
            --bg-surface: #1e293b;    /* Slate 800 */
            --border-dim: #334155;    /* Slate 700 */
            --border-light: #475569;  /* Slate 600 */
            
            --accent-primary: #3b82f6; /* Blue 500 */
            --accent-hover: #2563eb;   /* Blue 600 */
            --accent-glow: rgba(59, 130, 246, 0.15);
            
            --text-high: #f1f5f9;     /* Slate 100 */
            --text-med: #94a3b8;      /* Slate 400 */
            --text-low: #64748b;      /* Slate 500 */
            
            --danger: #ef4444;
            --success: #10b981;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-high);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            /* Grid Pattern */
            background-image: 
                linear-gradient(var(--border-dim) 1px, transparent 1px),
                linear-gradient(90deg, var(--border-dim) 1px, transparent 1px);
            background-size: 40px 40px;
            background-position: center top;
        }

        /* --- Scrollbar Styling --- */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-med); }

        /* --- Header --- */
        .studio-header {
            height: 64px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-dim);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 50;
        }

        .brand-pill {
            display: flex; align-items: center; gap: 12px;
            padding: 6px 12px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 99px;
        }
        .brand-icon {
            color: var(--accent-primary);
            font-size: 14px;
        }
        .brand-text {
            font-size: 13px; font-weight: 700; color: var(--text-high); letter-spacing: -0.02em;
        }

        .action-button {
            height: 36px; padding: 0 16px; border-radius: 8px;
            font-size: 13px; font-weight: 500;
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        .btn-ghost {
            color: var(--text-med);
            background: transparent;
        }
        .btn-ghost:hover {
            background: var(--bg-surface);
            color: var(--text-high);
        }
        .btn-danger-ghost {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.1);
        }
        .btn-danger-ghost:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
        }
        .btn-solid {
            background: var(--accent-primary);
            color: white;
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        .btn-solid:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px var(--accent-glow);
        }
        .btn-solid:disabled {
            background: var(--bg-surface);
            color: var(--text-low);
            box-shadow: none; cursor: not-allowed; transform: none;
        }

        /* --- Workspace Layout --- */
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
            position: relative;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            backdrop-filter: blur(10px);
        }

        .preview-label {
            position: absolute; top: 24px; left: 24px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 2px; color: var(--text-low);
            display: flex; align-items: center; gap: 6px;
        }
        .status-dot { width: 6px; height: 6px; background: var(--success); border-radius: 50%; box-shadow: 0 0 8px var(--success); }

        .device-bezel {
            width: 340px; height: 680px;
            background: #000;
            border-radius: 48px;
            box-shadow: 
                0 0 0 12px #1e1e1e,
                0 0 0 13px #333,
                0 40px 100px -20px rgba(0,0,0,0.8);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
            transform: scale(0.95);
        }
        
        .device-glare {
            position: absolute; top: 0; left: 0; right: 0; height: 200px;
            background: linear-gradient(135deg, rgba(255,255,255,0.05), transparent 40%);
            z-index: 30; pointer-events: none; border-radius: 48px 48px 0 0;
        }

        .notch {
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            width: 100px; height: 30px; background: #000; border-radius: 20px; z-index: 20;
        }

        .tg-app-header {
            background: #1c1c1e;
            padding: 45px 16px 12px;
            display: flex; align-items: center; gap: 10px;
            border-bottom: 1px solid #000; z-index: 10;
        }
        
        .tg-chat-area {
            flex: 1; background: #000;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231c1c1e' fill-opacity='0.6' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 8px;
        }

        .tg-bubble {
            background: #2b5278; color: white; padding: 8px 12px;
            border-radius: 12px; border-top-left-radius: 4px;
            max-width: 80%; margin: 0 12px 10px; font-size: 13px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3); line-height: 1.5;
        }

        .tg-keyboard-panel {
            background: #1c1c1e; padding: 6px; min-height: 200px;
            border-top: 1px solid #000;
        }

        .tg-btn {
            background: linear-gradient(180deg, #333335 0%, #29292b 100%);
            color: #fff; border-radius: 6px; margin: 2px;
            padding: 10px 4px; font-size: 12px; text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            display: flex; align-items: center; justify-content: center;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        /* --- Right: Editor --- */
        .editor-container {
            display: flex; flex-direction: column;
            position: relative; background: transparent;
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
            position: relative;
            transition: all 0.2s ease;
        }
        .row-wrapper:hover {
            border-color: var(--border-light);
            background: #252f42; /* Slightly lighter on hover */
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }

        .handle-grip {
            position: absolute; left: -24px; top: 50%; transform: translateY(-50%);
            color: var(--text-low); cursor: grab; padding: 6px;
            opacity: 0; transition: 0.2s;
        }
        .row-wrapper:hover .handle-grip { opacity: 1; left: -28px; }

        .key-module {
            flex: 1; min-width: 130px;
            background: var(--bg-panel);
            border: 1px solid var(--border-dim);
            border-radius: 8px;
            padding: 10px 14px;
            position: relative; cursor: grab;
            display: flex; flex-direction: column; gap: 4px;
            transition: all 0.2s;
        }
        .key-module:hover {
            border-color: var(--accent-primary);
            background: #131c2e;
        }
        .key-module:active { transform: scale(0.98); }

        .key-var-name {
            font-family: 'Fira Code', monospace; font-size: 12px;
            color: var(--accent-primary); text-align: right; direction: ltr;
        }
        .key-translation {
            font-size: 11px; color: var(--text-med);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .module-actions {
            position: absolute; top: 6px; left: 6px;
            display: flex; gap: 4px; opacity: 0; transition: 0.2s;
        }
        .key-module:hover .module-actions { opacity: 1; }

        .icon-btn {
            width: 20px; height: 20px; border-radius: 4px;
            background: rgba(255,255,255,0.05); color: var(--text-high);
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer; backdrop-filter: blur(4px);
        }
        .icon-btn:hover { background: var(--accent-primary); color: white; }
        .icon-btn.del:hover { background: var(--danger); }

        /* Add Button in Row */
        .add-in-row {
            width: 40px; border: 1px dashed var(--border-light);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            color: var(--text-low); cursor: pointer; transition: 0.2s;
        }
        .add-in-row:hover {
            border-color: var(--accent-primary); color: var(--accent-primary);
            background: rgba(59, 130, 246, 0.05);
        }

        /* Delete Row Button */
        .delete-row-bar {
            width: 100%; text-align: center; font-size: 11px; color: var(--danger);
            padding: 6px; border: 1px dashed rgba(239, 68, 68, 0.3); border-radius: 6px;
            cursor: pointer; transition: 0.2s; background: rgba(239, 68, 68, 0.02);
        }
        .delete-row-bar:hover { background: rgba(239, 68, 68, 0.1); }

        /* Floating Add Row */
        .fab-add {
            width: 100%; padding: 18px; margin-top: 20px;
            border: 2px dashed var(--border-dim); border-radius: 12px;
            color: var(--text-med); font-weight: 600; font-size: 14px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            cursor: pointer; transition: 0.2s;
        }
        .fab-add:hover {
            border-color: var(--accent-primary); color: var(--accent-primary);
            background: rgba(59, 130, 246, 0.05); transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .workspace-grid { grid-template-columns: 1fr; }
            .preview-sidebar { display: none; }
            .editor-scroll-area { padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="studio-header">
        <div class="brand-pill">
            <i class="fa-solid fa-code brand-icon"></i>
            <span class="brand-text">MirzaBot Studio</span>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="action-button btn-ghost">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:inline">خروج</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('آیا از بازنشانی کامل تنظیمات اطمینان دارید؟')" class="action-button btn-danger-ghost">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <div class="w-px h-6 bg-slate-700 mx-1"></div>
            <button onclick="saveKeyboard()" id="btn-save" class="action-button btn-solid" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Workspace -->
    <div class="workspace-grid">
        
        <!-- Preview Sidebar -->
        <div class="preview-sidebar">
            <div class="preview-label">
                <div class="status-dot"></div>
                Live Preview
            </div>
            
            <div class="device-bezel animate__animated animate__fadeInLeft">
                <div class="device-glare"></div>
                <div class="notch"></div>
                
                <div class="tg-app-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1">
                        <div class="text-white font-bold text-sm">Mirza Bot</div>
                        <div class="text-blue-400 text-xs">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <div class="tg-chat-area">
                    <div class="tg-bubble">
                        تغییرات شما به صورت زنده اینجا اعمال می‌شود. 👇
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
                    <div onclick="addRow()" class="fab-add">
                        <i class="fa-solid fa-plus text-lg"></i>
                        افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Application Logic -->
    <script>
        // دیکشنری هوشمند: تبدیل کدهای فنی به متن فارسی برای نمایش
        const translations = {
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
        };

        // بارگذاری داده‌ها
        let keyboardData = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardData)) keyboardData = [];
        
        let initialSnapshot = JSON.stringify(keyboardData); // برای تشخیص تغییرات

        // DOM Elements
        const editorEl = document.getElementById('editor-render');
        const previewEl = document.getElementById('preview-render');
        const saveBtn = document.getElementById('btn-save');

        // Config SweetAlert
        const SwalPro = Swal.mixin({
            background: '#0f172a',
            color: '#f1f5f9',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
            customClass: { popup: 'border border-slate-700 rounded-2xl' }
        });

        // --- Core Functions ---

        function render() {
            renderEditor();
            renderPreview();
            checkChanges();
        }

        // رندر ویرایشگر
        function renderEditor() {
            editorEl.innerHTML = '';
            
            if (keyboardData.length === 0) {
                editorEl.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-24 opacity-25 select-none text-slate-400">
                        <i class="fa-solid fa-layer-group text-6xl mb-4"></i>
                        <p class="text-lg font-light">هیچ دکمه‌ای وجود ندارد</p>
                    </div>`;
            }

            keyboardData.forEach((row, rIdx) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row-wrapper animate__animated animate__fadeIn';
                
                // هندل درگ
                rowDiv.innerHTML += `<div class="handle-grip"><i class="fa-solid fa-grip-vertical text-lg"></i></div>`;

                row.forEach((btn, bIdx) => {
                    const label = translations[btn.text] || 'دکمه سفارشی';
                    const keyDiv = document.createElement('div');
                    keyDiv.className = 'key-module';
                    keyDiv.innerHTML = `
                        <div class="key-var-name" title="${btn.text}">${btn.text}</div>
                        <div class="key-translation">${label}</div>
                        <div class="module-actions">
                            <div class="icon-btn" onclick="editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="icon-btn del" onclick="deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                    `;
                    rowDiv.appendChild(keyDiv);
                });

                // دکمه افزودن (+) داخل سطر
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'add-in-row';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                    addBtn.onclick = () => addKeyToRow(rIdx);
                    rowDiv.appendChild(addBtn);
                }

                // دکمه حذف سطر خالی
                if (row.length === 0) {
                    const delRow = document.createElement('div');
                    delRow.className = 'delete-row-bar';
                    delRow.innerHTML = '<i class="fa-solid fa-trash-can ml-1"></i> حذف سطر خالی';
                    delRow.onclick = () => deleteRow(rIdx);
                    rowDiv.appendChild(delRow);
                }

                editorEl.appendChild(rowDiv);
            });

            initSortable();
        }

        // رندر پیش‌نمایش
        function renderPreview() {
            previewEl.innerHTML = '';
            keyboardData.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex w-full gap-1 mb-1';
                
                row.forEach(btn => {
                    const btnDiv = document.createElement('div');
                    btnDiv.className = 'tg-btn flex-1 truncate';
                    // نمایش ترجمه فارسی در پیش‌نمایش
                    btnDiv.innerText = translations[btn.text] || btn.text; 
                    rowDiv.appendChild(btnDiv);
                });
                
                if(row.length > 0) previewEl.appendChild(rowDiv);
            });
        }

        // فعال‌سازی Drag & Drop
        function initSortable() {
            // جابجایی سطرها
            new Sortable(editorEl, {
                animation: 200, handle: '.handle-grip', ghostClass: 'opacity-40',
                onEnd: (evt) => {
                    const item = keyboardData.splice(evt.oldIndex, 1)[0];
                    keyboardData.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // جابجایی دکمه‌ها
            document.querySelectorAll('.row-wrapper').forEach(el => {
                new Sortable(el, {
                    group: 'shared', animation: 200, draggable: '.key-module', ghostClass: 'opacity-40',
                    onEnd: () => rebuildData() 
                });
            });
        }

        // بازسازی دیتا از روی DOM
        function rebuildData() {
            const newData = [];
            const rows = editorEl.querySelectorAll('.row-wrapper');
            rows.forEach(row => {
                const btns = [];
                row.querySelectorAll('.key-var-name').forEach(el => {
                    btns.push({ text: el.innerText });
                });
                // اگر سطر دارای دکمه یا دکمه افزودن است
                if (btns.length > 0 || row.querySelector('.fa-plus')) {
                    newData.push(btns);
                }
            });
            keyboardData = newData;
            render();
        }

        // --- اکشن‌ها ---

        function checkChanges() {
            const current = JSON.stringify(keyboardData);
            if (current !== initialSnapshot) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                saveBtn.classList.add('animate-pulse');
            } else {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                saveBtn.classList.remove('animate-pulse');
            }
        }

        function addRow() {
            keyboardData.push([{text: 'text_new'}]);
            render();
            setTimeout(() => document.querySelector('.editor-scroll-area').scrollTop = 9999, 50);
        }

        function deleteRow(idx) {
            keyboardData.splice(idx, 1);
            render();
        }

        async function addKeyToRow(rIdx) {
            const { value: text } = await SwalPro.fire({
                title: 'افزودن دکمه',
                input: 'text',
                inputValue: 'text_new',
                inputLabel: 'کد متغیر (انگلیسی)',
                showCancelButton: true,
                confirmButtonText: 'تایید'
            });
            if (text) {
                keyboardData[rIdx].push({text});
                render();
            }
        }

        function deleteKey(rIdx, bIdx) {
            keyboardData[rIdx].splice(bIdx, 1);
            render();
        }

        async function editKey(rIdx, bIdx) {
            const current = keyboardData[rIdx][bIdx].text;
            const { value: text } = await SwalPro.fire({
                title: 'ویرایش کد',
                input: 'text',
                inputValue: current,
                showCancelButton: true,
                confirmButtonText: 'تایید'
            });
            if (text) {
                keyboardData[rIdx][bIdx].text = text;
                render();
            }
        }

        function saveKeyboard() {
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ...';
            saveBtn.disabled = true;

            fetch('keyboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(keyboardData)
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    initialSnapshot = JSON.stringify(keyboardData);
                    checkChanges();
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, 
                        timer: 3000, background: '#0f172a', color: '#fff'
                    });
                    Toast.fire({icon: 'success', title: 'با موفقیت ذخیره شد'});
                }
            })
            .catch(err => {
                checkChanges();
                SwalPro.fire({icon: 'error', title: 'خطا در ارتباط با سرور'});
            });
        }

        // شروع
        render();

    </script>
</body>
</html>