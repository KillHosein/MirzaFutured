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
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SortableJS for Drag and Drop -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <!-- Font -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @font-face {
            font-family: 'yekan';
            src: url('fonts/Vazir.eot'); 
            font-family: 'Vazirmatn'; 
        }

        body {
            font-family: 'Vazirmatn', 'yekan', sans-serif;
            background-color: #121212; /* Midnight Background */
            color: #e0e0e0;
        }

        /* دکمه‌های ناوبری */
        .btnback {
            display: inline-block;
            padding: 8px 15px;
            background-color: #2c2c2c;
            color: #fff;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            border: 1px solid #444;
            transition: background 0.3s;
        }
        .btnback:hover {
            background-color: #444;
        }

        .btndefult {
            display: inline-block;
            padding: 8px 15px;
            background-color: #1a1a1a;
            border: 1px solid #d32f2f;
            color: #d32f2f;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btndefult:hover {
            background-color: #d32f2f;
            color: #fff;
        }

        /* کانتینر چت */
        .chat-container {
            background-color: #1e1e2e; /* Dark Card */
            border: 1px solid #333;
        }

        .telegram-header {
            background-color: #232e3c; /* Darker Telegram Header */
            color: white;
            border-bottom: 1px solid #333;
        }
        
        /* دکمه‌های کیبورد */
        .telegram-btn {
            background-color: #2b2b2b; /* Dark Button */
            color: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            position: relative;
            cursor: move; /* نشانگر قابلیت جابجایی */
            border: 1px solid #3d3d3d;
            user-select: none;
            transition: transform 0.1s, box-shadow 0.1s;
        }
        .telegram-btn:active {
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
        }
        
        /* حالت در حال درگ */
        .sortable-ghost {
            opacity: 0.4;
            background-color: #4a4a4a;
        }
        .sortable-drag {
            cursor: grabbing;
        }

        .delete-btn {
            position: absolute;
            top: -6px;
            left: -6px;
            background: #ef5350;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
            cursor: pointer;
        }
        .telegram-btn:hover .delete-btn {
            opacity: 1;
        }

        /* دکمه‌های کنترلی */
        .control-btn {
            background-color: #2b2b2b;
            border: 1px dashed #555;
            color: #888;
        }
        .control-btn:hover {
            background-color: #333;
            color: #bbb;
        }

        .save-float-btn {
            background-color: #4caf50;
            color: white;
            border-radius: 6px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border: none;
            transition: transform 0.2s;
        }
        .save-float-btn:hover {
            background-color: #43a047;
            transform: translateY(-2px);
        }
        
        /* بک‌گراند چت تلگرام تیره */
        .chat-bg {
            background-color: #0f0f0f;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231a1a1a' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="pb-24">

    <!-- Header -->
    <div class="bg-[#1e1e2e] shadow-md p-4 sticky top-0 z-50 border-b border-[#333]">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="text-lg font-bold text-gray-200">مدیریت کیبورد (Midnight)</h1>
            <div class="flex gap-3">
                <a href="index.php" class="btnback">بازگشت</a>
                <a href="keyboard.php?action=reaset" onclick="return confirm('آیا مطمئن هستید؟')" class="btndefult">ریست</a>
            </div>
        </div>
    </div>

    <div class="max-w-md mx-auto mt-8 px-2">
        
        <div class="text-center mb-4">
            <p class="text-xs text-gray-400">نکته: برای جابجایی دکمه‌ها، آن‌ها را بکشید و رها کنید.</p>
        </div>

        <!-- Preview Container -->
        <div class="chat-container rounded-lg shadow-xl overflow-hidden" style="min-height: 550px; display: flex; flex-direction: column;">
            <!-- Telegram Header -->
            <div class="telegram-header p-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-xs">bot</div>
                    <div>
                        <div class="text-sm font-bold">Mirza Bot</div>
                        <div class="text-xs text-blue-300">bot</div>
                    </div>
                </div>
                <div class="text-gray-400">⋮</div>
            </div>
            
            <!-- Chat Area -->
            <div class="flex-1 chat-bg p-4 flex items-center justify-center">
                <div class="bg-[#2b2b2b] text-white px-4 py-2 rounded-lg text-sm shadow-sm border border-[#3d3d3d]">
                    فضای چت (کیبورد در پایین)
                </div>
            </div>

            <!-- Keyboard Area -->
            <div class="bg-[#161616] p-2 border-t border-[#333]">
                <div id="keyboard-container" class="space-y-1 mb-2">
                    <!-- Rows injected via JS -->
                </div>

                <!-- Add Row Button -->
                <button onclick="addRow()" class="w-full py-2 control-btn rounded transition text-sm flex items-center justify-center gap-2 mt-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    افزودن سطر جدید
                </button>
            </div>
        </div>

        <!-- Save Button Footer -->
        <div class="fixed bottom-8 left-0 right-0 px-4 flex justify-center z-40 pointer-events-none">
            <button onclick="saveKeyboard()" class="save-float-btn py-3 px-12 text-lg flex items-center gap-2 pointer-events-auto cursor-pointer">
                <span>ذخیره تغییرات</span>
            </button>
        </div>

    </div>

    <!-- Script -->
    <script>
        // Initial Data
        let initialData = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(initialData)) initialData = [];

        const container = document.getElementById('keyboard-container');

        // Render Function
        function render(data) {
            container.innerHTML = '';
            
            data.forEach((row, rowIndex) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'keyboard-row flex gap-1 w-full min-h-[42px]'; 
                rowDiv.dataset.rowIndex = rowIndex; // For tracking
                
                row.forEach((btn) => {
                    const btnEl = createButtonElement(btn.text);
                    rowDiv.appendChild(btnEl);
                });

                // Add Button (Placeholder style)
                if (row.length < 8) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'w-8 bg-[#2b2b2b] hover:bg-[#3d3d3d] text-gray-500 hover:text-white rounded flex items-center justify-center font-bold text-lg ml-1 border border-[#3d3d3d] transition-colors';
                    addBtn.innerHTML = '+';
                    addBtn.onclick = (e) => {
                        // Find the row element again to get current children count correctly
                        addButtonToRow(rowDiv);
                    };
                    // Mark as non-sortable
                    addBtn.classList.add('ignore-elements');
                    rowDiv.appendChild(addBtn);
                }

                // Row Delete (if emptyish - only has + button)
                if (rowDiv.children.length <= 1) { // 1 because of addBtn
                     const emptyMsg = document.createElement('div');
                     emptyMsg.className = 'text-xs text-red-400 w-full text-center py-2 cursor-pointer flex items-center justify-center border border-dashed border-red-900/50 rounded';
                     emptyMsg.innerText = 'حذف سطر';
                     emptyMsg.onclick = () => rowDiv.remove();
                     rowDiv.prepend(emptyMsg);
                }

                container.appendChild(rowDiv);
            });

            initSortable();
        }

        function createButtonElement(text) {
            const btnEl = document.createElement('div');
            btnEl.className = 'telegram-btn flex-1 relative group';
            btnEl.dataset.text = text; // Store text in dataset
            btnEl.innerHTML = `
                <span class="w-full text-center truncate px-1 pointer-events-none">${text}</span>
                <div class="delete-btn" onclick="this.parentElement.remove()">×</div>
                <div class="absolute inset-0 bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity rounded pointer-events-none"></div>
            `;
            // Edit on click
            btnEl.addEventListener('click', (e) => {
                if(e.target.classList.contains('delete-btn')) return;
                editButtonText(btnEl);
            });
            return btnEl;
        }

        // Initialize SortableJS
        function initSortable() {
            // Sort Rows
            new Sortable(container, {
                animation: 150,
                handle: '.keyboard-row', // Drag by row area (empty parts)
                filter: '.ignore-elements',
                ghostClass: 'sortable-ghost',
                fallbackOnBody: true,
                swapThreshold: 0.65
            });

            // Sort Buttons inside Rows
            const rows = document.querySelectorAll('.keyboard-row');
            rows.forEach(row => {
                new Sortable(row, {
                    group: 'shared', // Allow dragging between rows
                    animation: 150,
                    filter: '.ignore-elements, .text-xs', // Don't drag the + button or delete text
                    draggable: '.telegram-btn',
                    ghostClass: 'sortable-ghost',
                    fallbackOnBody: true,
                    swapThreshold: 0.65
                });
            });
        }

        // Helper Functions
        function addRow() {
            const rowDiv = document.createElement('div');
            rowDiv.className = 'keyboard-row flex gap-1 w-full min-h-[42px]';
            
            // Add initial button
            rowDiv.appendChild(createButtonElement('دکمه جدید'));
            
            // Add (+) button
            const addBtn = document.createElement('button');
            addBtn.className = 'w-8 bg-[#2b2b2b] hover:bg-[#3d3d3d] text-gray-500 hover:text-white rounded flex items-center justify-center font-bold text-lg ml-1 border border-[#3d3d3d] transition-colors ignore-elements';
            addBtn.innerHTML = '+';
            addBtn.onclick = () => addButtonToRow(rowDiv);
            rowDiv.appendChild(addBtn);

            container.appendChild(rowDiv);
            initSortable(); // Re-init for new row
        }

        async function addButtonToRow(rowElement) {
            const { value: text } = await Swal.fire({
                title: 'نام دکمه',
                input: 'text',
                inputValue: 'دکمه جدید',
                background: '#1e1e2e',
                color: '#fff',
                confirmButtonColor: '#4caf50',
                cancelButtonColor: '#d33',
                confirmButtonText: 'افزودن',
                cancelButtonText: 'لغو',
                showCancelButton: true
            });

            if (text) {
                // Insert before the last element (which is the + button)
                const newBtn = createButtonElement(text);
                rowElement.insertBefore(newBtn, rowElement.lastElementChild);
            }
        }

        async function editButtonText(btnElement) {
            const currentText = btnElement.querySelector('span').innerText;
            
            const { value: text } = await Swal.fire({
                title: 'ویرایش نام',
                input: 'text',
                inputValue: currentText,
                background: '#1e1e2e',
                color: '#fff',
                confirmButtonColor: '#4caf50',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ذخیره',
                cancelButtonText: 'لغو',
                showCancelButton: true
            });

            if (text) {
                btnElement.querySelector('span').innerText = text;
                btnElement.dataset.text = text;
            }
        }

        function saveKeyboard() {
            // Scrape the DOM to build the JSON
            const newKeyboardData = [];
            
            const rows = container.querySelectorAll('.keyboard-row');
            rows.forEach(row => {
                const rowData = [];
                const buttons = row.querySelectorAll('.telegram-btn');
                buttons.forEach(btn => {
                    const text = btn.querySelector('span').innerText;
                    rowData.push({ text: text });
                });
                
                if (rowData.length > 0) {
                    newKeyboardData.push(rowData);
                }
            });

            // Send to server
            fetch('keyboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(newKeyboardData)
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#1e1e2e',
                        color: '#fff'
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'کیبورد ذخیره شد'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: 'مشکلی پیش آمد',
                        background: '#1e1e2e',
                        color: '#fff'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: 'ارتباط برقرار نشد',
                    background: '#1e1e2e',
                    color: '#fff'
                });
            });
        }

        // Start
        render(initialData);

    </script>
</body>
</html>