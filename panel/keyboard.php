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
    // اگر داده‌ها آرایه‌ای از سطرها باشند (فرمت استاندارد کیبورد تلگرام)
    $keyboardStructure = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStructure), null, null);
    
    // ارسال پاسخ موفقیت به JS
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

// تلاش برای دریافت کیبورد فعلی از دیتابیس برای نمایش در ویرایشگر
// فرضیه: در جدول setting ستونی به نام keyboardmain یا مشابه وجود دارد که update آن را پر می‌کند
$currentKeyboardJSON = '[]';
try {
    // با توجه به تابع update شما، سعی می‌کنیم مقدار را بخوانیم
    // اگر تابع get_option یا مشابه دارید بهتر است از آن استفاده کنید. 
    // اینجا یک کوئری عمومی می‌زنیم
    $stmt = $pdo->prepare("SELECT * FROM setting LIMIT 1"); // معمولاً تنظیمات در یک سطر یا با کلید مشخص هستند
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($settings && isset($settings['keyboardmain'])) {
        $decoded = json_decode($settings['keyboardmain'], true);
        if(isset($decoded['keyboard'])) {
            $currentKeyboardJSON = json_encode($decoded['keyboard']);
        }
    } else {
         // استفاده از مقدار پیش‌فرض اگر دیتابیس خالی بود
         $def = json_decode('{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}', true);
         $currentKeyboardJSON = json_encode($def['keyboard']);
    }
} catch (Exception $e) {
    // در صورت خطا، یک آرایه خالی یا پیش‌فرض
    $currentKeyboardJSON = '[]'; 
}
?>

<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>مدیریت چیدمان کیبورد | ربات میرزا</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Persian Font (Vazirmatn) -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <!-- SweetAlert2 for nice alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f3f4f6;
        }
        .keyboard-btn {
            transition: all 0.2s;
        }
        .keyboard-btn:hover {
            transform: translateY(-2px);
        }
        /* Telegram Button Style Mockup */
        .telegram-btn {
            background-color: #fff;
            color: #000;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            position: relative;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .telegram-btn:hover {
            border-color: #3b82f6;
        }
        .delete-btn {
            position: absolute;
            top: -8px;
            left: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .telegram-btn:hover .delete-btn {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen pb-20">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-bold text-gray-800">چیدمان کیبورد ربات</h1>
                </div>
                <div class="flex gap-2">
                    <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm transition">بازگشت</a>
                    <a href="keyboard.php?action=reaset" onclick="return confirm('آیا مطمئن هستید؟ تمام تغییرات حذف می‌شود.')" class="bg-red-100 hover:bg-red-200 text-red-700 px-4 py-2 rounded-lg text-sm transition">بازگشت به پیش‌فرض</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-md mx-auto mt-10 px-4">
        
        <!-- Preview Area (Mobile Simulator) -->
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-200" style="min-height: 600px; display: flex; flex-direction: column;">
            <!-- Header -->
            <div class="bg-indigo-600 p-4 text-white flex items-center justify-between">
                <span>Mirza Bot</span>
                <span class="text-xs opacity-75">bot</span>
            </div>
            
            <!-- Chat Area (Placeholder) -->
            <div class="flex-1 bg-[#87aadd] p-4 opacity-50 flex items-center justify-center">
                <p class="text-white text-sm text-center">فضای چت<br>(پیش‌نمایش کیبورد در پایین)</p>
            </div>

            <!-- Keyboard Area -->
            <div class="bg-[#f0f2f5] p-2 border-t border-gray-300">
                <div id="keyboard-container" class="space-y-2">
                    <!-- Rows will be injected here -->
                </div>

                <!-- Add Row Button -->
                <button onclick="addRow()" class="w-full mt-3 py-2 border-2 border-dashed border-gray-400 text-gray-500 rounded-lg hover:bg-gray-100 hover:border-gray-500 transition flex items-center justify-center gap-2 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    افزودن سطر جدید
                </button>
            </div>
        </div>

        <!-- Save Button -->
        <div class="fixed bottom-6 left-0 right-0 px-4 flex justify-center">
            <button onclick="saveKeyboard()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transform hover:scale-105 transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                ذخیره تغییرات
            </button>
        </div>

    </div>

    <!-- Script -->
    <script>
        // Load initial data from PHP
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;

        // Ensure structure is correct
        if (!Array.isArray(keyboardRows)) {
            keyboardRows = [];
        }

        const container = document.getElementById('keyboard-container');

        function render() {
            container.innerHTML = '';
            
            keyboardRows.forEach((row, rowIndex) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex gap-2 w-full';
                
                // Add buttons in this row
                row.forEach((btn, btnIndex) => {
                    const btnEl = document.createElement('div');
                    btnEl.className = 'telegram-btn flex-1';
                    btnEl.innerHTML = `
                        <span onclick="editButton(${rowIndex}, ${btnIndex})">${btn.text}</span>
                        <div class="delete-btn" onclick="deleteButton(${rowIndex}, ${btnIndex})">×</div>
                    `;
                    rowDiv.appendChild(btnEl);
                });

                // "Add Button" placeholder for this row
                if (row.length < 8) { // Max 8 buttons per row limit for safety
                    const addBtn = document.createElement('button');
                    addBtn.className = 'w-8 flex items-center justify-center text-gray-400 hover:text-indigo-600 text-xl font-bold';
                    addBtn.innerHTML = '+';
                    addBtn.onclick = () => addButton(rowIndex);
                    addBtn.title = "افزودن دکمه به این سطر";
                    rowDiv.appendChild(addBtn);
                }

                // Row Delete Action (if needed, simplified here to just clearing if empty)
                if (row.length === 0) {
                     const emptyMsg = document.createElement('div');
                     emptyMsg.className = 'text-xs text-gray-400 w-full text-center py-2 cursor-pointer hover:text-red-500';
                     emptyMsg.innerText = 'حذف سطر خالی';
                     emptyMsg.onclick = () => deleteRow(rowIndex);
                     rowDiv.appendChild(emptyMsg);
                }

                container.appendChild(rowDiv);
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
                inputLabel: 'متن دکمه را وارد کنید',
                inputValue: 'دکمه جدید',
                showCancelButton: true,
                confirmButtonText: 'افزودن',
                cancelButtonText: 'لغو'
            });

            if (text) {
                keyboardRows[rowIndex].push({text: text});
                render();
            }
        }

        function deleteButton(rowIndex, btnIndex) {
            keyboardRows[rowIndex].splice(btnIndex, 1);
            if(keyboardRows[rowIndex].length === 0) {
                // Optional: Auto remove empty row? Let's keep it manual or show 'empty row' msg
            }
            render();
        }

        async function editButton(rowIndex, btnIndex) {
            const currentText = keyboardRows[rowIndex][btnIndex].text;
            
            const { value: text } = await Swal.fire({
                title: 'ویرایش نام دکمه',
                input: 'text',
                inputValue: currentText,
                showCancelButton: true,
                confirmButtonText: 'ذخیره',
                cancelButtonText: 'لغو'
            });

            if (text) {
                keyboardRows[rowIndex][btnIndex].text = text;
                render();
            }
        }

        function saveKeyboard() {
            // Filter out empty rows just in case
            const cleanData = keyboardRows.filter(row => row.length > 0);

            fetch('keyboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(cleanData)
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'عالی!',
                        text: 'تغییرات کیبورد با موفقیت ذخیره شد.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: 'مشکلی در ذخیره پیش آمد.'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: 'ارتباط با سرور برقرار نشد.'
                });
            });
        }

        // Initial Render
        render();
    </script>
</body>
</html>