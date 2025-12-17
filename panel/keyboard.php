<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';

// --- Authentication Check ---
$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    exit;
}

// --- PHP Logic: Handle Save & Reset ---
$inputData = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];

// Save Logic
if($method == "POST" && is_array($inputData)){
    // Wrap in standard Telegram keyboard structure
    $keyboardStruct = ['keyboard' => $inputData];
    update("setting", "keyboardmain", json_encode($keyboardStruct), null, null);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'timestamp' => time()]);
    exit;
}

// Reset Logic
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

// --- Fetch Current Data ---
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
         // Default fallback if DB is empty
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
} catch (Exception $e) { $currentKeyboardJSON = '[]'; }
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>مدیریت کیبورد ربات | پنل حرفه‌ای</title>
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Color Palette --- */
        :root {
            --bg-app: #0f1115;
            --bg-panel: #161b22;
            --bg-card: #21262d;
            --border: #30363d;
            --accent: #58a6ff;
            --accent-hover: #79c0ff;
            --danger: #f85149;
            --text-main: #c9d1d9;
            --text-muted: #8b949e;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: var(--bg-app);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* --- Scrollbar --- */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        /* --- Header --- */
        .app-header {
            height: 64px;
            background: rgba(22, 27, 34, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 50;
        }

        .btn-nav {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-nav:hover {
            background: var(--border);
            border-color: var(--text-muted);
            color: white;
        }
        .btn-nav.danger {
            color: var(--danger);
            border-color: rgba(248, 81, 73, 0.3);
        }
        .btn-nav.danger:hover {
            background: rgba(248, 81, 73, 0.1);
        }

        .btn-save {
            background: #238636; /* GitHub Green */
            color: white;
            border: 1px solid rgba(240, 246, 252, 0.1);
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 700;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-save:hover { background: #2ea043; transform: translateY(-1px); }
        .btn-save:active { transform: translateY(0); }
        .btn-save:disabled { background: var(--bg-card); color: var(--text-muted); cursor: not-allowed; }
        
        .btn-save.dirty {
            position: relative;
        }
        .btn-save.dirty::after {
            content: ''; position: absolute; top: -4px; right: -4px;
            width: 10px; height: 10px; background: var(--accent);
            border-radius: 50%; border: 2px solid var(--bg-app);
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0% { transform: scale(0.95); opacity: 1; } 50% { transform: scale(1.1); opacity: 0.7; } 100% { transform: scale(0.95); opacity: 1; } }

        /* --- Layout --- */
        .workspace {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* --- Preview Pane (Left) --- */
        .preview-pane {
            width: 420px;
            background: #010409;
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            background-image: radial-gradient(#161b22 1px, transparent 1px);
            background-size: 20px 20px;
        }
        
        .device-frame {
            width: 350px;
            height: 700px;
            background: #000;
            border-radius: 40px;
            box-shadow: 0 0 0 8px #21262d, 0 20px 50px -10px rgba(0,0,0,0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .device-notch {
            position: absolute; top: 0; left: 50%; transform: translateX(-50%);
            width: 120px; height: 26px; background: #21262d;
            border-bottom-left-radius: 14px; border-bottom-right-radius: 14px; z-index: 10;
        }

        .tg-header {
            background: #17212b;
            padding: 35px 16px 10px;
            display: flex; align-items: center; gap: 12px;
            color: white; border-bottom: 1px solid #000;
        }
        
        .tg-bg {
            flex: 1; background: #0e1621;
            background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h21.5v21.5h-1.5z' fill='%23182533' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
            display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 8px;
        }

        .tg-keyboard-area {
            background: #17212b; padding: 6px; min-height: 220px;
        }

        .tg-btn {
            background: #2b5278; color: white;
            border-radius: 5px; margin: 3px;
            padding: 10px 4px; font-size: 13px; text-align: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.3);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* --- Editor Pane (Right) --- */
        .editor-pane {
            flex: 1;
            background: var(--bg-app);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .editor-content {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
        }

        .row-card {
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            position: relative;
            transition: all 0.2s;
        }
        .row-card:hover { border-color: var(--text-muted); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

        .handle-row {
            position: absolute; left: -24px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); cursor: grab; padding: 8px;
            opacity: 0; transition: 0.2s;
        }
        .row-card:hover .handle-row { opacity: 1; }

        .key-item {
            flex: 1; min-width: 150px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            position: relative;
            cursor: grab;
            transition: all 0.2s;
        }
        .key-item:hover {
            border-color: var(--accent);
            background: #262c36;
        }

        .key-text-main {
            font-family: monospace; font-size: 14px; color: var(--accent);
            direction: ltr; text-align: right;
            margin-bottom: 4px;
        }
        .key-text-sub {
            font-size: 11px; color: var(--text-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .key-controls {
            position: absolute; top: 6px; left: 6px;
            display: flex; gap: 4px; opacity: 0; transition: 0.2s;
        }
        .key-item:hover .key-controls { opacity: 1; }

        .ctrl-btn {
            width: 24px; height: 24px; border-radius: 4px;
            background: rgba(0,0,0,0.4); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; cursor: pointer;
        }
        .ctrl-btn:hover { background: var(--accent); }
        .ctrl-btn.del:hover { background: var(--danger); }

        /* Add Buttons */
        .add-row-big {
            width: 100%; padding: 20px;
            border: 2px dashed var(--border);
            border-radius: 12px;
            color: var(--text-muted); font-weight: 600;
            cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .add-row-big:hover {
            border-color: var(--accent); color: var(--accent);
            background: rgba(88, 166, 255, 0.05);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .preview-pane { display: none; }
            .editor-content { padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <header class="app-header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-keyboard text-white text-sm"></i>
            </div>
            <div>
                <h1 class="font-bold text-base tracking-tight text-white">Keyboard Editor</h1>
                <p class="text-[10px] text-gray-500 font-mono tracking-wide uppercase">Professional Mode</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-nav">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:inline">بازگشت</span>
            </a>
            <a href="keyboard.php?action=reaset" onclick="return confirm('آیا از بازنشانی به تنظیمات کارخانه اطمینان دارید؟')" class="btn-nav danger">
                <i class="fa-solid fa-rotate-right"></i>
            </a>
            <div class="h-6 w-px bg-gray-700 mx-1"></div>
            <button onclick="saveKeyboard()" id="btn-save" class="btn-save" disabled>
                <i class="fa-solid fa-floppy-disk"></i>
                <span>ذخیره تغییرات</span>
            </button>
        </div>
    </header>

    <div class="workspace">
        <!-- Preview -->
        <div class="preview-pane">
            <div class="absolute top-6 left-6 text-[10px] font-bold text-gray-600 uppercase tracking-widest">Live Preview</div>
            
            <div class="device-frame animate__animated animate__fadeInLeft">
                <div class="device-notch"></div>
                <div class="tg-header">
                    <i class="fa-solid fa-arrow-right text-gray-500"></i>
                    <div class="flex-1">
                        <div class="font-bold text-sm">Mirza Bot</div>
                        <div class="text-xs text-blue-400">bot</div>
                    </div>
                    <i class="fa-solid fa-ellipsis-vertical text-gray-500"></i>
                </div>
                <div class="tg-bg">
                    <div class="bg-[#2b5278] text-white text-sm px-3 py-2 rounded-lg rounded-tl-none ml-3 mb-3 max-w-[80%] shadow">
                        سلام! منوی ربات آپدیت شد. 👇
                    </div>
                </div>
                <div id="preview-render" class="tg-keyboard-area flex flex-col justify-end"></div>
            </div>
        </div>

        <!-- Editor -->
        <div class="editor-pane">
            <div class="editor-content">
                <div id="editor-render" class="max-w-4xl mx-auto pb-8"></div>
                
                <div class="max-w-4xl mx-auto pb-20">
                    <div onclick="addRow()" class="add-row-big group">
                        <span class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                            <i class="fa-solid fa-plus"></i>
                        </span>
                        <span>افزودن سطر جدید</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logic -->
    <script>
        // دیکشنری ترجمه کدهای ربات به متن فارسی قابل فهم
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

        let keyboardData = <?php echo $currentKeyboardJSON ?: '[]'; ?>;
        if (!Array.isArray(keyboardData)) keyboardData = [];
        
        // Snapshot for dirty check
        let initialSnapshot = JSON.stringify(keyboardData);

        const editorContainer = document.getElementById('editor-render');
        const previewContainer = document.getElementById('preview-render');
        const saveBtn = document.getElementById('btn-save');

        // Config SweetAlert
        const SwalDark = Swal.mixin({
            background: '#161b22',
            color: '#c9d1d9',
            confirmButtonColor: '#238636',
            cancelButtonColor: '#da3633',
            showClass: { popup: 'animate__animated animate__fadeInDown' },
            hideClass: { popup: 'animate__animated animate__fadeOutUp' }
        });

        function render() {
            renderEditor();
            renderPreview();
            checkChanges();
        }

        // --- Render Editor ---
        function renderEditor() {
            editorContainer.innerHTML = '';
            
            if (keyboardData.length === 0) {
                editorContainer.innerHTML = `
                    <div class="text-center py-10 opacity-30">
                        <i class="fa-solid fa-keyboard text-5xl mb-4"></i>
                        <p>کیبورد خالی است</p>
                    </div>`;
            }

            keyboardData.forEach((row, rIndex) => {
                const rowEl = document.createElement('div');
                rowEl.className = 'row-card animate__animated animate__fadeIn';
                
                // Handle
                rowEl.innerHTML += `<div class="handle-row"><i class="fa-solid fa-grip-vertical"></i></div>`;

                row.forEach((btn, bIndex) => {
                    const desc = translations[btn.text] || 'دکمه سفارشی';
                    const keyEl = document.createElement('div');
                    keyEl.className = 'key-item';
                    keyEl.innerHTML = `
                        <div class="key-text-main" title="${btn.text}">${btn.text}</div>
                        <div class="key-text-sub">${desc}</div>
                        <div class="key-controls">
                            <div class="ctrl-btn" onclick="editKey(${rIndex}, ${bIndex})"><i class="fa-solid fa-pen"></i></div>
                            <div class="ctrl-btn del" onclick="deleteKey(${rIndex}, ${bIndex})"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                    `;
                    rowEl.appendChild(keyEl);
                });

                // Add Key Button
                if (row.length < 8) {
                    const addKey = document.createElement('div');
                    addKey.className = 'w-[40px] border border-dashed border-gray-700 rounded-lg flex items-center justify-center cursor-pointer hover:border-blue-500 hover:text-blue-500 transition text-gray-600';
                    addKey.innerHTML = '<i class="fa-solid fa-plus text-xs"></i>';
                    addKey.onclick = () => addKeyToRow(rIndex);
                    rowEl.appendChild(addKey);
                }

                // Delete Row if empty
                if (row.length === 0) {
                    const delRow = document.createElement('div');
                    delRow.className = 'flex-1 text-center py-2 text-xs text-red-400 border border-dashed border-red-900/40 rounded cursor-pointer hover:bg-red-900/10';
                    delRow.innerHTML = 'حذف سطر خالی';
                    delRow.onclick = () => deleteRow(rIndex);
                    rowEl.appendChild(delRow);
                }

                editorContainer.appendChild(rowEl);
            });

            initSortable();
        }

        // --- Render Preview ---
        function renderPreview() {
            previewContainer.innerHTML = '';
            keyboardData.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex w-full';
                row.forEach(btn => {
                    const btnDiv = document.createElement('div');
                    btnDiv.className = 'tg-btn flex-1 truncate';
                    // در پیش‌نمایش هم نام فنی را می‌گذاریم تا دقیق باشد، یا می‌توانید ترجمه را بگذارید
                    // طبق درخواست شما، متن دکمه (نام متغیر) مهم است
                    btnDiv.innerText = btn.text; 
                    rowDiv.appendChild(btnDiv);
                });
                if(row.length > 0) previewContainer.appendChild(rowDiv);
            });
        }

        // --- Sortable JS ---
        function initSortable() {
            // Row Sorting
            new Sortable(editorContainer, {
                animation: 200,
                handle: '.handle-row',
                ghostClass: 'opacity-50',
                onEnd: function(evt) {
                    const item = keyboardData.splice(evt.oldIndex, 1)[0];
                    keyboardData.splice(evt.newIndex, 0, item);
                    render();
                }
            });

            // Button Sorting
            document.querySelectorAll('.row-card').forEach((el, rIdx) => {
                new Sortable(el, {
                    group: 'shared',
                    animation: 200,
                    draggable: '.key-item',
                    ghostClass: 'opacity-50',
                    onEnd: function(evt) {
                        rebuildData();
                    }
                });
            });
        }

        function rebuildData() {
            const newStruct = [];
            const rows = editorContainer.querySelectorAll('.row-card');
            rows.forEach(row => {
                const btns = [];
                row.querySelectorAll('.key-text-main').forEach(k => {
                    btns.push({ text: k.innerText });
                });
                // نگه داشتن سطر اگر دکمه دارد یا دکمه افزودن دارد (یعنی سطر وجود دارد)
                // اینجا فرض می‌کنیم اگر دکمه‌ای نیست اما المان سطر هست، یعنی آرایه خالی
                // اما منطق ساده‌تر: بازسازی کامل
                if (btns.length > 0 || row.querySelector('.fa-plus')) {
                   newStruct.push(btns);
                }
            });
            keyboardData = newStruct;
            render();
        }

        // --- Actions ---
        function checkChanges() {
            const current = JSON.stringify(keyboardData);
            if (current !== initialSnapshot) {
                saveBtn.disabled = false;
                saveBtn.classList.add('dirty');
                saveBtn.querySelector('span').innerText = 'ذخیره تغییرات *';
            } else {
                saveBtn.disabled = true;
                saveBtn.classList.remove('dirty');
                saveBtn.querySelector('span').innerText = 'ذخیره تغییرات';
            }
        }

        function addRow() {
            keyboardData.push([{text: 'text_new'}]);
            render();
            // Scroll to bottom
            setTimeout(() => {
                document.querySelector('.editor-content').scrollTop = 99999;
            }, 50);
        }

        function deleteRow(idx) {
            keyboardData.splice(idx, 1);
            render();
        }

        async function addKeyToRow(rIdx) {
            const { value: text } = await SwalDark.fire({
                title: 'افزودن دکمه',
                input: 'text',
                inputValue: 'text_new',
                inputPlaceholder: 'مثال: text_sell',
                showCancelButton: true,
                confirmButtonText: 'افزودن'
            });
            if(text) {
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
            if(text) {
                keyboardData[rIdx][bIdx].text = text;
                render();
            }
        }

        function saveKeyboard() {
            const originalBtn = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال ذخیره...';
            saveBtn.disabled = true;

            fetch('keyboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(keyboardData)
            })
            .then(r => r.json())
            .then(data => {
                saveBtn.innerHTML = originalBtn;
                initialSnapshot = JSON.stringify(keyboardData);
                checkChanges();
                
                const Toast = Swal.mixin({
                    toast: true, position: 'top-end', showConfirmButton: false, 
                    timer: 3000, timerProgressBar: true,
                    background: '#161b22', color: '#fff'
                });
                Toast.fire({ icon: 'success', title: 'کیبورد با موفقیت ذخیره شد' });
            })
            .catch(err => {
                saveBtn.innerHTML = originalBtn;
                saveBtn.disabled = false;
                SwalDark.fire({icon: 'error', title: 'خطا در ارتباط'});
            });
        }

        // Init
        render();

    </script>
</body>
</html>