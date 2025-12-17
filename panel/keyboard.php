<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// --- بررسی دسترسی ادمین ---
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

// کوئری فاکتورها (طبق فایل اصلی شما)
$query = $pdo->prepare("SELECT * FROM invoice");
$query->execute();
$listinvoice = $query->fetchAll();

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// --- دریافت ورودی JSON برای ذخیره‌سازی ---
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

// منطق ذخیره
if($method == "POST" && !empty($inputData)){
    $keyboardStruct = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStruct), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// منطق ریست
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

// --- خواندن اطلاعات فعلی از دیتابیس ---
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
         // مقدار پیش‌فرض اگر دیتابیس خالی بود
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
    <title>مدیریت کیبورد | پنل حرفه‌ای</title>
    
    <!-- استایل‌ها و اسکریپت‌ها -->
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
            --text-main: #f8fafc;
            --border: #334155;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            overflow: hidden; /* جلوگیری از اسکرول صفحه اصلی */
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* هدر */
        .header {
            height: 60px;
            background: rgba(30, 41, 59, 0.9);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 50;
        }

        /* چیدمان اصلی */
        .main-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* پنل پیش‌نمایش (چپ) */
        .preview-pane {
            width: 400px;
            background: #0b1120;
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            background-image: radial-gradient(#1e293b 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .mobile-frame {
            width: 320px;
            height: 650px;
            background: #000;
            border-radius: 40px;
            box-shadow: 0 0 0 10px #1f2937, 0 20px 50px rgba(0,0,0,0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .telegram-header {
            padding: 30px 15px 10px;
            background: #17212b;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #000;
        }

        .telegram-chat {
            flex: 1;
            background: #0e1621;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 10px;
        }

        .telegram-keyboard {
            background: #17212b;
            padding: 5px;
            min-height: 200px;
        }

        .tg-btn {
            background: #2b5278;
            color: white;
            border-radius: 6px;
            padding: 10px 2px;
            font-size: 12px;
            text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* پنل ویرایشگر (راست) */
        .editor-pane {
            flex: 1;
            background: var(--bg-panel);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .editor-content {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }

        .row-card {
            background: rgba(51, 65, 85, 0.5);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            transition: all 0.2s;
        }
        .row-card:hover {
            border-color: #64748b;
            background: rgba(51, 65, 85, 0.8);
        }

        .row-handle {
            position: absolute;
            left: -25px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            cursor: grab;
            padding: 5px;
        }

        .key-card {
            flex: 1;
            min-width: 120px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            position: relative;
            cursor: grab;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .key-card:hover {
            border-color: var(--accent);
        }

        .key-code {
            font-family: monospace;
            font-size: 13px;
            color: var(--accent);
            text-align: right;
            direction: ltr;
        }
        .key-label {
            font-size: 11px;
            color: #94a3b8;
        }

        .key-actions {
            position: absolute;
            top: 5px;
            left: 5px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: 0.2s;
        }
        .key-card:hover .key-actions {
            opacity: 1;
        }

        .action-icon {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            background: rgba(255,255,255,0.1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            cursor: pointer;
        }
        .action-icon:hover { background: var(--accent); }
        .action-icon.del:hover { background: #ef4444; }

        /* دکمه‌های عمومی */
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-outline {
            border: 1px solid var(--border);
            color: #cbd5e1;
        }
        .btn-outline:hover {
            background: #334155;
            color: white;
        }
        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-add-row {
            width: 100%;
            padding: 15px;
            border: 2px dashed var(--border);
            color: #94a3b8;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-add-row:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(59, 130, 246, 0.05);
        }

        /* ریسپانسیو */
        @media (max-width: 1024px) {
            .preview-pane { display: none; }
        }
    </style>
</head>
<body>

    <!-- هدر -->
    <div class="header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shadow">
                <i class="fa-solid fa-keyboard text-white text-sm"></i>
            </div>
            <h1 class="text-white font-bold text-lg">ویرایشگر کیبورد</h1>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="btn btn-outline">
                <i class="fa-solid fa-arrow-right"></i> بازگشت
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('همه چیز ریست شود؟')" class="btn btn-outline text-red-400 border-red-900/50 hover:bg-red-900/20">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <button onclick="saveKeyboard()" id="btn-save" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> ذخیره تغییرات
            </button>
        </div>
    </div>

    <!-- محیط اصلی -->
    <div class="main-layout">
        
        <!-- پیش‌نمایش -->
        <div class="preview-pane">
            <div class="mobile-frame animate__animated animate__fadeInUp">
                <!-- هدر تلگرام -->
                <div class="telegram-header">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1">
                        <div class="font-bold text-sm">Mirza Bot</div>
                        <div class="text-xs text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>
                <!-- بدنه چت -->
                <div class="telegram-chat">
                    <div class="bg-[#2b5278] text-white text-sm px-3 py-2 rounded-lg rounded-tl-none mx-3 mb-2 shadow max-w-[80%]">
                        منوی ربات به صورت زیر نمایش داده می‌شود 👇
                    </div>
                </div>
                <!-- کیبورد -->
                <div id="preview-render" class="telegram-keyboard flex flex-col justify-end">
                    <!-- دکمه‌ها اینجا ساخته می‌شوند -->
                </div>
            </div>
        </div>

        <!-- ویرایشگر -->
        <div class="editor-pane">
            <div class="editor-content">
                <div id="editor-render" class="max-w-4xl mx-auto pb-8">
                    <!-- سطرها اینجا ساخته می‌شوند -->
                </div>
                <div class="max-w-4xl mx-auto pb-20">
                    <button onclick="addRow()" class="btn-add-row">
                        <i class="fa-solid fa-plus-circle text-xl"></i> افزودن سطر جدید
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        // ترجمه دکمه‌ها برای نمایش زیبا در پیش‌نمایش
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

        // دریافت داده‌ها از PHP با امنیت بالا
        let keyboardData = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardData)) keyboardData = [];

        const editorEl = document.getElementById('editor-render');
        const previewEl = document.getElementById('preview-render');
        const saveBtn = document.getElementById('btn-save');

        // تنظیمات آلرت
        const SwalDark = Swal.mixin({
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
        });

        // تابع اصلی رندر
        function render() {
            renderEditor();
            renderPreview();
        }

        // رندر ویرایشگر (راست)
        function renderEditor() {
            editorEl.innerHTML = '';
            
            if (keyboardData.length === 0) {
                editorEl.innerHTML = `
                    <div class="text-center py-10 opacity-50">
                        <i class="fa-solid fa-keyboard text-4xl mb-2"></i>
                        <p>هیچ دکمه‌ای وجود ندارد</p>
                    </div>`;
            }

            keyboardData.forEach((row, rIdx) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row-card animate__animated animate__fadeIn';
                
                // هندل درگ
                rowDiv.innerHTML += `<div class="row-handle"><i class="fa-solid fa-grip-vertical"></i></div>`;

                row.forEach((btn, bIdx) => {
                    const label = translations[btn.text] || 'دکمه سفارشی';
                    const keyDiv = document.createElement('div');
                    keyDiv.className = 'key-card';
                    keyDiv.innerHTML = `
                        <div class="key-code" title="${btn.text}">${btn.text}</div>
                        <div class="key-label">${label}</div>
                        <div class="key-actions">
                            <div class="action-icon" onclick="editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="action-icon del" onclick="deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                    `;
                    rowDiv.appendChild(keyDiv);
                });

                // دکمه افزودن آیتم در سطر
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'w-[40px] border border-dashed border-gray-600 rounded flex items-center justify-center cursor-pointer hover:border-blue-500 hover:text-blue-500 text-gray-500 transition';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                    addBtn.onclick = () => addKeyToRow(rIdx);
                    rowDiv.appendChild(addBtn);
                }

                // دکمه حذف سطر خالی
                if (row.length === 0) {
                    const delRow = document.createElement('div');
                    delRow.className = 'w-full text-center text-xs text-red-400 cursor-pointer border border-dashed border-red-500/30 p-2 rounded hover:bg-red-500/10';
                    delRow.innerHTML = 'حذف سطر خالی';
                    delRow.onclick = () => deleteRow(rIdx);
                    rowDiv.appendChild(delRow);
                }

                editorEl.appendChild(rowDiv);
            });

            initSortable();
        }

        // رندر پیش‌نمایش (چپ)
        function renderPreview() {
            previewEl.innerHTML = '';
            keyboardData.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex w-full';
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
                animation: 200, handle: '.row-handle', ghostClass: 'opacity-50',
                onEnd: (evt) => {
                    const item = keyboardData.splice(evt.oldIndex, 1)[0];
                    keyboardData.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // جابجایی دکمه‌ها
            document.querySelectorAll('.row-card').forEach(el => {
                new Sortable(el, {
                    group: 'shared', animation: 200, draggable: '.key-card', ghostClass: 'opacity-50',
                    onEnd: () => rebuildData() // بازسازی داده‌ها بعد از دراپ
                });
            });
        }

        // بازسازی آرایه از روی DOM (برای وقتی دکمه بین سطرها جابجا می‌شود)
        function rebuildData() {
            const newData = [];
            const rows = editorEl.querySelectorAll('.row-card');
            rows.forEach(row => {
                const btns = [];
                row.querySelectorAll('.key-code').forEach(el => {
                    btns.push({ text: el.innerText });
                });
                // اگر سطر دکمه دارد یا دکمه افزودن دارد (یعنی وجود دارد)
                if (btns.length > 0 || row.querySelector('.fa-plus')) {
                    newData.push(btns);
                }
            });
            keyboardData = newData;
            render();
        }

        // --- اکشن‌ها ---

        function addRow() {
            keyboardData.push([{text: 'text_new'}]);
            render();
            // اسکرول به پایین
            setTimeout(() => document.querySelector('.editor-content').scrollTop = 9999, 50);
        }

        function deleteRow(idx) {
            keyboardData.splice(idx, 1);
            render();
        }

        async function addKeyToRow(rIdx) {
            const { value: text } = await SwalDark.fire({
                title: 'نام متغیر دکمه',
                input: 'text',
                inputValue: 'text_new',
                showCancelButton: true,
                confirmButtonText: 'افزودن'
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
            const { value: text } = await SwalDark.fire({
                title: 'ویرایش کد دکمه',
                input: 'text',
                inputValue: current,
                showCancelButton: true,
                confirmButtonText: 'ذخیره'
            });
            if (text) {
                keyboardData[rIdx][bIdx].text = text;
                render();
            }
        }

        function saveKeyboard() {
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';
            saveBtn.disabled = true;

            fetch('keyboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(keyboardData)
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                if(data.status === 'success') {
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, 
                        timer: 3000, background: '#1e293b', color: '#fff'
                    });
                    Toast.fire({icon: 'success', title: 'با موفقیت ذخیره شد'});
                }
            })
            .catch(err => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                SwalDark.fire({icon: 'error', title: 'خطا در ارتباط'});
            });
        }

        // شروع برنامه
        render();

    </script>
</body>
</html>