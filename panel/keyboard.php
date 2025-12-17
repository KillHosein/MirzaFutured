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

// کوئری‌های جانبی (جهت اطمینان از عدم ارور در هدرهای احتمالی)
$query = $pdo->prepare("SELECT * FROM invoice");
$query->execute();
$listinvoice = $query->fetchAll();

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// --- منطق ذخیره‌سازی ---
$inputJSON = file_get_contents("php://input");
$inputData = json_decode($inputJSON, true);
$method = $_SERVER['REQUEST_METHOD'];

if($method == "POST" && is_array($inputData)){
    $keyboardStruct = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStruct), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// --- منطق ریست ---
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

// --- دریافت اطلاعات ---
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
    <title>مدیریت کیبورد | ربات میرزا</title>
    
    <!-- کتابخانه‌های ضروری -->
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
            --primary: #3b82f6;
            --text-light: #f8fafc;
            --border-col: #334155;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* هدر */
        .top-nav {
            height: 64px;
            background: rgba(30, 41, 59, 0.95);
            border-bottom: 1px solid var(--border-col);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 50;
        }

        /* ناحیه اصلی */
        .workspace-area {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* پنل چپ: پیش‌نمایش */
        .preview-sidebar {
            width: 420px;
            background: #0b1120;
            border-left: 1px solid var(--border-col);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(#1e293b 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .mobile-shell {
            width: 340px;
            height: 680px;
            background: #000;
            border-radius: 45px;
            box-shadow: 0 0 0 10px #1f2937, 0 25px 50px rgba(0,0,0,0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .tg-head {
            padding: 35px 15px 10px;
            background: #17212b;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #000;
        }

        .tg-body {
            flex: 1;
            background: #0e1621;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 10px;
        }

        .tg-keys-container {
            background: #17212b;
            padding: 6px;
            min-height: 200px;
        }

        .tg-key {
            background: #2b5278;
            color: white;
            border-radius: 6px;
            padding: 10px 4px;
            font-size: 13px;
            text-align: center;
            margin: 2px;
            box-shadow: 0 1px 0 rgba(0,0,0,0.3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        /* پنل راست: ویرایشگر */
        .editor-main {
            flex: 1;
            background: var(--bg-panel);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .editor-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
        }

        /* کارت‌های ویرایش */
        .row-item {
            background: rgba(51, 65, 85, 0.4);
            border: 1px solid var(--border-col);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            position: relative;
            transition: all 0.2s;
        }
        .row-item:hover {
            border-color: #64748b;
            background: rgba(51, 65, 85, 0.7);
        }

        .row-drag {
            position: absolute;
            left: -28px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            cursor: grab;
            padding: 8px;
        }

        .btn-edit {
            flex: 1;
            min-width: 130px;
            background: #0f172a;
            border: 1px solid var(--border-col);
            border-radius: 8px;
            padding: 12px;
            position: relative;
            cursor: grab;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .btn-edit:hover { border-color: var(--primary); }

        .btn-code {
            font-family: monospace;
            font-size: 13px;
            color: var(--primary);
            text-align: right;
            direction: ltr;
        }
        .btn-desc { font-size: 11px; color: #94a3b8; }

        .btn-actions {
            position: absolute; top: 6px; left: 6px; display: flex; gap: 4px; opacity: 0; transition: 0.2s;
        }
        .btn-edit:hover .btn-actions { opacity: 1; }

        .act-icon {
            width: 22px; height: 22px; border-radius: 4px;
            background: rgba(255,255,255,0.1); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer;
        }
        .act-icon:hover { background: var(--primary); }
        .act-icon.del:hover { background: #ef4444; }

        /* دکمه‌های کنترلی */
        .btn-save-top {
            background: var(--primary);
            color: white;
            padding: 8px 24px;
            border-radius: 8px;
            font-weight: bold;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .btn-save-top:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-save-top:disabled { background: #475569; opacity: 0.7; cursor: not-allowed; transform: none; }

        .btn-add-row {
            width: 100%;
            padding: 15px;
            border: 2px dashed var(--border-col);
            border-radius: 12px;
            color: #94a3b8;
            font-weight: bold;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-add-row:hover { border-color: var(--primary); color: var(--primary); background: rgba(59, 130, 246, 0.05); }

        /* موبایل */
        @media (max-width: 1024px) {
            .preview-sidebar { display: none; }
        }
    </style>
</head>
<body>

    <!-- هدر -->
    <header class="top-nav">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-keyboard text-white text-sm"></i>
            </div>
            <h1 class="text-lg font-bold">ویرایشگر کیبورد</h1>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="px-4 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 text-sm">
                <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('آیا مطمئن هستید؟ همه چیز ریست می‌شود.')" class="px-3 py-2 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <button onclick="saveKeyboard()" id="saveBtn" class="btn-save-top" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <!-- محیط کار -->
    <div class="workspace-area">
        
        <!-- چپ: پیش‌نمایش -->
        <div class="preview-sidebar">
            <div class="mobile-shell animate__animated animate__fadeInLeft">
                <div class="tg-head">
                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-sm font-bold">MB</div>
                    <div class="flex-1">
                        <div class="font-bold text-sm">Mirza Bot</div>
                        <div class="text-xs text-blue-300">bot</div>
                    </div>
                </div>
                
                <div class="tg-body">
                    <div class="bg-[#2b5278] text-white text-sm px-3 py-2 rounded-lg rounded-tl-none mx-3 mb-2 max-w-[85%]">
                        منوی ربات به این صورت خواهد بود 👇
                    </div>
                </div>

                <div id="preview-container" class="tg-keys-container flex flex-col justify-end">
                    <!-- دکمه‌ها اینجا ساخته می‌شوند -->
                </div>
            </div>
        </div>

        <!-- راست: ادیتور -->
        <div class="editor-main">
            <div class="editor-scroll">
                <div id="editor-container" class="max-w-4xl mx-auto pb-8">
                    <!-- سطرها اینجا ساخته می‌شوند -->
                </div>
                
                <div class="max-w-4xl mx-auto pb-24">
                    <div onclick="addRow()" class="btn-add-row">
                        <i class="fa-solid fa-plus text-lg"></i>
                        افزودن سطر جدید
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // دیکشنری ترجمه
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

        // داده‌های اولیه
        let keyboardRows = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardRows)) keyboardRows = [];
        
        // ذخیره وضعیت اولیه برای تشخیص تغییر
        let initialSnapshot = JSON.stringify(keyboardRows);

        const editorEl = document.getElementById('editor-container');
        const previewEl = document.getElementById('preview-container');
        const saveBtn = document.getElementById('saveBtn');

        // SweetAlert
        const SwalDark = Swal.mixin({
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
        });

        function render() {
            renderEditor();
            renderPreview();
            checkDirty();
        }

        // --- ویرایشگر ---
        function renderEditor() {
            editorEl.innerHTML = '';
            
            if (keyboardRows.length === 0) {
                editorEl.innerHTML = `
                    <div class="text-center py-12 opacity-40">
                        <i class="fa-solid fa-keyboard text-5xl mb-4"></i>
                        <p>کیبورد خالی است</p>
                    </div>`;
            }

            keyboardRows.forEach((row, rIdx) => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'row-item animate__animated animate__fadeIn';
                
                // هندل درگ
                rowDiv.innerHTML += `<div class="row-drag"><i class="fa-solid fa-grip-vertical"></i></div>`;

                row.forEach((btn, bIdx) => {
                    const label = translations[btn.text] || 'دکمه سفارشی';
                    const keyDiv = document.createElement('div');
                    keyDiv.className = 'btn-edit';
                    keyDiv.innerHTML = `
                        <div class="btn-code" title="${btn.text}">${btn.text}</div>
                        <div class="btn-desc">${label}</div>
                        <div class="btn-actions">
                            <div class="act-icon" onclick="editKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-pen"></i></div>
                            <div class="act-icon del" onclick="deleteKey(${rIdx}, ${bIdx})"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                    `;
                    rowDiv.appendChild(keyDiv);
                });

                // دکمه افزودن آیتم
                if (row.length < 8) {
                    const addBtn = document.createElement('div');
                    addBtn.className = 'w-[45px] border border-dashed border-slate-600 rounded-lg flex items-center justify-center cursor-pointer hover:border-blue-500 hover:text-blue-500 text-slate-500 transition';
                    addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                    addBtn.onclick = () => addKeyToRow(rIdx);
                    rowDiv.appendChild(addBtn);
                }

                // حذف سطر خالی
                if (row.length === 0) {
                    const delRow = document.createElement('div');
                    delRow.className = 'w-full text-center text-red-400 text-xs py-2 cursor-pointer border border-dashed border-red-500/30 rounded hover:bg-red-500/10';
                    delRow.innerText = 'حذف سطر خالی';
                    delRow.onclick = () => deleteRow(rIdx);
                    rowDiv.appendChild(delRow);
                }

                editorEl.appendChild(rowDiv);
            });

            initSortable();
        }

        // --- پیش‌نمایش ---
        function renderPreview() {
            previewEl.innerHTML = '';
            keyboardRows.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex w-full gap-1 mb-1';
                
                row.forEach(btn => {
                    const btnDiv = document.createElement('div');
                    btnDiv.className = 'tg-key';
                    // نمایش ترجمه در پیش‌نمایش
                    btnDiv.innerText = translations[btn.text] || btn.text; 
                    rowDiv.appendChild(btnDiv);
                });
                
                if(row.length > 0) previewEl.appendChild(rowDiv);
            });
        }

        // --- دراگ و دراپ ---
        function initSortable() {
            // سطرها
            new Sortable(editorEl, {
                animation: 200, handle: '.row-drag', ghostClass: 'opacity-50',
                onEnd: (evt) => {
                    const item = keyboardRows.splice(evt.oldIndex, 1)[0];
                    keyboardRows.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // دکمه‌ها
            document.querySelectorAll('.row-item').forEach(el => {
                new Sortable(el, {
                    group: 'shared', animation: 200, draggable: '.btn-edit', ghostClass: 'opacity-50',
                    onEnd: () => rebuildData()
                });
            });
        }

        function rebuildData() {
            const newRows = [];
            const domRows = editorEl.querySelectorAll('.row-item');
            
            domRows.forEach(row => {
                const btns = [];
                row.querySelectorAll('.btn-code').forEach(el => {
                    btns.push({ text: el.innerText });
                });
                // اگر سطر دارای دکمه یا دکمه افزودن است
                if (btns.length > 0 || row.querySelector('.fa-plus')) {
                    newRows.push(btns);
                }
            });
            
            keyboardRows = newRows;
            render();
        }

        // --- اکشن‌ها ---
        function checkDirty() {
            const current = JSON.stringify(keyboardRows);
            if (current !== initialSnapshot) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> ذخیره تغییرات';
                saveBtn.classList.add('animate-pulse');
            } else {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> ذخیره شد';
                saveBtn.classList.remove('animate-pulse');
            }
        }

        function addRow() {
            keyboardRows.push([{text: 'text_new'}]);
            render();
            setTimeout(() => document.querySelector('.editor-scroll').scrollTop = 99999, 50);
        }

        function deleteRow(idx) {
            keyboardRows.splice(idx, 1);
            render();
        }

        async function addKeyToRow(rIdx) {
            const { value: text } = await SwalDark.fire({
                title: 'افزودن دکمه',
                input: 'text',
                inputValue: 'text_new',
                inputLabel: 'نام متغیر (انگلیسی)',
                showCancelButton: true,
                confirmButtonText: 'افزودن'
            });
            if (text) {
                keyboardRows[rIdx].push({text});
                render();
            }
        }

        function deleteKey(rIdx, bIdx) {
            keyboardRows[rIdx].splice(bIdx, 1);
            render();
        }

        async function editKey(rIdx, bIdx) {
            const current = keyboardRows[rIdx][bIdx].text;
            const { value: text } = await SwalDark.fire({
                title: 'ویرایش کد دکمه',
                input: 'text',
                inputValue: current,
                showCancelButton: true,
                confirmButtonText: 'ذخیره'
            });
            if (text) {
                keyboardRows[rIdx][bIdx].text = text;
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
                body: JSON.stringify(keyboardRows)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    initialSnapshot = JSON.stringify(keyboardRows);
                    checkDirty();
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, 
                        timer: 3000, background: '#1e293b', color: '#fff'
                    });
                    Toast.fire({icon: 'success', title: 'ذخیره شد'});
                }
            })
            .catch(err => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                SwalDark.fire({icon: 'error', title: 'خطا در ارتباط'});
            });
        }

        // شروع
        render();

    </script>
</body>
</html>