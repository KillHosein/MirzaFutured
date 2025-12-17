<?php
session_start();
require_once '../config.php';
require_once '../jdf.php';
require_once '../function.php';

// بررسی لاگین بودن ادمین
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// منطق ذخیره‌سازی (POST)
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

if($method == "POST" && !empty($inputData)){
    $keyboardStructure = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStructure), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'کیبورد با موفقیت ذخیره شد']);
    exit;
}

// منطق ریست کردن (GET)
if(isset($_GET['action']) && $_GET['action'] == "reaset"){
    $defaultKeyboard = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    update("setting", "keyboardmain", $defaultKeyboard, null, null);
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
} catch (Exception $e) {
    $currentKeyboardJSON = '[]'; 
}
?>

<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>مدیریت کیبورد | ربات میرزا</title>
    <!-- Tailwind CSS (برای چیدمان) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font (Using Vazir as Yekan replacement for online preview) -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* استایل‌های تم اختصاصی */
        @font-face {
            font-family: 'yekan';
            src: url('fonts/Vazir.eot'); /* مسیر فرضی */
            /* استفاده از فونت آنلاین اگر لوکال نبود */
            font-family: 'Vazirmatn'; 
        }

        body {
            font-family: 'Vazirmatn', 'yekan', sans-serif;
            background-color: #f0f0f0;
        }

        /* دکمه‌های اصلی پنل قدیمی */
        .btnback {
            display: inline-block;
            padding: 8px 15px;
            background-color: #3d3d3d;
            color: #fff;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            border: none;
            transition: background 0.3s;
        }
        .btnback:hover {
            background-color: #555;
        }

        .btndefult {
            display: inline-block;
            padding: 8px 15px;
            background-color: #fff;
            border: 2px solid #3d3d3d;
            color: #3d3d3d;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btndefult:hover {
            background-color: #3d3d3d;
            color: #fff;
        }

        /* استایل تلگرام */
        .telegram-header {
            background-color: #517da2; /* رنگ هدر تلگرام */
            color: white;
        }
        
        .telegram-btn {
            background-color: #fff;
            color: #000;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            position: relative;
            cursor: pointer;
            border: 1px solid #ddd;
        }
        .telegram-btn:hover {
            background-color: #f5f5f5;
        }
        
        .delete-btn {
            position: absolute;
            top: -5px;
            left: -5px;
            background: #d32f2f;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }
        .telegram-btn:hover .delete-btn {
            opacity: 1;
        }

        /* دکمه ذخیره شناور */
        .save-float-btn {
            background-color: #3d3d3d;
            color: white;
            border-radius: 6px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .save-float-btn:hover {
            background-color: #222;
        }
    </style>
</head>
<body class="pb-20">

    <!-- Header / Nav Actions -->
    <div class="bg-white shadow-sm p-4 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="text-lg font-bold text-gray-700">مدیریت کیبورد</h1>
            <div class="flex gap-3">
                <a href="index.php" class="btnback">بازگشت به پنل</a>
                <a href="keyboard.php?action=reaset" onclick="return confirm('آیا مطمئن هستید؟')" class="btndefult">پیش‌فرض</a>
            </div>
        </div>
    </div>

    <div class="max-w-md mx-auto mt-6 px-2">
        
        <!-- Preview Container -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-300" style="min-height: 550px; display: flex; flex-direction: column;">
            <!-- Header -->
            <div class="telegram-header p-3 flex items-center justify-between">
                <span>Mirza Bot</span>
                <span class="text-xs opacity-80">bot</span>
            </div>
            
            <!-- Chat Area -->
            <div class="flex-1 bg-[#d7e3ec] p-4 flex items-center justify-center bg-opacity-60" style="background-image: url('https://web.telegram.org/img/bg_patter.png'); background-size: contain;">
                <p class="text-gray-500 text-sm bg-white/50 px-3 py-1 rounded-full">پیش‌نمایش کیبورد</p>
            </div>

            <!-- Keyboard Area -->
            <div class="bg-[#f0f2f5] p-2 border-t border-gray-300">
                <div id="keyboard-container" class="space-y-2 mb-2">
                    <!-- Rows injected via JS -->
                </div>

                <!-- Add Row Button -->
                <button onclick="addRow()" class="w-full py-2 border border-dashed border-gray-400 text-gray-500 rounded hover:bg-gray-100 transition text-sm">
                    + افزودن سطر جدید
                </button>
            </div>
        </div>

        <!-- Save Button Footer -->
        <div class="fixed bottom-6 left-0 right-0 px-4 flex justify-center z-40">
            <button onclick="saveKeyboard()" class="save-float-btn py-3 px-12 text-lg flex items-center gap-2">
                <span>ذخیره تغییرات</span>
            </button>
        </div>

    </div>

    <!-- Script -->
    <script>
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;

        if (!Array.isArray(keyboardRows)) {
            keyboardRows = [];
        }

        const container = document.getElementById('keyboard-container');

        function render() {
            container.innerHTML = '';
            
            keyboardRows.forEach((row, rowIndex) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex gap-1 w-full'; // gap reduced for tighter look
                
                row.forEach((btn, btnIndex) => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'telegram-btn flex-1';
                    btnEl.innerHTML = `
                        <span onclick="editButton(${rowIndex}, ${btnIndex})" class="w-full text-center truncate px-1">${btn.text}</span>
                        <div class="delete-btn" onclick="deleteButton(${rowIndex}, ${btnIndex})">×</div>
                    `;
                    rowDiv.appendChild(btnEl);
                });

                if (row.length < 8) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'w-8 bg-gray-200 hover:bg-gray-300 text-gray-600 rounded flex items-center justify-center font-bold text-lg ml-1';
                    addBtn.innerHTML = '+';
                    addBtn.onclick = () => addButton(rowIndex);
                    rowDiv.appendChild(addBtn);
                }

                if (row.length === 0) {
                     const emptyMsg = document.createElement('div');
                     emptyMsg.className = 'text-xs text-red-400 w-full text-center py-1 cursor-pointer';
                     emptyMsg.innerText = '[حذف سطر خالی]';
                     emptyMsg.onclick = () => deleteRow(rowIndex);
                     rowDiv.appendChild(emptyMsg);
                }

                container.appendChild(rowDiv);
            });
        }

        function addRow() {
            keyboardRows.push([{text: 'دکمه'}]);
            render();
        }

        function deleteRow(index) {
            keyboardRows.splice(index, 1);
            render();
        }

        async function addButton(rowIndex) {
            const { value: text } = await Swal.fire({
                title: 'نام دکمه',
                input: 'text',
                inputValue: 'دکمه جدید',
                confirmButtonColor: '#3d3d3d',
                confirmButtonText: 'تایید',
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
                title: 'ویرایش نام',
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
            const cleanData = keyboardRows.filter(row => row.length > 0);

            fetch('keyboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cleanData)
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'ذخیره شد',
                        confirmButtonColor: '#3d3d3d',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({icon: 'error', title: 'خطا'});
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({icon: 'error', title: 'خطا در ارتباط'});
            });
        }

        render();
    </script>
</body>
</html>