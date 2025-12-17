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

// منطق ریست کردن (GET) - با متن‌های فارسی و آیکون
$action = filter_input(INPUT_GET, 'action');
if($action === "reaset"){
    // کیبورد پیش‌فرض با متن‌های فارسی و جذاب
    $defaultKeyboard = json_encode([
        "keyboard" => [
            [
                ["text" => "🛍 خرید سرویس"],
                ["text" => "🔄 تمدید سرویس"]
            ],
            [
                ["text" => "👤 حساب کاربری"],
                ["text" => "💰 کیف پول"]
            ],
            [
                ["text" => "🔥 تست رایگان"],
                ["text" => "🎰 گردونه شانس"]
            ],
            [
                ["text" => "🤝 همکاری در فروش"],
                ["text" => "📊 لیست تعرفه‌ها"]
            ],
            [
                ["text" => "🎧 پشتیبانی"],
                ["text" => "📚 راهنما"]
            ]
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
        // اگر دیتایی نبود، از همین ساختار فارسی استفاده کن
         $def = [
            "keyboard" => [
                [["text" => "🛍 خرید سرویس"], ["text" => "🔄 تمدید سرویس"]],
                [["text" => "👤 حساب کاربری"], ["text" => "💰 کیف پول"]],
                [["text" => "🔥 تست رایگان"], ["text" => "🎰 گردونه شانس"]],
                [["text" => "🤝 همکاری در فروش"], ["text" => "📊 لیست تعرفه‌ها"]],
                [["text" => "🎧 پشتیبانی"], ["text" => "📚 راهنما"]]
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>مدیریت کیبورد حرفه‌ای</title>
    
    <!-- کتابخانه‌ها -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --bg-dark: #0f172a;
            --bg-panel: #1e293b;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --text-main: #f8fafc;
            --border-color: #334155;
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
            background-color: var(--bg-dark);
            color: var(--text-main);
            overflow-x: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- Layout --- */
        .main-container {
            display: flex;
            height: 100%;
            overflow: hidden;
        }

        /* --- Left Side: Preview --- */
        .preview-pane {
            width: 420px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            flex-shrink: 0;
            box-shadow: inset 10px 0 20px rgba(0,0,0,0.2);
        }

        .phone-frame {
            width: 360px;
            height: 720px;
            background: #000;
            border-radius: 45px;
            box-shadow: 
                0 0 0 12px #334155,
                0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            border: 4px solid #1e293b;
        }

        .phone-notch {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 28px;
            background: #334155;
            border-bottom-left-radius: 18px;
            border-bottom-right-radius: 18px;
            z-index: 20;
        }

        .telegram-preview-header {
            background: #17212b;
            padding: 40px 15px 12px 15px;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #0f1318;
        }

        .telegram-preview-chat {
            flex: 1;
            background-color: #0e1621;
            /* Telegram Dark Pattern */
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 10px;
        }

        .telegram-preview-keyboard {
            background: #17212b;
            padding: 5px;
            min-height: 180px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }

        .preview-btn {
            background: #2b5278; /* Classic Telegram Dark Button */
            color: #fff;
            border-radius: 6px;
            padding: 10px 4px;
            text-align: center;
            font-size: 13px;
            margin: 2px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }

        /* --- Right Side: Editor --- */
        .editor-pane {
            flex: 1;
            background: var(--bg-panel);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
        }

        .editor-header {
            padding: 24px 40px;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .editor-workspace {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
            scroll-behavior: smooth;
        }

        /* Editor Rows */
        .edit-row {
            background: rgba(51, 65, 85, 0.6);
            border: 1px solid rgba(71, 85, 105, 0.6);
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(4px);
        }
        .edit-row:hover {
            border-color: #64748b;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            background: rgba(51, 65, 85, 0.9);
            transform: translateY(-2px);
        }

        .row-handle {
            position: absolute;
            left: -14px;
            top: 50%;
            transform: translateY(-50%) translateX(-100%);
            color: #64748b;
            cursor: grab;
            padding: 12px;
            opacity: 0.6;
            transition: 0.2s;
        }
        .row-handle:hover { color: #94a3b8; opacity: 1; }
        .row-handle:active { cursor: grabbing; }

        /* Editor Buttons */
        .edit-btn {
            flex: 1;
            min-width: 140px;
            background: linear-gradient(145deg, #1e293b, #172033);
            border: 1px solid #475569;
            border-radius: 10px;
            padding: 14px 16px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: grab;
            transition: all 0.2s;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .edit-btn:hover {
            background: #27354f;
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .edit-btn:active { cursor: grabbing; transform: scale(0.99); }

        .btn-text {
            font-size: 15px;
            font-weight: 500;
            max-width: 75%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #f1f5f9;
        }

        .btn-actions {
            display: flex;
            gap: 8px;
            opacity: 0.3;
            transition: opacity 0.2s;
            background: rgba(30, 41, 59, 0.8);
            border-radius: 6px;
            padding: 2px;
        }
        .edit-btn:hover .btn-actions { opacity: 1; }

        .action-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }
        .action-edit:hover { background: var(--accent); color: white; }
        .action-delete:hover { background: #ef4444; color: white; }

        /* Add Buttons */
        .add-btn-in-row {
            width: 45px;
            background: rgba(255,255,255,0.03);
            border: 1px dashed rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .add-btn-in-row:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.5);
            transform: scale(1.05);
        }

        .btn-add-row-big {
            width: 100%;
            padding: 20px;
            border: 2px dashed #475569;
            border-radius: 16px;
            background: rgba(30, 41, 59, 0.5);
            color: #94a3b8;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .btn-add-row-big:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(59, 130, 246, 0.08);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2);
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { 
            background: #475569; 
            border-radius: 5px; 
            border: 2px solid var(--bg-dark);
        }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }

        /* SweetAlert Dark */
        div:where(.swal2-container) div:where(.swal2-popup) {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            border-radius: 16px !important;
        }
        div:where(.swal2-container) .swal2-title, 
        div:where(.swal2-container) .swal2-html-container {
            color: #f8fafc !important;
        }
        div:where(.swal2-container) .swal2-input {
            color: #fff !important;
            background: #0f172a !important;
            border-color: #334155 !important;
            border-radius: 8px !important;
        }
    </style>
  </head>
  <body>

    <div class="main-container">
        
        <!-- SIDEBAR: PREVIEW (LEFT) -->
        <div class="preview-pane hidden lg:flex">
            <div class="mb-6 text-gray-400 text-xs font-bold tracking-[0.2em] uppercase opacity-70">
                <i class="fa-solid fa-eye mr-2"></i> پیش‌نمایش زنده ربات
            </div>
            
            <div class="phone-frame animate__animated animate__fadeInLeft">
                <div class="phone-notch"></div>
                
                <!-- Telegram Header -->
                <div class="telegram-preview-header">
                    <i class="fa-solid fa-arrow-right text-gray-400 cursor-pointer hover:text-white"></i>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/30">
                        MB
                    </div>
                    <div>
                        <div class="font-bold text-sm text-white">Mirza Bot</div>
                        <div class="text-xs text-blue-300 font-medium">bot</div>
                    </div>
                </div>

                <!-- Chat Body -->
                <div class="telegram-preview-chat flex-1">
                    <div class="bg-[#2b5278] px-5 py-3 rounded-2xl rounded-tl-none text-sm text-white shadow-lg max-w-[85%] mb-4 mx-4 animate__animated animate__fadeInUp">
                        سلام! من ربات میرزا هستم. 👋<br>
                        از کیبورد زیر استفاده کنید:
                    </div>
                </div>

                <!-- Live Keyboard Preview -->
                <div id="preview-container" class="telegram-preview-keyboard flex flex-col justify-end">
                    <!-- Buttons Render Here -->
                </div>
            </div>
        </div>

        <!-- MAIN: EDITOR (RIGHT) -->
        <div class="editor-pane">
            <!-- Top Bar -->
            <div class="editor-header">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow-lg shadow-blue-600/20 border border-blue-500/30">
                        <i class="fa-solid fa-keyboard text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">ویرایشگر کیبورد</h1>
                        <p class="text-xs text-gray-400 font-medium mt-1">طراحی رابط کاربری ربات با کشیدن و رها کردن</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <a href="index.php" class="px-5 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition text-sm font-bold flex items-center gap-2">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> بازگشت
                    </a>
                    <a href="keyboard.php?action=reaset" onclick="return confirm('آیا مطمئن هستید؟ تمام تنظیمات به حالت اولیه با متن‌های فارسی باز می‌گردد.')" class="px-5 py-2.5 rounded-xl border border-red-500/30 text-red-400 hover:bg-red-500/10 transition text-sm font-bold flex items-center gap-2">
                        <i class="fa-solid fa-rotate-right"></i> ریست پیش‌فرض
                    </a>
                    <button onclick="saveKeyboard()" id="btn-save" class="px-7 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-600/30 transition font-bold flex items-center gap-2 transform active:scale-95">
                        <i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات
                    </button>
                </div>
            </div>

            <!-- Workspace -->
            <div class="editor-workspace">
                <div id="editor-rows-container" class="max-w-4xl mx-auto pb-10 min-h-[300px]">
                    <!-- Edit Rows Render Here -->
                </div>

                <div class="max-w-4xl mx-auto pb-20">
                    <button onclick="addRow()" class="btn-add-row-big group">
                        <span class="w-12 h-12 rounded-full bg-slate-700 flex items-center justify-center group-hover:bg-blue-600 transition duration-300 shadow-lg">
                            <i class="fa-solid fa-plus text-white text-xl"></i>
                        </span>
                        افزودن سطر جدید
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        // دیتای اولیه از PHP
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardRows)) keyboardRows = [];

        // المان‌های DOM
        const editorContainer = document.getElementById('editor-rows-container');
        const previewContainer = document.getElementById('preview-container');

        // SweetAlert Config
        const SwalDark = Swal.mixin({
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
            background: '#1e293b',
            color: '#fff',
            showClass: { popup: 'animate__animated animate__fadeInDown' },
            hideClass: { popup: 'animate__animated animate__fadeOutUp' }
        });

        // تابع اصلی رندر (هر دو بخش را آپدیت می‌کند)
        function render() {
            renderEditor();
            renderPreview();
        }

        // 1. رندر بخش ویرایشگر (سمت راست)
        function renderEditor() {
            editorContainer.innerHTML = '';

            if (keyboardRows.length === 0) {
                editorContainer.innerHTML = `
                    <div class="text-center text-gray-500 py-10 animate__animated animate__fadeIn">
                        <i class="fa-solid fa-layer-group text-4xl mb-3 opacity-50"></i>
                        <p>هیچ دکمه‌ای وجود ندارد. با دکمه پایین یک سطر اضافه کنید.</p>
                    </div>
                `;
            }

            keyboardRows.forEach((row, rowIndex) => {
                const rowEl = document.createElement('div');
                rowEl.className = 'edit-row animate__animated animate__fadeInUp';
                rowEl.style.animationDelay = `${rowIndex * 0.05}s`; // Staggered animation
                rowEl.dataset.rowIndex = rowIndex;

                // هندل درگ برای سطر
                const handle = document.createElement('div');
                handle.className = 'row-handle';
                handle.innerHTML = '<i class="fa-solid fa-grip-vertical text-xl"></i>';
                handle.title = 'برای جابجایی سطر بکشید';
                rowEl.appendChild(handle);

                // دکمه‌ها
                row.forEach((btn, btnIndex) => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'edit-btn group';
                    btnEl.innerHTML = `
                        <div class="btn-text" title="${btn.text}">
                            <i class="fa-regular fa-keyboard mr-2 opacity-50 text-xs"></i>${btn.text}
                        </div>
                        <div class="btn-actions">
                            <div class="action-icon action-edit" onclick="editButton(${rowIndex}, ${btnIndex})" title="ویرایش متن">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                            <div class="action-icon action-delete" onclick="deleteButton(${rowIndex}, ${btnIndex})" title="حذف دکمه">
                                <i class="fa-solid fa-trash-can"></i>
                            </div>
                        </div>
                    `;
                    rowEl.appendChild(btnEl);
                });

                // دکمه افزودن آیتم در سطر
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'add-btn-in-row ignore-elements';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus text-sm text-gray-400"></i>';
                    addBtn.onclick = () => addButton(rowIndex);
                    addBtn.title = "افزودن دکمه به این سطر";
                    rowEl.appendChild(addBtn);
                }

                // دکمه حذف سطر (اگر خالی باشد یا دکمه مخصوص حذف)
                if (row.length === 0) {
                    const emptyInfo = document.createElement('div');
                    emptyInfo.className = 'flex-1 text-center text-sm text-red-400 border border-dashed border-red-500/30 p-3 rounded-lg cursor-pointer hover:bg-red-500/10 transition flex items-center justify-center gap-2';
                    emptyInfo.innerHTML = '<i class="fa-solid fa-trash-can"></i> سطر خالی است - برای حذف کلیک کنید';
                    emptyInfo.onclick = () => deleteRow(rowIndex);
                    rowEl.appendChild(emptyInfo);
                }

                editorContainer.appendChild(rowEl);
            });

            initSortable();
        }

        // 2. رندر بخش پیش‌نمایش (سمت چپ)
        function renderPreview() {
            previewContainer.innerHTML = '';
            
            keyboardRows.forEach(row => {
                const rowEl = document.createElement('div');
                rowEl.className = 'flex w-full gap-[2px] mb-[2px]';
                
                row.forEach(btn => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'preview-btn flex-1 truncate';
                    btnEl.innerText = btn.text;
                    rowEl.appendChild(btnEl);
                });

                if (row.length > 0) {
                    previewContainer.appendChild(rowEl);
                }
            });
        }

        // فعال‌سازی Drag & Drop
        function initSortable() {
            // جابجایی سطرها
            new Sortable(editorContainer, {
                animation: 200,
                handle: '.row-handle',
                ghostClass: 'opacity-50',
                onEnd: function (evt) {
                    const item = keyboardRows.splice(evt.oldIndex, 1)[0];
                    keyboardRows.splice(evt.newIndex, 0, item);
                    render(); // رندر مجدد برای همگام‌سازی
                }
            });

            // جابجایی دکمه‌ها داخل سطر
            document.querySelectorAll('.edit-row').forEach(rowEl => {
                new Sortable(rowEl, {
                    group: 'shared', // اجازه جابجایی بین سطرها
                    animation: 200,
                    filter: '.ignore-elements', // نادیده گرفتن دکمه +
                    draggable: '.edit-btn',
                    ghostClass: 'opacity-50',
                    onEnd: function (evt) {
                        updateStateFromDOM();
                    }
                });
            });
        }

        // تابع مهم: خواندن وضعیت فعلی از ادیتور و آپدیت متغیر اصلی
        function updateStateFromDOM() {
            const newRows = [];
            const rows = editorContainer.querySelectorAll('.edit-row');
            
            rows.forEach(row => {
                const rowData = [];
                const buttons = row.querySelectorAll('.edit-btn .btn-text');
                buttons.forEach(btn => {
                    // متن داخل title ذخیره شده
                    rowData.push({ text: btn.getAttribute('title') });
                });
                
                // حفظ سطر اگر دکمه دارد یا دکمه افزودن دارد (یعنی سطر خالی نیست)
                if (rowData.length > 0 || row.querySelectorAll('.add-btn-in-row').length > 0) {
                    newRows.push(rowData);
                }
            });
            
            keyboardRows = newRows;
            renderPreview(); 
        }

        // --- Actions ---

        function addRow() {
            keyboardRows.push([{text: 'دکمه جدید'}]);
            render();
            // اسکرول نرم به پایین
            setTimeout(() => {
                editorContainer.scrollTo({ top: editorContainer.scrollHeight, behavior: 'smooth' });
            }, 100);
        }

        function deleteRow(index) {
            keyboardRows.splice(index, 1);
            render();
        }

        async function addButton(rowIndex) {
            const { value: text } = await SwalDark.fire({
                title: 'افزودن دکمه جدید',
                input: 'text',
                inputPlaceholder: 'مثال: 🛍 خرید سرویس',
                confirmButtonText: '<i class="fa-solid fa-plus ml-1"></i> افزودن',
                showCancelButton: true,
                cancelButtonText: 'لغو'
            });

            if (text) {
                keyboardRows[rowIndex].push({text: text});
                render();
            }
        }

        function deleteButton(rowIndex, btnIndex) {
            keyboardRows[rowIndex].splice(btnIndex, 1);
            render();
        }

        async function editButton(rowIndex, btnIndex) {
            const currentText = keyboardRows[rowIndex][btnIndex].text;
            const { value: text } = await SwalDark.fire({
                title: 'ویرایش متن دکمه',
                input: 'text',
                inputValue: currentText,
                confirmButtonText: '<i class="fa-solid fa-check ml-1"></i> ذخیره',
                showCancelButton: true,
                cancelButtonText: 'لغو'
            });

            if (text) {
                keyboardRows[rowIndex][btnIndex].text = text;
                render();
            }
        }

        function saveKeyboard() {
            // آپدیت نهایی
            updateStateFromDOM();
            
            const btn = document.getElementById('btn-save');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> در حال ذخیره...';
            btn.disabled = true;
            btn.classList.add('opacity-75');

            fetch('keyboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(keyboardRows)
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.classList.remove('opacity-75');
                
                if(data.status === 'success') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#1e293b',
                        color: '#fff'
                    });
                    
                    Toast.fire({
                        icon: 'success',
                        title: 'کیبورد با موفقیت ذخیره شد'
                    });
                }
            })
            .catch(err => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.classList.remove('opacity-75');
                SwalDark.fire({icon: 'error', title: 'خطا در ارتباط با سرور'});
            });
        }

        // شروع
        render();

    </script>
  </body>
</html>