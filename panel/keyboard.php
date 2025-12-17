<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// بررسی دسترسی ادمین
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// محاسبه مسیرها
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDirectory = str_replace('\\', '/', dirname($scriptName));
$applicationBasePath = str_replace('\\', '/', dirname($scriptDirectory));
$applicationBasePath = rtrim($applicationBasePath, '/');
if ($applicationBasePath === '/' || $applicationBasePath === '.' || $applicationBasePath === '\\') {
    $applicationBasePath = '';
}

// منطق ذخیره‌سازی (POST)
$keyboard = json_decode(file_get_contents("php://input"),true);
$method = $_SERVER['REQUEST_METHOD'];

if($method == "POST" && is_array($keyboard)){
    $keyboardmain = ['keyboard' => []];
    $keyboardmain['keyboard'] = $keyboard;
    update("setting","keyboardmain",json_encode($keyboardmain),null,null);
    echo json_encode(['status' => 'success']); 
    exit;
}

// منطق ریست کردن (GET) - بازگشت به متغیرهای اصلی ربات
$action = filter_input(INPUT_GET, 'action');
if($action === "reaset"){
    // استفاده از کلمات کلیدی سیستمی ربات
    $defaultKeyboard = json_encode([
        "keyboard" => [
            [["text" => "text_sell"], ["text" => "text_extend"]],
            [["text" => "text_usertest"], ["text" => "text_wheel_luck"]],
            [["text" => "text_Purchased_services"], ["text" => "accountwallet"]],
            [["text" => "text_affiliates"], ["text" => "text_Tariff_list"]],
            [["text" => "text_support"], ["text" => "text_help"]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    update("setting","keyboardmain",$defaultKeyboard,null,null);
    header('Location: keyboard.php');
    exit;
}

// دریافت دیتای فعلی
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
         // فال‌بک به متغیرهای سیستمی
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
} catch (Exception $e) { $currentKeyboardJSON = '[]'; }
?>

<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>پنل مدیریت حرفه‌ای کیبورد</title>
    
    <!-- کتابخانه‌ها -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* تم رنگی فوق حرفه‌ای */
        :root {
            --bg-body: #09090b;
            --bg-panel: #18181b;
            --bg-card: #27272a;
            --accent-primary: #6366f1; /* Indigo */
            --accent-secondary: #ec4899; /* Pink */
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --border-subtle: #3f3f46;
        }

        @font-face {
            font-family: 'yekan';
            src: url('fonts/Vazir.eot');
            src: url('fonts/Vazir.eot#iefix') format('embedded-opentype'),
                 url('fonts/Vazir.woff') format('woff'),
                 url('fonts/Vazir.ttf') format('truetype');
            font-weight: normal;
        }
        
        body {
            font-family: 'yekan', 'Vazirmatn', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-primary);
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- UI Elements --- */
        .glass-panel {
            background: rgba(24, 24, 27, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* دکمه‌های مدرن */
        .btn-modern {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-modern:active { transform: scale(0.96); }
        .btn-modern::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(255,255,255,0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .btn-modern:hover::after { opacity: 1; }

        /* --- Layout --- */
        .main-container {
            display: flex;
            height: 100%;
            overflow: hidden;
        }

        /* --- PREVIEW PANE (Left) --- */
        .preview-pane {
            width: 450px;
            background: radial-gradient(circle at top left, #1c1c2e, #09090b);
            border-left: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
            z-index: 10;
        }

        .phone-mockup {
            width: 370px;
            height: 740px;
            background: #000;
            border-radius: 50px;
            box-shadow: 
                0 0 0 14px #2e2e30,
                0 0 0 16px #1a1a1a,
                0 50px 100px -20px rgba(0,0,0,0.7);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            transform: scale(0.95);
            transition: transform 0.5s;
        }

        .notch-island {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 35px;
            background: #000;
            border-radius: 20px;
            z-index: 50;
        }

        .tg-header {
            background: #1c242f; /* Telegram Dark Header */
            padding: 50px 20px 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            border-bottom: 1px solid #000;
        }

        .tg-chat {
            flex: 1;
            background-color: #0f1621;
            /* Telegram Pattern */
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 10px;
            overflow: hidden;
        }

        .tg-bubble {
            background: #2b5278;
            color: white;
            padding: 10px 15px;
            border-radius: 16px;
            border-top-left-radius: 4px;
            max-width: 85%;
            margin: 0 15px 15px 15px;
            font-size: 14px;
            line-height: 1.5;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            cursor: text;
            position: relative;
        }
        .tg-bubble:hover::before {
            content: '✎';
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--accent-primary);
            width: 20px; height: 20px;
            border-radius: 50%;
            font-size: 10px;
            display: flex; align-items: center; justify-content: center;
        }

        .tg-keyboard {
            background: #1c242f;
            padding: 6px;
            min-height: 200px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
        }

        .tg-key {
            background: #2b5278;
            color: white;
            border-radius: 6px;
            margin: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 2px 0 rgba(0,0,0,0.2);
            padding: 10px 4px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* --- EDITOR PANE (Right) --- */
        .editor-pane {
            flex: 1;
            background: var(--bg-body);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }
        
        /* Grid background effect */
        .editor-pane::before {
            content: '';
            position: absolute;
            width: 100%; height: 100%;
            background-image: linear-gradient(var(--border-subtle) 1px, transparent 1px),
            linear-gradient(90deg, var(--border-subtle) 1px, transparent 1px);
            background-size: 40px 40px;
            opacity: 0.05;
            pointer-events: none;
        }

        .editor-header {
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
        }

        .editor-workspace {
            flex: 1;
            overflow-y: auto;
            padding: 20px 40px 100px 40px;
            scroll-behavior: smooth;
            z-index: 10;
        }

        /* --- Edit Row & Buttons --- */
        .row-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            position: relative;
            transition: all 0.2s;
        }
        .row-wrapper:hover {
            border-color: #52525b;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .row-drag-handle {
            position: absolute;
            left: -30px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: grab;
            padding: 10px;
            opacity: 0;
            transition: 0.2s;
        }
        .row-wrapper:hover .row-drag-handle { opacity: 1; left: -25px; }

        .key-btn {
            flex: 1;
            min-width: 140px;
            background: #18181b;
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: grab;
            user-select: none;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .key-btn::before {
            content: ''; position: absolute; left: 0; top: 0; width: 4px; height: 100%;
            background: var(--accent-primary); opacity: 0; transition: 0.2s;
        }
        .key-btn:hover {
            background: #27272a;
            border-color: #52525b;
        }
        .key-btn:hover::before { opacity: 1; }

        .key-actions {
            display: flex; gap: 5px; opacity: 0; transition: 0.2s;
        }
        .key-btn:hover .key-actions { opacity: 1; }

        .action-mini-btn {
            width: 24px; height: 24px; border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; cursor: pointer; color: var(--text-secondary);
            background: rgba(255,255,255,0.05);
        }
        .action-mini-btn:hover { color: white; background: var(--accent-primary); }
        .action-mini-btn.del:hover { background: #ef4444; }

        .key-meta {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .key-title {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #fff;
            direction: ltr; /* Force LTR for variable names */
        }
        .key-desc {
            font-size: 11px;
            color: #71717a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Add Buttons */
        .add-key-inline {
            width: 40px;
            border: 1px dashed var(--border-subtle);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary);
            cursor: pointer;
            transition: 0.2s;
        }
        .add-key-inline:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--accent-primary);
            color: var(--accent-primary);
        }

        .fab-add-row {
            width: 100%;
            padding: 15px;
            border: 2px dashed var(--border-subtle);
            border-radius: 12px;
            color: var(--text-secondary);
            font-weight: bold;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            cursor: pointer;
            transition: 0.2s;
        }
        .fab-add-row:hover {
            border-color: var(--accent-primary);
            color: var(--accent-primary);
            background: rgba(99, 102, 241, 0.05);
        }

        /* Save Button Indicator */
        .save-indicator {
            width: 10px; height: 10px; background: #ef4444;
            border-radius: 50%; display: none; margin-left: auto;
            box-shadow: 0 0 10px #ef4444;
        }
        .btn-save.dirty .save-indicator { display: block; }
        .btn-save.dirty { border: 1px solid rgba(239, 68, 68, 0.5); }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 3px; }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .preview-pane { display: none; } /* On mobile, maybe hide or tabulate */
            .editor-header { padding: 15px 20px; }
            .editor-workspace { padding: 15px 20px; }
        }
    </style>
  </head>
  <body>

    <div class="main-container">
        
        <!-- LEFT: PREVIEW -->
        <div class="preview-pane glass-panel hidden lg:flex">
            <div class="absolute top-5 left-5 text-xs font-bold text-gray-500 uppercase tracking-widest">
                Bot Realtime Preview
            </div>

            <div class="phone-mockup animate__animated animate__fadeInLeft">
                <div class="notch-island"></div>
                
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right opacity-70"></i>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-sm font-bold shadow-lg">MB</div>
                    <div>
                        <div class="font-bold text-sm">Mirza Bot</div>
                        <div class="text-xs text-indigo-300">bot</div>
                    </div>
                </div>

                <div class="tg-chat">
                    <div class="tg-bubble animate__animated animate__fadeInUp" contenteditable="true" title="برای ویرایش کلیک کنید">
                        سلام! من ربات میرزا هستم. 👋<br>
                        (نمایش دکمه‌های سیستمی)
                    </div>
                </div>

                <div id="preview-container" class="tg-keyboard flex flex-col justify-end">
                    <!-- Rendered Keys -->
                </div>
            </div>
            
            <div class="mt-6 flex gap-2">
                <button onclick="copyJSON()" class="text-xs text-gray-500 hover:text-white transition flex items-center gap-1 px-3 py-1 rounded border border-gray-800 hover:border-gray-600">
                    <i class="fa-regular fa-copy"></i> کپی JSON
                </button>
            </div>
        </div>

        <!-- RIGHT: EDITOR -->
        <div class="editor-pane">
            <div class="editor-header glass-panel">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center border border-indigo-500/30">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">ویرایشگر کیبورد</h1>
                        <div class="flex items-center gap-2 text-xs text-gray-400">
                            <span id="row-count">0</span> سطر
                            <span class="w-1 h-1 bg-gray-600 rounded-full"></span>
                            <span id="btn-count">0</span> دکمه
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="index.php" class="btn-modern px-4 py-2 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 text-sm font-medium flex items-center gap-2">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span class="hidden sm:inline">بازگشت</span>
                    </a>
                    <a href="keyboard.php?action=reaset" onclick="return confirm('همه تنظیمات ریست شود؟')" class="btn-modern px-4 py-2 rounded-lg border border-red-900/30 text-red-400 hover:bg-red-900/10 text-sm font-medium flex items-center gap-2">
                        <i class="fa-solid fa-rotate-right"></i>
                    </a>
                    <button onclick="saveKeyboard()" id="btn-save" class="btn-save btn-modern px-6 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/20 text-sm font-bold flex items-center gap-3">
                        <span>ذخیره تغییرات</span>
                        <span class="save-indicator"></span>
                    </button>
                </div>
            </div>

            <div class="editor-workspace">
                <div id="editor-container" class="max-w-4xl mx-auto pb-8">
                    <!-- Rows go here -->
                </div>

                <div class="max-w-4xl mx-auto pb-20">
                    <button onclick="addRow()" class="fab-add-row btn-modern">
                        <i class="fa-solid fa-plus-circle text-xl"></i>
                        افزودن سطر جدید
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Data Initialization
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardRows)) keyboardRows = [];
        
        let initialDataStr = JSON.stringify(keyboardRows);
        let isDirty = false;

        // دیکشنری ترجمه برای نمایش (صرفاً بصری)
        const keyMap = {
            'text_sell': 'خرید سرویس',
            'text_extend': 'تمدید سرویس',
            'text_usertest': 'تست رایگان',
            'text_wheel_luck': 'گردونه شانس',
            'text_Purchased_services': 'سرویس‌های من',
            'accountwallet': 'کیف پول',
            'text_affiliates': 'همکاری در فروش',
            'text_Tariff_list': 'لیست تعرفه‌ها',
            'text_support': 'پشتیبانی',
            'text_help': 'راهنما'
        };

        const editorContainer = document.getElementById('editor-container');
        const previewContainer = document.getElementById('preview-container');
        const saveBtn = document.getElementById('btn-save');

        // SweetAlert Configuration
        const SwalTheme = Swal.mixin({
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#ef4444',
            background: '#18181b',
            color: '#f4f4f5',
            showClass: { popup: 'animate__animated animate__zoomIn' },
            hideClass: { popup: 'animate__animated animate__zoomOut' }
        });

        // --- Main Render Function ---
        function render() {
            renderEditor();
            renderPreview();
            updateStats();
            checkDirty();
        }

        // --- Editor Render ---
        function renderEditor() {
            editorContainer.innerHTML = '';
            
            if (keyboardRows.length === 0) {
                editorContainer.innerHTML = `
                    <div class="text-center py-20 opacity-30">
                        <i class="fa-solid fa-keyboard text-6xl mb-4"></i>
                        <p>هیچ دکمه‌ای وجود ندارد</p>
                    </div>
                `;
            }

            keyboardRows.forEach((row, rowIndex) => {
                const rowEl = document.createElement('div');
                rowEl.className = 'row-wrapper animate__animated animate__fadeIn';
                rowEl.style.animationDuration = '0.3s';
                
                // Drag Handle
                const handle = document.createElement('div');
                handle.className = 'row-drag-handle';
                handle.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
                rowEl.appendChild(handle);

                // Buttons in Row
                row.forEach((btn, btnIndex) => {
                    // دریافت ترجمه (اگر وجود نداشت خود متن را نشان بده)
                    const desc = keyMap[btn.text] || 'دکمه سفارشی';

                    const btnEl = document.createElement('div');
                    btnEl.className = 'key-btn';
                    btnEl.innerHTML = `
                        <div class="flex items-center gap-3 overflow-hidden" style="width: 100%;">
                            <i class="fa-solid fa-code text-gray-600 text-xs"></i>
                            <div class="key-meta">
                                <span class="key-title" title="${btn.text}">${btn.text}</span>
                                <span class="key-desc">${desc}</span>
                            </div>
                        </div>
                        <div class="key-actions">
                            <div class="action-mini-btn" onclick="editButton(${rowIndex}, ${btnIndex})" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                            <div class="action-mini-btn del" onclick="deleteButton(${rowIndex}, ${btnIndex})" title="Delete">
                                <i class="fa-solid fa-xmark"></i>
                            </div>
                        </div>
                    `;
                    rowEl.appendChild(btnEl);
                });

                // Add Button (Inline)
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'add-key-inline ignore-elements';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                    addBtn.onclick = () => addButton(rowIndex);
                    addBtn.title = "Add Button";
                    rowEl.appendChild(addBtn);
                }

                // Delete Row if Empty
                if (row.length === 0) {
                    const emptyState = document.createElement('div');
                    emptyState.className = 'flex-1 text-center py-2 text-xs text-red-400 cursor-pointer border border-dashed border-red-500/20 rounded hover:bg-red-500/10 transition';
                    emptyState.innerHTML = '<i class="fa-solid fa-trash mr-2"></i> سطر خالی (حذف)';
                    emptyState.onclick = () => deleteRow(rowIndex);
                    rowEl.appendChild(emptyState);
                }

                editorContainer.appendChild(rowEl);
            });

            initSortable();
        }

        // --- Preview Render ---
        function renderPreview() {
            previewContainer.innerHTML = '';
            
            keyboardRows.forEach(row => {
                const rowEl = document.createElement('div');
                rowEl.className = 'flex w-full gap-[4px] mb-[4px]';
                
                row.forEach(btn => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'tg-key flex-1';
                    // در پیش‌نمایش هم نام متغیر را نشان می‌دهیم تا دقیق باشد
                    btnEl.innerText = btn.text;
                    rowEl.appendChild(btnEl);
                });
                
                if(row.length > 0) previewContainer.appendChild(rowEl);
            });
        }

        // --- Logic & Helpers ---
        function updateStats() {
            document.getElementById('row-count').innerText = keyboardRows.length;
            let btnCount = 0;
            keyboardRows.forEach(r => btnCount += r.length);
            document.getElementById('btn-count').innerText = btnCount;
        }

        function checkDirty() {
            const currentStr = JSON.stringify(keyboardRows);
            isDirty = currentStr !== initialDataStr;
            if(isDirty) {
                saveBtn.classList.add('dirty');
                saveBtn.querySelector('span').innerText = 'ذخیره تغییرات *';
            } else {
                saveBtn.classList.remove('dirty');
                saveBtn.querySelector('span').innerText = 'ذخیره تغییرات';
            }
        }

        // --- Actions ---
        function addRow() {
            keyboardRows.push([{text: 'text_new'}]);
            render();
            // Scroll to bottom
            setTimeout(() => {
                document.querySelector('.editor-workspace').scrollTop = document.querySelector('.editor-workspace').scrollHeight;
            }, 50);
        }

        function deleteRow(idx) {
            keyboardRows.splice(idx, 1);
            render();
        }

        async function addButton(rowIdx) {
            const { value: text } = await SwalTheme.fire({
                title: 'افزودن دکمه (نام متغیر)',
                input: 'text',
                inputValue: 'text_new',
                showCancelButton: true,
                confirmButtonText: 'افزودن',
                cancelButtonText: 'لغو'
            });
            if(text) {
                keyboardRows[rowIdx].push({text});
                render();
            }
        }

        function deleteButton(rowIdx, btnIdx) {
            keyboardRows[rowIdx].splice(btnIdx, 1);
            render();
        }

        async function editButton(rowIdx, btnIdx) {
            const current = keyboardRows[rowIdx][btnIdx].text;
            const { value: text } = await SwalTheme.fire({
                title: 'ویرایش نام متغیر',
                input: 'text',
                inputValue: current,
                showCancelButton: true,
                confirmButtonText: 'ذخیره',
                cancelButtonText: 'لغو'
            });
            if(text) {
                keyboardRows[rowIdx][btnIdx].text = text;
                render();
            }
        }

        function copyJSON() {
            navigator.clipboard.writeText(JSON.stringify({keyboard: keyboardRows}, null, 2));
            SwalTheme.fire({
                icon: 'success',
                title: 'کپی شد',
                text: 'ساختار JSON در کلیپ‌بورد کپی شد.',
                timer: 1500,
                showConfirmButton: false,
                backdrop: false
            });
        }

        // --- Drag & Drop ---
        function initSortable() {
            // Rows
            new Sortable(editorContainer, {
                animation: 200,
                handle: '.row-drag-handle',
                ghostClass: 'opacity-50',
                onEnd: function (evt) {
                    const item = keyboardRows.splice(evt.oldIndex, 1)[0];
                    keyboardRows.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // Buttons
            document.querySelectorAll('.row-wrapper').forEach((rowEl, rIndex) => {
                new Sortable(rowEl, {
                    group: 'shared',
                    animation: 200,
                    draggable: '.key-btn',
                    filter: '.ignore-elements',
                    ghostClass: 'opacity-50',
                    onEnd: function (evt) {
                        // Reconstruct array from DOM to handle complex drag across rows
                        rebuildDataFromDOM();
                    }
                });
            });
        }

        function rebuildDataFromDOM() {
            const newRows = [];
            const domRows = editorContainer.querySelectorAll('.row-wrapper');
            domRows.forEach(domRow => {
                const rowData = [];
                // توجه: ما از تایتل المان برای گرفتن متن اصلی استفاده می‌کنیم
                const buttons = domRow.querySelectorAll('.key-title');
                buttons.forEach(span => rowData.push({text: span.getAttribute('title')}));
                
                if (rowData.length > 0 || domRow.querySelector('.add-key-inline')) {
                    newRows.push(rowData);
                }
            });
            keyboardRows = newRows;
            render(); 
        }

        // --- Save ---
        function saveKeyboard() {
            const btnContent = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            saveBtn.disabled = true;

            fetch('keyboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(keyboardRows)
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.innerHTML = btnContent;
                saveBtn.disabled = false;
                if(data.status === 'success') {
                    initialDataStr = JSON.stringify(keyboardRows);
                    checkDirty();
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false,
                        timer: 3000, background: '#18181b', color: '#fff',
                        timerProgressBar: true
                    });
                    Toast.fire({ icon: 'success', title: 'تغییرات ذخیره شد' });
                }
            })
            .catch(err => {
                saveBtn.innerHTML = btnContent;
                saveBtn.disabled = false;
                SwalTheme.fire({icon:'error', title:'خطا در ارتباط'});
            });
        }

        window.onbeforeunload = function() {
            if (isDirty) return "تغییرات ذخیره نشده دارید. آیا مطمئن هستید؟";
        };

        // Initialize
        render();

    </script>
  </body>
</html>