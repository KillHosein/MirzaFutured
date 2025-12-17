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

// منطق ریست کردن (GET)
$action = filter_input(INPUT_GET, 'action');
if($action === "reaset"){
    $defaultKeyboard = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
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
         $def = json_decode('{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}', true);
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
            --text-main: #f1f5f9;
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
            width: 400px;
            background: #0b1120;
            border-left: 1px solid #334155;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            flex-shrink: 0;
            background-image: radial-gradient(#1e293b 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .phone-frame {
            width: 340px;
            height: 680px;
            background: #000;
            border-radius: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 10px #334155;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .phone-notch {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 25px;
            background: #334155;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
            z-index: 20;
        }

        .telegram-preview-header {
            background: #17212b;
            padding: 35px 15px 10px 15px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #000;
        }

        .telegram-preview-chat {
            flex: 1;
            background-color: #0e1621;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .telegram-preview-keyboard {
            background: #17212b;
            padding: 5px;
            min-height: 150px;
        }

        .preview-btn {
            background: #2b5278; /* Classic Telegram Dark Button */
            color: #fff;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
            font-size: 12px;
            margin: 2px;
            box-shadow: 0 1px 0 #00000040;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* --- Right Side: Editor --- */
        .editor-pane {
            flex: 1;
            background: var(--bg-panel);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .editor-header {
            padding: 20px 30px;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .editor-workspace {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }

        /* Editor Rows */
        .edit-row {
            background: #334155;
            border: 1px solid #475569;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            transition: all 0.2s;
        }
        .edit-row:hover {
            border-color: #64748b;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .row-handle {
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%) translateX(-100%);
            color: #64748b;
            cursor: grab;
            padding: 10px;
        }
        .row-handle:hover { color: #94a3b8; }

        /* Editor Buttons */
        .edit-btn {
            flex: 1;
            min-width: 120px;
            background: #1e293b;
            border: 1px solid #475569;
            border-radius: 8px;
            padding: 12px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: grab;
            transition: all 0.2s;
            position: relative;
        }
        .edit-btn:hover {
            background: #283548;
            border-color: #3b82f6;
        }
        .edit-btn:active { cursor: grabbing; }

        .btn-text {
            font-size: 14px;
            font-weight: 500;
            max-width: 80%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-actions {
            display: flex;
            gap: 5px;
            opacity: 0.5;
            transition: opacity 0.2s;
        }
        .edit-btn:hover .btn-actions { opacity: 1; }

        .action-icon {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
        }
        .action-edit:hover { background: #3b82f6; }
        .action-delete:hover { background: #ef4444; }

        /* Add Buttons */
        .add-btn-in-row {
            width: 40px;
            background: rgba(255,255,255,0.05);
            border: 1px dashed rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .add-btn-in-row:hover {
            background: rgba(255,255,255,0.1);
            border-color: #fff;
        }

        .btn-add-row-big {
            width: 100%;
            padding: 15px;
            border: 2px dashed #475569;
            border-radius: 12px;
            background: transparent;
            color: #94a3b8;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-row-big:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* SweetAlert Dark */
        div:where(.swal2-container) div:where(.swal2-popup) {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
        }
        div:where(.swal2-container) .swal2-title, 
        div:where(.swal2-container) .swal2-html-container,
        div:where(.swal2-container) .swal2-input {
            color: #f8fafc !important;
        }
        div:where(.swal2-container) .swal2-input {
            background: #0f172a !important;
            border-color: #334155 !important;
        }
    </style>
  </head>
  <body>

    <div class="main-container">
        
        <!-- SIDEBAR: PREVIEW (LEFT) -->
        <div class="preview-pane hidden lg:flex">
            <div class="mb-4 text-gray-400 text-sm font-bold tracking-wider uppercase">
                <i class="fa-solid fa-mobile-screen mr-2"></i> پیش‌نمایش زنده
            </div>
            
            <div class="phone-frame animate__animated animate__fadeInLeft">
                <div class="phone-notch"></div>
                
                <!-- Telegram Header -->
                <div class="telegram-preview-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-400 to-blue-600 flex items-center justify-center font-bold shadow-lg">
                        MB
                    </div>
                    <div>
                        <div class="font-bold text-sm">Mirza Bot</div>
                        <div class="text-xs text-blue-300">bot</div>
                    </div>
                </div>

                <!-- Chat Body -->
                <div class="telegram-preview-chat flex-1">
                    <div class="bg-[#17212b] px-4 py-2 rounded-lg text-sm text-gray-300 shadow max-w-[80%] text-center">
                        تغییرات شما در پنل سمت راست، بلافاصله اینجا نمایش داده می‌شود.
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
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <i class="fa-solid fa-keyboard text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">ویرایشگر کیبورد</h1>
                        <p class="text-xs text-gray-400">چیدمان را با کشیدن و رها کردن تغییر دهید</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <a href="index.php" class="px-4 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition text-sm font-medium">
                        <i class="fa-solid fa-arrow-right ml-2"></i> بازگشت
                    </a>
                    <a href="keyboard.php?action=reaset" onclick="return confirm('آیا مطمئن هستید؟ تمامی تغییرات حذف می‌شود.')" class="px-4 py-2 rounded-lg border border-red-900/50 text-red-400 hover:bg-red-900/20 transition text-sm font-medium">
                        <i class="fa-solid fa-rotate-right ml-2"></i> ریست
                    </a>
                    <button onclick="saveKeyboard()" id="btn-save" class="px-6 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-600/20 transition font-bold flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات
                    </button>
                </div>
            </div>

            <!-- Workspace -->
            <div class="editor-workspace">
                <div id="editor-rows-container" class="max-w-4xl mx-auto pb-20">
                    <!-- Edit Rows Render Here -->
                </div>

                <div class="max-w-4xl mx-auto">
                    <button onclick="addRow()" class="btn-add-row-big">
                        <i class="fa-solid fa-plus-circle text-xl mb-2 block"></i>
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

            keyboardRows.forEach((row, rowIndex) => {
                const rowEl = document.createElement('div');
                rowEl.className = 'edit-row animate__animated animate__fadeIn';
                rowEl.dataset.rowIndex = rowIndex;

                // هندل درگ برای سطر
                const handle = document.createElement('div');
                handle.className = 'row-handle';
                handle.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
                handle.title = 'جایچایی سطر';
                rowEl.appendChild(handle);

                // دکمه‌ها
                row.forEach((btn, btnIndex) => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'edit-btn group';
                    btnEl.innerHTML = `
                        <div class="btn-text" title="${btn.text}"><i class="fa-regular fa-square mr-2 opacity-50"></i>${btn.text}</div>
                        <div class="btn-actions">
                            <div class="action-icon action-edit" onclick="editButton(${rowIndex}, ${btnIndex})" title="ویرایش">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                            <div class="action-icon action-delete" onclick="deleteButton(${rowIndex}, ${btnIndex})" title="حذف">
                                <i class="fa-solid fa-times"></i>
                            </div>
                        </div>
                    `;
                    rowEl.appendChild(btnEl);
                });

                // دکمه افزودن آیتم در سطر
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'add-btn-in-row ignore-elements';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                    addBtn.onclick = () => addButton(rowIndex);
                    addBtn.title = "افزودن دکمه به این سطر";
                    rowEl.appendChild(addBtn);
                }

                // دکمه حذف سطر (اگر خالی باشد یا دکمه مخصوص حذف)
                if (row.length === 0) {
                    const emptyInfo = document.createElement('div');
                    emptyInfo.className = 'flex-1 text-center text-xs text-red-400 border border-dashed border-red-900/50 p-2 rounded cursor-pointer hover:bg-red-900/10 transition';
                    emptyInfo.innerHTML = '<i class="fa-solid fa-trash mr-1"></i> سطر خالی - برای حذف کلیک کنید';
                    emptyInfo.onclick = () => deleteRow(rowIndex);
                    rowEl.appendChild(emptyInfo);
                } else {
                    // دکمه کوچک حذف سطر در گوشه (اختیاری)
                    // فعلاً با خالی کردن سطر، سطر حذف می‌شود
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
                        // اینجا کمی پیچیده است چون دراگ بین سطرها داریم
                        // ساده‌ترین راه: بازخوانی کل DOM و ساختن مجدد آرایه
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
                    // متن داخل title ذخیره شده برای دسترسی راحت‌تر
                    rowData.push({ text: btn.getAttribute('title') });
                });
                
                // سطر خالی را هم نگه می‌داریم مگر اینکه کاربر حذف کند
                // یا فقط سطرهای دارای دکمه را ذخیره کنیم؟ معمولا سطرهای خالی نباید باشند
                if (rowData.length > 0 || row.querySelectorAll('.add-btn-in-row').length > 0) {
                    newRows.push(rowData);
                }
            });
            
            keyboardRows = newRows;
            renderPreview(); // فقط پیش‌نمایش را آپدیت کن، ادیتور دست نخورد که دراگ خراب نشود
        }

        // --- Actions ---

        function addRow() {
            keyboardRows.push([{text: 'دکمه جدید'}]);
            render();
            // اسکرول به پایین
            setTimeout(() => {
                editorContainer.scrollTop = editorContainer.scrollHeight;
            }, 50);
        }

        function deleteRow(index) {
            keyboardRows.splice(index, 1);
            render();
        }

        async function addButton(rowIndex) {
            const { value: text } = await SwalDark.fire({
                title: 'افزودن دکمه',
                input: 'text',
                inputPlaceholder: 'عنوان دکمه...',
                confirmButtonText: 'افزودن',
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
            // اگر سطر خالی شد، سطر باقی می‌ماند تا کاربر تصمیم بگیرد
            render();
        }

        async function editButton(rowIndex, btnIndex) {
            const currentText = keyboardRows[rowIndex][btnIndex].text;
            const { value: text } = await SwalDark.fire({
                title: 'ویرایش عنوان',
                input: 'text',
                inputValue: currentText,
                confirmButtonText: 'بروزرسانی',
                showCancelButton: true,
                cancelButtonText: 'لغو'
            });

            if (text) {
                keyboardRows[rowIndex][btnIndex].text = text;
                render();
            }
        }

        function saveKeyboard() {
            // آپدیت نهایی قبل از ذخیره
            updateStateFromDOM();
            
            const btn = document.getElementById('btn-save');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';
            btn.disabled = true;

            fetch('keyboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(keyboardRows)
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                if(data.status === 'success') {
                    SwalDark.fire({
                        icon: 'success',
                        title: 'ذخیره شد',
                        text: 'کیبورد ربات با موفقیت بروزرسانی شد',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            })
            .catch(err => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                SwalDark.fire({icon: 'error', title: 'خطا در ارتباط'});
            });
        }

        // شروع
        render();

    </script>
  </body>
</html>