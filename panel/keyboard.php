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
    // پاسخ کوتاه برای JS
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

// دریافت کیبورد فعلی برای نمایش در ادیتور
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
        // پیش‌فرض در صورت نبود دیتا
         $def = json_decode('{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}', true);
         $currentKeyboardJSON = json_encode($def['keyboard']);
    }
} catch (Exception $e) { $currentKeyboardJSON = '[]'; }
?>

<!doctype html>
<html lang="FA" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>پنل مدیریت ربات میرزا</title>
    
    <!-- کتابخانه‌های ضروری برای عملکرد ادیتور -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />

    <style>
        @font-face {
            font-family: 'yekan';
            src: url('fonts/Vazir.eot');
            src: url('fonts/Vazir.eot#iefix') format('embedded-opentype'),
                 url('fonts/Vazir.woff') format('woff'),
                 url('fonts/Vazir.ttf') format('truetype'),
                 url('fonts/Vazir.svg#CartoGothicStdBook') format('svg');
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            font-family: 'yekan', 'Vazirmatn', sans-serif;
            background-color: #0f0f0f; /* میدنایت: پس‌زمینه خیلی تیره */
            color: #e0e0e0;
            padding-top: 60px;
        }
        
        button {
            font-family: yekan;
        }

        /* استایل‌های اصلی دکمه‌های ناوبری */
        .btnback {
            position: fixed;
            top: 10px;
            left: 10px;
            padding: 7px 15px;
            background-color: #2c2c2c; /* تیره */
            color: #fff;
            border-radius: 6px;
            font-family: yekan;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            z-index: 1000;
            border: 1px solid #444;
            transition: background 0.3s;
        }
        .btnback:hover {
            background-color: #444;
        }
        
        .btndefult {
            position: fixed;
            top: 10px;
            left: 160px;
            padding: 7px 15px;
            background-color: #1a1a1a;
            border: 1px solid #d32f2f; /* قرمز ملایم برای ریست */
            color: #d32f2f;
            border-radius: 6px;
            font-family: yekan;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            z-index: 1000;
            transition: all 0.3s;
        }
        .btndefult:hover {
            background-color: #d32f2f;
            color: #fff;
        }

        /* کانتینر اصلی ادیتور */
        .editor-container {
            max-width: 600px;
            margin: 20px auto;
            background: #1e1e1e; /* خاکستری تیره */
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            overflow: hidden;
            border: 1px solid #333;
        }

        .telegram-header {
            background-color: #232e3c; /* رنگ هدر تلگرام در دارک مود */
            color: #fff;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #2b2b2b;
        }

        .chat-area {
            background-color: #000; /* پس زمینه چت تیره */
            /* پترن تلگرام */
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231a1a1a' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #333;
        }

        .keyboard-area {
            background-color: #181818; /* پس زمینه قسمت کیبورد */
            padding: 10px;
            min-height: 200px;
        }

        .telegram-btn {
            background-color: #2b2b2b; /* دکمه تیره */
            border: 1px solid #3d3d3d;
            border-radius: 4px;
            color: #fff;
            padding: 10px 5px;
            text-align: center;
            font-size: 14px;
            cursor: grab;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
            transition: all 0.2s;
            user-select: none;
        }
        
        .telegram-btn:active {
            cursor: grabbing;
            background-color: #333;
        }

        .telegram-btn:hover {
            background-color: #333;
            border-color: #555;
        }

        /* دکمه حذف قرمز */
        .delete-btn {
            position: absolute;
            top: -6px;
            left: -6px;
            width: 18px;
            height: 18px;
            background: #ef5350;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }

        .telegram-btn:hover .delete-btn {
            opacity: 1;
        }

        .add-row-btn {
            background-color: transparent;
            border: 1px dashed #555;
            color: #888;
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
            font-size: 13px;
        }
        
        .add-row-btn:hover {
            background-color: #2b2b2b;
            color: #fff;
            border-color: #777;
        }

        .save-btn-container {
            position: fixed;
            bottom: 30px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            pointer-events: none;
            z-index: 900;
        }

        .save-btn {
            background-color: #2196f3; /* آبی متریال برای دکمه اصلی */
            color: #fff;
            padding: 12px 50px;
            border-radius: 30px;
            border: none;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            cursor: pointer;
            pointer-events: auto;
            transition: transform 0.2s, background-color 0.2s;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            background-color: #1e88e5;
        }
        
        /* استایل اختصاصی برای دکمه افزودن (+) داخل سطر */
        .add-item-btn {
            background-color: #2b2b2b !important;
            color: #888 !important;
            border: 1px solid #3d3d3d !important;
        }
        .add-item-btn:hover {
            background-color: #333 !important;
            color: #fff !important;
        }
        
        /* استایل اختصاصی حذف سطر */
        .delete-row-msg {
            color: #ef5350;
            border-color: rgba(239, 83, 80, 0.3);
        }
        .delete-row-msg:hover {
            background-color: rgba(239, 83, 80, 0.1);
        }
    </style>
  </head>
  <body>
    <!-- دکمه‌های بازگشت -->
    <a class="btnback" href="index.php">بازگشت به پنل</a>
    <a class="btndefult" href="keyboard.php?action=reaset">ریست پیشفرض</a>
    
    <!-- روت ادیتور -->
    <div id="root">
        <div class="editor-container">
            <div class="telegram-header">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-xs">bot</div>
                    <div class="flex flex-col">
                        <span class="font-bold text-sm">Mirza Bot</span>
                        <span class="text-xs text-blue-300">bot</span>
                    </div>
                </div>
            </div>
            
            <div class="chat-area">
                <div style="background: #2b2b2b; padding: 6px 12px; border-radius: 6px; font-size: 13px; color: #ccc; border: 1px solid #3d3d3d;">
                    فضای چت (پیش‌نمایش)
                </div>
            </div>

            <div class="keyboard-area" id="keyboard-container">
                <!-- دکمه‌ها اینجا رندر می‌شوند -->
            </div>
            
            <div style="padding: 0 10px 20px 10px;">
                <button onclick="addRow()" class="add-row-btn">
                    + افزودن سطر جدید
                </button>
            </div>
        </div>
    </div>

    <div class="save-btn-container">
        <button onclick="saveKeyboard()" class="save-btn">ذخیره تغییرات</button>
    </div>

    <!-- اسکریپت منطق ادیتور -->
    <script>
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardRows)) keyboardRows = [];

        const container = document.getElementById('keyboard-container');

        // تنظیمات سراسری SweetAlert برای تم دارک
        const SwalDark = Swal.mixin({
            background: '#1e1e1e',
            color: '#fff',
            confirmButtonColor: '#2196f3',
            cancelButtonColor: '#d33',
        });

        function render() {
            container.innerHTML = '';
            
            keyboardRows.forEach((row, rowIndex) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex gap-1 mb-1 w-full row-container';
                rowDiv.dataset.rowIndex = rowIndex;

                row.forEach((btn, btnIndex) => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'telegram-btn flex-1 relative group';
                    btnEl.innerHTML = `
                        <span onclick="editButton(${rowIndex}, ${btnIndex})" class="w-full h-full flex items-center justify-center truncate px-1">${btn.text}</span>
                        <div class="delete-btn" onclick="deleteButton(${rowIndex}, ${btnIndex})">×</div>
                    `;
                    rowDiv.appendChild(btnEl);
                });

                // دکمه افزودن آیتم به سطر
                if (row.length < 8) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'w-8 rounded flex items-center justify-center font-bold text-lg transition ignore-elements add-item-btn';
                    addBtn.innerText = '+';
                    addBtn.onclick = () => addButton(rowIndex);
                    rowDiv.appendChild(addBtn);
                }
                
                // دکمه حذف سطر خالی
                if (row.length === 0) {
                     const emptyMsg = document.createElement('div');
                     emptyMsg.className = 'text-xs w-full text-center py-2 cursor-pointer border border-dashed rounded delete-row-msg';
                     emptyMsg.innerText = 'حذف سطر خالی';
                     emptyMsg.onclick = () => deleteRow(rowIndex);
                     rowDiv.appendChild(emptyMsg);
                }

                container.appendChild(rowDiv);
            });

            initSortable();
        }

        function initSortable() {
            // قابلیت جابجایی سطرها
            new Sortable(container, {
                animation: 150,
                handle: '.row-container', 
                ghostClass: 'opacity-50',
                onEnd: function (evt) {
                    // بروزرسانی آرایه اصلی بعد از جابجایی سطر
                    const item = keyboardRows.splice(evt.oldIndex, 1)[0];
                    keyboardRows.splice(evt.newIndex, 0, item);
                }
            });

            // قابلیت جابجایی دکمه‌ها درون سطر و بین سطرها
            document.querySelectorAll('.row-container').forEach(rowEl => {
                new Sortable(rowEl, {
                    group: 'shared',
                    animation: 150,
                    filter: '.ignore-elements',
                    draggable: '.telegram-btn',
                    ghostClass: 'opacity-50',
                    onEnd: function (evt) {
                        // منطق در saveKeyboard هندل می‌شود
                    }
                });
            });
        }

        function addRow() {
            keyboardRows.push([{text: 'دکمه جدید'}]);
            render();
        }

        function deleteRow(index) {
            keyboardRows.splice(index, 1);
            render();
        }

        async function addButton(rowIndex) {
            const { value: text } = await SwalDark.fire({
                title: 'نام دکمه جدید',
                input: 'text',
                inputValue: 'دکمه',
                confirmButtonText: 'افزودن',
                cancelButtonText: 'لغو',
                showCancelButton: true
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
                title: 'ویرایش نام دکمه',
                input: 'text',
                inputValue: currentText,
                confirmButtonText: 'ذخیره',
                cancelButtonText: 'لغو',
                showCancelButton: true
            });

            if (text) {
                keyboardRows[rowIndex][btnIndex].text = text;
                render();
            }
        }

        function saveKeyboard() {
            // خواندن اطلاعات از روی صفحه
            const newKeyboardData = [];
            const rows = container.querySelectorAll('.row-container');
            
            rows.forEach(row => {
                const rowData = [];
                const buttons = row.querySelectorAll('.telegram-btn span');
                buttons.forEach(btnSpan => {
                    rowData.push({ text: btnSpan.innerText });
                });
                
                if (rowData.length > 0) {
                    newKeyboardData.push(rowData);
                }
            });

            // بروزرسانی آرایه جهانی
            keyboardRows = newKeyboardData;

            fetch('keyboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(newKeyboardData)
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    SwalDark.fire({
                        icon: 'success',
                        title: 'ذخیره شد!',
                        text: 'تغییرات با موفقیت اعمال شد.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error(error);
                SwalDark.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: 'مشکلی در ارتباط با سرور وجود دارد.'
                });
            });
        }

        render();
    </script>
  </body>
</html>