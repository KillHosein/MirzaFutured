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

// کوئری فاکتورها (جهت سازگاری با سیستم موجود)
$query = $pdo->prepare("SELECT * FROM invoice");
$query->execute();
$listinvoice = $query->fetchAll();

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// --- مدیریت درخواست‌ها (API) ---
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

// ذخیره‌سازی
if($method == "POST" && !empty($inputData)){
    $keyboardStruct = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStruct), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// ریست پیش‌فرض
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

// --- دریافت اطلاعات فعلی ---
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
         // فال‌بک
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
    <title>مدیریت کیبورد | پنل فوق حرفه‌ای</title>
    
    <!-- کتابخانه‌ها -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Obsidian Theme --- */
        :root {
            --bg-deep: #050505;
            --bg-surface: #0a0a0a;
            --bg-card: #141414;
            --bg-hover: #1f1f1f;
            --border: #262626;
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.15);
            --text-main: #e5e5e5;
            --text-sub: #a3a3a3;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
            /* Subtle Pattern */
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(236, 72, 153, 0.03) 0px, transparent 50%);
        }

        /* --- Scrollbar --- */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }

        /* --- Header --- */
        .glass-header {
            height: 64px;
            background: rgba(10, 10, 10, 0.7);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 50;
        }

        .nav-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-sub);
            border: 1px solid var(--border);
            background: var(--bg-card);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; gap: 8px;
        }
        .nav-btn:hover {
            color: var(--text-main);
            border-color: #404040;
            background: var(--bg-hover);
            transform: translateY(-1px);
        }
        .nav-btn.danger:hover {
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.05);
        }

        .save-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            box-shadow: 0 0 20px var(--accent-glow);
            transition: all 0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .save-btn:hover { background: #2563eb; transform: translateY(-1px); box-shadow: 0 0 30px rgba(59, 130, 246, 0.3); }
        .save-btn:disabled { background: #262626; color: #525252; box-shadow: none; cursor: not-allowed; transform: none; }

        /* --- Layout --- */
        .main-stage {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* --- Preview (Left) --- */
        .preview-col {
            width: 440px;
            background: var(--bg-surface);
            border-left: 1px solid var(--border);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            position: relative;
            background-image: radial-gradient(#1f1f1f 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .phone-mockup {
            width: 360px; height: 720px;
            background: #000;
            border-radius: 50px;
            box-shadow: 
                0 0 0 10px #1a1a1a, /* Inner Bezel */
                0 0 0 12px #333,    /* Outer Bezel */
                0 40px 100px -20px rgba(0,0,0,0.8);
            overflow: hidden;
            display: flex; flex-direction: column;
            position: relative;
            transform: scale(0.9);
        }

        .dynamic-island {
            position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
            width: 120px; height: 35px; background: #000; border-radius: 100px; z-index: 20;
        }

        .tg-top-bar {
            padding: 50px 20px 15px; background: #1c1c1e;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid #000; color: white;
        }

        .tg-bg-pattern {
            flex: 1; background: #0f0f0f;
            /* Dark Telegram Pattern */
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%231a1a1a' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 10px;
        }

        .tg-msg-bubble {
            background: #2b5278; color: white; padding: 10px 14px;
            border-radius: 16px; border-top-left-radius: 4px;
            max-width: 85%; margin: 0 15px 10px; font-size: 14px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .tg-kb-area {
            background: #1c1c1e; padding: 6px; min-height: 220px;
            border-top: 1px solid #000;
        }

        .tg-key {
            background: linear-gradient(180deg, #323234 0%, #28282a 100%);
            color: #fff; border-radius: 5px;
            padding: 12px 4px; font-size: 13px; text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.5);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin: 2px; border-top: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; justify-content: center;
        }

        /* --- Editor (Right) --- */
        .editor-col {
            flex: 1; display: flex; flex-direction: column;
            background: transparent; position: relative;
        }

        .editor-scroll {
            flex: 1; overflow-y: auto; padding: 40px;
        }

        .row-block {
            background: rgba(20, 20, 20, 0.6);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 12px; margin-bottom: 16px;
            display: flex; flex-wrap: wrap; gap: 10px;
            position: relative; transition: all 0.2s ease;
        }
        .row-block:hover {
            border-color: #404040; background: rgba(20, 20, 20, 0.9);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .drag-handle {
            position: absolute; left: -28px; top: 50%; transform: translateY(-50%);
            color: var(--text-sub); cursor: grab; padding: 8px; opacity: 0; transition: 0.2s;
        }
        .row-block:hover .drag-handle { opacity: 1; left: -32px; }

        .btn-card {
            flex: 1; min-width: 140px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            position: relative; cursor: grab;
            display: flex; flex-direction: column; justify-content: center;
            transition: all 0.2s;
        }
        .btn-card:hover {
            border-color: var(--accent); background: #1a1a1a;
        }
        .btn-card:active { cursor: grabbing; transform: scale(0.98); }

        .code-txt {
            font-family: 'Fira Code', monospace; font-size: 13px; color: var(--accent);
            text-align: right; direction: ltr; margin-bottom: 2px;
        }
        .label-txt {
            font-size: 11px; color: var(--text-sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .card-tools {
            position: absolute; top: 6px; left: 6px; display: flex; gap: 4px;
            opacity: 0; transition: 0.2s;
        }
        .btn-card:hover .card-tools { opacity: 1; }

        .tool-icon {
            width: 22px; height: 22px; border-radius: 6px;
            background: rgba(255,255,255,0.05); color: var(--text-main);
            display: flex; align-items: center; justify-content: center; font-size: 10px;
            cursor: pointer; backdrop-filter: blur(4px);
        }
        .tool-icon:hover { background: var(--accent); color: white; }
        .tool-icon.del:hover { background: #ef4444; }

        /* New Row Button */
        .new-row-btn {
            width: 100%; padding: 16px;
            border: 1px dashed #404040; border-radius: 12px;
            color: var(--text-sub); font-size: 14px; font-weight: 500;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            cursor: pointer; transition: 0.2s; margin-top: 20px;
        }
        .new-row-btn:hover {
            border-color: var(--accent); color: var(--accent); background: rgba(59, 130, 246, 0.05);
        }

        @media (max-width: 1024px) {
            .preview-col { display: none; }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="glass-header">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-900/20">
                <i class="fa-solid fa-layer-group text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-base tracking-tight">MirzaBot <span class="text-xs text-gray-500 font-normal ml-1">Keyboard Studio</span></h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="nav-btn">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:block">بازگشت</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('آیا از بازنشانی کامل تنظیمات اطمینان دارید؟')" class="nav-btn danger">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <div class="w-px h-6 bg-white/10 mx-1"></div>
            <button onclick="saveKeyboard()" id="btn-save" class="save-btn" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- Workspace -->
    <div class="main-stage">
        
        <!-- Left: Live Preview -->
        <div class="preview-col">
            <div class="absolute top-8 left-8 text-[10px] font-bold text-gray-600 uppercase tracking-[3px]">Live Preview</div>
            
            <div class="phone-mockup animate__animated animate__fadeInLeft">
                <div class="dynamic-island"></div>
                
                <div class="tg-top-bar">
                    <i class="fa-solid fa-arrow-right text-gray-400"></i>
                    <div class="flex-1">
                        <div class="font-bold text-sm">Mirza Bot</div>
                        <div class="text-xs text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
                </div>

                <div class="tg-bg-pattern">
                    <div class="tg-msg-bubble">
                        سلام! منوی ربات با موفقیت آپدیت شد. از دکمه‌های زیر استفاده کنید 👇
                    </div>
                </div>

                <div id="preview-render" class="tg-kb-area flex flex-col justify-end">
                    <!-- Buttons will render here -->
                </div>
            </div>
        </div>

        <!-- Right: Editor -->
        <div class="editor-col">
            <div class="editor-scroll">
                <div id="editor-render" class="max-w-4xl mx-auto pb-8">
                    <!-- Rows will render here -->
                </div>
                
                <div class="max-w-4xl mx-auto pb-20">
                    <button onclick="addRow()" class="new-row-btn">
                        <i class="fa-solid fa-plus text-lg"></i>
                        افزودن سطر جدید
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Logic -->
    <script>
        // دیکشنری هوشمند: تبدیل کدهای فنی به متن فارسی برای نمایش
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

        // بارگذاری داده‌ها با هندلینگ خطا
        let keyboardData = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardData)) keyboardData = [];
        
        let initialSnapshot = JSON.stringify(keyboardData); // برای تشخیص تغییرات

        // المان‌های DOM
        const editorEl = document.getElementById('editor-render');
        const previewEl = document.getElementById('preview-render');
        const saveBtn = document.getElementById('btn-save');

        // کانفیگ SweetAlert دارک
        const SwalDark = Swal.mixin({
            background: '#141414',
            color: '#e5e5e5',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
            customClass: { popup: 'border border-[#262626] rounded-xl' }
        });

        // --- توابع اصلی ---

        function render() {
            renderEditor();
            renderPreview();
            checkChanges();
        }

        // رندر ویرایشگر (کارت‌ها)
        function renderEditor() {
            editorEl.innerHTML = '';
            
            if (keyboardData.length === 0) {
                editorEl.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-20 opacity-30 select-none">
                        <i class="fa-solid fa-keyboard text-5xl mb-4"></i>
                        <p>هیچ دکمه‌ای وجود ندارد</p>
                    </div>`;
            }

            keyboardData.forEach((row, rIdx) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row-block animate__animated animate__fadeIn';
                
                // هندل درگ سطر
                rowDiv.innerHTML += `<div class="drag-handle"><i class="fa-solid fa-grip-vertical text-lg"></i></div>`;

                row.forEach((btn, bIdx) => {
                    const label = translations[btn.text] || 'دکمه سفارشی';
                    const btnCard = document.createElement('div');
                    btnCard.className = 'btn-card';
                    btnCard.innerHTML = `
                        <div class="code-txt" title="${btn.text}">${btn.text}</div>
                        <div class="label-txt">${label}</div>
                        <div class="card-tools">
                            <div class="tool-icon" onclick="editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="tool-icon del" onclick="deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                    `;
                    rowDiv.appendChild(btnCard);
                });

                // دکمه افزودن (+) داخل سطر
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'w-[45px] border border-dashed border-[#404040] rounded-lg flex items-center justify-center cursor-pointer hover:border-blue-500 hover:text-blue-500 text-[#525252] transition';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                    addBtn.onclick = () => addKeyToRow(rIdx);
                    rowDiv.appendChild(addBtn);
                }

                // دکمه حذف سطر خالی
                if (row.length === 0) {
                    const delRow = document.createElement('div');
                    delRow.className = 'w-full text-center text-xs text-red-400 py-2 border border-dashed border-red-900/30 rounded cursor-pointer hover:bg-red-900/10 transition';
                    delRow.innerHTML = 'حذف سطر خالی';
                    delRow.onclick = () => deleteRow(rIdx);
                    rowDiv.appendChild(delRow);
                }

                editorEl.appendChild(rowDiv);
            });

            initSortable();
        }

        // رندر پیش‌نمایش (موبایل)
        function renderPreview() {
            previewEl.innerHTML = '';
            keyboardData.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex w-full gap-1 mb-1'; // فاصله استاندارد تلگرام
                
                row.forEach(btn => {
                    const btnDiv = document.createElement('div');
                    btnDiv.className = 'tg-key flex-1 truncate';
                    // نکته کلیدی: نمایش ترجمه فارسی در پیش‌نمایش
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
                animation: 200, handle: '.drag-handle', ghostClass: 'opacity-40',
                onEnd: (evt) => {
                    const item = keyboardData.splice(evt.oldIndex, 1)[0];
                    keyboardData.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // جابجایی دکمه‌ها (بین سطرها هم کار می‌کند)
            document.querySelectorAll('.row-block').forEach(el => {
                new Sortable(el, {
                    group: 'shared', animation: 200, draggable: '.btn-card', ghostClass: 'opacity-40',
                    onEnd: () => rebuildData() 
                });
            });
        }

        // بازسازی دیتا از روی DOM بعد از درگ دکمه‌ها
        function rebuildData() {
            const newData = [];
            const rows = editorEl.querySelectorAll('.row-block');
            rows.forEach(row => {
                const btns = [];
                row.querySelectorAll('.code-txt').forEach(el => {
                    btns.push({ text: el.innerText });
                });
                // اگر سطر دارای دکمه یا دکمه افزودن است (پس سطر وجود دارد)
                if (btns.length > 0 || row.querySelector('.fa-plus')) {
                    newData.push(btns);
                }
            });
            keyboardData = newData;
            render();
        }

        // --- اکشن‌ها و دکمه‌ها ---

        function checkChanges() {
            const current = JSON.stringify(keyboardData);
            if (current !== initialSnapshot) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                saveBtn.classList.add('animate-pulse'); // افکت توجه
            } else {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ذخیره شد';
                saveBtn.classList.remove('animate-pulse');
            }
        }

        function addRow() {
            keyboardData.push([{text: 'text_new'}]);
            render();
            setTimeout(() => document.querySelector('.editor-scroll').scrollTo({ top: 9999, behavior: 'smooth' }), 50);
        }

        function deleteRow(idx) {
            keyboardData.splice(idx, 1);
            render();
        }

        async function addKeyToRow(rIdx) {
            const { value: text } = await SwalDark.fire({
                title: 'افزودن دکمه جدید',
                input: 'text',
                inputValue: 'text_new',
                inputLabel: 'کد متغیر (مثال: text_sell)',
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
                confirmButtonText: 'بروزرسانی'
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
                if(data.status === 'success') {
                    initialSnapshot = JSON.stringify(keyboardData);
                    checkChanges();
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, 
                        timer: 3000, background: '#141414', color: '#fff'
                    });
                    Toast.fire({icon: 'success', title: 'تغییرات با موفقیت ذخیره شد'});
                }
            })
            .catch(err => {
                checkChanges();
                SwalDark.fire({icon: 'error', title: 'خطا در ارتباط با سرور'});
            });
        }

        // شروع اپلیکیشن
        render();

    </script>
</body>
</html>