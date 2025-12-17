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

// محاسبه مسیرها (طبق کد ارسالی شما)
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
            background-color: #f4f4f4; /* رنگ پس زمینه روشن */
            padding-top: 60px; /* فضا برای دکمه‌های فیکس شده */
        }
        
        button {
            font-family: yekan;
        }

        /* استایل‌های اصلی شما */
        .btnback {
            position: fixed;
            top: 10px;
            left: 10px;
            padding: 7px 15px;
            background-color: #3d3d3d;
            color: #fff;
            border-radius: 6px;
            font-family: yekan;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            z-index: 1000;
        }
        
        .btndefult {
            position: fixed;
            top: 10px;
            left: 160px; /* کمی فاصله بیشتر برای جلوگیری از تداخل */
            padding: 7px 15px;
            background-color: #fff;
            border: 2px solid #3d3d3d;
            color: #3d3d3d;
            border-radius: 6px;
            font-family: yekan;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            z-index: 1000;
        }

        /* استایل‌های ادیتور (هماهنگ با تم شما) */
        .editor-container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .telegram-header {
            background-color: #5682a3; /* رنگ آبی تلگرام کلاسیک */
            color: white;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-area {
            background-color: #d7e3ec; /* پس زمینه چت تلگرام */
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #ccc;
        }

        .keyboard-area {
            background-color: #f1f2f6;
            padding: 10px;
            min-height: 200px;
        }

        .telegram-btn {
            background-color: #fff;
            border: 1px solid #cdd7e0;
            border-radius: 4px;
            color: #000;
            padding: 10px 5px;
            text-align: center;
            font-size: 14px;
            cursor: grab;
            position: relative;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
            transition: all 0.2s;
            user-select: none;
        }
        
        .telegram-btn:active {
            cursor: grabbing;
        }

        .telegram-btn:hover {
            background-color: #f5f5f5;
        }

        .delete-btn {
            position: absolute;
            top: -8px;
            left: -8px;
            width: 20px;
            height: 20px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
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
            border: 2px dashed #3d3d3d;
            color: #3d3d3d;
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }
        
        .add-row-btn:hover {
            background-color: #3d3d3d;
            color: #fff;
        }

        .save-btn-container {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            pointer-events: none;
        }

        .save-btn {
            background-color: #3d3d3d;
            color: #fff;
            padding: 12px 40px;
            border-radius: 30px;
            border: none;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            pointer-events: auto;
            transition: transform 0.2s;
        }

        .save-btn:hover {
            transform: scale(1.05);
            background-color: #222;
        }
    </style>
  </head>
  <body>
    <!-- دکمه‌های بازگشت طبق درخواست -->
    <a class="btnback" href="index.php">بازگشت به پنل کاربری</a>
    <a class="btndefult" href="keyboard.php?action=reaset">بازگشت به حالت پیشفرض</a>
    
    <!-- روت ادیتور -->
    <div id="root">
        <div class="editor-container">
            <div class="telegram-header">
                <span>Mirza Bot</span>
                <span style="font-size: 12px; opacity: 0.8;">bot</span>
            </div>
            
            <div class="chat-area">
                <div style="background: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; color: #555;">
                    پیش‌نمایش کیبورد
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
                        <span onclick="editButton(${rowIndex}, ${btnIndex})">${btn.text}</span>
                        <div class="delete-btn" onclick="deleteButton(${rowIndex}, ${btnIndex})">×</div>
                    `;
                    rowDiv.appendChild(btnEl);
                });

                // دکمه افزودن آیتم به سطر
                if (row.length < 8) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'w-8 bg-gray-200 text-gray-600 rounded flex items-center justify-center font-bold text-lg hover:bg-gray-300 transition ignore-elements';
                    addBtn.innerText = '+';
                    addBtn.onclick = () => addButton(rowIndex);
                    rowDiv.appendChild(addBtn);
                }
                
                // دکمه حذف سطر خالی
                if (row.length === 0) {
                     const emptyMsg = document.createElement('div');
                     emptyMsg.className = 'text-xs text-red-500 w-full text-center py-2 cursor-pointer border border-dashed border-red-300 rounded';
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
                ghostClass: 'bg-gray-100',
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
                    onEnd: function (evt) {
                        // منطق پیچیده جابجایی بین آرایه‌ها را اینجا ساده می‌کنیم:
                        // ما هر بار قبل از ذخیره، کل DOM را می‌خوانیم، پس نیازی به آپدیت دقیق آرایه در لحظه دراپ نیست
                        // اما برای اینکه UI بهم نریزد، تابع saveKeyboard از روی DOM میخواند.
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
            const { value: text } = await Swal.fire({
                title: 'نام دکمه جدید',
                input: 'text',
                inputValue: 'دکمه',
                confirmButtonColor: '#3d3d3d',
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
            const { value: text } = await Swal.fire({
                title: 'ویرایش نام دکمه',
                input: 'text',
                inputValue: currentText,
                confirmButtonColor: '#3d3d3d',
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
            // خواندن اطلاعات از روی صفحه (چون دراگ و دراپ ممکن است آرایه را بهم ریخته باشد)
            const newKeyboardData = [];
            const rows = container.querySelectorAll('.row-container');
            
            rows.forEach(row => {
                const rowData = [];
                const buttons = row.querySelectorAll('.telegram-btn span'); // span حاوی متن است
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
                    Swal.fire({
                        icon: 'success',
                        title: 'ذخیره شد!',
                        text: 'تغییرات با موفقیت اعمال شد.',
                        confirmButtonColor: '#3d3d3d',
                        timer: 2000
                    });
                }
            })
            .catch(error => {
                console.error(error);
                Swal.fire({
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