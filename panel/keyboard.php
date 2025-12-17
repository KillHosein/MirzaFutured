<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// بررسی لاگین بودن ادمین
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

// دریافت کیبورد فعلی
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
    <!-- آیکون‌ها -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- انیمیشن -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --bg-dark: #121212;
            --bg-card: #1e1e1e;
            --accent: #6c5ce7;
            --accent-hover: #5649c0;
            --text-main: #e0e0e0;
            --telegram-bg: #0f0f0f;
            --telegram-header: #232e3c;
            --telegram-btn: #2b2b2b;
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
            background: radial-gradient(circle at top right, #2d3436, #000000);
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* نوار ابزار بالا */
        .top-bar {
            width: 100%;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(30, 30, 30, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            z-index: 100;
        }

        .btn-action {
            padding: 8px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-reset {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .btn-reset:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-2px);
        }

        /* فریم موبایل */
        .phone-mockup {
            width: 380px;
            height: 750px;
            background: #000;
            border-radius: 45px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 12px #2d2d2d;
            position: relative;
            overflow: hidden;
            margin-top: 100px;
            margin-bottom: 50px;
            border: 8px solid #1a1a1a;
            display: flex;
            flex-direction: column;
        }

        /* ناچ موبایل */
        .notch {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 25px;
            background: #1a1a1a;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
            z-index: 50;
        }

        /* هدر تلگرام */
        .telegram-header {
            background: var(--telegram-header);
            padding: 40px 15px 10px 15px; /* فضای بیشتر برای ناچ */
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* فضای چت */
        .chat-area {
            flex: 1;
            background-color: #0f0f0f;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231a1a1a' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow: hidden;
        }

        .preview-bubble {
            background: var(--telegram-header);
            padding: 10px 20px;
            border-radius: 15px;
            font-size: 13px;
            color: #aaa;
            max-width: 80%;
            text-align: center;
            animation: fadeInDown 0.5s;
        }

        /* ناحیه کیبورد */
        .keyboard-wrapper {
            background: #1c1c1d;
            border-top: 1px solid #2c2c2e;
            max-height: 50%;
            display: flex;
            flex-direction: column;
        }

        .keyboard-scroll {
            overflow-y: auto;
            padding: 8px;
            flex: 1;
        }
        
        /* اسکرول بار باریک */
        .keyboard-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .keyboard-scroll::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 2px;
        }

        .row-container {
            display: flex;
            gap: 4px;
            margin-bottom: 4px;
            padding: 2px;
            background: rgba(255,255,255,0.02);
            border-radius: 6px;
            transition: background 0.2s;
        }
        .row-container:hover {
            background: rgba(255,255,255,0.05);
        }

        .telegram-btn {
            background: linear-gradient(145deg, #2b2b2b, #222);
            color: #fff;
            border-radius: 6px;
            font-size: 13px;
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: grab;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: all 0.2s;
            overflow: hidden;
        }
        
        .telegram-btn:hover {
            transform: translateY(-1px);
            border-color: rgba(255,255,255,0.15);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        .telegram-btn:active {
            cursor: grabbing;
            transform: scale(0.98);
        }

        /* اکشن‌های دکمه (حذف/ویرایش) */
        .btn-controls {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.2s;
            backdrop-filter: blur(2px);
        }
        .telegram-btn:hover .btn-controls {
            opacity: 1;
        }

        .control-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 10px;
            transition: transform 0.2s;
        }
        .control-icon:hover {
            transform: scale(1.2);
        }
        .icon-edit { background: #3b82f6; color: white; }
        .icon-delete { background: #ef4444; color: white; }

        /* دکمه‌های افزودن */
        .add-item-btn {
            width: 30px;
            min-width: 30px;
            background: rgba(255,255,255,0.05) !important;
            border: 1px dashed rgba(255,255,255,0.2) !important;
            color: rgba(255,255,255,0.4) !important;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .add-item-btn:hover {
            background: rgba(255,255,255,0.1) !important;
            color: white !important;
        }

        .add-row-container {
            padding: 10px;
            border-top: 1px solid #2c2c2e;
            background: #1c1c1d;
        }
        .btn-add-row {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px dashed #444;
            background: transparent;
            color: #888;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-row:hover {
            background: rgba(255,255,255,0.05);
            color: #fff;
            border-color: #666;
        }

        /* دکمه ذخیره شناور */
        .save-float {
            position: fixed;
            bottom: 30px;
            background: #3b82f6;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
            border: none;
            cursor: pointer;
            z-index: 200;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        .save-float:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(59, 130, 246, 0.5);
        }

        /* SweetAlert Dark Override */
        .swal2-popup {
            background: #1e1e1e !important;
            border: 1px solid #333 !important;
        }
        .swal2-title, .swal2-content, .swal2-input {
            color: #fff !important;
        }
        .swal2-input {
            background: #2b2b2b !important;
            border: 1px solid #444 !important;
        }
    </style>
  </head>
  <body>

    <!-- نوار ابزار -->
    <div class="top-bar">
        <h1 class="text-lg font-bold text-gray-200"><i class="fa-solid fa-keyboard mr-2"></i> ویرایشگر کیبورد</h1>
        <div class="flex gap-3">
            <a href="index.php" class="btn-action btn-back">
                <i class="fa-solid fa-arrow-right"></i> پنل
            </a>
            <a href="keyboard.php?action=reaset" class="btn-action btn-reset" onclick="return confirm('آیا مطمئن هستید؟')">
                <i class="fa-solid fa-rotate-right"></i> ریست
            </a>
        </div>
    </div>

    <!-- قاب موبایل -->
    <div class="phone-mockup animate__animated animate__fadeInUp">
        <div class="notch"></div>
        
        <!-- هدر تلگرام -->
        <div class="telegram-header">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-arrow-right text-gray-400"></i>
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-sm font-bold shadow-lg">
                    MB
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-sm tracking-wide">Mirza Bot</span>
                    <span class="text-[11px] text-blue-300">bot</span>
                </div>
            </div>
            <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
        </div>

        <!-- فضای چت -->
        <div class="chat-area">
            <div class="preview-bubble">
                <i class="fa-solid fa-wand-magic-sparkles mb-2 block text-yellow-500 text-lg"></i>
                این پیش‌نمایش زنده کیبورد شماست.
                <br>
                دکمه‌ها را بکشید و رها کنید.
            </div>
        </div>

        <!-- ناحیه کیبورد -->
        <div class="keyboard-wrapper">
            <div class="keyboard-scroll" id="keyboard-container">
                <!-- دکمه‌ها اینجا رندر می‌شوند -->
            </div>
            <div class="add-row-container">
                <button onclick="addRow()" class="btn-add-row">
                    <i class="fa-solid fa-plus"></i> افزودن سطر جدید
                </button>
            </div>
        </div>
    </div>

    <!-- دکمه ذخیره -->
    <button onclick="saveKeyboard()" class="save-float animate__animated animate__bounceIn">
        <i class="fa-solid fa-floppy-disk"></i> ذخیره تغییرات
    </button>

    <script>
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardRows)) keyboardRows = [];

        const container = document.getElementById('keyboard-container');

        // کانفیگ دارک مود آلرت‌ها
        const SwalDark = Swal.mixin({
            background: '#1e1e1e',
            color: '#fff',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
            showClass: { popup: 'animate__animated animate__fadeInDown' },
            hideClass: { popup: 'animate__animated animate__fadeOutUp' }
        });

        function render() {
            container.innerHTML = '';
            
            keyboardRows.forEach((row, rowIndex) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row-container animate__animated animate__fadeIn';
                rowDiv.dataset.rowIndex = rowIndex;

                // رندر دکمه‌های واقعی
                row.forEach((btn, btnIndex) => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'telegram-btn flex-1 group';
                    btnEl.innerHTML = `
                        <span class="truncate px-2 w-full text-center">${btn.text}</span>
                        <div class="btn-controls">
                            <div class="control-icon icon-edit" onclick="editButton(${rowIndex}, ${btnIndex})" title="ویرایش">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                            <div class="control-icon icon-delete" onclick="deleteButton(${rowIndex}, ${btnIndex})" title="حذف">
                                <i class="fa-solid fa-trash"></i>
                            </div>
                        </div>
                    `;
                    rowDiv.appendChild(btnEl);
                });

                // دکمه افزودن (+) در انتهای سطر (اگر جا باشد)
                if (row.length < 8) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'add-item-btn flex items-center justify-center ignore-elements';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                    addBtn.onclick = () => addButton(rowIndex);
                    addBtn.title = "افزودن دکمه به این سطر";
                    rowDiv.appendChild(addBtn);
                }
                
                // دکمه حذف سطر خالی (فقط اگر دکمه‌ای نباشد)
                if (row.length === 0) {
                     const emptyMsg = document.createElement('div');
                     emptyMsg.className = 'text-[10px] text-red-400 w-full text-center py-2 cursor-pointer border border-dashed border-red-900/30 rounded hover:bg-red-900/10 transition';
                     emptyMsg.innerHTML = '<i class="fa-solid fa-trash-can mr-1"></i> حذف سطر خالی';
                     emptyMsg.onclick = () => deleteRow(rowIndex);
                     rowDiv.appendChild(emptyMsg);
                }

                container.appendChild(rowDiv);
            });

            initSortable();
        }

        function initSortable() {
            // جابجایی سطرها
            new Sortable(container, {
                animation: 200,
                handle: '.row-container', 
                ghostClass: 'opacity-50',
                easing: "cubic-bezier(1, 0, 0, 1)",
                onEnd: function (evt) {
                    const item = keyboardRows.splice(evt.oldIndex, 1)[0];
                    keyboardRows.splice(evt.newIndex, 0, item);
                }
            });

            // جابجایی دکمه‌ها
            document.querySelectorAll('.row-container').forEach(rowEl => {
                new Sortable(rowEl, {
                    group: 'shared',
                    animation: 200,
                    filter: '.ignore-elements',
                    draggable: '.telegram-btn',
                    ghostClass: 'opacity-40',
                    swapThreshold: 0.65,
                    onEnd: function (evt) {
                        // منطق ذخیره‌سازی نهایی از روی DOM خوانده می‌شود
                    }
                });
            });
        }

        function addRow() {
            keyboardRows.push([{text: 'دکمه جدید'}]);
            render();
            // اسکرول به پایین
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 100);
        }

        function deleteRow(index) {
            keyboardRows.splice(index, 1);
            render();
        }

        async function addButton(rowIndex) {
            const { value: text } = await SwalDark.fire({
                title: 'افزودن دکمه',
                input: 'text',
                inputPlaceholder: 'عنوان دکمه را وارد کنید...',
                confirmButtonText: 'افزودن <i class="fa-solid fa-check mr-1"></i>',
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
                title: 'ویرایش دکمه',
                input: 'text',
                inputValue: currentText,
                confirmButtonText: 'ذخیره <i class="fa-solid fa-save mr-1"></i>',
                showCancelButton: true,
                cancelButtonText: 'لغو'
            });

            if (text) {
                keyboardRows[rowIndex][btnIndex].text = text;
                render();
            }
        }

        function saveKeyboard() {
            const saveBtn = document.querySelector('.save-float');
            const originalContent = saveBtn.innerHTML;
            
            // حالت لودینگ دکمه
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال ذخیره...';
            saveBtn.disabled = true;

            // استخراج دیتا از DOM برای اطمینان از ترتیب درست
            const newKeyboardData = [];
            const rows = container.querySelectorAll('.row-container');
            
            rows.forEach(row => {
                const rowData = [];
                // فقط متن‌هایی که داخل telegram-btn هستند رو بگیر
                const buttons = row.querySelectorAll('.telegram-btn span');
                buttons.forEach(btnSpan => {
                    rowData.push({ text: btnSpan.innerText });
                });
                
                if (rowData.length > 0) {
                    newKeyboardData.push(rowData);
                }
            });

            keyboardRows = newKeyboardData;

            fetch('keyboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(newKeyboardData)
            })
            .then(response => response.json())
            .then(data => {
                saveBtn.innerHTML = originalContent;
                saveBtn.disabled = false;

                if(data.status === 'success') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        background: '#1e1e1e',
                        color: '#fff',
                        timerProgressBar: true,
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'کیبورد با موفقیت ذخیره شد'
                    });
                }
            })
            .catch(error => {
                console.error(error);
                saveBtn.innerHTML = originalContent;
                saveBtn.disabled = false;
                SwalDark.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: 'مشکلی در ذخیره‌سازی رخ داد.'
                });
            });
        }

        render();
    </script>
  </body>
</html>